<?php namespace Nfreear\MoodleBackupParser\Generator;

/**
 * Output static pages compatible with OctoberCMS.
 *
 * @copyright © Nick Freear, 20 April 2016.
 * @copyright © 2016 The Open University.
 */

#use Symfony\Component\Yaml\Yaml;

use Nfreear\MoodleBackupParser\Generator\Html;
use Nfreear\MoodleBackupParser\Generator\StaticMenus;

class StaticPages
{
    const MOD_TREAT_AS_PAGE = 'X_SWITCH_TREAT_AS_PAGE';
    const MOD_IS_SIMPLE_ACTIVITY = 'X_IS_SIMPLE_ACTIVITY';

    protected $options = [];
    protected $base = '/';
    protected $verbose = false;

    protected $output_dir;
    protected $activities = [];
    protected $sections   = [];
    protected $index_html = [];
    protected $sideblock_html = [];
    protected $other_html = [];
    protected $references = [];

    protected $menu;

    protected $html;

    public function __construct()
    {
        $this->html = new Html();
        $this->menu = new StaticMenus();
    }

    public function setVerbose()
    {
        $this->verbose = true;
    }

    public function opt($key, $default = null)
    {
        return isset($this->options[ $key ]) ? $this->options[ $key ] : $default;
    }

    public function setOptions($options)
    {
        if ($this->isVerbose()) {
            printf("Set options: %s\n", json_encode($options, JSON_PRETTY_PRINT));
        }
        $this->options = $options;
        $this->html->setReplacements($this->opt('preg_replace_html', []));
        $this->html->setIconMap($this->opt('font_icon_map', []), null, $this->opt('font_icon_enable'));
        $this->html->setAbbreviations($this->opt('abbreviations', []));
        $this->menu->setOptions($this->options);
    }

    public function setMetaData($metadata)
    {
        return $this->html->setMetaData($metadata);
    }

    public function setURIReferences($references)
    {
        if ($this->isVerbose()) {
            printf("URI references: %s\n", print_r($references, true));
        }
        return $this->html->setURIReferences($references);
    }

    public function setAbbreviations($abbr_array)
    {
        if ($this->isVerbose()) {
            printf("Abbreviations: %s\n", print_r($abbr_array, true));
        }
        return $this->html->setAbbreviations($abbr_array);
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
        if ($this->isVerbose()) {
            var_dump(count($this->sideblock_html), count($this->index_html));
        }

        $this->putIndex();
        $this->putSideblock();

        $this->menu->putMenuYaml($this->output_dir);

        return $this->putYaml();
    }

    public function isVerbose()
    {
        return $this->verbose;
    }

    protected function switchExt($modname)
    {
        $treat_as_page = $this->opt('treat_as_page');
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
        if ($this->opt('sideblock_section_id')
         && $this->opt('sideblock_section_id') === $section->id) {
            $this->sideblock_html = array_merge($this->sideblock_html, $section_html);

        } elseif ($section->is_on_course_home_page || $this->opt('section_is_on_course_home_page')) {
            $this->index_html = array_merge($this->index_html, $section_html);

            $bytes = $this->putSectionPage($section, $section_html);
            /*if (isset($this->opt('sections_pages')[ 'sid:' . $section->id ])) {
                $sec_page = (object) [
                    'filename' => $this->opt('sections_pages')[ 'sid:' . $section->id ],
                    'name' => $section->title,
                    'content' => $section_html,
                ];
                $sec_page->url = $this->url($sec_page->filename);
                $filename = $this->output_dir . '/' . $sec_page->filename . '.htm';
                $bytes = file_put_contents($filename, $this->html->staticHtml($sec_page));
            }*/
        } else {
            // Section on a sub-page, or similar!
            $this->other_html = array_merge($this->other_html, $section_html);
        }

        //$this->assignSectionMenu($section);
    }

    protected function putSectionPage($section, $section_html)
    {
        $section_pages = $this->opt('section_pages');
        if (isset($section_pages[ 'sid:' . $section->id ])) {
            $sec_page = (object) [
                'section_id'  => $section->id,
                'modulename'  => 'section',
                'filename' => $section_pages[ 'sid:' . $section->id ],
                'name'  => $section->title,
                'content' => implode("\n", $section_html),
            ];
            $sec_page->url = $this->url($sec_page->filename);
            $filename = $this->output_dir . '/' . $sec_page->filename . '.htm';
            $bytes = file_put_contents($filename, $this->html->staticHtml($sec_page));

            $this->menu->assignSectionMenu($sec_page);

            return $bytes;
        }
        return null;
    }

    public function putFiles($output_files_dir, $files_r)
    {
        $copy_count = $skip_count = 0;
        $skip_files = [];
        foreach ($files_r as $file) {
            if (in_array($file->filename, $this->opt('put_files_skip', []))) {
                $skip_count++;
                $skip_files[] = $file;
            } else {
                $b_ok = copy($file->hashpath, $output_files_dir . '/' . $file->filename);
                $copy_count += (int) $b_ok;
            }
        }
        return $copy_count;
    }


    protected function trySimpleActivityLink($activity)
    {
        $sa_config = $this->opt('simple_activity_link');
        $modname = $activity->modulename;
        if (isset($sa_config[ $modname ])) {
            $url_format = $sa_config[ $modname ][ 'url' ];
            $url = sprintf($url_format, $activity->moduleid);

            $this->menu->addMenuItem($activity);

            return Html::wrap($activity, "<a href='$url'>$activity->name</a>");
        }
        return null;
    }

    /** Process "resources bank" page names,
     * Eg. "Designing and Planning Learning Activities (A1)"
     */
    protected function processPageName($page_name)
    {
        $page_name = preg_replace('/(.+) \(([AKV]\d)\)$/', '<b>$2</b>$1', $page_name);
        return $page_name;
    }

    protected function putPageActivity($page)
    {
        $page->url = $this->url($page->filename);
        $filename = $this->output_dir . '/' . $page->filename . '.htm';
        $bytes = file_put_contents($filename, $this->html->staticHtml($page));
        $page_name = $this->processPageName($page->name);
        $index_html = Html::wrap($page, "<a href='.$page->url'>$page_name</a>");
        $this->references[] = $page->filename;

        $this->menu->addMenuItem($page);

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

        $this->menu->addMenuItem($folder);

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

        $this->menu->addMenuItem($url);

        return $index_html;
    }

    protected function url($filename)
    {
        return $this->base . preg_replace('/^[-_\.]/', '', $filename);
    }

    protected function putIndex()
    {
        if ($this->sections) {
            $index_html = implode("\n", $this->index_html);
        } else {
            $index_html = "\n<ul>\n" . implode("\n", $this->index_html) . "\n</ul>\n";
        }
        $filename = $this->output_dir . '/' . $this->opt('index_file', '-index') . '.htm';
        $page = (object) [
            'name' => 'Home',  //Was: 'APPLAuD', 'Site map',
            'url'  => $this->url($this->opt('index_url', 'index')),
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
            "\n\nstatic-pages:\n    ";
        $yml_pre .= implode(": { }\n    ", $this->opt('static_pages_add', [])) . ": { }\n    ";
        $yml_post = ": { }\n\n#End.\n";
        $yml_join = ": { }\n    ";

        $references = array_merge($this->opt('section_pages', []), $this->references);

        $bytes = file_put_contents($filename, $yml_pre . implode($yml_join, $references) . $yml_post);
        return $bytes;
    }
}
