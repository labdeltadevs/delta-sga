<?php

use App\Models\LeaveApproval;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Aprobaciones pendientes | DELTA SGA')] class extends Component {
    use WithPagination;

    public string $filtro = 'pendientes';
    public string $tipo = '';

    #[Computed]
    public function pendientes()
    {
        return LeaveApproval::query()
            ->with(['leaveRequest.user', 'leaveRequest.aprobaciones'])
            ->where('aprobador_id', auth()->id())
            ->where('accion', 'pendiente')
            ->whereHas('leaveRequest', fn ($q) => $q->where('estado', 'pendiente'))
            ->when($this->tipo, fn ($q) => $q->whereHas('leaveRequest', fn ($q2) => $q2->where('tipo', $this->tipo)))
            ->latest('created_at')
            ->simplePaginate(10);
    }

    #[Computed]
    public function historial()
    {
        return LeaveApproval::query()
            ->with(['leaveRequest.user'])
            ->where('aprobador_id', auth()->id())
            ->where('accion', '!=', 'pendiente')
            ->latest('fecha_accion')
            ->simplePaginate(10);
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Aprobaciones</flux:heading>
            <flux:text>Revisa y aprueba las solicitudes que requieren tu firma biométrica.</flux:text>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-1 rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800">
            <flux:button size="sm" variant="ghost" wire:click="$set('filtro', 'pendientes')"
                :class="$filtro === 'pendientes' ? '!bg-white !text-zinc-900 shadow-sm dark:!bg-zinc-700 dark:!text-white' : '!text-zinc-500'">
                Pendientes ({{ $this->pendientes->count() }})
            </flux:button>
            <flux:button size="sm" variant="ghost" wire:click="$set('filtro', 'historial')"
                :class="$filtro === 'historial' ? '!bg-white !text-zinc-900 shadow-sm dark:!bg-zinc-700 dark:!text-white' : '!text-zinc-500'">
                Historial
            </flux:button>
        </div>

        <flux:select wire:model.live="tipo" size="sm" class="w-auto">
            <option value="">Todos los tipos</option>
            <option value="permiso">Permiso</option>
            <option value="licencia">Licencia</option>
            <option value="vacacion">Vacación</option>
        </flux:select>
    </div>

    @if ($filtro === 'pendientes')
        <div class="w-full space-y-3">
            @forelse ($this->pendientes as $aprobacion)
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" color="amber">Pendiente</flux:badge>
                                <flux:badge size="sm" :color="match($aprobacion->leaveRequest->tipo) {
                                    'vacacion' => 'purple',
                                    'licencia' => 'sky',
                                    default => 'indigo',
                                }">{{ str($aprobacion->leaveRequest->tipo)->ucfirst() }}</flux:badge>
                                @if ($aprobacion->leaveRequest->getSlaVencidoAttribute() && $aprobacion->leaveRequest->estado === 'pendiente')
                                    <flux:badge size="sm" color="red">SLA vencido</flux:badge>
                                @elseif ($aprobacion->leaveRequest->estado === 'pendiente')
                                    <flux:badge size="sm" color="amber">
                                        {{ $aprobacion->leaveRequest->getHorasRestantesSlaAttribute() }} h restantes
                                    </flux:badge>
                                @endif
                            </div>

                            <p class="mt-2 font-medium">{{ $aprobacion->leaveRequest->user->nombre_completo }}</p>
                            <p class="text-sm text-zinc-500">
                                {{ $aprobacion->leaveRequest->motivo ?: 'Sin motivo' }} ·
                                {{ $aprobacion->leaveRequest->fecha_desde->format('d/m/Y') }}
                                @if ($aprobacion->leaveRequest->fecha_desde->ne($aprobacion->leaveRequest->fecha_hasta))
                                    → {{ $aprobacion->leaveRequest->fecha_hasta->format('d/m/Y') }}
                                @endif
                            </p>
                            <p class="text-xs text-zinc-400">
                                {{ $aprobacion->leaveRequest->dias_habiles }} día(s) hábil(es) · {{ $aprobacion->leaveRequest->user->area?->nombre }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <flux:button size="sm" variant="primary" :href="route('solicitudes.show', $aprobacion->leave_request_id)" wire:navigate>
                                Revisar y aprobar
                            </flux:button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-300 py-16">
                    <flux:icon.check-circle class="size-10 text-zinc-300" />
                    <flux:text>No tienes solicitudes pendientes de aprobación.</flux:text>
                </div>
            @endforelse

            <div>
                {{ $this->pendientes->links() }}
            </div>
        </div>
    @else
        <div class="w-full space-y-3">
            @forelse ($this->historial as $aprobacion)
                <a href="{{ route('solicitudes.show', $aprobacion->leave_request_id) }}" wire:navigate
                    class="block rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" :color="match($aprobacion->accion) {
                                    'aprobado' => 'green',
                                    default => 'red',
                                }">{{ str($aprobacion->accion)->ucfirst() }}</flux:badge>
                                <flux:badge size="sm" color="zinc">{{ str($aprobacion->leaveRequest->tipo)->ucfirst() }}</flux:badge>
                            </div>
                            <p class="mt-2 font-medium">{{ $aprobacion->leaveRequest->user->nombre_completo }}</p>
                            <p class="text-xs text-zinc-500">{{ $aprobacion->leaveRequest->motivo ?: 'Sin motivo' }}</p>
                        </div>
                        <flux:text size="xs" class="!text-zinc-400">{{ $aprobacion->fecha_accion?->format('d/m/Y H:i') }}</flux:text>
                    </div>
                </a>
            @empty
                <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-300 py-16">
                    <flux:text>Aún no has aprobado ni rechazado solicitudes.</flux:text>
                </div>
            @endforelse

            <div>
                {{ $this->historial->links() }}
            </div>
        </div>
    @endif
</div>