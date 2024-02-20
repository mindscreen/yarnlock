<?php

declare(strict_types=1);

namespace Mindscreen\YarnLock;

enum DependencyType: string
{
    case ProdRequired = 'dependencies';

    case ProdOptional ='optionalDependencies';

    case PeerRequired = 'peerDependencies';

    case DevRequired = 'devDependencies';
}
