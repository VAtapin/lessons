<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\NeighborDocumentationInstaller;
use Illuminate\Console\Command;
use Throwable;

final class InstallNeighborDocumentation extends Command
{
    protected $signature = 'lessons:install-neighbor-docs';

    protected $description = 'Install the approved bilingual teacher plan and supplied source files without replacing releases';

    public function handle(NeighborDocumentationInstaller $installer): int
    {
        try {
            $this->info($installer->install() ? 'Installed: neighbor documentation' : 'Already installed: neighbor documentation');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Documentation installation failed. Existing content was preserved.');

            return self::FAILURE;
        }
    }
}
