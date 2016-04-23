<?php namespace Nfreear\MoodleBackupParser\Test;

/**
 * Unit tests for the 'Clean' utility functions.
 *
 * @copyright Nick Freear, 20 April 2016.
 * @copyright Copyright 2016 The Open University.
 * @link  http://generator.lorem-ipsum.info/
 */

use \Nfreear\MoodleBackupParser\Clean;

class CleanTest extends \PHPUnit_Framework_TestCase
{

    public function setup()
    {
    }

    public function testFilenames()
    {
        $names = [
            'What is Lorem IpsUm?',
        ];
        $filenames = [
            'what-is-lorem-ipsum',
        ];

        foreach ($names as $idx => $name) {
            $this->assertEquals($filenames[ $idx ], Clean::filename($name));
        }
    }

    public function testHtml()
    {
        // tests/output/static-pages/is-lorem-for-me.htm:
        $dirty = <<<EOA
    <p><span style="font-size: 1em; line-height: 1.4;">Lorem ipsum dolor sit amet, mel summo epicuri an, quo doctus nonumes ex. Et alii ignota abhorreant vix. Ea albucius qualisque hendrerit duo...</span></p>
EOA;
        $clean = Clean::html($dirty);

        $this->assertNotRegExp('/style=/', $clean);
    }
}
