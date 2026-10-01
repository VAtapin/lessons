<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\TestCase;

final class LessonJsonMapsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('numericRoles')]
    public function test_capacity_maps_preserve_numeric_role_keys_through_studio_library_and_runtime(array $ids): void
    {
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $capacities = array_fill_keys($ids, 1);
        $block = ['id' => 'roles', 'type' => 'core.roles', 'schemaVersion' => 1,
            'content' => ['ru' => ['text' => 'Choose a role', 'roles' => array_map(fn ($id) => ['roleId' => $id, 'text' => 'Role '.$id], $ids)]],
            'config' => ['capacities' => $capacities], 'teacherNotes' => ['ru' => 'Private roles note']];
        $document = ['id' => 'fixture', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'],
            'content' => ['ru' => ['title' => 'Numeric role fixture']],
            'stages' => [['id' => 'first', 'content' => ['ru' => ['title' => 'First']], 'blocks' => [$block]]]];
        $response = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated();
        $wire = json_decode($response->getContent());
        $this->assertMap($wire->lesson->document->stages[0]->blocks[0]->config->capacities, $ids);
        $lesson = $response->json('lesson');
        $this->assertSame($capacities, $lesson['document']['stages'][0]['blocks'][0]['config']['capacities']);
        $wire = json_decode($this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk()->getContent());
        $this->assertMap($wire->lesson->document->stages[0]->blocks[0]->config->capacities, $ids);
        $templateResponse = $this->postJson('/api/studio/templates', [
            'lessonId' => $lesson['id'], 'expectedLessonRevision' => $lesson['revision'], 'blockId' => 'roles',
            'title' => 'Roles template', 'tags' => [], 'author' => 'Test', 'source' => 'Own test',
            'rightsBasis' => 'self_created', 'usageRights' => 'Test use',
        ])->assertCreated();
        $wire = json_decode($templateResponse->getContent());
        $this->assertMap($wire->template->versions[0]->block->config->capacities, $ids);
        $template = $templateResponse->json('template');
        $wire = json_decode($this->postJson('/api/studio/templates/'.$template['id'].'/versions/'.$template['currentVersionId'].'/instantiate', ['locales' => ['ru']])
            ->assertOk()->getContent());
        $this->assertMap($wire->block->config->capacities, $ids);
        $sessionResponse = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision']])->assertCreated();
        $wire = json_decode($sessionResponse->getContent());
        $this->assertMap($wire->session->document->stages[0]->blocks[0]->config->capacities, $ids);
        $this->assertMap($wire->session->publicStage->blocks[0]->config->capacities, $ids);
        $this->assertObjectNotHasProperty('teacherNotes', $wire->session->publicStage->blocks[0]);
        $session = $sessionResponse->json('session');
        $projection = $this->getJson('/api/projection/'.basename($session['projectorUrl']))->assertOk();
        $this->assertMap(json_decode($projection->getContent())->session->stage->blocks[0]->config->capacities, $ids);
        $this->assertStringNotContainsString('Private roles note', $projection->getContent());
        $this->postJson('/api/join', ['code' => $session['joinCode'], 'name' => 'Test participant'])->assertOk();
        $student = $this->getJson('/api/participation/'.$session['id'])->assertOk();
        $this->assertMap(json_decode($student->getContent())->session->stage->blocks[0]->config->capacities, $ids);
        $this->assertStringNotContainsString('Private roles note', $student->getContent());
        $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => 'roles', 'value' => ['roleId' => $ids[0]]])
            ->assertOk()->assertJsonPath('session.ownAnswers.0.value.roleId', $ids[0]);
        // Decoded wire data can be saved again without changing IDs or the domain representation.
        $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => $lesson['revision'] + 1, 'document' => $lesson['document']])
            ->assertOk()->assertJsonPath('lesson.document.stages.0.blocks.0.content.ru.roles.0.roleId', $ids[0]);
    }

    public static function numericRoles(): array
    {
        return [['ids' => ['0']], ['ids' => ['0', '1']]];
    }

    private function assertMap(mixed $value, array $ids): void
    {
        $this->assertInstanceOf(stdClass::class, $value);
        $this->assertSame(array_fill_keys($ids, 1), (array) $value);
    }
}
