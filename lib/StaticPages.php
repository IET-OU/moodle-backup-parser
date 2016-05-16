<?php namespace Nfreear\MoodleBackupParser;

/**
 * Output static pages compatible with OctoberCMS.
 *
 * @copyright Nick Freear, 20 April 2016.
 */

use \Nfreear\MoodleBackupParser\Clean;

class StaticPages
{
    protected $base = '/';
    protected $wordwrap = 96;

    protected $output_dir;
    protected $activities = [];
    protected $urls = [];
    protected $index_html = [];
    protected $site_map   = [];
    protected $references = [];

    public function putContents($output_dir, $activities_r, $sections = null)
    {
        $this->output_dir = $output_dir;
        $this->activities = $activities_r;

        foreach ($this->activities as $activity) {
            switch ($activity->modulename) {
                case 'label':
                    $this->index_html[] = $this->wrap($activity, $activity->content);
                    break;
                case 'page':
                    $this->putPage($activity);
                    break;
                default:
                    $this->index_html[] = $this->wrap(
                        $activity, "<i>$activity->modulename</i> $activity->name", 'mod-placeholder', 'Placeholder');
                    break;
            }
        }

        $this->putIndex();

        return $this->putYaml();
    }

    public function putFiles($output_files_dir, $files_r)
    {
        $count = 0;
        foreach ($files_r as $file) {
            $b_ok = copy($file->hashpath, $output_files_dir . '/' . $file->filename);
            $count += (int) $b_ok;
        }
        return $count;
    }

    protected function wrap($obj, $content, $cls = null, $ttl = null)
    {
        return Clean::html("<li class='mod-$obj->modulename $cls' data-mid='$obj->moduleid' title='$ttl'>$content</li>");
    }

    protected function putPage($page)
    {
        $page->url = $this->url($page->filename);
        $filename = $this->output_dir . '/' . $page->filename . '.htm';
        $bytes = file_put_contents($filename, $this->html($page));
        $this->urls[ $page->url ] = $page->name;
        $this->index_html[] = $this->wrap($page, "<a href='.$page->url'>$page->name</a>");
        $this->site_map[] = "<a href='.$page->url'>$page->name</a>";
        $this->references[] = $page->filename;
        return $bytes;
    }

    public function getUrls()
    {
        return $this->urls;
    }

    protected function url($filename)
    {
        return $this->base . $filename;
    }

    protected function putIndex()
    {
        $filename = $this->output_dir . '/' . 'index' . '.htm';
        $page = (object) [
            'name' => 'Home',  //Was: 'APPLAuD', 'Site map',
            'url'  => $this->url(''),  //Was: 'index', 'site-map'.
            'content' => "\n<ul>\n" . implode("\n", $this->index_html) . "\n</ul>\n",
        ];
        $bytes = file_put_contents($filename, $this->html($page));
        return $bytes;
    }

    protected function putYaml()
    {
        $filename = $this->output_dir . '/' . '-static-pages.yaml';

        $yml_pre = "# Auto-generated:  " . date('c') .
            "\n\nstatic-pages:\n    index: { }\n    ";
        $yml_post = ": { }\n\n#End.\n";
        $yml_join = ": { }\n    ";

        $bytes = file_put_contents($filename, $yml_pre . implode($yml_join, $this->references) . $yml_post);
        return $bytes;
    }

    protected function html($page)
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

    protected function expandLinks($page)
    {
        //TODO: E.g. is-applaud-for-me.htm
    }
}
