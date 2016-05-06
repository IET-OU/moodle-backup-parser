[![Build status: Travis-CI][travis-icon]][travis-ci]

# IET-OU / moodle-backup-parser

PHP library to parse files within a [Moodle][] course backup `MBZ` archive.
Generate static pages compatible with [October][].

* <https://docs.moodle.org/29/en/Course_backup>

_NOTE: this is not a plugin for Moodle! It is a standalone library._

## Status

_License — to be confirmed!_

_This library is a work-in-progress – though with working unit tests!_

Initial limitations:

* `MBZ` archive file needs to be unzipped already;
* Expecting the ['_newer_' backup format][faq] (`.tar.gz` as opposed to `.zip`)
* Test source is Moodle _2.9.3—2.9.5_ (`Learn3.open.ac.uk`)
* See [todo list][todos].

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
    require_once './vendor/autoload.php';

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
[backup]: https://docs.moodle.org/29/en/Course_backup
[faq]: https://docs.moodle.org/27/en/Backup_and_restore_FAQ#Using_the_new_backup_format_.28experimental.29
[todos]: https://github.com/IET-OU/moodle-backup-parser/issues/1#issue-150009370
[Composer]: https://getcomposer.org/
[October]: http://octobercms.com/ "October CMS"
[iet]: http://iet.open.ac.uk/
[ou]: http://www.open.ac.uk/
