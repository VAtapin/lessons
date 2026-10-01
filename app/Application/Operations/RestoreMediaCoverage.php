<?php

declare(strict_types=1);

namespace App\Application\Operations;

final class RestoreMediaCoverage
{
    /** Every SQL row requires matching bytes; pre-dump inventory supplies additional coverage evidence. */
    public function verify(iterable $rows, array $media, ?array $requiredVersionIds): int
    {
        $entries = array_column($media, null, 'versionId');
        $required = array_fill_keys($requiredVersionIds ?? [], true);
        foreach ($rows as $version) {
            $entry = $entries[$version->id] ?? null;
            if ($entry === null || $entry['assetId'] !== $version->media_asset_id || $entry['file'] !== 'media/'.$version->storage_key
                || $entry['versionNo'] !== (int) $version->version_no || $entry['bytes'] !== (int) $version->bytes
                || ! is_string($version->sha256) || ! hash_equals($entry['sha256'], $version->sha256)) {
                throw new BackupFailure('Restored immutable media metadata does not match the verified bundle.');
            }
            unset($entries[$version->id], $required[$version->id]);
        }
        if ($required !== []) {
            throw new BackupFailure('Restored SQL is missing immutable media versions committed before the verified SQL dump.');
        }

        // Fully verified extras remain private files without restored DB references or quota claims.
        return count($entries);
    }
}
