<?php

use App\Models\User;

it('sauvegarde les instructions personnalisées', function () {
    $user = User::factory()->create();

    $payload = [
        'tone'    => 'amical',
        'style'   => 'concise',
        'context' => 'répondre comme un coach',
    ];

    $this->actingAs($user)
        ->post('/settings', $payload)   // ou route('settings.update') si tu préfères
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // Vérifie la persistance (table user_settings)
    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'tone'    => 'amical',
        'style'   => 'concise',
        'context' => 'répondre comme un coach',
    ]);
});
