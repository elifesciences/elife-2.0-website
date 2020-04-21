<?php

namespace Drupal\jcms_admin;

use Psr\Log\LoggerInterface;

/**
 * Class Figshare.
 */
final class Figshare implements FigshareInterface {
  private $embed;
  private $logger;

  /**
   * GoogleMap constructor.
   */
  public function __construct(Embed $embed, LoggerInterface $logger) {
    $this->embed = $embed;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdFromUri(string $uri): string {
    if (preg_match('/\/articles\/([^\/]+\/)?(?P<id>[0-9]+)/', stripslashes($uri), $match)) {
      return $match['id'];
    }

    $this->logger->warning('Figshare ID not found in uri.', ['uri' => $uri]);
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(string $id): string {
    try {
      if ($info = $this->embed->create('https://figshare.com/articles/og/' . $id)) {
        if (isset($info->getProviders()['opengraph'])) {
          /* @var \Embed\Providers\OpenGraph $opengraph */
          $opengraph = $info->getProviders()['opengraph'];
          // Retrieve title of the Fighsare.
          if ($title = $opengraph->getTitle()) {
            return $title;
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Figshare could not be reached.', ['id' => $id]);
    }
  }

}
