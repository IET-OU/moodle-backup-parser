<?php namespace Nfreear\MoodleBackupParser\Generator;

/**
 * Abbreviation expander.
 *
 * @copyright © Nick Freear, 10 June 2016.
 * @copyright © 2016 The Open University.
 */

class AbbrHtml
{
    protected static $abbr = [];
    protected static $definitions = [];

    public static function setAbbreviations($abbr_array, $preg_quote = false)
    {
        foreach ($abbr_array as $abbr => $definition) {
            $abbr = $preg_quote ? preg_quote($abbr) : $abbr;
            $definition = htmlentities(preg_replace('/<.+?>/', '', $definition));
            self::$abbr[] = '/([>\s])(' . $abbr . ')([,;\?\.\s<&])/';
            self::$definitions[] = "$1<abbr title='$definition'>$2</abbr>$3";
        }
    }

    public static function abbr($html)
    {
        return preg_replace(self::$abbr, self::$definitions, $html);
    }
}
