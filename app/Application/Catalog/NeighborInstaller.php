<?php

declare(strict_types=1);

namespace App\Application\Catalog;

final readonly class NeighborInstaller
{
    public function __construct(private ReviewedLessonInstaller $installer) {}

    public function install(): array
    {
        return $this->installer->install(require resource_path('content/kto-moi-blizhnii.php'));
    }
}
