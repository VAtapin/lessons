<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\FearLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\MediaCatalogue;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\ValidationException;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class FearLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_screens_full_plan_images_downloads_and_reusable_blocks_are_preserved(): void
    {
        $result = app(FearLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/strakh-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        $images = [];
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString($raw['screens'][$i]['notes'], $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertSame('ocean', $stage->config['theme']);
            $this->assertArrayNotHasKey('notes', $stage->project(Audience::Projector, 'de')['content']);
            $text = $normalize(json_encode(array_map(fn ($b) => $b->content['ru'], $stage->blocks), JSON_UNESCAPED_UNICODE));
            foreach ($raw['screens'][$i]['slides'] as $number) {
                if ($number === 1) {
                    $this->assertStringContainsString($raw['title'], $text);

                    continue;
                }
                foreach ($raw['slides'][$number - 1]['slide'] as $line) {
                    $this->assertStringContainsString($normalize($line), $text);
                }
            }
            foreach ($stage->blocks as $block) {
                if (isset($block->media['image'])) {
                    $asset = $block->media['image']['assetId'];
                    $images[] = $asset;
                    $n = (int) str_replace('builtin-fear-', '', $asset);
                    $this->assertSame(hash_file('sha256', base_path('assets/lessons/kogda-mne-strashno/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/strakh/'.sprintf('%02d.png', $n))));
                }
            }
        }
        $this->assertCount(14, array_unique($images));
        $plans = $doc->documentation->toArray()['content'];
        foreach (['handout', 'teacherPreparation'] as $section) {
            $this->assertStringContainsString($raw[$section], $plans['ru']['plan']);
        }
        $this->assertStringContainsString('41 И убоялись страхом великим', $plans['ru']['plan']);
        $this->assertStringContainsString('41 Sie fürchteten sich sehr', $plans['de']['plan']);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $this->assertDatabaseCount('common_templates', 26);
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(FearLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', 26);
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_illustrated_reveals_sequence_and_separate_answers_follow_teacher_commands(string $locale): void
    {
        app(FearLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('kogda-mne-strashno', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $block = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        foreach ([2 => 'builtin-fear-2-v1', 6 => 'builtin-fear-9-v1'] as $n => $image) {
            $stage = 'fear-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $id = $stage.'-next-picture';
            $execute('stage', ['stageId' => $stage]);
            foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
                $reveal = $block($dto, $id);
                $this->assertSame('', $reveal['content']['text']);
                $this->assertArrayNotHasKey('title', $reveal['content']);
                $this->assertEmpty($reveal['media']);
                $this->assertEmpty($reveal['resources'] ?? []);
                $this->assertArrayNotHasKey('documentation', $dto);
            }
            $execute('presentation.toggle', ['blockId' => $id]);
            foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
                $reveal = $block($dto, $id);
                $this->assertNotEmpty($reveal['content']['text']);
                $this->assertNotEmpty($reveal['content']['title']);
                $this->assertSame($image, $reveal['media']['image']['versionId']);
                $this->assertNotEmpty($reveal['resources']['image']);
            }
            $execute('presentation.toggle', ['blockId' => $id]);
            $this->assertEmpty($block($runtime->projector($token), $id)['media']);
        }
        $execute('stage', ['stageId' => 'fear-step-03']);
        $sequence = $block($runtime->student($session['id'], $participant), 'fear-step-03-sequence');
        $this->assertArrayNotHasKey('solution', $sequence);
        $value = ['itemIds' => ['event-1', 'event-2', 'event-3', 'event-4']];
        $runtime->answer($session['id'], $participant, 'fear-step-03', 'fear-step-03-sequence', ['stageId' => 'fear-step-03', 'blockId' => 'fear-step-03-sequence', 'value' => $value]);
        $dto = $runtime->student($session['id'], $participant);
        $this->assertSame($value, collect($dto['ownAnswers'])->firstWhere('blockId', 'fear-step-03-sequence')['value']);
        $this->assertArrayNotHasKey('results', $block($dto, 'fear-step-03-sequence')['runtime']);
        $execute('block.review', ['blockId' => 'fear-step-03-sequence']);
        $this->assertNotEmpty($block($runtime->projector($token), 'fear-step-03-sequence')['runtime']['results']);
        foreach ([5, 6, 9, 10, 11] as $n) {
            $stage = 'fear-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $execute('stage', ['stageId' => $stage]);
            $dto = $runtime->student($session['id'], $participant);
            $this->assertFalse(collect($dto['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        }
        foreach ([4 => ['answer'], 7 => ['request'], 8 => ['support'], 12 => ['disciples', 'step']] as $n => $ids) {
            $stage = 'fear-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $execute('stage', ['stageId' => $stage]);
            foreach ($ids as $suffix) {
                $id = $stage.'-'.$suffix;
                $runtime->answer($session['id'], $participant, $stage, $id, ['stageId' => $stage, 'blockId' => $id, 'value' => ['text' => 'Synthetic answer '.$suffix]]);
                $this->assertSame('Synthetic answer '.$suffix, collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id)['value']['text']);
            }
        }
        $execute('finish');
        $this->assertSame('finished', $runtime->student($session['id'], $participant)['status']);
        $this->assertSame('ocean', $runtime->projector($token)['stage']['config']['theme']);
        $this->assertNotEmpty($runtime->projector($token)['closing']['content']['title']);
    }

    public static function invalidMedia(): array
    {
        return [['scene'], ['missing-version'], ['extra-key']];
    }

    public function test_illustrated_reveal_checks_media_access_and_installation_receipt(): void
    {
        $source = require resource_path('content/strakh.php');
        $source['document']['stages'][1]['blocks'][2]['media']['image'] = ['assetId' => 'foreign-image', 'versionId' => 'foreign-image-v1'];
        $doc = LessonDocument::fromArray($source['document'], app(BlockRegistry::class));
        try {
            app(MediaCatalogue::class)->assertDocument($doc);
            $this->fail('Unowned reveal image cannot be installed.');
        } catch (ApiProblem $problem) {
            $this->assertSame(422, $problem->status);
        }
        $entry = app(FearLessonInstaller::class)->install()['entry'];
        $before = $entry->version->getAttributes();
        $manifest = config('media_builtin');
        foreach ($manifest as &$image) {
            if ($image['assetId'] === 'builtin-fear-2') {
                $image['file'] = 'assets/library/strakh/01.png';
            }
        }
        unset($image);
        config(['media_builtin' => $manifest]);
        try {
            app(FearLessonInstaller::class)->install();
            $this->fail('Reveal image drift must fail closed.');
        } catch (RuntimeException) {
            $this->assertSame($before, $entry->version->fresh()->getAttributes());
            $this->assertDatabaseCount('common_templates', 26);
        }
    }

    #[DataProvider('invalidMedia')]
    public function test_reveal_media_reuses_strict_image_reference_validation(string $case): void
    {
        $source = require resource_path('content/strakh.php');
        $b = &$source['document']['stages'][1]['blocks'][2];
        match ($case) {
            'scene' => $b['config']['kind'] = 'scene',
            'missing-version' => $b['media']['image'] = ['assetId' => 'builtin-fear-2'],
            'extra-key' => $b['media']['script'] = 'invalid',
        };
        $this->expectException(ValidationException::class);
        LessonDocument::fromArray($source['document'], app(BlockRegistry::class));
    }
}
