<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

final readonly class Stage
{
    /** @param list<BlockInstance> $blocks */
    private function __construct(
        public string $id,
        public array $content,
        public array $config,
        public array $blocks,
    ) {}

    public static function fromArray(array $data, BlockRegistry $registry, array $locales): self
    {
        $data = Shape::copy($data);
        Shape::locales($locales);
        Shape::object($data, ['id', 'content', 'blocks'], ['config'], 'stage');
        $id = Shape::id($data['id'], 'stage.id');
        $content = Shape::translations($data['content'], $locales, 'stage.content');
        foreach ($locales as $locale) {
            $translation = Shape::object($content[$locale], ['title'], ['notes'], "stage.content.{$locale}");
            Shape::text($translation['title'], "stage.content.{$locale}.title");
            if (array_key_exists('notes', $translation)) {
                Shape::text($translation['notes'], "stage.content.{$locale}.notes", true);
            }
        }

        $config = array_key_exists('config', $data) ? $data['config'] : [];
        Shape::object($config, [], ['layout', 'durationSeconds', 'openTasks', 'sequentialTasks', 'closeOnTimer', 'answerSeconds', 'theme'], 'stage.config');
        if (array_key_exists('theme', $config) && ! in_array($config['theme'], ['green', 'terracotta'], true)) {
            throw new ValidationException('Unsupported stage theme.');
        }
        if (array_key_exists('answerSeconds', $config)) {
            Shape::integer($config['answerSeconds'], 'stage.config.answerSeconds', 1, 3600);
        }
        foreach (['openTasks', 'sequentialTasks', 'closeOnTimer'] as $flag) {
            if (array_key_exists($flag, $config)) {
                Shape::boolean($config[$flag], 'stage.config.'.$flag);
            }
        }
        $config += ['layout' => 'vertical'];
        if (! in_array($config['layout'], ['vertical', 'two-columns', 'material-above-task'], true)) {
            throw new ValidationException('The stage layout is unsupported.');
        }

        if (array_key_exists('durationSeconds', $config)
            && (! is_int($config['durationSeconds']) || $config['durationSeconds'] < 1)) {
            throw new ValidationException('stage.config.durationSeconds must be a positive integer.');
        }

        $blocks = [];
        foreach (Shape::list($data['blocks'], 'stage.blocks') as $block) {
            if (! is_array($block)) {
                throw new ValidationException('stage.blocks must contain objects.');
            }

            $blocks[] = BlockInstance::fromArray($block, $registry, $locales);
        }

        return new self($id, $content, $config, $blocks);
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'content' => $this->content, 'config' => $this->config,
            'blocks' => array_map(fn (BlockInstance $block) => $block->toArray(), $this->blocks)];
    }

    public function project(Audience $audience, string $locale): array
    {
        if (! array_key_exists($locale, $this->content)) {
            throw new ValidationException('Requested stage translation is unavailable.');
        }

        $content = ['title' => $this->content[$locale]['title']];
        if ($audience === Audience::Teacher && array_key_exists('notes', $this->content[$locale])) {
            $content['notes'] = $this->content[$locale]['notes'];
        }

        return ['id' => $this->id, 'content' => $content, 'config' => $this->config,
            'blocks' => array_map(fn (BlockInstance $block) => $block->project($audience, $locale), $this->blocks)];
    }
}
