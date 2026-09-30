<?php

declare(strict_types=1);

namespace App\Application\Shared;

use Illuminate\Support\Facades\Validator;

final class LibraryMetadata
{
    public function parse(array $data): array
    {
        return Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'tags' => ['present', 'array', 'list', 'max:20'],
            'tags.*' => ['required', 'string', 'max:50', 'distinct:strict'],
            'author' => ['required', 'string', 'max:500'],
            'source' => ['required', 'string', 'max:2000'],
            'rightsBasis' => ['required', 'in:self_created,permission,public_domain,licensed,ai_generated'],
            'usageRights' => ['required', 'string', 'max:2000'],
        ])->validate();
    }
}
