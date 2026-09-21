{{-- Nodo recursivo para árbol vertical/colapsable. Recibe $area, $depth --}}
@php
    $isExpanded = in_array($area->id, $this->expandedIds, true);
    $hasChildren = ($area->children_count ?? $area->childrenTree?->count() ?? 0) > 0;
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
    $dotBorderClass = $isExpanded ? 'border-zinc-600 dark:border-zinc-300' : 'border-zinc-400 dark:border-zinc-500';
@endphp
<div class="relative group">
    @if ($depth > 0)
        <div class="pointer-events-none absolute left-[15px] top-0 bottom-3 w-0.5 rounded-full {{ $lineClass }} transition-colors"></div>
        <div class="pointer-events-none absolute left-[15px] top-[24px] h-0.5 w-6 rounded-full {{ $lineClass }} transition-colors"></div>
        <div class="pointer-events-none absolute left-[13px] top-[20px] size-2 rounded-full border-2 {{ $dotBorderClass }} bg-white transition-colors dark:bg-zinc-900"></div>
    @endif

    <div @class([
        'group relative rounded-xl border bg-white shadow-sm transition dark:bg-zinc-900',
        'border-zinc-200 dark:border-zinc-700' => $area->activo,
        'border-dashed border-zinc-300 bg-zinc-50/70 dark:border-zinc-600 dark:bg-zinc-800/40 opacity-75' => ! $area->activo,
        'ms-6' => $depth > 0,
        'hover:border-zinc-300 dark:hover:border-zinc-500' => $area->activo,
    ])>
        <div class="flex items-center gap-3 p-3 sm:p-4">
            @if ($hasChildren)
                <button
                    type="button"
                    wire:click="toggle({{ $area->id }})"
                    aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                    aria-label="{{ $isExpanded ? 'Colapsar' : 'Expandir' }} {{ $area->nombre }}"
                    class="flex size-7 shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-500 hover:bg-zinc-50 hover:text-zinc-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700"
                >
                    <flux:icon.chevron-right @class(['size-4 transition-transform', 'rotate-90' => $isExpanded]) />
                </button>
            @else
                <div class="flex size-7 shrink-0 items-center justify-center">
                    <span class="size-1.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                </div>
            @endif

            <div class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-zinc-100 text-xs font-semibold text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                @if ($area->head?->avatar)
                    <img src="{{ $area->head->avatar }}" alt="{{ $area->head->name }}" class="size-full object-cover" />
                @else
                    {{ $area->head ? $area->head->iniciales : '—' }}
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5">
                    <p class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $area->nombre }}</p>
                    <flux:badge size="sm" :color="$tipoColor" class="shrink-0">{{ $tipoLabel }}</flux:badge>
                    @if (! $area->activo)
                        <flux:badge size="sm" color="zinc">Inactiva</flux:badge>
                    @endif
                </div>
                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="font-mono">{{ $area->codigo }}</span>
                    <span class="hidden sm:inline">·</span>
                    <span class="truncate">{{ $area->head?->nombre_completo ?? $area->head?->name ?? 'Sin responsable' }}</span>
                    <span>·</span>
                    <span class="inline-flex items-center gap-1">
                        <flux:icon.folder class="size-3" /> {{ $area->children_count }} sub
                    </span>
                    <span>·</span>
                    <span class="inline-flex items-center gap-1">
                        <flux:icon.users class="size-3" /> {{ $area->users_count }} usuarios
                    </span>
                    <span class="hidden sm:inline">· orden {{ $area->orden }}</span>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-1.5">
                <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editar({{ $area->id }})" :label="'Editar'" />
                <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmarEliminar({{ $area->id }})" class="text-red-500 hover:text-red-600" />
            </div>
        </div>
    </div>

    @if ($hasChildren && $isExpanded)
        <div class="relative mt-3 space-y-3 pl-6">
            @foreach ($children as $child)
                @include('pages.admin.partials.nodo-area', ['area' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
