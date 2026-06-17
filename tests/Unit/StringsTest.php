<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Lsr\Helpers\Tools\Strings;

class StringsTest extends Unit
{
    /**
     * @return string[][]
     */
    public function camelCaseStrings(): array {
        return [
            ['file_number', 'fileNumber'],
            ['test string', 'testString'],
            ['camelCase', 'camelCase'],
            ['', ''],
            ['TestString', 'testString'],
            ['test', 'test'],
            ['Test', 'test'],
        ];
    }

    /**
     * @return string[][]
     */
    public function pascalCaseStrings(): array {
        return [
            ['file_number', 'FileNumber'],
            ['test string', 'TestString'],
            ['camelCase', 'CamelCase'],
            ['', ''],
            ['TestString', 'TestString'],
            ['test', 'Test'],
            ['Test', 'Test'],
        ];
    }

    /**
     * @return string[][]
     */
    public function snakeCaseStrings(): array {
        return [
            ['file_number', 'file_number'],
            ['test string', 'test_string'],
            ['camelCase', 'camel_case'],
            ['', ''],
            ['TestString', 'test_string'],
            ['test', 'test'],
            ['Test', 'test'],
        ];
    }

    /**
     * @param string $original
     * @param string $expected
     *
     * @dataProvider camelCaseStrings
     */
    public function test_to_camel_case(string $original, string $expected): void {
        $this::assertSame($expected, Strings::toCamelCase($original));
    }

    /**
     * @param string $original
     * @param string $expected
     *
     * @dataProvider pascalCaseStrings
     */
    public function test_to_pascal_case(string $original, string $expected): void {
        $this::assertSame($expected, Strings::toPascalCase($original));
    }

    /**
     * @param string $original
     * @param string $expected
     *
     * @dataProvider snakeCaseStrings
     */
    public function test_to_snake_case(string $original, string $expected): void {
        $this::assertSame($expected, Strings::toSnakeCase($original));
    }
}
