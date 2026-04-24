<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemoryRequest;
use App\Models\Memory;
use App\Services\ClaudeService;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

class MemoryController extends Controller
{
    public function __construct(
        private ClaudeService $claude,
        private GeminiService $gemini,
    ) {}

    public function store(StoreMemoryRequest $request): JsonResponse
    {
        $type = $request->input('type');

        $memory = Memory::create([
            'user_id'   => $request->user()->id,
            'type'      => $type,
            'content'   => $type === 'text' ? $request->input('content') : null,
            'file_path' => null,
        ]);

        $extractedText = match ($type) {
            'text'  => $request->input('content'),
            'image' => $this->extractFromImage($request, $memory),
            'pdf'   => $this->extractFromPdf($request, $memory),
        };

        $embedding = $this->gemini->embed($extractedText);

        DB::statement(
            'UPDATE memories SET embedding = ?::vector WHERE id = ?',
            [json_encode($embedding), $memory->id]
        );

        return response()->json($memory->fresh(), 201);
    }

    private function extractFromImage(StoreMemoryRequest $request, Memory $memory): string
    {
        $file     = $request->file('file');
        $path     = $file->store('memories/images', 'local');
        $mimeType = $file->getMimeType();
        $base64   = base64_encode(Storage::disk('local')->get($path));

        $memory->update(['file_path' => $path]);

        return $this->claude->extractTextFromImage($base64, $mimeType);
    }

    private function extractFromPdf(StoreMemoryRequest $request, Memory $memory): string
    {
        $file = $request->file('file');
        $path = $file->store('memories/pdfs', 'local');

        $memory->update(['file_path' => $path]);

        $parser   = new PdfParser();
        $pdf      = $parser->parseFile(Storage::disk('local')->path($path));

        return $pdf->getText();
    }
}
