<?php

namespace App\Application\Shared;

use App\Domain\Lessons\LessonDocument;

final class MediaCatalogue
{
    public function all(): array
    {
        return [[
            'assetId' => 'builtin-conversation',
            'versionId' => 'builtin-conversation-v1',
            'url' => '/media/builtin/builtin-conversation-v1',
            'labelKey' => 'media_conversation',
        ]];
    }

    public function resolve(string $assetId, string $versionId): array
    {
        foreach ($this->all() as $media) {
            if ($media['assetId'] === $assetId && $media['versionId'] === $versionId
                && is_file(base_path('UI-Design/1.png'))) {
                return $media;
            }
        }

        throw new ApiProblem('invalid_media', 422);
    }

    public function assertDocument(LessonDocument $document): void
    {
        foreach ($document->stages as $stage) {
            foreach ($stage->blocks as $block) {
                if ($block->type === 'core.image') {
                    $image = $block->media['image'];
                    $this->resolve($image['assetId'], $image['versionId']);
                }
            }
        }
    }
}
