<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Runtime\RuntimeController;
use App\Models\SessionParticipant;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

final class WorkspacePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_studio_and_join_pages_bootstrap_both_interface_languages(): void
    {
        foreach (['ru' => 'Мастерская занятий', 'de' => 'Unterrichtswerkstatt'] as $locale => $workspace) {
            foreach (['studio', 'library', 'media', 'join'] as $page) {
                $this->withoutVite()->get('/'.$locale.'/'.$page)->assertOk()
                    ->assertSee('lang="'.$locale.'"', false)->assertSee('data-page="'.$page.'"', false)
                    ->assertSee('name="csrf-token"', false)->assertSee($workspace)
                    ->assertViewHas('locale', $locale)->assertViewHas('page', $page)
                    ->assertViewHas('context', []);
            }
        }
        $this->assertTrue(Str::isUuid(session('studio_owner_key')));
        $this->assertDatabaseCount('lesson_materials', 0);
    }

    public function test_unknown_locale_does_not_match_any_workspace_page(): void
    {
        $id = (string) Str::uuid();
        foreach (['studio', 'library', 'media', 'join', 'studio/lessons/'.$id, 'teach/'.$id, 'control/'.$id, 'participate/'.$id, 'project/unknown'] as $path) {
            $this->get('/xx/'.$path)->assertNotFound();
        }
    }

    public function test_editor_and_teacher_html_are_available_only_to_the_owner(): void
    {
        [$owner, $lesson, $session] = $this->createClassroom();
        $this->withoutVite()->get('/ru/studio/lessons/'.$lesson['id'])->assertOk()
            ->assertViewHas('page', 'editor')->assertViewHas('context', ['lessonId' => $lesson['id']])
            ->assertDontSee('Private material title');
        $this->get('/de/teach/'.$session['id'])->assertOk()
            ->assertViewHas('page', 'teacher')->assertViewHas('context', ['sessionId' => $session['id']])
            ->assertDontSee('Private teacher notes');

        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->get('/ru/studio/lessons/'.$lesson['id'].'?owner_key='.$owner)->assertNotFound()
            ->assertDontSee('data-page="editor"', false);
        $this->get('/de/teach/'.$session['id'].'?owner_key='.$owner)->assertNotFound()
            ->assertDontSee('data-page="teacher"', false);
        $this->get('/ru/studio/lessons/'.Str::uuid())->assertNotFound();
        $this->get('/ru/teach/'.Str::uuid())->assertNotFound();
    }

    public function test_detached_controller_uses_the_same_owner_access_without_a_public_control_credential(): void
    {
        [$owner, , $session] = $this->createClassroom();
        foreach (['ru', 'de'] as $locale) {
            $this->withoutVite()->get('/'.$locale.'/control/'.$session['id'].'?instance='.Str::uuid())
                ->assertOk()->assertViewHas('page', 'control')
                ->assertViewHas('context', ['sessionId' => $session['id']])
                ->assertDontSee($owner)->assertDontSee('Private teacher notes');
        }
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->get('/ru/control/'.$session['id'].'?owner_key='.$owner.'&code='.$session['joinCode'])
            ->assertNotFound()->assertDontSee('data-page="control"', false);
        $this->get('/de/control/'.Str::uuid())->assertNotFound();
    }

    public function test_student_html_checks_real_membership_in_the_requested_session(): void
    {
        [, $lesson, $first] = $this->createClassroom();
        $second = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => 2, 'locale' => 'ru'])
            ->assertCreated()->json('session');
        $this->withoutVite()->get('/ru/participate/'.$first['id'])->assertNotFound();
        $this->withSession([RuntimeController::SESSION_PARTICIPANTS_KEY => [$first['id'] => (string) Str::uuid()]])
            ->get('/ru/participate/'.$first['id'])->assertNotFound();

        $participant = $this->postJson('/api/join', ['code' => $first['joinCode'], 'name' => 'Student'])
            ->assertOk()->json('participant');
        $this->get('/ru/participate/'.$first['id'])->assertOk()
            ->assertViewHas('page', 'student')->assertViewHas('context', ['sessionId' => $first['id']])
            ->assertDontSee('Private teacher notes');
        // A real participant from another room still does not authorize this room.
        $this->withSession([RuntimeController::SESSION_PARTICIPANTS_KEY => [$second['id'] => $participant['id']]])
            ->get('/ru/participate/'.$second['id'])->assertNotFound();
        $this->withSession([RuntimeController::SESSION_PARTICIPANTS_KEY => [$first['id'] => $participant['id']]]);
        SessionParticipant::findOrFail($participant['id'])->delete();
        $this->get('/ru/participate/'.$first['id'])->assertNotFound();
    }

    public function test_projector_page_uses_a_real_read_only_token_and_rejects_unknown_tokens(): void
    {
        [$owner, , $session] = $this->createClassroom();
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->withoutVite()->get('/de/project/'.$token)->assertOk()
            ->assertViewHas('page', 'projector')->assertViewHas('context', ['projectorToken' => $token])
            ->assertDontSee($owner)->assertDontSee('Private teacher notes')->assertDontSee($session['joinCode']);
        $this->get('/de/project/'.str_repeat('0', 64))->assertNotFound();
        $this->get('/de/project/'.$session['id'])->assertNotFound();
    }

    public function test_builtin_media_catalogue_and_its_versioned_file_are_public_and_real(): void
    {
        $this->getJson('/api/studio/media')->assertOk()->assertJsonFragment([
            'assetId' => 'builtin-conversation', 'versionId' => 'builtin-conversation-v1',
            'url' => '/media/builtin/builtin-conversation-v1', 'labelKey' => 'media_conversation',
        ])->assertJsonFragment([
            'assetId' => 'builtin-mutual-help', 'versionId' => 'builtin-mutual-help-v1',
            'url' => '/media/builtin/builtin-mutual-help-v1', 'labelKey' => 'media_mutual_help',
        ])->assertJsonPath('quota.limitBytes', 100 * 1024 * 1024)
            ->assertJsonPath('quota.maxFileBytes', 20 * 1024 * 1024)
            ->assertDontSee('storage_key')->assertDontSee('assets/library/');
        $response = $this->get('/media/builtin/builtin-conversation-v1')->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame(realpath(base_path('UI-Design/1.png')), $response->baseResponse->getFile()->getRealPath());
        $this->assertGreaterThan(0, $response->baseResponse->getFile()->getSize());
        $new = $this->get('/media/builtin/builtin-mutual-help-v1')->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(realpath(base_path('assets/library/mutual-help-v1.png')), $new->baseResponse->getFile()->getRealPath());
        $this->get('/media/builtin/unknown-version')->assertNotFound();
        $this->get('/media/builtin/.env')->assertNotFound();
    }

    public function test_mutation_requires_a_matching_csrf_token_with_real_web_middleware_enabled(): void
    {
        // Laravel intentionally bypasses CSRF while app['env'] is testing.
        // Changing that binding exercises the actual middleware without replacing it.
        $environment = $this->app['env'];
        $this->app->instance('env', 'production');
        $this->withMiddleware();
        try {
            $token = Str::random(40);
            $this->withSession(['_token' => $token]);
            $this->postJson('/api/studio/lessons', ['document' => $this->document()], ['Sec-Fetch-Site' => 'cross-site'])
                ->assertStatus(419);
            $this->postJson('/api/studio/lessons', ['document' => $this->document()], [
                'Sec-Fetch-Site' => 'cross-site', 'X-CSRF-TOKEN' => 'incorrect-token',
            ])->assertStatus(419);
            $this->assertDatabaseCount('lesson_materials', 0);
            $this->assertDatabaseCount('lesson_versions', 0);
            $this->postJson('/api/studio/lessons', ['document' => $this->document()], ['X-CSRF-TOKEN' => $token])
                ->assertCreated();
        } finally {
            $this->app->instance('env', $environment);
        }
    }

    private function createClassroom(): array
    {
        $owner = (string) Str::uuid();
        $lesson = $this->withSession(['studio_owner_key' => $owner])
            ->postJson('/api/studio/lessons', ['document' => $this->document()])->assertCreated()->json('lesson');
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => 1, 'locale' => 'ru'])
            ->assertCreated()->json('session');

        return [$owner, $lesson, $session];
    }

    private function document(): array
    {
        return ['id' => 'page-test-document', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'],
            'content' => ['ru' => ['title' => 'Private material title']],
            'stages' => [['id' => 'stage-1', 'content' => ['ru' => ['title' => 'Stage', 'notes' => 'Private teacher notes']],
                'blocks' => [['id' => 'text-1', 'type' => 'core.text', 'schemaVersion' => 1, 'content' => ['ru' => ['text' => 'Text']]]]]]];
    }
}
