<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Support;

use InvalidArgumentException;
use stdClass;

/**
 * Validates decoded JSON (objects as stdClass) against the subset of JSON Schema that docs/manifest.schema.json uses.
 * A keyword outside the subset is an error, so a schema can never pass silently because it uses something this does not check.
 */
final class JsonSchemaSubset
{
    private const KEYWORDS = [
        '$schema', '$id', '$defs', '$ref', 'title', 'description',
        'type', 'enum', 'const', 'pattern', 'minLength', 'minimum',
        'required', 'properties', 'additionalProperties',
        'items', 'minItems', 'uniqueItems', 'anyOf',
    ];

    public function __construct(private readonly stdClass $root)
    {
    }

    /** @return list<string> one "<path>: <rule>" line per violation; empty when the data satisfies the schema */
    public function validate(mixed $data): array
    {
        return $this->check($this->root, $data, '');
    }

    /** @return list<string> */
    private function check(stdClass $schema, mixed $data, string $path): array
    {
        $where = $path === '' ? '/' : $path;
        foreach (array_keys(get_object_vars($schema)) as $keyword) {
            if (! in_array($keyword, self::KEYWORDS, true)) {
                throw new InvalidArgumentException(sprintf('Unsupported JSON Schema keyword [%s] at %s', $keyword, $where));
            }
        }

        if (isset($schema->{'$ref'})) {
            return $this->check($this->resolve((string) $schema->{'$ref'}), $data, $path);
        }

        $errors = [];
        if (isset($schema->type) && ! $this->hasType($schema->type, $data)) {
            return [sprintf('%s: expected type %s', $where, implode(' or ', (array) $schema->type))];
        }
        if (isset($schema->enum) && ! in_array($data, $schema->enum, true)) {
            $errors[] = sprintf('%s: value is not one of the allowed values', $where);
        }
        if (property_exists($schema, 'const') && $data !== $schema->const) {
            $errors[] = sprintf('%s: value must equal the constant', $where);
        }
        if (is_string($data)) {
            $errors = [...$errors, ...$this->checkString($schema, $data, $where)];
        }
        if (is_int($data) || is_float($data)) {
            if (isset($schema->minimum) && $data < $schema->minimum) {
                $errors[] = sprintf('%s: must be at least %s', $where, $schema->minimum);
            }
        }
        if (is_array($data)) {
            $errors = [...$errors, ...$this->checkArray($schema, $data, $path, $where)];
        }
        if ($data instanceof stdClass) {
            $errors = [...$errors, ...$this->checkObject($schema, $data, $path, $where)];
        }
        if (isset($schema->anyOf) && ! $this->matchesAny($schema->anyOf, $data, $path)) {
            $errors[] = sprintf('%s: matches none of the allowed alternatives', $where);
        }

        return $errors;
    }

    /** @return list<string> */
    private function checkString(stdClass $schema, string $data, string $where): array
    {
        $errors = [];
        if (isset($schema->pattern) && preg_match('~' . $schema->pattern . '~u', $data) !== 1) {
            $errors[] = sprintf('%s: does not match the pattern %s', $where, $schema->pattern);
        }
        if (isset($schema->minLength) && mb_strlen($data) < $schema->minLength) {
            $errors[] = sprintf('%s: needs at least %d characters', $where, $schema->minLength);
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $data
     * @return list<string>
     */
    private function checkArray(stdClass $schema, array $data, string $path, string $where): array
    {
        $errors = [];
        if (isset($schema->minItems) && count($data) < $schema->minItems) {
            $errors[] = sprintf('%s: needs at least %d items', $where, $schema->minItems);
        }
        if (($schema->uniqueItems ?? false) === true && count(array_unique(array_map(static fn(mixed $item): string => serialize($item), $data))) !== count($data)) {
            $errors[] = sprintf('%s: items must be unique', $where);
        }
        if (isset($schema->items)) {
            foreach ($data as $index => $item) {
                $errors = [...$errors, ...$this->check($schema->items, $item, $path . '/' . $index)];
            }
        }

        return $errors;
    }

    /** @return list<string> */
    private function checkObject(stdClass $schema, stdClass $data, string $path, string $where): array
    {
        $errors = [];
        $values = get_object_vars($data);
        foreach ($schema->required ?? [] as $key) {
            if (! array_key_exists($key, $values)) {
                $errors[] = sprintf('%s: missing required key %s', $where, $key);
            }
        }

        $known = isset($schema->properties) ? get_object_vars($schema->properties) : [];
        $additional = $schema->additionalProperties ?? true;
        foreach ($values as $key => $value) {
            $key = (string) $key;
            if (array_key_exists($key, $known)) {
                $errors = [...$errors, ...$this->check($known[$key], $value, $path . '/' . $key)];
            } elseif ($additional === false) {
                $errors[] = sprintf('%s: unexpected key %s', $where, $key);
            } elseif ($additional instanceof stdClass) {
                $errors = [...$errors, ...$this->check($additional, $value, $path . '/' . $key)];
            }
        }

        return $errors;
    }

    /** @param list<stdClass> $alternatives */
    private function matchesAny(array $alternatives, mixed $data, string $path): bool
    {
        foreach ($alternatives as $alternative) {
            if ($this->check($alternative, $data, $path) === []) {
                return true;
            }
        }

        return false;
    }

    /** @param string|list<string> $types */
    private function hasType(string|array $types, mixed $data): bool
    {
        foreach ((array) $types as $type) {
            $matches = match ($type) {
                'string' => is_string($data),
                'integer' => is_int($data),
                'number' => is_int($data) || is_float($data),
                'boolean' => is_bool($data),
                'null' => $data === null,
                'array' => is_array($data),
                'object' => $data instanceof stdClass,
                default => throw new InvalidArgumentException(sprintf('Unsupported JSON Schema type [%s]', $type)),
            };
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private function resolve(string $reference): stdClass
    {
        $node = $this->root;
        foreach (explode('/', ltrim($reference, '#/')) as $segment) {
            $node = is_object($node) && property_exists($node, $segment) ? $node->{$segment} : null;
        }
        if (! $node instanceof stdClass) {
            throw new InvalidArgumentException(sprintf('Cannot resolve the reference %s', $reference));
        }

        return $node;
    }
}
