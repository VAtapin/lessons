<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Shared\ApiProblem;
use App\Domain\Lessons\TeacherDocumentation;
use Illuminate\Support\Str;

/** Explicit reusable files supplied for publication; no arbitrary paths or remote downloads. */
final class DocumentationFiles
{
    public function resolve(string $id): array
    {
        $file = config('lesson-files.'.$id) ?? config('german-lesson-files.files.'.$id);
        if (! is_array($file) || ! is_string($file['path'] ?? null)) {
            throw new ApiProblem('not_found', 404);
        }
        $root = realpath(base_path('assets/lessons'));
        $path = realpath(base_path($file['path']));
        if ($root === false || $path === false || ! str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $root).'/')
            || ! is_file($path) || is_link(base_path($file['path'])) || ! hash_equals($file['sha256'], (string) hash_file('sha256', $path))) {
            throw new ApiProblem('not_found', 404);
        }

        return [...$file, 'path' => $path, 'url' => '/lesson-files/'.rawurlencode($id), 'bytes' => filesize($path)];
    }

    public function assertDocumentation(?TeacherDocumentation $documentation): void
    {
        foreach ($documentation?->toArray()['files'] ?? [] as $reference) {
            $file = $this->resolve($reference['fileId']);
            if ($file['kind'] !== $reference['kind'] || $file['locale'] !== $reference['locale']) {
                throw new ApiProblem('invalid_document', 422);
            }
        }
    }

    public function present(TeacherDocumentation $documentation, string $locale): array
    {
        $result = $documentation->project($locale);
        $result['files'] = array_map(function (array $reference) use ($locale): array {
            $file = $this->resolve($reference['fileId']);
            if ($file['kind'] !== $reference['kind'] || $file['locale'] !== $reference['locale']) {
                throw new ApiProblem('invalid_document', 422);
            }

            return [...$reference, 'url' => $file['url'], 'bytes' => $file['bytes'], ...isset($file['labelKey']) ? ['label' => __('studio.'.$file['labelKey'], [], $locale)] : []];
        }, $this->localizedReferences($documentation, $locale));
        if ($locale === 'de' && $result['files'] !== []) {
            $result['plan'] = $result['plan'] === null ? null : str_replace(
                config('german-lesson-files.obsoleteDownloadNotice', ''), '', $result['plan']
            );
        }

        // Render only at the presentation boundary; immutable sources remain Markdown.
        $result['planHtml'] = $result['plan'] === null ? null : Str::markdown($result['plan'], [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);

        return $result;
    }

    /** Attach published translations without rewriting immutable lesson snapshots. */
    public function localizedReferences(TeacherDocumentation $documentation, string $locale): array
    {
        $references = $documentation->toArray()['files'];
        $translated = [];
        $sets = [];
        if ($locale === 'de') {
            foreach ($references as $reference) {
                $set = config('german-lesson-files.sourceFiles.'.$reference['fileId']);
                if (is_string($set)) {
                    $sets[$set] = true;
                }
            }
            foreach (array_keys($sets) as $set) {
                foreach (config('german-lesson-files.sets.'.$set, []) as $id) {
                    $file = config('german-lesson-files.files.'.$id);
                    $translated[$id] = ['fileId' => $id, 'kind' => $file['kind'], 'locale' => 'de'];
                }
            }
        }
        foreach ($references as $reference) {
            if ($reference['locale'] === $locale && ! isset($sets[config('german-lesson-files.sourceFiles.'.$reference['fileId'], '')])) {
                $translated[$reference['fileId']] = $reference;
            }
        }

        return array_values($translated);
    }
}
