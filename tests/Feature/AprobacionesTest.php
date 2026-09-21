<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('aprobaciones.index'))->assertRedirect(route('login'));
});

test('authenticated users can visit the aprobaciones page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('aprobaciones.index'))->assertOk();
});
