<?php

namespace islandora_rest\ingesters;

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

/**
 * Base class for Islandora REST Ingesters.
 */
abstract class Ingester
{
    /**
     * @param object $log
     *    The Monolog logger.
     * @param object $command
     *    The command used to invoke ingest.php.
     */
    public function __construct($log, $command)
    {
        $this->log = $log;
        $this->command = $command;

        $this->client = new \GuzzleHttp\Client();
    }

    /**
     * Package the object.
     *
     * Inspect the object-level input directory and get the
     * object's title.
     *
     * @param string $object_dir
     *    The absolute path to the object's input directory.
     */
    abstract public function packageObject($object_dir);

    /**
     * Ingests the Islandora object vi the REST interface.
     *
     * @param string $dir
     *    The absolute path to the object's input directory.
     * @param string $label
     *    The object's label.
     *
     * @return string|bool
     *    The new object's PID, FALSE if there is an exception or error.
    */
    public function ingestObject($dir, $label)
    {
        // If no namespace is provided, use input directory names as PIDs.
        // Here, 'namespace' can be a full PID, as per the Fedora and Islandora
        // REST APIs.
        if (strlen($this->command['n'])) {
            $namespace = $this->command['n'];
        }
        else {
            $namespace = basename(realpath($dir));
            $namespace = urldecode($namespace);
        }

        // If the "namespace" is a valid PID, check to see if the object exists.
        if (is_valid_pid($namespace)) {
            $url = $this->command['e'] . '/object/' . $namespace;
            $http_status = ping_url($url, $this->command, $this->log);
            if (is_string($http_status) && $http_status == '200') {
                // If it does, log it and skip ingesting it.
                $this->log->addWarning("Object " . $namespace . " (from " . realpath($dir) . ") already exists, skipping.");
                return FALSE;
            }
        }

        // Ingest Islandora object.
        try {
            $object_response = $this->client->request('POST', $this->command['e'] . '/object', [
                'form_params' => [
                    'namespace' => $namespace,
                    'owner' => $this->command['o'],
                    'label' => $label,
                ],
               'headers' => [
                    'Accept' => 'application/json',
                    'X-Authorization-User' => $this->command['u'] . ':' . $this->command['t'],
                ]
            ]);
        } catch (Exception $e) {
            if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException ) {
                $this->log->addError(Psr7\str($e->getRequest()));
                if ($e->hasResponse()) {
                    $this->log->addError(Psr7\str($e->getResponse()));
                    print Psr7\str($e->getResponse()) . "\n";
                }
                return false;
            }
        }

        $object_response_body = $object_response->getBody();
        $object_response_body_array = json_decode($object_response_body, true);
        $pid = $object_response_body_array['pid'];

        $cmodel_params = array(
            'uri' => 'info:fedora/fedora-system:def/model#',
            'predicate' => 'hasModel',
            'object' => $this->command['m'],
            'type' => 'uri',
        );
        $this->addRelationship($pid, $cmodel_params);

        $parent_params = array(
            'uri' => 'info:fedora/fedora-system:def/relations-external#',
            'predicate' => $this->command['r'],
            'object' => $this->command['p'],
            'type' => 'uri',
        );
        $this->addRelationship($pid, $parent_params);

        $this->ingestDatastreams($pid, $dir);

        return $pid;
    }

    /**
     * Add a relationship.
     *
     * @param string $pid
     *    The PID of the subject of the relationship.
     * @param array $params
     *    Associative array containing the relationship uri, predicate,
     *    object, and optionally, type.
     */
    public function addRelationship($pid, $params)
    {
        try {
            $model_response = $this->client->request('POST', $this->command['e'] . '/object/' .
                $pid . '/relationship', [
                'form_params' => [
                    'uri' => $params['uri'],
                    'predicate' => $params['predicate'],
                    'object' => $params['object'],
                    'type' => $params['type'],
                ],
               'headers' => [
                    'Accept' => 'application/json',
                    'X-Authorization-User' => $this->command['u'] . ':' . $this->command['t'],
                ]
            ]);
        } catch (Exception $e) {
            $this->log->addError("Relationship for $pid (predicate: " .
                $params['predicate'] . " , object: " . $params['predicate'] .
                " not added");
            if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException ) {
                $this->log->addError(Psr7\str($e->getRequest()));
                if ($e->hasResponse()) {
                    $this->log->addError(Psr7\str($e->getResponse()));
                }
                return;
            }
        }
    }

    /**
     * Reads the object-level directory and ingests each file as a datastream.
     *
     * If the datastream already exists due to derivative generation (e.g., a
     * TN datastream), its content is updated from the datastream file.
     *
     * @param $pid string
     *   The PID of the parent object.
     * @param $dir string
     *   Absolute path to the directory containing the object's datastream files.
     */
    public function ingestDatastreams($pid, $dir) {
        $files = array_slice(scandir(realpath($dir)), 2);
        if (count($files)) {
            foreach ($files as $file) {
                $path_to_file = realpath($dir) . DIRECTORY_SEPARATOR . $file;
                $pathinfo = pathinfo($path_to_file);
                $dsid = $pathinfo['filename'];
                // This is the POST request and multipart form data required
                // to create a new datastream.
                $post_request = $this->command['e'] . '/object/' . $pid . '/datastream';
                $multipart = array(
                    [
                        'name' => 'file',
                        'filename' => $pathinfo['basename'],
                        'contents' => fopen($path_to_file, 'r'),
                    ],
                    [
                        'name' => 'dsid',
                        'contents' => $dsid,
                    ],
                    [
                        'name' => 'checksumType',
                        'contents' => $this->command['c'],
                    ],
                );
                // However, before we create the datastream, check to see if the
                // datastream already exists, in which case we modify the request
                // in order to replace the datastream content.
                $ds_url = $this->command['e'] . '/object/' . $pid . '/datastream/' . $dsid . '?content=false';
                $http_status = ping_url($ds_url, $this->command, $this->log);
                // If the datastream already exists, change the POST values and
                // URL to update the datastream's content.
                if (is_string($http_status)) {
                    if ($http_status == '200') {
                        // This POST value is necessary for replacing the datastream content.
                        $multipart[] = array(
                            'name' => 'method',
                            'contents' => 'PUT',
                        );
                        $post_request = $this->command['e'] . '/object/' . $pid . '/datastream/' . $dsid;
                        $this->log->addInfo("Ping URL response code for the $dsid datastream was $http_status; will attempt to update datastream content.");
                    }
                    else {
                        // If the status code was not 200, log it.
                        if ($http_status == '404') {
                            $this->log->addInfo("Ping URL response code for the $dsid datastream was $http_status (this is OK; it means the datastream hasn't been ingested yet).");
                        }
                        else {
                            $this->log->addInfo("Ping URL response code for the $dsid datastream was $http_status.");
                        }
                    }
                }
                else {
                    // If there was an error getting the status code, move on to
                    // the next file. The exception will be logged from within
                    // ping_url() but we log the response code here.
                    continue;
                }
                // Now that we have the correct request URL and multipart form
                // data, attempt to ingest the datastream if it doesn't already
                // exist, or if it does, update its content.
                try {
                    $response = $this->client->request('POST', $post_request, [
                       'multipart' => $multipart,
                       'headers' => [
                            'Accept' => 'application/json',
                            'X-Authorization-User' => $this->command['u'] . ':' . $this->command['t'],
                        ]
                    ]);
                    $this->log->addInfo("Object $pid datastream $dsid ingested from $path_to_file");
                } catch (Exception $e) {
                    if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException ) {
                        $log->addError(Psr7\str($e->getRequest()));
                        if ($e->hasResponse()) {
                            $this->log->addError(Psr7\str($e->getResponse()));
                            continue;
                        }
                    }
                }
                if ($this->command['c'] != 'none') {
                    $local_checksum = get_local_checksum($path_to_file, $this->command);
                    $response_body = $response->getBody();
                    $response_body_array = json_decode($response_body, true);
                    if ($local_checksum == $response_body_array['checksum']) {
                        $this->log->addInfo($this->command['c'] . " checksum for object $pid datastream $dsid verified.");
                    } else {
                        $this->log->addWarning($this->command['c'] . " checksum for object $pid datastream $dsid mismatch.");
                    }
                }
            }
        }
    }

}
