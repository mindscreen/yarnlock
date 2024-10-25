<?php

namespace Mindscreen\YarnLock\Tests\Unit;

use Mindscreen\YarnLock\Parser;
use Mindscreen\YarnLock\ParserException;

/**
 * @covers \Mindscreen\YarnLock\Parser
 */
class ParserTest extends TestBase
{

    /**
     * @var Parser
     */
    protected $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new Parser();
    }

    /**
     * Not using valid input should throw an exception.
     *
     * @throws ParserException
     */
    public function testNullInput()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1519142104);
        $this->parser->parse(null);
    }

    /**
     * Comments don't have to follow indentation rules
     * @throws ParserException
     */
    public function testComments()
    {
        $fileContents = static::getInput('comments.txt');
        $result = $this->parser->parse($fileContents, true);
        static::assertSame(
            [
                'foo' => 4,
                'bar' => [
                    'foo' => false,
                    'baz' => null,
                ],
                'baz' => true,
            ],
            $result
        );
    }

    /**
     * Using mixed indentation characters (like tab and space) should throw an exception
     * @throws ParserException
     */
    public function testMixedIndentations()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(1519140104);
        $fileContents = static::getInput('mixed_indentation.txt');
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
        $fileContents = static::getInput('mixed_indentation_depth.txt');
        $this->parser->parse($fileContents, true);
    }

    /**
     * Indentation should work with other indentation than two spaces
     * @throws ParserException
     */
    public function testDifferentIndentationDepth()
    {
        $fileContents = static::getInput('indentation_depth.txt');
        $result = $this->parser->parse($fileContents, true);
        static::assertSame(
            [
                'foo' => [
                    'bar' => 'bar',
                    'baz' => [
                        'foobar' => true,
                    ],
                ],
            ],
            $result
        );
    }

    /**
     * A key-value cannot be further indented as the previous one
     * @throws ParserException
     */
    public function testUnexpectedIndentation()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(1519140493);
        $fileContents = static::getInput('unexpected_indentation.txt');
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
        $fileContents = static::getInput('missing_property.txt');
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
        $fileContents = static::getInput('missing_property2.txt');
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
        $fileContents = static::getInput('missing_property_eof.txt');
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
        $fileContents = static::getInput('datatypes.txt');
        $result = $this->parser->parse($fileContents);
        static::assertSame(true, $result->bool_t);
        static::assertSame(false, $result->bool_f);
        static::assertSame(null, $result->unset);
        static::assertSame(42, $result->int);
        static::assertSame(13.37, $result->float);
        static::assertSame('true', $result->string_t);
        static::assertSame('string string', $result->string);
        static::assertSame('12.13.14', $result->other);
    }

    /**
     * The parser should create a valid \stdClass structure
     * @throws ParserException
     */
    public function testYarnExampleObject()
    {
        $fileContents = static::getInput('valid_input.txt');
        $result = $this->parser->parse($fileContents);
        static::assertSame(true, $result instanceof \stdClass);
        static::assertSame(4, count(get_object_vars($result)));
        static::assertObjectHasAttribute('package-1@^1.0.0', $result);
        $key = 'package-3@^3.0.0';
        $package3 = $result->$key;
        static::assertObjectHasAttribute('version', $package3);
        static::assertObjectHasAttribute('resolved', $package3);
        static::assertObjectHasAttribute('dependencies', $package3);
        $package3_dependencies = $package3->dependencies;
        static::assertSame(1, count(get_object_vars($package3_dependencies)));
    }

    /**
     * The parser should create a valid array structure
     * @throws ParserException
     */
    public function testYarnExampleArray()
    {
        $fileContents = static::getInput('valid_input.txt');
        $result = $this->parser->parse($fileContents, true);
        static::assertSame(true, is_array($result));
        static::assertSame(4, count($result));
        static::assertArrayHasKey('package-1@^1.0.0', $result);
        $package3 = $result['package-3@^3.0.0'];
        static::assertArrayHasKey('version', $package3);
        static::assertArrayHasKey('resolved', $package3);
        static::assertArrayHasKey('dependencies', $package3);
        $package3_dependencies = $package3['dependencies'];
        static::assertSame(true, is_array($package3_dependencies));
        static::assertSame(1, count($package3_dependencies));
    }

    /**
     * The parser should split the package name and version parts
     *
     * Scoped packages names (prefixed with @) and Git version references should be detected properly.
     *
     * @param string $versionString
     * @param array{string, string} $expectedResult
     * @dataProvider provideVersionsForSplitting
     * @return void
     */
    public function testVersionSplitting($versionString, array $expectedResult)
    {
        self::assertSame($expectedResult, Parser::splitVersionString($versionString));
    }

    /**
     * @return array<string, array{
     *     versionString: string,
     *     expectedResult: array{string, string}
     * }>
     */
    public static function provideVersionsForSplitting(): array
    {
        return [
            'simple package, simple version'           => [
                'versionString'  => 'gulp-sourcemaps@2.6.4',
                'expectedResult' => ['gulp-sourcemaps', '2.6.4'],
            ],
            'namespaced package, simple version'       => [
                'versionString'  => '@gulp-sourcemaps/identity-map@1.X',
                'expectedResult' => ['@gulp-sourcemaps/identity-map', '1.X'],
            ],
            'simple package, Git semver reference'     => [
                'versionString'  => 'foo-bar@git+ssh://user@host:1234/foo/bar#semver:^1.2.3',
                'expectedResult' => ['foo-bar', 'git+ssh://user@host:1234/foo/bar#semver:^1.2.3'],
            ],
            'namespaced package, Git semver reference' => [
                'versionString'  => '@foo/bar@git+ssh://user@host:1234/foo/bar#semver:^1.2.3',
                'expectedResult' => ['@foo/bar', 'git+ssh://user@host:1234/foo/bar#semver:^1.2.3'],
            ],
            'simple package, Git tag reference'        => [
                'versionString'  => 'foo-bar@git://user@host/foo/bar.git#v1.2.3',
                'expectedResult' => ['foo-bar', 'git://user@host/foo/bar.git#v1.2.3'],
            ],
            'namespaced package, Git tag reference'    => [
                'versionString'  => '@foo/bar@git://user@host/foo/bar.git#v1.2.3',
                'expectedResult' => ['@foo/bar', 'git://user@host/foo/bar.git#v1.2.3'],
            ],
            'simple package, file reference'           => [
                'versionString'  => 'foo-bar@file:vendor/foo/bar',
                'expectedResult' => ['foo-bar', 'file:vendor/foo/bar'],
            ],
            'namespaced package, file reference'       => [
                'versionString'  => '@foo/bar@file:vendor/foo/bar',
                'expectedResult' => ['@foo/bar', 'file:vendor/foo/bar'],
            ],
        ];
    }


    /**
     * Single-value keys should not be split at spaces if they are surrounded with quotes
     * @throws ParserException
     */
    public function testQuotedKeys()
    {
        $fileContents = static::getInput('quoted-key.txt');
        $result = $this->parser->parse($fileContents, true);
        $data = $result['test'];
        foreach (['foo', 'bar', 'foo bar', 'foobar'] as $item) {
            static::assertArrayHasKey($item, $data);
            static::assertSame($item, $data[$item]);
        }
    }

    public function testParseVersionStrings()
    {
        $input = 'minimatch@^3.0.0, minimatch@^3.0.2, "minimatch@2 || 3"';
        $versionStrings = Parser::parseVersionStrings($input);
        static::assertSame(['minimatch@^3.0.0', 'minimatch@^3.0.2', 'minimatch@2 || 3'], $versionStrings);

        $input = 'babel-types@^6.10.2, babel-types@^6.14.0, babel-types@^6.15.0';
        $versionStrings = Parser::parseVersionStrings($input);
        static::assertSame(['babel-types@^6.10.2', 'babel-types@^6.14.0', 'babel-types@^6.15.0'], $versionStrings);

        $input = 'array-uniq@^1.0.1';
        $versionStrings = Parser::parseVersionStrings($input);
        static::assertSame(['array-uniq@^1.0.1'], $versionStrings);

        $input = '"cssom@>= 0.3.0 < 0.4.0", cssom@0.3.x';
        $versionStrings = Parser::parseVersionStrings($input);
        static::assertSame(['cssom@>= 0.3.0 < 0.4.0', 'cssom@0.3.x'], $versionStrings);

        $input = '"graceful-readlink@>= 1.0.0"';
        $versionStrings = Parser::parseVersionStrings($input);
        static::assertSame(['graceful-readlink@>= 1.0.0'], $versionStrings);
    }
}
