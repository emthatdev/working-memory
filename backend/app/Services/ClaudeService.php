<?php

namespace App\Services;

use GuzzleHttp\Client;

class ClaudeService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com',
            'headers' => [
                'x-api-key'         => config('services.claude.key'),
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
        ]);
    }

    public function extractTextFromImage(string $base64Data, string $mimeType): string
    {
        $response = $this->client->post('/v1/messages', [
            'json' => [
                'model'      => 'claude-sonnet-4-20250514',
                'max_tokens' => 4096,
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type'   => 'image',
                                'source' => [
                                    'type'       => 'base64',
                                    'media_type' => $mimeType,
                                    'data'       => $base64Data,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Extract all text content visible in this image. Return only the extracted text with no additional commentary.',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['content'][0]['text'];
    }
}
