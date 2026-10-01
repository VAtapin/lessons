<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\History\RetentionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ApplyRetention implements ShouldQueue
{
    use Queueable;

    public function handle(RetentionService $retention): void
    {
        $retention->run(false);
    }
}
