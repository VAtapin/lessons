<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CommonTemplateService;
use App\Models\BlockTemplateVersion;
use App\Models\LessonMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CommonTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->withSession(['auth_password_hash' => $admin->password]);

        return $admin;
    }

    private function body(): array
    {
        return ['locales' => ['ru', 'de'], 'defaultLocale' => 'ru',
            'labels' => ['ru' => ['title' => 'Обсуждение помощи', 'description' => 'Вопрос для работы в парах.'], 'de' => ['title' => 'Über Hilfe sprechen', 'description' => 'Eine Frage für die Partnerarbeit.']],
            'attribution' => ['title' => 'Discussion', 'tags' => ['discussion'], 'author' => 'Synthetic editor', 'source' => 'Own teaching text', 'rightsBasis' => 'self_created', 'usageRights' => 'Reusable in this platform'],
            'block' => ['id' => 'discussion-source', 'type' => 'core.text', 'schemaVersion' => 2, 'content' => ['ru' => ['title' => '', 'text' => "С кем можно поговорить?\n ", 'source' => ''], 'de' => ['title' => '', 'text' => 'Mit wem können wir sprechen?', 'source' => '']],
                'teacherNotes' => ['ru' => 'Приватное указание учителю.', 'de' => 'Privater Hinweis für die Leitung.']]];
    }

    public function test_admin_creates_actual_shared_template_using_existing_library_versions_and_public_safe_preview(): void
    {
        $this->admin();
        $body = $this->body();
        $template = $this->postJson('/api/admin/templates', $body)->assertCreated()->json('template');
        $this->assertDatabaseCount('block_template_records', 1);
        $this->assertDatabaseCount('block_template_versions', 1);
        $this->assertDatabaseCount('common_templates', 1);
        $this->assertSame(CommonTemplateService::OWNER, LessonMaterial::firstOrFail()->owner_key);
        $this->getJson('/api/catalog/templates?locale=de')->assertOk()->assertJsonPath('templates.0.title', 'Über Hilfe sprechen')->assertJsonPath('templates.0.versionId', $template['versionId']);
        $response = $this->getJson('/api/catalog/templates/'.$template['id'].'?locale=de')->assertOk()->assertJsonPath('preview.content.text', 'Mit wem können wir sprechen?');
        $this->assertStringNotContainsString('teacherNotes', $response->getContent());
        $this->assertStringNotContainsString('owner_key', $response->getContent());
        $this->getJson('/api/admin/templates')->assertOk()->assertJsonPath('templates.0.block.content.ru.title', '');
        $this->assertSame("С кем можно поговорить?\n ", $template['block']['content']['ru']['text']);
    }

    public function test_public_instantiations_and_version_update_preserve_source_origin_rights_and_independent_instances(): void
    {
        $this->admin();
        $body = $this->body();
        $template = $this->postJson('/api/admin/templates', $body)->assertCreated()->json('template');
        $old = BlockTemplateVersion::findOrFail($template['versionId'])->getAttributes();
        $url = '/api/catalog/templates/'.$template['id'].'/instantiate';
        $copyBody = ['versionId' => $template['versionId'], 'locales' => ['ru', 'de']];
        $first = $this->postJson($url, $copyBody)->assertOk()->json('block');
        $second = $this->postJson($url, $copyBody)->assertOk()->json('block');
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame(['templateId' => $template['templateId'], 'versionId' => $template['versionId']], $first['origin']);
        $first['content']['ru']['text'] = 'Independent edit';
        $this->assertNotSame($first['content']['ru']['text'], $second['content']['ru']['text']);
        $body['block']['content']['ru']['text'] = 'Новая версия';
        $updated = $this->putJson('/api/admin/templates/'.$template['id'], $body + ['expectedRevision' => 1])->assertOk()->json('template');
        $this->assertNotSame($template['versionId'], $updated['versionId']);
        $this->assertSame($old, BlockTemplateVersion::findOrFail($template['versionId'])->getAttributes());
        $this->postJson($url, $copyBody)->assertOk()->assertJsonPath('block.content.ru.text', "С кем можно поговорить?\n ");
        $this->postJson($url, ['versionId' => $updated['versionId'], 'locales' => ['de']])->assertOk()->assertJsonCount(1, 'block.content');
        $this->postJson($url, ['versionId' => (string) Str::uuid(), 'locales' => ['ru']])->assertNotFound();
        $this->postJson($url, ['versionId' => $updated['versionId'], 'locales' => ['fr']])->assertUnprocessable();
        $this->postJson('/api/admin/templates/'.$template['id'].'/visibility', ['expectedRevision' => 1, 'visible' => false])->assertConflict();
        $this->postJson('/api/admin/templates/'.$template['id'].'/visibility', ['expectedRevision' => 2, 'visible' => false])->assertOk();
        $this->getJson('/api/catalog/templates')->assertOk()->assertJsonCount(0, 'templates');
        $this->postJson($url, $copyBody)->assertNotFound();
        $this->assertSame($old, BlockTemplateVersion::findOrFail($template['versionId'])->getAttributes());
        $this->assertSame('Synthetic editor', $updated['attribution']['author']);
    }

    public function test_common_templates_cannot_publish_private_files_or_incomplete_translations(): void
    {
        $this->admin();
        Storage::fake('media');
        $asset = $this->post('/api/studio/media', ['file' => UploadedFile::fake()->image('private.png'), 'title' => 'Private', 'tags' => '[]', 'author' => 'Test', 'source' => 'Fixture', 'rightsBasis' => 'self_created', 'usageRights' => 'Private'], ['Accept' => 'application/json'])->assertCreated()->json('asset');
        $body = $this->body();
        $body['block'] = ['id' => 'private-image', 'type' => 'core.image', 'schemaVersion' => 1, 'content' => ['ru' => ['alt' => 'Private'], 'de' => ['alt' => 'Privat']], 'media' => ['image' => ['assetId' => $asset['id'], 'versionId' => $asset['currentVersionId']]]];
        $this->postJson('/api/admin/templates', $body)->assertUnprocessable()->assertJsonPath('error.code', 'invalid_media');
        $body = $this->body();
        $body['block']['content']['de']['text'] = '';
        $this->postJson('/api/admin/templates', $body)->assertUnprocessable();
        $this->assertDatabaseCount('common_templates', 0);
        $this->assertDatabaseCount('block_template_versions', 0);
    }

    public function test_public_instantiation_requires_actual_csrf_and_admin_actions_cannot_be_called_by_author(): void
    {
        $this->admin();
        $template = $this->postJson('/api/admin/templates', $this->body())->assertCreated()->json('template');
        $environment = $this->app['env'];
        $this->app->instance('env', 'production');
        try {
            $token = Str::random(40);
            $this->withSession(['_token' => $token]);
            $body = ['versionId' => $template['versionId'], 'locales' => ['ru']];
            $url = '/api/catalog/templates/'.$template['id'].'/instantiate';
            $this->postJson($url, $body, ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
            $this->postJson($url, $body, ['Sec-Fetch-Site' => 'cross-site', 'X-CSRF-TOKEN' => $token])->assertOk();
        } finally {
            $this->app->instance('env', $environment);
        }
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);
        $this->postJson('/api/admin/templates', $this->body())->assertForbidden();
        $this->getJson('/api/catalog/templates/'.$template['id'])->assertOk();
        $this->assertDatabaseCount('common_templates', 1);
    }
}
