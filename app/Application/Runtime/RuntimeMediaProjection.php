<?php

declare(strict_types=1);

namespace App\Application\Runtime;

use App\Application\Shared\MediaCatalogue;
use App\Domain\Lessons\Audience;
use App\Models\TeachingSession;

/** Add allowed file URLs only after the domain has removed private content. */
final readonly class RuntimeMediaProjection
{
    public function __construct(private MediaCatalogue $media) {}

    public function stage(array $projection, TeachingSession $session, Audience $audience): array
    {
        foreach ($projection['blocks'] as &$block) {
            if ($block['type'] !== 'core.image') {
                continue;
            }
            $reference = $block['media']['image'];
            $resolved = $this->media->resolve($reference['assetId'], $reference['versionId'], $session->owner_key);
            $imageUrl = $resolved['url'];
            if (! str_starts_with($imageUrl, '/media/builtin/')) {
                $pair = rawurlencode($reference['assetId']).'/'.rawurlencode($reference['versionId']);
                $imageUrl = $session->mode === 'rehearsal' && $audience !== Audience::Teacher
                    ? '/media/rehearsal/'.rawurlencode($session->id).'/'.$pair : match ($audience) {
                        Audience::Teacher => '/media/owned/'.$pair,
                        Audience::Student => '/media/participation/'.rawurlencode($session->id).'/'.$pair,
                        Audience::Projector => '/media/projection/'.rawurlencode($session->projector_token).'/'.$pair,
                    };
            }
            $block['resources'] = ['image' => $imageUrl];
        }
        unset($block);

        return $projection;
    }
}
