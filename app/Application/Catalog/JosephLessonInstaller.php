<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Shared\OwnerMutation;

final readonly class JosephLessonInstaller
{
    public function __construct(private ReviewedLessonInstaller $lessons, private CommonStarterInstaller $starters) {}

    public function install(): array
    {
        $source = require resource_path('content/joseph.php');
        $pack = require resource_path('content/joseph-starters.php');

        return OwnerMutation::transaction([$source['ownerKey'], CommonTemplateService::OWNER], function () use ($source, $pack): array {
            return $this->lessons->install($source) + ['templates' => $this->starters->install($pack)];
        });
    }
}
