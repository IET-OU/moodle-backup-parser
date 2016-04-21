<?php namespace Nfreear\MoodleBackupParser;

/**
 * THE Parser.
 *
 * Parse files within a Moodle course backup 'MBZ' archive.
 *
 * Initial limitations:
 *  - 'MBZ' archive file needs to be unzipped already.
 *  - Expecting the 'new' backup format ('.tar.gz' as opposed to '.zip')
 *  - Test source is Moodle 2.9.3 (Learn3.open.ac.uk)
 *
 * @copyright Nick Freear, 20 April 2016.
 * @copyright Copyright 2016 The Open University.
 * @link  https://docs.moodle.org/27/en/Backup_and_restore_FAQ#Using_the_new_backup_format_.28experimental.29
 * @link  https://learn3.open.ac.uk/course/view.php?name=APPLAuD2016
 * @link  https://github.com/IET-OU/nnco/blob/master/themes/nnco/content/static-pages/about.htm
 */


class Parser
{
    const ROOT_XML_FILE = 'moodle_backup.xml';

    protected $input_dir;
    protected $output_dir;
    protected $xmlo_root;
    protected $first_ordered;
    protected $metadata;
    protected $pages = [];

    public function parse($input_dir, $output_dir = null, $first_ordered = null)
    {
        $this->input_dir = (string) $input_dir;
        $this->output_dir = (string) $output_dir;
        $this->first_ordered = (object) $first_ordered;

        $xml_path = $input_dir . '/' . self::ROOT_XML_FILE;
        $this->xmlo_root = simplexml_load_file($xml_path);

        if (! $this->xmlo_root) {
            throw new Exception('simplexml fail on: ' . $xml_path);
        }

        $info = $this->xmlo_root->information;

        $this->metadata = (object) [
            'name' => (string) $info->name,
            'moodle_release' => (string) $info->moodle_release,
            'backup_date' => date('c', (int) $info->backup_date), # Unix timestamp;
            'wwwroot'     => (string) $info->original_wwwroot,
            'course_id'   => (int) $info->original_course_id,
            'course_format' => (string) $info->original_course_format,
            'course_fullname' => (string) trim($info->original_course_fullname),
            'course_shortname'=> (string) $info->original_course_shortname,
            // ...
            'backup_id'   => (string) $info->details->detail[ 'backup_id' ],
            'backup_type' => (string) $info->details->detail->type,  # 'course'
            'backup_format' => (string) $info->details->detail->format,
        ];
        #var_dump($this->metadata);

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
        #var_dump('Count activities: ', $activities->count());
        foreach ($activities as $act) {
            $modulename = (string) $act->modulename;
            /*if ($act->title === $this->first_ordered->title) {
                break;
            }*/
            switch ($modulename) {
                case 'page':
                    $this->parsePage($this->input_dir . '/' . (string) $act->directory);

                    //return;
                    break;
                default:
                    //var_dump('Pass. Module not currently supported:', (string) $act->directory);
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
            'filename' => $this->safeFilename((string) $xmlo->name),
            'intro' => (string) $xmlo->intro,
            'content' => (string) html_entity_decode($xmlo->content),
            'contentformat' => (int) $xmlo->contentformat,
            'displayoptions' => unserialize((string) $xmlo->displayoptions),
            'revision' => (int) $xmlo->revision,
            'timemodified' => date('c', (int) $xmlo->timemodified),
            'links' => $this->pageLinks((string) $xmlo->content),
        ];
        $this->pages[] = $page;
        //var_dump('PAGE:', $xml_path, $page);
    }

    protected function pageLinks($content)
    {
        $links = [];
        if (preg_match('/\$@URLVIEWBYID\*(?P<id>\d+)@\$/', $content, $matches)) {
            $links[ $matches[ 0 ]] = $matches[ 'id' ];
        }
        return $links;
    }

    protected function safeFilename($name)
    {
        $filename = str_replace('  -  ', '-', strtolower(trim($name)));
        $filename = str_replace(' - ', '-', $filename);
        $filename = str_replace(' ', '-', $filename);
        $filename = preg_replace('/[^\w\-]/', '', $filename);
        return $filename;
    }
}
