<?php

use App\Enums\JornadaTipo;
use App\Enums\LeaveConsideration;
use App\Enums\LeaveRequestType;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\HolidayService;
use App\Services\VacationService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nueva solicitud | DELTA SGA')] class extends Component {
    public string $tipo = 'permiso';
    public ?string $motivo = null;
    public ?string $fecha_desde = null;
    public ?string $fecha_hasta = null;
    public ?string $hora_desde = null;
    public ?string $hora_hasta = null;
    public string $jornada = 'completa';
    public string $considerado_como = 'sin_afectar';
    public ?string $comentarios = null;
    public ?int $recibido_por = null;

    #[Computed]
    public function balance()
    {
        if ($this->tipo !== 'vacacion' || ! $this->fecha_desde) {
            return null;
        }

        return app(VacationService::class)->balance(auth()->user(), (int) date('Y', strtotime($this->fecha_desde)));
    }

    #[Computed]
    public function recepcionistas()
    {
        $usuario = auth()->user();

        return User::query()
            ->role(['admin', 'supervisor'])
            ->where('id', '!=', $usuario->id)
            ->orderBy('apellido1')
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'nombre' => $u->nombre_completo]);
    }

    public function updatedTipo(): void
    {
        $this->reset('fecha_desde', 'fecha_hasta', 'hora_desde', 'hora_hasta', 'comentarios');

        if ($this->tipo === 'vacacion') {
            $this->considerado_como = 'vacacion';
            $this->jornada = 'completa';
        } else {
            $this->considerado_como = 'sin_afectar';
        }
    }

    public function updatedFechaDesde($value): void
    {
        if ($this->tipo === 'vacacion' && $value && ! $this->fecha_hasta) {
            $this->fecha_hasta = $value;
        }
    }

    public function guardarBorrador(): void
    {
        $this->validateCampos();

        $user = auth()->user();

        if (! $user) {
            abort(401, 'Debes iniciar sesión para crear una solicitud.');
        }

        $solicitud = new LeaveRequest();
        $solicitud->fill($this->datosPrevios());
        $solicitud->user()->associate($user);
        $solicitud->estado = 'borrador';
        $solicitud->save();

        Flux::toast(variant: 'success', text: 'Borrador guardado.');

        $this->redirectRoute('solicitudes.index', navigate: true);
    }

    public function emitir(): void
    {
        $this->validateCampos();
        $this->validarSaldoVacaciones();

        $solicitud = new LeaveRequest();
        $solicitud->fill($this->datosPrevios());

        $solicitud->emitir(auth()->user());

        Flux::toast(variant: 'success', text: 'Solicitud emitida y enviada a aprobación.');

        $this->redirectRoute('solicitudes.show', [$solicitud], navigate: true);
    }

    protected function datosPrevios(): array
    {
        return [
            'tipo' => $this->tipo,
            'motivo' => $this->motivo,
            'fecha_desde' => $this->fecha_desde,
            'fecha_hasta' => $this->fecha_hasta,
            'hora_desde' => $this->hora_desde ? $this->hora_desde.':00' : null,
            'hora_hasta' => $this->hora_hasta ? $this->hora_hasta.':00' : null,
            'jornada' => $this->jornada,
            'considerado_como' => $this->considerado_como,
            'comentarios' => $this->comentarios,
            'recibido_por' => $this->recibido_por,
        ];
    }

    protected function validarSaldoVacaciones(): void
    {
        if ($this->tipo !== 'vacacion') {
            return;
        }

        $service = app(VacationService::class);
        $dias = $service->diasHabilesParaRango(
            \Illuminate\Support\Carbon::parse($this->fecha_desde),
            \Illuminate\Support\Carbon::parse($this->fecha_hasta),
        );

        if ($dias === 0) {
            $this->addError('fecha_hasta', 'El rango seleccionado no contiene días hábiles.');
            $this->validate();
        }

        if (! $service->saldoSuficiente(auth()->user(), (int) date('Y', strtotime($this->fecha_desde)), $dias)) {
            $this->addError('fecha_hasta', 'No tienes saldo suficiente de vacaciones para este rango.');
        }
    }

    protected function validateCampos(): void
    {
        $rules = [
            'tipo' => ['required', 'in:permiso,licencia,vacacion'],
            'motivo' => ['required', 'string', 'max:255'],
            'fecha_desde' => ['required', 'date', 'after_or_equal:today'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
            'jornada' => ['required', 'in:completa,media'],
            'considerado_como' => ['required', 'in:vacacion,descuento_planilla,sin_afectar'],
            'comentarios' => ['nullable', 'string', 'max:2000'],
            'recibido_por' => ['nullable', 'exists:users,id'],
        ];

        if ($this->tipo !== 'vacacion') {
            $rules['hora_desde'] = ['nullable', 'date_format:H:i'];
            $rules['hora_hasta'] = ['nullable', 'date_format:H:i', 'after:hora_desde'];
        }

        $this->validate($rules);
    }
}; ?>

<div class="mx-auto w-full max-w-3xl p-6">
    <div class="mb-6">
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('solicitudes.index')" wire:navigate class="-ms-2">
            Volver a mis solicitudes
        </flux:button>
        <flux:heading size="xl" class="mt-2">Nueva solicitud</flux:heading>
        <flux:text>Completa los datos. Tu solicitud se enviará a tu inmediato superior para aprobación.</flux:text>
    </div>

    <div class="space-y-6">
        <flux:card class="space-y-6">
            <div>
                <flux:heading size="sm">Tipo de solicitud</flux:heading>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    @foreach ([LeaveRequestType::Permiso, LeaveRequestType::Licencia, LeaveRequestType::Vacacion] as $t)
                        <button type="button" wire:click="$set('tipo', '{{ $t->value }}')"
                            class="flex flex-col items-center gap-2 rounded-xl border-2 p-4 text-sm font-medium transition
                                {{ $tipo === $t->value ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-zinc-200 text-zinc-600 hover:border-zinc-300' }}">
                            <flux:icon :name="match($t) {
                                LeaveRequestType::Permiso => 'clock',
                                LeaveRequestType::Licencia => 'shield-check',
                                LeaveRequestType::Vacacion => 'calendar-days',
                            }" class="size-6" />
                            {{ $t->label() }}
                        </button>
                    @endforeach
                </div>
                @error('tipo')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            <flux:input wire:model="motivo" :label="'Permiso / Licencia por motivo de...'" placeholder="Describe el motivo" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model.live="fecha_desde" :label="'Desde'" min="{{ now()->toDateString() }}" required />
                <flux:input type="date" wire:model="fecha_hasta" :label="'Hasta'" min="{{ $fecha_desde ?? now()->toDateString() }}" required />
            </div>

            @if ($tipo !== 'vacacion')
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input type="time" wire:model="hora_desde" :label="'Desde horas'" />
                    <flux:input type="time" wire:model="hora_hasta" :label="'Hasta horas'" />
                </div>
                @error('hora_hasta')
                    <flux:error>{{ $message }}</flux:error>
                @enderror

                <flux:radio.group wire:model="jornada" :label="'Jornada'">
                    <flux:radio value="completa" label="Día completo" />
                    <flux:radio value="media" label="Medio día" />
                </flux:radio.group>
            @endif

            <flux:radio.group wire:model="considerado_como" :label="'Permiso considerado como'">
                @foreach (LeaveConsideration::cases() as $c)
                    <flux:radio :value="$c->value" :label="$c->label()" :disabled="$tipo === 'vacacion' && $c->value !== 'vacacion'" />
                @endforeach
            </flux:radio.group>

            <flux:textarea wire:model="comentarios" :label="'Comentarios u observaciones'" rows="3" />
        </flux:card>

        @if ($tipo === 'vacacion')
            <div class="rounded-xl border border-brand-200 bg-brand-50 p-5 dark:border-brand-500/30 dark:bg-brand-500/10">
                <div class="flex items-center gap-2">
                    <flux:icon.calendar-days class="size-5 text-brand-600" />
                    <flux:heading size="sm">Panel de vacaciones</flux:heading>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <flux:card class="!bg-white !border-brand-100">
                        <flux:text size="xs" class="!text-zinc-500">Días solicitados</flux:text>
                        <p class="mt-1 text-2xl font-bold text-brand-600">
                            @if ($fecha_desde && $fecha_hasta)
                                {{ app(HolidayService::class)->contarDiasHabiles(\Illuminate\Support\Carbon::parse($fecha_desde), \Illuminate\Support\Carbon::parse($fecha_hasta)) }}
                            @else
                                0
                            @endif
                        </p>
                        <flux:text size="xs">días hábiles en el rango</flux:text>
                    </flux:card>

                    <flux:card class="!bg-white !border-brand-100">
                        <flux:text size="xs" class="!text-zinc-500">Saldo disponible</flux:text>
                        <p class="mt-1 text-2xl font-bold text-zinc-900">{{ $this->balance?->dias_pendientes ?? 0 }}</p>
                        <flux:text size="xs">días pendientes · gestión {{ date('Y', strtotime($fecha_desde ?? now())) }}</flux:text>
                    </flux:card>

                    <flux:card class="!bg-white !border-brand-100">
                        <flux:text size="xs" class="!text-zinc-500">Fecha límite de toma</flux:text>
                        <p class="mt-1 text-lg font-bold text-zinc-900">{{ $this->balance?->fecha_limite_toma?->format('d/m/Y') ?? '—' }}</p>
                        <flux:text size="xs">según registro</flux:text>
                    </flux:card>
                </div>

                <div class="mt-4 rounded-lg bg-white p-4 text-sm">
                    <p>
                        Tomarás vacaciones del <strong>{{ $fecha_desde ? \Illuminate\Support\Carbon::parse($fecha_desde)->format('d/m/Y') : '—' }}</strong>
                        al <strong>{{ $fecha_hasta ? \Illuminate\Support\Carbon::parse($fecha_hasta)->format('d/m/Y') : '—' }}</strong>,
                        totalizando un máximo de <strong>{{ $this->balance?->dias_pendientes ?? 0 }} días</strong> disponibles.
                    </p>
                    @if ($this->balance)
                        <p class="mt-2 text-zinc-600">
                            Según registro te corresponden <strong>{{ $this->balance->dias_totales }}</strong> días de vacaciones
                            ({{ $this->balance->anios_servicio }} año(s) de servicio) correspondientes a la gestión <strong>{{ $this->balance->gestion }}</strong>.
                            @if ($this->balance->fecha_limite_toma)
                                Debes tomarlos hasta el <strong>{{ $this->balance->fecha_limite_toma->format('d/m/Y') }}</strong>.
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                <flux:text size="xs" class="!text-zinc-500">Receptor de la solicitud (recibido por)</flux:text>
                <div class="mt-2">
                    <flux:select wire:model="recibido_por" :label="'Recibido por'">
                        <option value="">{{ auth()->user()->superior?->nombre_completo ?: 'Inmediato superior (por defecto)' }}</option>
                        @foreach ($this->recepcionistas as $r)
                            <option value="{{ $r['id'] }}">{{ $r['nombre'] }}</option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
        @endif

        <div class="flex flex-col items-start gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            <div class="flex gap-2">
                <flux:icon.clock class="size-5 shrink-0" />
                <p>
                    <strong>Aviso importante:</strong> esta solicitud debe aprobarse en un máximo de <strong>48 horas</strong> desde su emisión.
                    Pasado ese plazo, se considerará pendiente de gestión y será escalada.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <flux:button variant="ghost" wire:click="guardarBorrador">Guardar borrador</flux:button>
            <flux:button variant="primary" wire:click="emitir" icon="arrow-right">
                Emitir solicitud
            </flux:button>
        </div>
    </div>

    <div wire:loading wire:target="emitir, guardarBorrador" class="fixed inset-0 z-50 flex items-center justify-center bg-white/60 backdrop-blur-sm">
        <div class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white px-5 py-3 shadow-lg">
            <flux:icon.arrow-path spin class="size-4" />
            <span class="text-sm font-medium">Procesando...</span>
        </div>
    </div>
</div>