<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Shared\OwnerMutation;
use App\Models\CatalogEntry;

final readonly class VineyardLessonInstaller
{
    public function __construct(private ReviewedLessonInstaller $lessons, private CommonStarterInstaller $starters) {}

    public function install(): array
    {
        $source = require resource_path('content/vineyard.php');
        $pack = require resource_path('content/vineyard-starters.php');

        return OwnerMutation::transaction([$source['ownerKey'], CommonTemplateService::OWNER], function () use ($source, $pack): array {
            // A reviewed upgrade repins only the catalog; existing classes retain v1.
            $entry = CatalogEntry::query()->where('slug', $source['slug'])->first();
            $lesson = $entry !== null && $entry->source_revision === 'vineyard-ru-de-2026-10-03-v1'
                ? $this->lessons->upgrade(require resource_path('content/vineyard-v1.php'), $source)
                : $this->lessons->install($source);

            return $lesson + ['templates' => $this->starters->install($pack)];
        });
    }
}
