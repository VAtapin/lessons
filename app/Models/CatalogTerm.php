<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class CatalogTerm extends Model
{
    use HasUuids;

    protected $fillable = ['kind', 'key', 'labels', 'active', 'revision'];

    protected function casts(): array
    {
        return ['labels' => 'array', 'active' => 'boolean', 'revision' => 'integer'];
    }
}
