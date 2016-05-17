<?php namespace Nfreear\MoodleBackupParser\Generator;

/**
 * HTML helper functions, for October CMS static pages.
 *
 * @copyright Nick Freear, 17 May 2016.
 */

use Nfreear\MoodleBackupParser\Clean;

class Html
{
    protected $wordwrap = 96;

    public static function sectionHead($sect, $idx)
    {
        $heading = $sect->title ? "<h2>$sect->title</h2>" : '';
        $cls = 'mod-section' . ($sect->title ? '': ' anonymous');
        return Clean::html(
            "<div id='section-$idx' data-sid='$sect->id' class='$cls'>" . $heading . "<ul>\n"
        );
    }

    public static function activityPlaceholder($activity)
    {
        return self::wrap(
            $activity,
            "<i>$activity->modulename</i> $activity->name",
            'mod-placeholder',
            'Placeholder'
        );
    }

    public static function wrap($obj, $content, $cls = null, $ttl = null)
    {
        return Clean::html("<li class='mod-$obj->modulename $cls' data-mid='$obj->moduleid' title='$ttl'>$content</li>");
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
