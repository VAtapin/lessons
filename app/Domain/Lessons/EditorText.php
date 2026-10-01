<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

/** Internal paths and validation-copy mechanics; it never returns a document DTO. */
final class EditorText
{
    /** Replace only present, required, blank strings. The strict validator checks every other value. */
    public static function prepare(array &$data, array $base, array $fields, array $context): array
    {
        $leaves = [];
        foreach ($fields as $field) {
            if (! $field['required']) {
                continue;
            }
            foreach (self::paths($data, [...$base, ...$field['path']]) as $path) {
                $value = self::get($data, $path);
                if (! is_string($value)) {
                    continue;
                }
                $blank = $field['blankMode'] === 'trim' ? trim($value) === ''
                    : preg_match('/\A[\p{Z}\s]*\z/u', $value) === 1;
                $leaves[] = ['path' => $path, 'original' => $value, 'blank' => $blank, ...$context];
                if ($blank) {
                    self::put($data, $path, self::replacement($value));
                }
            }
        }

        return $leaves;
    }

    public static function restore(array $data, array $leaves): array
    {
        foreach ($leaves as $leaf) {
            if ($leaf['blank']) {
                self::put($data, $leaf['path'], $leaf['original']);
            }
        }

        return $data;
    }

    public static function issue(string $code, array $path, array $context = []): array
    {
        $pointer = implode('/', array_map(fn ($key) => str_replace(['~', '/'], ['~0', '~1'], (string) $key), $path));

        return ['code' => $code, 'path' => $path === [] ? '' : '/'.$pointer, ...$context];
    }

    private static function replacement(string $value): string
    {
        if ($value === '') {
            return 'x';
        }
        $first = mb_substr($value, 0, 1, 'UTF-8');
        // Preserve both code-point and UTF-8 byte length, including a long blank input.
        $letter = match (strlen($first)) {
            1 => 'x',
            2 => 'α',
            3 => '字',
            4 => '🙂',
        };

        return $letter.substr($value, strlen($first));
    }

    private static function paths(mixed $data, array $pattern, array $path = []): array
    {
        if ($pattern === []) {
            return [$path];
        }
        if (! is_array($data)) {
            return [];
        }
        $key = array_shift($pattern);
        if ($key !== '*') {
            return array_key_exists($key, $data) ? self::paths($data[$key], $pattern, [...$path, $key]) : [];
        }
        if (! array_is_list($data)) {
            return [];
        }
        $paths = [];
        foreach ($data as $index => $item) {
            array_push($paths, ...self::paths($item, $pattern, [...$path, $index]));
        }

        return $paths;
    }

    private static function get(array $data, array $path): mixed
    {
        foreach ($path as $key) {
            $data = $data[$key];
        }

        return $data;
    }

    private static function put(array &$data, array $path, string $value): void
    {
        $cursor = &$data;
        foreach ($path as $key) {
            $cursor = &$cursor[$key];
        }
        $cursor = $value;
    }
}
