<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Shared\OwnerMutation;

final readonly class ForgivenessLessonInstaller
{
    public function __construct(private ReviewedLessonInstaller $lessons, private CommonStarterInstaller $starters) {}

    public function install(): array
    {
        $source = require resource_path('content/forgiveness.php');
        $pack = require resource_path('content/forgiveness-starters.php');

        return OwnerMutation::transaction([$source['ownerKey'], CommonTemplateService::OWNER], function () use ($source, $pack): array {
            return $this->lessons->install($source) + ['templates' => $this->starters->install($pack)];
        });
    }
}
