<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Perfil')] class extends Component {

    public string $name = '';
    public string $email = '';

    public ?string $nombre1 = null;
    public ?string $nombre2 = null;
    public ?string $apellido1 = null;
    public ?string $apellido2 = null;
    public ?string $genero = null;
    public ?string $fecha_nacimiento = null;
    public ?string $fecha_contrato = null;

    public string $firma_bpm = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->nombre1 = $user->nombre1;
        $this->nombre2 = $user->nombre2;
        $this->apellido1 = $user->apellido1;
        $this->apellido2 = $user->apellido2;
        $this->genero = $user->genero;
        $this->fecha_nacimiento = $user->fecha_nacimiento?->format('Y-m-d');
        $this->fecha_contrato = $user->fecha_contrato?->format('Y-m-d');
        $this->firma_bpm = $user->firma_bpm ?? '';
    }

    #[Computed]
    public function tienePasskey(): bool
    {
        return Auth::user()->passkeys()->count() > 0;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'nombre1' => ['required', 'string', 'max:255'],
            'nombre2' => ['nullable', 'string', 'max:255'],
            'apellido1' => ['required', 'string', 'max:255'],
            'apellido2' => ['nullable', 'string', 'max:255'],
            'genero' => ['nullable', Rule::in(['M', 'F'])],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'fecha_contrato' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if (! $user->firma_bpm && $this->firma_bpm) {
            $user->update(['firma_bpm' => $this->firma_bpm]);
        }

        Flux::toast(variant: 'success', text: __('Perfil actualizado.'));
    }

    public function saveSignature(): void
    {
        if (blank(trim($this->firma_bpm))) {
            Flux::toast(variant: 'danger', text: __('La firma no puede estar vacía.'));

            return;
        }

        Auth::user()->update(['firma_bpm' => $this->firma_bpm]);

        Flux::toast(variant: 'success', text: __('Firma BPM guardada.'));
    }

    public function clearSignature(): void
    {
        $this->firma_bpm = '';
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Perfil') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Perfil')" :subheading="__('Actualiza tu información personal y de contacto')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <div>
                <flux:heading size="sm">{{ __('Nombres y apellidos') }}</flux:heading>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="nombre1" :label="__('Primer nombre')" type="text" required autocomplete="given-name" />
                    <flux:input wire:model="nombre2" :label="__('Segundo nombre')" type="text" autocomplete="additional-name" />
                    <flux:input wire:model="apellido1" :label="__('Primer apellido')" type="text" required autocomplete="family-name" />
                    <flux:input wire:model="apellido2" :label="__('Segundo apellido')" type="text" autocomplete="family-name" />
                </div>
            </div>

            <flux:separator />

            <div>
                <flux:heading size="sm">{{ __('Información general') }}</flux:heading>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="genero" :label="__('Género')">
                        <option value="">{{ __('Seleccionar') }}</option>
                        <option value="M">{{ __('Masculino') }}</option>
                        <option value="F">{{ __('Femenino') }}</option>
                    </flux:select>
                    <flux:input wire:model="fecha_nacimiento" :label="__('Fecha de nacimiento')" type="date" />
                    <flux:input wire:model="fecha_contrato" :label="__('Fecha de contrato')" type="date" help="{{ __('Determina tus días de vacaciones: 15 por año de servicio cumplido.') }}" />
                </div>
            </div>

            <flux:separator />

            <div>
                <flux:heading size="sm">{{ __('Cuenta') }}</flux:heading>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" :label="__('Nombre de usuario')" type="text" required autocomplete="username" />
                    <flux:input wire:model="email" :label="__('Correo electrónico')" type="email" required autocomplete="email" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Guardar') }}
                    </flux:button>
                </div>
            </div>
        </form>

        <flux:separator class="my-8" />

        <div>
            <flux:heading size="sm">{{ __('Firma BPM') }}</flux:heading>
            <flux:subheading>{{ __('Tu firma digital se adjuntará a cada solicitud que emitas para garantizar su integridad.') }}</flux:subheading>

            <div class="mt-6">
                @if ($firma_bpm)
                    <div class="w-full overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <img src="{{ $firma_bpm }}" alt="{{ __('Firma') }}" class="w-full max-w-sm bg-white" />
                    </div>
                @else
                    <div class="flex max-w-sm items-center justify-center rounded-xl border border-dashed border-zinc-300 py-10 text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                        {{ __('Aún no registraste tu firma.') }}
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <flux:modal.trigger name="firma-bpm-modal">
                        <flux:button variant="primary" icon="pencil">{{ $firma_bpm ? __('Cambiar firma') : __('Registrar firma') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>
        </div>

        <livewire:pages::settings.delete-user-form />
    </x-pages::settings.layout>

    <flux:modal name="firma-bpm-modal" class="max-w-lg" wire:model="firma_bpm">
        <div class="space-y-4">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Firmar aquí') }}</flux:heading>
                <flux:text>{{ __('Dibuja tu firma con el dedo o el mouse.') }}</flux:text>
            </div>

            <div
                x-data="{
                    pad: null,
                    canvas: null,
                    drawing: false,
                    init() {
                        this.canvas = this.$refs.canvas;
                        const dpr = window.devicePixelRatio || 1;
                        this.canvas.width = this.canvas.offsetWidth * dpr;
                        this.canvas.height = this.canvas.offsetHeight * dpr;
                        const ctx = this.canvas.getContext('2d');
                        ctx.scale(dpr, dpr);

                        const down = (e) => {
                            this.drawing = true;
                            ctx.beginPath();
                            const { x, y } = this.pos(e);
                            ctx.moveTo(x, y);
                        };
                        const move = (e) => {
                            if (! this.drawing) return;
                            const { x, y } = this.pos(e);
                            ctx.lineTo(x, y);
                            ctx.strokeStyle = '#000';
                            ctx.lineWidth = 2.5;
                            ctx.lineCap = 'round';
                            ctx.lineJoin = 'round';
                            ctx.stroke();
                        };
                        const up = () => { this.drawing = false; };

                        this.canvas.addEventListener('pointerdown', down);
                        this.canvas.addEventListener('pointermove', move);
                        window.addEventListener('pointerup', up);

                        this.pad = { ctx, clear: () => ctx.clearRect(0, 0, this.canvas.width, this.canvas.height) };
                    },
                    pos(e) {
                        const r = this.canvas.getBoundingClientRect();
                        return { x: e.clientX - r.left, y: e.clientY - r.top };
                    },
                    limpiar() { this.pad.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height); },
                    guardar() {
                        @this.set('firma_bpm', this.canvas.toDataURL('image/png'));
                        @this.call('saveSignature');
                        document.querySelector('[data-firma-modal-close]')?.click();
                    }
                }"
                class="touch-none"
            >
                <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700">
                    <canvas x-ref="canvas" class="h-44 w-full rounded-xl"></canvas>
                </div>

                <div class="mt-4 flex flex-wrap justify-between gap-3">
                    <flux:button variant="ghost" x-on:click="limpiar">{{ __('Limpiar') }}</flux:button>
                    <div class="flex gap-3">
                        <flux:button variant="outline" as="button" data-firma-modal-close>{{ __('Cancelar') }}</flux:button>
                        <flux:button variant="primary" x-on:click="guardar">{{ __('Guardar firma') }}</flux:button>
                    </div>
                </div>
            </div>
        </div>

        <div class="hidden">
            <input wire:model="firma_bpm" />
        </div>
    </flux:modal>
</section>