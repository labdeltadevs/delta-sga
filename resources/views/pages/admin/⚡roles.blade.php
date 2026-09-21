<?php

use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Title('Roles y permisos | DELTA SGA')] class extends Component {
    public bool $mostrandoForm = false;
    public ?int $editandoId = null;
    public string $nombre = '';
    public array $permisosSeleccionados = [];
    public array $seleccion = [];

    #[Computed]
    public function roles()
    {
        return Role::with('permissions')->orderBy('name')->get();
    }

    #[Computed]
    public function permisosAgrupados()
    {
        return Permission::orderBy('name')->get()
            ->groupBy(fn (Permission $p) => Str::before($p->name, '.'))
            ->map(fn ($permisos, $recurso) => [
                'recurso' => Str::lower($recurso),
                'etiqueta' => Str::headline($recurso),
                'permisos' => $permisos,
            ])
            ->values();
    }

    public function nuevo(): void
    {
        $this->reset('editandoId', 'nombre', 'permisosSeleccionados');
        $this->mostrandoForm = true;
    }

    public function editar(int $id): void
    {
        $role = Role::with('permissions')->findOrFail($id);
        $this->editandoId = $role->id;
        $this->nombre = $role->name;
        $this->permisosSeleccionados = $role->permissions->pluck('name')->all();
        $this->mostrandoForm = true;
    }

    public function alternarPermiso(string $permiso): void
    {
        if (($this->permisosSeleccionados[$permiso] ?? false) === true) {
            unset($this->permisosSeleccionados[$permiso]);
        } else {
            $this->permisosSeleccionados[$permiso] = true;
        }
    }

    public function seleccionarTodoDelRecurso(string $recurso): void
    {
        $permisos = Permission::where('name', 'like', "$recurso%")->pluck('name')->all();
        $todosMarcados = collect($permisos)->every(fn ($p) => ($this->permisosSeleccionados[$p] ?? false) === true);

        foreach ($permisos as $permiso) {
            if ($todosMarcados) {
                unset($this->permisosSeleccionados[$permiso]);
            } else {
                $this->permisosSeleccionados[$permiso] = true;
            }
        }
    }

    public function guardar(): void
    {
        $validated = $this->validate([
            'nombre' => ['required', 'string', 'max:255', 'alpha_dash'],
        ]);

        $nombre = Str::lower($validated['nombre']);

        $role = $this->editandoId
            ? Role::findOrFail($this->editandoId)
            : Role::findOrCreate($nombre, 'web');

        if (! $this->editandoId && ($role->wasRecentlyCreated === false)) {
            $this->addError('nombre', 'Ya existe un rol con ese nombre.');

            return;
        }

        $role->update(['name' => $nombre]);
        $role->syncPermissions(array_keys($this->permisosSeleccionados));

        Flux::toast(variant: 'success', text: 'Rol guardado.');
        $this->resetForm();
    }

    public function confirmarEliminar(int $id): void
    {
        $role = Role::findOrFail($id);

        if ($role->users()->exists()) {
            Flux::toast(variant: 'danger', text: 'No se puede eliminar un rol asignado a usuarios.');

            return;
        }

        if (in_array($role->name, ['superadmin', 'admin', 'supervisor', 'trabajador'])) {
            Flux::toast(variant: 'danger', text: 'No se puede eliminar un rol del sistema.');

            return;
        }

        $role->delete();
        Flux::toast(variant: 'success', text: 'Rol eliminado.');
    }

    public function resetForm(): void
    {
        $this->reset('mostrandoForm', 'editandoId', 'nombre', 'permisosSeleccionados');
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Roles y permisos</flux:heading>
            <flux:text>Define los roles del sistema y qué permisos controlan.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="nuevo">Nuevo rol</flux:button>
    </div>

    @if ($mostrandoForm)
        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ $editandoId ? 'Editar rol' : 'Nuevo rol' }}</flux:heading>
                <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="resetForm" />
            </div>

            <div class="mt-4 max-w-sm">
                <flux:input wire:model="nombre" :label="'Nombre del rol'" placeholder="ej: gerente_regional" required />
            </div>

            <flux:heading size="sm" class="mt-6 mb-3">Permisos</flux:heading>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->permisosAgrupados as $grupo)
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <p class="font-medium capitalize">{{ $grupo['etiqueta'] }}</p>
                            <flux:button size="xs" variant="ghost" wire:click="seleccionarTodoDelRecurso('{{ $grupo['recurso'] }}')">
                                Seleccionar todo
                            </flux:button>
                        </div>
                        <div class="mt-3 space-y-2">
                            @foreach ($grupo['permisos'] as $permiso)
                                <label class="flex cursor-pointer items-start gap-2 text-sm" wire:key="permiso-{{ $permiso->id }}">
                                    <flux:checkbox
                                        wire:model="permisosSeleccionados.{{ $permiso->name }}"
                                        wire:key="cb-{{ $permiso->id }}"
                                    />
                                    <span>{{ Str::headline(Str::after($permiso->name, '.')) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="resetForm">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="guardar">Guardar rol</flux:button>
            </div>
        </flux:card>
    @endif

    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->roles as $role)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold capitalize">{{ $role->name }}</p>
                        <p class="text-xs text-zinc-500">{{ $role->permissions->count() }} permisos · {{ $role->users()->count() }} usuarios</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editar({{ $role->id }})" />
                        <flux:button size="xs" variant="ghost" icon="trash" x-on:click="if (confirm('¿Eliminar el rol {{ $role->name }}?')) $wire.confirmarEliminar({{ $role->id }})" />
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach ($role->permissions->take(6) as $permiso)
                        <flux:badge size="sm" variant="outline">{{ Str::after($permiso->name, '.') }}</flux:badge>
                    @endforeach
                    @if ($role->permissions->count() > 6)
                        <flux:badge size="sm">+{{ $role->permissions->count() - 6 }}</flux:badge>
                    @endif
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-300 py-16 md:col-span-3">
                <flux:text>No hay roles registrados.</flux:text>
            </div>
        @endforelse
    </div>
</div>