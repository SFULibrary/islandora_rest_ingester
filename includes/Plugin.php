<?php

namespace islandora_rest_ingester\plugins;

/**
 * Abstract class for Islandora REST Ingester plugins.
 */
abstract class Plugin
{
    /**
     * @param string dir$
     *    The object directory.
     * @param object $log
     *    The Monolog logger.
     * @param object $command
     *    The Commando command used in ingest.php.
     */
    public function __construct($dir, $log, $command)
    {
        $this->dir = $dir;
        $this->log = $log;
        $this->command = $command;
    }

    /**
     * Execute the plugin.
     */
    abstract public function execute();
}
