<?php

declare(strict_types=1);

namespace App\Application\Shared;

use Illuminate\Support\Facades\Validator;

final class LibraryMetadata
{
    public function parse(array $data): array
    {
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'tags' => ['present', 'array', 'list', 'max:20'],
            'tags.*' => ['required', 'string', 'max:50', 'distinct:strict'],
            'author' => ['sometimes', 'nullable', 'string', 'max:500'],
            'source' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'rightsBasis' => ['sometimes', 'in:unspecified,self_created,permission,public_domain,licensed,ai_generated'],
            'usageRights' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ])->validate();

        foreach (['author', 'source', 'usageRights'] as $field) {
            $validated[$field] = $validated[$field] ?? '';
        }
        $validated['rightsBasis'] = $validated['rightsBasis'] ?? 'unspecified';

        return $validated;
    }
}
