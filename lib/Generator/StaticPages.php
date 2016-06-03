<?php namespace Nfreear\MoodleBackupParser\Generator;

/**
 * Output static pages compatible with OctoberCMS.
 *
 * @copyright © Nick Freear, 20 April 2016.
 * @copyright © 2016 The Open University.
 */

use Nfreear\MoodleBackupParser\Generator\Html;

class StaticPages
{
    const MOD_TREAT_AS_PAGE = 'X_SWITCH_TREAT_AS_PAGE';
    const MOD_IS_SIMPLE_ACTIVITY = 'X_IS_SIMPLE_ACTIVITY';

    protected $options = [];
    protected $base = '/';

    protected $output_dir;
    protected $activities = [];
    protected $sections   = [];
    protected $index_html = [];
    protected $sideblock_html = [];
    protected $references = [];

    protected $html;

    public function __construct()
    {
        $this->html = new Html();
    }

    public function setOptions($options)
    {
        printf("Set options: %s\n", json_encode($options, JSON_PRETTY_PRINT));
        $this->options = $options;
        $this->html->setReplacements($options[ 'preg_replace_html' ]);
        $this->html->setIconMap($options[ 'font_icon_map' ]);
    }

    public function setMetaData($metadata)
    {
        return $this->html->setMetaData($metadata);
    }

    public function putContents($output_dir, $activities_r, $sections = null)
    {
        $this->output_dir = $output_dir;
        $this->activities = $activities_r;
        $this->sections = $sections;

        if (! $sections) {
            return $this->putContentsFlat();
        }

        $count = 0;
        foreach ($sections->sections as $section) {
            $count++;
            $sequence = $section->activity_sequence;

            $section_html = [];
            $section_html[] = Html::sectionHead($section, $count);

            foreach ($sequence as $mod_id) {
                if (! isset($activities_r[ "mid:$mod_id" ])) {
                    throw new Exception(sprintf('Error! Activity not found, mod ID: %s, section ID: %s', $mod_id, $section->id));
                }

                $activity = $activities_r[ "mid:$mod_id" ];
                $modname  = $activity->modulename;

                $try_html = $this->trySimpleActivityLink($activity);
                if ($try_html) {
                    $modname = self::MOD_IS_SIMPLE_ACTIVITY;
                }

                switch ($this->switchExt($modname)) {
                    case self::MOD_IS_SIMPLE_ACTIVITY:
                        $section_html[] = $try_html;
                        break;
                    case 'label':
                        $section_html[] = Html::wrap($activity, $activity->content);
                        break;
                    case 'page':
                    case self::MOD_TREAT_AS_PAGE:  // Drop-through!
                    #Was: case 'subpage':
                        $section_html[] = $this->putPageActivity($activity);
                        break;
                    case 'folder':
                        $section_html[] = $this->putFolderActivity($activity);
                        break;
                    case 'resource':
                        $section_html[] = Html::activityResource($activity);
                        break;
                    case 'url':
                        $section_html[] = $this->putUrlActivity($activity);
                        break;
                    default:
                        $section_html[] = Html::activityPlaceholder($activity);
                        break;
                }
            }
            $section_html[] = Html::clean("</ul></div>\n");

            $this->assignSection($section, $section_html);
        }

        var_dump(count($this->sideblock_html), count($this->index_html));

        $this->putIndex();
        $this->putSideblock();

        return $this->putYaml();
    }

    protected function switchExt($modname)
    {
        $treat_as_page = $this->options[ 'treat_as_page' ];
        return in_array($modname, $treat_as_page) ? self::MOD_TREAT_AS_PAGE : $modname;
    }

    protected function putContentsFlat() // LEGACY?
    {
        foreach ($this->activities as $activity) {
            switch ($activity->modulename) {
                case 'label':
                    $this->index_html[] = Html::wrap($activity, $activity->content);
                    break;
                case 'page':
                    $this->putPage($activity);
                    break;
                default:
                    $this->index_html[] = Html::activityPlaceholder($activity);
                    break;
            }
        }

        $this->putIndex();

        return $this->putYaml();
    }

    protected function assignSection($section, $section_html)
    {
        if (isset($this->options[ 'sideblock_section_id' ])
         && $this->options[ 'sideblock_section_id' ] === $section->id) {
            $this->sideblock_html = array_merge($this->sideblock_html, $section_html);
        } else {
            $this->index_html = array_merge($this->index_html, $section_html);
        }
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



    protected function trySimpleActivityLink($activity)
    {
        $sa_config = $this->options[ 'simple_activity_link' ];
        $modname = $activity->modulename;
        if (isset($sa_config[ $modname ])) {
            $url_format = $sa_config[ $modname ][ 'url' ];
            $url = sprintf($url_format, $activity->moduleid);
            return Html::wrap($activity, "<a href='$url'>$activity->name</a>");
        }
        return null;
    }

    protected function putPageActivity($page)
    {
        $page->url = $this->url($page->filename);
        $filename = $this->output_dir . '/' . $page->filename . '.htm';
        $bytes = file_put_contents($filename, $this->html->staticHtml($page));
        $index_html = Html::wrap($page, "<a href='.$page->url'>$page->name</a>");
        $this->references[] = $page->filename;

        return $index_html;
    }

    protected function putFolderActivity($folder)
    {
        $folder_files = $folder->files;
        $folder_html  = [];

        foreach ($folder_files as $file) {
            $prefix = '@@PLUGINFILE@@';
            $folder_html[] = "<li><a href='$prefix/$file->filepath'>$file->filename</a>";
        }

        $folder_page_template = '<div class="intro">%s</div> <ul class="folder-files">%s</ul>';
        $folder->content = sprintf($folder_page_template, $folder->intro, implode("\n", $folder_html));

        $folder->url = $this->url($folder->filename);
        $filename = $this->output_dir . '/' . $folder->filename . '.htm';
        $bytes = file_put_contents($filename, $this->html->staticHtml($folder));

        $index_html = Html::wrap($folder, "<a href='.$folder->url'>$folder->name</a>");
        $this->references[] = $folder->filename;

        return $index_html;
    }

    protected function putUrlActivity($url)
    {
        if ($url->embed) {
            $url->url = $this->url($url->filename);
            $filename = $this->output_dir . '/' . $url->filename . '.htm';
            $bytes = file_put_contents($filename, $this->html->staticHtml($url));

            $index_html = Html::wrap($url, "<a href='.$url->url'>$url->name</a>", 'embed');
            $this->references[] = $url->filename;
        } else {
            $index_html = Html::wrap($url, "<a href='$url->resolve_url'>$url->name</a>");
        }
        return $index_html;
    }

    protected function url($filename)
    {
        return $this->base . $filename;
    }

    protected function putIndex()
    {
        if ($this->sections) {
            $index_html = implode("\n", $this->index_html);
        } else {
            $index_html = "\n<ul>\n" . implode("\n", $this->index_html) . "\n</ul>\n";
        }
        $filename = $this->output_dir . '/' . 'index' . '.htm';
        $page = (object) [
            'name' => 'Home',  //Was: 'APPLAuD', 'Site map',
            'url'  => $this->url(''),  //Was: 'index', 'site-map'.
            'content' => $index_html,
        ];
        $bytes = file_put_contents($filename, $this->html->staticHtml($page));
        return $bytes;
    }

    protected function putSideblock()
    {
        $filename = $this->output_dir . '/' . '-sideblock' . '.htm';
        $page = (object) [
            'name' => 'Sideblock',
            'url'  => $this->url('#sideblock'),
            'content' => implode("\n", $this->sideblock_html),
        ];
        $bytes = file_put_contents($filename, $this->html->staticHtml($page));
        return $bytes;
    }

    protected function putYaml()
    {
        $filename = $this->output_dir . '/' . '-static-pages.yaml';

        $yml_pre = "# Auto-generated:  " . gmdate('c') .
            "\n\nstatic-pages:\n    index: { }\n    ";
        $yml_post = ": { }\n\n#End.\n";
        $yml_join = ": { }\n    ";

        $bytes = file_put_contents($filename, $yml_pre . implode($yml_join, $this->references) . $yml_post);
        return $bytes;
    }

    protected function expandLinks($page)
    {
        //TODO: E.g. is-applaud-for-me.htm
    }
}
