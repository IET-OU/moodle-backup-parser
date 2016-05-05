<?php namespace Nfreear\MoodleBackupParser;

/**
 * Output static pages compatible with OctoberCMS.
 *
 * @copyright Nick Freear, 20 April 2016.
 */

use \Nfreear\MoodleBackupParser\Clean;

class StaticPages
{
    protected $wordwrap = 96;

    protected $output_dir;
    protected $pages = [];
    protected $urls = [];
    protected $index_html = [];
    protected $references = [];

    public function putContents($output_dir, $pages_r)
    {
        $this->output_dir = $output_dir;
        $this->pages = $pages_r;

        foreach ($this->pages as $page) {
            $page->url = $this->url($page->filename);
            $filename = $this->output_dir . '/' . $page->filename . '.htm';
            $bytes = file_put_contents($filename, $this->html($page));
            $this->urls[ $page->url ] = $page->name;
            $this->index_html[] = "<a href='$page->url'>$page->name</a>";
            $this->references[] = $page->filename;
        }

        $this->putIndex();

        return $this->putYaml();
    }

    public function getUrls()
    {
        return $this->urls;
    }

    protected function url($filename)
    {
        return '/' . $filename;
    }

    protected function putIndex()
    {
        $filename = $this->output_dir . '/' . 'index' . '.htm';
        $page = (object) [
            'name' => 'Site map',
            'url'  => $this->url('site-map'),
            'content' => "\n<ul>\n<li>" . implode("\n<li>", $this->index_html) . "\n</ul>\n",
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
%html
<script type="application/json">
%json
</script>

EOT;
        return strtr($template, [
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
