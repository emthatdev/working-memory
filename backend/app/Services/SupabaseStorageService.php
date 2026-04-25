<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SupabaseStorageService
{
    private string $url;
    private string $key;
    private string $bucket;

    public function __construct()
    {
        $this->url    = config('services.supabase.url');
        $this->key    = config('services.supabase.service_key');
        $this->bucket = config('services.supabase.storage_bucket');
    }

    public function upload(string $contents, string $mimeType, string $extension): string
    {
        $path = 'images/' . Str::uuid() . '.' . $extension;

        Http::withHeaders([
            'Authorization' => "Bearer {$this->key}",
            'Content-Type'  => $mimeType,
        ])->withBody($contents, $mimeType)
          ->post("{$this->url}/storage/v1/object/{$this->bucket}/{$path}");

        return "{$this->url}/storage/v1/object/public/{$this->bucket}/{$path}";
    }

    public function delete(string $publicUrl): void
    {
        $prefix = "{$this->url}/storage/v1/object/public/{$this->bucket}/";
        $path   = Str::after($publicUrl, $prefix);

        Http::withHeaders(['Authorization' => "Bearer {$this->key}"])
            ->delete("{$this->url}/storage/v1/object/{$this->bucket}", ['prefixes' => [$path]]);
    }
}
