<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchMemoryRequest;
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

    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $memories = Memory::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($memories);
    }

    public function store(StoreMemoryRequest $request): JsonResponse
    {
        $type     = $request->input('type');
        $filePath = null;

        // 1. Store file and extract text — no DB writes yet
        $extractedText = match ($type) {
            'text'  => $request->input('content'),
            'image' => $this->storeAndExtractImage($request, $filePath),
            'pdf'   => $this->storeAndExtractPdf($request, $filePath),
        };

        // 2. Generate embedding before touching the DB; clean up file on failure
        try {
            $embedding = $this->gemini->embed($extractedText);
        } catch (\Throwable $e) {
            if ($filePath) {
                Storage::disk('local')->delete($filePath);
            }
            throw $e;
        }

        // 3. Persist atomically — record creation + embedding in one transaction
        $memory = DB::transaction(function () use ($request, $type, $filePath, $embedding) {
            $memory = Memory::create([
                'user_id'   => $request->user()->id,
                'type'      => $type,
                'content'   => $type === 'text' ? $request->input('content') : null,
                'file_path' => $filePath,
            ]);

            DB::statement(
                'UPDATE memories SET embedding = ?::vector WHERE id = ?',
                [json_encode($embedding), $memory->id]
            );

            return $memory->fresh();
        });

        return response()->json($memory, 201);
    }

    public function search(SearchMemoryRequest $request): JsonResponse
    {
        $embedding = $this->gemini->embed($request->input('query'));
        $results   = Memory::semanticSearch($request->user()->id, $embedding);

        return response()->json($results);
    }

    private function storeAndExtractImage(StoreMemoryRequest $request, ?string &$filePath): string
    {
        $file     = $request->file('file');
        $filePath = $file->store('memories/images', 'local');
        $mimeType = $file->getMimeType();
        $base64   = base64_encode(Storage::disk('local')->get($filePath));

        return $this->claude->extractTextFromImage($base64, $mimeType);
    }

    private function storeAndExtractPdf(StoreMemoryRequest $request, ?string &$filePath): string
    {
        $file     = $request->file('file');
        $filePath = $file->store('memories/pdfs', 'local');

        $parser = new PdfParser();
        $pdf    = $parser->parseFile(Storage::disk('local')->path($filePath));

        return $pdf->getText();
    }
}
