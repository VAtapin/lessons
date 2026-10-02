<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\IllustratedLessonsInstaller;
use Illuminate\Console\Command;
use RuntimeException;

final class UpgradeIllustratedLessons extends Command
{
    protected $signature = 'lessons:upgrade-illustrated';

    protected $description = 'Install reviewed illustrated releases without changing earlier copies or classes';

    public function handle(IllustratedLessonsInstaller $installer): int
    {
        try {
            $result = $installer->install();
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        foreach ($result as $key => $entry) {
            $this->info($key.': '.($key === 'templates' ? $entry['total'] : ($entry['created'] ? 'updated' : 'already installed')));
        }

        return self::SUCCESS;
    }
}
