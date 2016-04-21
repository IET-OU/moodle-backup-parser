<?php namespace Nfreear\MoodleBackupParser;

/**
 * Output static pages compatible with OctoberCMS.
 *
 * @copyright Nick Freear, 20 April 2016.
 */

class StaticPages
{
    protected $output_dir;
    protected $pages = [];
    protected $urls = [];
    protected $index = [];

    public function putContents($output_dir, $pages_r)
    {
         $this->output_dir = $output_dir;
         $this->pages = $pages_r;

        foreach ($this->pages as $page) {
            $filename = $this->output_dir . '/' . $page->filename . '.htm';
            $bytes = file_put_contents($filename, $this->html($page));
            $this->urls[ $page->filename . '.htm' ] = $page->name;
            $this->index[] = "<a href='$page->filename.htm'>$page->name</a>";
        }

         return $this->putIndex();
    }

    public function getUrls()
    {
        return $this->urls;
    }

    public function putIndex()
    {
        $filename = $this->output_dir . '/' . 'index' . '.htm';
        $bytes = file_put_contents($filename, implode("\n<li>", $this->index));
    }

    protected function html($page)
    {
          $content = $page->content;
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
            '%url'  => '/' . $page->filename . '.htm',
            '%html' => $this->clean($content),
            '%json' => json_encode($page, JSON_PRETTY_PRINT),
        ]);
    }

    protected function expandLinks($page)
    {
        //TODO: E.g. is-applaud-for-me.htm
    }

    protected function clean($content)
    {
        return preg_replace('/style="[^"]*"/', '', $content);
    }
}
