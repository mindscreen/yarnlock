<?php

namespace Mindscreen\YarnLock\Tests\Unit;

use Mindscreen\YarnLock\Package;

/**
 * @covers \Mindscreen\YarnLock\Package
 */
class PackageTest extends TestBase
{
    public function testAvoidDuplicates()
    {
        $package1 = new Package();
        $package1->setName('package1');
        $package1->setVersion('1.0.1');

        $package2 = new Package();
        $package2->setName('package2');
        $package2->setVersion('0.0.8');

        $package1->addDependency($package2);
        $package1->addDependency($package2);
        $package2->addResolves($package1);

        static::assertSame(1, count($package1->getDependencies()));
        static::assertSame(1, count($package2->getResolves()));
    }
}
