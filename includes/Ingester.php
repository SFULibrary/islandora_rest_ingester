<?php

namespace islandora_rest_client\ingesters;

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

/**
 * Abstract class for Islandora REST Ingesters.
 */
abstract class Ingester
{
    /**
     * @param object $log
     *    The Monolog logger.
     * @param object $command
     *    The Commando command used in ingest.php.
     */
    public function __construct($log, $command)
    {
        $this->log = $log;
        $this->command = $command;

        $this->client = new \GuzzleHttp\Client();

        // These files are skipped for the purpose of creating datastreams.
        $this->unwantedFiles = array(
            'cmodel.txt',
            'foxml.xml',
            '.Thumbs.db',
            'Thumbs.db',
            '.DS_Store',
            'DS_Store'
        );
    }

    /**
     * Package the object.
     *
     * Inspect the object-level input directory, get the
     * object's title, and ingest any children.
     *
     * @param string $object_dir
     *    The absolute path to the object's input directory.
     *
     * @return string|bool
     *    The new object's PID, FALSE if it wasn't ingested.
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
        } else {
            $namespace = basename(realpath($dir));
            $namespace = urldecode($namespace);
        }

        // If the value of $namespace is a valid PID, check to see if the object exists.
        if (is_valid_pid($namespace)) {
            $url = $this->command['e'] . '/object/' . $namespace;
            $http_status = ping_url($url, $this->command, $this->log);
            if (is_string($http_status) && $http_status == '200') {
                // If it does, log it and skip ingesting it.
                $this->log->addWarning("Object " . $namespace . " (from " . realpath($dir) .
                    ") already exists, skipping.");
                return false;
            }
        }

        // If foxml.xml exists in the object directory, parse it and get
        // the object owner, label, and state.
        $state_from_foxml = null;
        if (file_exists(realpath($dir . DIRECTORY_SEPARATOR . 'foxml.xml'))) {
            $props = get_properties_from_foxml(realpath($dir . DIRECTORY_SEPARATOR . 'foxml.xml'));
            $owner_id = $props['object']['ownerId'];
            $label = $props['object']['label'];
            $state_from_foxml = $props['object']['state'];
        } else {
            // label is set above, and state defaults to A.
            $owner_id = $this->command['o'];
        }

        // Ingest Islandora object.
        try {
            $object_response = $this->client->request('POST', $this->command['e'] . '/object', [
                'form_params' => [
                    'namespace' => $namespace,
                    'owner' => $owner_id,
                    'label' => $label,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Authorization-User' => $this->command['u'] . ':' . $this->command['t'],
                ]
            ]);
        } catch (Exception $e) {
            if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException) {
                $this->log->addError(Psr7\str($e->getRequest()));
                if ($e->hasResponse()) {
                    $this->log->addError(Psr7\str($e->getResponse()));
                }
                return false;
            }
        }

        $object_response_body = $object_response->getBody();
        $object_response_body_array = json_decode($object_response_body, true);
        $pid = $object_response_body_array['pid'];

        if ($pid && $this->command['s'] != 'A') {
            $this->setObjectState($pid, $this->command['s']);
        }
        if ($state_from_foxml) {
            $this->setObjectState($pid, $state_from_foxml);
        }

        // Add any relationships expressed in the object-level relationships.json file.
        if (file_exists(realpath($dir . DIRECTORY_SEPARATOR . 'relationships.json'))) {
            $rels = file_get_contents(realpath($dir . DIRECTORY_SEPARATOR . 'relationships.json'));
            $rels = json_decode($rels, true);
            $rels = $rels['relationships'];
            foreach ($rels as $relationship) {
                $this->addRelationship($pid, $relationship);
            }
        }

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
            $response = $this->client->request('POST', $this->command['e'] . '/object/' .
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
                    ],
                ]);
        } catch (Exception $e) {
            $this->log->addError("Relationship for $pid (predicate: " .
                $params['predicate'] . " , object: " . $params['predicate'] .
                " not added");
            if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException) {
                $this->log->addError(Psr7\str($e->getRequest()));
                if ($e->hasResponse()) {
                    $this->log->addError(Psr7\str($e->getResponse()));
                }
                return;
            }
        }
    }

    /**
     * Set object state.
     *
     * We cannot do this on ingesting via POST, it must be done via a secondary PUT.
     *
     * @param string $pid
     *    The PID of the subject of the relationship.
     * @param string $state
     *     One of 'A' (default), 'I', or 'D'.
     */
    public function setObjectState($pid, $state = 'A')
    {
        try {
            $put_response = $this->client->request('PUT', $this->command['e'] . '/object/' . $pid, [
                'json' => [
                    'state' => $state,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Authorization-User' => $this->command['u'] . ':' . $this->command['t'],
                ]
            ]);
            $status_code = $put_response->getStatusCode();
        } catch (Exception $e) {
            if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException) {
                $this->log->addError(Psr7\str($e->getRequest()));
                if ($e->hasResponse()) {
                    $this->log->addError(Psr7\str($e->getResponse()));
                }
                return false;
            }
        }
        return $status_code;
    }

    /**
     * Read the object-level directory and ingest each file in it as a datastream.
     *
     * If the datastream already exists due to derivative generation (e.g., a
     * TN datastream), its content is updated from the datastream file.
     *
     * @param $pid string
     *   The PID of the parent object.
     * @param $dir string
     *   Absolute path to the directory containing the object's datastream files.
     * @param $dsid string
     *   The DSID of the datastream. If present, only it will be ingested (i.e.,
     *   no scanning of $dir takes place).
     * @param $dsid_path string
     *   The absolute path to the datastream file. If present, and if $dsid present,
     *   only it will be ingested (i.e., no scanning of $dir takes place).
     */
    public function ingestDatastreams($pid, $dir, $dsid = null, $dsid_path = null)
    {
        if ($dsid && $dsid_path) {
            $files = array($dsid_path);
        } else {
            // Get rid of . and .. directories and unwanted files.
            $files = array_slice(scandir(realpath($dir)), 2);
            $files = array_diff($files, $this->unwantedFiles);
        }
        if (count($files)) {
            foreach ($files as $file) {
                if ($dsid && $dsid_path) {
                    $path_to_file = $dsid_path;
                    $pathinfo = pathinfo($path_to_file);
                } else {
                    $path_to_file = realpath($dir) . DIRECTORY_SEPARATOR . $file;
                    $pathinfo = pathinfo($path_to_file);
                    $dsid = $pathinfo['filename'];
                }

                if (!is_file($path_to_file)) {
                    continue;
                }

                $file_size_in_bytes = filesize($path_to_file);
                // Convert from bytes to MiB, same unit that PHP uses for its .ini settings.
                $file_size_in_mib = number_format($file_size_in_bytes / 1048576);
                if ((int) $file_size_in_mib > (int) $this->command['z']) {
                    $this->log->addWarning("Datastream file $path_to_file is larger than " .
                        $this->command['z'] . ' MB, skipping');
                    continue;
                }

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
                // However, before we create the datastream, we check to see if the
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
                        $this->log->addInfo("Ping URL response code for object $pid datastream $dsid was " .
                            "$http_status; will attempt to update datastream content.");
                    } else {
                        // If the status code was not 200, log it.
                        if ($http_status == '404') {
                            $this->log->addInfo("Ping URL response code for object $pid datastream $dsid " .
                                "was $http_status (this is OK).");
                        } else {
                            $this->log->addInfo("Ping URL response code for object $pid datastream $dsid " .
                                "was $http_status.");
                        }
                    }
                } else {
                    // If there was an error getting the status code, move on to
                    // the next file. The exception will be logged from within
                    // ping_url().
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
                    if ($response->getStatusCode() === 201) {
                        $this->log->addInfo("Object $pid datastream $dsid ingested from $path_to_file");
                    } else {
                        $this->log->addInfo("Object $pid datastream $dsid not ingested from " .
                            $path_to_file . "(HTTP response " . $response->getStatusCode() . ")");
                    }
                } catch (Exception $e) {
                    if ($e instanceof RequestException or $e instanceof ClientException or
                        $e instanceof ServerException) {
                        $this->log->addError(Psr7\str($e->getRequest()));
                        if ($e->hasResponse()) {
                            $this->log->addError(Psr7\str($e->getResponse()));
                            continue;
                        }
                    }
                }

                // Verify checksum of newly created datastream.
                if ($this->command['c'] != 'none') {
                    $local_checksum = get_local_checksum($path_to_file, $this->command);
                    $response_body = $response->getBody();
                    $response_body_array = json_decode($response_body, true);
                    if ($local_checksum == $response_body_array['checksum']) {
                        $this->log->addInfo($this->command['c'] . " checksum for object $pid " .
                            "datastream $dsid verified.");
                    } else {
                        $this->log->addWarning($this->command['c'] . " checksum for object $pid " .
                            "datastream $dsid mismatch.");
                    }
                }
            }
        }
    }
}
