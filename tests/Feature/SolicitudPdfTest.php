<?php

use App\Models\LeaveApproval;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed('Database\\Seeders\\DatabaseSeeder');
});

it('permite al dueño descargar el PDF de su solicitud', function (): void {
    $user = User::where('email', 'trabajador@delta-sga.com')->first();
    $solicitud = LeaveRequest::where('user_id', $user->id)->first();

    $this->actingAs($user)
        ->get(route('solicitudes.pdf', $solicitud))
        ->assertStatus(200)
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition');
});

it('permite al aprobador descargar el PDF', function (): void {
    $aprobador = User::where('email', 'jefe.rrhh@delta-sga.com')->first();
    $solicitud = LeaveRequest::first();

    LeaveApproval::updateOrCreate(
        ['leave_request_id' => $solicitud->id, 'orden' => 1],
        [
            'aprobador_id' => $aprobador->id,
            'accion' => 'aprobado',
            'fecha_accion' => now(),
        ],
    );

    $this->actingAs($aprobador)
        ->get(route('solicitudes.pdf', $solicitud))
        ->assertStatus(200);
});

it('bloquea a usuarios no autorizados', function (): void {
    $otro = User::where('email', 'trabajador2@delta-sga.com')->first();
    $solicitud = LeaveRequest::first();

    $this->actingAs($otro)
        ->get(route('solicitudes.pdf', $solicitud))
        ->assertStatus(403);
});

it('admin puede descargar cualquier PDF', function (): void {
    $admin = User::where('email', 'superadmin@delta-sga.com')->first();
    $solicitud = LeaveRequest::first();

    $this->actingAs($admin)
        ->get(route('solicitudes.pdf', $solicitud))
        ->assertStatus(200);
});

it('renderiza PDF con tokens reemplazados', function (): void {
    $user = User::where('email', 'trabajador@delta-sga.com')->first();
    $solicitud = LeaveRequest::where('user_id', $user->id)->first();

    $response = $this->actingAs($user)
        ->get(route('solicitudes.pdf', $solicitud));

    $content = $response->getContent();

    expect($content)->toContain('%PDF');
});
