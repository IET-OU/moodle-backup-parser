<?php namespace Nfreear\MoodleBackupParser\Test\Extend;

/**
 * Add custom assertions -- assertStrMinLength, assertStrContains, assertISODate ...?
 *
 * @copyright Copyright 2016 The Open University.
 * @author    Nick Freear, 4 April 2016.
 * @link      https://phpunit.de/manual/current/en/extending-phpunit.html
 * @link      https://phpunit.de/manual/current/en/appendixes.assertions.html
 */

//Was: namespace IET_OU\Open_Media_Player\Test\Extend;
//Was: PHPUnit_TestCase_Extended

abstract class TestCaseExtended extends \PHPUnit_Framework_TestCase //\PHPUnit_Framework_Assert
{
    public function assertStrMinLength($expectedLength, $testString, $message = null)
    {
        $this->assertThat(
            strlen($testString),
            new \PHPUnit_Framework_Constraint_GreaterThan($expectedLength),
            self::f($message, __FUNCTION__)
        );
    }

    public function assertISODate($testDateTime, $message = null)
    {
        $this->assertRegExp('/^20\d{2}-\d{2}-\d{2}[T ]\d{2}:/', $testDateTime, self::f($message, __FUNCTION__, 'ISO 8601'));
    }

    public function assertRFCLikeDate($testDateTime, $message = null)
    {
        $this->assertRegExp('/\d{2}:\d{2}:\d{2} 20\d{2}/', $testDateTime, self::f($message, __FUNCTION__, 'RFC 2822'));
    }

    public function assertEmailLike($testEmailish, $message = null)
    {
        $this->assertRegExp('/\w+@\w+/', $testEmailish, self::f($message, __FUNCTION__));
    }

    public function assertUrlLike($expectedRegex, $testUrlish, $message = null)
    {
        $expectedRegex = $expectedRegex ? $expectedRegex : '/^https?:\/\/\w+/';
        $this->assertRegExp($expectedRegex, $testUrlish, self::f($message, __FUNCTION__));
    }

    public function assertIsHex($lengthRange, $testString, $message = null)
    {
        $pattern = sprintf('/^[0-9a-f]{%s}$/', ($lengthRange ? $lengthRange : 40));
        $this->assertRegExp($pattern, $testString, self::f($message, __FUNCTION__, $pattern));
    }

    public static function isTravis()
    {
        return getenv('TRAVIS');
    }

    public static function notTravis()
    {
        return ! getenv('TRAVIS');
    }

    /**
    * @return string Format a message.
    */
    protected static function f($message, $caller, $extra = null)
    {
        return sprintf('%s (%s)', $message, ($extra ? "$caller: $extra" : $caller));
    }
}
