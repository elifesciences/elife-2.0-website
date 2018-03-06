<?php

namespace Drupal\jcms_migrate\Plugin\migrate\process;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

/**
 * Methods for retrieval of remote files.
 */
trait JCMSGetRemoteFileTrait {

  /**
   * Get filename, from filename.
   */
  public function getFile(string $filename, array $options = []) {
    $guzzle = new Client();
    try {
      $options += [
        'timeout' => 13,
        'http_errors' => FALSE,
      ];
      $response = $guzzle->get($filename, $options);
      if ($response->getStatusCode() == 200) {
        return (string) $response->getBody();
      }

      error_log(sprintf("File %s didn't download. (return code %d)", $filename, $response->getStatusCode()));
      return FALSE;
    }
    catch (ConnectException $e) {
      error_log(sprintf("File %s didn't download. (%s)", $filename, $e->getMessage()));
    }
  }

}
