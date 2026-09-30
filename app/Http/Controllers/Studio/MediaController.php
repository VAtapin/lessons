<?php

declare(strict_types=1);

namespace App\Http\Controllers\Studio;

use App\Application\Media\MediaLibraryService;
use App\Application\Shared\GuestIdentity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use JsonException;

final class MediaController extends Controller
{
    public function __construct(private readonly MediaLibraryService $library, private readonly GuestIdentity $identity) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['sometimes', 'nullable', 'string', 'max:200'], 'tag' => ['sometimes', 'nullable', 'string', 'max:50'],
            'archived' => ['sometimes', 'in:0,1']]);

        return response()->json($this->library->list($this->identity->key($request), $filters));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file']]);
        $asset = $this->library->upload($this->identity->key($request), $request->file('file'), $this->metadata($request));

        return response()->json(['asset' => $this->library->present($asset)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['asset' => $this->library->detail($this->identity->key($request), $id)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $owner = $this->identity->key($request);
        $this->library->findOwned($owner, $id);
        $asset = $this->library->update($owner, $id, $this->revision($request), $this->metadata($request));

        return response()->json(['asset' => $this->library->present($asset)]);
    }

    public function replace(Request $request, string $id): JsonResponse
    {
        $owner = $this->identity->key($request);
        $this->library->findOwned($owner, $id);
        $request->validate(['file' => ['required', 'file']]);
        $asset = $this->library->replace($owner, $id, $this->revision($request), $request->file('file'));

        return response()->json(['asset' => $this->library->present($asset)]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $owner = $this->identity->key($request);
        $this->library->findOwned($owner, $id);
        if (! is_bool($request->input('archived'))) {
            throw ValidationException::withMessages(['archived' => __('validation.boolean', ['attribute' => 'archived'])]);
        }
        $asset = $this->library->archive($owner, $id, $this->revision($request), $request->boolean('archived'));

        return response()->json(['asset' => $this->library->present($asset)]);
    }

    private function metadata(Request $request): array
    {
        $metadata = $request->only(['title', 'tags', 'author', 'source', 'rightsBasis', 'usageRights']);
        if (! $request->isJson() && isset($metadata['tags']) && is_string($metadata['tags'])) {
            try {
                $metadata['tags'] = json_decode($metadata['tags'], true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages(['tags' => __('validation.array', ['attribute' => 'tags'])]);
            }
        }

        return $metadata;
    }

    private function revision(Request $request): int
    {
        $value = $request->input('expectedRevision');
        if (! $request->isJson() && is_string($value) && preg_match('/\A[1-9][0-9]*\z/', $value)) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
        }
        if (! is_int($value) || $value < 1) {
            throw ValidationException::withMessages(['expectedRevision' => __('validation.integer', ['attribute' => 'expectedRevision'])]);
        }

        return $value;
    }
}
