<?php namespace Nfreear\MoodleBackupParser\Test;

/**
 * Unit tests for the FilesParser.
 *
 * @copyright Nick Freear, 6 May 2016.
 */

use \Nfreear\MoodleBackupParser\Parser;
use \Nfreear\MoodleBackupParser\Generator\StaticPages;

define('TEST_FILES_DIR', __DIR__ . '/output/static-pages/files');

class FilesParserTest extends \PHPUnit_Framework_TestCase
{
    private $parser;
    private $generator;

    public function setup()
    {
        // Arrange
        printf("Setup moodle-backup-parser. %s\n", '');
        $this->parser = new Parser();
        $this->generator = new StaticPages();
    }

    public function testFiles()
    {
        $result = $this->parser->parse(TEST_INPUT_DIR);
        $files = $this->parser->getFiles();
        $file_count = $this->generator->putFiles(TEST_FILES_DIR, $files);

        printf("Count files:  %s\n", $file_count);

        $this->assertCount(2, $files, 'files');
        $this->assertCount($file_count, $files, 'file_count');
        $this->assertFileExists(TEST_FILES_DIR . '/' . 'Lorem-Ipsum.pdf');
    }
}
