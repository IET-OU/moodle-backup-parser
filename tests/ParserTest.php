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
use \Nfreear\MoodleBackupParser\Generator\StaticPages;
use \Nfreear\MoodleBackupParser\Test\Extend\TestCaseExtended;

define('TEST_INPUT_DIR', __DIR__ . '/fixtures/backup-moodle2');
define('TEST_OUTPUT_DIR', __DIR__ . '/output/static-pages');

class ParserTest extends TestCaseExtended
{
    protected $parser;
    protected static $parse_options = [
        'modulename' => 'page',
        'title' => 'Is APPLAuD for me?',
    ];
    protected static $generator_options = [
        'simple_activity_link' => [ ],
        'preg_replace_html' => [ ],
        'font_icon_map' => [ ],
        'treat_as_page' => [ ],
    ];
    protected static $verbose = false;

    public function setup()
    {
        // Arrange
        printf("Setup moodle-backup-parser. %s\n", '');
        $this->parser = new Parser();
    }

    public function testInput()
    {
        $this->assertFileExists(TEST_INPUT_DIR . Parser::ROOT_XML_FILE);
        $this->assertFileExists(TEST_INPUT_DIR . Parser::FILES_XML_FILE);
    }

    public function testParse()
    {
        // Arrange

        // Act
        $result = $this->parser->parse(TEST_INPUT_DIR, self::$parse_options);
        $metadata = $this->parser->getMetaData();

        if (self::$verbose) {
            var_dump('Meta-data: ', $metadata);
        } else {
            printf("Backup name:  %s\n", $metadata->name);
            printf("Count activities:  %s\n", $metadata->count_activities);
        }

        // Asserts
        $this->assertEquals('course', $metadata->backup_type, 'backup_type');
        $this->assertEquals('moodle2', $metadata->backup_format, 'backup_format');
        $this->assertRegExp(Parser::MBZ_FILE_REGEX, $metadata->name, 'name');
        $this->assertRegExp('/^(topics|studyplan)$/', $metadata->course_format, 'course_format');
        $this->assertUrlLike(null, $metadata->wwwroot, 'wwwroot');
        $this->assertRegExp('/^2\.9\.\d+\+? \(Build: 201\d/', $metadata->moodle_release, 'moodle_release');
        $this->assertIsHex(32, $metadata->backup_id, 'backup_id');
        $this->assertISODate($metadata->backup_date, 'backup_date');
        $this->assertGreaterThan(6, $metadata->count_activities, 'count_activities'); # 67;
        $this->assertGreaterThan(1, $metadata->course_id); # 300638;
    }

    public function testParsePages()
    {
        $result = $this->parser->parse(TEST_INPUT_DIR);
        $pages = $this->parser->getPages();

        $this->assertCount(3, $pages);

        $page = $pages[ 0 ];

        printf("Count pages:  %s\n", count($pages));

        $this->assertEquals(1, $page->id);
        $this->assertEquals('is-lorem-ipsum-for-me', $page->filename);
        $this->assertISODate($page->timemodified);
        $this->assertCount(2, $page->links);
        $this->assertCount(0, $page->files);
    }

    public function testStaticPages()
    {
        // Arrange
        $generator = new StaticPages();

        $this->parser->parse(TEST_INPUT_DIR);
        $activities = $this->parser->getActivities();
        $sections   = $this->parser->getSections();
        $metadata   = $this->parser->getMetaData();

        $generator->setOptions(self::$generator_options);
        $generator->setMetaData($metadata);
        $result = $generator->putContents(TEST_OUTPUT_DIR, $activities, $sections);

        printf("Handled activities:  %s\n", count($activities));

        $this->assertGreaterThan(2, count($activities), 'activities_count');  # 35;

        $this->thenTestOutput();
    }

    protected function thenTestOutput()
    {
        $this->assertFileExists(TEST_OUTPUT_DIR . '/-static-pages.yaml');
        $this->assertFileExists(TEST_OUTPUT_DIR . '/index.htm');
        $this->assertFileExists(TEST_OUTPUT_DIR . '/is-lorem-ipsum-for-me.htm'); # '/is-applaud-for-me.htm'

        $index= file_get_contents(TEST_OUTPUT_DIR . '/index.htm');
        $html = file_get_contents(TEST_OUTPUT_DIR . '/is-lorem-ipsum-for-me.htm');

        $this->assertStringStartsWith('[viewBag]', $html);
        $this->assertRegExp('/url = "\/[\w\-]+"/', $html);
        $this->assertRegExp('/<p>/', $html);

        $this->assertRegExp('/mod-label/', $index, 'mod_label');
        $this->assertRegExp('/data-mid=["\']\d+["\']/', $index, 'data_mid');

        $this->assertRegexp("/id='sect-\d+'/", $index, 'html ID section');
        $this->assertRegExp('/mod-section/', $index, 'mod_section');
        $this->assertRegExp('/data-sid=["\']\d+["\']/', $index, 'data-sid');
    }
}
