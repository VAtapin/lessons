<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\WordsLessonInstaller;
use Illuminate\Console\Command;
use RuntimeException;

final class InstallWordsLesson extends Command
{
    protected $signature = 'lessons:install-words';

    protected $description = 'Install the reviewed words lesson, supplied resources and reusable RU/DE blocks';

    public function handle(WordsLessonInstaller $installer): int
    {
        try {
            $result = $installer->install();
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        $this->info(($result['created'] ? 'Installed: ' : 'Already installed: ').$result['entry']->slug);
        $this->info('Reusable blocks: '.$result['templates']['total']);

        return self::SUCCESS;
    }
}
