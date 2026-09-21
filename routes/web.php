<?php

use App\Http\Controllers\PasskeyApprovalController;
use App\Http\Controllers\SolicitudPdfController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('solicitudes', 'pages::solicitudes.index')->name('solicitudes.index');
    Route::livewire('solicitudes/crear', 'pages::solicitudes.create')->name('solicitudes.create');

    // Verificación biométrica para aprobaciones (no inicia sesión, solo registra evidencia)
    // Antes de la ruta con parámetro para no capturar "passkey" como ID de solicitud.
    Route::get('solicitudes/passkey/options', [PasskeyApprovalController::class, 'options'])
        ->name('solicitudes.passkey-options');
    Route::post('solicitudes/passkey/verify', [PasskeyApprovalController::class, 'verify'])
        ->name('solicitudes.passkey-verify');

    Route::livewire('solicitudes/{solicitud}', 'pages::solicitudes.show')->name('solicitudes.show');
    Route::get('solicitudes/{solicitud}/pdf', SolicitudPdfController::class)->name('solicitudes.pdf');

    Route::livewire('aprobar', 'pages::aprobaciones.index')->name('aprobaciones.index');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
