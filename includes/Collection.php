<?php

namespace islandora_rest_client\ingesters;

/**
 *
 */
class Collection extends Ingester
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
        // @todo: Finish.
    }
}