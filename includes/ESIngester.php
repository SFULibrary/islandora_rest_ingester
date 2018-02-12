<?php

namespace islandora_rest_ingester\ingesters;

/**
 * Islandora REST Ingester "Extremely Simple Ingester" class.
 */
class ESIngester extends Ingester
{
    /**
     * @param object $log
     *    The Monolog logger.
     * @param object $command
     *    The Commando command used in ingest.php.
     */
    public function __construct($log, $command)
    {
        parent::__construct($log, $command);

        if (preg_match('#/$#', $command['e'])) {
            $endpoint = $command['e'];
        } else {
            $endpoint = $command['e'] . '/';
        }
    
        $this->client_defaults = array(
            'base_uri' => $endpoint,
            'headers' => array('X-Authorization-User' => $command['u'] . ':' . $command['t']),
        );
    }

    /**
     * Package the object.
     *
     * In this specialized ingester, the input is just a directory of files,
     * there are no accompanying MODS.xml files and no subdirectory structure.
     *
     * @param string $path
     *    The absolute path to the object's input file.
     *
     * @return string|bool
     *    The new object's PID, FALSE if it wasn't ingested.
     */
    public function packageObject($path)
    {
        $pathinfo = pathinfo($path);

        if (in_array($pathinfo['basename'], $this->unwantedFiles)) {
            return;
        }

        $this->executePlugins($path);

        // Get the object's label from the file.
        $label = trim($pathinfo['filename']);

        $cmodel = $this->command['m'];

        $object = new \mjordan\Irc\Object($this->client_defaults);
        $object_response = $object->create(
            $this->command['n'],
            $this->command['o'],
            $label,
            $cmodel,
            $this->command['p']
        );

        if ($object->pid) {
            $message = "Object " . $object->pid . " ingested from " . realpath($path);
            $this->log->addInfo($message);
            print $message . "\n";
        }

        $ds = new \mjordan\Irc\Datastream($this->client_defaults);

        $ds_response = $ds->create(
            $object->pid,
            'OBJ',
            $path,
            array('label' => 'OBJ')
        );

        if ($object->pid) {
            return $object->pid;
        } else {
            return false;
        }
    }
}
