<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('affiche Chat pour un user connecté', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/chat')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Chat'));
});
