# Ingest Islandora Objects Via REST

Script to ingest Islandora objects using Islandora's REST interface.

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
 └── baz
    ├── MODS.xml
    └── OJB.jpg
```

### Running the script

`php ingest [options] INPUT_DIR`

```
INPUT_DIR
     Required. Ablsolute or relative path to a directory containing Islandora import packages. Trailing slash is optional.


-m/--cmodel <argument>
     PID of the object's content model.


-e/--endpoint <argument>
     Fully qualified REST endpoing for the Islandora instance. Default is http://localhost/islandora/rest/v1.


--help
     Show the help page for this command.


-l/--label <argument>
     Object's label.


-n/--namespace <argument>
     Object's namespace.


-o/--owner <argument>
     Object's owner.


-p/--parent <argument>
     Object's parent collection, book, newspaper issue, compound object, etc.


-r/--relationship <argument>
     Predicate describing relationship of object to its parent. Default is isMemberOfCollection.


-t/--token <argument>
     REST authentication token.


-u/--user <argument>
     REST user.
```

## Maintainer

* [Mark Jordan](https://github.com/mjordan)

## Development and feedback

Bug reports, use cases and suggestions are welcome. If you want to open a pull request, please open an issue first.

## License

The Unlicense
