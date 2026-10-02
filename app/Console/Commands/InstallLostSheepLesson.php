<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\LostSheepLessonInstaller;
use Illuminate\Console\Command;
use RuntimeException;

final class InstallLostSheepLesson extends Command
{
    protected $signature = 'lessons:install-sheep';

    protected $description = 'Install the reviewed Lost sheep lesson and reusable RU/DE teaching blocks';

    public function handle(LostSheepLessonInstaller $installer): int
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
