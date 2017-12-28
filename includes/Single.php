<?php

namespace islandora_rest\ingesters;

/**
 *
 */
class Single extends Ingester
{
    /**
     * @param object $log
     *    The Monolog logger.
     * @param object $command
     *    The command used to invoke ingest.php.
     */
    public function __construct($log, $command)
    {
        parent::__construct($log, $command);
    }

    public function ingestObject($dir) {
        require_once 'utilites.inc';

        $mods_path = realpath($dir) . DIRECTORY_SEPARATOR . 'MODS.xml';
        if (file_exists($mods_path)) {
            $mods_xml = file_get_contents($mods_path);
            $xml = simplexml_load_string($mods_xml);
            $label = (string) current($xml->xpath('//mods:title'));
        }
        else {
            $this->log->addWarning(realpath($dir) . " appears to be empty, skipping.");
            return;
        }

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
                return;
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
                exit;
            }
        }

        $object_response_body = $object_response->getBody();
        $object_response_body_array = json_decode($object_response_body, true);
        $pid = $object_response_body_array['pid'];

        $message = "Object $pid ingested from " . realpath($dir);
        $this->log->addInfo($message);
        print $message . "\n";
    }
}
