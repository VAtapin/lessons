<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Account\AccountIdentity;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\OwnerMutation;
use App\Models\CatalogEntry;
use App\Models\CatalogSubmission;
use App\Models\LessonMaterial;
use App\Models\User;

final readonly class CatalogReviewService
{
    public function __construct(private AdminAccess $access, private CatalogService $catalog, private TaxonomyService $taxonomy) {}

    public function submit(User $author, string $lessonId, string $versionId, int $expectedRevision, string $slug, array $metadata): array
    {
        $author = $this->access->account($author);
        $owner = AccountIdentity::owner($author);

        return OwnerMutation::transaction([$owner, TaxonomyService::MUTEX], function () use ($owner, $lessonId, $versionId, $expectedRevision, $slug, $metadata): array {
            $material = LessonMaterial::query()->where('owner_key', $owner)->lockForUpdate()->find($lessonId) ?? throw new ApiProblem('not_found', 404);
            if ($material->archived) {
                throw new ApiProblem('lesson_in_trash', 409);
            }
            if ($material->revision !== $expectedRevision) {
                throw new ApiProblem('revision_conflict', 409);
            }
            $version = $material->versions()->find($versionId) ?? throw new ApiProblem('not_found', 404);
            $this->catalog->validatePublication($version, $metadata);
            $this->taxonomy->assertMetadata($metadata);
            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 120 || in_array($slug, ['taxonomy', 'templates'], true)) {
                throw new ApiProblem('invalid_catalog_entry', 422);
            }
            if (CatalogEntry::query()->where('slug', $slug)->exists()) {
                throw new ApiProblem('catalog_slug_conflict', 409);
            }
            $existing = CatalogSubmission::query()->where('owner_key', $owner)->where('lesson_version_id', $versionId)->where('slug', $slug)->first();
            if ($existing !== null) {
                if ($existing->metadata !== $metadata) {
                    throw new ApiProblem('submission_immutable', 409);
                }

                return $this->present($existing);
            }
            $submission = CatalogSubmission::create(['owner_key' => $owner, 'lesson_version_id' => $versionId, 'slug' => $slug, 'metadata' => $metadata, 'status' => 'pending', 'revision' => 1]);

            return $this->present($submission);
        });
    }

    public function authorList(User $author): array
    {
        $author = $this->access->account($author);

        return CatalogSubmission::query()->where('owner_key', AccountIdentity::owner($author))->with(['version', 'entry'])->orderByDesc('created_at')->orderBy('id')->get()->map($this->present(...))->all();
    }

    public function adminList(User $admin, ?string $status = null): array
    {
        $this->access->require($admin);
        $query = CatalogSubmission::query()->with(['version', 'entry'])->orderBy('created_at')->orderBy('id');
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get()->map($this->present(...))->all();
    }

    public function detail(User $admin, string $id): array
    {
        $this->access->require($admin);
        $submission = CatalogSubmission::query()->with(['version', 'entry'])->find($id) ?? throw new ApiProblem('not_found', 404);

        return ['submission' => $this->present($submission), 'document' => $submission->version->document];
    }

    public function review(User $admin, string $id, int $revision, string $decision, ?string $reason): array
    {
        $admin = $this->access->require($admin);
        if (! in_array($decision, ['approve', 'return'], true) || ($decision === 'return' && (! is_string($reason) || trim($reason) === '' || mb_strlen($reason) > 2000))) {
            throw new ApiProblem('invalid_catalog_entry', 422);
        }

        return OwnerMutation::transaction([TaxonomyService::MUTEX], function () use ($admin, $id, $revision, $decision, $reason): array {
            $submission = CatalogSubmission::query()->lockForUpdate()->find($id) ?? throw new ApiProblem('not_found', 404);
            if ($submission->revision !== $revision) {
                throw new ApiProblem('revision_conflict', 409);
            }
            if ($submission->status !== 'pending') {
                throw new ApiProblem('invalid_state', 409);
            }
            if ($decision === 'approve') {
                $this->taxonomy->assertMetadata($submission->metadata);
                if (CatalogEntry::query()->where('slug', $submission->slug)->exists()) {
                    throw new ApiProblem('catalog_slug_conflict', 409);
                }
                $entry = $this->catalog->approve($submission->version, $submission->metadata, 'account:'.$admin->id, $submission->slug);
                $submission->catalog_entry_id = $entry->id;
            }
            $submission->status = $decision === 'approve' ? 'approved' : 'returned';
            $submission->reason = $decision === 'return' ? $reason : null;
            $submission->reviewed_by = $admin->id;
            $submission->reviewed_at = now();
            $submission->revision++;
            $submission->save();

            return $this->present($submission->load('entry'));
        });
    }

    public function entries(User $admin): array
    {
        $this->access->require($admin);

        return CatalogEntry::query()->orderBy('slug')->get()->map($this->entry(...))->all();
    }

    public function visibility(User $admin, string $slug, int $revision, bool $visible): array
    {
        $this->access->require($admin);

        return OwnerMutation::transaction([TaxonomyService::MUTEX], function () use ($slug, $revision, $visible): array {
            $entry = CatalogEntry::query()->where('slug', $slug)->lockForUpdate()->first() ?? throw new ApiProblem('not_found', 404);
            if ((int) $entry->revision !== $revision) {
                throw new ApiProblem('revision_conflict', 409);
            }
            if ($visible && ($entry->approved_at === null || $entry->approved_by === null)) {
                throw new ApiProblem('invalid_state', 409);
            }
            if ($visible) {
                $this->catalog->validatePublication($entry->version, $entry->metadata);
            }
            $entry->status = $visible ? 'approved' : 'retracted';
            $entry->revision++;
            $entry->save();

            return $this->entry($entry);
        });
    }

    private function entry(CatalogEntry $entry): array
    {
        return ['slug' => $entry->slug, 'versionId' => $entry->lesson_version_id, 'revision' => (int) $entry->revision, 'status' => $entry->status, 'metadata' => $entry->metadata];
    }

    private function present(CatalogSubmission $submission): array
    {
        return ['id' => $submission->id, 'lessonId' => $submission->version->lesson_material_id, 'versionId' => $submission->lesson_version_id,
            'slug' => $submission->slug, 'revision' => $submission->revision, 'status' => $submission->status, 'reason' => $submission->reason,
            'metadata' => $submission->metadata, 'catalogSlug' => $submission->entry?->slug,
            'submittedAt' => $submission->created_at->toIso8601String(), 'reviewedAt' => $submission->reviewed_at?->toIso8601String()];
    }
}
