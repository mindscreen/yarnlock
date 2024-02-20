<?php

declare(strict_types=1);

namespace Mindscreen\YarnLock;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Deprecated;

class Package implements \Stringable
{

    protected string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    protected string $version = '';

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    /**
     * The distribution resolved for this package.
     */
    protected string $resolved = '';

    public function getResolved(): string
    {
        return $this->resolved;
    }

    public function setResolved(string $resolved): static
    {
        $this->resolved = $resolved;

        return $this;
    }

    /**
     * Depth in the dependency tree.
     *
     * Only initialized once the YarnLock computes the depth of all contained
     * packages.
     */
    protected ?int $depth = null;

    public function getDepth(): ?int
    {
        return $this->depth;
    }

    public function setDepth(?int $depth): static
    {
        $this->depth = $depth;

        return $this;
    }

    /**
     * Array of version strings satisfied by this package,
     * e.g. version = "1.14.0" might satisfy "^1.0.0", "1.14.0", ...
     *
     * @var string[]
     */
    protected array $satisfies = [];

    /**
     * @return string[]
     */
    public function getSatisfiedVersions(): array
    {
        return $this->satisfies;
    }

    /**
     * @deprecated
     *
     * @see addSatisfiedVersion()
     */
    public function addVersion(string $versionString): static
    {
        return $this->addSatisfiedVersion($versionString);
    }

    public function addSatisfiedVersion(string $versionString): static
    {
        $this->satisfies[] = $versionString;

        return $this;
    }

    /**
     * @phpstan-var array<string, array<\Mindscreen\YarnLock\Package>>
     */
    #[ArrayShape([
        'dependencies' => '\Mindscreen\YarnLock\Package[]',
        'optionalDependencies' => '\Mindscreen\YarnLock\Package[]',
        'devDependencies' => '\Mindscreen\YarnLock\Package[]',
        'peerDependencies' => '\Mindscreen\YarnLock\Package[]',
    ])]
    protected array $dependencies = [
        DependencyType::ProdRequired->value => [],
        DependencyType::ProdOptional->value => [],
        DependencyType::DevRequired->value => [],
        DependencyType::PeerRequired->value => [],
    ];

    /**
     * @return \Mindscreen\YarnLock\Package[]
     *
     * @see getProdRequiredDependencies()
     */
    #[Deprecated(
        reason: 'Ambiguous name',
        replacement: '%class%::getProdRequiredDependencies()',
    )]
    public function getDependencies(): array
    {
        return $this->getProdRequiredDependencies();
    }

    /**
     * @return \Mindscreen\YarnLock\Package[]
     */
    public function getProdRequiredDependencies(): array
    {
        return $this->dependencies[DependencyType::ProdRequired->value];
    }

    /**
     * @return \Mindscreen\YarnLock\Package[]
     */
    #[Deprecated(
        reason: 'Inconsistent naming.',
        replacement: '%class%::getProdOptionalDependencies',
    )]
    public function getOptionalDependencies(): array
    {
        return $this->dependencies[DependencyType::ProdOptional->value];
    }

    /**
     * @return \Mindscreen\YarnLock\Package[]
     */
    public function getProdOptionalDependencies(): array
    {
        return $this->dependencies[DependencyType::ProdOptional->value];
    }

    /**
     * @return \Mindscreen\YarnLock\Package[]
     */
    public function getDevRequiredDependencies(): array
    {
        return $this->dependencies[DependencyType::DevRequired->value];
    }

    /**
     * @return \Mindscreen\YarnLock\Package[]
     */
    public function getPeerRequiredDependencies(): array
    {
        return $this->dependencies[DependencyType::PeerRequired->value];
    }

    /**
     * @return \Mindscreen\YarnLock\Package[]
     */
    public function getAllDependencies(): array
    {
        return array_merge(
            $this->getProdRequiredDependencies(),
            $this->getProdOptionalDependencies(),
            $this->getPeerRequiredDependencies(),
            $this->getDevRequiredDependencies(),
        );
    }

    /**
     * @return \Mindscreen\YarnLock\Package[]
     */
    public function getDependenciesByType(DependencyType $type): array
    {
        return match ($type) {
            DependencyType::ProdRequired => $this->getProdRequiredDependencies(),
            DependencyType::ProdOptional => $this->getProdOptionalDependencies(),
            DependencyType::DevRequired => $this->getDevRequiredDependencies(),
            DependencyType::PeerRequired => $this->getPeerRequiredDependencies(),
        };
    }

    /**
     * Packages that require this package, i.e. packages who's dependencies are
     * (in part) resolved by this one.
     *
     * @var \Mindscreen\YarnLock\Package[]
     */
    protected array $resolves = [];

    /**
     * @return \Mindscreen\YarnLock\Package[]
     */
    public function getResolves(): array
    {
        return $this->resolves;
    }

    /**
     * @internal
     */
    public function addResolves(Package $package): static
    {
        if (!in_array($package, $this->resolves)) {
            $this->resolves[] = $package;
        }

        return $this;
    }

    /**
     * Add a package as dependency to the current one.
     */
    public function addDependency(Package $package, DependencyType $type = DependencyType::ProdRequired): static
    {
        if (in_array($package, $this->dependencies[$type->value])) {
            return $this;
        }

        $this->dependencies[$type->value][] = $package;
        $package->addResolves($this);

        return $this;
    }

    /**
     * Printing a package should contain it's name and version.
     */
    public function __toString(): string
    {
        return $this->getName() . '@' . $this->getVersion();
    }
}
