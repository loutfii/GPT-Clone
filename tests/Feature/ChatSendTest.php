<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('crée une conversation et un message via /chat/send', function () {
    $user = User::factory()->create();

    // Empêche tout appel sortant : on simule la réponse du LLM
    Http::fake([
        '*' => Http::response([
            'id' => 'fake-completion',
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Bonjour !']]
            ],
        ], 200),
    ]);

    $resp = $this->actingAs($user)->postJson('/chat/send', [
        'model' => 'gpt-4o-mini',
        'content' => 'Salut',
        'conversation_id' => null,
    ])->assertOk()
        ->assertJson(['ok' => true]);

    $cid = $resp->json('conversation_id');
    expect($cid)->not->toBeNull();

    // Vérifie que la conversation et les messages ont été créés
    $this->assertDatabaseHas('conversations', [
        'id' => $cid,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $cid,
        'role' => 'user',
        'content' => 'Salut',
    ]);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $cid,
        'role' => 'assistant',
        // pas besoin d'asserter le contenu exact
    ]);
});
