<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\EditorFixture;
use Tests\TestCase;

final class EditorPreviewTest extends TestCase
{
    use EditorFixture, RefreshDatabase;

    public function test_owner_preview_preserves_blank_and_literal_text_and_audience_privacy_without_persisting(): void
    {
        $this->editorIdentity();
        $lesson = $this->editorLesson();
        $working = $this->blankLocale($lesson['document'], 'de');
        $working['stages'][0]['blocks'][0]['content']['de']['text'] = " \n<b>literal</b> \n";
        $working['stages'][0]['config']['layout'] = 'two-columns';
        $version = LessonVersion::findOrFail($lesson['versionId'])->getAttributes();
        foreach (['teacher', 'student', 'projector'] as $audience) {
            $body = ['expectedRevision' => $lesson['revision'], 'document' => $working, 'audience' => $audience, 'locale' => 'de', 'stageId' => 'stage-a'];
            $response = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/preview', $body)->assertOk();
            $preview = $response->json('preview');
            $this->assertSame('', $preview['stage']['content']['title']);
            $this->assertSame(" \n<b>literal</b> \n", $preview['stage']['blocks'][0]['content']['text']);
            $this->assertSame('two-columns', $preview['stage']['config']['layout']);
            $this->assertSame(['ru'], $preview['readiness']['readyLocales']);
            $this->assertStringNotContainsString('Future DE', $response->getContent());
            $this->assertArrayNotHasKey('runtime', $preview['stage']['blocks'][1]);
            if ($audience === 'teacher') {
                $this->assertSame('Private DE note', $preview['stage']['content']['notes']);
                $this->assertSame(['optionId' => 'a'], $preview['stage']['blocks'][1]['solution']);
            } else {
                foreach (['solution', 'teacherNotes', 'origin'] as $key) {
                    $this->assertArrayNotHasKey($key, $preview['stage']['blocks'][1]);
                }
                $this->assertStringNotContainsString('Private DE', $response->getContent());
            }
        }
        $this->assertSame($version, LessonVersion::findOrFail($lesson['versionId'])->getAttributes());
        $this->assertSame(1, LessonMaterial::findOrFail($lesson['id'])->revision);
        $this->assertDatabaseCount('teaching_sessions', 0);
        $this->assertDatabaseCount('session_participants', 0);
        $this->assertDatabaseCount('lesson_save_receipts', 0);
        $body['stageId'] = 'missing';
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/preview', $body)->assertNotFound();
        $body['stageId'] = 'stage-a';
        $body['expectedRevision'] = 99;
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/preview', $body)->assertConflict();
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/preview', $body)->assertNotFound();
    }
}
