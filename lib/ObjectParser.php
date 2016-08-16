<?php namespace Nfreear\MoodleBackupParser;

/**
 * Parser for generic 'objects' within a Moodle backup MBZ.
 *
 * @copyright © Nick Freear, 20 May 2016.
 * @copyright © 2016 The Open University.
 */

class ObjectParser
{
    const MBZ_URI_REGEX = '/\$@(?P<type>[A-Z]+)\*(?P<id>\d+)@\$/';
    const HTTP_REGEX = '/(?P<uri>https?:\/\/[^"]+)/';
    const FILE_REGEX = '/(?P<attr>(src|href))="@@PLUGINFILE@@(?P<path>[^"]+)/';

    protected $input_dir;
    protected $uri_references = [];
    protected $content_uris = [];
    protected $activity_rename = [];

    /**
     * @var The *Singleton* instance.
     */
    private static $instance;

    public static function getInstance()
    {
        if (! self::$instance) {
            self::$instance = new ObjectParser();
        }
        return self::$instance;
    }

    private function __construct()
    {
        //noop.
    }

    public function setInputDir($input_dir)
    {
        $this->input_dir = (string) $input_dir;
    }

    public function getInputDir()
    {
        return $this->input_dir;
    }

    public function loadXmlFile($dir, $modtype)
    {
        $xml_path = $this->input_dir . '/' . (string) $dir . '/' . $modtype . '.xml';
        $xmlo = simplexml_load_file($xml_path);
        return $xmlo;
    }

    /** Was: 'setReplaceName'
    */
    public function setActivityRename($activity_rename)
    {
        $this->activity_rename = $activity_rename;
    }

    protected function activityRename($xmlo)
    {
        $replace_count = 0;
        $replace = $this->activity_rename;
        $name = preg_replace(array_keys($replace), array_values($replace), (string) $xmlo->name, $limit = -1, $replace_count);
        #$name = preg_replace('/.*Why do I want to do this.*/', 'Explore how I want to do this', (string) $xmlo->name, $limit = -1, $replace_count);
        return (object) [
            'name' => $name,
            'original_name' => $replace_count ? (string) $xmlo->name : null,
            'is_replace_name' => (bool) $replace_count,
        ];
    }

    public function parseObject($dir, $modtype, $content = 'intro', $extra = [])
    {
        $xmlo = $this->loadXmlFile($dir, $modtype);
        $context = (int) $xmlo[ 'contextid' ];
        $modid = (int) $xmlo[ 'moduleid' ];
        $modname = (string) $xmlo[ 'modulename' ];
        $xmlo = $xmlo->{ $modtype };

        $obj_name = $this->activityRename($xmlo);

        #$replace_count = 0;
        #$name = preg_replace('/.*Why do I want to do this.*/', 'Explore how I want to do this', (string) $xmlo->name, $limit = -1, $replace_count);

        $object = (object) [
            'id' => (int) $xmlo[ 'id' ],
            'moduleid' => $modid,
            'modulename' => $modname,
            'name' => (string) $obj_name->name,
            'original_name' => $obj_name->original_name,
            'filename' => Clean::filename($obj_name->name),
            'intro' => (string) $xmlo->intro,
            'content' => $content ? (string) html_entity_decode($xmlo->{ $content }) : null,
            'contentformat' => $content ? (int) $xmlo->{ $content . 'format' } : null,
            'timemodified' => gmdate('c', (int) $xmlo->timemodified),
            'links' => $this->parseLinks((string) $xmlo->{ $content }),
            'files' => $this->parseFileLinks((string) $xmlo->{ $content }),
        ];
        #if ($replace_count) {
        #    $object->original_name = (string) $xmlo->name;
        #}
        foreach ($extra as $key) {
            $object->{ $key } = (string) $xmlo->{ $key };
        }
        $this->addUriReference($object);

        return $object;
    }

    protected function addURIReference($activity)
    {
        if ('label' === $activity->modulename) {
            //return;
        }
        if (in_array($activity->modulename, [ 'page', 'folder', 'url' ])) {
            $key = sprintf('$@%sVIEWBYID*%d@$', strtoupper($activity->modulename), $activity->moduleid);
            $this->uri_references[ $key ] = $activity->filename;
            if ('url' === $activity->modulename) {
                $this->uri_references[ $key ] = $activity->externalurl;
            }
        }
        return $activity;
    }

    /**
    * @return array  Get array of references to each parsed 'page', 'folder' & 'url'.
    */
    public function getURIReferences()
    {
        return $this->uri_references;
    }

    /**
    * @return array  Get array of URLs parsed from the content.
    */
    public function getContentURIs()
    {
        return $this->content_uris;
    }

    /**
     * @return array Get array of MBZ archive URIs - PAGEVIEWBYID; URLVIEWBYID; FOLDERVIEWBYID; OUBLOGVIEW (..?)
     */
    protected function parseLinks($content)
    {
        $links = [];
        if (preg_match_all(self::MBZ_URI_REGEX, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $links[ $match[ 'type' ] .'*'. $match[ 'id'] ] = $match[ 'id' ];
            }
        }

        if (preg_match_all(self::HTTP_REGEX, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->content_uris[] = $match[ 'uri' ];
            }
        }

        return $links;
    }

    /**
     * @return array Get array of parsed links to files and embedded images – @@PLUGINFILE@@/path to file.pdf | jpg
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
