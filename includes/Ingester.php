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
}
