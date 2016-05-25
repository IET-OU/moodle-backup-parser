<?php namespace Nfreear\MoodleBackupParser;

/**
 * Parse all 'resources' -- 'files.xml', Moodle folders, resources & URLs within a Moodle backup MBZ.
 *
 * @copyright © Nick Freear, 6 May 2016.
 * @copyright © 2016 The Open University.
 */

use \Nfreear\MoodleBackupParser\ObjectParser;

class FilesParser
{
    const FILES_XML_FILE= '/files.xml';

    const COURSE_REGEX = '/\$@COURSEVIEWBYID\*(?P<id>\d+)@\$/';
    const COURSE_URL = 'https://learn3.open.ac.uk/course/view.php?id=%s';

    const YOUTUBE_REGEX = '/youtube.com\/playlist\?list=(?P<id>[\w-]+)/';
    const YOUTUBE_EMBED = '<iframe src="https://www.youtube.com/embed/videoseries?list=%s" allowfullscreen="1"></iframe>';

    protected $input_dir;
    protected $object;
    protected $files = [];
    protected $url_lookup = [];

    public function __construct($input_dir = null)
    {
        $this->object = ObjectParser::getInstance();
        $this->input_dir = $input_dir;
    }

    public function parseFiles($input_dir)
    {
        $this->input_dir = $input_dir;

        $files = [];
        $xmlo = simplexml_load_file($this->input_dir . self::FILES_XML_FILE);
        foreach ($xmlo->file as $file) {
            if ('.' === (string) $file->filename) {
                continue;
            }
            $hash = (string) $file->contenthash;
            $file_id = (int) $file[ 'id' ];
            $files[ "fid:$file_id" ] = (object) [
                'id' => (int) $file_id,
                'filename' => (string) $file->filename,
                'filepath' => rawurlencode((string) $file->filename),
                'fileext'  => preg_replace('/.+\./', '', (string) $file->filename),
                'hashpath' => $this->input_dir . '/files/' . substr($hash, 0, 2) . '/' . $hash,
                'mimetype' => (string) $file->mimetype,
                'filesize' => (int) $file->filesize,
                'timemodified' => date('c', (int) $file->timemodified),
            ];
        }
        $this->files = $files;
    }

    public function parseResource($dir, $mid)
    {
        $resource = $this->object->parseObject($dir, 'resource', null);
        $xmlo = $this->object->loadXmlFile($dir, 'inforef');
        $file_ids = $xmlo->fileref->file->id;
        foreach ($file_ids as $id) {
            $file_id = (int) $id;
            if (isset($this->files[ "fid:$file_id" ])) {  // Use the first match!
                $resource->file = $this->files[ "fid:$file_id" ];
                break;
            }
        }
        //var_dump("RESOURCE:", $resource); exit;

        return $resource;
    }

    public function parseUrl($dir, $mid)
    {
        $url = $this->object->parseObject($dir, 'url', 'intro', [ 'externalurl', 'display' ]);
        $url->embed = null;
        $url->require_embed = (1 === (int) $url->display);  // Default: 5;
        $url->resolve_url = $url->externalurl;
        if (preg_match(self::COURSE_REGEX, $url->externalurl, $matches)) {
            $url->resolve_url = sprintf(self::COURSE_URL, $matches[ 'id' ]);
        }
        if (preg_match(self::YOUTUBE_REGEX, $url->externalurl, $matches)) {
            $url->embed = sprintf(self::YOUTUBE_EMBED, $matches[ 'id' ]);
        }

        //var_dump("URL:", $url); //exit;
        return $url;
    }

    public function getFiles()
    {
        return $this->files;
    }
}
