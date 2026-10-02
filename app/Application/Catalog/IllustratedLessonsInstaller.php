<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Shared\OwnerMutation;
use App\Models\CatalogEntry;

final readonly class IllustratedLessonsInstaller
{
    public function __construct(private ReviewedLessonInstaller $lessons, private CommonStarterInstaller $starters) {}

    public function install(): array
    {
        $releases = require resource_path('content/illustrated-releases.php');
        $pack = require resource_path('content/illustrated-starters.php');

        return OwnerMutation::transaction([...array_column(array_column($releases, 'old'), 'ownerKey'), CommonTemplateService::OWNER], function () use ($releases, $pack): array {
            $result = [];
            foreach ($releases as $key => $release) {
                if (! CatalogEntry::query()->where('slug', $release['old']['slug'])->exists()) {
                    if ($key === 'neighbor') {
                        app(NeighborInstaller::class)->install();
                        app(NeighborUpgradeInstaller::class)->install('v2');
                        app(NeighborUpgradeInstaller::class)->install('v3');
                    } else {
                        $installer = match ($key) {
                            'words' => WordsLessonInstaller::class,
                            'zakkhei' => ZakkheiLessonInstaller::class,
                            'judge' => JudgmentLessonInstaller::class,
                            'sheep' => LostSheepLessonInstaller::class,
                            'talent' => TalentLessonInstaller::class,
                        };
                        app($installer)->install();
                    }
                }
                $result[$key] = $this->lessons->upgrade($release['old'], $release['next'], $key === 'neighbor');
            }
            $result['templates'] = $this->starters->install($pack);

            return $result;
        });
    }
}
