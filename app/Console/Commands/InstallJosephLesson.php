<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\JosephLessonInstaller;
use Illuminate\Console\Command;
use RuntimeException;

final class InstallJosephLesson extends Command
{
    protected $signature = 'lessons:install-joseph';

    protected $description = 'Install the reviewed Joseph lesson and reusable RU/DE teaching blocks';

    public function handle(JosephLessonInstaller $installer): int
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
