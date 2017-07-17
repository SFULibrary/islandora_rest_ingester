# Islandora REST Ingester

Script to ingest simple Islandora objects using Islandora's REST interface.

## Requirements

* On the target Islandora instance
  * [Islandora REST](https://github.com/discoverygarden/islandora_rest)
  * [Islandora REST Authen](https://github.com/mjordan/islandora_rest_authen)
* On the system where the script is run
  * PHP 5.5.0 or higher.
  * [Composer](https://getcomposer.org)

## Installation

1. `git clone https://github.com/mjordan/islandora_rest_ingester.git`
1. `cd islandora_rest_ingester`
1. `php composer.phar install` (or equivalent on your system, e.g., `./composer install`)

## Overview and usage

### Preparing content for ingestion

Currently, this tool only ingests single-file Islandora objects (basic and large image, PDF, video, etc.). To prepare your content for ingesting, within the input directory, create subdirectories for each object. Within each, put a MODS.xml file and the file intended to be the OBJ datastream. This file should be named 'OBJ' and have whichever extension is appropriate for its content. Subdirectories that do not contain a MODS.xml file are skipped:

```
sampleinput/
 ├── foo
 │   ├── MODS.xml
 │   └── OBJ.png
 ├── bar
 │   ├── MODS.xml
 │   └── OBJ.jpg
 ├── empty
 └── baz
    ├── MODS.xml
    ├── TN.png 
    └── OJB.jpg
```

You may add whatever additional datastream files you want to the object directories. For example, if you want to pregenerate FITS output for each object, you can add 'TECHMD.xml' and it will be ingested as the TECHMD datastream. Another common use for ingesting pregenerated datastream files is custom thumbnails.

### Running the script

`php ingest [options] INPUT_DIR`

For example,

`php ingest.php -l mylog.log -e http://localhost:8000/islandora/rest/v1 -m islandora:sp_basic_image -p rest:collection -n rest -o admin -u admin -t admin testinput`

```
INPUT_DIR
     Required. Ablsolute or relative path to a directory containing Islandora import packages. Trailing slash is optional.

-e/--endpoint <argument>
     Fully qualified REST endpoing for the Islandora instance. Default is http://localhost/islandora/rest/v1.

-m/--cmodel <argument>
     Required. PID of the object's content model.

-n/--namespace <argument>
     Required. Object's namespace.

-o/--owner <argument>
     Required. Object's owner.

-p/--parent <argument>
     Required. PID of the object's parent collection, book, newspaper issue, compound object, etc.

-r/--relationship <argument>
     Predicate describing relationship of object to its parent. Default is isMemberOfCollection.

-c/--checksum_type <argument>
     Checksum type to apply to datastreams. Use "none" to not apply checksums. Default is SHA-1.

-l/--log/--log <argument>
     Path to the log. Default is ./rest_ingest.log

-t/--token <argument>
     Required. REST authentication token.

-u/--user <argument>
     Required. REST user name.

--help
     Show the help page for this script.
```

### The log file

The log file records when the Islandora REST Ingester was run, what objects and datastreams it ingested, and checksum verifications (if checksums were enabled on datastreams). It also records any empty directories it encounters and exceptions thown during REST requests:

```
[2017-07-17 07:12:35] Ingest via REST.INFO: ingest.php (endpoint http://localhost:8000/islandora/rest/v1) started at July 17, 2017, 7:12 am [] []
[2017-07-17 07:12:35] Ingest via REST.WARNING: /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/bar appears to be empty, skipping. [] []
[2017-07-17 07:12:35] Ingest via REST.INFO: Object rest:172 ingested from /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/baz [] []
[2017-07-17 07:12:36] Ingest via REST.INFO: Object rest:172 datastream MODS ingested from /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/baz/MODS.xml [] []
[2017-07-17 07:12:36] Ingest via REST.INFO: SHA-1 checksum for object rest:172 datastream MODS verified. [] []
[2017-07-17 07:13:37] Ingest via REST.INFO: Object rest:172 datastream OBJ ingested from /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/baz/OBJ.png [] []
[2017-07-17 07:13:37] Ingest via REST.INFO: SHA-1 checksum for object rest:172 datastream OBJ verified. [] []
[2017-07-17 07:13:38] Ingest via REST.INFO: Object rest:173 ingested from /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/foo [] []
[2017-07-17 07:13:38] Ingest via REST.INFO: Object rest:173 datastream MODS ingested from /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/foo/MODS.xml [] []
[2017-07-17 07:13:38] Ingest via REST.INFO: SHA-1 checksum for object rest:173 datastream MODS verified. [] []
[2017-07-17 07:13:48] Ingest via REST.INFO: Object rest:173 datastream OBJ ingested from /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/foo/OBJ.jpg [] []
[2017-07-17 07:13:48] Ingest via REST.INFO: SHA-1 checksum for object rest:173 datastream OBJ verified. [] []
[2017-07-17 07:13:48] Ingest via REST.INFO: ingest.php finished at July 17, 2017, 7:13 am [] []
```

You can specify the location of the log file with the `-l` option.

## Maintainer

* [Mark Jordan](https://github.com/mjordan)

## Development and feedback

Bug reports, use cases and suggestions are welcome. If you want to open a pull request, please open an issue first.

## License

The Unlicense
