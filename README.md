[![Build status: Travis-CI][travis-icon]][travis-ci]

# IET-OU / moodle-backup-parser

PHP library to parse files within a [Moodle][] course backup `MBZ` archive.

* <https://docs.moodle.org/29/en/Course_backup>

Initial limitations:

* `MBZ` archive file needs to be unzipped already;
* Expecting the '_new_' backup format (`.tar.gz` as opposed to ``.zip`)
* Test source is Moodle 2.9.3 (`Learn3.open.ac.uk`)

## Todos

* See [this list][todos].

## Installation

Install and test using Git and [Composer][],

```sh
    git clone https://github.com/IET-OU/moodle-backup-parser
    composer install
    composer test
```

## Example

```php
<?php
    require_once './moodle-backup-parser/vendor/autoload.php';

    $parser = new \Nfreear\MoodleBackupParser\Parser();
    $dumper = new \Nfreear\MoodleBackupParser\StaticPages();

    $result = $parser->parse('./backup');

    $result = $dumper->putContents('./static_pages', $parser->getPages());

    printf("End. Parsed:  %s\n", $parser->getMetaData()->name);
```


---
© 2016 [The Open University][ou] ([Institute of Educational Technology][iet]).


[travis-icon]: https://travis-ci.org/IET-OU/moodle-backup-parser.svg
[travis-ci]: https://travis-ci.org/IET-OU/moodle-backup-parser "Build status – Travis-CI"
[Moodle]: https://moodle.org/
[Composer]: https://getcomposer.org/
[iet]: http://iet.open.ac.uk/
[ou]: http://www.open.ac.uk/
