<?php

use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\VacationBalance;
use App\Models\VacationConfig;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('guests are redirected to the login page from create', function () {
    $this->get(route('solicitudes.create'))->assertRedirect(route('login'));
});

test('authenticated users can visit the solicitudes create page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('solicitudes.create'))->assertOk();
});

test('authenticated users can view a solicitud', function () {
    $user = User::factory()->create();
    $solicitud = LeaveRequest::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $this->get(route('solicitudes.show', $solicitud))->assertOk();
});

test('emitir stores the solicitud with an authenticated user and pending state', function () {
    Notification::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::solicitudes.create')
        ->set('tipo', 'permiso')
        ->set('motivo', 'Malestar general')
        ->set('fecha_desde', now()->toDateString())
        ->set('fecha_hasta', now()->toDateString())
        ->set('hora_desde', '10:01')
        ->set('hora_hasta', '16:30')
        ->set('jornada', 'media')
        ->set('considerado_como', 'descuento_planilla')
        ->call('emitir')
        ->assertHasNoErrors()
        ->assertRedirect();

    $solicitud = LeaveRequest::where('user_id', $user->id)->first();

    expect($solicitud)->not->toBeNull()
        ->and($solicitud->user_id)->toBe($user->id)
        ->and($solicitud->estado)->toBe('pendiente');
});

test('emitir stores a vacation solicitud with vacation-specific fields', function () {
    Notification::fake();

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('supervisor', 'web');

    $user = User::factory()->create(['fecha_contrato' => now()->subYears(2)]);
    $gestion = now()->year;
    VacationConfig::factory()->paraGestion($gestion)->create();
    VacationBalance::factory()->paraUsuario($user, $gestion)->create(['dias_pendientes' => 30]);

    $this->actingAs($user);

    $fecha = now()->nextWeekday()->toDateString();

    Livewire::test('pages::solicitudes.create')
        ->set('tipo', 'vacacion')
        ->set('motivo', 'Vacaciones familiares')
        ->set('fecha_desde', $fecha)
        ->set('fecha_hasta', $fecha)
        ->set('considerado_como', 'vacacion')
        ->call('emitir')
        ->assertHasNoErrors()
        ->assertRedirect();

    $solicitud = LeaveRequest::where('user_id', $user->id)->first();

    expect($solicitud)->not->toBeNull()
        ->and($solicitud->user_id)->toBe($user->id)
        ->and($solicitud->estado)->toBe('pendiente')
        ->and($solicitud->tipo)->toBe('vacacion')
        ->and($solicitud->dias_habiles)->toBe(1)
        ->and($solicitud->fecha_limite_vacaciones)->not->toBeNull();
});
