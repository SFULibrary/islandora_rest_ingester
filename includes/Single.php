<?php

namespace islandora_rest_client\ingesters;

/**
 * Islandora REST Ingester Single (e.g. basic image, PDF, etc.) class.
 */
class Single extends Ingester
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
    public function packageObject($dir)
    {
        // Get the object's label from the MODS.xml file. If there is
        // no MODS.xml file in the input directory, move on to the
        // next directory.
        $mods_path = realpath($dir) . DIRECTORY_SEPARATOR . 'MODS.xml';
        if (!$label = get_value_from_mods($mods_path, '//mods:titleInfo/mods:title', $this->log)) {
            $this->log->addWarning(realpath($dir) . " appears to be empty, skipping.");
            return;
        }

        $pid = $this->ingestObject($dir, $label);

        $cmodel = get_cmodel_from_cmodel_txt($dir) ? get_cmodel_from_cmodel_txt($dir) : $this->command['m'];
        $cmodel_params = array(
            'uri' => 'info:fedora/fedora-system:def/model#',
            'predicate' => 'hasModel',
            'object' => $cmodel,
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

        // ingestDatastreams() must come after the object's content
        // model is set in order to derivatives to be generated.
        $this->ingestDatastreams($pid, $dir);

        if ($pid) {
            $message = "Object $pid ingested from " . realpath($dir);
            $this->log->addInfo($message);
            print $message . "\n";
        }

        if ($pid) {
            return $pid;
        } else {
            return false;
        }
    }
}
