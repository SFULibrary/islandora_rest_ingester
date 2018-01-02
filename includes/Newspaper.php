<?php

namespace islandora_rest_client\ingesters;

/**
 * Islandora REST Ingester Newspaper Issue (islandora:newspaperIssueCModel) class.
 */
class NewspaperIssue extends Ingester
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

    public function packageObject($dir)
    {
        // Get the issue object's label from the MODS.xml file. If there
        // is no MODS.xml file in the input directory, move on to the
        // next directory.
        $mods_path = realpath($dir) . DIRECTORY_SEPARATOR . 'MODS.xml';
        if (!$label = get_value_from_mods($mods_path, '//mods:titleInfo/mods:title', $this->log)) {
            $this->log->addWarning(realpath($dir) . " appears to be empty, skipping.");
            return;
        }

        // Get dateIssued from MODS.
        if (!$date_issued = get_value_from_mods($mods_path, '//mods:originInfo/mods:dateIssued', $this->log)) {
            $this->log->addWarning(realpath($dir) . " appears to be empty, skipping.");
            return;
        }

        $issue_pid = $this->ingestObject($dir, $label);

        $cmodel = get_cmodel_from_cmodel_txt(realpath($dir)) ?
            get_cmodel_from_cmodel_txt(realpath($dir)) : $this->command['m'];
        $cmodel_params = array(
            'uri' => 'info:fedora/fedora-system:def/model#',
            'predicate' => 'hasModel',
            'object' => $cmodel,
            'type' => 'uri',
        );
        $this->addRelationship($issue_pid, $cmodel_params);

        $parent_params = array(
            'uri' => 'info:fedora/fedora-system:def/relations-external#',
            'predicate' => 'isMemberOf',
            'object' => $this->command['p'],
            'type' => 'uri',
        );
        $this->addRelationship($issue_pid, $parent_params);

        $date_issued_params = array(
            'uri' => 'http://islandora.ca/ontology/relsext#',
            'predicate' => 'dateIssued',
            'object' => $date_issued,
            'type' => 'none',
        );
        $this->addRelationship($issue_pid, $date_issued_params);

        // Newspaper Solution Pack and Newspaper Batch use islandora_newspaper_get_issues()
        // to get the numer of newspaper issues. REST doesn't have access to that, so we
        // query Solr instead.
        $num_issues_solr_query = "RELS_EXT_isMemberOf_uri_s:info%3Afedora/" .
            urlencode($this->command['p']) . "+AND+RELS_EXT_isSequenceNumber_literal_s%3A*&wt=json&fl=PID";
        if ($num_issues_solr_response = query_solr($num_issues_solr_query, $this->command, $this->log)) {
            $num_issues_solr_response = json_decode($num_issues_solr_response, true);
            $num_issues = (int) $num_issues_solr_response['response']['numFound'];
        } else {
            continue;
        }
        $issue_sequence_number_params = array(
            'uri' => 'http://islandora.ca/ontology/relsext#',
            'predicate' => 'isSequenceNumber',
            'object' => $num_issues + 1,
            'type' => 'none',
        );
        $this->addRelationship($issue_pid, $issue_sequence_number_params);

        // Ingest MODS.xml.
        $this->ingestDatastreams($issue_pid, $dir);

        if ($issue_pid) {
            $message = "Object $issue_pid ingested from " . realpath($dir);
            $this->log->addInfo($message);
            print $message . "\n";
        }

        // Get the child (page) directories and ingest each one.
        $page_pids = array();
        $page_ingester = new \islandora_rest_client\ingesters\Single($this->log, $this->command);
        $page_dirs = new \FilesystemIterator(realpath($dir));
        foreach ($page_dirs as $page_dir) {
            $page_dir = $page_dir->getPathname();

            if (!is_dir($page_dir)) {
                continue;
            }

            // Get page/sequence number from directory name.
            // @todo: If there's a MODS file, get the label from it?
            $page_dir_name = pathinfo($page_dir, PATHINFO_FILENAME);
            $page_label = 'Page ' . $page_dir_name;
            $page_pid = $page_ingester->ingestObject($page_dir, $page_label);

            // If $page_pid is FALSE, log error and continue.
            if (!$page_pid) {
                $this->log->addError("Page object at " . realpath($page_dir) . " not ingested");
                continue;
            }

            // Keep track of page PIDS so we can get the first one's TN later.
            array_push($page_pids, $page_pid);


            if (!$page_cmodel = get_cmodel_from_cmodel_txt(realpath($page_dir))) {
                $page_cmodel = 'islandora:newspaperPageCModel';
            }
            $cmodel_params = array(
                'uri' => 'info:fedora/fedora-system:def/model#',
                'predicate' => 'hasModel',
                'object' => $page_cmodel,
                'type' => 'uri',
            );
            $page_ingester->addRelationship($page_pid, $cmodel_params);

            // ingestDatastreams() must come after the object's content
            // model is set in order to derivatives to be generated.
            $page_ingester->ingestDatastreams($page_pid, $page_dir);

            $parent_params = array(
                'uri' => 'info:fedora/fedora-system:def/relations-external#',
                'predicate' => 'isMemberOf',
                'object' => $issue_pid,
                'type' => 'uri',
            );
            $page_ingester->addRelationship($page_pid, $parent_params);

            $is_page_of_params = array(
                'uri' => 'http://islandora.ca/ontology/relsext#',
                'predicate' => 'isPageOf',
                'object' => $issue_pid,
                'type' => 'uri',
            );
            $page_ingester->addRelationship($page_pid, $is_page_of_params);

            $is_sequence_number_params = array(
                'uri' => 'http://islandora.ca/ontology/relsext#',
                'predicate' => 'isSequenceNumber',
                'object' => $page_dir_name,
                'type' => 'none',
            );
            $page_ingester->addRelationship($page_pid, $is_sequence_number_params);

            $is_page_number_params = array(
                'uri' => 'http://islandora.ca/ontology/relsext#',
                'predicate' => 'isPageNumber',
                'object' => $page_dir_name,
                'type' => 'none',
            );
            $page_ingester->addRelationship($page_pid, $is_page_number_params);

            $is_section_params = array(
                'uri' => 'http://islandora.ca/ontology/relsext#',
                'predicate' => 'isSection',
                'object' => '1',
                'type' => 'none',
            );
            $page_ingester->addRelationship($page_pid, $is_section_params);

            if ($page_pid) {
                $message = "Object $page_pid ingested from " . realpath($page_dir);
                $this->log->addInfo($message);
                print $message . "\n";
            }
        }

        // Give the issue object the TN from its first page.
        if ($path_to_tn_file = download_datastream_content($page_pids[0], 'TN', $this->command, $this->log)) {
            $this->ingestDatastreams($issue_pid, $dir, 'TN', $path_to_tn_file);
            unlink($path_to_tn_file);
        } else {
            $this->log->addWarning("TN for newspaper issue object $issue_pid not replaced with TN for first page");
        }
    }
}
