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

If you maybe don't just want all packages but only the direct dependencies plus one level of indirection, you have to go a little extra mile:

```php
<?php
use Mindscreen\YarnLock\YarnLock;

// read the dependencies from the package.json file
$packageDependencies = (json_decode(file_get_contents('package.json')))->dependencies;
// get these packages from the yarn lock-file
$rootDependencies = array_map(function($packageName, $packageVersion) use ($yarnLock) {
    return $yarnLock->getPackage($packageName, $packageVersion);
}, array_keys($packageDependencies), array_values($packageDependencies));
// some of our dependencies might be used by other dependencies deeper down the tree so
// they wouldn't appear in the top levels, if we wouldn't explicitly set them there.
$yarnLock->calculateDepth($rootDependencies);

// get the first two levels; the second argument is the exclusive upper limit
$yarnLock->getPackagesByDepth(0, 2);
```
