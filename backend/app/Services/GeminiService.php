<?php

namespace App\Services;

use GuzzleHttp\Client;

class GeminiService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com',
        ]);
    }

    public function embed(string $text): array
    {
        $response = $this->client->post('/v1beta/models/text-embedding-004:embedContent', [
            'query' => ['key' => config('services.gemini.key')],
            'json'  => [
                'model'   => 'models/text-embedding-004',
                'content' => [
                    'parts' => [['text' => $text]],
                ],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['embedding']['values'];
    }
}
