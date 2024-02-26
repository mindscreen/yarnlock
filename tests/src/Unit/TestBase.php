<?php

namespace Mindscreen\YarnLock\Tests\Unit;

use PHPUnit\Framework\TestCase;

class TestBase extends TestCase
{

    protected static function rootDir(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @param string[] $parts
     */
    protected static function fixturesPath(array $parts): string
    {
        array_unshift(
            $parts,
            static::rootDir(),
            'tests',
            'fixtures'
        );

        return implode(\DIRECTORY_SEPARATOR, $parts);
    }

    protected static function getInput(string $filePath): string
    {
        return file_get_contents(static::fixturesPath([$filePath])) ?: '';
    }
}
