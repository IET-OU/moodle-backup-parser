<?php namespace Nfreear\MoodleBackupParser\Test;

/**
 * Unit tests for the Parser.
 *
 * @copyright Nick Freear, 20 April 2016.
 * @copyright Copyright 2016 The Open University.
 *
 * @link https://phpunit.de/manual/current/en/appendixes.assertions.html
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

use \Nfreear\MoodleBackupParser\Parser;
use \Nfreear\MoodleBackupParser\StaticPages;

define('TEST_INPUT_DIR', __DIR__ . '/fixtures/backup-moodle2');
define('TEST_OUTPUT_DIR', __DIR__ . '/output/static-pages');

class ParserTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;
    protected $verbose = false;

    public function setup()
    {
        // Arrange
        printf("Setup moodle-backup-parser. %s\n", '');
        $this->parser = new Parser();
    }

    public function testParse()
    {
        // Arrange

        // Act
        $result = $this->parser->parse(TEST_INPUT_DIR, [
            'modulename' => 'page',
            'title' => 'Is APPLAuD for me?',
        ]);
        $metadata = $this->parser->getMetaData();

        if ($this->verbose) {
            var_dump('Meta-data: ', $metadata);
        } else {
            printf("Backup name:  %s\n", $metadata->name);
            printf("Count activities:  %s\n", $metadata->count_activities);
        }

        // Asserts
        $this->assertEquals('course', $metadata->backup_type, 'backup_type');
        $this->assertEquals('moodle2', $metadata->backup_format, 'backup_format');
        $this->assertRegExp('/^backup-moodle2-course-\d+-\w+-\d{8}-\d+-nu\.mbz$/', $metadata->name, 'name');
        $this->assertRegExp('/^(topics|studyplan)$/', $metadata->course_format, 'course_format');
        $this->assertRegExp('/^https?:\/\/\w+/', $metadata->wwwroot, 'wwwroot');
        $this->assertRegExp('/^2\.9\.\d+\+? \(Build: 201/', $metadata->moodle_release, 'moodle_release');
        $this->assertRegExp('/^[\da-f]{32}$/', $metadata->backup_id, 'backup_id');
        $this->assertRegExp('/^201\d-\d{2}-\d{2}T../', $metadata->backup_date, 'backup_date');
        $this->assertGreaterThan(6, $metadata->count_activities, 'count_activities'); # 67;
        $this->assertGreaterThan(1, $metadata->course_id); # 300638;
        $this->assertFileExists(TEST_INPUT_DIR . Parser::ROOT_XML_FILE);
        $this->assertFileExists(TEST_INPUT_DIR . Parser::FILES_XML_FILE);
    }

    public function testStaticPages()
    {
        // Arrange
        $dumper = new StaticPages();

        $this->parser->parse(TEST_INPUT_DIR);
        $pages = $this->parser->getPages();
        $result = $dumper->putContents(TEST_OUTPUT_DIR, $pages);

        printf("Count pages:  %s\n", count($pages));

        $this->assertGreaterThan(1, count($pages), 'pages_count');  # 35;
        $this->assertFileExists(TEST_OUTPUT_DIR . '/index.htm');
        $this->assertFileExists(TEST_OUTPUT_DIR . '/is-lorem-ipsum-for-me.htm'); # '/is-applaud-for-me.htm'

        $html = file_get_contents(TEST_OUTPUT_DIR . '/is-lorem-ipsum-for-me.htm');

        $this->assertStringStartsWith('[viewBag]', $html);
        $this->assertRegExp('/url = "\/[\w\-]+.htm"/', $html);
        $this->assertRegExp('/<p>/', $html);
    }
}
