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
    ) {}

    public static function fromArray(array $data, BlockRegistry $registry, array $locales): self
    {
        $data = Shape::copy($data);
        Shape::locales($locales);
        Shape::object($data, ['id', 'type', 'schemaVersion', 'content'], ['config', 'media', 'solution', 'origin'], 'block');
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

        $block = new self($id, $typeId, $data['schemaVersion'],
            Shape::translations($data['content'], $locales, 'block.content'),
            array_replace(Shape::copy($type->defaults()), $config), $media, $solution, $origin);
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
            $this->config, $this->media, $this->solution, $origin);
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'schemaVersion' => $this->schemaVersion,
            'content' => $this->content, 'config' => $this->config, 'media' => $this->media,
            'solution' => $this->solution, 'origin' => $this->origin];
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
        }

        return $view;
    }
}
