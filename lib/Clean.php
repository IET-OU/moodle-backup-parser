<?php namespace Nfreear\MoodleBackupParser;

/**
 * 'Clean' and 'safe' string utility functions.
 *
 * @copyright Nick Freear, 20 April 2016.
 * @copyright Copyright 2016 The Open University.
 */

class Clean
{
    /**
     * @param string
     * @return string
     */
    public static function filename($name)
    {
        $filename = str_replace('  -  ', '-', strtolower(trim($name)));
        $filename = str_replace(' - ', '-', $filename);
        $filename = str_replace(' ', '-', $filename);
        $filename = preg_replace('/[^\w\-]/', '', $filename);
        return $filename;
    }

    /**
     * @param string
     * @return string
     */
    public static function html($content)
    {
        $html = preg_replace('/style="[^"]*"/', '', $content);
        $html = preg_replace('/(color|face|size)="[^"]*"/', '', $html);
        $html = preg_replace('/&#160;/', ' ', $html);

        //$html = preg_replace('/&/', '&amp;', $html);
        //$html = preg_replace('/> (\w)/', '>$1', $html);
        //$html = htmlentities($html, ENT_XML1);
        return $html;
    }
}
