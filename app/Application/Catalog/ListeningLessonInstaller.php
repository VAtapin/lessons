<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Shared\OwnerMutation;

final readonly class ListeningLessonInstaller
{
    public function __construct(private ReviewedLessonInstaller $lessons, private CommonStarterInstaller $starters) {}

    public function install(): array
    {
        $sources = require resource_path('content/listening.php');
        $pack = require resource_path('content/listening-starters.php');

        return OwnerMutation::transaction([...array_column($sources, 'ownerKey'), CommonTemplateService::OWNER], function () use ($sources, $pack): array {
            $entries = [];
            foreach ($sources as $variant => $source) {
                $entries[$variant] = $this->lessons->install($source);
            }

            return ['entries' => $entries, 'templates' => $this->starters->install($pack)];
        });
    }
}
