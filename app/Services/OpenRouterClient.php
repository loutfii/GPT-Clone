<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OpenRouterClient
{
    protected string $base;
    protected string $key;
    protected string $referer;
    protected string $title;

    public function __construct()
    {
        $cfg = config('services.openrouter');
        $this->base    = rtrim($cfg['base'] ?? '', '/');
        $this->key     = (string) ($cfg['key'] ?? '');
        $this->referer = (string) ($cfg['referer'] ?? '');
        $this->title   = (string) ($cfg['title'] ?? '');

        if ($this->key === '') {
            throw ValidationException::withMessages([
                'openrouter' => 'OPENROUTER_API_KEY manquante dans .env',
            ]);
        }
    }

    protected function http(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->key,
            'HTTP-Referer'  => $this->referer,
            'X-Title'       => $this->title,
            'Accept'        => 'application/json',
        ])->timeout(60);
    }

    /**
     * Appel "non-stream" simple pour tester le chat.
     * $messages: array de tableaux [role=>..., content=>...]
     */
    public function chat(string $model, array $messages): array
    {
        $url = $this->base.'/chat/completions';

        $res = $this->http()->post($url, [
            'model'    => $model,
            'messages' => $messages,
        ])->throw();

        return $res->json();
    }

    public function chatStream(string $model, array $messages)
    {
        $url = $this->base . '/chat/completions';

        $res = $this->http()
            ->withOptions(['stream' => true]) // important pour recevoir un flux
            ->post($url, [
                'model'    => $model,
                'messages' => $messages,
                'stream'   => true, // active le streaming côté OpenRouter
            ])
            ->throw();

        // Renvoie le corps PSR-7 lisible en continu
        return $res->toPsrResponse()->getBody();
    }


    public function models(): array
    {
        $url = $this->base . '/models';

        $res = $this->http()->get($url)->throw()->json();

        // $res ressemble à: ['data' => [ ['id' => 'openai/gpt-4o-mini', 'name' => 'GPT-4o mini', ...], ... ]]
        return $res;
    }
}
