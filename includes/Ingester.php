<?php

namespace islandora_rest\ingesters;

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

/**
 *
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
     * Ingest the object whose input files are within $object_dir.
     *
     * @param string $object_dir
     *    The object-level input directory.
     */
    abstract public function ingestObject($object_dir);

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
            if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException ) {
                $this->log->addError(Psr7\str($e->getRequest()));
                if ($e->hasResponse()) {
                    $this->log->addError(Psr7\str($e->getResponse()));
                    print Psr7\str($e->getResponse()) . "\n";
                }
                exit;
            }
        }
    }

}
