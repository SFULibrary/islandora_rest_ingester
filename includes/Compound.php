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

    public function packageObject($dir) {
        // Get the compound object's label from the MODS.xml file. If there
        // is no MODS.xml file in the input directory, move on to the
        // next directory.
        $mods_path = realpath($dir) . DIRECTORY_SEPARATOR . 'MODS.xml';
        if (!$label = get_label_from_mods($mods_path, $this->log)) {
            $this->log->addWarning(realpath($dir) . " appears to be empty, skipping.");
            return;
        }

        $cpd_pid = $this->ingestObject($dir, $label);

        $cmodel_params = array(
            'uri' => 'info:fedora/fedora-system:def/model#',
            'predicate' => 'hasModel',
            'object' => $this->command['m'],
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
        foreach($child_dirs as $child_dir) {
            $child_dir = $child_dir->getPathname();

            if (!is_dir($child_dir)) {
                continue;
            }

            // Get sequence number from directory name.
            $child_dir_name = pathinfo($child_dir, PATHINFO_FILENAME);

            $child_mods_path = realpath($child_dir) . DIRECTORY_SEPARATOR . 'MODS.xml';
            $child_label = get_label_from_mods($child_mods_path, $this->log);
            $child_pid = $child_ingester->ingestObject($child_dir, $child_label);

            // If $child_pid is FALSE, log error and move on to next object.
            if (!$child_pid) {
                $this->log->addError("Child object at " . realpath($child_dir) . " not ingested");
                continue;
            }

            // Keep track of child PIDS so we can get the first one's TN later.
            array_push($child_pids, $child_pid);

            $obj_files = glob($child_dir . DIRECTORY_SEPARATOR . 'OBJ.*');
            $obj_file_path = $child_dir . DIRECTORY_SEPARATOR . $obj_files[0];
            $obj_file_ext = pathinfo($obj_file_path, PATHINFO_EXTENSION);
            $obj_file_cmodel = get_cmodel_from_extension($obj_file_ext);

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

        // Get the first child's TN and push it up to replace the parent's TN.
        $uri_safe_first_child_pid = preg_replace('/:/', '_', $child_pids[0]);
        $ds_content_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            $uri_safe_first_child_pid . '_' . $dsid . '.' . $extensions[0]; // <- where?
        // download_datastream_content($child_pids[0], 'TN', $cmd, $log)
        // replace_datastream_content($child_pids[0], 'TN', $path_to_file, $cmd, $log)
    }
}