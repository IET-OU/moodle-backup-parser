<?php namespace Nfreear\MoodleBackupParser;

/**
 * THE Parser.
 *
 * Parse files within a Moodle course backup 'MBZ' archive.
 *
 * @copyright Nick Freear, 20 April 2016.
 * @copyright Copyright 2016 The Open University.
 * @link  https://docs.moodle.org/27/en/Backup_and_restore_FAQ#Using_the_new_backup_format_.28experimental.29
 * @link  https://learn3.open.ac.uk/course/view.php?name=APPLAuD2016
 * @link  https://github.com/IET-OU/nnco/blob/master/themes/nnco/content/static-pages/about.htm
 */

use \Nfreear\MoodleBackupParser\Clean;

class Parser
{
    const INDEX_FILE    = '/.ARCHIVE_INDEX';
    const ROOT_XML_FILE = '/moodle_backup.xml';
    const COURSE_XML_FILE = '/course/course.xml';
    const FILES_XML_FILE= '/files.xml';

    protected $input_dir;
    protected $xmlo_root;
    protected $first_ordered;
    protected $metadata;
    protected $pages = [];
    protected $verbose = false;

    public function parse($input_dir, $first_ordered = null)
    {
        $this->input_dir = (string) $input_dir;
        $this->first_ordered = (object) $first_ordered;

        $xml_path = $input_dir . self::ROOT_XML_FILE;
        $this->xmlo_root = simplexml_load_file($xml_path);

        if (! $this->xmlo_root) {
            throw new Exception('simplexml fail on: ' . $xml_path);
        }

        $info = $this->xmlo_root->information;

        $this->metadata = (object) [
            'name' => (string) $info->name,
            'moodle_release' => (string) $info->moodle_release,
            'backup_release' => (string) $info->backup_release,
            'backup_date' => date('c', (int) $info->backup_date), # Unix timestamp;
            'wwwroot'     => (string) $info->original_wwwroot,
            'site_id'     => (string) $info->original_site_identifier_hash,
            'course_id'   => (int) $info->original_course_id,
            'course_format' => (string) $info->original_course_format,
            'course_fullname' => (string) trim($info->original_course_fullname),
            'course_shortname'=> (string) $info->original_course_shortname,
            // ...
            'backup_id'   => (string) $info->details->detail[ 'backup_id' ],
            'backup_type' => (string) $info->details->detail->type,  # 'course'
            'backup_format' => (string) $info->details->detail->format,
        ];

        return $this->parseActivities();
    }

    public function getMetaData()
    {
        return $this->metadata;
    }

    public function getPages()
    {
        return $this->pages;
    }

    protected function parseActivities()
    {
        $activities = $this->xmlo_root->information->contents->activities->activity;
        $this->metadata->count_activities = $activities->count();
        foreach ($activities as $act) {
            $modulename = (string) $act->modulename;
            /*if ($act->title === $this->first_ordered->title) {
                break;
            }*/
            switch ($modulename) {
                case 'page':
                    $this->parsePage($this->input_dir . '/' . (string) $act->directory);
                    break;
                default:
                    if ($this->verbose) {
                        printf("Pass. Module not currently supported: %s\n", (string) $act->directory);
                    }
                    break;
            }
        }
    }

    protected function parsePage($dir)
    {
        $xml_path = $dir . '/' . 'page.xml';
        $xmlo = simplexml_load_file($xml_path);
        $modid = (int) $xmlo[ 'moduleid' ];
        $xmlo = $xmlo->page;

        $page = (object) [
            'id' => (int) $xmlo[ 'id' ],
            'moduleid' => $modid,
            'name' => (string) $xmlo->name,
            'filename' => Clean::filename((string) $xmlo->name),
            'intro' => (string) $xmlo->intro,
            'content' => (string) html_entity_decode($xmlo->content),
            'contentformat' => (int) $xmlo->contentformat,
            'displayoptions' => unserialize((string) $xmlo->displayoptions),
            'revision' => (int) $xmlo->revision,
            'timemodified' => date('c', (int) $xmlo->timemodified),
            'links' => $this->pageLinks((string) $xmlo->content),
        ];
        $this->pages[] = $page;
    }

    protected function pageLinks($content)
    {
        $links = [];
        if (preg_match('/\$@URLVIEWBYID\*(?P<id>\d+)@\$/', $content, $matches)) {
            $links[ $matches[ 0 ]] = $matches[ 'id' ];
        }
        return $links;
    }
}
