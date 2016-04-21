[![Build status: Travis-CI][travis-icon]][travis-ci]

# IET-OU / moodle-backup-parser

Parse files within a [Moodle][] course backup 'MBZ' archive.

* <https://docs.moodle.org/29/en/Course_backup>

Initial limitations:

* 'MBZ' archive file needs to be unzipped already.
* Expecting the 'new' backup format ('.tar.gz' as opposed to '.zip')
* Test source is Moodle 2.9.3 (`Learn3.open.ac.uk`)


## Installation

Install and test using Git and [Composer][],

```sh
    git clone https://github.com/IET-OU/moodle-backup-parser
    composer install
    composer test
```


---
© 2016 [The Open University][ou]. ([Institute of Educational Technology][iet])


[travis-icon]: https://travis-ci.org/IET-OU/moodle-backup-parser.svg
[travis-ci]: https://travis-ci.org/IET-OU/moodle-backup-parser
[Moodle]: https://moodle.org/
[Composer]: https://getcomposer.org/
[iet]: http://iet.open.ac.uk/
[ou]: http://www.open.ac.uk/
