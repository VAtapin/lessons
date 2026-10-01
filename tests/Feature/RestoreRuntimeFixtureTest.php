<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Studio\StudioService;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EditorFixture;
use Tests\Support\RestoreRuntimeFixture;
use Tests\TestCase;

/** Checks the real runtime fixture/assertions locally; this is not SQL import evidence. */
final class RestoreRuntimeFixtureTest extends TestCase
{
    use EditorFixture, RefreshDatabase, RestoreRuntimeFixture;

    public function test_isolated_connection_keeps_hydrated_model_mutations_out_of_source(): void
    {
        $source = DB::connection();
        $targetName = 'restore_fixture_target';
        config(['database.connections.'.$targetName => array_replace($source->getConfig(), [
            'name' => $targetName, 'driver' => 'sqlite', 'database' => ':memory:', 'url' => null, 'options' => [],
        ])]);
        $target = DB::connection($targetName);
        $this->assertSame($targetName, $target->getName());
        $target->getSchemaBuilder()->create('teaching_sessions', function ($table): void {
            $table->uuid('id')->primary();
        });
        $id = (string) Str::uuid();
        $target->table('teaching_sessions')->insert(['id' => $id]);
        // This UUID is absent in source. An inherited source connection name would
        // leave the target row untouched while Eloquent reports successful deletion.
        $this->assertSame(0, $source->table('teaching_sessions')->where('id', $id)->count());
        $model = TeachingSession::on($targetName)->findOrFail($id);
        $this->assertSame($targetName, $model->getConnectionName());
        $this->assertTrue($model->delete());
        $this->assertSame(0, $target->table('teaching_sessions')->where('id', $id)->count());
        $this->assertSame(0, $source->table('teaching_sessions')->where('id', $id)->count());
    }

    public function test_runtime_restore_acceptance_fixture_uses_actual_access_and_expiry_rules(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 12:00:00 UTC');
        try {
            $owner = (string) Str::uuid();
            $material = app(StudioService::class)->create($owner, $this->restoreRuntimeDocument());
            $fixture = $this->createRestoreRuntimeFixture($owner, $material);
            $this->assertRestoredRuntimeFixture($owner, $fixture);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
