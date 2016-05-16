<?php namespace Nfreear\MoodleBackupParser;

/**
 * THE Parser.
 *
 * Parse files within a Moodle course backup 'MBZ' archive.
 *
 * @copyright Nick Freear, 20 April 2016.
 * @copyright Copyright 2016 The Open University.
 * @link  https://learn3.open.ac.uk/course/view.php?name=APPLAuD2016
 * @link  https://github.com/IET-OU/nnco/blob/master/themes/nnco/content/static-pages/about.htm
 */

use \Nfreear\MoodleBackupParser\Clean;
use \Nfreear\MoodleBackupParser\FilesParser;
use \Nfreear\MoodleBackupParser\SectionsParser;

class Parser
{
    const INDEX_FILE    = '/.ARCHIVE_INDEX';
    const ROOT_XML_FILE = '/moodle_backup.xml';
    const COURSE_XML_FILE = '/course/course.xml';
    const FILES_XML_FILE= '/files.xml';

    const MBZ_FILE_REGEX = '/^backup-moodle2-course-\d+-\w+-20\d{6}-\d{4}-nu\.mbz$/';
    const LINK_REGEX = '/\$@(?P<type>[A-Z]+)\*(?P<id>\d+)@\$/';
    const FILE_REGEX = '/(?P<attr>(src|href))="@@PLUGINFILE@@(?P<path>[^"]+)/';

    protected $input_dir;
    protected $xmlo_root;
    protected $first_ordered;
    protected $metadata;
    protected $pages = [];
    protected $activities = []; // THE sequence.
    protected $files;
    protected $sections;
    protected $verbose = false;

    public function __construct()
    {
        $this->files = new FilesParser();
        $this->sections = new SectionsParser();
    }

    /**
     * @param string Parse the MBZ contents of the input directory.
     * @return object Meta-data.
     */
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

        $this->sections->parseSectionsSequences($this->input_dir, $this->xmlo_root);

        $this->parseActivities();
        $this->files->parseFiles($this->input_dir);

        return $this->getMetaData();
    }

    /**
     * @return object
     */
    public function getMetaData()
    {
        return $this->metadata;
    }

    public function getFiles()
    {
        return $this->files->getFiles();
    }

    public function getSections()
    {
        return $this->sections->getSections();
    }

    /**
     * @return array
     */
    public function getPages()
    {
        return $this->pages;
    }

    /**
     * @return array
     */
    public function getActivities()
    {
        return $this->activities;
    }

    protected function parseActivities()
    {
        $activities = $this->xmlo_root->information->contents->activities->activity;
        $this->metadata->count_activities = $activities->count();
        foreach ($activities as $act) {
            $modulename = (string) $act->modulename;
            $mid = (int) $act->moduleid;
            /*if ($act->title === $this->first_ordered->title) {
                break;
            }*/
            switch ($modulename) {
                case 'label':
                    $this->parseLabel($act->directory, $mid);
                    break;
                case 'page':
                    $this->parsePage($act->directory, $mid);
                    break;
                default:
                    if ($this->verbose) {
                        printf("Pass. Module not currently supported: %s\n", (string) $act->directory);
                    }
                    $this->activities[ "mid:$mid" ] = $this->parseObject($act->directory, $modulename, null);
                    break;
            }
        }
    }

    protected function parseObject($dir, $modtype, $content = 'intro', $extra = [])
    {
        $xml_path = $this->input_dir . '/' . (string) $dir . '/' . $modtype . '.xml';
        $xmlo = simplexml_load_file($xml_path);
        $context = (int) $xmlo[ 'contextid' ];
        $modid = (int) $xmlo[ 'moduleid' ];
        $modname = (string) $xmlo[ 'modulename' ];
        $xmlo = $xmlo->{ $modtype };

        $object = [
            'id' => (int) $xmlo[ 'id' ],
            'moduleid' => $modid,
            'modulename' => $modname,
            'name' => (string) $xmlo->name,
            'filename' => Clean::filename((string) $xmlo->name),
            'intro' => (string) $xmlo->intro,
            'content' => $content ? (string) html_entity_decode($xmlo->{ $content }) : null,
            'contentformat' => $content ? (int) $xmlo->{ $content . 'format' } : null,
            'timemodified' => date('c', (int) $xmlo->timemodified),
            'links' => $this->parseLinks((string) $xmlo->{ $content }),
            'files' => $this->parseFileLinks((string) $xmlo->{ $content }),
        ];
        foreach ($extra as $key) {
            $object[ $key ] = (string) $xmlo->{ $key };
        }
        return (object) $object;
    }

    protected function parseLabel($dir, $mid)
    {
        $this->activities[ "mid:$mid" ] = $this->parseObject($dir, 'label', 'intro');
    }

    protected function parsePage($dir, $mid)
    {
        $page = $this->parseObject($dir, 'page', 'content', [ 'revision', 'displayoptions' ]);
        $this->pages[] = $this->activities[ "mid:$mid" ] = $page;
    }

    /**
     * PAGEVIEWBYID; URLVIEWBYID; FOLDERVIEWBYID; OUBLOGVIEW (..?)
     * @return array
     */
    protected function parseLinks($content)
    {
        $links = [];
        if (preg_match_all(self::LINK_REGEX, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $links[ $match[ 'type' ] .'*'. $match[ 'id'] ] = $match[ 'id' ];
            }
        }
        return $links;
    }

    /**
     * Parse links to files and embedded images – @@PLUGINFILE@@/path to file.pdf | jpg
     * @return array
     */
    protected function parseFileLinks($content)
    {
        $file_links = [];
        if (preg_match_all(self::FILE_REGEX, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $file_links[] = $match[ 'path' ];
            }
        }
        return $file_links;
    }
}
