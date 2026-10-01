<?php

declare(strict_types=1);

namespace App\Application\Studio;

use Illuminate\Support\Str;
use JsonException;
use stdClass;

/** Fingerprint the original JSON before PHP's associative decode erases object/list distinctions. */
final readonly class EditorSaveRequest
{
    private function __construct(public string $saveId, public int $revision, public array $document, public string $fingerprint) {}

    public static function fromJson(string $json): self
    {
        try {
            $body = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            if (! $body instanceof stdClass) {
                throw new JsonException;
            }
            $keys = array_keys(get_object_vars($body));
            sort($keys);
            if ($keys !== ['document', 'expectedRevision', 'saveId'] || ! is_string($body->saveId) || ! Str::isUuid($body->saveId)
                || ! is_int($body->expectedRevision) || $body->expectedRevision < 1 || ! $body->document instanceof stdClass) {
                throw new JsonException;
            }
            $fingerprint = hash('sha256', json_encode(self::canonical((object) ['expectedRevision' => $body->expectedRevision, 'document' => $body->document]),
                JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return new self(strtolower($body->saveId), $body->expectedRevision, json_decode($json, true, 512, JSON_THROW_ON_ERROR)['document'], $fingerprint);
        } catch (JsonException) {
            throw new EditorProblem('invalid_editor_document', 422, [['code' => 'invalid_envelope', 'path' => '']]);
        }
    }

    private static function canonical(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);
            $object = new stdClass;
            foreach ($properties as $key => $item) {
                $object->{$key} = self::canonical($item);
            }

            return $object;
        }
        if (is_array($value)) {
            return array_map(self::canonical(...), $value);
        }

        return $value;
    }
}
