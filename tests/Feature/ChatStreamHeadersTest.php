<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('expose du SSE sur /chat/stream', function () {
    $user = User::factory()->create();

    // On évite tout appel externe (sécurise le test)
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $resp = $this->actingAs($user)->post('/chat/stream', [
        'model' => 'gpt-4o-mini',
        'content' => 'Test streaming',
        'conversation_id' => null,
    ]);

    $resp->assertOk();

    // Tolérant: accepte "text/event-stream; charset=UTF-8"
    expect($resp->headers->get('Content-Type'))
        ->toStartWith('text/event-stream')
        ->and($resp->headers->has('Cache-Control'))->toBeTrue()
        ->and($resp->headers->has('X-Accel-Buffering'))->toBeTrue();

    // On vérifie juste la présence de ces en-têtes (valeur libre)
});
