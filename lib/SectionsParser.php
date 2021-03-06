<?php namespace Nfreear\MoodleBackupParser;

/**
 * Parser for 'section.xml' files within a Moodle backup MBZ.
 *
 * @copyright © Nick Freear, 13 May 2016.
 * @copyright © 2016 The Open University.
 */

class SectionsParser
{
    const SECTION_XML_FILE= '/section.xml';

    protected $input_dir;
    protected $sections_out  = [];
    protected $sections_skip = [];
    protected $sections_lookup = [];

    public function __construct($input_dir = null)
    {
        $this->input_dir = $input_dir;
    }

    public function parseSectionsSequences($input_dir, $xmlo_root)
    {
        $this->input_dir = $input_dir;

        $sections = $xmlo_root->information->contents->sections->section;
        $sections_out = [];
        $sections_skip = [];
        $sections_lookup = [];

        foreach ($sections as $section) {
            //$title = (string) $section->title;
            $dir = (string) $section->directory;
            $xml_path = $input_dir . '/' . $dir . self::SECTION_XML_FILE;

            $xmlo = simplexml_load_file($xml_path);
            $section_id = (int) $xmlo[ 'id' ];
            $sequence = (string) $xmlo->sequence;
            if ('' === $sequence || '$@NULL@$' === $sequence) {
                $sections_skip[] = $section_id;
                continue;
            }
            $sequence_r = explode(',', $sequence);

            $sp = $xmlo->plugin_format_studyplan_section;
            $sections_out [ "sid:$section_id" ] = (object) [
                'id' => $section_id,
                'week_id'  => (int) $sp->week[ 'id' ],
                'title'    => $sp ? (string) $sp->week->title : null,
                'is_on_course_home_page' => ($sp && (string) $sp->week->title),
                'activity_sequence' => $sequence_r,
            ];
            foreach ($sequence_r as $activity_mid) {
                $sections_lookup[ "mid:$activity_mid" ] = $section_id;
            }
        }

        $this->sections_out = $sections_out;
        $this->sections_skip = $sections_skip;
        $this->sections_lookup = $sections_lookup;

        return $sections_out;
    }

    public function getSections()
    {
        return (object) [
            'sections' => $this->sections_out,
            'lookup'   => $this->sections_lookup,
            'skip'     => $this->sections_skip,
        ];
    }
}
