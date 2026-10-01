<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Shared\ApiProblem;
use App\Domain\Lessons\TeacherDocumentation;

/** Explicit reusable files supplied for publication; no arbitrary paths or remote downloads. */
final class DocumentationFiles
{
    public function resolve(string $id): array
    {
        $file = config('lesson-files.'.$id);
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
        $result['files'] = array_map(function (array $reference): array {
            $file = $this->resolve($reference['fileId']);
            if ($file['kind'] !== $reference['kind'] || $file['locale'] !== $reference['locale']) {
                throw new ApiProblem('invalid_document', 422);
            }

            return [...$reference, 'url' => $file['url'], 'bytes' => $file['bytes']];
        }, $result['files']);

        return $result;
    }
}
