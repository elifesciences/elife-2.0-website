<?php

namespace Drupal\jcms_article\Entity;

/**
 * Store article versions.
 *
 * @package Drupal\jcms_article\Entity
 */
final class ArticleVersions {

  const WRITE = 1;

  const DELETE = 2;

  const PUBLISHED = 'published';

  const UNPUBLISHED = 'preview';

  /**
   * Article ID.
   *
   * @var string
   */
  private $id;

  /**
   * Article snippet.
   *
   * @var string
   */
  private $json = '';

  /**
   * Action.
   *
   * @var int
   */
  private $action;

  /**
   * ArticleVersions constructor.
   */
  public function __construct(string $id, string $json = '', int $action = self::WRITE) {
    $json = $json ?: '{}';
    if (!$this->isValidJson($json)) {
      throw new \InvalidArgumentException('JSON error: ' . json_last_error_msg());
    }
    $this->id = $id;
    $this->action = $action;
    $this->json = $json;
  }

  /**
   * Generate sample json.
   */
  public function generateSampleJson() {
    $this->json = json_encode([
      'versions' => [
        [
          'stage' => self::PUBLISHED,
          'status' => 'vor',
          'id' => (string) $this->id,
          'version' => 1,
          'type' => 'research-article',
          'doi' => '10.7554/eLife.' . $this->id,
          'title' => 'Article ' . $this->id,
          'stage' => 'published',
          'published' => '2016-03-28T00:00:00Z',
          'versionDate' => '2016-03-28T00:00:00Z',
          'statusDate' => '2016-03-28T00:00:00Z',
          'volume' => 1,
          'elocationId' => 'e' . $this->id,
        ],
      ],
    ]);
  }

  /**
   * Checks if the string passed is valid JSON (passes with an empty string).
   */
  public function isValidJson(string $json) : bool {
    json_decode($json);
    return (json_last_error() === JSON_ERROR_NONE);
  }

  /**
   * Returns the article ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Returns the article action, write or delete.
   */
  public function getAction(): string {
    return $this->action;
  }

  /**
   * Returns a string of JSON or an empty string.
   */
  public function getJson(): string {
    return $this->json;
  }

  /**
   * Returns an object of the JSON or an empty object.
   */
  public function getJsonObject(): \stdClass {
    return json_decode($this->getJson());
  }

  /**
   * Returns the latest published article version.
   */
  public function getLatestPublishedVersionJson(): string {
    return $this->getLatestStageVersionJson(self::PUBLISHED);
  }

  /**
   * Returns the latest unpublished article version.
   */
  public function getLatestUnpublishedVersionJson(): string {
    return $this->getLatestStageVersionJson(self::UNPUBLISHED);
  }

  /**
   * Returns the latest version for a stage.
   */
  public function getLatestStageVersionJson(string $stage): string {
    $needle = '';
    $json = $this->getJsonObject();
    if (!property_exists($json, 'versions')) {
      return $needle;
    }
    $versions = array_filter($json->versions, function ($version) {
      return isset($version->version);
    });

    usort($versions, function ($a, $b) {
      return $a->version === $b->version ? 0 : (($a->version > $b->version) ? -1 : 1);
    });
    foreach ($versions as $version) {
      if ($version->stage === $stage) {
        $needle = json_encode($version);
        break;
      }
    }
    return $needle;
  }

}
