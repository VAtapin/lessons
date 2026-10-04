<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\ListeningLessonInstaller;
use Illuminate\Console\Command;
use RuntimeException;

final class InstallListeningLesson extends Command
{
    protected $signature = 'lessons:install-listening';

    protected $description = 'Install adult and couples Listening lessons and reusable RU/DE blocks';

    public function handle(ListeningLessonInstaller $installer): int
    {
        try {
            $result = $installer->install();
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        foreach ($result['entries'] as $entry) {
            $this->info(($entry['created'] ? 'Installed: ' : 'Already installed: ').$entry['entry']->slug);
        }
        $this->info('Reusable blocks: '.$result['templates']['total']);

        return self::SUCCESS;
    }
}
