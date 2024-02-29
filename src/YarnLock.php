<?php

declare(strict_types=1);

namespace Mindscreen\YarnLock;

class YarnLock
{

    /**
     * @var Package[]
     */
    protected array $packages;

    protected bool $depthCalculated = false;

    /**
     * Creates an instance from the contents of a yarn.lock file.
     *
     * @throws ParserException
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $input): static
    {
        $parser = new Parser();
        /** @var \stdClass $yarnLockData */
        $yarnLockData = $parser->parse($input);
        // @phpstan-ignore-next-line
        $yarnLock = new static();
        $yarnLock->setPackages(static::evaluatePackages($yarnLockData));

        return $yarnLock;
    }

    /**
     * @return Package[]
     */
    protected static function evaluatePackages(object $data): array
    {
        $packageVersionMap = [];
        $allPackages = [];
        $handledPackages = [];
        foreach (get_object_vars($data) as $packageVersions => $dependencyInformation) {
            $packageVersionStrings = Parser::parseVersionStrings($packageVersions);
            $packageName = Parser::splitVersionString($packageVersionStrings[0])[0];
            $packageVersion = $dependencyInformation->version;
            if (!array_key_exists($packageName, $packageVersionMap)) {
                $packageVersionMap[$packageName] = [];
            }
            $package = new Package();
            $package->setName($packageName);
            $package->setVersion($packageVersion);
            $package->setResolved($dependencyInformation->resolved);
            foreach ($packageVersionStrings as &$packageVersionString) {
                $packageVersionString = Parser::splitVersionString($packageVersionString)[1];
                $package->addSatisfiedVersion($packageVersionString);
                $packageVersionMap[$packageName][$packageVersionString] = [
                    'package' => $package,
                    'data' => $dependencyInformation,
                ];
            }
            $allPackages[] = $package;
        }

        foreach ($packageVersionMap as $versions) {
            foreach ($versions as $packageInformation) {
                /** @var Package $package */
                $package = $packageInformation['package'];
                if (!empty($handledPackages[spl_object_id($package)])) {
                    continue;
                }

                $handledPackages[spl_object_id($package)] = true;
                $data = $packageInformation['data'];
                foreach (DependencyType::cases() as $dependencyType) {
                    $field = $dependencyType->value;
                    if (isset($data->{$field})) {
                        foreach ($data->{$field} as $dependencyName => $dependencyVersion) {
                            $dependencyPackage = $packageVersionMap[$dependencyName][$dependencyVersion]['package'];
                            $package->addDependency($dependencyPackage, $dependencyType);
                        }
                    }
                }
            }
        }
        return $allPackages;
    }

    /**
     * Get all packages resolved in this lock file.
     *
     * @return Package[]
     */
    public function getPackages(): array
    {
        return $this->packages;
    }

    /**
     * @param Package[] $packages
     */
    public function setPackages(array $packages): static
    {
        $this->packages = $packages;

        return $this;
    }

    /**
     * A package might be required in multiple versions.
     *
     * @return Package[]
     */
    public function getPackagesByName(string $packageName): array
    {
        return array_values(
            array_filter(
                $this->packages,
                function (Package $package) use ($packageName): bool {
                    return $package->getName() === $packageName;
                },
            ),
        );
    }

    /**
     * Get all dependencies with a certain depth (`getPackagesByDepth(1)`), up to a certain depth
     * (`getPackagesByDepth(0, 2)`) or starting from a certain depth (`getPackagesByDepth(1, null)`).
     * Note that $end is exclusive.
     *
     * @return Package[]
     */
    public function getPackagesByDepth(int $start, ?int $end = 0): array
    {
        $this->calculateDepth();
        if ($end === 0 || ($end !== null && $end < $start)) {
            $end = $start + 1;
        }

        return array_values(
            array_filter(
                $this->packages,
                function (Package $package) use ($start, $end): bool {
                    $depth = $package->getDepth();
                    if ($depth === null) {
                        return $end === null;
                    }

                    $to = $end === null || $depth < $end;

                    return $depth >= $start && $to;
                }
            )
        );
    }

    /**
     * Get the maximal dependency-tree depth.
     */
    public function getDepth(): int
    {
        $this->calculateDepth();

        return 1 + array_reduce(
            $this->packages,
            function ($d, Package $package) {
                return max($d, $package->getDepth());
            },
            0,
        );
    }

    /**
     * Gets a package matching the name and - if given - version.
     * Returns null, if no package was found.
     * If no version is given and a package is resolved with multiple versions,
     * the first one will be returned. Consider using `getPackagesByName` and
     * filter for the version that fits your needs closest.
     */
    public function getPackage(string $name, string $version = null): ?Package
    {
        $candidates = $this->getPackagesByName($name);
        if (count($candidates) < 1) {
            return null;
        }

        if ($version === null) {
            return $candidates[0];
        }

        foreach ($candidates as $package) {
            if ($package->getVersion() === $version
                || in_array($version, $package->getSatisfiedVersions())
            ) {
                return $package;
            }
        }

        return null;
    }

    /**
     * Checks whether a specific package is resolved in this lock file.
     */
    public function hasPackage(string $name, ?string $version = null): bool
    {
        return $this->getPackage($name, $version) !== null;
    }

    /**
     * Calculate the depth of the packages in the dependency tree.
     * To avoid your root dependencies getting listed deeper in the tree, you should
     * specify them as $root.
     *
     * @param Package[] $root
     */
    public function calculateDepth(?array $root = null): void
    {
        if ($this->depthCalculated) {
            return;
        }

        if ($root === null) {
            $root = array_values(
                array_filter(
                    $this->packages,
                    function (Package $p): bool {
                        return count($p->getResolves()) === 0;
                    },
                ),
            );
        }

        /** @var Package $rootNode */
        foreach ($root as $rootNode) {
            $this->calculateChildDepth($rootNode, 0);
        }
        $this->depthCalculated = true;
    }

    protected function calculateChildDepth(Package $node, int $depth): void
    {
        if ($node->getDepth() !== null && $node->getDepth() <= $depth) {
            return;
        }

        $node->setDepth($depth);
        foreach ($node->getAllDependencies() as $dependency) {
            $this->calculateChildDepth($dependency, $depth + 1);
        }
    }
}
