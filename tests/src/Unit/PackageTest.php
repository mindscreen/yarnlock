<?php

declare(strict_types=1);

namespace Mindscreen\YarnLock\Tests\Unit;

use Mindscreen\YarnLock\Package;

class PackageTest extends TestBase
{
    public function testAvoidDuplicates(): void
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

        static::assertCount(1, $package1->getProdRequiredDependencies());
        static::assertCount(1, $package2->getResolves());
    }
}
