<?php

/**
 * @file
 * Script to ingest objects via Islandora's REST interface.
 *
 * Requires https://github.com/discoverygarden/islandora_rest and
 * https://github.com/mjordan/islandora_rest_authen on the target
 * Islandora instance.
 *
 * Usage information is available by running 'php ingest.php --help'.
 *
 * sampleinput/
 * ├── foo
 * │   ├── MODS.xml
 * │   └── OBJ.png
 * ├── bar
 * │   ├── MODS.xml
 * │   └── OBJ.jpg
 * └── baz
 *    ├── MODS.xml
 *    └── OJB.jpg
 */

require_once 'vendor/autoload.php';

$cmd = new Commando\Command();
$cmd->option()
    ->require(true)
    ->describedAs('Ablsolute or relative path to a directory containing Islandora import packages. ' .
        'Trailing slash is optional.')
    ->must(function ($dir_path) {
        if (file_exists($dir_path)) {
            return true;
        } else {
            return false;
        }
    });
$cmd->option('m')
    ->aka('cmodel')
    ->describedAs("PID of the object's content model.");
$cmd->option('p')
    ->aka('parent')
    ->describedAs("Object's parent collection, book, newspaper issue, compound object, etc.");
$cmd->option('n')
    ->aka('namespace')
    ->describedAs("Object's namespace.");
$cmd->option('o')
    ->aka('owner')
    ->describedAs("Object's owner.");
$cmd->option('l')
    ->aka('label')
    ->describedAs("Object's label.");
$cmd->option('r')
    ->aka('relationship')
    ->default('isMemberOfCollection')
    ->describedAs('Predicate describing relationship of object to its parent. Default is isMemberOfCollection.');
$cmd->option('e')
    ->aka('endpoint')
    ->default('http://localhost/islandora/rest/v1')
    ->describedAs('Fully qualified REST endpoing for the Islandora instance. Default is http://localhost/islandora/rest/v1.');
$cmd->option('u')
    ->aka('user')
    ->describedAs('REST user.');
$cmd->option('t')
    ->aka('token')
    ->describedAs('REST authentication token.');

$object_dirs = new FilesystemIterator($cmd[0]);
foreach($object_dirs as $object_dir) {
    ingest_object($object_dir->getPathname(), $cmd);
}

/**
 * Ingests an Islandora object.
 *
 * @param $dir string
 *   Absolute path to the directory containing the object's datastream files.
 * @param $cmd object
 *   The Commando Command object.
 */
function ingest_object($dir, $cmd) {
    $client = new GuzzleHttp\Client();
    /*
    // Ingest Islandora object.
    $response = $client->request('POST', $cmd['e'] . '/object', [
        'form_params' => [
            'namespace' => '',
            'owner' => '',
            'label' => '',
        ],
       'headers' => [
            'Accept' => 'application/json',
            'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'];
        ]
    ]);
    */
    // $response = json_decode($response, true);
    $pid = 'islandora:1'; // testing
    $response = ''; // testing

/*
    // Add parent relationship.
    $response = $client->request('POST', $cmd['e'] . '/' . $response['pid'] . '/relationship', [
        'form_params' => [
            'uri' => '',
            'predicate' => $cmd['r'],
            'object' => '',
            'literal' => true,
        ],
       'headers' => [
            'Accept' => 'application/json',
            'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'];
        ]
    ]);
*/
    ingest_datastreams($response, $dir, $cmd);
}

/**
 * Reads the object-level directory and ingests each file as a datastream.
 *
 * @param $response array
 *   The Guzzle response as an associative array.
 * @param $dir string
 *   Absolute path to the directory containing the object's datastream files.
 * @param $cmd object
 *   The Commando Command object.
 */
function ingest_datastreams($response, $dir, $cmd) {
    $client = new GuzzleHttp\Client();
    $files = array_slice(scandir(realpath($dir)), 2);
    if (count($files)) {
        foreach ($files as $file) {
            $path_to_file = realpath($dir) . DIRECTORY_SEPARATOR . $file;
            /*
            $body = fopen($path_to_file, 'r');
            $request = $cmd['e'] . '/object/' . $response['pid'] . '/datastream';
            $response = $client->request('POST', $request, [
               'body' => $body,
               'headers' => [
                    'dsid' => '',
                    'label' => '',
                    'mimeType' => '',
                    'checksumType' => '',
                    'controlGroup' => 'M',
                    'Accept' => 'application/json',
                    'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'];
                ]
            ]);
            */
        }
    }

}
