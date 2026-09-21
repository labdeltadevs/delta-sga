<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\VacationBalance;
use App\Models\VacationConfig;
use App\Services\AreaHierarchyService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
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

test('area hierarchy is built correctly with parent-child relationships', function () {
    $administrativa = Area::factory()->create(['nombre' => 'Administrativa', 'parent_id' => null]);
    $contabilidad = Area::factory()->create(['nombre' => 'Contabilidad', 'parent_id' => $administrativa->id]);
    $rrhh = Area::factory()->create(['nombre' => 'RRHH', 'parent_id' => $administrativa->id]);

    $jefe = User::factory()->create(['area_id' => $contabilidad->id]);
    $trabajador = User::factory()->create(['area_id' => $contabilidad->id]);

    expect($contabilidad->descendantIds())->toBe([]);
    expect($administrativa->descendantIds())->toContain($contabilidad->id);
    expect($administrativa->descendantIds())->toContain($rrhh->id);
    expect($administrativa->selfAndDescendantIds())->toContain($administrativa->id);
    expect($administrativa->selfAndDescendantIds())->toContain($contabilidad->id);
});

test('contabilidad users cannot see solicitudes from rrhh (separate siblings)', function () {
    Permission::findOrCreate('solicitudes.view-all', 'web');

    $administrativa = Area::factory()->create(['nombre' => 'Administrativa', 'parent_id' => null]);
    $contabilidad = Area::factory()->create(['nombre' => 'Contabilidad', 'parent_id' => $administrativa->id]);
    $rrhh = Area::factory()->create(['nombre' => 'RRHH', 'parent_id' => $administrativa->id]);

    $jefeContabilidad = User::factory()->create(['area_id' => $contabilidad->id]);
    $trabajadorRRHH = User::factory()->create(['area_id' => $rrhh->id]);

    $solicitudRRHH = LeaveRequest::factory()->create(['user_id' => $trabajadorRRHH->id]);

    $jefeContabilidad->givePermissionTo('solicitudes.view-all');

    $universe = $jefeContabilidad->areaUniverseUserIds();

    expect($universe)->not->toContain($trabajadorRRHH->id);
});

test('administrativa head can see solicitudes from all sub-areas (universe)', function () {
    Permission::findOrCreate('solicitudes.view-all', 'web');

    $administrativa = Area::factory()->create(['nombre' => 'Administrativa', 'parent_id' => null]);
    $contabilidad = Area::factory()->create(['nombre' => 'Contabilidad', 'parent_id' => $administrativa->id]);
    $rrhh = Area::factory()->create(['nombre' => 'RRHH', 'parent_id' => $administrativa->id]);

    $adminUser = User::factory()->create(['area_id' => $administrativa->id, 'es_superadmin' => false]);
    $adminUser->givePermissionTo('solicitudes.view-all');

    $trabajadorContabilidad = User::factory()->create(['area_id' => $contabilidad->id]);
    $trabajadorRRHH = User::factory()->create(['area_id' => $rrhh->id]);

    LeaveRequest::factory()->create(['user_id' => $trabajadorContabilidad->id]);
    LeaveRequest::factory()->create(['user_id' => $trabajadorRRHH->id]);

    $universe = $adminUser->areaUniverseUserIds();

    expect($universe)->toContain($trabajadorContabilidad->id);
    expect($universe)->toContain($trabajadorRRHH->id);
});

test('hybrid approval chain includes head of area when no superior exists', function () {
    $administrativa = Area::factory()->create(['nombre' => 'Administrativa', 'parent_id' => null, 'head_user_id' => null]);
    Area::factory()->create(['nombre' => 'Contabilidad', 'parent_id' => $administrativa->id]);
    $jefe = User::factory()->create(['area_id' => $administrativa->id]);
    $administrativa->update(['head_user_id' => $jefe->id]);

    $trabajador = User::factory()->create(['area_id' => $administrativa->id]);

    $cadena = app(AreaHierarchyService::class)->cadenaAprobacionHibrida($trabajador);

    expect(array_map(fn ($u) => $u->id, $cadena))->toContain($jefe->id);
});

test('scopeVisibleFor filters by universe for admin and excludes other branches', function () {
    Permission::findOrCreate('solicitudes.view-all', 'web');

    $administrativa = Area::factory()->create(['nombre' => 'Administrativa', 'parent_id' => null]);
    $contabilidad = Area::factory()->create(['nombre' => 'Contabilidad', 'parent_id' => $administrativa->id]);
    $rrhh = Area::factory()->create(['nombre' => 'RRHH', 'parent_id' => $administrativa->id]);

    $jefeContabilidad = User::factory()->create(['area_id' => $contabilidad->id]);
    $trabajadorContabilidad = User::factory()->create(['area_id' => $contabilidad->id]);
    $trabajadorRRHH = User::factory()->create(['area_id' => $rrhh->id]);

    $jefeContabilidad->givePermissionTo('solicitudes.view-all');

    LeaveRequest::factory()->create(['user_id' => $trabajadorContabilidad->id]);
    LeaveRequest::factory()->create(['user_id' => $trabajadorRRHH->id]);

    $query = LeaveRequest::query()->visibleFor($jefeContabilidad);
    $results = $query->get();

    foreach ($results as $solicitud) {
        expect($solicitud->user_id)->toBe($trabajadorContabilidad->id);
    }
});
