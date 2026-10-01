<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\NeighborInstaller;
use App\Application\Catalog\NeighborUpgradeInstaller;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\EditorDraft;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\ValidationException;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use App\Models\TeachingSession;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class NeighborFidelityTest extends TestCase
{
    use RefreshDatabase;

    private function v2(): array
    {
        app(NeighborInstaller::class)->install();

        return app(NeighborUpgradeInstaller::class)->install();
    }

    public function test_v3_adds_a_release_and_exact_repin_preserves_v2_copies_classes_and_visibility(): void
    {
        $entry = $this->v2()['entry'];
        $oldVersion = $entry->version->getAttributes();
        $copy = app(CatalogService::class)->use($entry->slug, 'ru', (string) Str::uuid(), true);
        $copyVersion = LessonMaterial::findOrFail($copy['lesson']['id'])->currentVersion;
        $copyBefore = $copyVersion->getAttributes();
        $sessionBefore = TeachingSession::findOrFail($copy['session']['id'])->getAttributes();
        $entry->update(['status' => 'hidden']);
        $approval = $entry->only(['status', 'approved_by', 'approved_at', 'metadata', 'slug']);
        $this->artisan('lessons:upgrade-neighbor')->expectsOutput('Upgraded: kto-moi-blizhnii')->assertSuccessful();
        $entry->refresh();
        $this->assertSame((require resource_path('content/kto-moi-blizhnii-v3.php'))['versionId'], $entry->lesson_version_id);
        $this->assertSame($oldVersion, LessonVersion::findOrFail($oldVersion['id'])->getAttributes());
        $this->assertSame($copyBefore, $copyVersion->fresh()->getAttributes());
        $this->assertSame($sessionBefore, TeachingSession::findOrFail($copy['session']['id'])->getAttributes());
        $this->assertEquals($approval, $entry->only(array_keys($approval)));
        $this->assertSame(3, $entry->version->material->revision);
        $newBefore = $entry->version->getAttributes();
        $this->artisan('lessons:upgrade-neighbor')->expectsOutput('Already upgraded: kto-moi-blizhnii')->assertSuccessful();
        $this->assertSame($newBefore, $entry->fresh()->version->getAttributes());
        $this->assertSame(3, LessonVersion::where('lesson_material_id', $entry->version->lesson_material_id)->count());
    }

    public static function faults(): array
    {
        return [['owner'], ['revision'], ['document'], ['receipt'], ['occupied']];
    }

    #[DataProvider('faults')]
    public function test_v3_rejects_changed_source_or_occupied_id_atomically(string $fault): void
    {
        $entry = $this->v2()['entry'];
        if ($fault === 'owner') {
            $entry->version->material->update(['owner_key' => (string) Str::uuid()]);
        } elseif ($fault === 'revision') {
            $entry->version->material->update(['revision' => 9]);
        } elseif ($fault === 'document') {
            $document = $entry->version->document;
            $document['content']['ru']['title'] = 'Changed';
            DB::table('lesson_versions')->where('id', $entry->lesson_version_id)->update(['document' => json_encode($document)]);
        } elseif ($fault === 'receipt') {
            DB::table('catalog_entries')->where('id', $entry->id)->update(['source_hash' => str_repeat('0', 64)]);
        } else {
            $occupied = new LessonVersion(['lesson_material_id' => $entry->version->lesson_material_id, 'status' => 'released', 'purpose' => 'authoring', 'document' => $entry->version->document]);
            $occupied->id = (require resource_path('content/kto-moi-blizhnii-v3.php'))['versionId'];
            $occupied->save();
        }
        $before = LessonVersion::query()->orderBy('id')->get()->map->getAttributes()->all();
        try {
            app(NeighborUpgradeInstaller::class)->install('v3');
            $this->fail('Changed immutable source must be refused.');
        } catch (RuntimeException) {
            $this->assertSame($before, LessonVersion::query()->orderBy('id')->get()->map->getAttributes()->all());
            $this->assertSame($entry->lesson_version_id, $entry->fresh()->lesson_version_id);
        }
    }

    public function test_failure_after_repin_rolls_back_the_new_release_pin_and_revision(): void
    {
        $entry = $this->v2()['entry'];
        $material = $entry->version->material;
        $beforeMaterial = $material->getAttributes();
        $beforeEntry = $entry->getAttributes();
        $beforeVersions = LessonVersion::query()->orderBy('id')->get()->map->getAttributes()->all();
        LessonMaterial::updating(function (LessonMaterial $saving) use ($material): void {
            if ($saving->id === $material->id && $saving->revision === 3) {
                throw new RuntimeException('Simulated failure after the catalog repin.');
            }
        });
        try {
            app(NeighborUpgradeInstaller::class)->install('v3');
            $this->fail('The interrupted upgrade must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failure after the catalog repin.', $exception->getMessage());
            $this->assertSame($beforeMaterial, $material->fresh()->getAttributes());
            $this->assertSame($beforeEntry, $entry->fresh()->getAttributes());
            $this->assertSame($beforeVersions, LessonVersion::query()->orderBy('id')->get()->map->getAttributes()->all());
        }
    }

    public function test_v3_contains_verbatim_visible_original_words_on_all_thirteen_stages_and_finish(): void
    {
        $this->v2();
        $entry = app(NeighborUpgradeInstaller::class)->install('v3')['entry'];
        $document = $entry->version->document;
        $html = new DOMDocument;
        @$html->loadHTML('<?xml encoding="UTF-8">'.str_replace('<br>', ' ', file_get_contents(base_path('OLD/kto-moi-blizhnii/lesson.html'))));
        $xpath = new DOMXPath($html);
        $scenes = $xpath->query('//main[@id="lessonStage"]/section');
        $this->assertCount(13, $document['stages']);
        $this->assertSame(13, $scenes->length);
        $normal = fn (string $text): string => trim(preg_replace('/\s+/u', ' ', $text));
        foreach ($scenes as $index => $scene) {
            $content = array_map(fn (array $block): array => BlockInstance::fromArray($block, app(BlockRegistry::class), ['ru', 'de'])->project(Audience::Student, 'ru')['content'], $document['stages'][$index]['blocks']);
            $flat = [];
            array_walk_recursive($content, function ($value) use (&$flat): void {
                if (is_string($value)) {
                    $flat[] = $value;
                }
            });
            $haystack = $normal(implode(' ', $flat));
            foreach ($xpath->query('.//*[self::p or self::h1 or self::h2 or self::strong or self::label or self::button or self::cite][not(ancestor::*[contains(@class,"scene__cue")])]', $scene) as $node) {
                // Decorative choice numerals belong to the renderer; the lesson stores semantic words.
                $semantic = $node->cloneNode(true);
                foreach ((new DOMXPath($semantic->ownerDocument))->query('.//*[@aria-hidden="true"]', $semantic) as $decoration) {
                    $decoration->parentNode->removeChild($decoration);
                }
                $text = $normal($semantic->textContent);
                if ($text !== '') {
                    $this->assertStringContainsString($text, $haystack, 'Missing OLD words on stage '.($index + 1));
                }
            }
            foreach ($xpath->query('.//input[@placeholder]', $scene) as $node) {
                $this->assertStringContainsString($node->getAttribute('placeholder'), $haystack);
            }
        }
        $closing = collect($document['stages'][12]['blocks'])->firstWhere('id', 'lesson-closing');
        $this->assertSame('Милосердие начинается с первого шага', $closing['content']['ru']['title']);
        $this->assertSame('Спасибо за честные ответы, внимание друг к другу и готовность помочь.', $closing['content']['ru']['text']);
        $this->assertSame('Вернуться к началу', $closing['content']['ru']['label']);
        $this->assertSame('«Иди, и ты поступай так же»', $closing['content']['ru']['quote']);
    }

    public function test_v3_role_sequence_and_school_dynamic_words_match_the_original_script(): void
    {
        $source = require resource_path('content/kto-moi-blizhnii-v3.php');
        $stages = $source['document']['stages'];
        $script = file_get_contents(base_path('OLD/kto-moi-blizhnii/app.js'));
        $block = fn (int $stage, string $id): array => collect($stages[$stage]['blocks'])->firstWhere('id', $id)['content']['ru'];
        $classroom = file_get_contents(base_path('OLD/kto-moi-blizhnii/classroom.js'));
        foreach (['readyLabel' => 'Готов ✦', 'questionLabel' => 'Есть вопрос'] as $field => $label) {
            $this->assertStringContainsString($label, $classroom);
            $this->assertSame($label, $block(0, 'intro-signals')[$field]);
        }
        preg_match('/const roles = \[(.*?)\];/s', $script, $roleArray);
        preg_match_all("/'([^']+)'/u", $roleArray[1], $roles);
        $this->assertSame($roles[1], array_column($block(1, 'traveler-roles')['roles'], 'text'));
        $scene = $block(1, 'neighbor-scene-2');
        foreach (['actionLabel' => 'Следующая роль', 'resetLabel' => 'Сбросить роли', 'restartLabel' => 'Первая роль', 'resetText' => 'Начинаем новый набор ролей'] as $field => $original) {
            $this->assertStringContainsString($original, $script);
            $this->assertSame($original, $scene[$field]);
        }
        $this->assertSame('Скрыть поступок героя', $block(4, 'samaritan-reveal')['hideLabel']);
        preg_match_all("/\{ label: '([^']+)', icon: '([^']+)' \}/u", $script, $sequence);
        $journey = $block(6, 'help-sequence');
        $journeyBlock = collect($stages[6]['blocks'])->firstWhere('id', 'help-sequence');
        $itemsById = array_column($journey['items'], null, 'itemId');
        $ordered = array_map(fn (string $id): array => $itemsById[$id], $journeyBlock['solution']['itemIds']);
        $this->assertCount(5, $sequence[1]);
        $this->assertSame($sequence[1], array_column($ordered, 'text'));
        $this->assertSame($sequence[2], array_column($ordered, 'icon'));
        foreach ([
            'feedbackFirstWrong' => 'С чего начинается помощь? Сначала нужно заметить человека.',
            'feedbackWrong' => 'Этот поступок будет позже. Что сделал самарянин перед ним?',
            'feedbackComplete' => 'Помощь продолжилась даже после отъезда самарянина.',
            'reviewLabel' => 'Идёт общая проверка',
        ] as $field => $original) {
            $this->assertStringContainsString($original, $script);
            $this->assertSame($original, $journey[$field]);
        }
        $this->assertSame('Верно. Теперь шаг {step}.', $journey['feedbackCorrect']);
        preg_match_all("/(newcomer|books|game): \{\s*excuse: \['([^']+)', '([^']+)'\],\s*help: \['([^']+)', '([^']+)'\]/u", $script, $situations, PREG_SET_ORDER);
        $this->assertCount(3, $situations);
        foreach ($situations as $index => $situation) {
            $modes = $block(8 + $index, $situation[1].'-discussion')['modes'];
            $this->assertSame($situation[2], $modes[0]['title']);
            $this->assertSame($situation[3], $modes[0]['text']);
            $this->assertSame($situation[4], $modes[1]['title']);
            $this->assertSame($situation[5], $modes[1]['text']);
        }
    }

    public function test_plan_instructions_are_copied_into_every_private_stage_and_never_public_notes(): void
    {
        $source = require resource_path('content/kto-moi-blizhnii-v3.php');
        $document = LessonDocument::fromArray($source['document'], app(BlockRegistry::class));
        $this->assertSame(['ru', 'de'], EditorDraft::fromArray($source['document'], app(BlockRegistry::class))->readiness()['readyLocales']);
        $mapping = [1, 2, 3, 3, 4, 5, 6, 7, 8, 8, 8, 8, 8];
        foreach (['ru', 'de'] as $locale) {
            $sections = preg_split('/\n(?=[1-8]\. )/u', $source['document']['documentation']['content'][$locale]['plan']);
            foreach ($document->stages as $index => $stage) {
                $this->assertStringContainsString(trim($sections[$mapping[$index]]), $stage->content[$locale]['notes']);
                $this->assertArrayNotHasKey('notes', $stage->project(Audience::Student, $locale)['content']);
                $this->assertArrayNotHasKey('notes', $stage->project(Audience::Projector, $locale)['content']);
                $this->assertSame($stage->content[$locale]['notes'], $stage->project(Audience::Teacher, $locale)['content']['notes']);
            }
        }
    }

    public function test_discussion_accepts_only_known_mode_identity_without_any_publication_fields(): void
    {
        $source = require resource_path('content/kto-moi-blizhnii-v3.php');
        $blockData = collect($source['document']['stages'][8]['blocks'])->firstWhere('id', 'newcomer-discussion');
        $registry = app(BlockRegistry::class);
        $block = BlockInstance::fromArray($blockData, $registry, ['ru', 'de']);
        $definition = $registry->resolve($block->type, 1);
        $this->assertSame(['modeId' => 'help'], $definition->validateAnswer($block, ['modeId' => 'help']));
        foreach ([['modeId' => 'private'], ['modeId' => false], ['modeId' => 'help', 'text' => 'Injected'], ['text' => 'help']] as $answer) {
            try {
                $definition->validateAnswer($block, $answer);
                $this->fail('Unknown or extra discussion answer fields must fail.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        foreach (['scene', 'summary', 'closing', 'reveal', 'response-board'] as $kind) {
            $blockData['config']['kind'] = $kind;
            $blockData['content']['ru']['modes'] = $blockData['content']['de']['modes'] = [];
            if ($kind === 'summary') {
                $blockData['content']['ru']['items'] = $blockData['content']['de']['items'] = [['itemId' => 'one', 'text' => 'One']];
            } else {
                unset($blockData['content']['ru']['items'], $blockData['content']['de']['items']);
            }
            $block = BlockInstance::fromArray($blockData, $registry, ['ru', 'de']);
            try {
                $definition->validateAnswer($block, ['modeId' => 'help']);
                $this->fail('Only discussion kind accepts student choices.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }
}
