[![Build Status](https://travis-ci.org/mindscreen/yarnlock.svg?branch=master)](https://travis-ci.org/mindscreen/yarnlock)
[![Coverage Status](https://coveralls.io/repos/github/mindscreen/yarnlock/badge.svg?branch=master)](https://coveralls.io/github/mindscreen/yarnlock?branch=master)

# Mindscreen\YarnLock

A php-package for parsing and evaluating the [yarn.lock](https://yarnpkg.com/lang/en/docs/yarn-lock/) format.

## Basic Usage

```php
<?php
use Mindscreen\YarnLock\YarnLock;

$yarnLock = YarnLock::fromString(file_get_contents('yarn.lock'));

$allPackages = $yarnLock->getPackages();
$hasBabelCore = $yarnLock->hasPackage('babel-core', '^6.0.0');
$babelCorePackages = $yarnLock->getPackagesByName('babel-core');
$babelCoreDependencies = $babelCorePackages[0]->getDependencies();
```

## Package Depth

You can also query packages by depth:

```php
// Get just the direct dependencies.
$directDependencies = $yarnLock->getPackagesByDepth(0);
// Get the first two levels of dependencies.
$firstTwoLevels = $yarnLock->getPackagesByDepth(0, 2);
```
