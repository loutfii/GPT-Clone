<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\UserSetting; // pour lire les préférences utilisateur
use App\Services\OpenRouterClient;
use Illuminate\Http\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;



class ChatController extends Controller
{
    /**
     * Construit le prompt système à partir des préférences utilisateur.
     * - Si "custom_system" est présent, il prime.
     * - Sinon on compose: You are a helpful assistant. + Tone + Style + Context
     */
    private function buildSystemPromptForUser(int $userId): string
    {
        $settings = \App\Models\UserSetting::where('user_id', $userId)->first();

        // tableau JSON si présent, sinon []
        $json = $settings?->preferences ?? [];

        // colonnes prioritaire, fallback JSON, fallback défaut
        $custom = trim((string)($settings?->custom_system ?? ($json['custom_system'] ?? '')));
        if ($custom !== '') {
            return $custom; // "custom_system" prime
        }

        $tone    = trim((string)($settings?->tone    ?? ($json['tone']    ?? 'neutral')));
        $style   = trim((string)($settings?->style   ?? ($json['style']   ?? 'concise')));
        $context = trim((string)($settings?->context ?? ($json['context'] ?? '')));

        $parts = [];
        $parts[] = 'You are a helpful assistant.';
        if ($tone !== '')  { $parts[] = "Tone: {$tone}."; }
        if ($style !== '') { $parts[] = "Writing style: {$style}."; }
        if ($context !== '') { $parts[] = "Context: {$context}"; }

        return implode(' ', $parts);
    }

    /**
     * Affiche la page du chat avec le sélecteur de modèles.
     */
    public function index(OpenRouterClient $client)
    {
        try {
            $modelsResponse = $client->models();

            $models = collect($modelsResponse['data'] ?? [])
                ->map(function ($m) {
                    $id    = $m['id']   ?? ($m['name'] ?? '');
                    $label = $m['name'] ?? $id;
                    return ['id' => $id, 'label' => $label];
                })
                ->filter(fn ($m) => $m['id'] !== '')
                ->values()
                ->all();

            $preferred = [
                'google/gemini-2.5-flash',
                'openai/gpt-4o',
                'openai/gpt-4o-mini',
                'anthropic/claude-3.5-sonnet',
                'openrouter/auto',
            ];
            usort($models, function ($a, $b) use ($preferred) {
                $pa = array_search($a['id'], $preferred, true);
                $pb = array_search($b['id'], $preferred, true);
                $pa = $pa === false ? PHP_INT_MAX : $pa;
                $pb = $pb === false ? PHP_INT_MAX : $pb;
                return $pa <=> $pb;
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to load models from OpenRouter', ['e' => $e]);
            $models = [
                ['id' => 'openai/gpt-4o-mini', 'label' => 'GPT-4o mini'],
                ['id' => 'openrouter/auto',    'label' => 'OpenRouter Auto'],
            ];
        }

        $conversations = Conversation::where('user_id', Auth::id())
            ->latest('updated_at')
            ->get(['id','title','updated_at']);

        return Inertia::render('Chat', [
            'models'        => $models,
            'conversations' => $conversations,
        ]);
    }

    public function send(Request $request, OpenRouterClient $client)
    {
        $validated = $request->validate([
            'model'           => ['required','string','max:200'],
            'content'         => ['required','string'],
            'conversation_id' => ['nullable','integer'],
        ]);

        // 1) Récupère ou crée la conversation
        if (!empty($validated['conversation_id'])) {
            $conversation = Conversation::where('user_id', Auth::id())
                ->findOrFail($validated['conversation_id']);
        } else {
            $conversation = Conversation::create([
                'user_id' => Auth::id(),
                'title'   => null,
                'meta'    => ['model' => $validated['model']],
            ]);
        }

        // 2) Titre auto au premier message
        if (is_null($conversation->title)) {
            $flat = trim(preg_replace('/\s+/', ' ', $validated['content']));
            $conversation->title = mb_strimwidth($flat, 0, 60, '…');
            $conversation->save();
        }

        // 3) Sauvegarde le message utilisateur
        Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $validated['content'],
        ]);

        // 4) Historique + prompt système personnalisé
        $history = $conversation->messages()
            ->orderBy('id')
            ->get(['role','content'])
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->all();

        $system = $this->buildSystemPromptForUser(Auth::id());
        array_unshift($history, ['role' => 'system', 'content' => $system]);

        // 5) Appel OpenRouter
        try {
            $json = $client->chat($validated['model'], $history);
            $text = data_get($json, 'choices.0.message.content', '');

            // 6) Sauvegarde la réponse assistant
            Message::create([
                'conversation_id' => $conversation->id,
                'role'            => 'assistant',
                'content'         => $text,
            ]);

            return response()->json([
                'ok'              => true,
                'text'            => $text,
                'conversation_id' => $conversation->id,
                'title'           => $conversation->title,
            ]);
        } catch (RequestException $e) {
            $payload = $e->response?->json();
            Log::warning('OpenRouter API error', [
                'status' => $e->response?->status(),
                'body'   => $payload,
            ]);

            return response()->json([
                'ok'    => false,
                'error' => data_get($payload, 'error.message')
                    ?: data_get($payload, 'message')
                        ?: 'OpenRouter call failed.',
            ], $e->response?->status() ?? 502);
        } catch (\Throwable $e) {
            Log::error('OpenRouter unexpected error', ['e' => $e]);
            return response()->json([
                'ok'    => false,
                'error' => 'Unexpected server error.',
            ], 500);
        }
    }

    public function show($id)
    {
        $c = Conversation::where('user_id', Auth::id())->findOrFail($id);

        $messages = $c->messages()
            ->orderBy('id')
            ->get(['role','content']);

        return response()->json([
            'id'       => $c->id,
            'title'    => $c->title ?? 'Untitled',
            'messages' => $messages,
        ]);
    }

    public function stream(Request $request, OpenRouterClient $client)
    {
        $validated = $request->validate([
            'model'           => ['required','string','max:200'],
            'content'         => ['required','string'],
            'conversation_id' => ['nullable','integer'],
        ]);

        // 1) Récupère ou crée la conversation
        if (!empty($validated['conversation_id'])) {
            $conversation = Conversation::where('user_id', Auth::id())
                ->findOrFail($validated['conversation_id']);
        } else {
            $conversation = Conversation::create([
                'user_id' => Auth::id(),
                'title'   => null,
                'meta'    => ['model' => $validated['model']],
            ]);
        }

        // 2) Titre auto au premier message
        if (is_null($conversation->title)) {
            $flat = trim(preg_replace('/\s+/', ' ', $validated['content']));
            $conversation->title = mb_strimwidth($flat, 0, 60, '…');
            $conversation->save();
        }

        // 3) Sauvegarde le message utilisateur
        Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $validated['content'],
        ]);

        // 4) Historique + prompt système personnalisé
        $history = $conversation->messages()
            ->orderBy('id')
            ->get(['role','content'])
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->all();

        $system = $this->buildSystemPromptForUser(Auth::id());
        array_unshift($history, ['role' => 'system', 'content' => $system]);

        // 5) Stream SSE
        $response = new StreamedResponse(function () use ($client, $validated, $conversation, $history) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');

            echo "event: meta\n";
            echo 'data: ' . json_encode([
                    'conversation_id' => $conversation->id,
                    'title'           => $conversation->title,
                ]) . "\n\n";
            @ob_flush(); @flush();

            $buffer = '';

            try {
                $stream = $client->chatStream($validated['model'], $history);

                while (!$stream->eof()) {
                    $chunk = $stream->read(8192);
                    if ($chunk === '' || $chunk === false) { usleep(10000); continue; }

                    foreach (preg_split("/\r\n|\n|\r/", $chunk) as $line) {
                        $line = trim($line);
                        if ($line === '' || !str_starts_with($line, 'data:')) continue;

                        $json = trim(substr($line, 5));
                        if ($json === '[DONE]') {
                            echo "event: end\n";
                            echo "data: {}\n\n";
                            @ob_flush(); @flush();
                            break 2;
                        }

                        $obj = json_decode($json, true);
                        $delta = data_get($obj, 'choices.0.delta.content', '');
                        if ($delta !== '') {
                            $buffer .= $delta;
                            echo "event: token\n";
                            echo 'data: ' . json_encode(['content' => $delta]) . "\n\n";
                            @ob_flush(); @flush();
                        }
                    }
                }

                // 6) Fin de flux : sauvegarder la réponse complète
                if ($buffer !== '') {
                    Message::create([
                        'conversation_id' => $conversation->id,
                        'role'            => 'assistant',
                        'content'         => $buffer,
                    ]);
                }
            } catch (RequestException $e) {
                $payload = $e->response?->json();
                Log::warning('OpenRouter stream API error', [
                    'status' => $e->response?->status(),
                    'body'   => $payload,
                ]);
                echo "event: error\n";
                echo 'data: ' . json_encode([
                        'message' => data_get($payload, 'error.message')
                            ?: data_get($payload, 'message')
                                ?: 'OpenRouter call failed.',
                    ]) . "\n\n";
                @ob_flush(); @flush();
            } catch (\Throwable $e) {
                Log::error('OpenRouter stream unexpected error', ['e' => $e]);
                echo "event: error\n";
                echo 'data: ' . json_encode(['message' => 'Unexpected server error.']) . "\n\n";
                @ob_flush(); @flush();
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    public function rename(Request $request, Conversation $conversation)
    {
        // sécurité : l'utilisateur doit posséder la conversation
        abort_unless($conversation->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:60'],
        ]);

        $conversation->update(['title' => $validated['title']]);

        return response()->json([
            'ok'    => true,
            'id'    => $conversation->id,
            'title' => $conversation->title,
        ]);
    }

    public function destroy(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);

        DB::transaction(function () use ($conversation) {
            // si tu as la relation messages()
            $conversation->messages()->delete();
            $conversation->delete();
        });

        return response()->noContent(); // 204
    }

}
