<?php namespace Nfreear\MoodleBackupParser;

/**
 * Parser for generic 'objects' within a Moodle backup MBZ.
 *
 * @copyright © Nick Freear, 20 May 2016.
 * @copyright © 2016 The Open University.
 */

class ObjectParser
{
    const LINK_REGEX = '/\$@(?P<type>[A-Z]+)\*(?P<id>\d+)@\$/';
    const FILE_REGEX = '/(?P<attr>(src|href))="@@PLUGINFILE@@(?P<path>[^"]+)/';

    protected $input_dir;

    protected static $instance;

    public static function getInstance()
    {
        if (! self::$instance) {
            self::$instance = new ObjectParser();
        }
        return self::$instance;
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

    public function parseObject($dir, $modtype, $content = 'intro', $extra = [])
    {
        $xmlo = $this->loadXmlFile($dir, $modtype);
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
            'timemodified' => gmdate('c', (int) $xmlo->timemodified),
            'links' => $this->parseLinks((string) $xmlo->{ $content }),
            'files' => $this->parseFileLinks((string) $xmlo->{ $content }),
        ];
        foreach ($extra as $key) {
            $object[ $key ] = (string) $xmlo->{ $key };
        }
        return (object) $object;
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
