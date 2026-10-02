<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\ZakkheiLessonInstaller;
use Illuminate\Console\Command;
use RuntimeException;

final class InstallZakkheiLesson extends Command
{
    protected $signature = 'lessons:install-zakkhei';

    protected $description = 'Install the reviewed Zacchaeus lesson and reusable RU/DE teaching blocks';

    public function handle(ZakkheiLessonInstaller $installer): int
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
