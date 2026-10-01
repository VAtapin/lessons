<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

/** Methodical material is part of an immutable lesson snapshot, never a student projection. */
final readonly class TeacherDocumentation
{
    private function __construct(private array $data) {}

    public static function fromArray(array $data, array $locales): self
    {
        $data = Shape::copy($data);
        Shape::object($data, ['schemaVersion', 'content', 'files'], ['video'], 'documentation');
        Shape::version($data['schemaVersion'], 'documentation.schemaVersion');
        Shape::object($data['content'], [], $locales, 'documentation.content');
        foreach ($data['content'] as $locale => $translation) {
            Shape::object($translation, ['plan'], [], "documentation.content.{$locale}");
            Shape::boundedText($translation['plan'], "documentation.content.{$locale}.plan", 30000);
        }
        $files = Shape::list($data['files'], 'documentation.files', 0);
        if (count($files) > 20) {
            throw new ValidationException('Documentation accepts at most 20 files.');
        }
        $ids = [];
        foreach ($files as $file) {
            Shape::object($file, ['fileId', 'kind', 'locale'], [], 'documentation.file');
            $id = Shape::id($file['fileId'], 'documentation.file.fileId');
            Shape::locales([$file['locale']]);
            if (! in_array($file['kind'], ['plan', 'presentation'], true) || in_array($id, $ids, true)) {
                throw new ValidationException('Documentation files need unique IDs and a supported kind.');
            }
            $ids[] = $id;
        }
        if (isset($data['video'])) {
            Shape::object($data['video'], ['id', 'locale'], [], 'documentation.video');
            Shape::locales([$data['video']['locale']]);
            if (! is_string($data['video']['id']) || preg_match('/\A[A-Za-z0-9_-]{11}\z/', $data['video']['id']) !== 1) {
                throw new ValidationException('Documentation video must be a YouTube video ID.');
            }
        }

        return new self($data);
    }

    public function toArray(): array
    {
        return Shape::copy($this->data);
    }

    public function forLocales(array $locales): array
    {
        $data = $this->toArray();
        $data['content'] = array_intersect_key($data['content'], array_flip($locales));

        return $data;
    }

    public function project(string $locale): array
    {
        return ['plan' => $this->data['content'][$locale]['plan'] ?? null,
            'files' => $this->data['files'], 'video' => $this->data['video'] ?? null];
    }
}
