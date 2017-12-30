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
 * Parses a MODS document to get a label.
 *
 * @param $path string
 *   The absolute path to the MODS XML file.
 * @param $log object
 *   The Monolog logger.
 *
 * @return string|bool
 *   The label, or false if one cannot be parsed.
 */
function get_label_from_mods($path, $log) {
    if (file_exists($path)) {
        $dom = new DOMDocument();
        $dom->load($path);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('mods', 'http://www.loc.gov/mods/v3');
        $titles = $xpath->query('//mods:titleInfo/mods:title');
        if ($titles->length > 0) {
          $label = $titles->item(0)->nodeValue;
          return $label;
        }
        else {
            $log->addWarning("Cannot parse label from MODS file at $path");
            return false;
        }
    }
    else {
        $log->addWarning("Cannot get label from MODS file at " .
            "$path since file does not exist.");
        return false;
    }
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

/**
 * Determines content model based on file extension.
 *
 * @param string $extension
 *   The file extension to look up.
 *
 * @return string
 *   The corresponding content model.
 */
function get_cmodel_from_extension($extension) {
    $cmodels = array(
      'jpeg' => 'islandora:sp_basic_image',
      'jpg' => 'islandora:sp_basic_image',
      'gif' => 'islandora:sp_basic_image',
      'png' => 'islandora:sp_basic_image',
      'tif' => 'islandora:sp_large_image_cmodel',
      'tiff' => 'islandora:sp_large_image_cmodel',
      'jp2' => 'islandora:sp_large_image_cmodel',
      'pdf' => 'islandora:sp_pdf',
      'mp3' => 'islandora:sp-audioCModel',
      'mp4a' => 'islandora:sp-audioCModel',
      'm4a' => 'islandora:sp-audioCModel',
      'oga' => 'islandora:sp-audioCModel',
      'ogg' => 'islandora:sp-audioCModel',
      'flac' => 'islandora:sp-audioCModel',
      'wav' => 'islandora:sp-audioCModel',
      'mp4' => 'islandora:sp_videoCModel',
      'm4v'  => 'islandora:sp_videoCModel',
      'mkv'  => 'islandora:sp_videoCModel',
      'mpeg' => 'islandora:sp_videoCModel',
      'mpe' => 'islandora:sp_videoCModel',
      'mpg' => 'islandora:sp_videoCModel',
      'qt' => 'islandora:sp_videoCModel',
      'mov' => 'islandora:sp_videoCModel',
      'ogv' => 'islandora:sp_videoCModel',
    );
    return $cmodels[$extension];
}
