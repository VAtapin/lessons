<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Types;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockType;
use App\Domain\Lessons\Shape;
use App\Domain\Lessons\ValidationException;

final class ImageBlock implements BlockType
{
    public function id(): string
    {
        return 'core.image';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function defaults(): array
    {
        return ['fit' => 'contain'];
    }

    public function validate(BlockInstance $block, array $locales): void
    {
        Shape::object($block->config, ['fit'], [], 'image.config');
        if (! in_array($block->config['fit'], ['contain', 'cover'], true) || $block->solution !== null) {
            throw new ValidationException('Image has an invalid fit or solution.');
        }

        Shape::object($block->media, ['image'], [], 'image.media');
        $image = Shape::object($block->media['image'], ['assetId', 'versionId'], [], 'image.media.image');
        Shape::id($image['assetId'], 'image.media.image.assetId');
        Shape::id($image['versionId'], 'image.media.image.versionId');
        foreach ($locales as $locale) {
            $content = Shape::object($block->content[$locale], ['alt'], ['caption'], "image.content.{$locale}");
            Shape::text($content['alt'], "image.content.{$locale}.alt");
            if (array_key_exists('caption', $content)) {
                Shape::text($content['caption'], "image.content.{$locale}.caption", true);
            }
        }
    }
}
