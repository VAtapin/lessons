<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Operations\BackupFailure;
use App\Application\Operations\DatabaseBackup;
use App\Application\Operations\DatabaseDumpProcess;
use App\Application\Operations\DatabaseRestoreProcess;
use App\Application\Operations\PrivateBackupFiles;
use App\Application\Operations\RestoreBundle;
use App\Application\Operations\RestoreMediaCoverage;
use App\Application\Operations\RestoreTarget;
use Illuminate\Database\Connection;
use Mockery;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/** Integrity failure tests use synthetic bytes and never count as SQL restore evidence. */
final class RestoreBundleValidationTest extends TestCase
{
    private string $directory;

    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = str_replace('\\', '/', sys_get_temp_dir()).'/lessons-restore-validation-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        mkdir($this->directory.'/bundle', 0700);
        $sql = "-- synthetic integrity fixture, never an integration restore\n";
        file_put_contents($this->directory.'/bundle/database.sql', $sql);
        $this->manifest = ['formatVersion' => 1, 'createdAt' => '2026-10-01T00:00:00+00:00',
            'database' => ['file' => 'database.sql', 'bytes' => strlen($sql), 'sha256' => hash('sha256', $sql)], 'media' => []];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/bundle/*') as $file) {
            if (is_file($file) || is_link($file)) {
                unlink($file);
            }
        }
        rmdir($this->directory.'/bundle');
        if (is_dir($this->directory.'/existing-media')) {
            rmdir($this->directory.'/existing-media');
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_checksum_inventory_and_legacy_database_switch_fail_before_import_or_target_files(): void
    {
        $original = $this->manifest;
        foreach (['hash', 'sql', 'missing', 'unlisted', 'traversal', 'duplicate', 'legacy', 'required-not-list', 'required-unknown', 'required-traversal', 'required-null', 'required-duplicate'] as $case) {
            $this->manifest = $original;
            $sql = "-- synthetic integrity fixture, never an integration restore\n";
            file_put_contents($this->directory.'/bundle/database.sql', $sql);
            if ($case === 'sql') {
                file_put_contents($this->directory.'/bundle/database.sql', $sql.'corrupted');
            } elseif ($case === 'missing') {
                unlink($this->directory.'/bundle/database.sql');
            } elseif ($case === 'unlisted') {
                file_put_contents($this->directory.'/bundle/unlisted.txt', 'unlisted private bytes');
            } elseif ($case === 'traversal') {
                $this->manifest['media'] = [['file' => '../escape', 'bytes' => 1, 'sha256' => hash('sha256', 'x')]];
            } elseif ($case === 'duplicate') {
                $entry = ['file' => 'media/versions/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa/bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb.png',
                    'assetId' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'versionId' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 'versionNo' => 1, 'bytes' => 1, 'sha256' => hash('sha256', 'x')];
                $this->manifest['media'] = [$entry, $entry];
            } elseif ($case === 'legacy') {
                $sql = "CREATE DATABASE `lessons_test`;\nUSE `lessons_test`;\n";
                file_put_contents($this->directory.'/bundle/database.sql', $sql);
                $this->manifest['database']['bytes'] = strlen($sql);
                $this->manifest['database']['sha256'] = hash('sha256', $sql);
            } elseif ($case === 'required-not-list') {
                $this->manifest['requiredMediaVersionIds'] = ['private' => 'not-a-version'];
            } elseif ($case === 'required-unknown') {
                $this->manifest['requiredMediaVersionIds'] = ['bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'];
            } elseif ($case === 'required-traversal') {
                $this->manifest['requiredMediaVersionIds'] = ['../escape'];
            } elseif ($case === 'required-null') {
                $this->manifest['requiredMediaVersionIds'] = null;
            } elseif ($case === 'required-duplicate') {
                $id = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
                $asset = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
                $this->manifest['media'] = [['file' => 'media/versions/'.$asset.'/'.$id.'.png', 'assetId' => $asset, 'versionId' => $id, 'versionNo' => 1, 'bytes' => 1, 'sha256' => hash('sha256', 'x')]];
                $this->manifest['requiredMediaVersionIds'] = [$id, $id];
            }
            $hash = $this->writeManifest();
            if ($case === 'hash') {
                $hash = str_repeat('0', 64);
            }
            try {
                $this->restore($hash);
                $this->fail('Invalid bundle was accepted: '.$case);
            } catch (BackupFailure) {
                $this->addToAssertionCount(1);
            }
            $this->assertDirectoryDoesNotExist($this->directory.'/restored-media');
            $this->assertSame([], glob($this->directory.'/*.partial'));
            if (is_file($this->directory.'/bundle/unlisted.txt')) {
                unlink($this->directory.'/bundle/unlisted.txt');
            }
        }
    }

    public function test_existing_media_and_application_paths_cannot_be_overwritten(): void
    {
        mkdir($this->directory.'/existing-media', 0700);
        foreach ([$this->directory.'/existing-media', base_path('public/restored-media'), $this->directory.'/bundle/media'] as $target) {
            try {
                $this->restore($this->writeManifest(), $target);
                $this->fail('Unsafe media target was accepted.');
            } catch (BackupFailure) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertDirectoryExists($this->directory.'/existing-media');
        $this->assertDirectoryDoesNotExist(base_path('public/restored-media'));
    }

    private function writeManifest(): string
    {
        file_put_contents($this->directory.'/bundle/manifest.json', json_encode($this->manifest, JSON_THROW_ON_ERROR));

        return hash_file('sha256', $this->directory.'/bundle/manifest.json');
    }

    private function restore(string $hash, ?string $target = null): void
    {
        $database = Mockery::mock(Connection::class);
        $database->shouldReceive('getConfig')->andReturn(['driver' => 'mysql', 'database' => 'lessons_restore_test', 'host' => '127.0.0.1']);
        $database->shouldReceive('selectOne')->with('SELECT DATABASE() AS database_name, VERSION() AS server_version')->andReturn((object) ['database_name' => 'lessons_restore_test', 'server_version' => '10.6.23-MariaDB']);
        $database->shouldReceive('select')->with('SHOW GRANTS FOR CURRENT_USER')->andReturn([(object) ['grant' => "GRANT ALL PRIVILEGES ON `lessons\\_restore\\_test`.* TO 'restore'@'%'"]]);
        $database->shouldReceive('selectOne')->withArgs(fn ($query) => str_contains($query, 'information_schema.'))->andReturn((object) ['objects' => 0]);
        $database->shouldNotReceive('statement');
        $process = Mockery::mock(DatabaseRestoreProcess::class);
        $process->shouldNotReceive('run');
        $finder = Mockery::mock(ExecutableFinder::class);
        $finder->shouldReceive('find')->andReturn('/trusted/mariadb');
        $restore = new RestoreBundle(new RestoreTarget, $process, $finder, new DatabaseBackup(new DatabaseDumpProcess, $finder), new PrivateBackupFiles, new RestoreMediaCoverage);
        $restore->restore($database, 'testing', $this->directory.'/bundle', $hash, $target ?? $this->directory.'/restored-media', base_path(), 30);
    }
}
