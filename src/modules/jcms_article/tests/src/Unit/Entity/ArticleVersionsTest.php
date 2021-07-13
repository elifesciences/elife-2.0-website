<?php

namespace Drupal\jcms_article\Tests\Unit\Entity;

use Drupal\Tests\UnitTestCase;
use Drupal\jcms_article\Entity\ArticleVersions;

/**
 * Tests for ArticleVersions.
 *
 * @package Drupal\jcms_article\Tests\Unit\Entity
 */
class ArticleVersionsTest extends UnitTestCase {

  protected $testJson;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // This is cut down but follows the correct version JSON structure.
    $this->testJson = '{"versions":[{"status":"preprint"},{"stage":"published","title":"Older published","version":2},{"stage":"preview","title":"Older preview","version":1},{"stage":"published","title":"Latest published","version":4},{"stage":"preview","title":"Latest preview","version":2}]}';
  }

  /**
   * Test basic getters.
   *
   * @test
   * @group journal-cms-tests
   */
  public function testBasicGetters() {
    $id = 19887;
    $json = '{"versions":[{"stage":"published","title":"Article title","version": 1}]}';
    $versions = new ArticleVersions($id, $json, ArticleVersions::DELETE);
    $this->assertEquals($id, $versions->getId());
    $this->assertEquals($json, $versions->getJson());
    $this->assertEquals(ArticleVersions::DELETE, $versions->getAction());
    $this->assertInstanceOf('stdClass', $versions->getJsonObject());
  }

  /**
   * Test reaction to invalid json.
   *
   * @test
   * @group journal-cms-tests
   * @expectedException \InvalidArgumentException
   */
  public function testInvalidJson() {
    $id = 19887;
    $json = '{"versions":[{"stage":}';
    $versions = new ArticleVersions($id, $json, ArticleVersions::DELETE);
  }

  /**
   * Test retrieval of published article snippet.
   *
   * @test
   * @group journal-cms-tests
   */
  public function testGetLatestPublishedVersionJson() {
    $versions = new ArticleVersions(19887, $this->testJson);
    $actual = $versions->getLatestPublishedVersionJson();
    $json = json_decode($actual);
    $this->assertEquals('Latest published', $json->title);
  }

  /**
   * Test retrieval of unpublished article snippet.
   *
   * @test
   * @group journal-cms-tests
   */
  public function testGetLatestUnpublishedVersionJson() {
    $versions = new ArticleVersions(19887, $this->testJson);
    $actual = $versions->getLatestUnpublishedVersionJson();
    $json = json_decode($actual);
    $this->assertEquals('Latest preview', $json->title);
  }

  /**
   * Test empty result if no article snippet available.
   *
   * @test
   * @group journal-cms-tests
   */
  public function testGetLatestStageVersionJsonNoVersions() {
    $versions = new ArticleVersions(19887, '{}');
    $actual = $versions->getLatestUnpublishedVersionJson();
    $this->assertEmpty($actual);
  }

  /**
   * Test sample content generation.
   *
   * @test
   * @group journal-cms-tests
   */
  public function testGenerateSampleJson() {
    $versions = new ArticleVersions(19887, '');
    $versions->generateSampleJson();
    $this->assertEquals(json_decode(json_encode([
      'versions' => [
        [
          'stage' => 'published',
          'status' => 'vor',
          'id' => '19887',
          'version' => 1,
          'type' => 'research-article',
          'doi' => '10.7554/eLife.19887',
          'title' => 'Article 19887',
          'published' => '2016-03-28T00:00:00Z',
          'versionDate' => '2016-03-28T00:00:00Z',
          'statusDate' => '2016-03-28T00:00:00Z',
          'volume' => 1,
          'elocationId' => 'e19887',
        ],
      ],
    ])), $versions->getJsonObject());
  }

}
