<?php

namespace App\Application\Shared;

use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\LessonDocument;
use App\Models\MediaVersion;
use Illuminate\Support\Facades\Storage;

final class MediaCatalogue
{
    public function all(?string $ownerKey = null): array
    {
        $media = [];
        foreach (config('media_builtin', []) as $entry) {
            if (is_file(base_path($entry['file']))) {
                $media[] = ['assetId' => $entry['assetId'], 'versionId' => $entry['versionId'],
                    'url' => '/media/builtin/'.$entry['versionId'], 'labelKey' => $entry['labelKey']];
            }
        }
        if ($ownerKey !== null) {
            $versions = MediaVersion::query()->whereHas('asset', fn ($query) => $query->where('owner_key', $ownerKey))
                ->with('asset')->orderBy('media_asset_id')->orderBy('version_no')->get();
            foreach ($versions as $version) {
                $media[] = $this->summary($version);
            }
        }

        return $media;
    }

    public function resolve(string $assetId, string $versionId, ?string $ownerKey = null): array
    {
        foreach (config('media_builtin', []) as $entry) {
            if ($entry['assetId'] === $assetId && $entry['versionId'] === $versionId && is_file(base_path($entry['file']))) {
                return ['assetId' => $assetId, 'versionId' => $versionId, 'url' => '/media/builtin/'.$versionId,
                    'labelKey' => $entry['labelKey'], 'path' => base_path($entry['file']), 'mime' => $entry['mime']];
            }
        }
        if ($ownerKey !== null) {
            $version = MediaVersion::query()->whereKey($versionId)->where('media_asset_id', $assetId)
                ->whereHas('asset', fn ($query) => $query->where('owner_key', $ownerKey))->with('asset')->first();
            if ($version !== null) {
                $path = Storage::disk(config('lessons.media.disk'))->path($version->storage_key);
                if (is_file($path)) {
                    return $this->summary($version) + ['path' => $path];
                }
            }
        }

        throw new ApiProblem('invalid_media', 422);
    }

    public function assertDocument(LessonDocument $document, ?string $ownerKey = null): void
    {
        foreach ($document->stages as $stage) {
            foreach ($stage->blocks as $block) {
                $this->assertBlock($block, $ownerKey);
            }
        }
    }

    public function assertBlock(BlockInstance $block, ?string $ownerKey = null): void
    {
        if ($block->type === 'core.image') {
            $image = $block->media['image'];
            $this->resolve($image['assetId'], $image['versionId'], $ownerKey);
        }
    }

    public function summary(MediaVersion $version): array
    {
        $asset = $version->asset;

        return ['assetId' => $asset->id, 'versionId' => $version->id, 'versionNo' => $version->version_no,
            'title' => $asset->title, 'url' => '/media/owned/'.$asset->id.'/'.$version->id,
            'mime' => $version->mime, 'bytes' => $version->bytes, 'width' => $version->width, 'height' => $version->height,
            'archived' => $asset->archived];
    }
}
