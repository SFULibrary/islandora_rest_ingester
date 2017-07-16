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
use Monolog\Logger;

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
    ->require(true)
    ->describedAs("PID of the object's content model.");
$cmd->option('p')
    ->aka('parent')
    ->require(true)
    ->describedAs("PID of the object's parent collection, book, newspaper issue, compound object, etc.");
$cmd->option('n')
    ->aka('namespace')
    ->require(true)
    ->describedAs("Object's namespace.");
$cmd->option('o')
    ->aka('owner')
    ->require(true)
    ->describedAs("Object's owner.");
$cmd->option('s')
    ->aka('skip_empty')
    ->boolean()
    ->describedAs("Skip ingesting objects if the directory is empty. Default is false.");
$cmd->option('r')
    ->aka('relationship')
    ->default('isMemberOfCollection')
    ->describedAs('Predicate describing relationship of object to its parent. Default is isMemberOfCollection.');
$cmd->option('c')
    ->aka('checksum_type')
    ->default('SHA-1')
    ->describedAs('Checksum type to apply to datastreams. Use "none" to not apply checksums. Default is SHA-1.');
$cmd->option('e')
    ->aka('endpoint')
    ->default('http://localhost/islandora/rest/v1')
    ->describedAs('Fully qualified REST endpoing for the Islandora instance. Default is http://localhost/islandora/rest/v1.');
$cmd->option('u')
    ->aka('user')
    ->require(true)
    ->describedAs('REST user name.');
$cmd->option('t')
    ->aka('token')
    ->require(true)
    ->describedAs('REST authentication token.');
$cmd->option('l')
    ->aka('log')
    ->describedAs('Path to the log. Default is ./iipqa.log')
    ->default('./iipqa.log');
$cmd->option('l')
    ->aka('log')
    ->describedAs('Path to the log. Default is ./rest_ingest.log')
    ->default('./rest_ingest.log');

$path_to_log = $cmd['l'];
$log = new Monolog\Logger('Ingest via REST');
$log_stream_handler= new Monolog\Handler\StreamHandler($path_to_log, Logger::INFO);
$log->pushHandler($log_stream_handler);

$log->addInfo("ingest.php (endpoint " . $cmd['e'] . ") started at ". date("F j, Y, g:i a"));

$object_dirs = new FilesystemIterator($cmd[0]);
foreach($object_dirs as $object_dir) {
    ingest_object($object_dir->getPathname(), $cmd, $log);
}

$log->addInfo("ingest.php finished at ". date("F j, Y, g:i a"));

/**
 * Ingests an Islandora object.
 *
 * @param $dir string
 *   Absolute path to the directory containing the object's datastream files.
 * @param $cmd object
 *   The Commando Command object.
 * @param $log object
 *   The Monolog logger.
 */
function ingest_object($dir, $cmd, $log) {
    $client = new GuzzleHttp\Client();

    $mods_path = realpath($dir) . DIRECTORY_SEPARATOR . 'MODS.xml';
    if (file_exists($mods_path)) {
        $xml = simplexml_load_file($mods_path);
        $label = (string) current($xml->xpath('//mods:title'));
    }
    else {
        if (!$cmd['s']) {
           $label = $dir;
        }
        else {
            return;
        }
    }

    // Ingest Islandora object.
    $object_response = $client->request('POST', $cmd['e'] . '/object', [
        'form_params' => [
            'namespace' => $cmd['n'],
            'owner' => $cmd['o'],
            'label' => $label,
        ],
       'headers' => [
            'Accept' => 'application/json',
            'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
        ]
    ]);

    $object_response_body = $object_response->getBody();
    $object_response_body_array = json_decode($object_response_body, true);
    $pid = $object_response_body_array['pid'];

    $log->addInfo("Object $pid ingested from " . realpath($dir));

    // Add object's model.
    $model_response = $client->request('POST', $cmd['e'] . '/object/' .
        $pid . '/relationship', [
        'form_params' => [
            'uri' => 'info:fedora/fedora-system:def/model#',
            'predicate' => 'hasModel',
            'object' => $cmd['m'],
            'type' => 'uri',
        ],
       'headers' => [
            'Accept' => 'application/json',
            'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
        ]
    ]);

    // Add parent relationship.
    $parent_response = $client->request('POST', $cmd['e'] . '/object/' .
        $pid . '/relationship', [
        'form_params' => [
            'uri' => 'info:fedora/fedora-system:def/relations-external#',
            'predicate' => $cmd['r'],
            'object' => $cmd['p'],
            'type' => 'uri',
        ],
       'headers' => [
            'Accept' => 'application/json',
            'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
        ]
    ]);

    ingest_datastreams($pid, $dir, $cmd, $log);
}

/**
 * Reads the object-level directory and ingests each file as a datastream.
 *
 * @param $pid string
 *   The PID of the parent object.
 * @param $dir string
 *   Absolute path to the directory containing the object's datastream files.
 * @param $cmd object
 *   The Commando Command object.
 * @param $log object
 *   The Monolog logger.
 */
function ingest_datastreams($pid, $dir, $cmd, $log) {
    $client = new GuzzleHttp\Client();
    $mimes = new \Mimey\MimeTypes;
    $files = array_slice(scandir(realpath($dir)), 2);
    if (count($files)) {
        foreach ($files as $file) {
            $path_to_file = realpath($dir) . DIRECTORY_SEPARATOR . $file;
            $pathinfo = pathinfo($path_to_file);
            $dsid = $pathinfo['filename'];
            $mime_type = $mimes->getMimeType($pathinfo['extension']);

            $request = $cmd['e'] . '/object/' . $pid . '/datastream';
            $response = $client->request('POST', $request, [
                'multipart' => [
                    [
                        'name' => 'file',
                        'filename' => $pathinfo['basename'],
                        'contents' => fopen($path_to_file, 'r'),
                    ],
                    [
                        'name' => 'dsid',
                        'contents' => $dsid,
                    ],
                    [
                        'name' => 'checksumType',
                        'contents' => $cmd['c'],
                    ],
                ],
               'headers' => [
                    'Accept' => 'application/json',
                    'X-Authorization-User' => $cmd['u'] . ':' . $cmd['t'],
                ]
            ]);
            $log->addInfo("Object $pid datastream $dsid ingested from $path_to_file");
        }
    }
}
