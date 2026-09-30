<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

/** A validated immutable template revision; library metadata and access live outside this DTO. */
final readonly class BlockTemplate
{
    public function __construct(
        public string $templateId,
        public string $versionId,
        public BlockInstance $block,
    ) {
        Shape::id($templateId, 'template.id');
        Shape::id($versionId, 'template.versionId');
    }

    public function instantiate(string $instanceId): BlockInstance
    {
        if ($instanceId === $this->block->id) {
            throw new ValidationException('Template insertion requires a new instance identifier.');
        }

        return $this->block->withIdentity($instanceId,
            ['templateId' => $this->templateId, 'versionId' => $this->versionId]);
    }
}
