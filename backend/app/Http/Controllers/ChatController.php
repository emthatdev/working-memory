<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatRequest;
use App\Models\Chat;
use App\Models\Memory;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    const SYSTEM_PROMPT = <<<'PROMPT'
You are Loci OS, a personal AI memory palace. The user has stored memories, notes, and ideas. Answer the user's question using only the memories provided as context. Be conversational and helpful.
PROMPT;

    public function __construct(
        private GeminiService $gemini,
    ) {}

    public function store(StoreChatRequest $request): JsonResponse
    {
        $userMessage = $request->input('message');
        $userId      = $request->user()->id;

        $embedding = $this->gemini->embed($userMessage);
        $memories  = Memory::semanticSearch($userId, $embedding);

        $contextLines = array_map(
            fn ($m) => '- ' . ($m->content ?? "({$m->type} file: {$m->file_path})"),
            $memories
        );

        $prompt = implode("\n", [
            'Here are my relevant memories:',
            implode("\n", $contextLines),
            '',
            "My question: {$userMessage}",
        ]);

        $reply = $this->gemini->chat(self::SYSTEM_PROMPT, $prompt);

        Chat::insert([
            ['user_id' => $userId, 'role' => 'user',      'message' => $userMessage],
            ['user_id' => $userId, 'role' => 'assistant',  'message' => $reply],
        ]);

        return response()->json(['message' => $reply]);
    }
}
