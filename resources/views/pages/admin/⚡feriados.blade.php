<?php

use App\Models\Holiday;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Feriados | DELTA SGA')] class extends Component {
    public int $gestion = 0;

    public bool $mostrandoForm = false;
    public bool $mostrandoEliminar = false;
    public ?int $editandoId = null;
    public ?Holiday $eliminando = null;

    public string $nombre = '';
    public string $fecha = '';
    public string $tipo = 'nacional';
    public bool $recurrente_anual = true;
    public bool $activo = true;

    public function mount(): void
    {
        $this->gestion = now()->year;
    }

    #[Computed]
    public function feriados()
    {
        return Holiday::whereYear('fecha', $this->gestion)
            ->orderBy('fecha')
            ->get();
    }

    #[Computed]
    public function gestiones()
    {
        return collect(
            Holiday::query()
                ->orderBy('fecha')
                ->get(['fecha'])
                ->map(fn (Holiday $feriado) => $feriado->fecha->year),
        )
            ->merge([now()->year - 1, now()->year, now()->year + 1])
            ->unique()
            ->sortDesc()
            ->values();
    }

    public function nuevo(): void
    {
        $this->reset('editandoId', 'nombre', 'fecha', 'tipo', 'recurrente_anual', 'activo');
        $this->activo = true;
        $this->mostrandoForm = true;
    }

    public function editar(int $id): void
    {
        $feriado = Holiday::findOrFail($id);
        $this->editandoId = $feriado->id;
        $this->nombre = $feriado->nombre;
        $this->fecha = $feriado->fecha->format('Y-m-d');
        $this->tipo = $feriado->tipo;
        $this->recurrente_anual = $feriado->recurrente_anual;
        $this->activo = $feriado->activo;
        $this->mostrandoForm = true;
    }

    public function guardar(): void
    {
        $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'in:nacional,regional,empresa'],
        ]);

        $datos = [
            'nombre' => $this->nombre,
            'fecha' => Carbon::parse($this->fecha),
            'tipo' => $this->tipo,
            'recurrente_anual' => $this->recurrente_anual,
            'activo' => $this->activo,
        ];

        if ($this->editandoId) {
            Holiday::findOrFail($this->editandoId)->update($datos);
            Flux::toast(variant: 'success', text: 'Feriado actualizado.');
        } else {
            Holiday::create($datos);
            Flux::toast(variant: 'success', text: 'Feriado creado.');
        }

        $this->resetForm();
    }

    public function confirmarEliminar(int $id): void
    {
        $this->eliminando = Holiday::findOrFail($id);
        $this->mostrandoEliminar = true;
    }

    public function eliminar(): void
    {
        if ($this->eliminando) {
            $this->eliminando->delete();
            Flux::toast(variant: 'success', text: 'Feriado eliminado.');
            $this->eliminando = null;
            $this->mostrandoEliminar = false;
        }
    }

    public function resetForm(): void
    {
        $this->reset('mostrandoForm', 'editandoId', 'nombre', 'fecha', 'tipo', 'recurrente_anual', 'activo');
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Feriados</flux:heading>
            <flux:text>Feriados nacionales y de la empresa que afectan el cálculo de días hábiles.</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:select wire:model.live="gestion" size="sm" class="w-auto">
                @foreach ($this->gestiones as $anio)
                    <option value="{{ $anio }}">{{ $anio }}</option>
                @endforeach
            </flux:select>
            <flux:button variant="primary" icon="plus" wire:click="nuevo">Nuevo feriado</flux:button>
        </div>
    </div>

    @if ($mostrandoForm)
        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ $editandoId ? 'Editar feriado' : 'Nuevo feriado' }}</flux:heading>
                <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="resetForm" />
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="nombre" :label="'Nombre'" required />
                <flux:input type="date" wire:model="fecha" :label="'Fecha'" required />
                <flux:select wire:model="tipo" :label="'Tipo'">
                    <option value="nacional">Nacional</option>
                    <option value="regional">Regional</option>
                    <option value="empresa">Empresa</option>
                </flux:select>
                <flux:switch wire:model="recurrente_anual" :label="'Recurrente cada año'" />
                <flux:switch wire:model="activo" :label="'Activo'" />
            </div>
            <div class="mt-4 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="resetForm">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="guardar">Guardar</flux:button>
            </div>
        </flux:card>
    @endif

    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->feriados as $feriado)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-medium">{{ $feriado->nombre }}</p>
                            <flux:badge size="sm" :color="match($feriado->tipo) {
                                'nacional' => 'green',
                                'empresa' => 'purple',
                                default => 'zinc',
                            }">{{ ucfirst($feriado->tipo) }}</flux:badge>
                        </div>
                        <p class="mt-1 text-sm text-zinc-500">{{ $feriado->fecha->format('d/m/Y') }} · {{ $feriado->fecha->isoFormat('dddd') }}</p>
                        @if ($feriado->recurrente_anual)
                            <p class="text-xs text-zinc-400">Recurrente cada año</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-1">
                        <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editar({{ $feriado->id }})" />
                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmarEliminar({{ $feriado->id }})" />
                    </div>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-300 py-16 md:col-span-3">
                <flux:icon.check-circle class="size-10 text-zinc-300" />
                <flux:text>No hay feriados registrados para {{ $this->gestion }}.</flux:text>
            </div>
        @endforelse
    </div>

    <flux:modal name="eliminar-feriado" wire:model="mostrandoEliminar" class="max-w-md">
        @if ($eliminando)
            <div class="space-y-4">
                <flux:heading size="lg">¿Eliminar feriado?</flux:heading>
                <flux:text>¿Seguro que deseas eliminar "{{ $eliminando->nombre }}"? Esta acción no se puede deshacer.</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="$set('mostrandoEliminar', false)">Cancelar</flux:button>
                    <flux:button variant="danger" wire:click="eliminar">Eliminar</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>