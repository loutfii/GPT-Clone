<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('affiche la page login', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('redirige un invité vers /login pour /chat et /settings', function () {
    $this->get('/chat')->assertRedirect('/login');
    $this->get('/settings')->assertRedirect('/login');
});

it('permet à un user connecté d’accéder au chat', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/chat')
        ->assertOk();
});
