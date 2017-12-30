<?php

/**
 * @file
 * Utilities for the Islandora REST Ingester.
 */

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
 * Sends a request to a REST endpoint (e.g. object or datastream).
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

/**
 * Parses object and datastream properties from FOXML.
 *
 * @param $path string
 *   The path to the FOXML file.
 *
 * @return array
 *   An array with two members, 'object' and 'datastreams':
 *     -'object' contains an array of object properties 'PID',
 *      'ownerId', 'label', 'state', 'createdDate', and 'lastModifiedDate'.
 *     -'datastreams' contains an array of arrays, one per datastream.
 *      The datastream ID is the key for each array, which in turn contains
 *      and array of datastream properties 'state', 'control_group', and
 *      'versionable'.
 */
function get_properties_from_foxml($path) {
    $properties = array();
    $dom = new DOMDocument;
    $dom->load($path);

    $object = $dom->getElementsByTagNameNS("info:fedora/fedora-system:def/foxml#", 'digitalObject');
    $pid = $object->item(0)->getAttribute("PID");
    $properties['object']['PID'] = $pid;

    $object_properties = $dom->getElementsByTagNameNS("info:fedora/fedora-system:def/foxml#", 'property');
    foreach ($object_properties as $object_property) {
        $name = $object_property->getAttribute("NAME");
        preg_match("/#.*$/", $name, $matches);
        $short_name = $matches[0];
        $short_name = ltrim($short_name, '#');
        $value = $object_property->getAttribute("VALUE");
        $properties['object'][$short_name] = $value;
    }

    $datastreams = $dom->getElementsByTagNameNS("info:fedora/fedora-system:def/foxml#", 'datastream');
    foreach ($datastreams as $datastream) {
        $id = $datastream->getAttribute("ID");
        $state = $datastream->getAttribute("STATE");
        $properties['datastreams'][$id]['state'] = $state;
        $control_group = $datastream->getAttribute("CONTROL_GROUP");
        $properties['datastreams'][$id]['control_group'] = $control_group;
        $versionable = $datastream->getAttribute("VERSIONABLE");
        $properties['datastreams'][$id]['versionable'] = $versionable;
    }

    return $properties;
}

/**
 * Checks if the given pid is valid.
 *
 * Adapted from the Islandora utilities.inc file.
 *
 * @param string $pid
 *   The object id to check.
 *
 * @return bool
 *   TRUE if valid, FALSE otherwise.
 */
function is_valid_pid($pid) {
  return strlen(trim($pid)) <= 64 && preg_match('/^([A-Za-z0-9]|-|\.)+:(([A-Za-z0-9])|-|\.|~|_|(%[0-9A-F]{2}))+$/', trim($pid));
}
