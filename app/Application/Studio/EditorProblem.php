<?php

declare(strict_types=1);

namespace App\Application\Studio;

use RuntimeException;

final class EditorProblem extends RuntimeException
{
    public function __construct(public readonly string $problemCode, public readonly int $status, public readonly array $issues = [], public readonly ?array $readiness = null, public readonly ?array $lesson = null)
    {
        parent::__construct($problemCode);
    }

    public function payload(): array
    {
        $data = ['error' => ['code' => $this->problemCode]];
        if (in_array($this->problemCode, ['invalid_editor_document', 'translation_not_ready'], true)) {
            $data['issues'] = $this->issues;
        }
        if ($this->readiness !== null) {
            $data['readiness'] = $this->readiness;
        }
        if ($this->lesson !== null) {
            $data['lesson'] = $this->lesson;
        }

        return $data;
    }
}
