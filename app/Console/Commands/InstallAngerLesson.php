<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\AngerLessonInstaller;
use Illuminate\Console\Command;
use RuntimeException;

final class InstallAngerLesson extends Command
{
    protected $signature = 'lessons:install-anger';

    protected $description = 'Install the reviewed Anger lesson and reusable RU/DE teaching blocks';

    public function handle(AngerLessonInstaller $installer): int
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
