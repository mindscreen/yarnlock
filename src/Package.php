<?php
namespace Mindscreen\YarnLock;

class Package
{
    const DEPENDENCY_TYPE_DEFAULT = '';
    const DEPENDENCY_TYPE_DEV = 'dev';
    const DEPENDENCY_TYPE_OPTIONAL = 'optional';
    const DEPENDENCY_TYPE_PEER = 'peer';

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $version;

    /**
     * Array of version strings satisfied by this package,
     * e.g. version = "1.14.0" might satisfy "^1.0.0", "1.14.0", ...
     *
     * @var string[]
     */
    protected $satisfies = [];

    /**
     * @var Package[]
     */
    protected $dependencies = [];

    /**
     * @var Package[]
     */
    protected $devDependencies = [];

    /**
     * @var Package[]
     */
    protected $peerDependencies = [];

    /**
     * @var Package[]
     */
    protected $optionalDependencies = [];

    /**
     * The distribution resolved for this package.
     *
     * @var string
     */
    protected $resolved;

    /**
     * Packages that require this package, i.e. packages who's dependencies are
     * (in part) resolved by this one.
     *
     * @var Package[]
     */
    protected $resolves = [];

    /**
     * Depth in the dependency tree. Only initialized once the YarnLock computes
     * the depth of all contained packages.
     *
     * @var int
     */
    protected $depth = null;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param string $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getResolved()
    {
        return $this->resolved;
    }

    /**
     * @param string $resolved
     */
    public function setResolved($resolved)
    {
        $this->resolved = $resolved;
    }

    /**
     * @return int|null
     */
    public function getDepth()
    {
        return $this->depth;
    }

    /**
     * @param int $depth
     */
    public function setDepth($depth)
    {
        $this->depth = $depth;
    }

    /**
     * @param string $versionString
     */
    public function addVersion($versionString)
    {
        array_push($this->satisfies, $versionString);
    }

    /**
     * @return string[]
     */
    public function getSatisfiedVersions()
    {
        return $this->satisfies;
    }

    /**
     * @return Package[]
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

    /**
     * @return Package[]
     */
    public function getDevDependencies()
    {
        return $this->devDependencies;
    }

    /**
     * @return Package[]
     */
    public function getPeerDependencies()
    {
        return $this->peerDependencies;
    }

    /**
     * @return Package[]
     */
    public function getOptionalDependencies()
    {
        return $this->optionalDependencies;
    }

    /**
     * @return Package[]
     */
    public function getAllDependencies()
    {
        return array_merge(
            $this->getDependencies(),
            $this->getDevDependencies(),
            $this->getOptionalDependencies(),
            $this->getPeerDependencies()
        );
    }

    /**
     * @return Package[]
     */
    public function getResolves()
    {
        return $this->resolves;
    }

    /**
     * @param Package $package
     * @internal
     */
    public function addResolves(Package $package)
    {
        if (in_array($package, $this->resolves)) {
            return;
        }
        $this->resolves[] = $package;
    }

    /**
     * Add a package as dependency to the current one.
     *
     * @param Package $package
     * @param string $type
     */
    public function addDependency(Package $package, $type = self::DEPENDENCY_TYPE_DEFAULT)
    {
        $field = self::getDependencyField($type);
        if (in_array($package, $this->$field)) {
            return;
        }
        array_push($this->$field, $package);
        $package->addResolves($this);
    }

    /**
     * Get the property-name and yarn-lock key for the given dependency-type.
     *
     * @param string $type
     * @return string
     */
    public static function getDependencyField($type)
    {
        $field = 'dependencies';
        switch ($type) {
            case self::DEPENDENCY_TYPE_DEV:
                $field = 'devDependencies';
                break;
            case self::DEPENDENCY_TYPE_OPTIONAL:
                $field = 'optionalDependencies';
                break;
            case self::DEPENDENCY_TYPE_PEER:
                $field = 'peerDependencies';
                break;
        }
        return $field;
    }

    /**
     * Printing a package should contain it's name and version.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName() . '@' . $this->getVersion();
    }
}