<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

/** Internal validation of the versioned JSON contract. */
final class Shape
{
    /** Detach PHP references and reject values that cannot belong to JSON content. */
    public static function copy(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 64) {
            throw new ValidationException('Content exceeds the supported nesting depth.');
        }

        if (is_array($value)) {
            $copy = [];
            foreach ($value as $key => $item) {
                $copy[self::copy($key, $depth + 1)] = self::copy($item, $depth + 1);
            }

            return $copy;
        }

        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new ValidationException('Content strings and object keys must be valid UTF-8.');
            }

            return $value;
        }

        if ($value === null || is_bool($value) || is_int($value)
            || (is_float($value) && is_finite($value))) {
            return $value;
        }

        throw new ValidationException('Content must contain JSON-compatible values only.');
    }

    public static function object(mixed $value, array $required, array $optional, string $path): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new ValidationException("{$path} must be an object.");
        }

        foreach ($required as $key) {
            if (! array_key_exists($key, $value)) {
                throw new ValidationException("{$path}.{$key} is required.");
            }
        }

        foreach (array_keys($value) as $key) {
            if (! in_array($key, [...$required, ...$optional], true)) {
                throw new ValidationException("{$path} contains an unknown field.");
            }
        }

        return $value;
    }

    public static function list(mixed $value, string $path, int $minimum = 1): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) < $minimum) {
            throw new ValidationException("{$path} must be a list with at least {$minimum} item(s).");
        }

        return $value;
    }

    public static function text(mixed $value, string $path, bool $allowEmpty = false): string
    {
        if (! is_string($value) || (! $allowEmpty && trim($value) === '')) {
            throw new ValidationException("{$path} must be a non-empty string.");
        }

        return $value;
    }

    public static function boundedText(mixed $value, string $path, int $maximum, bool $allowEmpty = false): string
    {
        if (! is_string($value) || preg_match('//u', $value) !== 1
            || mb_strlen($value, 'UTF-8') > $maximum
            || (! $allowEmpty && preg_match('/\A[\p{Z}\s]*\z/u', $value) === 1)) {
            throw new ValidationException("{$path} must be valid text within its length limit.");
        }

        return $value;
    }

    public static function boolean(mixed $value, string $path): bool
    {
        if (! is_bool($value)) {
            throw new ValidationException("{$path} must be boolean.");
        }

        return $value;
    }

    public static function integer(mixed $value, string $path, int $minimum, int $maximum): int
    {
        if (! is_int($value) || $value < $minimum || $value > $maximum) {
            throw new ValidationException("{$path} must be an integer within its bounds.");
        }

        return $value;
    }

    public static function id(mixed $value, string $path): string
    {
        if (! is_string($value) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/', $value) !== 1) {
            throw new ValidationException("{$path} must be a stable identifier (1–128 ASCII characters).");
        }

        return $value;
    }

    public static function version(mixed $value, string $path): int
    {
        if ($value !== 1) {
            throw new ValidationException("{$path} is unsupported; expected integer 1.");
        }

        return $value;
    }

    public static function locales(mixed $value): array
    {
        $locales = self::list($value, 'locales');
        foreach ($locales as $locale) {
            if (! is_string($locale) || preg_match('/\A[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*\z/', $locale) !== 1) {
                throw new ValidationException('locales contains an invalid language tag.');
            }
        }

        if (count(array_unique($locales)) !== count($locales)) {
            throw new ValidationException('locales contains duplicates.');
        }

        return $locales;
    }

    public static function translations(mixed $value, array $locales, string $path): array
    {
        return self::object($value, $locales, [], $path);
    }
}
