<?php namespace Nfreear\MoodleBackupParser\Generator;

/**
 * HTML helper functions, for October CMS static pages.
 *
 * @copyright © Nick Freear, 17 May 2016.
 * @copyright © 2016 The Open University.
 */

use Nfreear\MoodleBackupParser\Clean;

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

    public function setIconMap($icon_map, $icon_html = self::ICON_HTML)
    {
        self::$icons = $icon_map;
        self::$icon_html = $icon_html;
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
        printf("Set replacements: %s\n", json_encode($replace_r, JSON_PRETTY_PRINT));
        $this->replacements = $replace_r;
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
        unset($page->content);
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
==
<div id="mbp-pg-content" class="%className" data-uri="%url">
%html
</div>

EOT;
        if (false === strpos($page->url, 'sideblock')) {
            $template .= <<<EOT
<script id="mbp-pg-data" type="application/json">
%json
</script>

EOT;
        }

        return strtr($template, [
            '%className' => isset($page->modulename) ? "pg-mod-$page->modulename" : 'pg-other',
            '%title'=> $page->name,
            '%url'  => $page->url,
            '%html' => $this->wordwrap ? wordwrap($html, $this->wordwrap) : $html,
            '%json' => json_encode($page, JSON_PRETTY_PRINT),
        ]);
    }
}
