# Wwwision.ContentRepositoryDumper

CLI commands to dump and compare [Neos Content Repository](https://github.com/neos/content-repository) instances

## Installation

Install via composer:

    composer require wwwision/contentrepositorydumper

**Note:** There are currently **two different versions** of this package:
* The 7.x-releases are compatible with the Neos Content Repository version 7.x and 8.x+
* The 9.x-releases are compatible with the Neos Content Repository version 9.x

## Usage

Currently, this package comes with a single CLI command `cr:dump`:

    Dump the structure of the Content Repository to flat files in order to debug/compare them
    
    COMMAND:
    wwwision.contentrepositorydumper:cr:dump
    
    USAGE:
    ./flow cr:dump [<options>] <site>
    
    ARGUMENTS:
    --site               Name of the site node to dump (e.g. "neosdemo")
    
    OPTIONS:
    --dimensions         The dimensions to export in the format: "<key1>:<value1>,<value2>;<key2>:<value1>,...;..." (e.g. "language:de,en_UK;market:us").
                         If omitted, all dimensions will be included
    --path               Path to dump the file(s) to. This has to be the full path to an empty directory.
                         If omitted, files are dumped to "/Data/ContentRepositoryDump/<Timestamp>

### Example 1:

Exporting all nodes of all content dimensions to a folder `/Data/ContentRepositoryDump/<Timestamp>`:

    ./flow cr:dump neosdemo

### Example 2

Exporting only specific content dimensions of a custom site to a custom path

    ./flow cr:dump somesite --dimensions "lanuage:de,en;market:eu" --path Some/Custom/Path
