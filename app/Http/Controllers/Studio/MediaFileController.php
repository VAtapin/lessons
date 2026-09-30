<?php

declare(strict_types=1);

namespace App\Http\Controllers\Studio;

use App\Application\Shared\ApiProblem;
use App\Application\Shared\GuestIdentity;
use App\Application\Shared\MediaCatalogue;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Runtime\RuntimeController;
use App\Models\SessionParticipant;
use App\Models\TeachingSession;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MediaFileController extends Controller
{
    public function __construct(private readonly MediaCatalogue $catalogue, private readonly GuestIdentity $identity, private readonly BlockRegistry $registry) {}

    public function owned(Request $request, string $assetId, string $versionId): BinaryFileResponse
    {
        return $this->file($this->identity->key($request), $assetId, $versionId);
    }

    public function participation(Request $request, string $sessionId, string $assetId, string $versionId): BinaryFileResponse
    {
        $session = TeachingSession::query()->find($sessionId) ?? throw new ApiProblem('not_found', 404);
        $participantMap = $request->session()->get(RuntimeController::SESSION_PARTICIPANTS_KEY, []);
        $participant = is_array($participantMap) ? ($participantMap[$sessionId] ?? null) : null;
        if (! is_string($participant) || ! SessionParticipant::query()->whereKey($participant)->where('teaching_session_id', $sessionId)->exists()) {
            throw new ApiProblem('not_found', 404);
        }
        $this->assertActiveReference($session, $assetId, $versionId);

        return $this->file($session->owner_key, $assetId, $versionId);
    }

    public function projection(string $token, string $assetId, string $versionId): BinaryFileResponse
    {
        $session = TeachingSession::query()->where('projector_token', $token)->first() ?? throw new ApiProblem('not_found', 404);
        $this->assertActiveReference($session, $assetId, $versionId);

        return $this->file($session->owner_key, $assetId, $versionId);
    }

    private function assertActiveReference(TeachingSession $session, string $assetId, string $versionId): void
    {
        $document = LessonDocument::fromArray($session->version->document, $this->registry);
        foreach ($document->stages as $stage) {
            if ($stage->id === $session->current_stage_id) {
                foreach ($stage->blocks as $block) {
                    if ($block->type === 'core.image' && $block->media['image']['assetId'] === $assetId
                        && $block->media['image']['versionId'] === $versionId) {
                        return;
                    }
                }
            }
        }
        throw new ApiProblem('not_found', 404);
    }

    private function file(string $ownerKey, string $assetId, string $versionId): BinaryFileResponse
    {
        try {
            $media = $this->catalogue->resolve($assetId, $versionId, $ownerKey);
        } catch (ApiProblem) {
            throw new ApiProblem('not_found', 404);
        }

        // BinaryFileResponse marks files public by default after applying headers.
        $response = response()->file($media['path'], ['Content-Type' => $media['mime'], 'X-Content-Type-Options' => 'nosniff']);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
