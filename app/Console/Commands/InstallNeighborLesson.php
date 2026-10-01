<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\NeighborInstaller;
use Illuminate\Console\Command;
use RuntimeException;

final class InstallNeighborLesson extends Command
{
    protected $signature = 'lessons:install-neighbor';

    protected $description = 'Install the reviewed immutable RU/DE neighbor lesson and its public catalog receipt safely';

    public function handle(NeighborInstaller $installer): int
    {
        try {
            $result = $installer->install();
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        $this->info(($result['created'] ? 'Installed: ' : 'Already installed: ').$result['entry']->slug);

        return self::SUCCESS;
    }
}
