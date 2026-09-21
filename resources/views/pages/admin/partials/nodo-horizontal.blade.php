{{-- Nodo horizontal (organigrama filas). Recibe $area --}}
@php
    $isExpanded = in_array($area->id, $this->expandedIds, true);
    $hasChildren = ($area->children_count ?? 0) > 0;
    $children = $area->childrenTree ?? collect();
    $tipoLabel = str($area->tipo)->replace('_', ' ')->ucfirst();
    $tipoColor = match ($area->tipo) {
        'administrativa' => 'zinc',
        'comercial' => 'blue',
        'tic' => 'violet',
        'produccion' => 'amber',
        'gerencia_regional' => 'teal',
        'equipo_ventas' => 'emerald',
        'area_produccion' => 'orange',
        default => 'zinc',
    };
    $lineClass = $isExpanded ? 'bg-zinc-600 dark:bg-zinc-300' : 'bg-zinc-400 dark:bg-zinc-500';
    $dotClass = $isExpanded ? 'border-zinc-600 dark:border-zinc-300' : 'border-zinc-400 dark:border-zinc-500';
@endphp
<div class="flex flex-col items-center gap-3">
    <div @class([
        'group relative w-[240px] shrink-0 rounded-xl border bg-white p-3 shadow-sm dark:bg-zinc-900',
        'border-zinc-200 dark:border-zinc-700' => $area->activo,
        'border-dashed border-zinc-300 bg-zinc-50/70 opacity-75 dark:border-zinc-600 dark:bg-zinc-800/40' => ! $area->activo,
    ])>
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1">
                    <p class="truncate text-sm font-semibold leading-tight text-zinc-900 dark:text-zinc-100">{{ $area->nombre }}</p>
                </div>
                <flux:badge size="sm" :color="$tipoColor" class="mt-1">{{ $tipoLabel }}</flux:badge>
                @if (! $area->activo)
                    <flux:badge size="sm" color="zinc" class="ms-1">Inactiva</flux:badge>
                @endif
            </div>
            @if ($hasChildren)
                <button
                    type="button"
                    wire:click="toggle({{ $area->id }})"
                    aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                    class="flex size-6 shrink-0 items-center justify-center rounded-md border border-zinc-200 bg-white text-zinc-500 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400"
                >
                    <flux:icon.chevron-down @class(['size-3.5 transition-transform', ' -rotate-90' => ! $isExpanded]) />
                </button>
            @endif
        </div>

        <p class="mt-1 truncate font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ $area->codigo }} · orden {{ $area->orden }}</p>

        <div class="mt-2 flex items-center gap-2 rounded-lg bg-zinc-50 px-2 py-1.5 dark:bg-zinc-800">
            <div class="flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-white text-[11px] font-semibold text-zinc-600 dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                @if ($area->head?->avatar)
                    <img src="{{ $area->head->avatar }}" alt="{{ $area->head->name }}" class="size-full object-cover" />
                @else
                    {{ $area->head ? $area->head->iniciales : '—' }}
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-medium leading-none text-zinc-700 dark:text-zinc-200">{{ $area->head?->nombre_completo ?? $area->head?->name ?? 'Sin responsable' }}</p>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $area->children_count }} sub · {{ $area->users_count }} usuarios</p>
            </div>
        </div>

        <div class="mt-2 flex justify-end gap-1">
            <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editar({{ $area->id }})" />
            <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmarEliminar({{ $area->id }})" class="text-red-500" />
        </div>
    </div>

    @if ($hasChildren && $isExpanded)
        <div class="flex items-start justify-center gap-4">
            {{-- tronco vertical desde padre hasta barra horizontal --}}
            <div class="h-6 w-0.5 rounded-full {{ $lineClass }} transition-colors"></div>
            {{-- barra horizontal que une ramas --}}
            <div class="flex flex-col items-center gap-3">
                @if ($children->count() > 1)
                    <div class="h-0.5 w-full rounded-full {{ $lineClass }} transition-colors"></div>
                @endif
                {{-- lista de hijas con gota en intersección --}}
                @foreach ($children as $child)
                    <div class="flex flex-col items-center gap-0">
                        <div class="relative h-6 w-0.5 rounded-full {{ $lineClass }} transition-colors">
                            <div class="absolute -top-[7px] left-1/2 size-2 -translate-x-1/2 rounded-full border-2 {{ $dotClass }} bg-white dark:bg-zinc-900"></div>
                        </div>
                        @include('pages.admin.partials.nodo-horizontal', ['area' => $child])
                    </div>
                @endforeach
            </div>
        </div>
    @elseif ($hasChildren && ! $isExpanded)
        <button type="button" wire:click="toggle({{ $area->id }})" class="rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-500 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
            +{{ $area->children_count }} ramas
        </button>
    @endif
</div>
