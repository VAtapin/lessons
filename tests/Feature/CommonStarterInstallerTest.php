<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CommonStarterInstaller;
use App\Application\Catalog\CommonTemplateService;
use App\Application\Shared\ApiProblem;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\ValidationException;
use App\Models\BlockTemplateRecord;
use App\Models\BlockTemplateVersion;
use App\Models\CommonTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class CommonStarterInstallerTest extends TestCase
{
    use RefreshDatabase;

    private function source(): array
    {
        return require resource_path('content/common-starter-v1.php');
    }

    private function install(): array
    {
        return app(CommonStarterInstaller::class)->install();
    }

    private function commonId(string $slug): string
    {
        return CommonStarterInstaller::identity($this->source()['id'], $slug, 'common');
    }

    public function test_trusted_cli_installs_sixteen_real_common_templates_once_without_creating_an_admin_or_parallel_source_materials(): void
    {
        $this->artisan('lessons:install-common')->expectsOutput('Common starters: installed 16, preserved 0, total 16.')->assertSuccessful();
        $before = BlockTemplateVersion::query()->orderBy('id')->get()->map(fn ($version) => $version->getAttributes())->all();
        $this->artisan('lessons:install-common')->expectsOutput('Common starters: installed 0, preserved 16, total 16.')->assertSuccessful();
        $this->assertSame($before, BlockTemplateVersion::query()->orderBy('id')->get()->map(fn ($version) => $version->getAttributes())->all());
        $this->assertDatabaseCount('common_templates', 16);
        $this->assertDatabaseCount('block_template_records', 16);
        $this->assertDatabaseCount('block_template_versions', 16);
        $this->assertDatabaseCount('lesson_materials', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_all_starters_have_full_ru_de_validation_pinned_solutions_and_exact_existing_image_attribution(): void
    {
        $this->install();
        $source = $this->source();
        $images = 0;
        foreach ($source['templates'] as $entry) {
            $common = CommonTemplate::findOrFail($this->commonId($entry['slug']));
            $record = $common->record;
            $version = $record->currentVersion;
            $this->assertSame(CommonTemplateService::OWNER, $record->owner_key);
            $this->assertSame(['ru', 'de'], $version->locales);
            $this->assertSame($entry['labels'], $common->labels);
            $block = BlockInstance::fromArray($version->block, app(BlockRegistry::class), $version->locales);
            $this->assertSame($entry['block']['content'], $block->content);
            $this->assertSame($source['sourceRevision'], $version->attribution['installation']['sourceRevision']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $version->attribution['installation']['sourceHash']);
            $this->assertSame($entry['block']['solution'] ?? null, $block->solution);
            if (in_array($block->type, ['core.poll', 'core.free-response'], true)) {
                $this->assertNull($block->solution);
            }
            if ($block->type === 'core.image') {
                $images++;
                $reference = $block->media['image'];
                $manifest = collect(config('media_builtin'))->firstWhere('assetId', $reference['assetId']);
                $this->assertSame($manifest['versionId'], $reference['versionId']);
                foreach (['author', 'source', 'rightsBasis', 'usageRights'] as $field) {
                    $this->assertSame($manifest['attribution'][$field], $version->attribution[$field]);
                }
                $this->assertSame('permission', $version->attribution['rightsBasis']);
            }
            foreach (['ru', 'de'] as $locale) {
                $this->assertArrayNotHasKey('solution', $block->project(Audience::Projector, $locale));
                $this->assertArrayNotHasKey('teacherNotes', $block->project(Audience::Projector, $locale));
            }
        }
        $this->assertSame(8, $images);
        $this->assertSame(['optionId' => 'adult'], CommonTemplate::findOrFail($this->commonId('safe-help'))->record->currentVersion->block['solution']);
        $this->assertSame(['itemIds' => ['notice', 'ask', 'help']], CommonTemplate::findOrFail($this->commonId('help-steps'))->record->currentVersion->block['solution']);
    }

    public function test_public_listing_and_existing_renderer_projection_preserve_locale_and_never_expose_solutions_or_teacher_notes(): void
    {
        $this->install();
        foreach (['ru', 'de'] as $locale) {
            $templates = $this->getJson('/api/catalog/templates?locale='.$locale)->assertOk()->assertJsonCount(16, 'templates')->json('templates');
            foreach ($templates as $template) {
                $response = $this->getJson('/api/catalog/templates/'.$template['id'].'?locale='.$locale)->assertOk();
                $preview = $response->json('preview');
                foreach (['teacherNotes', 'solution', 'origin', 'owner_key', 'storage_key'] as $private) {
                    $this->assertStringNotContainsString('"'.$private.'"', $response->getContent());
                }
                if ($preview['type'] === 'core.image') {
                    $this->assertSame('/media/builtin/'.$preview['media']['image']['versionId'], $preview['resources']['image']);
                    $this->get($preview['resources']['image'])->assertOk()->assertHeader('Content-Type', 'image/webp');
                }
            }
        }
    }

    public function test_insertions_use_existing_library_and_return_independent_ids_exact_origin_and_selected_locale_maps(): void
    {
        $this->install();
        $id = $this->commonId('safe-help');
        $template = $this->getJson('/api/catalog/templates/'.$id)->assertOk()->json('template');
        $body = ['versionId' => $template['versionId'], 'locales' => ['ru', 'de']];
        $first = $this->postJson('/api/catalog/templates/'.$id.'/instantiate', $body)->assertOk()->json('block');
        $second = $this->postJson('/api/catalog/templates/'.$id.'/instantiate', $body)->assertOk()->json('block');
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame(['templateId' => $template['templateId'], 'versionId' => $template['versionId']], $first['origin']);
        $this->assertSame(['optionId' => 'adult'], $first['solution']);
        $this->assertSame($first['teacherNotes'], $second['teacherNotes']);
        $first['content']['ru']['question'] = 'Моя отдельная формулировка';
        $this->assertNotSame($first['content']['ru']['question'], $second['content']['ru']['question']);
        $de = $this->postJson('/api/catalog/templates/'.$id.'/instantiate', ['versionId' => $template['versionId'], 'locales' => ['de']])->assertOk()->json('block');
        $this->assertSame(['de'], array_keys($de['content']));
        $this->assertSame(['de'], array_keys($de['teacherNotes']));
        $this->assertSame($second['content'], BlockTemplateVersion::findOrFail($template['versionId'])->block['content']);
    }

    public function test_reinstallation_preserves_editor_versions_labels_attribution_hidden_decisions_and_prior_instances(): void
    {
        $this->install();
        $source = $this->source();
        $entry = collect($source['templates'])->firstWhere('slug', 'welcome');
        $id = $this->commonId('welcome');
        $common = CommonTemplate::findOrFail($id);
        $firstVersion = $common->record->current_version_id;
        $initial = $common->record->currentVersion->getAttributes();
        $copy = app(CommonTemplateService::class)->instantiate($id, $firstVersion, ['ru', 'de'], (string) Str::uuid());
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $entry['labels']['ru']['title'] = 'Наше приветствие';
        $entry['block']['content']['ru']['text'] = 'Редакторская новая версия';
        $entry['attribution']['author'] = 'Actual synthetic editor';
        $edited = app(CommonTemplateService::class)->save($admin, $entry, $id, 1);
        app(CommonTemplateService::class)->visibility($admin, $id, $edited['revision'], false);
        $beforeRecord = $common->record->fresh()->getAttributes();
        $beforeCommon = $common->fresh()->getAttributes();
        $this->assertSame(['installed' => 0, 'preserved' => 16, 'total' => 16], $this->install());
        $this->assertSame($beforeRecord, $common->record->fresh()->getAttributes());
        $this->assertSame($beforeCommon, $common->fresh()->getAttributes());
        $this->assertSame($initial, BlockTemplateVersion::findOrFail($firstVersion)->getAttributes());
        $this->assertSame($copy['content'], BlockTemplateVersion::findOrFail($firstVersion)->block['content']);
        $this->assertNotSame($firstVersion, $common->record->fresh()->current_version_id);
        $this->assertDatabaseCount('block_template_versions', 17);
        $this->getJson('/api/catalog/templates/'.$id)->assertNotFound();
        $this->postJson('/api/catalog/templates/'.$id.'/instantiate', ['versionId' => $firstVersion, 'locales' => ['ru']])->assertNotFound();
    }

    public function test_changed_source_or_receipt_refuses_overwrite_atomically(): void
    {
        $this->install();
        $before = BlockTemplateVersion::query()->orderBy('id')->get()->map(fn ($version) => $version->getAttributes())->all();
        $changed = $this->source();
        $changed['templates'][7]['block']['content']['ru']['question'] = 'Другая редакция источника';
        try {
            app(CommonStarterInstaller::class)->install($changed);
            $this->fail('Changed source must have a separately reviewed pack identity.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('No templates were overwritten', $error->getMessage());
        }
        $this->assertSame($before, BlockTemplateVersion::query()->orderBy('id')->get()->map(fn ($version) => $version->getAttributes())->all());
        $changed = $this->source();
        $changed['sourceRevision'] .= '-unexpected';
        try {
            app(CommonStarterInstaller::class)->install($changed);
            $this->fail('Changed source revision must not overwrite the installed pack.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('receipt', $error->getMessage());
        }
        $changed = $this->source();
        $additional = $changed['templates'][0];
        $additional['slug'] = 'new-starter';
        $changed['templates'][] = $additional;
        try {
            app(CommonStarterInstaller::class)->install($changed);
            $this->fail('Pack membership cannot change under an installed identity.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('receipt', $error->getMessage());
        }
        $manifest = config('media_builtin');
        $altered = array_map(function (array $builtin): array {
            if ($builtin['assetId'] === 'builtin-neighbor-road') {
                $builtin['file'] = 'assets/library/neighbor/scene-books.webp';
            }

            return $builtin;
        }, $manifest);
        config(['media_builtin' => $altered]);
        try {
            $this->install();
            $this->fail('Changed bytes behind a pinned image version must block reinstall.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('receipt', $error->getMessage());
        } finally {
            config(['media_builtin' => $manifest]);
        }
        $this->assertDatabaseCount('common_templates', 16);
        $this->assertDatabaseCount('block_template_versions', 16);
    }

    public function test_stable_id_collision_at_end_of_pack_does_not_overwrite_user_resource_or_leave_partial_installation(): void
    {
        $source = $this->source();
        $last = $source['templates'][15];
        $owner = (string) Str::uuid();
        $record = new BlockTemplateRecord(['owner_key' => $owner, 'title' => 'User data', 'tags' => [], 'author' => 'User', 'source' => 'Private', 'rights_basis' => 'self_created', 'usage_rights' => 'Private', 'revision' => 27, 'archived' => true]);
        $record->id = CommonStarterInstaller::identity($source['id'], $last['slug'], 'record');
        $record->save();
        $before = $record->fresh()->getAttributes();
        $this->artisan('lessons:install-common')->assertFailed();
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertDatabaseCount('common_templates', 0);
        $this->assertDatabaseCount('block_template_versions', 0);
        $this->assertDatabaseCount('block_template_records', 1);
    }

    public function test_private_image_and_incomplete_translation_are_rejected_before_any_template_is_installed(): void
    {
        Storage::fake('media');
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $asset = $this->post('/api/studio/media', ['file' => UploadedFile::fake()->image('private.png'), 'title' => 'Private', 'tags' => '[]', 'author' => 'Test', 'source' => 'Fixture', 'rightsBasis' => 'self_created', 'usageRights' => 'Private'], ['Accept' => 'application/json'])->assertCreated()->json('asset');
        $source = $this->source();
        $source['templates'][8]['block']['media']['image'] = ['assetId' => $asset['id'], 'versionId' => $asset['currentVersionId']];
        try {
            app(CommonStarterInstaller::class)->install($source);
            $this->fail('Private media is not an approved reusable builtin.');
        } catch (ApiProblem $problem) {
            $this->assertSame('invalid_media', $problem->problemCode);
        }
        $source = $this->source();
        $source['templates'][0]['block']['content']['de']['text'] = '';
        try {
            app(CommonStarterInstaller::class)->install($source);
            $this->fail('Empty DE content is not ready for the common library.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('text', $error->getMessage());
        }
        $this->assertDatabaseCount('common_templates', 0);
        $this->assertDatabaseCount('block_template_records', 0);
        $this->assertDatabaseCount('block_template_versions', 0);
    }
}
