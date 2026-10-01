<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Operations\BackupFailure;
use App\Application\Operations\RestoreMediaCoverage;
use Tests\TestCase;

final class RestoreMediaCoverageTest extends TestCase
{
    public function test_pre_dump_versions_are_required_but_after_snapshot_files_are_safe_unreferenced_extras(): void
    {
        $before = $this->entry('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 1);
        $after = $this->entry('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 2);
        $coverage = new RestoreMediaCoverage;
        $this->assertSame(1, $coverage->verify([$this->row($before)], [$before, $after], [$before['versionId']]));
        $this->assertSame(0, $coverage->verify([$this->row($before), $this->row($after)], [$before, $after], [$before['versionId']]));
        $this->expectException(BackupFailure::class);
        $this->expectExceptionMessage('committed before');
        $coverage->verify([], [$before, $after], [$before['versionId']]);
    }

    public function test_legacy_manifest_without_pre_dump_evidence_preserves_verified_extra_file_compatibility(): void
    {
        $entry = $this->entry('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 1);
        $this->assertSame(1, (new RestoreMediaCoverage)->verify([], [$entry], null));
        $this->assertSame(0, (new RestoreMediaCoverage)->verify([$this->row($entry)], [$entry], null));
    }

    public function test_every_restored_sql_row_must_have_matching_manifest_metadata(): void
    {
        $entry = $this->entry('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 1);
        foreach (['absent', 'asset', 'path', 'number', 'bytes', 'hash'] as $case) {
            $row = $this->row($entry);
            $media = [$entry];
            if ($case === 'absent') {
                $media = [];
            } elseif ($case === 'asset') {
                $row->media_asset_id = 'different-asset';
            } elseif ($case === 'path') {
                $row->storage_key = '../different-file';
            } elseif ($case === 'number') {
                $row->version_no = 2;
            } elseif ($case === 'bytes') {
                $row->bytes++;
            } elseif ($case === 'hash') {
                $row->sha256 = str_repeat('0', 64);
            }
            try {
                (new RestoreMediaCoverage)->verify([$row], $media, null);
                $this->fail('A restored SQL mismatch was accepted: '.$case);
            } catch (BackupFailure) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function entry(string $id, int $number): array
    {
        $asset = 'cccccccc-cccc-cccc-cccc-cccccccccccc';

        return ['assetId' => $asset, 'versionId' => $id, 'versionNo' => $number, 'file' => 'media/versions/'.$asset.'/'.$id.'.png', 'bytes' => 1, 'sha256' => hash('sha256', 'x')];
    }

    private function row(array $entry): object
    {
        return (object) ['id' => $entry['versionId'], 'media_asset_id' => $entry['assetId'], 'version_no' => $entry['versionNo'],
            'storage_key' => substr($entry['file'], strlen('media/')), 'bytes' => $entry['bytes'], 'sha256' => $entry['sha256']];
    }
}
