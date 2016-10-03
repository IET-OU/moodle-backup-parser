<?php namespace Nfreear\MoodleBackupParser\Generator;

/**
 * Output a static menu compatible with OctoberCMS.
 *
 * @copyright © Nick Freear, 26 July 2016.
 * @copyright © 2016 The Open University.
 */

use Symfony\Component\Yaml\Yaml;

class StaticMenus
{
    protected $menu_current = [];
    protected $menu = [];
    protected $menu_pages = [];
    protected $menu_activities = [];
    protected $options = [];

    public function setOptions($options)
    {
        $this->options = $options;
    }

    public function assignSectionMenu($section)
    {
        $name = str_replace('_', '', $section->filename);
        $sec_class = 'other';
        if (preg_match('/Stage (\d)/i', $section->name, $matches)) {
            $sec_class = 's' . $matches[ 1 ];
        }
        foreach ($this->menu_current as $idx => $item) {
            $data = $item[ 'data' ];
            $menu_code = sprintf('cls:%s-sub sub, stage_url:/%s, mid:%s, modname:%s', $sec_class, $name, $data->moduleid, $data->modulename);
            $this->menu_current[ $idx ][ 'code' ] = $menu_code;
            $this->menu_current[ $idx ][ 'type' ] = $this->menuItemType($data);
            if ('url embed' === $this->menuItemType($data)) {
                $this->menu_current[ $idx ][ 'type' ] = 'static-page';
                $this->menu_current[ $idx ][ 'reference' ] = $this->getActivityUrl($data);

            } elseif ('url' === $this->menuItemType($data)) {
                $this->menu_current[ $idx ][ 'url' ] = $this->getActivityUrl($data);

            } elseif ('cms-page' === $this->menuItemType($data)) {
                $this->menu_current[ $idx ][ 'reference' ] = $this->getActivityUrl($data);
            }
            //Was: $this->menu_current[ $idx ][ 'x_modname' ] = $data->modulename;
            unset($this->menu_current[ $idx ][ 'data' ]);
        }
        $section_item[] = [
            'title' => $section->name,
            'reference' => $section->filename,
            'code'  => sprintf('cls:%s stage, sid:%s', $sec_class, $section->section_id),
            'type'  => 'static-page',
        ];
        $this->assignMenuPages($section_item, $this->menu_current);
    }

    protected function assignMenuPages($section_item, $menu_current)
    {
        $menu_pages_current = [];
        $menu_activities_current = [];
        foreach ($this->menu_current as $idx => $item) {
            if (preg_match('/(cms|static)-page/', $item[ 'type' ])) {
                $menu_pages_current[] = $item;
            } else {
                $menu_activities_current[] = $item;
            }
        }
        $this->menu = array_merge($this->menu, $section_item, $this->menu_current);
        $this->menu_pages = array_merge($this->menu_pages, $section_item, $menu_pages_current);
        $this->menu_activities = array_merge($this->menu_activities, $menu_activities_current);
        $this->menu_current = [];
    }

    protected function menuItemType($data)
    {
        if ('url' === $data->modulename && $data->embed) {
            return 'url embed';

        } elseif (preg_match('/(url|forumng)/', $data->modulename)) {
            return 'url';

        } elseif (preg_match('/(oublog|oucollaborate)/', $data->modulename)) {
            return 'cms-page';
        }
        return 'static-page';
    }

    public function addMenuItem($obj)
    {
        $this->menu_current[] = [ 'title' => $obj->name, 'reference' => $obj->filename, 'data' => $obj ];
    }

    public function putMenuYaml($output_dir)
    {
        $filename = $output_dir . '/' . '-stages-menu-deep.yaml';

        $yml_pre = "# Auto-generated:  " . gmdate('c') . "\n# Generator:  ". __CLASS__ . "\n";

        $yaml = \Symfony\Component\Yaml\Yaml::dump([
            'name'  => 'Stages Menu Deep',
            'items' => $this->menu
        ], 3);

        $bytes = file_put_contents($filename, $yml_pre . $yaml);
        $bytes = $this->putMenuPagesYaml($output_dir);
        return $this->putMenuActivitiesYaml($output_dir);
    }

    protected function putMenuPagesYaml($output_dir)
    {
        $filename = $output_dir . '/' . '-stages-menu-pages.yaml';

        $yml_pre = "# Auto-generated:  " . gmdate('c') . "\n# Generator:  ". __CLASS__ . "\n";

        $yaml = \Symfony\Component\Yaml\Yaml::dump([
            'name'  => 'Stages Menu Pages',
            'items' => $this->menu_pages,
        ], 3);

        $bytes = file_put_contents($filename, $yml_pre . $yaml);
        return $bytes;
    }

    protected function putMenuActivitiesYaml($output_dir)
    {
        $filename = $output_dir . '/' . '-activities-menu.yaml';

        $yml_pre = "# Auto-generated:  " . gmdate('c') . "\n# Generator:  ". __CLASS__ . "\n";

        $yaml = \Symfony\Component\Yaml\Yaml::dump([
            'name'  => 'Activities Menu / useful links',
            'items' => $this->menu_activities,
        ], 3);

        $bytes = file_put_contents($filename, $yml_pre . $yaml);
        return $bytes;
    }

    protected function getActivityUrl($data)
    {
        switch ($data->modulename) {
            case 'forumng':
                return '@forum@/' . $this->getActivityRef($data->moduleid) . '?mid=' . $data->moduleid;
                break;
            case 'url':
                if ($data->embed) {
                    return $data->filename;
                } else {
                    return $data->resolve_url;
                }
                break;
            case 'oublog':
                return 'private-journal';
                break;
            case 'oucollaborate':
                return 'common-room';
                break;
        }
    }

    protected function getActivityRef($moduleid)
    {
        if (! isset($this->options[ 'activity_refs' ])) {
            return null;
        }
        $activity_refs = $this->options[ 'activity_refs' ];
        $mid = 'mid:' . $moduleid;
        return isset($activity_refs[ $mid ]) ? $activity_refs[ $mid ] : null;
    }

    public function putMenuFilesYaml($output_dir, $files_menu)
    {
        $filename = $output_dir . '/' . '-files-menu.yaml';

        ksort($files_menu, SORT_NATURAL);

        foreach ($files_menu as $fid => $ff) {
            $file = $ff[ 'obj' ];
            $cls = sprintf(
                'cls: %s %s, fid: %d, time: %s',  // 'cls' needs to come first!
                $file->fileext,
                $file->aptype,
                $file->id,
                $file->timemodified
            );

            $files_menu[ $fid ] = [
                'title' => $file->filename,
                'type'  => 'url',
                'code'  => $cls,
                'url'   => $file->filepath,
            ];
        }

        $yml_pre = "# Auto-generated:  " . gmdate('c') . "\n# Generator:  ". __CLASS__ . "\n";

        $yaml = \Symfony\Component\Yaml\Yaml::dump([
            'name'  => 'Files Menu',
            'items' => array_values($files_menu),
        ], 3);

        $bytes = file_put_contents($filename, $yml_pre . $yaml);
        return $bytes;
    }
}
