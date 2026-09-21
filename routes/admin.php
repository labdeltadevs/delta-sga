<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:usuarios.list'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('areas', 'pages::admin.areas')->name('areas');
    Route::livewire('usuarios', 'pages::admin.usuarios')->name('usuarios');
    Route::livewire('roles', 'pages::admin.roles')->name('roles');
    Route::livewire('feriados', 'pages::admin.feriados')->name('feriados');
    Route::livewire('vacaciones', 'pages::admin.vacaciones')->name('vacaciones');
    Route::livewire('plantillas', 'pages::admin.plantillas')->name('plantillas');
});
