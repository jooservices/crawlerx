<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Support;

use InvalidArgumentException;
use JOOservices\CrawlerX\Tests\Support\JsonSchemaSubset;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonSchemaSubsetTest extends TestCase
{
    /** @return array<string, array{string, string, list<string>}> schema, data, expected violations */
    public static function cases(): array
    {
        return [
            'string passes' => ['{"type":"string"}', '"a"', []],
            'string fails on a number' => ['{"type":"string"}', '1', ['/: expected type string']],
            'integer rejects a float' => ['{"type":"integer"}', '1.5', ['/: expected type integer']],
            'number accepts an integer' => ['{"type":"number"}', '2', []],
            'union of types' => ['{"type":["string","null"]}', 'null', []],
            'boolean' => ['{"type":"boolean"}', '"true"', ['/: expected type boolean']],
            'array is not an object' => ['{"type":"object"}', '[]', ['/: expected type object']],
            'object is not an array' => ['{"type":"array"}', '{}', ['/: expected type array']],
            'required key missing' => ['{"type":"object","required":["a"]}', '{}', ['/: missing required key a']],
            'property is checked' => ['{"type":"object","properties":{"a":{"type":"string"}}}', '{"a":1}', ['/a: expected type string']],
            'additional property refused' => ['{"type":"object","properties":{"a":{}},"additionalProperties":false}', '{"a":1,"b":2}', ['/: unexpected key b']],
            'additional property allowed when not restricted' => ['{"type":"object","properties":{"a":{}}}', '{"a":1,"b":2}', []],
            'additional property checked' => ['{"type":"object","additionalProperties":{"type":"string"}}', '{"a":1}', ['/a: expected type string']],
            'items are checked' => ['{"type":"array","items":{"type":"integer"}}', '[1,"x"]', ['/1: expected type integer']],
            'enum' => ['{"enum":["a","b"]}', '"c"', ['/: value is not one of the allowed values']],
            'const' => ['{"const":"site"}', '"x"', ['/: value must equal the constant']],
            'pattern' => ['{"type":"string","pattern":"^[a-z]+$"}', '"A1"', ['/: does not match the pattern ^[a-z]+$']],
            'min items' => ['{"type":"array","minItems":1}', '[]', ['/: needs at least 1 items']],
            'min length' => ['{"type":"string","minLength":2}', '"a"', ['/: needs at least 2 characters']],
            'minimum' => ['{"type":"integer","minimum":1}', '0', ['/: must be at least 1']],
            'unique items' => ['{"type":"array","uniqueItems":true}', '["a","a"]', ['/: items must be unique']],
            'any of passes' => ['{"anyOf":[{"type":"string"},{"type":"integer"}]}', '1', []],
            'any of fails' => ['{"anyOf":[{"type":"string"},{"type":"integer"}]}', 'true', ['/: matches none of the allowed alternatives']],
            'reference' => ['{"$defs":{"s":{"type":"string"}},"properties":{"a":{"$ref":"#/$defs/s"}}}', '{"a":1}', ['/a: expected type string']],
            'annotations are ignored' => ['{"$schema":"x","$id":"y","title":"t","description":"d","type":"string"}', '"a"', []],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('cases')]
    public function test_it_reports_the_path_and_the_rule_of_each_violation(string $schema, string $data, array $expected): void
    {
        $validator = new JsonSchemaSubset(json_decode($schema, false, 512, JSON_THROW_ON_ERROR));

        self::assertSame($expected, $validator->validate(json_decode($data, false, 512, JSON_THROW_ON_ERROR)));
    }

    public function test_a_keyword_it_does_not_implement_is_an_error_not_a_pass(): void
    {
        $validator = new JsonSchemaSubset(json_decode('{"oneOf":[{"type":"string"}]}', false, 512, JSON_THROW_ON_ERROR));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported JSON Schema keyword [oneOf] at /');
        $validator->validate('a');
    }

    public function test_a_reference_that_cannot_be_resolved_is_an_error(): void
    {
        $validator = new JsonSchemaSubset(json_decode('{"$ref":"#/$defs/missing"}', false, 512, JSON_THROW_ON_ERROR));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot resolve the reference #/$defs/missing');
        $validator->validate('a');
    }
}
