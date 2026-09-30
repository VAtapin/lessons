<?php

declare(strict_types=1);

namespace App\Application\Media;

use App\Application\Shared\ApiProblem;
use App\Application\Shared\LibraryMetadata;
use App\Application\Shared\MediaCatalogue;
use App\Models\MediaAsset;
use App\Models\MediaOwnerQuota;
use App\Models\MediaVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class MediaLibraryService
{
    public function __construct(private LibraryMetadata $metadata, private ImageUpload $images, private MediaCatalogue $catalogue, private MediaUsage $usage) {}

    public function list(string $ownerKey, array $filters = []): array
    {
        $archived = ($filters['archived'] ?? '0') === '1';
        $assets = MediaAsset::query()->where('owner_key', $ownerKey)->where('archived', $archived)->orderBy('title')->orderBy('id')->get()
            ->filter(function (MediaAsset $asset) use ($filters): bool {
                $query = mb_strtolower(trim($filters['q'] ?? ''));

                return ($query === '' || str_contains(mb_strtolower($asset->title), $query))
                    && (($filters['tag'] ?? '') === '' || in_array($filters['tag'], $asset->tags, true));
            })->values();
        $assetIds = $assets->pluck('id')->all();
        $media = array_values(array_filter($this->catalogue->all($ownerKey), fn (array $version) => isset($version['labelKey'])
            ? ! $archived : in_array($version['assetId'], $assetIds, true)));

        return ['media' => $media, 'assets' => $assets->map(fn (MediaAsset $asset) => $this->assetSummary($asset))->all(),
            'quota' => $this->quota($ownerKey)];
    }

    public function findOwned(string $ownerKey, string $id): MediaAsset
    {
        return MediaAsset::query()->where('owner_key', $ownerKey)->find($id) ?? throw new ApiProblem('not_found', 404);
    }

    public function detail(string $ownerKey, string $id): array
    {
        return $this->present($this->findOwned($ownerKey, $id));
    }

    public function present(MediaAsset $asset): array
    {
        $versions = $asset->versions()->orderByDesc('version_no')->get();
        foreach ($versions as $version) {
            $version->setRelation('asset', $asset);
        }

        return $this->assetSummary($asset) + ['author' => $asset->author, 'source' => $asset->source,
            'rightsBasis' => $asset->rights_basis, 'usageRights' => $asset->usage_rights,
            'versions' => $versions->map(fn (MediaVersion $version) => $this->catalogue->summary($version)
                + ['sha256' => $version->sha256, 'attribution' => $version->attribution, 'createdAt' => $version->created_at->toIso8601String()])->all(),
            'usages' => $this->usage->forAsset($asset->owner_key, $asset->id)];
    }

    public function upload(string $ownerKey, UploadedFile $file, array $metadata): MediaAsset
    {
        $metadata = $this->metadata->parse($metadata);
        $image = $this->images->inspect($file);
        $createdKey = null;
        try {
            return DB::transaction(function () use ($ownerKey, $file, $metadata, $image, &$createdKey): MediaAsset {
                $quota = $this->reserve($ownerKey, $image['bytes']);
                $asset = MediaAsset::query()->create($this->databaseMetadata($metadata) + ['owner_key' => $ownerKey, 'revision' => 1, 'archived' => false]);
                $version = $this->storeVersion($asset, $file, $image, 1, $createdKey);
                $asset->current_version_id = $version->id;
                $asset->save();
                $quota->save();

                return $asset;
            });
        } catch (Throwable $failure) {
            $this->cleanup($createdKey);
            throw $failure instanceof ApiProblem ? $failure : new ApiProblem('media_storage_failed', 503);
        }
    }

    public function replace(string $ownerKey, string $id, int $expectedRevision, UploadedFile $file): MediaAsset
    {
        $this->findOwned($ownerKey, $id);
        $image = $this->images->inspect($file);
        $createdKey = null;
        try {
            return DB::transaction(function () use ($ownerKey, $id, $expectedRevision, $file, $image, &$createdKey): MediaAsset {
                $asset = $this->lockOwned($ownerKey, $id, $expectedRevision);
                $quota = $this->reserve($ownerKey, $image['bytes']);
                $versionNo = $asset->versions()->max('version_no') + 1;
                $version = $this->storeVersion($asset, $file, $image, $versionNo, $createdKey);
                $asset->current_version_id = $version->id;
                $asset->revision++;
                $asset->save();
                $quota->save();

                return $asset;
            });
        } catch (Throwable $failure) {
            $this->cleanup($createdKey);
            throw $failure instanceof ApiProblem ? $failure : new ApiProblem('media_storage_failed', 503);
        }
    }

    public function update(string $ownerKey, string $id, int $expectedRevision, array $metadata): MediaAsset
    {
        return DB::transaction(function () use ($ownerKey, $id, $expectedRevision, $metadata): MediaAsset {
            $asset = $this->lockOwned($ownerKey, $id, $expectedRevision);
            $asset->fill($this->databaseMetadata($this->metadata->parse($metadata)));
            $asset->revision++;
            $asset->save();

            return $asset;
        });
    }

    public function archive(string $ownerKey, string $id, int $expectedRevision, bool $archived): MediaAsset
    {
        return DB::transaction(function () use ($ownerKey, $id, $expectedRevision, $archived): MediaAsset {
            $asset = $this->lockOwned($ownerKey, $id, $expectedRevision);
            if ($asset->archived !== $archived) {
                $asset->archived = $archived;
                $asset->revision++;
                $asset->save();
            }

            return $asset;
        });
    }

    private function lockOwned(string $ownerKey, string $id, int $expectedRevision): MediaAsset
    {
        $asset = MediaAsset::query()->where('owner_key', $ownerKey)->lockForUpdate()->find($id) ?? throw new ApiProblem('not_found', 404);
        if ($asset->revision !== $expectedRevision) {
            throw new ApiProblem('revision_conflict', 409);
        }

        return $asset;
    }

    private function reserve(string $ownerKey, int $bytes): MediaOwnerQuota
    {
        MediaOwnerQuota::query()->insertOrIgnore(['owner_key' => $ownerKey, 'used_bytes' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $quota = MediaOwnerQuota::query()->lockForUpdate()->findOrFail($ownerKey);
        if ($quota->used_bytes + $bytes > config('lessons.media.guest_quota_bytes')) {
            throw new ApiProblem('quota_exceeded', 422);
        }
        $quota->used_bytes += $bytes;

        return $quota;
    }

    private function storeVersion(MediaAsset $asset, UploadedFile $file, array $image, int $number, ?string &$createdKey): MediaVersion
    {
        $version = new MediaVersion;
        $version->id = (string) Str::uuid();
        $createdKey = 'versions/'.$asset->id.'/'.$version->id.'.'.$image['extension'];
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new ApiProblem('media_storage_failed', 503);
        }
        try {
            if (! Storage::disk(config('lessons.media.disk'))->writeStream($createdKey, $stream, ['visibility' => 'private'])) {
                throw new ApiProblem('media_storage_failed', 503);
            }
        } finally {
            fclose($stream);
        }
        $version->fill(['media_asset_id' => $asset->id, 'version_no' => $number, 'storage_key' => $createdKey,
            'mime' => $image['mime'], 'bytes' => $image['bytes'], 'width' => $image['width'], 'height' => $image['height'], 'sha256' => $image['sha256'],
            'attribution' => ['author' => $asset->author, 'source' => $asset->source, 'rightsBasis' => $asset->rights_basis, 'usageRights' => $asset->usage_rights]]);
        $version->save();

        return $version;
    }

    private function cleanup(?string $key): void
    {
        if ($key !== null) {
            try {
                if (! Storage::disk(config('lessons.media.disk'))->delete($key)) {
                    throw new ApiProblem('media_storage_failed', 503);
                }
            } catch (Throwable) {
                throw new ApiProblem('media_storage_failed', 503);
            }
        }
    }

    private function quota(string $ownerKey): array
    {
        return ['usedBytes' => MediaOwnerQuota::query()->find($ownerKey)?->used_bytes ?? 0,
            'limitBytes' => config('lessons.media.guest_quota_bytes'), 'maxFileBytes' => config('lessons.media.max_file_bytes')];
    }

    private function databaseMetadata(array $metadata): array
    {
        return ['title' => $metadata['title'], 'tags' => $metadata['tags'], 'author' => $metadata['author'], 'source' => $metadata['source'],
            'rights_basis' => $metadata['rightsBasis'], 'usage_rights' => $metadata['usageRights']];
    }

    private function assetSummary(MediaAsset $asset): array
    {
        return ['id' => $asset->id, 'title' => $asset->title, 'tags' => $asset->tags, 'revision' => $asset->revision,
            'currentVersionId' => $asset->current_version_id, 'archived' => $asset->archived];
    }
}
