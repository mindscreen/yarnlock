<?php

use Mindscreen\YarnLock\Parser;
use Mindscreen\YarnLock\ParserException;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{

    /**
     * @var Parser
     */
    protected $parser;

    protected function setUp()
    {
        $this->parser = new Parser();
    }

    /**
     * Not using valid input should throw an exception
     * @throws ParserException
     */
    public function testNullInput()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1519142104);
        $this->parser->parse(null);
    }

    /**
     * Comments don't have to follow indentation rules
     * @throws ParserException
     */
    public function testComments()
    {
        $fileContents = file_get_contents('tests/parserinput/comments');
        $result = $this->parser->parse($fileContents, true);
        $this->assertEquals([
            'foo' => 4,
            'bar' => [
                'foo' => false,
                'baz' => null,
            ],
            'baz' => true,
        ], $result);
    }

    /**
     * Using mixed indentation characters (like tab and space) should throw an exception
     * @throws ParserException
     */
    public function testMixedIndentations()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(1519140104);
        $fileContents = file_get_contents('tests/parserinput/mixed_indentation');
        $this->parser->parse($fileContents, true);
    }

    /**
     * Inconsistent indentations should throw an exception
     * @throws ParserException
     */
    public function testMixedIndentationDepth()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(1519140379);
        $fileContents = file_get_contents('tests/parserinput/mixed_indentation_depth');
        $this->parser->parse($fileContents, true);
    }

    /**
     * Indentation should work with other indentation than two spaces
     * @throws ParserException
     */
    public function testDifferentIndentationDepth()
    {
        $fileContents = file_get_contents('tests/parserinput/indentation_depth');
        $result = $this->parser->parse($fileContents, true);
        $this->assertEquals([
            'foo' => [
                'bar' => 'bar',
                'baz' => [
                    'foobar' => true
                ]
            ]
        ], $result);
    }

    /**
     * A key-value cannot be further indented as the previous one
     * @throws ParserException
     */
    public function testUnexpectedIndentation()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(1519140493);
        $fileContents = file_get_contents('tests/parserinput/unexpected_indentation');
        $this->parser->parse($fileContents, true);
    }

    /**
     * An array key requires following properties
     * @throws ParserException
     */
    public function testMissingProperty()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(1519142311);
        $fileContents = file_get_contents('tests/parserinput/missing_property');
        $this->parser->parse($fileContents, true);
    }

    /**
     * Comments following an array key should still require properties
     * @throws ParserException
     */
    public function testMissingProperty2()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(1519142311);
        $fileContents = file_get_contents('tests/parserinput/missing_property2');
        $this->parser->parse($fileContents, true);
    }

    /**
     * The input ending on an array object without values should throw an exception
     * @throws ParserException
     */
    public function testMissingPropertyEof()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(1519142311);
        $fileContents = file_get_contents('tests/parserinput/missing_property_eof');
        $this->parser->parse($fileContents, true);
    }

    /**
     * Keys without value should throw an exception
     * @throws ParserException
     */
    public function testMissingPropertyValue()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(1519141916);
        $this->parser->parse('foo', true);
    }

    /**
     * Different values should yield different value-types
     * @throws ParserException
     */
    public function testDataTypes()
    {
        $fileContents = file_get_contents('tests/parserinput/datatypes');
        $result = $this->parser->parse($fileContents);
        $this->assertSame(true, $result->bool_t);
        $this->assertSame(false, $result->bool_f);
        $this->assertSame(null, $result->unset);
        $this->assertSame(42, $result->int);
        $this->assertSame(13.37, $result->float);
        $this->assertSame('true', $result->string_t);
        $this->assertSame('string string', $result->string);
        $this->assertSame('12.13.14', $result->other);
    }

    /**
     * The parser should create a valid \stdClass structure
     * @throws ParserException
     */
    public function testYarnExampleObject()
    {
        $fileContents = file_get_contents('tests/parserinput/valid_input');
        $result = $this->parser->parse($fileContents);
        $this->assertEquals(true, $result instanceof \stdClass);
        $this->assertEquals(4, count(get_object_vars($result)));
        $this->assertObjectHasAttribute('package-1@^1.0.0', $result);
        $key = 'package-3@^3.0.0';
        $package3 = $result->$key;
        $this->assertObjectHasAttribute('version', $package3);
        $this->assertObjectHasAttribute('resolved', $package3);
        $this->assertObjectHasAttribute('dependencies', $package3);
        $package3_dependencies = $package3->dependencies;
        $this->assertEquals(1, count(get_object_vars($package3_dependencies)));
    }

    /**
     * The parser should create a valid array structure
     * @throws ParserException
     */
    public function testYarnExampleArray()
    {
        $fileContents = file_get_contents('tests/parserinput/valid_input');
        $result = $this->parser->parse($fileContents, true);
        $this->assertEquals(true, is_array($result));
        $this->assertEquals(4, count($result));
        $this->assertArrayHasKey('package-1@^1.0.0', $result);
        $package3 = $result['package-3@^3.0.0'];
        $this->assertArrayHasKey('version', $package3);
        $this->assertArrayHasKey('resolved', $package3);
        $this->assertArrayHasKey('dependencies', $package3);
        $package3_dependencies = $package3['dependencies'];
        $this->assertEquals(true, is_array($package3_dependencies));
        $this->assertEquals(1, count($package3_dependencies));
    }

    /**
     * Single-value keys should not be split at spaces if they are surrounded with quotes
     * @throws ParserException
     */
    public function testQuotedKeys()
    {
        $fileContents = file_get_contents('tests/parserinput/quoted-key');
        $result = $this->parser->parse($fileContents, true);
        $data = $result['test'];
        foreach (['foo', 'bar', 'foo bar', 'foobar'] as $item) {
            $this->assertArrayHasKey($item, $data);
            $this->assertEquals($item, $data[$item]);
        }
    }

    public function testParseVersionStrings()
    {
        $input = 'minimatch@^3.0.0, minimatch@^3.0.2, "minimatch@2 || 3"';
        $versionStrings = Parser::parseVersionStrings($input);
        $this->assertEquals(['minimatch@^3.0.0', 'minimatch@^3.0.2', 'minimatch@2 || 3'], $versionStrings);

        $input = 'babel-types@^6.10.2, babel-types@^6.14.0, babel-types@^6.15.0';
        $versionStrings = Parser::parseVersionStrings($input);
        $this->assertEquals(['babel-types@^6.10.2', 'babel-types@^6.14.0', 'babel-types@^6.15.0'], $versionStrings);

        $input = 'array-uniq@^1.0.1';
        $versionStrings = Parser::parseVersionStrings($input);
        $this->assertEquals(['array-uniq@^1.0.1'], $versionStrings);

        $input = '"cssom@>= 0.3.0 < 0.4.0", cssom@0.3.x';
        $versionStrings = Parser::parseVersionStrings($input);
        $this->assertEquals(['cssom@>= 0.3.0 < 0.4.0', 'cssom@0.3.x'], $versionStrings);

        $input = '"graceful-readlink@>= 1.0.0"';
        $versionStrings = Parser::parseVersionStrings($input);
        $this->assertEquals(['graceful-readlink@>= 1.0.0'], $versionStrings);
    }
}
