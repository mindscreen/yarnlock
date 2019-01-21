<?php
namespace Mindscreen\YarnLock;


class YarnLock
{

    /**
     * @var Package[]
     */
    protected $packages;

    /**
     * @var boolean
     */
    protected $depthCalculated = false;

    /**
     * Creates an instance from the contents of a yarn.lock file
     * @param string $input
     * @return YarnLock
     * @throws ParserException
     * @throws \InvalidArgumentException
     */
    public static function fromString($input)
    {
        if (!is_string($input)) {
            throw new \InvalidArgumentException('YarnLock::fromString expects input to be a string', 1519201965);
        }
        $parser = new Parser();
        $yarnLockData = $parser->parse($input);
        $yarnLock = new static();
        $yarnLock->setPackages(self::evaluatePackages($yarnLockData));
        return $yarnLock;
    }

    /**
     * @param \stdClass $data
     * @return array
     */
    protected static function evaluatePackages($data)
    {
        $packageVersionMap = [];
        $allPackages = [];
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
                $package->addVersion($packageVersionString);
                $packageVersionMap[$packageName][$packageVersionString] = [
                    'package' => $package,
                    'data' => $dependencyInformation,
                ];
            }
            $allPackages[] = $package;
        }

        foreach ($packageVersionMap as $packageName => $versions) {
            foreach ($versions as $version => $packageInformation) {
                /** @var Package $package */
                $package = $packageInformation['package'];
                if (isset($package->__handled)) {
                    continue;
                }
                $package->__handled = true;
                $data = $packageInformation['data'];
                foreach ([
                    Package::DEPENDENCY_TYPE_DEFAULT,
                    Package::DEPENDENCY_TYPE_DEV,
                    Package::DEPENDENCY_TYPE_OPTIONAL,
                    Package::DEPENDENCY_TYPE_PEER,
                         ] as $dependencyType) {
                    $field = Package::getDependencyField($dependencyType);
                    if (isset($data->$field)) {
                        foreach ($data->$field as $dependencyName => $dependencyVersion) {
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
     * Get all packages resolved in this lock file
     * @return Package[]
     */
    public function getPackages()
    {
        return $this->packages;
    }

    /**
     * @param Package[] $packages
     */
    public function setPackages($packages)
    {
        $this->packages = $packages;
    }

    /**
     * A package might be required in multiple versions.
     *
     * @param string $packageName
     * @return Package[]
     */
    public function getPackagesByName($packageName)
    {
        return array_values(array_filter($this->packages, function(Package $package) use ($packageName) {
            return $package->getName() === $packageName;
        }));
    }

    /**
     * Get all dependencies with a certain depth (`getPackagesByDepth(1)`), up to a certain depth
     * (`getPackagesByDepth(0, 2)`) or starting from a certain depth (`getPackagesByDepth(1, null)`).
     * Note that $end is exclusive.
     *
     * @param int $start
     * @param int $end
     * @return Package[]
     */
    public function getPackagesByDepth($start, $end = 0)
    {
        $this->calculateDepth();
        if ($end === 0 || ($end !== null && $end < $start)) {
            $end = $start + 1;
        }
        return array_values(array_filter($this->packages, function(Package $package) use ($start, $end) {
            $depth = $package->getDepth();
            if ($depth === null) {
                return $end === null;
            }
            $to = $end === null ? true : $depth < $end;
            return $depth >= $start && $to;
        }));
    }

    /**
     * Get the maximal dependency-tree depth.
     *
     * @return int
     */
    public function getDepth()
    {
        $this->calculateDepth();
        return array_reduce($this->packages, function($d, Package $package) {
            return max($d, $package->getDepth());
        }, 0) + 1;
    }

    /**
     * Gets a package matching the name and - if given - version.
     * Returns null, if no package was found.
     * If no version is given and a package is resolved with multiple versions,
     * the first one will be returned. Consider using `getPackagesByName` and
     * filter for the version that fits your needs closest.
     *
     * @param string $name
     * @param string $version
     * @return Package|null
     */
    public function getPackage($name, $version = null)
    {
        $candidates = $this->getPackagesByName($name);
        if (count($candidates) < 1) {
            return null;
        }
        if ($version === null) {
            return $candidates[0];
        }
        foreach ($candidates as $package) {
            if ($package->getVersion() === $version) {
                return $package;
            }
            if (in_array($version, $package->getSatisfiedVersions())) {
                return $package;
            }
        }
        return null;
    }

    /**
     * Checks whether a specific package is resolved in this lock file.
     *
     * @param string $name
     * @param string $version
     * @return bool
     */
    public function hasPackage($name, $version = null)
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
    public function calculateDepth(array $root = null)
    {
        if ($this->depthCalculated) {
            return;
        }
        if ($root === null) {
            $root = array_values(array_filter($this->packages, function(Package $p) {
                return count($p->getResolves()) === 0;
            }));
        }
        /** @var Package $rootNode */
        foreach ($root as $rootNode) {
            $this->calculateChildDepth($rootNode, 0);
        }
        $this->depthCalculated = true;
    }

    /**
     * @param Package $node
     * @param int $depth
     */
    protected function calculateChildDepth(Package $node, $depth) {
        if ($node->getDepth() !== null && $node->getDepth() <= $depth) {
            return;
        }
        $node->setDepth($depth);
        foreach ($node->getAllDependencies() as $dependency) {
            $this->calculateChildDepth($dependency, $depth + 1);
        }
    }
}