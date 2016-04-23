<?php namespace Nfreear\MoodleBackupParser;

/**
 * 'Clean' and 'safe' string utility functions.
 *
 * @copyright Nick Freear, 20 April 2016.
 * @copyright Copyright 2016 The Open University.
 */

class Clean
{
    public static function filename($name)
    {
        $filename = str_replace('  -  ', '-', strtolower(trim($name)));
        $filename = str_replace(' - ', '-', $filename);
        $filename = str_replace(' ', '-', $filename);
        $filename = preg_replace('/[^\w\-]/', '', $filename);
        return $filename;
    }

    public static function html($content)
    {
        return preg_replace('/style="[^"]*"/', '', $content);
    }
}
