<?php namespace Nfreear\MoodleBackupParser\Generator;

/**
 * HTML helper functions, for October CMS static pages.
 *
 * @copyright © Nick Freear, 17 May 2016.
 * @copyright © 2016 The Open University.
 */

use Nfreear\MoodleBackupParser\Clean;
use Nfreear\MoodleBackupParser\Generator\AbbrHtml;

class Html
{
    const RESOURCE_PREFIX = '@@PLUGINRES@@';
    const ICON_HTML = '<i class="fa %s" aria-hidden="true"></i>';

    protected $wordwrap = 96;
    protected static $icons = [
        // File resource types.
        'doc'  => 'fa-file-word-o',
        'docx' => 'fa-file-word-o',
        'pdf'  => 'fa-file-pdf-o',
        'DEFAULT' => 'fa-file-text-o',

        // Activity module types.
        'folder'  => 'fa-folder-open-o',
        //...
        'url embed' => 'fa-video-camera',
    ];
    protected static $icon_html = self::ICON_HTML;
    protected $replacements = [];
    protected $uri_references = [];
    protected $metadata;

    public static function sectionHead($section, $idx)
    {
        $heading = $section->title ? "<h2>$section->title</h2>" : '';
        $cls = 'mod-section' . ($section->title ? '': ' anonymous');
        $sid = $section->id;
        return Clean::html("<div id='sect-$idx' data-sid='$sid' class='$cls'>$heading<ul>\n");
    }

    public static function activityPlaceholder($activity)
    {
        $mod_name = $activity->modulename;
        $name = $activity->name;
        return self::wrap($activity, "<i>$mod_name</i> $name", 'mod-placeholder', 'Placeholder');
    }

    public static function activityResource($resource)
    {
        $file = $resource->file;
        $ext = $file->fileext;
        $pre = self::RESOURCE_PREFIX;
        $icon = self::getResourceIcon($ext);
        return self::wrap($resource, "<a href='$pre/$file->filepath'>$icon$resource->name</a>", "ext-$ext", null, false);
    }

    public function setIconMap($icon_map, $icon_html = null, $enable = false)
    {
        self::$icons = $enable ? $icon_map : null;
        self::$icon_html = $icon_html ? $icon_html : self::ICON_HTML;
    }

    public static function getResourceIcon($type)
    {
        $icon_class = isset(self::$icons[ $type ]) ? self::$icons[ $type ] : self::$icons[ 'DEFAULT' ];
        return sprintf(self::$icon_html, $icon_class);
    }

    public static function getFontIcon($type)
    {
        $icon_class = isset(self::$icons[ $type ]) ? self::$icons[ $type ] : null;
        return $icon_class ? sprintf(self::$icon_html, $icon_class) : '';
    }

    public static function wrap($activity, $text, $cls = null, $title = null, $add_icon = true)
    {
        $mod_name = $activity->modulename;
        $mod_id   = $activity->moduleid;
        $icon_type = $cls ? "$mod_name $cls" : $mod_name;
        $icon  = $add_icon ? self::getFontIcon($icon_type) : '';
        $ptext = preg_replace('/(<[ap][^>]*>)/', '$1' . $icon, $text, 1, $count);
        $ptext = 0 === $count ? $icon . $text : $ptext;
        return Clean::html("<li class='mod-$mod_name $cls' data-mid='$mod_id' title='$title'>$ptext</li>");
    }

    public static function clean($html)
    {
        return Clean::html($html);
    }

    public function setMetaData($metadata)
    {
        $this->metadata = $metadata;
    }

    public function setReplacements($replace_r)
    {
        $this->replacements = $replace_r;
    }

    public function setURIReferences($references)
    {
        $regex_references = [];
        foreach ($references as $pattern => $path) {
            $regex = sprintf('/"(%s)"/', preg_quote($pattern));
            $uri = sprintf('"./%s" data-uri="$1"', $path);
            $regex_references[ $regex ] = $uri;
        }
        $this->uri_references = $regex_references;
    }

    public function setAbbreviations($abbr_array, $preg_quote = false)
    {
        return AbbrHtml::setAbbreviations($abbr_array, $preg_quote);
    }

    protected function expandURIsInHtml($html)
    {
        $patterns = array_keys($this->uri_references);
        $replacements = array_values($this->uri_references);
        return preg_replace($patterns, $replacements, $html);
    }

    public function replace($html)
    {
        $patterns = array_keys($this->replacements);
        $replacements = array_values($this->replacements);
        return preg_replace($patterns, $replacements, $html);
    }

    public function staticHtml($page)
    {
        $content = $page->content;
        $html = Clean::html($content);
        $html = $this->replace($html);
        $html = $this->expandURIsInHtml($html);
        $html = $this->wordwrap ? wordwrap($html, $this->wordwrap) : $html;
        $html = AbbrHtml::abbr($html);
        unset($page->content);
        unset($page->intro);
        $page->file_date = gmdate('c');

        if ($this->metadata) {
            $page->backup_name = $this->metadata->name;
            $page->backup_date = $this->metadata->backup_date;
            $page->source_url  = $this->metadata->course_url;
        }
        $template = <<<EOT
[viewBag]
title = "%title"
url = "%url"
layout = "default"
is_hidden = 0
navigation_hidden = 0
meta_title = "About X"
meta_description = "About page ..Y"
tagline = "...Z"
==
{% put keywords %}
about
{% endput %}

{% put bodyid %}
about
{% endput %}

%put_mbp_page_data
==
<div id="mbp-pg-content" class="%className" data-uri="%url">
%html
</div>

EOT;

        $put_mbp_page_data = null;
        if (false === strpos($page->url, 'sideblock') || (isset($page->filename) && false === strpos($page->filename, '_resources-'))) {
            $json_ish = preg_replace([ '/^\{/', '/\}$/' ], '', json_encode($page, JSON_PRETTY_PRINT));
            $put_mbp_page_data = <<<EOT
{% put mbp_page_data %}$json_ish{% endput %}
EOT;
        }

        return strtr($template, [
            '%className' => isset($page->modulename) ? "pg-mod-$page->modulename" : 'pg-other',
            '%title'=> $page->name,
            '%url'  => $page->url,
            '%html' => $html,
            //'%json' => json_encode($page, JSON_PRETTY_PRINT),
            '%put_mbp_page_data' => $put_mbp_page_data,
        ]);
    }
}
