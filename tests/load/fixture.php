<?php

declare(strict_types=1);

use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use Illuminate\Contracts\Console\Kernel;

ini_set('display_errors', '0');
ini_set('log_errors', '0');

try {
    if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') {
        throw new RuntimeException('Fixture requires a testing CLI.');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    require __DIR__.'/guard.php';
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    loadDatabase($app);
    $options = [['optionId' => 'a', 'text' => 'A'], ['optionId' => 'b', 'text' => 'B']];
    $block = fn (string $id, string $type, array $content, array $config = [], ?array $solution = null): array => [
        'id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1,
        'content' => ['ru' => $content], 'config' => $config, 'solution' => $solution,
        'teacherNotes' => ['ru' => 'Private load fixture note'],
    ];
    $document = ['id' => 'load-fixture', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'],
        'content' => ['ru' => ['title' => 'Synthetic HTTP load fixture']], 'stages' => [
            ['id' => 'first', 'content' => ['ru' => ['title' => 'First']], 'blocks' => [
                $block('single', 'single-choice', ['question' => 'Choose', 'options' => $options], [], ['optionId' => 'a']),
                $block('poll', 'poll', ['question' => 'Vote', 'options' => $options]),
            ]],
            ['id' => 'second', 'content' => ['ru' => ['title' => 'Second']], 'blocks' => [
                $block('free', 'free-response', ['question' => 'Write'], ['allowRepeat' => true, 'maxLength' => 100]),
            ]],
        ]];
    $validated = LessonDocument::fromArray($document, $app->make(BlockRegistry::class));
    // Provisioning/release happens through the real HTTP APIs with independent owner cookies.
    echo json_encode(['guard' => 'testing/lessons_test/MariaDB10.6', 'document' => $validated->toArray()], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    fwrite(STDERR, "Load fixture guard or domain validation failed.\n");
    exit(1);
}
