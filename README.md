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

Currently, this tool ingests single-file Islandora objects (basic and large image, PDF, video, etc.), compound objects, and book objects.

#### Single-file objects

To prepare your content for ingesting, within the input directory, create subdirectories for each object. Within each, put a MODS.xml file and the file intended to be the OBJ datastream. This file should be named 'OBJ' and have whichever extension is appropriate for its content. Subdirectories that do not contain a MODS.xml file are skipped:

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

If a datastream already exists (for example, a TN created as a derivative), and there is a datastream file in the input directory that would otherwise trigger the ingestion of the datastrea, the datastream's content is updated from the file. The check for the existence of the datastream is logged (HTTP response code 200 if it exists, 404 if it does not).

#### Compound objects

For compound objects, each parent object should be in its own directory, and within that directory, each child should be in its own subdirectory. The sequence of the children within the compound is determined by the numbering of the child subdirectories:

```
input/
├── foo
│   ├── 1
│   │   ├── MODS.xml
│   │   └── OBJ.jpg
│   ├── 2
│   │   ├── MODS.xml
│   │   └── OBJ.jpg
│   └── MODS.xml
└── bar
    ├── 1
    │   ├── MODS.xml
    │   └── OBJ.tif
    ├── 2
    │   ├── MODS.xml
    │   └── OBJ.tif
    └── MODS.xml
```

#### Book objects

Each book object should be in its own directory, and within that directory, each page should be in its own subdirectory. The sequence of the pages within the book (and the labels of page objects) is determined by the numbering of the page subdirectories:

```
input/
├── foo
│   ├── 1
│   │   └── OBJ.tiff
│   ├── 2
│   │   └── OBJ.tiff
│   ├── 3
│   │   └── OBJ.tiff
│   ├── 4
│   │   └── OBJ.tiff
│   └── MODS.xml
└── bar
    ├── 1
    │   └── OBJ.tiff
    ├── 2
    │   └── OBJ.tiff
    ├── 3
    │   └── OBJ.tiff
    ├── 4
    │   └── OBJ.tiff
    └── MODS.xml
```

### Replacing objects by providing PIDs

The Islandora REST interface allows you to provide a full PID when ingesting an object. This means that we can replace/restore objects. This is not an update operation; if an object with the specified PID exists, it must be purged before the PID can be reused.

If the value of `-n` is a full (and valid) PID, an object with that PID will be created. If an object with that PID already exists, it will be skipped and logged. However, providing a full PID as the value of `-n` is only useful if your input directory contains a single object directory.

If you omit the `-n` option, the Ingester assumes that each object-level directory encodes the PID it should use when ingesting the object. Directory names should be the same as the PID, e.g. `test:245`. If your PIDs contain characters that may not be safe in filenames (for example, `:` on Windows), you can URL-endcode them (e.g., `test%3A245`); the Ingester will automatically decode them to get the PID.

Changing our examples above so that the object directories encode PIDs would look like this:

```
sampleinput/
 ├── foo:1
 │   ├── MODS.xml
 │   └── OBJ.png
 ├── bar:1
 │   ├── MODS.xml
 │   └── OBJ.jpg
 ├── empty:1
 └── baz:1
    ├── MODS.xml
    ├── TN.png
    └── OJB.jpg
```

URL-encoding the directory names as `foo%3A1`, `bar%3A1`, etc. would be valid as well.

### Running the script

`php ingest.php [options] INPUT_DIR`

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
     Object's namespace. If you provide a full PID, it will be used. If you do not include this option, the ingester assumes that each object-level input directory encodes the object PIDs, and will ingest objects using those PIDs.

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

Please note:

* when ingesting compound objects, the value of the `--cmodel` option should be "islandora:compoundCModel". Content models for child elements are assigned automatically based on the OBJ datastream file's extension.
* when ingesting books, the value of the `--cmodel` option should be "islandora:bookCModel".

### The log file

The log file records when the Islandora REST Ingester was run, what objects and datastreams it ingested, and checksum verifications (if checksums were enabled on datastreams). It also records any exceptions thown during REST requests:

```
[2017-07-17 07:12:35] Islandora REST Ingester.INFO: ingest.php (endpoint http://localhost:8000/islandora/rest/v1) started at July 17, 2017, 7:12 am [] []
[2017-07-17 07:12:35] Islandora REST Ingester.WARNING: /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/bar appears to be empty, skipping. [] []
[2017-07-17 07:12:35] Islandora REST Ingester.INFO: Object rest:172 ingested from /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/baz [] []
[2017-07-17 07:12:36] Islandora REST Ingester.INFO: Object rest:172 datastream MODS ingested from /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/baz/MODS.xml [] []
[2017-07-17 07:12:36] Islandora REST Ingester.INFO: SHA-1 checksum for object rest:172 datastream MODS verified. [] []
[2017-07-17 07:13:37] Islandora REST Ingester.INFO: Object rest:172 datastream OBJ ingested from /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/baz/OBJ.png [] []
[2017-07-17 07:13:37] Islandora REST Ingester.INFO: SHA-1 checksum for object rest:172 datastream OBJ verified. [] []
[2017-07-17 07:13:38] Islandora REST Ingester.INFO: Object rest:173 ingested from /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/foo [] []
[2017-07-17 07:13:38] Islandora REST Ingester.INFO: Object rest:173 datastream MODS ingested from /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/foo/MODS.xml [] []
[2017-07-17 07:13:38] Islandora REST Ingester.INFO: SHA-1 checksum for object rest:173 datastream MODS verified. [] []
[2017-07-17 07:13:48] Islandora REST Ingester.INFO: Object rest:173 datastream OBJ ingested from /home/mark/Documents/hacking/islandora_rest_scripts/ingest_islandora_objects_via_rest/testinput/foo/OBJ.jpg [] []
[2017-07-17 07:13:48] Islandora REST Ingester.INFO: SHA-1 checksum for object rest:173 datastream OBJ verified. [] []
[2017-07-17 07:13:48] Islandora REST Ingester.INFO: ingest.php finished at July 17, 2017, 7:13 am [] []
```

You can specify the location of the log file with the `-l` option.

## Maintainer

* [Mark Jordan](https://github.com/mjordan)

## Development and feedback

* If you discover a bug, or have a use case not documented here, open an issue.
* If you want to open a pull request, open an issue first.
  * Check code style with `./vendor/bin/phpcs --standard=PSR2 src`
  * Use the pull request template.

## License

The Unlicense
