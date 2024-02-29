<?php

declare(strict_types=1);

namespace Mindscreen\YarnLock;

enum ParserErrorCode: int
{

    /**
     * Old 1519140104.
     */
    case MixedIndentStyle = 1;

    /**
     * Old 1519140379.
     */
    case MixedIndentSize = 2;

    /**
     * Old 1519140493.
     */
    case UnexpectedIndentation = 3;

    /**
     * Old 1519142311.
     */
    case MissingProperty = 4;

    /**
     * Old 1519141916.
     */
    case MissingValue = 5;

    /**
     * Old 1519142311.
     */
    case UnexpectedEof = 6;
}
