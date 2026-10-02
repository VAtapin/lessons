<?php

declare(strict_types=1);

namespace App\Application\Media;

use App\Application\Studio\LessonContentSources;
use App\Models\BlockTemplateVersion;
use App\Models\LessonVersion;
use App\Models\MediaVersion;
use Illuminate\Support\Facades\Schema;

final class MediaUsage
{
    public function forAsset(string $ownerKey, string $assetId): array
    {
        $usages = [];
        $mediaVersionIds = MediaVersion::query()->where('media_asset_id', $assetId)->pluck('id')->all();
        $versions = LessonVersion::query()->whereHas('material', fn ($query) => $query->where('owner_key', $ownerKey))->get();
        foreach ($versions as $version) {
            $seen = [];
            foreach (LessonContentSources::forVersion($version) as $source) {
                $document = $source['document'];
                foreach ($document['stages'] as $stage) {
                    foreach ($stage['blocks'] as $block) {
                        $reference = $block['media']['image'] ?? null;
                        if (is_array($reference) && $reference['assetId'] === $assetId
                            && in_array($reference['versionId'], $mediaVersionIds, true)) {
                            $key = json_encode([$block['id'], $reference['assetId'], $reference['versionId']], JSON_THROW_ON_ERROR);
                            if (isset($seen[$key])) {
                                continue;
                            }
                            $seen[$key] = true;
                            $usages[] = ['kind' => 'lesson', 'lessonId' => $version->lesson_material_id, 'lessonVersionId' => $version->id,
                                'versionId' => $reference['versionId'], 'title' => $document['content'][$document['defaultLocale']]['title'],
                                'status' => $version->status, 'blockId' => $block['id'], 'contentSource' => $source['source']];
                        }
                    }
                }
            }
        }
        if (Schema::hasTable('block_template_versions')) {
            $templates = BlockTemplateVersion::query()->whereHas('template', fn ($query) => $query->where('owner_key', $ownerKey))->with('template')->get();
            foreach ($templates as $version) {
                $block = $version->block;
                $reference = $block['media']['image'] ?? null;
                if (is_array($reference) && $reference['assetId'] === $assetId
                    && in_array($reference['versionId'], $mediaVersionIds, true)) {
                    $usages[] = ['kind' => 'template', 'templateId' => $version->block_template_record_id,
                        'templateVersionId' => $version->id, 'versionId' => $reference['versionId'],
                        'title' => $version->template->title, 'blockId' => $block['id']];
                }
            }
        }

        return $usages;
    }
}
