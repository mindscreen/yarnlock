<?php

namespace Mindscreen\YarnLock;

class Parser
{

    /**
     * Parse the yarn.lock format @link{https://yarnpkg.com/lang/en/docs/yarn-lock/} into either an object or an
     * associative array
     * @param string $input
     * @param bool $assoc
     * @return array|\stdClass
     * @throws ParserException
     */
    public function parse($input, $assoc = false)
    {
        if (!is_string($input)) {
            throw new \InvalidArgumentException('Parser input is expected to be a string.', 1519142104);
        }
        $data = new \stdClass();
        $current = $data;
        $lines = explode("\n", $input);
        $indentationCharacter = null;
        $indentationDepth = null;
        $indentationLevel = 0;
        $requireKey = false;
        foreach ($lines as $l => $line) {
            $l++;
            $line = rtrim($line);
            if (empty($line)) {
                continue;
            }
            if ($indentationCharacter === null && ctype_space($line[0])) {
                $indentationCharacter = $line[0];
            }
            for ($i = 0; $i < strlen($line); $i++) {
                if (ctype_space($line[$i])) {
                    if ($line[$i] !== $indentationCharacter) {
                        throw new ParserException(sprintf('Mixed indentation characters at line %s', $l), 1519140104);
                    }
                } else {
                    if ($i > 0) {
                        $line = substr($line, $i);
                        if ($line[0] === '#') {
                            // comment
                            break;
                        }
                        if ($indentationDepth === null) {
                            $indentationDepth = $i;
                        } else {
                            if ($i % $indentationDepth !== 0) {
                                throw new ParserException(sprintf('Indentation depth is not constant at line %s', $l), 1519140379);
                            }
                            if ($i / $indentationDepth > $indentationLevel) {
                                throw new ParserException(sprintf('Unexpected indentation at line %s', $l), 1519140493);
                            }
                        }
                        $newIndentationLevel = $i / $indentationDepth;
                    } else {
                        $newIndentationLevel = 0;
                    }
                    if ($newIndentationLevel < $indentationLevel) {
                        if ($requireKey) {
                            throw new ParserException('Expecting property at line ' . $l, 1519142311);
                        }
                        for ($j = $indentationLevel; $j > $newIndentationLevel; $j--) {
                            $current = $current->__parent;
                        }
                    }
                    $indentationLevel = $newIndentationLevel;
                    break;
                }
            }
            if ($line[0] === '#') {
                continue;
            }
            if ($line[strlen($line) - 1] == ':') {
                $indentationLevel += 1;
                $currentKey = substr($line, 0, -1);
                $current->$currentKey = new \stdClass();
                $current->$currentKey->__parent = $current;
                $current = $current->$currentKey;
                $requireKey = true;
            } else {
                $requireKey = false;
                $value = explode(' ', $line, 2);
                $currentKey = $value[0];
                if (count($value) === 1) {
                    throw new ParserException('Expecting value at line ' . $l, 1519141916);
                }
                $current->$currentKey = self::parseValue($value[1]);
            }
        }
        if ($requireKey) {
            throw new ParserException('Unexpected EOF, expecting property', 1519142311);
        }
        self::cleanupObject($data);
        if ($assoc) {
            return self::convertObjectToArray($data);
        }
        return $data;
    }

    protected static function parseValue($input)
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
        if ($input[0] === '"' && $input[strlen($input) - 1] === '"') {
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

    protected static function cleanupObject(&$object)
    {
        unset($object->__parent);
        foreach (get_object_vars($object) as $key => $value) {
            if ($value instanceof \stdClass) {
                self::cleanupObject($value);
            }
        }
    }

    protected static function convertObjectToArray($object)
    {
        if (is_object($object)) {
            $object = (array)$object;
        }
        foreach ($object as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $object[$key] = self::convertObjectToArray($value);
            }
        }
        return $object;
    }
}