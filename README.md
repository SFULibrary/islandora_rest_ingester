# Ingest Islandora Objects Via REST

Script to ingest simple Islandora objects using Islandora's REST interface.

## Requirements

* On the target Islandora instance
  * [Islandora REST](https://github.com/discoverygarden/islandora_rest)
  * [Islandora REST Authen](https://github.com/mjordan/islandora_rest_authen)
* On the system where the script is run
  * PHP 5.5.0 or higher.
  * [Composer](https://getcomposer.org)

## Installation

* Clone the Git repo
* `cd ingest_islandora_objects_via_rest`
* `php composer.phar install` (or equivalent on your system, e.g., `./composer install`)

## Overview and usage

### Preparing content for ingestion

```
sampleinput/
 ├── foo
 │   ├── MODS.xml
 │   └── OBJ.png
 ├── bar
 │   ├── MODS.xml
 │   └── OBJ.jpg
 ├── emtpy
 └── baz
    ├── MODS.xml
    └── OJB.jpg
```

### Running the script

`php ingest [options] INPUT_DIR`

For example,

`php ingest.php -s -e http://localhost:8000/islandora/rest/v1 -m islandora:sp_basic_image -p rest:collection -n rest -o admin -u admin -t admin testinput`

```
INPUT_DIR
     Required. Ablsolute or relative path to a directory containing Islandora import packages. Trailing slash is optional.

-c/--checksum_type <argument>
     Checksum type to apply to datastreams. Use "none" to not apply checksums. Default is SHA-1.


-m/--cmodel <argument>
     Required. PID of the object's content model.


-e/--endpoint <argument>
     Fully qualified REST endpoing for the Islandora instance. Default is http://localhost/islandora/rest/v1.


-n/--namespace <argument>
     Required. Object's namespace.


-o/--owner <argument>
     Required. Object's owner.


-p/--parent <argument>
     Required. PID of the object's parent collection, book, newspaper issue, compound object, etc.


-r/--relationship <argument>
     Predicate describing relationship of object to its parent. Default is isMemberOfCollection.


-s/--skip_empty
     Skip ingesting objects if the directory is empty. Default is false.


-t/--token <argument>
     Required. REST authentication token.

-u/--user <argument>
     Required. REST user name.

--help
     Show the help page for this script.
```

## Maintainer

* [Mark Jordan](https://github.com/mjordan)

## Development and feedback

Bug reports, use cases and suggestions are welcome. If you want to open a pull request, please open an issue first.

## License

The Unlicense
