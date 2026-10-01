<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Catalog\DocumentationFiles;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DocumentationFileController extends Controller
{
    public function __invoke(string $id, DocumentationFiles $files): BinaryFileResponse
    {
        $file = $files->resolve($id);

        return response()->download($file['path'], $file['downloadName'], ['Content-Type' => $file['mime'], 'X-Content-Type-Options' => 'nosniff']);
    }
}
