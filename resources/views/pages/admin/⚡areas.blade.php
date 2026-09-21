<?php

use App\Models\Area;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Áreas | DELTA SGA')] class extends Component {
    public bool $mostrandoForm = false;
    public bool $mostrandoEliminar = false;
    public ?int $editandoId = null;
    public ?Area $eliminandoArea = null;

    public string $nombre = '';
    public string $codigo = '';
    public ?int $parent_id = null;
    public ?int $head_user_id = null;
    public string $tipo = 'administrativa';
    public int $orden = 0;
    public bool $activo = true;

    /** @var array<int> */
    public array $expandedIds = [];

    public string $vista = 'auto'; // auto|vertical|horizontal

    #[Computed]
    public function arbol(): Collection
    {
        $all = Area::with(['head'])
            ->withCount(['children', 'users'])
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $grouped = $all->groupBy(fn (Area $a) => $a->parent_id ?? 'root');

        return $this->buildTree('root', $grouped, 0, []);
    }

    /**
     * @param  Collection<string, Collection<int, Area>>  $grouped
     * @param  array<int>  $visited
     * @return Collection<int, Area>
     */
    private function buildTree(string|int $parentKey, Collection $grouped, int $depth, array $visited): Collection
    {
        $nodes = $grouped->get($parentKey, collect())->sortBy(['orden', 'nombre']);

        return $nodes->map(function (Area $area) use ($grouped, $depth, $visited) {
            if (in_array($area->id, $visited, true)) {
                $area->setRelation('childrenTree', collect());

                return $area;
            }

            $newVisited = [...$visited, $area->id];
            $children = $this->buildTree($area->id, $grouped, $depth + 1, $newVisited);
            $area->setRelation('childrenTree', $children);

            return $area;
        })->values();
    }

    #[Computed]
    public function stats(): array
    {
        $all = Area::query()->selectRaw('count(*) as total, count(case when parent_id is null then 1 end) as raices, count(case when activo = false then 1 end) as inactivas')->first();

        return [
            'total' => (int) ($all->total ?? 0),
            'raices' => (int) ($all->raices ?? 0),
            'inactivas' => (int) ($all->inactivas ?? 0),
            'profundidad' => $this->maxDepth($this->arbol),
        ];
    }

    private function maxDepth(Collection $nodes, int $current = 1): int
    {
        if ($nodes->isEmpty()) {
            return $current - 1;
        }

        $max = $current;
        foreach ($nodes as $node) {
            $children = $node->getRelation('childrenTree') ?? collect();
            if ($children->isNotEmpty()) {
                $d = $this->maxDepth($children, $current + 1);
                $max = max($max, $d);
            }
        }

        return $max;
    }

    #[Computed]
    public function areasParaSelect(): Collection
    {
        $tree = $this->arbol;
        $flat = collect();
        $this->flattenTree($tree, 0, $flat);

        if ($this->editandoId) {
            $editada = Area::find($this->editandoId);
            $excluir = $editada ? $this->collectDescendantIds($editada, $tree) : [];
            $excluir[] = $this->editandoId;
            $flat = $flat->reject(fn (array $item) => in_array($item['area']->id, $excluir, true))->values();
        }

        return $flat;
    }

    private function flattenTree(Collection $nodes, int $depth, Collection $flat): void
    {
        foreach ($nodes as $node) {
            $flat->push(['area' => $node, 'depth' => $depth]);
            $children = $node->getRelation('childrenTree') ?? collect();
            if ($children->isNotEmpty()) {
                $this->flattenTree($children, $depth + 1, $flat);
            }
        }
    }

    /** @return array<int> */
    private function collectDescendantIds(Area $target, Collection $tree): array
    {
        $ids = [];
        $this->findAndCollect($tree, $target->id, $ids);

        return $ids;
    }

    private function findAndCollect(Collection $nodes, int $targetId, array &$ids): bool
    {
        foreach ($nodes as $node) {
            if ($node->id === $targetId) {
                $this->collectAllIds($node->getRelation('childrenTree') ?? collect(), $ids);

                return true;
            }
            $children = $node->getRelation('childrenTree') ?? collect();
            if ($children->isNotEmpty() && $this->findAndCollect($children, $targetId, $ids)) {
                return true;
            }
        }

        return false;
    }

    private function collectAllIds(Collection $nodes, array &$ids): void
    {
        foreach ($nodes as $node) {
            $ids[] = $node->id;
            $children = $node->getRelation('childrenTree') ?? collect();
            if ($children->isNotEmpty()) {
                $this->collectAllIds($children, $ids);
            }
        }
    }

    #[Computed]
    public function usuarios()
    {
        return User::orderBy('name')->get(['id', 'name', 'nombre1', 'apellido1', 'avatar']);
    }

    public function toggle(int $id): void
    {
        if (in_array($id, $this->expandedIds, true)) {
            $this->expandedIds = array_values(array_diff($this->expandedIds, [$id]));
        } else {
            $this->expandedIds[] = $id;
        }
    }

    public function expandirTodo(): void
    {
        $all = Area::whereNotNull('parent_id')->pluck('id');
        $raicesConHijos = Area::whereNull('parent_id')->whereHas('children')->pluck('id');
        $this->expandedIds = $all->merge($raicesConHijos)->unique()->values()->all();
    }

    public function colapsarTodo(): void
    {
        $this->expandedIds = [];
    }

    public function setVista(string $vista): void
    {
        if (in_array($vista, ['auto', 'vertical', 'horizontal'], true)) {
            $this->vista = $vista;
        }
    }

    public function nuevo(): void
    {
        $this->reset('editandoId', 'nombre', 'codigo', 'parent_id', 'head_user_id', 'tipo', 'orden', 'activo');
        $this->activo = true;
        $this->mostrandoForm = true;
    }

    public function editar(int $id): void
    {
        $area = Area::findOrFail($id);

        $this->editandoId = $area->id;
        $this->nombre = $area->nombre;
        $this->codigo = $area->codigo;
        $this->parent_id = $area->parent_id;
        $this->head_user_id = $area->head_user_id;
        $this->tipo = $area->tipo;
        $this->orden = $area->orden;
        $this->activo = $area->activo;
        unset($this->areasParaSelect);
        $this->mostrandoForm = true;
    }

    public function guardar(): void
    {
        $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'codigo' => ['required', 'string', 'max:20', 'alpha_num'],
            'parent_id' => ['nullable', 'exists:areas,id'],
            'head_user_id' => ['nullable', 'exists:users,id'],
            'tipo' => ['required', 'in:administrativa,comercial,tic,produccion,gerencia_regional,equipo_ventas,area_produccion'],
            'orden' => ['required', 'integer', 'min:0'],
        ]);

        if ($this->editandoId && $this->parent_id === $this->editandoId) {
            $this->addError('parent_id', 'Un área no puede ser su propio padre.');

            return;
        }

        if ($this->editandoId && $this->parent_id) {
            $editada = Area::find($this->editandoId);
            $padre = Area::find($this->parent_id);
            if ($editada && $padre && $editada->isAncestorOf($padre)) {
                $this->addError('parent_id', 'No puede asignar un descendiente como padre — crearía un ciclo.');

                return;
            }
        }

        $datos = [
            'nombre' => $this->nombre,
            'codigo' => strtoupper($this->codigo),
            'parent_id' => $this->parent_id,
            'head_user_id' => $this->head_user_id,
            'tipo' => $this->tipo,
            'orden' => $this->orden,
            'activo' => $this->activo,
        ];

        if ($this->editandoId) {
            Area::findOrFail($this->editandoId)->update($datos);
            Flux::toast(variant: 'success', text: 'Área actualizada.');
        } else {
            Area::create($datos);
            Flux::toast(variant: 'success', text: 'Área creada.');
        }

        $this->resetForm();
        $this->mostrandoForm = false;
    }

    public function confirmarEliminar(int $id): void
    {
        $this->eliminandoArea = Area::withCount(['children', 'users'])->findOrFail($id);
        $this->mostrandoEliminar = true;
    }

    public function eliminar(): void
    {
        if (! $this->eliminandoArea) {
            return;
        }

        if ($this->eliminandoArea->children_count > 0) {
            $this->eliminandoArea = null;
            $this->mostrandoEliminar = false;
            Flux::toast(variant: 'danger', text: 'No se puede eliminar un área con áreas hijas.');

            return;
        }

        if ($this->eliminandoArea->users_count > 0) {
            $this->eliminandoArea = null;
            $this->mostrandoEliminar = false;
            Flux::toast(variant: 'danger', text: 'No se puede eliminar un área con usuarios asignados.');

            return;
        }

        $this->eliminandoArea->delete();
        $this->eliminandoArea = null;
        $this->mostrandoEliminar = false;
        Flux::toast(variant: 'success', text: 'Área eliminada.');
    }

    public function resetForm(): void
    {
        $this->reset('editandoId', 'nombre', 'codigo', 'parent_id', 'head_user_id', 'tipo', 'orden', 'activo');
        $this->resetValidation();
        unset($this->areasParaSelect);
        $this->mostrandoForm = false;
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Áreas</flux:heading>
            <flux:text>Jerarquía de áreas del laboratorio — organigrama con ramas y responsables.</flux:text>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <flux:badge size="sm" variant="outline">{{ $this->stats['raices'] }} raíces</flux:badge>
                <flux:badge size="sm" variant="outline">{{ $this->stats['total'] }} total</flux:badge>
                @if ($this->stats['inactivas'] > 0)
                    <flux:badge size="sm" color="zinc">{{ $this->stats['inactivas'] }} inactivas</flux:badge>
                @endif
                <flux:badge size="sm" variant="outline">prof. {{ $this->stats['profundidad'] }}</flux:badge>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- toggle vista --}}
            <div class="flex items-center gap-1 rounded-full border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:button size="xs" :variant="$this->vista === 'auto' ? 'primary' : 'ghost'" wire:click="setVista('auto')">Auto</flux:button>
                <flux:button size="xs" :variant="$this->vista === 'vertical' ? 'primary' : 'ghost'" icon="bars-3" wire:click="setVista('vertical')">Vertical</flux:button>
                <flux:button size="xs" :variant="$this->vista === 'horizontal' ? 'primary' : 'ghost'" icon="view-columns" wire:click="setVista('horizontal')">Horizontal</flux:button>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="nuevo">Nueva área</flux:button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <flux:button size="sm" variant="outline" icon="arrows-pointing-out" wire:click="expandirTodo">Expandir todo</flux:button>
        <flux:button size="sm" variant="ghost" icon="arrows-pointing-in" wire:click="colapsarTodo">Contraer todo</flux:button>
        <flux:text size="sm" class="text-zinc-500">Todo contraído por defecto — usa los chevrons para explorar ramas.</flux:text>
    </div>

    {{-- ÁRBOL VERTICAL --}}
    @php
        $vista = $this->vista;
        $showVertical = $vista === 'vertical' || $vista === 'auto';
        $showHorizontal = $vista === 'horizontal' || $vista === 'auto';
    @endphp

    <div @class([
        'rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/40',
        'block md:hidden' => $vista === 'auto',
        'block' => $vista === 'vertical',
        'hidden' => $vista === 'horizontal',
    ]) @if($vista === 'auto') data-auto-vertical @endif>
        <div class="mb-3 flex items-center gap-2">
            <flux:icon.bars-3 class="size-4 text-zinc-400" />
            <span class="text-xs font-semibold uppercase tracking-widest text-zinc-500">Árbol vertical</span>
        </div>
        @forelse ($this->arbol as $nodo)
            <div class="mb-2 last:mb-0">
                @include('pages.admin.partials.nodo-area', ['area' => $nodo, 'depth' => 0])
            </div>
        @empty
            <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-300 bg-white py-16 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:icon.folder class="size-10 text-zinc-300" />
                <flux:text>No hay áreas registradas.</flux:text>
            </div>
        @endforelse
    </div>

    {{-- ORGANIGRAMA HORIZONTAL --}}
    <div @class([
        'rounded-xl border border-zinc-200 bg-zinc-50 p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900',
        'hidden md:block' => $vista === 'auto',
        'block' => $vista === 'horizontal',
        'hidden' => $vista === 'vertical',
    ])>
        <div class="mb-3 flex items-center gap-2">
            <flux:icon.view-columns class="size-4 text-zinc-400" />
            <span class="text-xs font-semibold uppercase tracking-widest text-zinc-500">Organigrama horizontal</span>
            <span class="text-xs text-zinc-400">— desplaza para ver ramas</span>
        </div>
        @if ($this->arbol->isNotEmpty())
            <div class="overflow-x-auto pb-4">
                <div class="flex min-w-max items-start justify-start gap-6">
                    @foreach ($this->arbol as $nodo)
                        @include('pages.admin.partials.nodo-horizontal', ['area' => $nodo])
                    @endforeach
                </div>
            </div>
        @else
            <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-300 bg-white py-16 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:icon.folder class="size-10 text-zinc-300" />
                <flux:text>No hay áreas registradas.</flux:text>
            </div>
        @endif
    </div>

    {{-- Modal formulario --}}
    <flux:modal name="area-form" wire:model="mostrandoForm" class="max-w-2xl">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ $editandoId ? 'Editar área' : 'Nueva área' }}</flux:heading>
                <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="$set('mostrandoForm', false)" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="nombre" :label="'Nombre'" required />
                <flux:input wire:model="codigo" :label="'Código'" placeholder="ADM01" required />
                <flux:select wire:model="tipo" :label="'Tipo'">
                    <option value="administrativa">Administrativa</option>
                    <option value="comercial">Comercial</option>
                    <option value="tic">T.I.C.</option>
                    <option value="produccion">Producción</option>
                    <option value="gerencia_regional">Gerencia Regional</option>
                    <option value="equipo_ventas">Equipo de Ventas</option>
                    <option value="area_produccion">Área de Producción</option>
                </flux:select>
                <flux:input type="number" wire:model="orden" :label="'Orden'" min="0" />
                <flux:select wire:model="parent_id" :label="'Área padre'">
                    <option value="">Sin padre (raíz)</option>
                    @foreach ($this->areasParaSelect as $item)
                        <option value="{{ $item['area']->id }}">{{ str_repeat('— ', $item['depth']) }}{{ $item['area']->nombre }} ({{ $item['area']->codigo }})</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="head_user_id" :label="'Responsable (jefe)'">
                    <option value="">Sin responsable</option>
                    @foreach ($this->usuarios as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </flux:select>
                <flux:switch wire:model="activo" :label="'Activa'" />
            </div>
            @error('parent_id')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="resetForm">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="guardar">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="eliminar-area" wire:model="mostrandoEliminar" class="max-w-md">
        @if ($eliminandoArea)
            <div class="space-y-4">
                <flux:heading size="lg">¿Eliminar área?</flux:heading>
                <flux:text>¿Seguro que deseas eliminar "{{ $eliminandoArea->nombre }}"? Esta acción no se puede deshacer.</flux:text>
                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="$set('mostrandoEliminar', false)">Cancelar</flux:button>
                    <flux:button variant="danger" wire:click="eliminar">Eliminar</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
