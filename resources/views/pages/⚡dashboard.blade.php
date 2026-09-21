<?php

use App\Models\LeaveApproval;
use App\Models\LeaveRequest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard | DELTA SGA')] class extends Component {
    #[Computed]
    public function misSolicitudes()
    {
        return LeaveRequest::query()
            ->with('aprobaciones')
            ->where('user_id', auth()->id())
            ->latest()
            ->take(5)
            ->get();
    }

    #[Computed]
    public function pendienteConteo()
    {
        return LeaveApproval::query()
            ->where('aprobador_id', auth()->id())
            ->where('accion', 'pendiente')
            ->whereHas('leaveRequest', fn ($q) => $q->where('estado', 'pendiente'))
            ->count();
    }

    #[Computed]
    public function misResumen()
    {
        return [
            'aprobadas' => LeaveRequest::where('user_id', auth()->id())->where('estado', 'aprobado')->count(),
            'pendientes' => LeaveRequest::where('user_id', auth()->id())->where('estado', 'pendiente')->count(),
            'rechazadas' => LeaveRequest::where('user_id', auth()->id())->where('estado', 'rechazado')->count(),
        ];
    }

    #[Computed]
    public function saldoVacaciones()
    {
        return auth()->user()->vacationBalances()
            ->where('gestion', now()->year)
            ->first();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 p-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="relative flex flex-col justify-between gap-2 overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">Solicitudes aprobadas</flux:heading>
                <flux:icon.check-circle class="size-5 text-green-600" />
            </div>
            <p class="text-3xl font-bold">{{ $this->misResumen['aprobadas'] }}</p>
            <flux:text size="xs" class="!text-zinc-400">En lo que va del registro</flux:text>
        </div>

        <div class="relative flex flex-col justify-between gap-2 overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">Pendientes por aprobar</flux:heading>
                <flux:icon.clock class="size-5 text-amber-600" />
            </div>
            <p class="text-3xl font-bold">{{ $this->pendienteConteo }}</p>
            <flux:text size="xs" class="!text-zinc-400">Requieren tu firma biométrica</flux:text>
        </div>

        <div class="relative flex flex-col justify-between gap-2 overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">Rechazadas</flux:heading>
                <flux:icon.x-mark class="size-5 text-red-600" />
            </div>
            <p class="text-3xl font-bold">{{ $this->misResumen['rechazadas'] }}</p>
            <flux:text size="xs" class="!text-zinc-400">De tus solicitudes</flux:text>
        </div>

        <div class="relative flex flex-col justify-between gap-2 overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">Saldo de vacaciones</flux:heading>
                <flux:icon.sun class="size-5 text-brand-600" />
            </div>
            <p class="text-3xl font-bold">{{ $this->saldoVacaciones?->dias_pendientes ?? 0 }}<span class="text-base font-medium text-zinc-400">/{{ $this->saldoVacaciones?->dias_totales ?? 0 }} d.</span></p>
            <flux:text size="xs" class="!text-zinc-400">Disponible este año</flux:text>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="sm">Mis últimas solicitudes</flux:heading>
                <flux:button size="xs" variant="ghost" :href="route('solicitudes.index')" wire:navigate>Ver todas</flux:button>
            </div>
            <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->misSolicitudes as $solicitud)
                    <a href="{{ route('solicitudes.show', $solicitud) }}" wire:navigate
                        class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-medium">{{ str($solicitud->tipo)->ucfirst() }}</p>
                                <flux:badge size="sm" :color="match($solicitud->estado) {
                                    'aprobado' => 'green',
                                    'rechazado' => 'red',
                                    'pendiente' => 'amber',
                                    default => 'zinc',
                                }">{{ str($solicitud->estado)->replace('_', ' ')->ucfirst() }}</flux:badge>
                            </div>
                            <p class="truncate text-sm text-zinc-500">{{ $solicitud->fecha_desde->format('d/m/Y') }} · {{ $solicitud->motivo ?: 'Sin motivo' }}</p>
                        </div>
                        <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-400" />
                    </a>
                @empty
                    <div class="flex flex-col items-center gap-2 py-10 text-center">
                        <flux:text>No tienes solicitudes aún.</flux:text>
                        <flux:button size="sm" variant="primary" :href="route('solicitudes.create')" wire:navigate>Crear solicitud</flux:button>
                    </div>
                @endforelse
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="sm">Acciones rápidas</flux:heading>
            </div>
            <div class="mt-4 grid gap-3">
                <flux:button variant="primary" icon="plus" :href="route('solicitudes.create')" wire:navigate>
                    Registrar nueva solicitud
                </flux:button>
                @if ($this->pendienteConteo > 0)
                    <flux:button variant="outline" icon="check" :href="route('aprobaciones.index')" wire:navigate>
                        Revisar {{ $this->pendienteConteo }} solicitud(es) pendiente(s)
                    </flux:button>
                @endif
                <flux:button variant="ghost" icon="calendar-days" :href="route('admin.vacaciones')" wire:navigate>
                    Ver mis vacaciones y balances
                </flux:button>
            </div>
        </flux:card>
    </div>
</div>