<?php namespace Nfreear\MoodleBackupParser;

/**
 * Parser for 'files.xml' within a Moodle backup MBZ.
 *
 * @copyright Nick Freear, 6 May 2016.
 */

class FilesParser
{
    const FILES_XML_FILE= '/files.xml';

    protected $input_dir;
    protected $files = [];

    public function __construct($input_dir = null)
    {
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
            $files[] = (object) [
                'id' => (int) $file[ 'id' ],
                'filename' => (string) $file->filename,
                'hashpath' => $this->input_dir . '/files/' . substr($hash, 0, 2) . '/' . $hash,
                'mimetype' => (string) $file->mimetype,
                'filesize' => (int) $file->filesize,
                'timemodified' => date('c', (int) $file->timemodified),
            ];
        }
        $this->files = $files;
    }


    public function getFiles()
    {
        return $this->files;
    }
}
