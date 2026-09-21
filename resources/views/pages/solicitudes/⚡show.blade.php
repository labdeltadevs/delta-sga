<?php

use App\Models\LeaveRequest;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Solicitud | DELTA SGA')] class extends Component {
    public LeaveRequest $solicitud;
    public ?string $comentario_aprobacion = null;
    public ?string $comentario_rechazo = null;
    public ?string $password_fallback = null;

    #[Computed]
    public function puedeAprobar(): bool
    {
        return $this->solicitud->estado === 'pendiente'
            && $this->solicitud->puedeSerAprobadaPor(auth()->user());
    }

    #[Computed]
    public function esDueño(): bool
    {
        return $this->solicitud->user_id === auth()->id();
    }

    public function aprobar(): void
    {
        $this->authorizeAprobar();
        $this->validarCredencial();

        $fallback = $this->verificarPasswordFallback();

        try {
            $this->solicitud->aprobar(
                auth()->user(),
                $fallback ? null : request()->session()->get('last_passkey_credential_id'),
                $fallback,
                $this->comentario_aprobacion ?? '',
            );

            $this->reset('password_fallback');
            Flux::toast(variant: 'success', text: 'Solicitud aprobada.');

            $this->redirectRoute('aprobaciones.index', navigate: true);
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function rechazar(): void
    {
        $this->authorizeAprobar();
        $this->validarCredencial();

        $fallback = $this->verificarPasswordFallback();

        try {
            $this->solicitud->rechazar(
                auth()->user(),
                $this->comentario_rechazo ?? '',
                $fallback ? null : request()->session()->get('last_passkey_credential_id'),
                $fallback,
            );

            $this->reset('password_fallback');
            Flux::toast(variant: 'success', text: 'Solicitud rechazada.');

            $this->redirectRoute('aprobaciones.index', navigate: true);
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    protected function authorizeAprobar(): void
    {
        if (! $this->puedeAprobar) {
            abort(403, 'No estás autorizado para aprobar esta solicitud.');
        }
    }

    protected function validarCredencial(): void
    {
        if (! request()->session()->has('approval_passkey_verified')) {
            $this->addError('password_fallback', 'Debes verificar tu identidad con tu passkey o contraseña.');
        }
    }

    protected function verificarPasswordFallback(): bool
    {
        $user = auth()->user();

        if (! filled($this->password_fallback)) {
            return false;
        }

        if (! Hash::check($this->password_fallback, $user->password)) {
            $this->addError('password_fallback', 'La contraseña es incorrecta.');

            throw new \Exception('Contraseña incorrecta.');
        }

        return true;
    }
}; ?>

<div class="mx-auto w-full max-w-3xl p-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('solicitudes.index')" wire:navigate class="-ms-2">
                Volver
            </flux:button>
            <flux:heading size="xl" class="mt-2">
                Solicitud {{ $solicitud->tipo }}
                <flux:badge size="sm" :color="$solicitud->estado === 'pendiente' ? 'amber' : 'green'">{{ $solicitud->estado }}</flux:badge>
            </flux:heading>
        </div>

        <div class="text-end">
            <flux:text size="xs" class="!text-zinc-400">Emitida</flux:text>
            <flux:text>{{ $solicitud->created_at->format('d/m/Y H:i') }}</flux:text>
            @if ($solicitud->estado === 'pendiente' && $solicitud->getSlaVencidoAttribute())
                <flux:badge color="red" size="sm" class="mt-1">SLA vencido (48h)</flux:badge>
            @endif
            <div class="mt-2">
                <flux:button
                    variant="outline"
                    size="sm"
                    icon="arrow-down-tray"
                    :href="route('solicitudes.pdf', $solicitud)"
                    tag="a"
                >
                    Descargar PDF
                </flux:button>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <flux:card>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:text size="xs" class="!text-zinc-400">Solicitante</flux:text>
                    <p class="font-medium">{{ $solicitud->user->nombre_completo }}</p>
                    <p class="text-sm text-zinc-500">{{ $solicitud->user->area?->nombre }}</p>
                </div>
                <div>
                    <flux:text size="xs" class="!text-zinc-400">Motivo</flux:text>
                    <p class="font-medium">{{ $solicitud->motivo ?: '—' }}</p>
                    @if ($solicitud->jornada === 'media')
                        <p class="text-sm text-zinc-500">Medio día</p>
                    @endif
                </div>
                <div>
                    <flux:text size="xs" class="!text-zinc-400">Desde</flux:text>
                    <p class="font-medium">{{ $solicitud->fecha_desde->format('d/m/Y') }}</p>
                    @if ($solicitud->hora_desde)
                        <p class="text-sm text-zinc-500">{{ $solicitud->hora_desde->format('H:i') }}</p>
                    @endif
                </div>
                <div>
                    <flux:text size="xs" class="!text-zinc-400">Hasta</flux:text>
                    <p class="font-medium">{{ $solicitud->fecha_hasta->format('d/m/Y') }}</p>
                    @if ($solicitud->hora_hasta)
                        <p class="text-sm text-zinc-500">{{ $solicitud->hora_hasta->format('H:i') }}</p>
                    @endif
                </div>
                <div>
                    <flux:text size="xs" class="!text-zinc-400">Días hábiles</flux:text>
                    <p class="font-medium">{{ $solicitud->dias_habiles }}</p>
                </div>
                <div>
                    <flux:text size="xs" class="!text-zinc-400">Considerado como</flux:text>
                    <p class="font-medium">{{ str($solicitud->considerado_como)->replace('_', ' ')->ucfirst() }}</p>
                </div>
            </div>

            @if ($solicitud->tipo === 'vacacion')
                <flux:separator class="my-4" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <flux:text size="xs" class="!text-zinc-400">Años de servicio</flux:text>
                        <p class="font-medium">{{ $solicitud->anios_servicio }}</p>
                    </div>
                    <div>
                        <flux:text size="xs" class="!text-zinc-400">Días correspondientes</flux:text>
                        <p class="font-medium">{{ $solicitud->dias_correspondientes }}</p>
                    </div>
                    <div>
                        <flux:text size="xs" class="!text-zinc-400">Fecha límite de toma</flux:text>
                        <p class="font-medium">{{ $solicitud->fecha_limite_vacaciones?->format('d/m/Y') }}</p>
                    </div>
                </div>
            @endif

            @if ($solicitud->comentarios)
                <flux:separator class="my-4" />
                <flux:text size="xs" class="!text-zinc-400">Observaciones</flux:text>
                <p class="mt-1 text-sm">{{ $solicitud->comentarios }}</p>
            @endif
        </flux:card>

        @if ($solicitud->firma_bpm_trabajador)
            <flux:card>
                <flux:text size="xs" class="!text-zinc-400">Firma BPM del trabajador</flux:text>
                <div class="mt-2 w-full max-w-xs rounded-lg border border-zinc-200 bg-white p-2">
                    <img src="{{ $solicitud->firma_bpm_trabajador }}" alt="Firma del trabajador" class="w-full" />
                </div>
            </flux:card>
        @endif

        <div>
            <flux:heading size="sm">Cadena de aprobación</flux:heading>
            <div class="mt-3 space-y-0">
                @foreach ($solicitud->aprobaciones as $i => $aprobacion)
                    <div class="relative flex gap-4 pb-6 {{ ! $loop->last ? 'before:absolute before:left-[15px] before:top-8 before:bottom-0 before:w-px before:bg-zinc-200' : '' }}">
                        <div class="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full text-white
                            {{ $aprobacion->accion === 'aprobado' ? 'bg-green-500' : ($aprobacion->accion === 'rechazado' ? 'bg-red-500' : 'bg-zinc-300') }}">
                            <flux:icon :name="$aprobacion->accion === 'aprobado' ? 'check' : ($aprobacion->accion === 'rechazado' ? 'x' : 'clock')" class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-medium">{{ $aprobacion->aprobador->nombre_completo }}</p>
                                <flux:badge size="sm" :color="match($aprobacion->accion) {
                                    'aprobado' => 'green',
                                    'rechazado' => 'red',
                                    default => 'zinc',
                                }">{{ str($aprobacion->accion)->ucfirst() }}</flux:badge>
                            </div>
                            <p class="text-xs text-zinc-500">
                                Nivel {{ $aprobacion->orden }} · {{ $aprobacion->aprobador->area?->nombre }}
                                @if ($aprobacion->fecha_accion)
                                    · {{ $aprobacion->fecha_accion->format('d/m/Y H:i') }}
                                @endif
                            </p>
                            @if ($aprobacion->fallback_password)
                                <p class="mt-1 text-xs text-amber-600">Aprobado con contraseña (fallback)</p>
                            @endif
                            @if ($aprobacion->comentario)
                                <p class="mt-1 text-sm text-zinc-600">{{ $aprobacion->comentario }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($solicitud->autorizador)
            <flux:card>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <flux:text size="xs" class="!text-zinc-400">Autorizado por</flux:text>
                        <p class="font-medium">{{ $solicitud->autorizador->nombre_completo }}</p>
                        <p class="text-sm text-zinc-500">{{ $solicitud->fecha_aprobacion?->format('d/m/Y H:i') }}</p>
                    </div>
                    <div>
                        <flux:text size="xs" class="!text-zinc-400">Recibido por</flux:text>
                        <p class="font-medium">{{ $solicitud->receptor?->nombre_completo }}</p>
                        <p class="text-sm text-zinc-500">{{ $solicitud->fecha_recepcion ? \Illuminate\Support\Carbon::parse($solicitud->fecha_recepcion)->format('d/m/Y H:i') : 'Pendiente' }}</p>
                    </div>
                </div>
            </flux:card>
        @endif

        @if ($this->puedeAprobar)
            <flux:card class="border-brand-200 bg-brand-50/50">
                <flux:heading size="sm">Acción requerida</flux:heading>
                <flux:text class="mt-1">Debes verificar tu identidad con tu <strong>passkey</strong> (biometría) para aprobar o rechazar. Si tu passkey no está disponible, puedes usar tu contraseña como respaldo.</flux:text>

                <div class="mt-4 flex flex-col gap-4">
                    <div>
                        <label for="approval-passkey" class="mb-1 block text-sm font-medium">
                            Verificar con passkey
                        </label>
                        <x-passkey-verify
                            :optionsRoute="'solicitudes.passkey-options'"
                            :submitRoute="'solicitudes.passkey-verify'"
                            :label="'Verificar con passkey'"
                            :loadingLabel="'Verificando...'"
                            :variant="'primary'"
                            :redirect="request()->fullUrl()"
                        />
                        @error('password_fallback')
                            <flux:error class="mt-2">{{ $message }}</flux:error>
                        @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:separator class="flex-1" />
                        <flux:text size="xs" class="!text-zinc-400">o</flux:text>
                        <flux:separator class="flex-1" />
                    </div>

                    <div class="grid gap-4">
                        <flux:input type="password" wire:model="password_fallback" :label="'Fallback con contraseña'" placeholder="Contraseña de tu cuenta" viewable />
                    </div>

                    <flux:separator />

                    <flux:textarea wire:model="comentario_aprobacion" :label="'Comentario de aprobación (opcional)'" rows="2" />

                    <div class="flex flex-wrap gap-3">
                        <flux:button variant="primary" wire:click="aprobar" wire:loading.attr="disabled">
                            <flux:icon.check class="size-4" />
                            Aprobar solicitud
                        </flux:button>
                        <flux:button variant="danger" wire:click="rechazar">
                            <flux:icon.x-mark class="size-4" />
                            Rechazar
                        </flux:button>
                    </div>
                    <flux:text size="xs" class="!text-zinc-400">La aprobación registrará tu firma biométrica (passkey) o fallback con contraseña como evidencia.</flux:text>
                </div>
            </flux:card>
        @endif

        @if ($solicitud->user_id === auth()->id() && $solicitud->estado === 'aprobado' && ! $solicitud->fecha_recepcion)
            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600">
                Pendiente de recepción. El receptor puede marcar la solicitud como recibida.
            </div>
        @endif
    </div>
</div>