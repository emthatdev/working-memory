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
You are Loci OS, a personal memory palace assistant. You ONLY answer questions using the memories provided to you as context. If the answer is not found in the provided memories, you must say 'I couldn't find anything about that in your memory palace.' NEVER use your own knowledge or make up information. ONLY speak from the user's memories.
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

        return response()->json([
            'message'    => $reply,
            'memory_ids' => array_column($memories, 'id'),
        ]);
    }
}
