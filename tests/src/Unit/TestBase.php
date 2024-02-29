<?php

declare(strict_types=1);

namespace Mindscreen\YarnLock\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class TestBase extends TestCase
{

    protected static function rootDir(): string
    {
        return dirname(__DIR__, 3);
    }

    protected static function fixturesPath(string ...$parts): string
    {
        return Path::join(
            static::rootDir(),
            'tests',
            'fixtures',
            ...$parts,
        );
    }

    protected static function getInput(string $filePath): string
    {
        return file_get_contents(static::fixturesPath($filePath)) ?: '';
    }
}
