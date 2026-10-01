<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Application\Shared\ApiProblem;
use App\Application\Shared\OwnerMutation;
use App\Application\Shared\OwnerQuota;
use App\Models\BlockTemplateRecord;
use App\Models\GuestWorkspaceClaim;
use App\Models\LessonMaterial;
use App\Models\MediaAsset;
use App\Models\MediaOwnerQuota;
use App\Models\MediaVersion;
use App\Models\TeachingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

final class GuestClaimService
{
    public function preview(Request $request): array
    {
        $user = $request->user();
        $target = AccountIdentity::owner($user);
        $source = $this->source($request);
        $receipt = $source !== null ? GuestWorkspaceClaim::query()->find($source) : null;
        $available = $source !== null && $receipt === null;
        $mine = $receipt?->target_user_id === $user->id;
        $counts = $available ? $this->counts($source) : ($mine ? $receipt->result['counts'] : $this->emptyCounts());
        $bytes = $available ? $this->bytes($source) : ($mine ? $receipt->result['bytes'] : 0);
        $quota = OwnerQuota::present($target);

        return ['claim' => ['available' => $available, 'status' => $available ? 'pending' : ($mine ? 'claimed' : 'none'), 'counts' => $counts, 'bytes' => $bytes],
            'quota' => ['usedBytes' => $quota['usedBytes'], 'limitBytes' => $quota['limitBytes'], 'afterClaimBytes' => $quota['usedBytes'] + ($available ? $bytes : 0)],
            'verificationRequired' => ! $user->hasVerifiedEmail()];
    }

    public function claim(Request $request): array
    {
        AuthInput::parse($request->all(), []);
        $user = $request->user();
        if (! $user->hasVerifiedEmail()) {
            throw new ApiProblem('verification_required', 403);
        }
        $target = AccountIdentity::owner($user);
        $source = $this->source($request) ?? throw new ApiProblem('claim_unavailable', 409);
        if ($source === $target || User::query()->where('owner_key', $source)->exists()) {
            throw new ApiProblem('claim_unavailable', 409);
        }

        try {
            return OwnerMutation::transaction([$source, $target], function () use ($source, $target, $user): array {
                $receipt = GuestWorkspaceClaim::query()->find($source);
                if ($receipt !== null) {
                    if ($receipt->target_user_id !== $user->id) {
                        throw new ApiProblem('claim_unavailable', 409);
                    }

                    return ['claim' => $receipt->result];
                }
                $sourceQuota = MediaOwnerQuota::query()->findOrFail($source);
                $targetQuota = MediaOwnerQuota::query()->findOrFail($target);
                $bytes = $this->bytes($source);
                $targetBytes = $this->bytes($target);
                if ($sourceQuota->used_bytes !== $bytes || $targetQuota->used_bytes !== $targetBytes) {
                    throw new ApiProblem('claim_failed', 503);
                }
                if ($targetBytes + $bytes > OwnerQuota::limit($target)) {
                    throw new ApiProblem('quota_exceeded', 422);
                }
                $counts = $this->counts($source);
                foreach ([LessonMaterial::class, BlockTemplateRecord::class, MediaAsset::class, TeachingSession::class] as $model) {
                    $model::query()->where('owner_key', $source)->update(['owner_key' => $target]);
                }
                $targetQuota->used_bytes += $bytes;
                $targetQuota->save();
                $sourceQuota->used_bytes = 0;
                $sourceQuota->save();
                $result = ['status' => 'claimed', 'counts' => $counts, 'bytes' => $bytes];
                GuestWorkspaceClaim::query()->create(['source_owner_key' => $source, 'target_user_id' => $user->id, 'target_owner_key' => $target, 'result' => $result]);

                return ['claim' => $result];
            }, true);
        } catch (Throwable $failure) {
            throw $failure instanceof ApiProblem ? $failure : new ApiProblem('claim_failed', 503);
        }
    }

    private function source(Request $request): ?string
    {
        $key = $request->session()->get(AuthService::GUEST_PROOF);

        return is_string($key) && Str::isUuid($key) && ! User::query()->where('owner_key', strtolower($key))->exists()
            ? strtolower($key) : null;
    }

    private function counts(string $key): array
    {
        return ['lessons' => LessonMaterial::query()->where('owner_key', $key)->count(),
            'templates' => BlockTemplateRecord::query()->where('owner_key', $key)->count(),
            'mediaAssets' => MediaAsset::query()->where('owner_key', $key)->count(),
            'sessions' => TeachingSession::query()->where('owner_key', $key)->count()];
    }

    private function bytes(string $key): int
    {
        return (int) MediaVersion::query()->join('media_assets', 'media_versions.media_asset_id', '=', 'media_assets.id')
            ->where('media_assets.owner_key', $key)->sum('media_versions.bytes');
    }

    private function emptyCounts(): array
    {
        return ['lessons' => 0, 'templates' => 0, 'mediaAssets' => 0, 'sessions' => 0];
    }
}
