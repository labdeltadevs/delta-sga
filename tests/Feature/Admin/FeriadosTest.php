<?php

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.feriados'))->assertRedirect(route('login'));
});

test('users without the feriados permission are forbidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.feriados'))->assertForbidden();
});

test('authorized users can visit the feriados page', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::create(['name' => 'usuarios.list']));
    $this->actingAs($user);

    $this->get(route('admin.feriados'))->assertOk();
});

test('the feriados page lists every year with registered holidays', function () {
    Holiday::factory()->create(['fecha' => Carbon::create(2023, 8, 6)]);
    Holiday::factory()->create(['fecha' => Carbon::create(2025, 1, 1)]);
    Holiday::factory()->create(['fecha' => Carbon::create(2025, 5, 1)]);

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::create(['name' => 'usuarios.list']));
    $this->actingAs($user);

    $this->get(route('admin.feriados'))
        ->assertOk()
        ->assertSee('2023')
        ->assertSee('2025');
});
