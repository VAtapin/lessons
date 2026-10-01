<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Studio\StudioService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\EditorFixture;
use Tests\Support\RestoreRuntimeFixture;
use Tests\TestCase;

/** Checks the real runtime fixture/assertions locally; this is not SQL import evidence. */
final class RestoreRuntimeFixtureTest extends TestCase
{
    use EditorFixture, RefreshDatabase, RestoreRuntimeFixture;

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
