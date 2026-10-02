<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\LostSheepLessonInstaller;
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

final class LostSheepLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_preserves_all_fourteen_slides_and_original_teaching_plan(): void
    {
        $first = app(LostSheepLessonInstaller::class)->install();
        $entry = $first['entry'];
        $this->assertTrue($first['created']);
        $doc = LessonDocument::fromArray($entry->version->document, app(BlockRegistry::class));
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2100, array_sum(array_column(array_map(fn ($s) => $s->config, $doc->stages), 'durationSeconds')));
        $raw = json_decode(file_get_contents(resource_path('content/poteryannaya-ovechka-source.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($doc->stages as $index => $stage) {
            $this->assertSame($raw['screens'][$index]['title'], $stage->content['ru']['title']);
            $this->assertSame('lavender', $stage->config['theme']);
            $this->assertStringContainsString($raw['screens'][$index]['notes'], $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertArrayNotHasKey('notes', $stage->project(Audience::Student, 'ru')['content']);
            $public = str_replace('\\n', ' ', json_encode(array_map(fn ($b) => $b->content['ru'], $stage->blocks), JSON_UNESCAPED_UNICODE));
            foreach ($raw['screens'][$index]['slides'] as $slide) {
                foreach ($raw['slides'][$slide - 1]['slide'] as $line) {
                    if ($slide === 1 && str_contains($line, '5–7')) {
                        continue;
                    }
                    $this->assertStringContainsString(preg_replace('/\s+/u', ' ', $line), preg_replace('/\s+/u', ' ', $public));
                }
            }
        }
        $this->assertStringContainsString($raw['handout'], $doc->documentation->toArray()['content']['ru']['plan']);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $count = count((require resource_path('content/poteryannaya-ovechka-starters.php'))['templates']);
        $this->assertDatabaseCount('common_templates', $count);
        $this->assertFalse(app(LostSheepLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('catalog_entries', 1);
        $this->assertDatabaseCount('common_templates', $count);
        $entry->update(['status' => 'hidden']);
        app(LostSheepLessonInstaller::class)->install();
        $this->assertSame('hidden', $entry->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_teacher_controls_flock_hints_story_review_and_private_choice(string $locale): void
    {
        app(LostSheepLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('poteryannaya-ovechka', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic child', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $execute('begin');
        $execute('stage', ['stageId' => 'sheep-step-02']);
        $herd = $runtime->student($session['id'], $participant)['stage']['blocks'][0];
        $this->assertSame([5, 4], array_column($herd['content']['modes'], 'count'));
        $this->get($herd['resources']['image'])->assertOk();
        $execute('presentation.mode', ['blockId' => 'sheep-step-02-herd', 'modeId' => 'missing']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $this->assertSame('missing', $dto['stage']['blocks'][0]['runtime']['presentation']['modeId']);
        }
        try {
            $runtime->answer($session['id'], $participant, 'sheep-step-02', 'sheep-step-02-herd', ['stageId' => 'sheep-step-02', 'blockId' => 'sheep-step-02-herd', 'value' => ['modeId' => 'herd']]);
            $this->fail('Children must not control the flock.');
        } catch (ApiProblem) {
            $this->assertSame('missing', $runtime->projector($token)['stage']['blocks'][0]['runtime']['presentation']['modeId']);
        }
        $execute('stage', ['stageId' => 'sheep-step-04']);
        $public = $runtime->projector($token);
        $this->assertSame('contain', $public['stage']['blocks'][1]['config']['fit']);
        $this->assertEmpty($public['stage']['blocks'][2]['content']['text']);
        $this->assertEmpty($public['stage']['blocks'][3]['content']['text']);
        $execute('presentation.toggle', ['blockId' => 'sheep-step-04-hint-1']);
        $public = $runtime->projector($token);
        $this->assertNotEmpty($public['stage']['blocks'][2]['content']['text']);
        $this->assertEmpty($public['stage']['blocks'][3]['content']['text']);
        $execute('stage', ['stageId' => 'sheep-step-08']);
        $public = $runtime->projector($token);
        $this->assertEmpty($public['stage']['blocks'][2]['content']['text']);
        $this->assertArrayNotHasKey('solution', $public['stage']['blocks'][1]);
        $execute('presentation.toggle', ['blockId' => 'sheep-step-08-review']);
        $this->assertNotEmpty($runtime->projector($token)['stage']['blocks'][2]['content']['text']);
        $execute('stage', ['stageId' => 'sheep-step-12']);
        $runtime->answer($session['id'], $participant, 'sheep-step-12', 'sheep-step-12-choice', ['stageId' => 'sheep-step-12', 'blockId' => 'sheep-step-12-choice', 'value' => ['modeId' => 'care-2']]);
        $this->assertSame('care-2', collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', 'sheep-step-12-choice')['value']['modeId']);
        $choice = collect($runtime->projector($token)['stage']['blocks'])->firstWhere('id', 'sheep-step-12-choice');
        $this->assertArrayNotHasKey('modeId', $choice['runtime']['presentation'] ?? []);
        $this->assertArrayNotHasKey('documentation', $runtime->projector($token));
        $execute('finish');
        $this->assertSame('finished', $runtime->student($session['id'], $participant)['status']);
        $this->assertSame('lavender', $runtime->projector($token)['stage']['config']['theme']);
        $this->assertNotEmpty($runtime->projector($token)['closing']['content']['title']);
    }

    public static function invalidCounts(): array
    {
        return [['negative'], ['too-large'], ['fraction'], ['locale-mismatch'], ['missing-media'], ['unexpected-count']];
    }

    public function test_count_image_is_checked_for_ownership_and_included_in_installation_receipt(): void
    {
        $source = require resource_path('content/poteryannaya-ovechka.php');
        $source['document']['stages'][1]['blocks'][0]['media']['image'] = ['assetId' => 'foreign-image', 'versionId' => 'foreign-image-v1'];
        $doc = LessonDocument::fromArray($source['document'], app(BlockRegistry::class));
        try {
            app(MediaCatalogue::class)->assertDocument($doc);
            $this->fail('An unowned image reference cannot be installed.');
        } catch (ApiProblem $problem) {
            $this->assertSame(422, $problem->getCode() ?: $problem->status);
        }
        app(LostSheepLessonInstaller::class)->install();
        $manifest = config('media_builtin');
        foreach ($manifest as &$entry) {
            if ($entry['assetId'] === 'builtin-sheep-5') {
                $entry['file'] = 'assets/library/poteryannaya-ovechka/03.png';
            }
        }
        unset($entry);
        config(['media_builtin' => $manifest]);
        $this->expectException(RuntimeException::class);
        app(LostSheepLessonInstaller::class)->install();
    }

    #[DataProvider('invalidCounts')]
    public function test_picture_count_validation_is_strict(string $case): void
    {
        $source = require resource_path('content/poteryannaya-ovechka.php');
        $block = &$source['document']['stages'][1]['blocks'][0];
        match ($case) {
            'negative' => $block['content']['ru']['modes'][0]['count'] = -1,
            'too-large' => $block['content']['ru']['modes'][0]['count'] = 6,
            'fraction' => $block['content']['ru']['modes'][0]['count'] = 4.5,
            'locale-mismatch' => $block['content']['de']['modes'][0]['count'] = 3,
            'missing-media' => $block['media'] = [],
            'unexpected-count' => $block['config']['kind'] = 'discussion',
        };
        $this->expectException(ValidationException::class);
        LessonDocument::fromArray($source['document'], app(BlockRegistry::class));
    }
}
