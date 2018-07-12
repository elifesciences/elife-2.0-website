<?php

namespace Drupal\jcms_admin;

use League\CommonMark\Block\Element\IndentedCode;
use League\CommonMark\Block\Element\FencedCode;
use League\CommonMark\Block\Element\BlockQuote;
use League\CommonMark\Block\Element\ListBlock;
use League\CommonMark\Block\Element\Paragraph;
use League\CommonMark\Block\Element\HtmlBlock;
use League\CommonMark\Block\Element\Heading;
use League\CommonMark\Block\Element\Document;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\DocParser;
use League\CommonMark\ElementRendererInterface;
use League\CommonMark\Node\Node;
use PHPHtmlParser\Dom;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Convert Markdown to Json.
 */
final class MarkdownJsonSerializer implements NormalizerInterface {

  private $docParser;
  private $htmlRenderer;
  private $mimeTypeGuesser;
  private $converter;
  private $depthOffset = NULL;
  private $iiif = '';
  private $bracketChar = 'ø';

  /**
   * Constructor.
   */
  public function __construct(DocParser $docParser, ElementRendererInterface $htmlRenderer, MimeTypeGuesserInterface $mimeTypeGuesser, CommonMarkConverter $converter) {
    $this->docParser = $docParser;
    $this->htmlRenderer = $htmlRenderer;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
    $this->converter = $converter;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) : array {
    $this->iiif = $context['iiif'] ?? 'https://iiif.elifesciences.org/journal-cms:';
    return $this->convertChildren($this->docParser->parse($object), $context);
  }

  /**
   * Convert children.
   */
  private function convertChildren(Document $document, array $context = []) : array {
    $nodes = [];
    $this->resetDepthOffset();
    foreach ($document->children() as $node) {
      if ($child = $this->convertChild($node, $context)) {
        $nodes[] = $child;
      }
    }

    return $this->implementHierarchy($nodes);
  }

  /**
   * Reset depth offset.
   */
  private function resetDepthOffset() {
    $this->depthOffset = $this->setDepthOffset(NULL, TRUE);
  }

  /**
   * Set depth offset.
   */
  private function setDepthOffset($depthOffset, bool $override = FALSE) {
    if (is_null($this->depthOffset) || $override === TRUE) {
      $this->depthOffset = $depthOffset;
    }
  }

  /**
   * Retrieve depth offset.
   */
  private function getDepthOffset() {
    return $this->depthOffset;
  }

  /**
   * Convert child.
   */
  private function convertChild(Node $node, array $context = []) : array {
    $encode = $context['encode'] ?? [];

    if ($node instanceof Heading && $rendered = $this->htmlRenderer->renderBlock($node)) {
      $depthOffset = $this->getDepthOffset();
      $heading = (int) preg_replace('/^h([1-5])$/', '$1', $rendered->getTagName());
      if (is_null($depthOffset) || $heading === 1) {
        $depthOffset = 1 - $heading;
        $this->setDepthOffset($depthOffset, ($heading === 1));
      }

      // Only allow 2 levels of hierarchy.
      $depth = (($heading + $depthOffset) === 1) ? 1 : 2;

      return [
        'type' => 'section',
        'title' => $this->prepareOutput($rendered->getContents(), $context),
        'depth' => $depth,
      ];
    }
    elseif ($node instanceof HtmlBlock) {
      if ($rendered = $this->htmlRenderer->renderBlock($node)) {
        $contents = trim($rendered);
        if (preg_match('/^(<table[^>]*>)(.*)(<\/table>)/', $contents, $matches)) {
          if (in_array('table', $encode)) {
            $contents = $matches[1] . $this->prepareOutput($matches[2], $context, TRUE) . $matches[3];
          }
          return [
            'type' => 'table',
            'tables' => [$this->prepareOutput($contents, $context)],
          ];
        }
        elseif (preg_match('/^<figure.*<\/figure>/', $contents)) {
          $dom = new Dom();
          $dom->setOptions([
            'preserveLineBreaks' => TRUE,
          ]);
          $dom->load($contents);
          /** @var \PHPHtmlParser\Dom\HtmlNode $figure */
          $figure = $dom->find('figure')[0];
          $uri = ltrim($figure->getAttribute('src'), '/');
          if (strpos($uri, 'http') !== 0) {
            $uri = 'public://' . preg_replace('~sites/default/files/~', '', $uri);
          }
          $filemime = $this->mimeTypeGuesser->guess($uri);
          if (strpos($uri, 'public://') === 0) {
            $uri = preg_replace('~^public://iiif/~', $this->iiif, $uri);
          }
          $basename = basename($uri);
          if ($filemime === 'image/png') {
            $filemime = 'image/jpeg';
            $basename = preg_replace('/\.png$/', '.jpg', $basename);
          }
          switch ($filemime) {
            case 'image/gif':
              $ext = 'gif';
              break;

            case 'image/png':
              $ext = 'png';
              break;

            default:
              $ext = 'jpg';
          }
          $caption = NULL;
          /** @var \PHPHtmlParser\Dom\Collection $captions */
          $captions = $figure->find('figcaption');
          if ($captions->count()) {
            $dom = new Dom();
            $dom->load($this->converter->convertToHtml(trim(preg_replace('~^.*<figcaption[^>]*>\s*(.*)\s*</figcaption>.*~', '$1', $contents))));
            /** @var \PHPHtmlParser\Dom\HtmlNode $text */
            $text = $dom->find('p')[0];
            $caption = $this->prepareOutput($text->innerHtml(), $context);
          }
          return array_filter([
            'type' => 'image',
            'image' => [
              'uri' => $uri,
              'alt' => $figure->getAttribute('alt') ?? '',
              'source' => [
                'mediaType' => $filemime,
                'uri' => $uri . '/full/full/0/default.' . $ext,
                'filename' => $basename,
              ],
              'size' => [
                'width' => (int) $figure->getAttribute('width'),
                'height' => (int) $figure->getAttribute('height'),
              ],
              'focalPoint' => [
                'x' => 50,
                'y' => 50,
              ],
            ],
            'title' => $caption,
            'inline' => (bool) preg_match('/align\-left/', $figure->getAttribute('class')),
          ]);
        }
      }
    }
    elseif ($node instanceof Paragraph) {
      if ($rendered = $this->htmlRenderer->renderBlock($node)) {
        $contents = $rendered->getContents();
        if (preg_match('/^<elifebutton.*<\/elifebutton>/', $contents)) {
          $dom = new Dom();
          $dom->load($contents);
          /** @var \PHPHtmlParser\Dom\HtmlNode $button */
          $button = $dom->find('elifebutton')[0];
          $uri = ltrim($button->getAttribute('data-href'), '/');
          $text = $button->innerHtml();
          return [
            'type' => 'button',
            'text' => $this->prepareOutput($text, $context),
            'uri' => $uri,
          ];
        }
        elseif (preg_match('/^<oembed>(?P<youtube>https:\/\/www\.youtube\.com\/watch\?v=.*)<\/oembed>/', $contents, $matches)) {
          $id = preg_replace('/^(|.*[^a-zA-Z0-9_-])([a-zA-Z0-9_-]{11})(|[^a-zA-Z0-9_-].*)$/', '$2', $matches['youtube']);
          // @todo - we need to store the width and height of videos on save.
          return [
            'type' => 'youtube',
            'id' => $id,
            'width' => 16,
            'height' => 9,
          ];
        }
        else {
          return [
            'type' => 'paragraph',
            'text' => $this->prepareOutput($contents, $context),
          ];
        }
      }
    }
    elseif ($node instanceof ListBlock) {
      return $this->processListBlock($node, $context);
    }
    elseif ($node instanceof BlockQuote && $rendered = $this->htmlRenderer->renderBlock($node)) {

      return [
        'type' => 'quote',
        'text' => [
                [
                  'type' => 'paragraph',
                  'text' => trim(preg_replace('/^[\s]*<p>(.*)<\/p>[\s]*$/s', '$1', $rendered->getContents())),
                ],
        ],
      ];
    }
    elseif (($node instanceof IndentedCode || $node instanceof FencedCode) && $contents = $node->getStringContent()) {
      if (in_array('code', $encode)) {
        $contents = $this->prepareOutput($contents, $context, TRUE);
      }
      return [
        'type' => 'code',
        'code' => $this->prepareOutput($contents, $context),
      ];
    }

    return [];
  }

  /**
   * Prepare output.
   */
  private function prepareOutput($content, $context = [], $decode = FALSE) {
    $regexes = $context['regexes'] ?? [];
    $output = trim(($decode) ? base64_decode($content) : $content);
    if (!empty($regexes)) {
      $output = preg_replace(array_keys($regexes), array_values($regexes), $output);
    }
    return $output;
  }

  /**
   * Implement hierarchy.
   */
  private function implementHierarchy(array $nodes) : array {
    // Organise 2 levels of section.
    for ($level = 2; $level > 0; $level--) {
      $hierarchy = [];
      for ($i = 0; $i < count($nodes); $i++) {
        $node = $nodes[$i];

        if ($node['type'] === 'section' && isset($node['depth']) && $node['depth'] === $level) {
          unset($node['depth']);
          for ($j = $i + 1; $j < count($nodes); $j++) {
            $sectionNode = $nodes[$j];
            if ($sectionNode['type'] === 'section' && isset($sectionNode['depth']) && $sectionNode['depth'] <= $level) {
              break;
            }
            else {
              $node['content'][] = $sectionNode;
            }
          }
          $i = $j - 1;
          if (empty($node['content'])) {
            continue;
          }
        }
        $hierarchy[] = $node;
      }
      $nodes = $hierarchy;
    };

    return $hierarchy ?? [];
  }

  /**
   * Process list block.
   */
  private function processListBlock(ListBlock $block, $context = []) {
    $gather = function (ListBlock $list) use (&$gather, &$render, $context) {
      $items = [];
      foreach ($list->children() as $item) {
        foreach ($item->children() as $child) {
          if ($child instanceof ListBlock) {
            $items[] = [$render($child)];
          }
          elseif ($item = $this->htmlRenderer->renderBlock($child)) {
            $items[] = $this->prepareOutput($item->getContents(), $context);
          }
        }
      }

      return $items;
    };

    $render = function (ListBlock $list) use ($gather) {
      return [
        'type' => 'list',
        'prefix' => (ListBlock::TYPE_ORDERED === $list->getListData()->type) ? 'number' : 'bullet',
        'items' => $gather($list),
      ];
    };

    return $render($block);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) : bool {
    return is_string($data);
  }

}
