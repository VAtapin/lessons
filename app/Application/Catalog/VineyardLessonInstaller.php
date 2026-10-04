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
            // Retired release receipts permit upgrades without shipping obsolete lesson content.
            $entry = CatalogEntry::query()->where('slug', $source['slug'])->first();
            $lesson = $entry !== null && $entry->source_revision === 'vineyard-ru-de-2026-10-03-v1'
                ? $this->lessons->upgradeRetired(json_decode(file_get_contents(resource_path('content/vineyard-retired-receipt.json')), true, flags: JSON_THROW_ON_ERROR), $source)
                : $this->lessons->install($source);

            return $lesson + ['templates' => $this->starters->install($pack)];
        });
    }
}
