<?php

namespace Drupal\jcms_article;

use GuzzleHttp\Client;
use Drupal\Core\Site\Settings;
use Drupal\jcms_article\Entity\ArticleVersions;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class FetchArticleVersions.
 *
 * @package Drupal\jcms_article
 */
final class FetchArticleVersions {

  const VERSION_ARTICLE_HISTORY = 2;

  /**
   * GuzzleHttp\Client definition.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * Article versions end point.
   *
   * @var string
   */
  protected $endpoint;

  /**
   * Constructor.
   */
  public function __construct(Client $client) {
    $this->client = $client;
    $this->endpoint = Settings::get('jcms_articles_endpoint');
    if (!$this->endpoint) {
      throw new \RuntimeException('No endpoint found for requesting article versions.');
    }
  }

  /**
   * Allow an alternate endpoint to be set.
   */
  public function setEndpoint(string $endpoint) {
    if (!empty($endpoint)) {
      $this->endpoint = $endpoint;
    }
  }

  /**
   * Gets article versions by ID.
   */
  public function getArticleVersions(string $id): ArticleVersions {
    $response = $this->requestArticleVersions($id);
    $action = $response->getStatusCode() == Response::HTTP_NOT_FOUND ? ArticleVersions::DELETE : ArticleVersions::WRITE;
    // This will almost always be a string but in case it's null or something.
    return new ArticleVersions($id, (string) $response->getBody(), $action);
  }

  /**
   * Makes the request to get the article versions.
   *
   * @throws BadResponseException
   */
  private function requestArticleVersions(string $id): ResponseInterface {
    $options = [
      'headers' => [
        'Accept' => 'application/vnd.elife.article-history+json;version=' . self::VERSION_ARTICLE_HISTORY,
      ],
    ];
    if ($auth = Settings::get('jcms_article_auth_unpublished')) {
      $options['headers'] += [
        'Authorization' => $auth,
      ];
    }
    $url = $this->formatUrl($id, $this->endpoint);
    try {
      $response = $this->client->get($url, $options);
      \Drupal::logger('jcms_article')
        ->notice(
          'Article versions have been requested @url with the response: @response',
          ['@url' => $url, '@response' => Message::toString($response)]
        );
      return $response;
    }
    catch (BadResponseException $exception) {
      if ($exception->getCode() === Response::HTTP_NOT_FOUND) {
        \Drupal::logger('jcms_article')
          ->notice(
            'Article versions have been requested but not found @url with the response: @response',
            ['@url' => $url, '@response' => Message::toString($exception->getResponse())]
          );
        return $exception->getResponse();
      }
      throw $exception;
    }
  }

  /**
   * Helper method to format an URL with a correct ID.
   */
  protected function formatUrl(string $id, string $url): string {
    return sprintf($url, $id);
  }

}
