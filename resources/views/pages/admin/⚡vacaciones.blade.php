<?php

use App\Models\User;
use App\Models\VacationBalance;
use App\Models\VacationConfig;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Configuración de vacaciones | DELTA SGA')] class extends Component {
    use WithPagination;

    public int $gestion = 0;
    public string $busqueda = '';

    public array $configForm = [
        'gestion' => 0,
        'dias_por_ley' => 15,
        'fecha_limite_toma' => null,
        'activo' => true,
    ];

    public function mount(): void
    {
        $this->gestion = now()->year;
        $this->cargarConfig();
    }

    #[Computed]
    public function configuracion()
    {
        return VacationConfig::where('gestion', $this->gestion)->first();
    }

    #[Computed]
    public function gestiones()
    {
        return collect(range(now()->year - 1, now()->year + 1))->sortDesc()->values();
    }

    #[Computed]
    public function balances()
    {
        return VacationBalance::query()
            ->with('user.area')
            ->where('gestion', $this->gestion)
            ->when($this->busqueda, fn ($q) => $q->whereHas('user', fn ($q2) => $q2
                ->where('name', 'like', "%{$this->busqueda}%")
                ->orWhere('email', 'like', "%{$this->busqueda}%")))
            ->orderByDesc('dias_pendientes')
            ->paginate(15);
    }

    private function cargarConfig(): void
    {
        $config = VacationConfig::where('gestion', $this->gestion)->first();

        $this->configForm = [
            'gestion' => $this->gestion,
            'dias_por_ley' => $config?->dias_por_ley ?? 15,
            'fecha_limite_toma' => $config?->fecha_limite_toma?->format('Y-m-d'),
            'activo' => $config?->activo ?? true,
        ];
    }

    public function actualizarGestion(int $gestion): void
    {
        $this->gestion = $gestion;
        $this->cargarConfig();
        $this->resetPage();
    }

    public function guardarConfiguracion(): void
    {
        $this->validate([
            'configForm.dias_por_ley' => ['required', 'integer', 'min:1', 'max:60'],
            'configForm.fecha_limite_toma' => ['nullable', 'date'],
        ]);

        VacationConfig::updateOrCreate(
            ['gestion' => $this->gestion],
            [
                'dias_por_ley' => $this->configForm['dias_por_ley'],
                'fecha_limite_toma' => $this->configForm['fecha_limite_toma']
                    ? Carbon::parse($this->configForm['fecha_limite_toma'])
                    : null,
                'activo' => $this->configForm['activo'],
            ],
        );

        Flux::toast(variant: 'success', text: 'Configuración guardada.');
    }

    public function recalcularBalances(): void
    {
        foreach (User::all() as $user) {
            VacationBalance::crearOActualizarParaUsuario($user, $this->gestion);
        }

        Flux::toast(variant: 'success', text: 'Balances recalculados para todos los usuarios.');
    }

    public function ajustarBalance(int $id, string $campo, int $valor): void
    {
        $balance = VacationBalance::findOrFail($id);

        $balance->update([
            $campo => max(0, $valor),
        ]);
        $balance->recalcular();

        Flux::toast(variant: 'success', text: 'Balance actualizado.');
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Vacaciones y balances</flux:heading>
            <flux:text>Configura los días por ley y revisa los saldos de vacaciones por gestión.</flux:text>
        </div>
        <flux:select wire:model="gestion" size="sm" class="w-auto" x-on:change="$wire.actualizarGestion($el.value)">
            @foreach ($this->gestiones as $anio)
                <option value="{{ $anio }}" @selected($anio === $this->gestion)>{{ $anio }}</option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <flux:card class="lg:col-span-1">
            <flux:heading size="sm">Configuración de {{ $this->gestion }}</flux:heading>

            <div class="mt-4 space-y-4">
                <flux:input type="number" wire:model="configForm.dias_por_ley" :label="'Días de vacaciones por año'" min="1" max="60" />
                <flux:input type="date" wire:model="configForm.fecha_limite_toma" :label="'Fecha límite de toma'" />
                <flux:switch wire:model="configForm.activo" :label="'Configuración activa'" />
            </div>

            <div class="mt-5 flex flex-col gap-2">
                <flux:button variant="primary" wire:click="guardarConfiguracion">Guardar configuración</flux:button>
                <flux:button variant="outline" wire:click="recalcularBalances">
                    <flux:icon.arrow-path class="size-4" />
                    Recalcular balances
                </flux:button>
            </div>
        </flux:card>

        <div class="lg:col-span-2">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <flux:text class="font-medium">Balances de vacaciones {{ $this->gestion }}</flux:text>
                <flux:input wire:model.live.debounce.300ms="busqueda" icon="magnifying-glass" placeholder="Buscar usuario..." class="w-56" />
            </div>

            <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-start text-xs font-semibold text-zinc-500">Empleado</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500">Años serv.</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500">Totales</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500">Tomados</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500">Pendientes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->balances as $balance)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $balance->user->nombre_completo }}</p>
                                    <p class="text-xs text-zinc-500">{{ $balance->user->email }}</p>
                                </td>
                                <td class="px-4 py-3 text-center text-sm">{{ $balance->anios_servicio }}</td>
                                <td class="px-4 py-3 text-center text-sm">
                                    <input type="number" min="0" value="{{ $balance->dias_totales }}"
                                        x-on:change="$wire.ajustarBalance({{ $balance->id }}, 'dias_totales', $el.value) ? null : null"
                                        class="w-16 rounded-lg border border-zinc-300 px-2 py-1 text-center text-sm dark:border-zinc-600 dark:bg-zinc-800" />
                                </td>
                                <td class="px-4 py-3 text-center text-sm">
                                    <input type="number" min="0" value="{{ $balance->dias_tomados }}"
                                        x-on:change="$wire.ajustarBalance({{ $balance->id }}, 'dias_tomados', $el.value) ? null : null"
                                        class="w-16 rounded-lg border border-zinc-300 px-2 py-1 text-center text-sm dark:border-zinc-600 dark:bg-zinc-800" />
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <flux:badge :color="$balance->dias_pendientes > 0 ? 'green' : 'amber'" size="sm">
                                        {{ $balance->dias_pendientes }} d.
                                    </flux:badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-zinc-500">
                                    No hay balances para {{ $this->gestion }}. Ejecuta "Recalcular balances".
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $this->balances->links() }}
            </div>
        </div>
    </div>
</div>