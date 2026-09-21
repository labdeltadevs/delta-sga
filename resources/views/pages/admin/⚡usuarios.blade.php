<?php

use App\Models\Area;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Title('Usuarios | DELTA SGA')] class extends Component {
    use WithPagination;

    public bool $mostrandoForm = false;
    public ?int $editandoId = null;

    public string $busqueda = '';
    public string $filtro_rol = '';

    public string $nombre1 = '';
    public ?string $nombre2 = null;
    public string $apellido1 = '';
    public ?string $apellido2 = null;
    public string $genero = 'M';
    public string $email = '';
    public ?string $fecha_contrato = null;
    public ?int $area_id = null;
    public ?int $superior_id = null;
    public string $password = '';
    public string $rol = 'trabajador';

    #[Computed]
    public function usuarios()
    {
        return User::query()
            ->with(['area', 'superior', 'roles'])
            ->when($this->busqueda, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$this->busqueda}%")
                ->orWhere('email', 'like', "%{$this->busqueda}%")
                ->orWhere('apellido1', 'like', "%{$this->busqueda}%")))
            ->when($this->filtro_rol, fn ($q) => $q->whereHas('roles', fn ($q2) => $q2->where('name', $this->filtro_rol)))
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function areas()
    {
        return Area::orderBy('orden')->get(['id', 'nombre']);
    }

    #[Computed]
    public function roles()
    {
        return Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name']);
    }

    public function nuevo(): void
    {
        $this->reset('editandoId', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'genero', 'email', 'fecha_contrato', 'area_id', 'superior_id', 'password', 'rol');
        $this->mostrandoForm = true;
    }

    public function editar(int $id): void
    {
        $user = User::with('roles')->findOrFail($id);

        $this->editandoId = $user->id;
        $this->nombre1 = $user->nombre1 ?? '';
        $this->nombre2 = $user->nombre2;
        $this->apellido1 = $user->apellido1 ?? '';
        $this->apellido2 = $user->apellido2;
        $this->genero = $user->genero ?? 'M';
        $this->email = $user->email;
        $this->fecha_contrato = $user->fecha_contrato?->format('Y-m-d');
        $this->area_id = $user->area_id;
        $this->superior_id = $user->superior_id;
        $this->password = '';
        $this->rol = $user->roles->first()?->name ?? 'trabajador';
        $this->mostrandoForm = true;
    }

    public function guardar(): void
    {
        $id = $this->editandoId;

        $validated = $this->validate([
            'nombre1' => ['required', 'string', 'max:255'],
            'apellido1' => ['required', 'string', 'max:255'],
            'genero' => ['required', 'in:M,F'],
            'email' => ['required', 'email', 'max:255', "unique:users,email,$id"],
            'fecha_contrato' => ['nullable', 'date'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'superior_id' => ['nullable', 'exists:users,id'],
            'password' => ['nullable', 'string', 'min:8'],
            'rol' => ['required', 'exists:roles,name'],
        ]);

        if ($id && $this->superior_id === $id) {
            $this->addError('superior_id', 'Un usuario no puede ser su propio superior.');

            return;
        }

        $datos = [
            'name' => trim("{$this->nombre1} {$this->nombre2} {$this->apellido1} {$this->apellido2}"),
            'nombre1' => $this->nombre1,
            'nombre2' => $this->nombre2 ?: null,
            'apellido1' => $this->apellido1,
            'apellido2' => $this->apellido2 ?: null,
            'genero' => $this->genero,
            'email' => $this->email,
            'fecha_contrato' => $this->fecha_contrato,
            'area_id' => $this->area_id,
            'superior_id' => $this->superior_id,
        ];

        if ($this->password) {
            $datos['password'] = $this->password;
        }

        $user = $id ? User::findOrFail($id) : new User();

        if (! $id) {
            $datos['email_verified_at'] = now();
        }

        $user->fill($datos)->save();
        $user->syncRoles([$this->rol]);

        Flux::toast(variant: 'success', text: $id ? 'Usuario actualizado.' : 'Usuario creado.');
        $this->resetForm();
    }

    public function confirmarEliminar(User $user): void
    {
        if ($user->id === auth()->id()) {
            Flux::toast(variant: 'danger', text: 'No puedes eliminar tu propia cuenta.');

            return;
        }

        $user->delete();
        Flux::toast(variant: 'success', text: 'Usuario eliminado.');
    }

    public function resetForm(): void
    {
        $this->reset('mostrandoForm', 'editandoId', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'genero', 'email', 'fecha_contrato', 'area_id', 'superior_id', 'password', 'rol');
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Usuarios</flux:heading>
            <flux:text>Administra cuentas, áreas, superiores y roles del personal.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="nuevo">Nuevo usuario</flux:button>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:input wire:model.live.debounce.300ms="busqueda" icon="magnifying-glass" placeholder="Buscar por nombre o email..." class="w-64" />
        <flux:select wire:model.live="filtro_rol" size="sm" class="w-auto">
            <option value="">Todos los roles</option>
            @foreach ($this->roles as $r)
                <option value="{{ $r->name }}">{{ ucfirst($r->name) }}</option>
            @endforeach
        </flux:select>
    </div>

    @if ($mostrandoForm)
        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ $editandoId ? 'Editar usuario' : 'Nuevo usuario' }}</flux:heading>
                <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="resetForm" />
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="nombre1" :label="'Primer nombre'" required />
                <flux:input wire:model="nombre2" :label="'Segundo nombre'" />
                <flux:input wire:model="apellido1" :label="'Primer apellido'" required />
                <flux:input wire:model="apellido2" :label="'Segundo apellido'" />
                <flux:select wire:model="genero" :label="'Género'">
                    <option value="M">Masculino</option>
                    <option value="F">Femenino</option>
                </flux:select>
                <flux:input type="email" wire:model="email" :label="'Email'" required />
                <flux:input type="date" wire:model="fecha_contrato" :label="'Fecha de contrato'" />
                <flux:select wire:model="area_id" :label="'Área'">
                    <option value="">Sin área</option>
                    @foreach ($this->areas as $a)
                        <option value="{{ $a->id }}">{{ $a->nombre }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="superior_id" :label="'Superior inmediato'">
                    <option value="">Sin superior</option>
                    @foreach (User::orderBy('name')->get(['id', 'name']) as $u)
                        @if ($editandoId !== $u->id)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endif
                    @endforeach
                </flux:select>
                <flux:select wire:model="rol" :label="'Rol'">
                    @foreach ($this->roles as $r)
                        <option value="{{ $r->name }}">{{ ucfirst($r->name) }}</option>
                    @endforeach
                </flux:select>
                <flux:input type="password" wire:model="password" :label="'Contraseña'" :placeholder="$editandoId ? 'Dejar vacío para no cambiar' : 'Mínimo 8 caracteres'" viewable />
            </div>

            <div class="mt-4 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="resetForm">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="guardar">Guardar</flux:button>
            </div>
        </flux:card>
    @endif

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-start text-xs font-semibold text-zinc-500">Usuario</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold text-zinc-500">Área</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold text-zinc-500">Superior</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold text-zinc-500">Rol</th>
                    <th class="px-4 py-3 text-end text-xs font-semibold text-zinc-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->usuarios as $user)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
                                    {{ $user->iniciales }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium">{{ $user->nombre_completo }}</p>
                                    <p class="truncate text-xs text-zinc-500">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm">{{ $user->area?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $user->superior?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="match($user->roles->first()?->name ?? '') {
                                'superadmin' => 'purple',
                                'admin' => 'red',
                                'supervisor' => 'amber',
                                default => 'zinc',
                            }">{{ $user->roles->first()?->name ?? 'Sin rol' }}</flux:badge>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editar({{ $user->id }})" />
                            <flux:button size="xs" variant="ghost" icon="trash" x-on:click="if (confirm('¿Eliminar a {{ $user->name }}?')) $wire.confirmarEliminar({{ $user->id }})" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-zinc-500">No hay usuarios que coincidan con la búsqueda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $this->usuarios->links() }}
    </div>
</div>