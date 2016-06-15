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

    public static function setAbbreviations($abbr_array, $quote = false)
    {
        foreach ($abbr_array as $abbr => $definition) {
            $abbr = $quote ? preg_quote($abbr) : $abbr;
            $definition = htmlentities($definition);
            self::$abbr[] = '/([>\s])' . $abbr . '([,;\?\.\s<])/';
            self::$definitions[] = "$1<abbr title='$definition'>$abbr</abbr>$2";
        }
    }

    public static function abbr($html)
    {
        return preg_replace(self::$abbr, self::$definitions, $html);
    }
}
