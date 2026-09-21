<?php

use App\Models\LeaveRequest;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Mis solicitudes | DELTA SGA')] class extends Component {
    use WithPagination;

    public string $estado = '';
    public string $tipo = '';
    public ?string $desde = null;
    public ?string $hasta = null;
    public string $vista = 'mis'; // mis|universo

    #[Computed]
    public function solicitudes()
    {
        $query = LeaveRequest::query()
            ->with('aprobaciones.aprobador')
            ->when($this->estado, fn ($q) => $q->where('estado', $this->estado))
            ->when($this->tipo, fn ($q) => $q->where('tipo', $this->tipo))
            ->when($this->desde, fn ($q) => $q->whereDate('fecha_desde', '>=', Carbon::parse($this->desde)))
            ->when($this->hasta, fn ($q) => $q->whereDate('fecha_desde', '<=', Carbon::parse($this->hasta)))
            ->latest();

        if ($this->vista === 'universo') {
            $query->visibleFor(auth()->user());
        } else {
            $query->where('user_id', auth()->id());
        }

        return $query->simplePaginate(10);
    }

    #[Computed]
    public function universoCount(): int
    {
        if (! auth()->user()->esJefeDeUniverso()) {
            return 0;
        }

        return LeaveRequest::visibleFor(auth()->user())
            ->where('estado', 'pendiente')
            ->count();
    }

    public function resetFiltros(): void
    {
        $this->reset('estado', 'tipo', 'desde', 'hasta');
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $this->vista === 'universo' ? 'Universo de áreas' : 'Mis solicitudes' }}</flux:heading>
            <flux:text>{{ $this->vista === 'universo' ? 'Permisos dentro de tu jerarquía de áreas.' : 'Emite y da seguimiento a tus permisos, licencias y vacaciones.' }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('solicitudes.create')" wire:navigate>
            Nueva solicitud
        </flux:button>
    </div>

    @if (auth()->user()->esJefeDeUniverso())
        <div class="flex items-center gap-1 rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800">
            <flux:button size="sm" variant="ghost" wire:click="$set('vista', 'mis')"
                :class="$vista === 'mis' ? '!bg-white !text-zinc-900 shadow-sm dark:!bg-zinc-700 dark:!text-white' : '!text-zinc-500'">
                Mis solicitudes
            </flux:button>
            <flux:button size="sm" variant="ghost" wire:click="$set('vista', 'universo')"
                :class="$vista === 'universo' ? '!bg-white !text-zinc-900 shadow-sm dark:!bg-zinc-700 dark:!text-white' : '!text-zinc-500'">
                Universo ({{ $this->universoCount }})
            </flux:button>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:select wire:model.live="estado" :label="'Estado'">
            <option value="">Todos</option>
            <option value="borrador">Borrador</option>
            <option value="pendiente">Pendiente</option>
            <option value="aprobado">Aprobado</option>
            <option value="rechazado">Rechazado</option>
            <option value="cancelado">Cancelado</option>
        </flux:select>

        <flux:select wire:model.live="tipo" :label="'Tipo'">
            <option value="">Todos</option>
            <option value="permiso">Permiso</option>
            <option value="licencia">Licencia</option>
            <option value="vacacion">Vacación</option>
        </flux:select>

        <flux:input type="date" wire:model.live.debounce.500ms="desde" :label="'Desde fecha'"/>
        <flux:input type="date" wire:model.live.debounce.500ms="hasta" :label="'Hasta fecha'"/>
    </div>

    <div class="w-full space-y-3">
        @forelse ($this->solicitudes as $solicitud)
            <a href="{{ route('solicitudes.show', $solicitud) }}" wire:navigate
                class="block rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-brand-300 hover:shadow dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <flux:badge size="sm" :color="match($solicitud->estado) {
                            'aprobado' => 'green',
                            'rechazado' => 'red',
                            'pendiente' => 'amber',
                            'borrador' => 'gray',
                            'cancelado' => 'gray',
                            default => 'gray',
                        }">{{ str($solicitud->estado)->replace('_', ' ')->ucfirst() }}</flux:badge>
                        <flux:badge size="sm" :color="match($solicitud->tipo) {
                            'vacacion' => 'purple',
                            'licencia' => 'sky',
                            default => 'indigo',
                        }">{{ str($solicitud->tipo)->ucfirst() }}</flux:badge>
                    </div>

                    <flux:text size="xs" class="text-zinc-400">{{ $solicitud->created_at->format('d/m/Y H:i') }}</flux:text>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $solicitud->motivo ?: '—' }}</p>
                        @if ($solicitud->tipo !== 'permiso' || $solicitud->jornada === 'completa')
                            <p class="text-sm text-zinc-500">
                                {{ $solicitud->fecha_desde->format('d/m/Y') }} → {{ $solicitud->fecha_hasta->format('d/m/Y') }}
                                <flux:text size="xs">· {{ $solicitud->dias_habiles }} días hábiles</flux:text>
                            </p>
                        @else
                            <p class="text-sm text-zinc-500">
                                {{ $solicitud->fecha_desde->format('d/m/Y') }} ·
                                {{ $solicitud->hora_desde?->format('H:i') }} → {{ $solicitud->hora_hasta?->format('H:i') }}
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($solicitud->estado === 'pendiente')
                            @if ($solicitud->getSlaVencidoAttribute())
                                <flux:badge color="red" size="sm">SLA vencido</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">{{ $solicitud->horas_restantes_sla }} h restantes</flux:badge>
                            @endif
                        @endif
                        <flux:icon.chevron-right class="size-4 text-zinc-400" />
                    </div>
                </div>
            </a>
        @empty
            <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-300 bg-white py-16 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:icon.document-text class="size-10 text-zinc-300" />
                <flux:text>{{ $this->vista === 'universo' ? 'No hay solicitudes en tu universo de áreas.' : ($this->estado || $this->tipo ? 'No tienes solicitudes con los filtros seleccionados.' : 'No tienes solicitudes registradas.') }}</flux:text>
                <flux:button variant="primary" :href="route('solicitudes.create')" wire:navigate>Crear solicitud</flux:button>
            </div>
        @endforelse
    </div>

    <div>
        {{ $this->solicitudes->links() }}
    </div>
</div>
