<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Catalog\NeighborUpgradeInstaller;
use Illuminate\Console\Command;
use RuntimeException;

final class UpgradeNeighborLesson extends Command
{
    protected $signature = 'lessons:upgrade-neighbor {--revision=v3 : Reviewed target revision (v2 or v3)}';

    protected $description = 'Add the reviewed OLD workflow release and repin its exact source receipt without changing existing copies or classes';

    public function handle(NeighborUpgradeInstaller $installer): int
    {
        try {
            $result = $installer->install((string) $this->option('revision'));
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        $this->info(($result['created'] ? 'Upgraded: ' : 'Already upgraded: ').$result['entry']->slug);

        return self::SUCCESS;
    }
}
