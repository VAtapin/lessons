<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

final readonly class BlockInstance
{
    private function __construct(
        public string $id,
        public string $type,
        public int $schemaVersion,
        public array $content,
        public array $config,
        public array $media,
        public ?array $solution,
        public ?array $origin,
        public array $teacherNotes,
    ) {}

    public static function fromArray(array $data, BlockRegistry $registry, array $locales): self
    {
        $data = Shape::copy($data);
        Shape::locales($locales);
        Shape::object($data, ['id', 'type', 'schemaVersion', 'content'], ['config', 'media', 'solution', 'origin', 'teacherNotes'], 'block');
        $id = Shape::id($data['id'], 'block.id');
        $typeId = Shape::id($data['type'], 'block.type');
        if (! is_int($data['schemaVersion'])) {
            throw new ValidationException('block.schemaVersion must be an integer.');
        }

        $type = $registry->resolve($typeId, $data['schemaVersion']);
        $config = array_key_exists('config', $data) ? $data['config'] : [];
        $media = array_key_exists('media', $data) ? $data['media'] : [];
        if (! is_array($config) || ! is_array($media)) {
            throw new ValidationException('block.config and block.media must be objects.');
        }

        $solution = $data['solution'] ?? null;
        if ($solution !== null && ! is_array($solution)) {
            throw new ValidationException('block.solution must be an object or null.');
        }

        $origin = $data['origin'] ?? null;
        if ($origin !== null) {
            Shape::object($origin, ['templateId', 'versionId'], [], 'block.origin');
            Shape::id($origin['templateId'], 'block.origin.templateId');
            Shape::id($origin['versionId'], 'block.origin.versionId');
        }

        $teacherNotes = $data['teacherNotes'] ?? [];
        if (array_key_exists('teacherNotes', $data) && $data['teacherNotes'] === null) {
            throw new ValidationException('block.teacherNotes must be an object.');
        }
        if ($teacherNotes !== []) {
            $teacherNotes = Shape::translations($teacherNotes, $locales, 'block.teacherNotes');
            foreach ($teacherNotes as $locale => $note) {
                Shape::boundedText($note, "block.teacherNotes.{$locale}", 5000, true);
            }
        }

        $block = new self($id, $typeId, $data['schemaVersion'],
            Shape::translations($data['content'], $locales, 'block.content'),
            array_replace(Shape::copy($type->defaults()), $config), $media, $solution, $origin, $teacherNotes);
        $type->validate($block, $locales);

        return $block;
    }

    public function withIdentity(string $id, ?array $origin = null): self
    {
        $origin = Shape::copy($origin);
        Shape::id($id, 'block.id');
        if ($origin !== null) {
            Shape::object($origin, ['templateId', 'versionId'], [], 'block.origin');
            Shape::id($origin['templateId'], 'block.origin.templateId');
            Shape::id($origin['versionId'], 'block.origin.versionId');
        }

        return new self($id, $this->type, $this->schemaVersion, $this->content,
            $this->config, $this->media, $this->solution, $origin, $this->teacherNotes);
    }

    public function toArray(): array
    {
        $data = ['id' => $this->id, 'type' => $this->type, 'schemaVersion' => $this->schemaVersion,
            'content' => $this->content, 'config' => $this->config, 'media' => $this->media,
            'solution' => $this->solution, 'origin' => $this->origin];
        if ($this->teacherNotes !== []) {
            $data['teacherNotes'] = $this->teacherNotes;
        }

        return $data;
    }

    public function project(Audience $audience, string $locale): array
    {
        if (! array_key_exists($locale, $this->content)) {
            throw new ValidationException('Requested block translation is unavailable.');
        }

        $view = ['id' => $this->id, 'type' => $this->type, 'schemaVersion' => $this->schemaVersion,
            'content' => $this->content[$locale], 'config' => $this->config, 'media' => $this->media];
        if ($audience === Audience::Teacher) {
            $view['solution'] = $this->solution;
            if ($this->teacherNotes !== []) {
                $view['teacherNotes'] = $this->teacherNotes[$locale];
            }
        }

        return $view;
    }
}
