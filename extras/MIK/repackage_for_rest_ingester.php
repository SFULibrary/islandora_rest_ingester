<?php

/**
 * MIK post-write hook script to repackage single-file
 * packages so they can be used with the Islandora REST
 * Ingester (https://github.com/mjordan/islandora_rest_ingester).
 *
 * This script will convert the output of MIK's single-file toolchains:
 *
 sampleoutput/
 ├── foo.xml
 ├── foo.jpg
 ├── bar.xml
 ├── bar.jpg
 ├── baz.jpg
 └── baz.xml
 * to the REST Ingesters's input format for single-file content models:
 *
 sampleinput/
 ├── foo
 │   ├── MODS.xml
 │   └── OBJ.png
 ├── bar
 │   ├── MODS.xml
 │   └── OBJ.jpg
 └── baz
    ├── MODS.xml
    ├── TN.png
    └── OJB.jpg
 *
 * To use this script, register it in your MIK .ini file's WRITER section like this:
[WRITER]
postwritehooks[] = "/usr/bin/php extras/scripts/postwritehooks/repackage_for_rest_ingester.php"
 */

// MIK post-write hook script setup stuff.
$record_key = trim($argv[1]);
$children_record_keys_string = trim($argv[2]);
$config_path = trim($argv[3]);
$config = parse_ini_file($config_path, true);

// Define various file paths.
$mik_output_dir = $config['WRITER']['output_directory'];
$object_level_dir = $mik_output_dir . DIRECTORY_SEPARATOR . $record_key;
$files_with_record_key = glob($object_level_dir . ".*");

// Create an object-level directory
mkdir($object_level_dir);

// Move files into the object-level directory. Since each object will have
// one .xml file and one other file, we can use simple if/else logic.
foreach ($files_with_record_key as $file) {
    $pathinfo = pathinfo($file);
    if ($pathinfo['extension'] == 'xml') {
        $moved_file_path = $object_level_dir . DIRECTORY_SEPARATOR . 'MODS.xml';
    } else {
        $moved_file_path = $object_level_dir . DIRECTORY_SEPARATOR . 'OBJ.' . $pathinfo['extension'];
    }
    rename($file, $moved_file_path);
}
