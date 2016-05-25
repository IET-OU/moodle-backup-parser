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

    protected $wordwrap = 96;
    protected static $icons = [
        'doc' => '<i class="fa fa-file-word-o" aria-hidden="true"></i>',
        'docx' => '<i class="fa fa-file-word-o" aria-hidden="true"></i>',
        'pdf' => '<i class="fa fa-file-pdf-o" aria-hidden="true"></i>',
        'DEFAULT' => '<i class="fa fa-file-text-o" aria-hidden="true"></i>',
    ];

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
        $icon = self::getFontIcon($ext);
        return self::wrap($resource, "<a href='$pre/$file->filepath'>$icon$resource->name</a>", "ext-$ext");
    }

    public static function getFontIcon($type)
    {
        return isset(self::$icons[ $type ]) ? self::$icons[ $type ] : self::$icons[ 'DEFAULT' ];
    }

    public static function wrap($activity, $text, $cls = null, $title = null)
    {
        $mod_name = $activity->modulename;
        $mod_id   = $activity->moduleid;
        return Clean::html("<li class='mod-$mod_name $cls' data-mid='$mod_id' title='$title'>$text</li>");
    }

    public static function clean($html)
    {
        return Clean::html($html);
    }

    public function staticHtml($page)
    {
          $content = $page->content;
          $html = Clean::html($content);
          unset($page->content);
          $page->file_date = date('c');
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
<script id="mbp-pg-data" type="application/json">
%json
</script>

EOT;
        return strtr($template, [
            '%className' => isset($page->modulename) ? "pg-mod-$page->modulename" : 'pg-other',
            '%title'=> $page->name,
            '%url'  => $page->url,
            '%html' => $this->wordwrap ? wordwrap($html, $this->wordwrap) : $html,
            '%json' => json_encode($page, JSON_PRETTY_PRINT),
        ]);
    }
}
