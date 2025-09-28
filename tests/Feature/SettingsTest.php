<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('affiche Settings pour un user connecté', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings'));
});
