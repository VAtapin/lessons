<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlockTemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class TemplateLibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $this->owner]);
        Storage::fake('media');
    }

    public function test_template_copies_the_saved_owned_block_and_all_translations_with_attribution(): void
    {
        [$lesson, $template] = $this->create();
        $this->assertSame(1, $template['revision']);
        $version = $template['versions'][0];
        $this->assertSame(1, $version['versionNo']);
        $this->assertSame(['ru', 'de'], $version['locales']);
        $this->assertSame('ru', $version['defaultLocale']);
        $this->assertSame($lesson['document']['stages'][0]['blocks'][0], $version['block']);
        $this->assertSame('Author', $version['attribution']['author']);
        $this->assertSame([], $template['usages']);
        $response = $this->getJson('/api/studio/templates/'.$template['id'])->assertOk()->json('template');
        $this->assertSame($template, $response);
        $this->assertArrayNotHasKey('owner_key', $response);
        $this->assertDatabaseCount('block_template_records', 1);
        $this->assertDatabaseCount('block_template_versions', 1);
    }

    public function test_two_insertions_are_independent_and_usage_changes_only_after_saving(): void
    {
        [$lesson, $template] = $this->create();
        $first = $this->instantiate($template);
        $second = $this->instantiate($template);
        $this->assertTrue(Str::isUuid($first['id']));
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertNotSame($template['versions'][0]['block']['id'], $first['id']);
        $this->assertSame(['templateId' => $template['id'], 'versionId' => $template['currentVersionId']], $first['origin']);
        $this->getJson('/api/studio/templates/'.$template['id'])->assertOk()->assertJsonPath('template.usages', []);
        $document = $lesson['document'];
        $document['stages'][0]['blocks'] = [$first, $second];
        $document['stages'][0]['blocks'][0]['content']['ru']['question'] = 'Local edit';
        $saved = $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => $lesson['revision'], 'document' => $document])->assertOk()->json('lesson');
        $detail = $this->getJson('/api/studio/templates/'.$template['id'])->assertOk()->json('template');
        $this->assertCount(2, $detail['usages']);
        $this->assertSame([$first['id'], $second['id']], array_column($detail['usages'], 'blockId'));
        $this->assertSame('Question', $detail['versions'][0]['block']['content']['ru']['question']);
        $this->assertSame('Question', $saved['document']['stages'][0]['blocks'][1]['content']['ru']['question']);
    }

    public function test_updates_create_immutable_versions_and_old_insertions_keep_the_old_version(): void
    {
        [$lesson, $template] = $this->create();
        $old = $template['versions'][0];
        $copy = $this->instantiate($template);
        $document = $lesson['document'];
        $document['stages'][0]['blocks'] = [$copy];
        $saved = $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => 1, 'document' => $document])->assertOk()->json('lesson');
        $block = $old['block'];
        $block['content']['ru']['question'] = 'New template';
        $updated = $this->putJson('/api/studio/templates/'.$template['id'], array_replace($this->updatePayload($template, $block), ['author' => 'New author']))->assertOk()->json('template');
        $this->assertSame(2, $updated['revision']);
        $this->assertCount(2, $updated['versions']);
        $this->assertNotSame($template['currentVersionId'], $updated['currentVersionId']);
        $this->assertSame('New template', $updated['versions'][0]['block']['content']['ru']['question']);
        $this->assertSame('New author', $updated['author']);
        $this->assertSame('New author', $updated['versions'][0]['attribution']['author']);
        $this->assertSame($old, $updated['versions'][1]);
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk()->assertJsonPath('lesson.document.stages.0.blocks.0.content.ru.question', 'Question');
        $this->assertSame($copy['origin'], $saved['document']['stages'][0]['blocks'][0]['origin']);
        $this->assertSame('Question', $this->instantiate($updated, ['ru', 'de'], $old['id'])['content']['ru']['question']);
        $this->assertSame('New template', $this->instantiate($updated)['content']['ru']['question']);
    }

    public function test_version_model_refuses_in_place_changes(): void
    {
        [, $template] = $this->create();
        $version = BlockTemplateVersion::findOrFail($template['currentVersionId']);
        $version->default_locale = 'de';
        $this->expectException(LogicException::class);
        $version->save();
    }

    public function test_insertion_selects_exact_declared_locales_without_fallback_or_missing_translations(): void
    {
        [, $template] = $this->create();
        $copy = $this->instantiate($template, ['de']);
        $this->assertSame(['de'], array_keys($copy['content']));
        $this->assertSame('Frage', $copy['content']['de']['question']);
        foreach ([['fr'], ['ru', 'fr'], ['ru', 'ru'], []] as $locales) {
            $this->postJson($this->instantiateUrl($template), ['locales' => $locales])->assertUnprocessable();
        }
        $block = $template['versions'][0]['block'];
        unset($block['content']['de']);
        $this->putJson('/api/studio/templates/'.$template['id'], $this->updatePayload($template, $block))->assertUnprocessable()->assertJsonPath('error.code', 'invalid_document');
        $this->assertDatabaseCount('block_template_versions', 1);
    }

    public function test_source_revision_and_block_identity_are_checked_before_creating_a_snapshot(): void
    {
        $lesson = $this->createLesson();
        $body = $this->metadata() + ['lessonId' => $lesson['id'], 'expectedLessonRevision' => 2, 'blockId' => 'Choice'];
        $this->postJson('/api/studio/templates', $body)->assertConflict()->assertJsonPath('error.code', 'revision_conflict');
        $body['expectedLessonRevision'] = 1;
        $body['blockId'] = 'choice';
        $this->postJson('/api/studio/templates', $body)->assertNotFound();
        $this->assertDatabaseCount('block_template_records', 0);
        $this->assertDatabaseCount('block_template_versions', 0);
    }

    public function test_foreign_resources_are_hidden_in_every_template_endpoint_and_source_creation(): void
    {
        [$lesson, $template] = $this->create();
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->getJson('/api/studio/templates')->assertOk()->assertJsonPath('templates', []);
        $this->getJson('/api/studio/templates/'.$template['id'].'?owner_key='.$this->owner)->assertNotFound();
        $this->postJson('/api/studio/templates', $this->metadata() + ['lessonId' => $lesson['id'], 'expectedLessonRevision' => 1, 'blockId' => 'Choice', 'owner_key' => $this->owner])->assertNotFound();
        $this->putJson('/api/studio/templates/'.$template['id'], $this->updatePayload($template))->assertNotFound();
        $this->postJson($this->instantiateUrl($template), ['locales' => ['ru'], 'owner_key' => $this->owner])->assertNotFound();
        $this->postJson('/api/studio/templates/'.$template['id'].'/archive', ['expectedRevision' => 1, 'archived' => true])->assertNotFound();
        $this->assertDatabaseCount('block_template_versions', 1);
    }

    public function test_version_id_must_belong_to_the_requested_template(): void
    {
        [, $first] = $this->create();
        [, $second] = $this->create();
        $this->postJson($this->instantiateUrl($first, $second['currentVersionId']), ['locales' => ['ru']])->assertNotFound();
    }

    public function test_conflicts_and_invalid_metadata_or_schema_do_not_change_saved_versions(): void
    {
        [, $template] = $this->create();
        $this->putJson('/api/studio/templates/'.$template['id'], array_replace($this->updatePayload($template), ['expectedRevision' => 2]))->assertConflict();
        foreach ([['author' => ''], ['source' => ''], ['usageRights' => ''], ['rightsBasis' => 'unknown'], ['tags' => ['duplicate', 'duplicate']], ['tags' => [str_repeat('a', 51)]], ['title' => str_repeat('a', 201)]] as $invalid) {
            $this->putJson('/api/studio/templates/'.$template['id'], array_replace($this->updatePayload($template), $invalid))->assertUnprocessable();
        }
        $bad = $template['versions'][0]['block'];
        $bad['resources'] = ['image' => 'https://example.test/untrusted'];
        $this->putJson('/api/studio/templates/'.$template['id'], $this->updatePayload($template, $bad))->assertUnprocessable()->assertJsonPath('error.code', 'invalid_document');
        $this->putJson('/api/studio/templates/'.$template['id'], array_replace($this->updatePayload($template), ['expectedRevision' => '1']))->assertUnprocessable();
        $this->getJson('/api/studio/templates/'.$template['id'])->assertOk()->assertJsonPath('template.revision', 1);
        $this->assertDatabaseCount('block_template_versions', 1);
    }

    public function test_archive_is_reversible_and_preserves_saved_released_runtime_instances(): void
    {
        [$lesson, $template] = $this->create();
        $copy = $this->instantiate($template);
        $document = $lesson['document'];
        $document['stages'][0]['blocks'] = [$copy];
        $lesson = $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => 1, 'document' => $document])->assertOk()->json('lesson');
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision']])->assertCreated()->json('session');
        $archived = $this->postJson('/api/studio/templates/'.$template['id'].'/archive', ['expectedRevision' => 1, 'archived' => true])->assertOk()->json('template');
        $this->assertSame(2, $archived['revision']);
        $this->getJson('/api/studio/templates')->assertOk()->assertJsonPath('templates', []);
        $this->getJson('/api/studio/templates?archived=1')->assertOk()->assertJsonPath('templates.0.id', $template['id']);
        $this->postJson($this->instantiateUrl($template), ['locales' => ['ru']])->assertConflict()->assertJsonPath('error.code', 'archived_resource');
        $this->getJson('/api/projection/'.basename($session['projectorUrl']))->assertOk()->assertJsonPath('session.stage.blocks.0.content.question', 'Question');
        $this->postJson('/api/studio/templates/'.$template['id'].'/archive', ['expectedRevision' => 1, 'archived' => false])->assertConflict();
        $restored = $this->postJson('/api/studio/templates/'.$template['id'].'/archive', ['expectedRevision' => 2, 'archived' => false])->assertOk()->json('template');
        $this->assertSame(3, $restored['revision']);
        $this->assertSame($copy['content'], $this->instantiate($restored)['content']);
    }

    public function test_usage_retains_released_snapshots_but_ignores_unknown_or_foreign_origins(): void
    {
        [$lesson, $template] = $this->create();
        $copy = $this->instantiate($template);
        $document = $lesson['document'];
        $document['stages'][0]['blocks'] = [$copy];
        $saved = $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => 1, 'document' => $document])->assertOk()->json('lesson');
        $released = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => $saved['revision']])->assertOk()->json('lesson');
        $document = $released['document'];
        $document['stages'][0]['blocks'][0]['origin']['versionId'] = (string) Str::uuid();
        $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => $released['revision'], 'document' => $document])->assertOk();
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->createLesson($this->documentWithBlocks([$copy]));
        $this->withSession(['studio_owner_key' => $this->owner]);
        $usages = $this->getJson('/api/studio/templates/'.$template['id'])->assertOk()->json('template.usages');
        $this->assertCount(1, $usages);
        $this->assertSame($released['versionId'], $usages[0]['lessonVersionId']);
        $this->assertSame('released', $usages[0]['status']);
    }

    public function test_search_and_exact_tag_type_locale_filters_select_only_the_owned_library(): void
    {
        [, $template] = $this->create(['title' => 'Example 100% block', 'tags' => ['group', 'children']]);
        $this->create(['title' => 'Another', 'tags' => ['different']]);
        $this->getJson('/api/studio/templates?q=100%25&tag=group&type=core.single-choice&locale=de')->assertOk()->assertJsonPath('templates.0.id', $template['id'])->assertJsonCount(1, 'templates');
        $this->getJson('/api/studio/templates?locale=fr')->assertOk()->assertJsonPath('templates', []);
        $this->getJson('/api/studio/templates?type=core.text')->assertOk()->assertJsonPath('templates', []);
    }

    public function test_template_update_rejects_foreign_private_media_and_accepts_own_immutable_versions(): void
    {
        [, $template] = $this->create();
        $asset = $this->upload();
        $image = $this->image($asset);
        $ownUpdate = $this->putJson('/api/studio/templates/'.$template['id'], $this->updatePayload($template, $image))->assertOk()->json('template');
        $this->assertSame($image['media'], $this->instantiate($ownUpdate)['media']);
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        [, $foreignTemplate] = $this->create();
        $this->putJson('/api/studio/templates/'.$foreignTemplate['id'], $this->updatePayload($foreignTemplate, $image))->assertUnprocessable()->assertJsonPath('error.code', 'invalid_media');
        $this->assertDatabaseCount('block_template_versions', 3);
    }

    public function test_template_mutations_use_actual_csrf_middleware(): void
    {
        $lesson = $this->createLesson();
        $environment = $this->app['env'];
        $this->app->instance('env', 'production');
        $this->withMiddleware();
        try {
            $token = Str::random(40);
            $this->withSession(['_token' => $token]);
            $body = $this->metadata() + ['lessonId' => $lesson['id'], 'expectedLessonRevision' => 1, 'blockId' => 'Choice'];
            $this->postJson('/api/studio/templates', $body, ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
            $this->assertDatabaseCount('block_template_records', 0);
            $this->postJson('/api/studio/templates', $body, ['Sec-Fetch-Site' => 'cross-site', 'X-CSRF-TOKEN' => $token])->assertCreated();
        } finally {
            $this->app->instance('env', $environment);
        }
    }

    private function create(array $metadata = []): array
    {
        $lesson = $this->createLesson();
        $template = $this->postJson('/api/studio/templates', array_replace($this->metadata(), $metadata) + [
            'lessonId' => $lesson['id'], 'expectedLessonRevision' => $lesson['revision'], 'blockId' => 'Choice',
        ])->assertCreated()->json('template');

        return [$lesson, $template];
    }

    private function createLesson(?array $document = null): array
    {
        return $this->postJson('/api/studio/lessons', ['document' => $document ?? $this->document()])->assertCreated()->json('lesson');
    }

    private function instantiate(array $template, array $locales = ['ru', 'de'], ?string $versionId = null): array
    {
        return $this->postJson($this->instantiateUrl($template, $versionId), ['locales' => $locales])->assertOk()->json('block');
    }

    private function instantiateUrl(array $template, ?string $versionId = null): string
    {
        return '/api/studio/templates/'.$template['id'].'/versions/'.($versionId ?? $template['currentVersionId']).'/instantiate';
    }

    private function updatePayload(array $template, ?array $block = null): array
    {
        $version = $template['versions'][0];

        return $this->metadata() + ['expectedRevision' => $template['revision'], 'locales' => $version['locales'], 'defaultLocale' => $version['defaultLocale'], 'block' => $block ?? $version['block']];
    }

    private function metadata(): array
    {
        return ['title' => 'Template', 'tags' => ['group'], 'author' => 'Author', 'source' => 'Own work', 'rightsBasis' => 'self_created', 'usageRights' => 'Use in owned lessons'];
    }

    private function upload(): array
    {
        $file = UploadedFile::fake()->createWithContent('photo.png', file_get_contents(base_path('UI-Design/1.png')));

        return $this->post('/api/studio/media', array_replace($this->metadata(), ['tags' => json_encode(['group']), 'file' => $file]), ['Accept' => 'application/json'])->assertCreated()->json('asset');
    }

    private function image(array $asset): array
    {
        return [
            'id' => 'image-copy', 'type' => 'core.image', 'schemaVersion' => 1,
            'content' => ['ru' => ['alt' => 'Own image', 'caption' => ''], 'de' => ['alt' => 'Eigenes Bild', 'caption' => '']],
            'media' => ['image' => ['assetId' => $asset['id'], 'versionId' => $asset['currentVersionId']]],
        ];
    }

    private function documentWithBlocks(array $blocks): array
    {
        $document = $this->document();
        $document['stages'][0]['blocks'] = $blocks;

        return $document;
    }

    private function document(): array
    {
        return [
            'id' => 'fixture', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'],
            'content' => ['ru' => ['title' => 'Library example'], 'de' => ['title' => 'Bibliothek']],
            'stages' => [['id' => 'stage-1', 'content' => ['ru' => ['title' => 'Stage', 'notes' => 'Private note'], 'de' => ['title' => 'Phase']], 'blocks' => [[
                'id' => 'Choice', 'type' => 'core.single-choice', 'schemaVersion' => 1,
                'content' => [
                    'ru' => ['question' => 'Question', 'options' => [['optionId' => 'first', 'text' => 'First'], ['optionId' => 'second', 'text' => 'Second']]],
                    'de' => ['question' => 'Frage', 'options' => [['optionId' => 'second', 'text' => 'Zweite'], ['optionId' => 'first', 'text' => 'Erste']]],
                ], 'solution' => ['optionId' => 'first'],
            ]]]],
        ];
    }
}
