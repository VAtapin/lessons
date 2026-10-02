<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\JudgmentLessonInstaller;
use Illuminate\Console\Command;
use RuntimeException;

final class InstallJudgmentLesson extends Command
{
    protected $signature = 'lessons:install-judge';

    protected $description = 'Install the reviewed Do not rush to judge lesson and reusable RU/DE teaching blocks';

    public function handle(JudgmentLessonInstaller $installer): int
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
