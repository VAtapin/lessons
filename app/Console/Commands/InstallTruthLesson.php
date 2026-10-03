<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\TruthLessonInstaller;
use Illuminate\Console\Command;
use RuntimeException;

final class InstallTruthLesson extends Command
{
    protected $signature = 'lessons:install-truth';

    protected $description = 'Install the reviewed Truth lesson and reusable RU/DE teaching blocks';

    public function handle(TruthLessonInstaller $installer): int
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
