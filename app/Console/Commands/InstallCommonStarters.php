<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\CommonStarterInstaller;
use Illuminate\Console\Command;
use RuntimeException;

final class InstallCommonStarters extends Command
{
    protected $signature = 'lessons:install-common';

    protected $description = 'Install the reviewed reusable RU/DE starter pack without overwriting editor versions or visibility';

    public function handle(CommonStarterInstaller $installer): int
    {
        try {
            $result = $installer->install();
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        $this->info('Common starters: installed '.$result['installed'].', preserved '.$result['preserved'].', total '.$result['total'].'.');

        return self::SUCCESS;
    }
}
