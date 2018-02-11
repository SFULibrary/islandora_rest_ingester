<?php

namespace islandora_rest_ingester\plugins;

/**
 * Class for Islandora REST Ingester Example plugin.
 */
class Example extends Plugin
{
    /**
     * @param string $dir
     *    The current object input directory.
     * @param object $log
     *    The Monolog logger.
     * @param object $command
     *    The Commando command used in ingest.php.
     */
    public function __construct($dir, $log, $command)
    {
        parent::__construct($dir, $log, $command);
    }

    public function execute()
    {
         $this->log->addInfo("Hello from the Example plugin. My directory is " . $this->dir);
    }
}
