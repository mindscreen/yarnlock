<?php

namespace Mindscreen\YarnLock\Tests\Unit;

use Mindscreen\YarnLock\Package;
use Mindscreen\YarnLock\YarnLock;

/**
 * @covers \Mindscreen\YarnLock\YarnLock
 */
class YarnLockTest extends TestBase
{
    /**
     * @var YarnLock
     */
    protected $yarnLock;

    /**
     * Creating a lock file from null should throw an exception.
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testNullInput()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1519201965);
        YarnLock::fromString(null);
    }

    protected function setUp(): void
    {
        $yarnLockContents = static::getInput('example-yarn-package.txt');
        $this->yarnLock = YarnLock::fromString($yarnLockContents);
    }

    /**
     * A package should be found with every satisfying version string.
     */
    public function testPackageExists()
    {
        // babel-core@^6.0.0, babel-core@^6.11.4, babel-core@^6.14.0:
        static::assertTrue($this->yarnLock->hasPackage('babel-core'));
        static::assertTrue($this->yarnLock->hasPackage('babel-core', '^6.0.0'));
        static::assertTrue($this->yarnLock->hasPackage('babel-core', '^6.11.4'));
        static::assertTrue($this->yarnLock->hasPackage('babel-core', '^6.14.0'));
        static::assertTrue($this->yarnLock->hasPackage('babel-core', '6.14.0'));
        static::assertFalse($this->yarnLock->hasPackage('babel-core', '6.15.0'));
    }

    /**
     * Querying for an existing package with different satisfied versions should yield in the
     * correct package. Asking for a unknown package should return null.
     */
    public function testGetPackage()
    {
        $packageName = 'babel-core';
        $package = $this->yarnLock->getPackage($packageName);
        static::assertSame($packageName, $package->getName());

        $packageVersion = '6.14.0';
        $package = $this->yarnLock->getPackage($packageName, $packageVersion);
        static::assertSame($packageVersion, $package->getVersion());

        $package = $this->yarnLock->getPackage($packageName, '^6.11.4');
        static::assertSame($packageVersion, $package->getVersion());

        $package = $this->yarnLock->getPackage('foo');
        static::assertNull($package);
    }

    /**
     * The maximal depth of the dependency tree
     */
    public function testDepth()
    {
        static::assertSame(10, $this->yarnLock->getDepth());
    }

    /**
     * Helper to stringify packages.
     * @param Package[] $packages
     * @return string[]
     */
    protected function getPackageStrings(array $packages)
    {
        return array_values(
            array_map(
                function (Package $p) {
                    return $p->__toString();
                },
                $packages
            )
        );
    }

    /**
     * The argument syntax should return correct subsets
     */
    public function testGetPackagesByDepth()
    {
        $rootPackages = [
            $this->yarnLock->getPackage('lodash', '^4.16.2'),
            $this->yarnLock->getPackage('jest-cli', '15.1.1'),
        ];
        $this->yarnLock->calculateDepth($rootPackages);
        $depth0 = $this->yarnLock->getPackagesByDepth(0);
        static::assertSame(count($rootPackages), count($depth0));

        $depth1 = $this->yarnLock->getPackagesByDepth(1);
        $depth2 = $this->yarnLock->getPackagesByDepth(2);
        $depth12 = $this->yarnLock->getPackagesByDepth(1, 3);
        static::assertSame(count($depth1) + count($depth2), count($depth12));

        // should not be calculated again
        $this->yarnLock->calculateDepth();
        $depthStart = $this->yarnLock->getPackagesByDepth(0, 2);
        $depthRest = $this->yarnLock->getPackagesByDepth(2, null);
        $allPackages = $this->yarnLock->getPackages();
        static::assertSame(count($allPackages), count($depthStart) + count($depthRest));
    }

    /**
     * Packages can be required in multiple versions, each satisfying certain requirements.
     */
    public function testGetPackagesByName()
    {
        $packages = $this->yarnLock->getPackagesByName('source-map');
        static::assertSame(4, count($packages));
        $expectedVersions = [
            ['^0.4.4'],
            ['^0.5.0', '^0.5.3', '~0.5.1'],
            ['~0.2.0'],
            ['0.1.32'],
        ];
        $versions = array_map(
            function (Package $p) {
                return $p->getSatisfiedVersions();
            },
            $packages
        );
        $versions = array_values($versions);
        static::assertSame($expectedVersions, $versions);
    }

    /**
     * The package-name should contain name and actual version for every package
     */
    public function testPackageString()
    {
        foreach ($this->yarnLock->getPackages() as $package) {
            static::assertSame($package->getName() . '@' . $package->getVersion(), $package->__toString());
        }
    }

    /**
     * The package-name should contain name and actual version for every package
     */
    public function testResolvedSet()
    {
        foreach ($this->yarnLock->getPackages() as $package) {
            static::assertNotEmpty($package->getResolved());
        }
    }

    public function testYarnExample()
    {
        $yarnLockContents = static::getInput('deep.txt');
        $yarnLock = YarnLock::fromString($yarnLockContents);
        static::assertSame(4, count($yarnLock->getPackages()));
    }
}
