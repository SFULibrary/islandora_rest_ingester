<?php

/**
 * Generates a checksum for comparison to the one reported by Islandora.
 *
 * @param $path_to_file string
 *   The path to the file.
 * @param $cmd object
 *   The Commando Command object.
 *
 * @return string|bool
 *   The checksum value, false if no checksum type is specified.
 */
function get_local_checksum($path_to_file, $cmd) {
    switch ($cmd['c']) {
        case 'SHA-1':
            $checksum = sha1_file($path_to_file);
            break;
        // @todo: Add more checksum types.
        default:
            $checksum = false;
    }
    return $checksum;
}

/**
 * Sends a describe object request to the REST endpoint.
 *
 * @param $pid string
 *   The PID of the parent object.
 * @param $cmd object
 *   The Commando Command object.
 * @param $log object
 *   The Monolog logger.
 *
 * @return array
 *   The body of the describe request.
 */
function describe_object($pid, $cmd, $log) {
  $client = new GuzzleHttp\Client();
  try {
      $response = $client->request('GET', $cmd['e'] . '/object/' .  $pid, [
         'headers' => [
              'Accept' => 'application/json',
              'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
          ]
      ]);
  } catch (Exception $e) {
      if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException ) {
          $log->addError(Psr7\str($e->getRequest()));
          if ($e->hasResponse()) {
              $log->addError(Psr7\str($e->getResponse()));
              print Psr7\str($e->getResponse()) . "\n";
          }
          exit;
      }
  }

  $response_body = $response->getBody();
  $response_body_array = json_decode($response_body, true);
  return $response_body_array;
}

/**
 * Sends a describe object request to the REST endpoint.
 *
 * @param $url string
 *   The URL to ping.
 * @param $cmd object
 *   The Commando Command object.
 * @param $log object
 *   The Monolog logger.
 *
 * @return string|object
 *   The returned status code or an exception object if one is encountered.
 */
function ping_url($url, $cmd, $log) {
  $client = new GuzzleHttp\Client(['http_errors' => false]);
  try {
      $response = $client->request('GET', $url, [
         'headers' => [
              'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
          ]
      ]);
      $status_code = $response->getStatusCode();
  } catch (Exception $e) {
      if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException ) {
          $log->addError(Psr7\str($e->getRequest()));
          if ($e->hasResponse()) {
              $log->addError(Psr7\str($e->getResponse()));
              print Psr7\str($e->getResponse()) . "\n";
          }
          return $e;
      }
  }

  return (string) $status_code;
}
