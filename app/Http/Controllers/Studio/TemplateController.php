<?php

declare(strict_types=1);

namespace App\Http\Controllers\Studio;

use App\Application\Library\TemplateLibraryService;
use App\Application\Shared\GuestIdentity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TemplateController extends Controller
{
    public function __construct(private TemplateLibraryService $library, private GuestIdentity $identity) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:200'], 'tag' => ['sometimes', 'nullable', 'string', 'max:50'],
            'type' => ['sometimes', 'nullable', 'string', 'max:128'], 'locale' => ['sometimes', 'nullable', 'string', 'max:35'],
            'archived' => ['sometimes', 'in:0,1'],
        ]);

        return response()->json(['templates' => $this->library->listOwned($this->identity->key($request), array_filter($filters, fn ($value) => $value !== null))]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['template' => $this->library->present($this->library->findOwned($this->identity->key($request), $id))]);
    }

    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'lessonId' => ['required', 'uuid'], 'expectedLessonRevision' => ['required', 'integer:strict', 'min:1'], 'blockId' => ['required', 'string', 'max:128'],
        ]);
        $record = $this->library->createFromLesson($this->identity->key($request), $input['lessonId'], $input['expectedLessonRevision'], $input['blockId'], $request->all());

        return response()->json(['template' => $this->library->present($record)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $input = $request->validate([
            'expectedRevision' => ['required', 'integer:strict', 'min:1'], 'locales' => ['required', 'array'],
            'defaultLocale' => ['required', 'string', 'max:35'], 'block' => ['required', 'array'],
        ]);
        $record = $this->library->update($this->identity->key($request), $id, $input['expectedRevision'], $input['locales'], $input['defaultLocale'], $input['block'], $request->all());

        return response()->json(['template' => $this->library->present($record)]);
    }

    public function instantiate(Request $request, string $id, string $versionId): JsonResponse
    {
        $input = $request->validate(['locales' => ['required', 'array']]);

        return response()->json(['block' => $this->library->instantiate($this->identity->key($request), $id, $versionId, $input['locales'])]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $input = $request->validate(['expectedRevision' => ['required', 'integer:strict', 'min:1'], 'archived' => ['required', 'boolean:strict']]);
        $record = $this->library->archive($this->identity->key($request), $id, $input['expectedRevision'], $input['archived']);

        return response()->json(['template' => $this->library->present($record)]);
    }
}
