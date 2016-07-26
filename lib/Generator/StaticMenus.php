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

    public function assignSectionMenu($section)
    {
        $name = str_replace('_', '', $section->filename);
        foreach ($this->menu_current as $idx => $item) {
            $data = $item[ 'data' ];
            $menu_code = sprintf('stage_url:/%s, mid:%s, modname:%s', $name, $data->moduleid, $data->modulename);
            $this->menu_current[ $idx ][ 'code' ] = $menu_code;
            $this->menu_current[ $idx ][ 'type' ] = 'static-page';
            unset($this->menu_current[ $idx ][ 'data' ]);
        }
        //$this->menu[ $name ] = $this->menu_current;
        $this->menu = array_merge($this->menu, $this->menu_current);
        $this->menu_current = [];
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
        return $bytes;
    }
}
