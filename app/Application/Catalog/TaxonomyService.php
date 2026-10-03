<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Shared\ApiProblem;
use App\Application\Shared\OwnerMutation;
use App\Models\CatalogTerm;
use App\Models\User;

final readonly class TaxonomyService
{
    public const MUTEX = 'd30958e6-e767-45d4-a99d-795844913736';

    public function __construct(private AdminAccess $access) {}

    public function listing(?string $locale = null): array
    {
        $query = CatalogTerm::query()->orderBy('kind')->orderBy('key');
        $query->where(fn ($query) => $query->where('kind', '!=', 'format')->orWhere('key', '!=', 'questions'));
        if ($locale !== null) {
            $query->where('active', true);
        }

        return $query->get()->map(fn ($term) => $this->present($term, $locale))->all();
    }

    public function save(User $actor, array $data, ?string $id = null, ?int $revision = null): array
    {
        $this->access->require($actor);
        if (($data['kind'] ?? null) === 'format' && ($data['key'] ?? null) === 'questions') {
            throw new ApiProblem('invalid_catalog_entry', 422);
        }
        if (! in_array($data['kind'] ?? null, ['age', 'topic', 'audience', 'format'], true)
            || ! is_string($data['key'] ?? null) || ! preg_match('/^[a-z0-9+-]{1,80}$/D', $data['key']) || ! is_bool($data['active'] ?? null)) {
            throw new ApiProblem('invalid_catalog_entry', 422);
        }
        self::labels($data['labels'] ?? null);

        return OwnerMutation::transaction([self::MUTEX], function () use ($data, $id, $revision): array {
            $term = $id === null ? new CatalogTerm(['revision' => 1]) : CatalogTerm::query()->lockForUpdate()->find($id) ?? throw new ApiProblem('not_found', 404);
            if ($id !== null && $term->revision !== $revision) {
                throw new ApiProblem('revision_conflict', 409);
            }
            if ($id !== null && ($term->kind !== $data['kind'] || $term->key !== $data['key'])) {
                throw new ApiProblem('catalog_key_immutable', 422);
            }
            if ($id === null && CatalogTerm::query()->where('kind', $data['kind'])->where('key', $data['key'])->exists()) {
                throw new ApiProblem('catalog_key_conflict', 409);
            }
            $term->fill($data);
            if ($id !== null) {
                $term->revision++;
            }
            $term->save();

            return $this->present($term);
        });
    }

    public function assertMetadata(array $metadata): void
    {
        foreach (['age', 'topic', 'audience', 'format'] as $kind) {
            foreach ($metadata[$kind] ?? [] as $key) {
                if (! CatalogTerm::query()->where('kind', $kind)->where('key', $key)->where('active', true)->exists()) {
                    throw new ApiProblem('catalog_term_unavailable', 422);
                }
            }
        }
    }

    public static function labels(mixed $labels): void
    {
        if (! is_array($labels) || count($labels) !== 2) {
            throw new ApiProblem('invalid_catalog_entry', 422);
        }
        foreach (['ru', 'de'] as $locale) {
            if (! is_string($labels[$locale] ?? null) || trim($labels[$locale]) === '' || mb_strlen($labels[$locale]) > 200) {
                throw new ApiProblem('invalid_catalog_entry', 422);
            }
        }
    }

    private function present(CatalogTerm $term, ?string $locale = null): array
    {
        $data = ['id' => $term->id, 'kind' => $term->kind, 'key' => $term->key, 'revision' => $term->revision, 'active' => $term->active];

        $label = $locale !== null && $term->kind === 'format' && $term->key === 'notes' && ($term->labels[$locale] ?? '') === 'Entwurf'
            ? __('interface.format_notes', [], $locale) : ($term->labels[$locale] ?? '');

        return $locale === null ? $data + ['labels' => $term->labels] : $data + ['label' => $label];
    }
}
