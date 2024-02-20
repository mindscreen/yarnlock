<?php

declare(strict_types=1);

namespace Mindscreen\YarnLock;

class Parser
{

    /**
     * Parse the yarn.lock format @link{https://yarnpkg.com/lang/en/docs/yarn-lock/} into either an object or an
     * associative array
     *
     * @return array<string, mixed>|\stdClass
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function parse(string $input, bool $assoc = false): array|\stdClass
    {
        $data = new \stdClass();
        $current = $data;
        $lines = explode("\n", $input);
        $indentationCharacter = null;
        $indentationDepth = null;
        $indentationLevel = 0;
        $requireKey = false;
        foreach ($lines as $lineIndex => $line) {
            $lineNumber = $lineIndex + 1;
            $line = rtrim($line);

            if ($line === '') {
                continue;
            }

            if ($indentationCharacter === null && ctype_space($line[0])) {
                $indentationCharacter = $line[0];
            }

            for ($charIndex = 0; $charIndex < strlen($line); $charIndex++) {
                $char = substr($line, $charIndex, 1);
                if (ctype_space($char)) {
                    if ($char !== $indentationCharacter) {
                        throw new ParserException(
                            sprintf('Mixed indentation characters at line %s', $lineNumber),
                            ParserErrorCode::MixedIndentStyle->value,
                        );
                    }

                    continue;
                }

                if ($charIndex > 0) {
                    $line = substr($line, $charIndex);
                    if ($line[0] === '#') {
                        // comment
                        break;
                    }

                    if ($indentationDepth === null) {
                        $indentationDepth = $charIndex;
                    } else {
                        if ($charIndex % $indentationDepth !== 0) {
                            throw new ParserException(
                                sprintf('Indentation depth is not constant at line %s', $lineNumber),
                                ParserErrorCode::MixedIndentSize->value,
                            );
                        }

                        if ($charIndex / $indentationDepth > $indentationLevel) {
                            throw new ParserException(
                                sprintf('Unexpected indentation at line %s', $lineNumber),
                                ParserErrorCode::UnexpectedIndentation->value,
                            );
                        }
                    }
                    $newIndentationLevel = $charIndex / $indentationDepth;
                } else {
                    $newIndentationLevel = 0;
                }

                if ($newIndentationLevel < $indentationLevel) {
                    if ($requireKey) {
                        throw new ParserException(
                            sprintf('Expecting property at line %d', $lineNumber),
                            ParserErrorCode::MissingProperty->value,
                        );
                    }

                    for ($j = $indentationLevel; $j > $newIndentationLevel; $j--) {
                        $current = $current->__parent;
                    }
                }
                $indentationLevel = $newIndentationLevel;
                break;
            }

            if (substr($line, 0, 1) === '#') {
                continue;
            }

            if (str_ends_with($line, ':')) {
                $indentationLevel += 1;
                $currentKey = substr($line, 0, -1);
                $current->$currentKey = new \stdClass();
                $current->$currentKey->__parent = $current;
                $current = $current->$currentKey;
                $requireKey = true;
            } else {
                $requireKey = false;
                if ($line[0] === '"') {
                    $currentKey = '';
                    for ($j = 1; $j < strlen($line); $j++) {
                        if ($line[$j] === '"') {
                            $j++;
                            break;
                        }
                        $currentKey .= $line[$j];
                    }
                    $value = substr($line, $j);
                } else {
                    $parts = explode(' ', $line, 2);
                    $currentKey = $parts[0];
                    if (count($parts) === 1) {
                        throw new ParserException(
                            sprintf('Expecting value at line %d', $lineNumber),
                            ParserErrorCode::MissingValue->value,
                        );
                    }
                    $value = $parts[1];
                }
                $current->$currentKey = static::parseValue(ltrim($value));
            }
        }
        if ($requireKey) {
            throw new ParserException(
                'Unexpected EOF, expecting property',
                ParserErrorCode::UnexpectedEof->value,
            );
        }

        static::cleanupObject($data);
        if ($assoc) {
            return static::convertObjectToArray($data);
        }

        return $data;
    }

    protected static function parseValue(string $input): mixed
    {
        if ($input === 'true') {
            return true;
        }

        if ($input === 'false') {
            return false;
        }

        if ($input === 'null') {
            return null;
        }

        if (str_starts_with($input, '"')
            && str_ends_with($input, '"')
        ) {
            return substr($input, 1, -1);
        }

        if (($value = filter_var($input, FILTER_VALIDATE_INT)) !== false) {
            return $value;
        }

        if (($value = filter_var($input, FILTER_VALIDATE_FLOAT)) !== false) {
            return $value;
        }

        return $input;
    }

    protected static function cleanupObject(object $object): void
    {
        unset($object->__parent);
        foreach (get_object_vars($object) as $value) {
            if ($value instanceof \stdClass) {
                static::cleanupObject($value);
            }
        }
    }

    /**
     * @param array<mixed>|object $object
     *
     * @return array<mixed>
     */
    protected static function convertObjectToArray(array|object $object): array
    {
        if (is_object($object)) {
            $object = (array) $object;
        }

        foreach ($object as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $object[$key] = static::convertObjectToArray($value);
            }
        }

        return $object;
    }

    /**
     * If parts of keys contain spaces, they are surrounded with quotes,
     * e.g. `minimatch@^3.0.0, minimatch@^3.0.2, "minimatch@2 || 3"`, which would
     * fail when simply splitting on spaces.
     *
     * @return string[]
     */
    public static function parseVersionStrings(string $key): array
    {
        $parts = preg_split('/\s*,\s*/', trim($key)) ?: [];
        foreach ($parts as &$part) {
            if (str_starts_with($part, '"') && str_ends_with($part, '"')) {
                $part = substr($part, 1, -1);
            }
        }

        return $parts;
    }

    /**
     * To avoid splitting on scoped package-names, every but the last @ are considered
     * package name.
     *
     * @return string[]
     */
    public static function splitVersionString(string $versionString): array
    {
         $parts = explode('@', $versionString);
         $version = array_pop($parts);

         return [
             implode('@', $parts),
             $version,
         ];
    }
}
