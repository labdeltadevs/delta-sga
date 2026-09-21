<?php

use App\Models\LeaveRequest;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Plantillas PDF | DELTA SGA')] class extends Component {
    public string $tipo = 'permiso';
    public string $contenido = '';
    public bool $guardando = false;

    private const LAYOUT_DIR = 'pdf/layouts';

    public function mount(): void
    {
        $this->cargarPlantilla('permiso');
    }

    public function updatedTipo(): void
    {
        $this->cargarPlantilla($this->tipo);
    }

    public function guardar(): void
    {
        $this->guardando = true;

        $path = resource_path(self::LAYOUT_DIR . '/' . $this->tipo . '.html');

        $directorio = dirname($path);
        if (! is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        file_put_contents($path, $this->contenido);

        $this->guardando = false;
        Flux::toast(variant: 'success', text: 'Plantilla guardada correctamente.');
    }

    public function restaurar(): void
    {
        $defaultPath = base_path('resources/views/pdf/layouts/' . $this->tipo . '.html');

        if (file_exists($defaultPath)) {
            $this->contenido = file_get_contents($defaultPath);
        }

        Flux::toast(variant: 'info', text: 'Plantilla restaurada (contenido original).');
    }

    private function cargarPlantilla(string $tipo): void
    {
        $path = resource_path(self::LAYOUT_DIR . '/' . $tipo . '.html');
        $this->contenido = file_exists($path) ? file_get_contents($path) : '';
    }
}; ?>

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Plantillas PDF</flux:heading>
            <flux:text>Edita los layouts HTML que generan los documentos PDF de solicitudes.</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="ghost" icon="arrow-path" wire:click="restaurar">
                Restaurar
            </flux:button>
            <flux:button variant="primary" icon="check" wire:click="guardar" wire:loading.attr="disabled">
                Guardar
            </flux:button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="font-semibold">Token</p>
            <p class="text-xs text-zinc-500">Reemplazo en el HTML</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="font-semibold">Tipo</p>
            <p class="text-xs text-zinc-500">permiso | licencia | vacacion</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <p class="font-semibold">Tabla con</p>
            <p class="text-xs text-zinc-500">Las tablas se renderizan correctamente en dompdf</p>
        </div>
    </div>

    <flux:card>
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <flux:select wire:model="tipo" class="w-48">
                    <option value="permiso">Permiso</option>
                    <option value="licencia">Licencia</option>
                    <option value="vacacion">Vacación</option>
                </flux:select>
            </div>
            <flux:text size="xs" class="!text-zinc-400">
                Archivo: resources/views/pdf/layouts/{{ $tipo }}.html
            </flux:text>
        </div>

        <div class="mt-4">
            <label class="mb-1 block text-sm font-medium">Contenido HTML</label>
            <textarea
                wire:model="contenido"
                class="w-full rounded-lg border border-zinc-300 bg-white p-3 font-mono text-sm text-zinc-800 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
                rows="30"
                spellcheck="false"
            ></textarea>
        </div>
    </flux:card>
</div>
