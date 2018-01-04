<?php

namespace islandora_rest_client\ingesters;

/**
 * Islandora REST Ingester Compound (islandora:compoundCModel) class.
 */
class Compound extends Ingester
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
        // Get the compound object's label from the MODS.xml file. If there
        // is no MODS.xml file in the input directory, move on to the
        // next directory.
        $mods_path = realpath($dir) . DIRECTORY_SEPARATOR . 'MODS.xml';
        if (!$label = get_value_from_mods($mods_path, '//mods:titleInfo/mods:title', $this->log)) {
            $this->log->addWarning(realpath($dir) . " appears to be empty, skipping.");
            return;
        }

        $cpd_pid = $this->ingestObject($dir, $label);

        $cmodel = get_cmodel_from_cmodel_txt(realpath($dir)) ?
            get_cmodel_from_cmodel_txt(realpath($dir)) : $this->command['m'];
        $cmodel_params = array(
            'uri' => 'info:fedora/fedora-system:def/model#',
            'predicate' => 'hasModel',
            'object' => $cmodel,
            'type' => 'uri',
        );
        $this->addRelationship($cpd_pid, $cmodel_params);

        $parent_params = array(
            'uri' => 'info:fedora/fedora-system:def/relations-external#',
            'predicate' => $this->command['r'],
            'object' => $this->command['p'],
            'type' => 'uri',
        );
        $this->addRelationship($cpd_pid, $parent_params);

        // Ingest MODS.xml.
        $this->ingestDatastreams($cpd_pid, $dir);

        if ($cpd_pid) {
            $message = "Object $cpd_pid ingested from " . realpath($dir);
            $this->log->addInfo($message);
            print $message . "\n";
        }

        // Get the child object directories and ingest each child.
        $child_pids = array();
        $child_ingester = new \islandora_rest_client\ingesters\Single($this->log, $this->command);
        $child_dirs = new \FilesystemIterator(realpath($dir));
        foreach ($child_dirs as $child_dir) {
            $child_dir = $child_dir->getPathname();

            if (!is_dir($child_dir)) {
                continue;
            }

            // Get sequence number from directory name.
            $child_dir_name = pathinfo($child_dir, PATHINFO_FILENAME);

            $obj_files = glob($child_dir . DIRECTORY_SEPARATOR . 'OBJ.*');
            $obj_file_path = $child_dir . DIRECTORY_SEPARATOR . $obj_files[0];

            if (!$obj_file_cmodel = get_cmodel_from_cmodel_txt($child_dir)) {
                $obj_file_ext = pathinfo($obj_file_path, PATHINFO_EXTENSION);
                if (!$obj_file_cmodel = get_cmodel_from_extension($obj_file_ext)) {
                    $this->log->addWarning("Cannot determine content model for child object " .
                        "at " . realpath($dir) . ", skipping.");
                    continue;
                }
            }

            $child_mods_path = realpath($child_dir) . DIRECTORY_SEPARATOR . 'MODS.xml';
            $child_label = get_value_from_mods($child_mods_path, '//mods:titleInfo/mods:title', $this->log);
            $child_pid = $child_ingester->ingestObject($child_dir, $child_label);

            // If $child_pid is FALSE, log error and move on to next object.
            if (!$child_pid) {
                $this->log->addError("Child object at " . realpath($child_dir) . " not ingested");
                continue;
            }

            // Keep track of child PIDS so we can get the first one's TN later.
            array_push($child_pids, $child_pid);

            $cmodel_params = array(
                'uri' => 'info:fedora/fedora-system:def/model#',
                'predicate' => 'hasModel',
                'object' => $obj_file_cmodel,
                'type' => 'uri',
            );
            $child_ingester->addRelationship($child_pid, $cmodel_params);

            $parent_params = array(
                'uri' => 'info:fedora/fedora-system:def/relations-external#',
                'predicate' => 'isConstituentOf',
                'object' => $cpd_pid,
                'type' => 'uri',
            );
            $child_ingester->addRelationship($child_pid, $parent_params);

            $uri_safe_parent_pid = preg_replace('/:/', '_', $cpd_pid);
            $is_sequence_number_of_params = array(
                'uri' => 'http://islandora.ca/ontology/relsext#',
                'predicate' => 'isSequenceNumberOf' . $uri_safe_parent_pid,
                'object' => $child_dir_name,
                'type' => 'none',
            );
            $child_ingester->addRelationship($child_pid, $is_sequence_number_of_params);

            // ingestDatastreams() must come after the object's content
            // model is set in order to derivatives to be generated.
            $child_ingester->ingestDatastreams($child_pid, $child_dir);

            if ($child_pid) {
                $message = "Object $child_pid ingested from " . realpath($child_dir);
                $this->log->addInfo($message);
                print $message . "\n";
            }
        }

        // Give the parent compound object the TN from its first child.
        if ($path_to_tn_file = download_datastream_content($child_pids[0], 'TN', $this->command, $this->log)) {
            $this->ingestDatastreams($cpd_pid, $dir, 'TN', $path_to_tn_file);
            unlink($path_to_tn_file);
        } else {
            $this->log->addWarning("TN for compound object $cpd_pid not replaced with TN for first child");
        }

        if ($cpd_pid) {
            return $cpd_pid;
        } else {
            return false;
        }
    }
}
