# Islandora REST Ingester

Command-line tool to ingest Islandora objects using Islandora's REST interface.

## Requirements

* On the target Islandora instance
  * [Islandora REST](https://github.com/discoverygarden/islandora_rest)
  * [Islandora REST Authen](https://github.com/mjordan/islandora_rest_authen)
  * Optionally, [Islandora REST Extras](https://github.com/mjordan/islandora_rest_extras) (see "Generating DC XML" below for more information).
* On the system where the script is run
  * PHP 5.5.0 or higher.
  * [Composer](https://getcomposer.org)

## Installation

1. `git clone https://github.com/mjordan/islandora_rest_ingester.git`
1. `cd islandora_rest_ingester`
1. `php composer.phar install` (or equivalent on your system, e.g., `./composer install`)

## Overview and usage

### Use cases

[Islandora Batch](https://github.com/Islandora/islandora_batch), [Islandora Book Batch](https://github.com/Islandora/islandora_book_batch), and [Islandora Newspaper Batch](https://github.com/Islandora/islandora_newspaper_batch) are the standard go-to tools for ingesting large amounts of content into Islandora. Other batch ingest modules also exist, such as [Islandora Compound Batch](https://github.com/MarcusBarnes/islandora_compound_batch). The command-line interfaces to these tools enable ingestion of thousands of objects at a time and also allow for scripted ingests, for example in automated workflows. But, they all need to be run as `drush` commands on the Islandora server.

The Islandora REST Ingester offers the ability to ingest content from any location that has HTTP access to your Islandora server. Some use cases for this ability include:

* the content is prepared by external partners (service providers, other libraries, etc.) and you want to allow them to ingest that content
* for security policy reasons, it is problematic to have people logging into your Islandora server to run `drush` commands
* during batch ingest, you will need to have enough disk space on your Islandora server for both the raw input data and the copies in Islandora created during ingestion (in other words, double the disk space taken up by your content)
* in automated ingestion workflows, moving content from where it is being digitized and processed to the filesystem of your Islandora server is problematic

Secondarily, ingestion tools that use Islandora's REST interface demonstrate the potential for the creation of desktop tools with graphical user interfaces (!) for ingesting content into Islandora, and for thinking about strategies and tools for batch ingesting content into Islandora CLAW, which has a REST interface.

### When not to use the REST Ingester

One significant advantage that the `drush`-based batch modules have over the Islandora REST Ingester is that they can ingest datastream files that exceed the Islandora server's maximum file upload setting. This setting is configurable but has practical limits. The best method for ingesting a video object whose OBJ is 3 GB is to use Islandora Batch's `drush` interface. Because the Islandora REST Ingester ingests objects over HTTP, it is also succeptible to this maxiumum file size.

The Islandora REST Ingester provides an option, `--max_file_size`, that will skip ingesting any datastream above the specified number of megabytes. All datastreams skipped for this reason are logged.

### Preparing content for ingestion

Currently, this tool ingests single-file Islandora objects (basic and large image, PDF, video, etc.), collection objects, compound objects, book objects, and newspaper issue objects (not newspaper objects).

#### Single-file objects

Single-file objects include all content models that have no child objects. To prepare your content for ingesting, within the input directory, create subdirectories for each object. Within each, put a MODS.xml file and the file intended to be the OBJ datastream. This file should be named 'OBJ' and have whichever extension is appropriate for its content. Subdirectories that do not contain a MODS.xml file are skipped:

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

You may add whatever additional datastream files you want to the object directories. For example, if you want to pregenerate FITS output for each object, you can add `TECHMD.xml` and it will be ingested as the TECHMD datastream. Another common use for ingesting pregenerated datastream files is custom thumbnails.

If a datastream already exists (for example, a TN created as a derivative), and there is a datastream file in the input directory that would otherwise trigger the ingestion of the datastream, the datastream's content is updated from the file. The check for the existence of the datastream is logged (HTTP response code 200 if it exists, 404 if it does not).

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
    │   ├── cmodel.txt
    │   └── OBJ.bin
    └── MODS.xml
```

In this example, the file 'cmodel.txt' contains the PID of the content model to assign to the child object (see "Specifying the content model" below for more information).

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

Page directories can contain OCR.txt files or any other datastream files.

#### Newspaper issue objects

Newspaper issues are arranged the same way as books. Each issue should be in its own directory, and within that directory, each page should be in its own subdirectory. The sequence of the pages within the issue (and the labels of page objects) is determined by the numbering of the page subdirectories:

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

-s/--state <argument>
     Object state. Default is A (active). Allowed values are I (inactive) and D (deleted).

-p/--parent <argument>
     Required. PID of the object's parent collection, book, newspaper issue, compound object, etc.

-r/--relationship <argument>
     Predicate describing relationship of object to its parent. Default is isMemberOfCollection.

-c/--checksum_type <argument>
     Checksum type to apply to datastreams. Use "none" to not apply checksums. Default is SHA-1.

-z/--max_file_size <argument>
     Maximum size, in MiB, of datastream files to ingest. If a file is larger than this, its datastream is not ingested. Default is 500 MiB.

-l/--log/--log <argument>
     Path to the log. Default is ./rest_ingest.log

-t/--token <argument>
     Required. REST authentication token.

-u/--user <argument>
     Required. REST user name.

-d/--delete_input
     Whether or not to delete the input files for an object after they have been successfully ingested.

--help
     Show the help page for this script.
```

Please note:

* when ingesting compound objects, the value of the `--cmodel` option should be "islandora:compoundCModel".
* when ingesting books, the value of the `--cmodel` option should be "islandora:bookCModel".
* when ingesting newspaper issues, the value of the `--cmodel` option should be "islandora:newspaperIssueCModel", and the value of the `--parent` option should be the PID of the newspaper object. You do not need to include the `--relationship` argument.
* operating system junk files 'Thumbs.db' and 'DS_Store' are ignored.

### Specifying the content model

The `--cmodel` option tells the ingest.php script which ingester class to invoke for each object in the input directory. A default (paged) content model is applied to pages in books and newspaper issues, and the content model for each child element in a compound object is assigned based on the OBJ datastream file's extension. If the content model cannot be assigned from the extension, the child object is not ingested.

There are situations where you may want to assign an object's content model explicitly. For example, some content models do not use OBJ datastreams, such as islandora:entityCModel and islandora:personCModel. Some solution packs do not rely on a specific set of file extensions to define their OBJ content models, such as the Binary Object Solution Pack.

The content model for any object can be overridden by the presence of a file called 'cmodel.txt' within the object directory. This file contains the PID of the desired content model. See the example in `sampledata/single/binary/cmodel.txt`, which contains

```
islandora:binaryObjectCModel
```
This content model is used instead of the one provided in the `--cmodel` option.

### Generating DC XML

All Fedora objects are assigned a default DC datastream that contains only the object label and its PID. Islandora generates richer DC XML from the MODS (or other XML) datastream either via XML Forms if the object is ingested using the Web interface or via one of the batch ingest modules. Islandora REST bypasses both, so objects ingested via REST only get the default Fedora DC XML datastream.

To generate DC from MODS or another XML datastream, install and enable the [Islandora REST Extras](https://github.com/mjordan/islandora_rest_extras) module.

### Adding extra relationships

All relationships defining content models, collection membership, and parent/page or parent/child relationships are added to objects automatically, but additional relationships can be added to objects by specifying them in a file named "relationships.json" within the object-level input directory. The relationships are expressed in a JSON structure like this:

```javascript
{
  "relationships": [
    {
      "uri": "info:fedora/fedora-system:def/relations-external#",
      "predicate": "isMemberOfCollection",
      "object": "myother:collection",
      "type": "uri"
    },
    {
      "uri": "info:fedora/fedora-system:def/relations-external#",
      "predicate": "isMemberOfCollection",
      "object": "yetanother:collection",
      "type": "uri"
    }
  ]
}
```
This relationships.json file will add the object to two additional collections, `myother:collection` and `yetanother:collection`.

### Replacing objects by providing PIDs

The Islandora REST interface allows you to provide a full PID when ingesting an object, allowing us to replace/restore objects. This is not an update operation; if an object with the specified PID exists, it must be purged before the PID can be reused.

If you omit the `--namespace` option, the Ingester assumes that each object-level directory encodes the PID it should use when ingesting the object. Directory names should be the same as the PID, e.g. `test:245`. If your PIDs contain characters that may not be safe in filenames (for example, `:` on Windows), you can URL-endcode them (e.g., `test%3A245`); the Ingester will automatically decode them to get the PID.

Note that this only works for top-level objects in the input directory; pages, and children of compound objects, cannot reuse PIDS.

Changing our examples above so that the object directories encode PIDs would look like this:

```
pidsample/
 ├── foo:1
 │   ├── MODS.xml
 │   └── OBJ.png
 ├── bar:1
 │   ├── MODS.xml
 │   ├── foxml.xml
 │   └── OBJ.jpg
 └── baz:1
    ├── MODS.xml
    ├── TN.png
    └── OJB.jpg
```

URL-encoding the directory names as `foo%3A1`, `bar%3A1`, etc. would be valid as well.

The ingest command should omit the `--namespace` option. For example, the following command will ingest the three objects in the above sample directory and assign each the PID encoded in the object-level directory:

`php ingest.php -l mylog.log -e http://localhost:8000/islandora/rest/v1 -m islandora:sp_basic_image -p test:collection -o admin -u admin -t admin pidsample`

Note that the restored object's owner, label, and state are assigned like they are for any other ingested object. However, if a 'foxml.xml' file is present in the object's input directory (like in the 'bar:1' object above), the owner, label, and state are taken from it.

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

### Sample content

The directory `sampledata` provides samples that are intended to illustrate how input should be arranged, and to let you try ingesting objects quickly. All objects are from Simon Fraser University's Islandora instance at http://digital.lib.sfu.ca; a few are concocted, such as the same binary object.

* single file objects: to ingest these three objects (two editorial cartoons and one binary object), run the command `php ingest.php -l mylog.log -e http://localhost:8000/islandora/rest/v1 -m islandora:sp_large_image_cmodel -p restingester:collection -n mynamespace -o admin -u admin -t admintoken sampledata/single`
* compound objects: to ingest these two objects (two postcards), run `php ingest.php -l mylog.log -e http://localhost:8000/islandora/rest/v1 -m islandora:compoundCModel -p restingester:collection -n mynamespace -o admin -u admin -t admintoken sampledata/compound`
* book: to ingest the sample book (there is only one, and to reduce the size of the sample data it only contains pages 1-4 and 17-19), run `php ingest.php -l mylog.log -e http://localhost:8000/islandora/rest/v1 -m islandora:bookCModel  -p restingester:collection -n mynamespace -o admin -u admin -t admintoken sampledata/book`
* newspaper issues: to ingest the two sample newspaper issues, create a newspaper object and run the command `php ingest.php -l mylog.log -e http://localhost:8000/islandora/rest/v1 -m islandora:newspaperIssueCModel -p my:newspaper -n mynamespace -o admin -u admin -t admintoken sampledata/newspaper`

## Ingesting custom content models

You can extend this tool to ingest objects that have content models not already represented, or override the default functionality, by doing the following:

1. mapping a content-model to an Ingester class
1. writing a PHP class that extends `islandora_rest_client\ingesters\Ingester`

### The content-model - Ingester class mapping

You can define custom mappings between content models and Ingester classes in a file named `cmodel_classmap.txt` in the same directory as `ingest.php`. This file should contain one mapping per line, and each line should have two columns separated by a tab. In the left column is the content model PID and in the right column is the class name:

```
islandora:foo   MyIngester
islandora:bar   Example
```
### Extending the base Ingester class

Custom Ingester class files must be placed in the `includes` directory. An example annotate Ingester is provided at `includes/Example.php`. After you put new class files in the `includes` directory, be sure to run `composer dump-autoload` to update the application's classmap.

## Integrating the Islandora REST Ingester with other tools

### Move to Islandora Kit

[Move to Islandora Kit](https://github.com/MarcusBarnes/mik)'s output can be used as the REST Ingester's input, except for its output for single-file objects. However, MIK can be configured to output single-file objects in the required format as follows:

1. copy `extras/MIK/repackage_for_rest_ingester.php` to MIK's post-write hook script directory (`extras/scripts/postwritehooks`)
1. register the script in your MIK .ini file's `[WRITER]` section as you would any other post-write hook script: `postwritehooks[] = "/usr/bin/php extras/scripts/postwritehooks/repackage_for_rest_ingester.php"`

If you would rather not copy the script to the MIK directory, provide a full path in the .ini file entry to its location.

### Islandora Import Package QA Tool

The [Islandora Import Package QA Tool](https://github.com/mjordan/iipqa) can validate the REST Ingester's input. Since the REST Ingester's input for single-file objects differs from Islandora Batch's, iipqa uses a custom value for its `--content_model` option, `single_rest_ingester`. Also, when validating compound objects, include the `--skip_structure` option.

### Automating ingests

The Islandora REST Ingester works well within scripted jobs. For example, you could schedule the script below to run overnight, in order to ingest newspaper issues prepared during the previous day. In this example, the ingest packsges are produced by the Move to Islandora Kit, they are then validated by the Islandora Ingest Package QA Tool, and finally, are ingested useing the REST Ingester. If either MIK or the iipqa fail, the script exits before the Ingester in run.

```bash
#!/bin/bash
#######################################################################
# Sample bash script to automate ingestion of content into Islandora. #
# using the Move to Islandora Kit, Islandora Ingest Package QA Tool,  #
# and the Islandora REST Ingester.                                    #
#                                                                     #
# Usage: ./sample_scripted_workflow.sh                                #
#######################################################################

# 'set -e' tells the shell script to stop running if any commands
# within it exit with a non-0 value.
set -e

# Change into the MIK directory and run MIK. The .ini file includes
# tells MIK to write its output to /tmp/sample_packages. Also,
# we run MIK in 'realtime' input validation mode, so it skips
# packages with malformed input.
cd /path/to/mik
php mik -c sample_config.ini

# Delete log files, or better yet move them somewhere for analysis
# in case something goes wrong.
rm /tmp/sample_packages/*.log

# Change into the Islandora Import Package QA Tool and run it.
# We add the --strict option so it exists with 1 if any packages
# have errors. We tell it this so the the next step, running
# drush to ingest the content, does not happen.
cd /path/to/iipqa
php iipqa --strict -m newspapers -l /tmp/sample_iipaq.log /tmp/sample_packages

# Change to the Islandora REST Ingester directory and run it.
cd /path/to/rest_ingester
php ingest.php -l mylog.log -e http://localhost:8000/islandora/rest/v1 -m islandora:newspaperIssueCModel -p my:newspaper -n mynamespace -o admin -u admin -t admintoken /tmp/sample_packages
```

## Maintainer

* [Mark Jordan](https://github.com/mjordan)

## Development and feedback

* If you discover a bug, or have a use case not documented here, open an issue.
* If you want to open a pull request, open an issue first.
  * Check code style with `./vendor/bin/phpcs --standard=PSR2 ingest.php `and `./vendor/bin/phpcs --standard=PSR2 includes`
  * Use the pull request template.

## License

The Unlicense
