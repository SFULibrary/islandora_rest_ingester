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
 * See README.md for additional information.
 */

require_once 'vendor/autoload.php';
use Monolog\Logger;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

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
        $log->addWarning(realpath($dir) . " appears to be empty, skipping.");
        return;
    }

    // Ingest Islandora object.
    try {
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
    } catch (Exception $e) {
        if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException ) {
            $log->addError(Psr7\str($e->getRequest()));
            if ($e->hasResponse()) {
                $log->addError(Psr7\str($e->getResponse()));
                print Psr7\str($e->getResponse()) . "\n";
            }
            exit;
        }
    }

    $object_response_body = $object_response->getBody();
    $object_response_body_array = json_decode($object_response_body, true);
    $pid = $object_response_body_array['pid'];

    $log->addInfo("Object $pid ingested from " . realpath($dir));

    // Add object's model.
    try {
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
    } catch (Exception $e) {
        if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException ) {
            $log->addError(Psr7\str($e->getRequest()));
            if ($e->hasResponse()) {
                $log->addError(Psr7\str($e->getResponse()));
                print Psr7\str($e->getResponse()) . "\n";
            }
            exit;
        }
    }

    // Add parent relationship.
    try {
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
    } catch (Exception $e) {
        if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException ) {
            $log->addError(Psr7\str($e->getRequest()));
            if ($e->hasResponse()) {
                $log->addError(Psr7\str($e->getResponse()));
                print Psr7\str($e->getResponse()) . "\n";
            }
            exit;
        }
    }

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
    $files = array_slice(scandir(realpath($dir)), 2);
    if (count($files)) {
        foreach ($files as $file) {
            $path_to_file = realpath($dir) . DIRECTORY_SEPARATOR . $file;
            $pathinfo = pathinfo($path_to_file);
            $dsid = $pathinfo['filename'];

            try {
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
            } catch (Exception $e) {
                if ($e instanceof RequestException or $e instanceof ClientException or $e instanceof ServerException ) {
                    $log->addError(Psr7\str($e->getRequest()));
                    if ($e->hasResponse()) {
                        $log->addError(Psr7\str($e->getResponse()));
                        print Psr7\str($e->getResponse()) . "\n";
                    }
                    exit;
                }
            }

            if ($cmd['c'] != 'none') {
                $local_checksum = get_local_checksum($path_to_file, $cmd);
                $response_body = $response->getBody();
                $response_body_array = json_decode($response_body, true);
                if ($local_checksum == $response_body_array['checksum']) {
                    $log->addInfo($cmd['c'] . " checksum for object $pid datastream $dsid verified.");
                } else {
                    $log->addWarning($cmd['c'] . " checksum for object $pid datastream $dsid mismatch.");
                }
            }
        }
    }
}

/**
 * Generates a checksum for comparison to the one reported by Islandora.
 *
 * @param $path_to_file string
 *   The path to the file.
 * @param $cmd object
 *   The Commando Command object.
 *
 * @return string|bool
 *   The checksum value, false if no checksum type is specified.
 */
function get_local_checksum($path_to_file, $cmd) {
    switch ($cmd['c']) {
        case 'SHA-1':
            $checksum = sha1_file($path_to_file);
            break;
        // @todo: Add more checksum types.
        default:
            $checksum = false;
    }
    return $checksum;
}
