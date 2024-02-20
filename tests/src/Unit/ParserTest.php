<?php

declare(strict_types=1);

namespace Mindscreen\YarnLock\Tests\Unit;

use Mindscreen\YarnLock\ParserErrorCode;
use Mindscreen\YarnLock\Parser;
use Mindscreen\YarnLock\ParserException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Parser::class)]
#[CoversClass(ParserException::class)]
class ParserTest extends TestBase
{

    protected Parser $parser;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new Parser();
    }

    /**
     * Comments don't have to follow indentation rules.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testComments(): void
    {
        static::assertSame(
            [
                'foo' => 4,
                'bar' => [
                    'foo' => false,
                    'baz' => null,
                ],
                'baz' => true,
            ],
            $this->parser->parse(static::getInput('comments.txt'), true),
        );
    }

    /**
     * Using mixed indentation characters (like tab and space) should throw an exception.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testMixedIndentations(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(ParserErrorCode::MixedIndentStyle->value);
        $this->parser->parse(static::getInput('mixed_indentation.txt'), true);
    }

    /**
     * Inconsistent indentations should throw an exception.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testMixedIndentationDepth(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(ParserErrorCode::MixedIndentSize->value);
        $this->parser->parse(static::getInput('mixed_indentation_depth.txt'), true);
    }

    /**
     * Indentation should work with other indentation than two spaces.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testDifferentIndentationDepth(): void
    {
        static::assertSame(
            [
                'foo' => [
                    'bar' => 'bar',
                    'baz' => [
                        'foobar' => true,
                    ],
                ],
            ],
            $this->parser->parse(static::getInput('indentation_depth.txt'), true),
        );
    }

    /**
     * A key-value cannot be further indented as the previous one.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testUnexpectedIndentation(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(ParserErrorCode::UnexpectedIndentation->value);
        $this->parser->parse(static::getInput('unexpected_indentation.txt'), true);
    }

    /**
     * An array key requires following properties.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testMissingProperty(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(ParserErrorCode::MissingProperty->value);
        $this->parser->parse(static::getInput('missing_property.txt'), true);
    }

    /**
     * Comments following an array key should still require properties.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testMissingProperty2(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(ParserErrorCode::MissingProperty->value);
        $this->parser->parse(static::getInput('missing_property2.txt'), true);
    }

    /**
     * The input ending on an array object without values should throw an exception.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testMissingPropertyEof(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(ParserErrorCode::UnexpectedEof->value);
        $this->parser->parse(static::getInput('missing_property_eof.txt'), true);
    }

    /**
     * Keys without value should throw an exception.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testMissingPropertyValue(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionCode(ParserErrorCode::MissingValue->value);
        $this->parser->parse('foo', true);
    }

    /**
     * Different values should yield different value-types.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testDataTypes(): void
    {
        /** @var \stdClass $result */
        $result = $this->parser->parse(static::getInput('datatypes.txt'));
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
     * The parser should create a valid \stdClass structure.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testYarnExampleObject(): void
    {
        $result = $this->parser->parse(static::getInput('valid_input.txt'));

        static::assertIsObject($result);
        static::assertCount(4, get_object_vars($result));
        static::assertObjectHasProperty('package-1@^1.0.0', $result);
        $package3 = $result->{'package-3@^3.0.0'};
        static::assertObjectHasProperty('version', $package3);
        static::assertObjectHasProperty('resolved', $package3);
        static::assertObjectHasProperty('dependencies', $package3);
        $package3_dependencies = $package3->dependencies;
        static::assertCount(1, get_object_vars($package3_dependencies));
    }

    /**
     * The parser should create a valid array structure.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testYarnExampleArray(): void
    {
        $result = $this->parser->parse(static::getInput('valid_input.txt'), true);
        static::assertIsArray($result);
        static::assertCount(4, $result);
        static::assertArrayHasKey('package-1@^1.0.0', $result);
        $package3 = $result['package-3@^3.0.0'];
        static::assertArrayHasKey('version', $package3);
        static::assertArrayHasKey('resolved', $package3);
        static::assertArrayHasKey('dependencies', $package3);
        $package3_dependencies = $package3['dependencies'];
        static::assertIsArray($package3_dependencies);
        static::assertCount(1, $package3_dependencies);
    }

    /**
     * @return array<string, mixed>
     */
    public static function casesVersionSplitting(): array
    {
        return [
            'a' => [
                'expected' => ['gulp-sourcemaps', '2.6.4'],
                'versionString' => 'gulp-sourcemaps@2.6.4',
            ],
            'b' => [
                'expected' => ['@gulp-sourcemaps/identity-map', '1.X'],
                'versionString' => '@gulp-sourcemaps/identity-map@1.X',
            ],
        ];
    }

    /**
     * Scoped packages names should not be split at the first '@'.
     *
     * @param string[] $expected
     */
    #[DataProvider('casesVersionSplitting')]
    public function testVersionSplitting(array $expected, string $versionString): void
    {
        static::assertSame(
            $expected,
            Parser::splitVersionString($versionString),
        );
    }

    /**
     * Single-value keys should not be split at spaces if they are surrounded with quotes.
     *
     * @throws \Mindscreen\YarnLock\ParserException
     */
    public function testQuotedKeys(): void
    {
        /** @var array<string, mixed> $result */
        $result = $this->parser->parse(static::getInput('quoted-key.txt'), true);
        $data = $result['test'];
        foreach (['foo', 'bar', 'foo bar', 'foobar'] as $item) {
            static::assertArrayHasKey($item, $data);
            static::assertSame($item, $data[$item]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function casesParseVersionStrings(): array
    {
        return [
            'a' => [
                'expected' => [
                    'minimatch@^3.0.0', 'minimatch@^3.0.2', 'minimatch@2 || 3',
                ],
                'key' => 'minimatch@^3.0.0, minimatch@^3.0.2, "minimatch@2 || 3"',
            ],
            'b' => [
                'expected' => [
                    'babel-types@^6.10.2', 'babel-types@^6.14.0', 'babel-types@^6.15.0',
                ],
                'key' => 'babel-types@^6.10.2, babel-types@^6.14.0, babel-types@^6.15.0',
            ],
            'c' => [
                'expected' => [
                    'array-uniq@^1.0.1',
                ],
                'key' => 'array-uniq@^1.0.1',
            ],
            'd' => [
                'expected' => [
                    'cssom@>= 0.3.0 < 0.4.0', 'cssom@0.3.x'
                ],
                'key' => '"cssom@>= 0.3.0 < 0.4.0", cssom@0.3.x',
            ],
            'e' => [
                'expected' => [
                    'graceful-readlink@>= 1.0.0'
                ],
                'key' => '"graceful-readlink@>= 1.0.0"',
            ],
        ];
    }

    /**
     * @param string[] $expected
     */
    #[DataProvider('casesParseVersionStrings')]
    public function testParseVersionStrings(array $expected, string $key): void
    {
        static::assertSame($expected, Parser::parseVersionStrings($key));
    }
}
