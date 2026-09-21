<?php

namespace App\Models;

use App\Notifications\LeaveRequestApprovalRequired;
use App\Notifications\LeaveRequestApproved;
use App\Notifications\LeaveRequestRejected;
use App\Notifications\LeaveRequestSubmitted;
use App\Services\AreaHierarchyService;
use App\Services\VacationService;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $tipo
 * @property string|null $motivo
 * @property Carbon $fecha_desde
 * @property Carbon $fecha_hasta
 * @property Carbon|null $hora_desde
 * @property Carbon|null $hora_hasta
 * @property string $jornada
 * @property string $considerado_como
 * @property string|null $comentarios
 * @property string $estado
 * @property int $dias_totales
 * @property int $dias_habiles
 * @property int|null $anios_servicio
 * @property int|null $dias_correspondientes
 * @property Carbon|null $fecha_limite_vacaciones
 * @property string|null $firma_bpm_trabajador
 * @property int|null $autorizado_por
 * @property Carbon|null $fecha_aprobacion
 * @property int|null $recibido_por
 * @property Carbon|null $fecha_recepcion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read User|null $autorizador
 * @property-read User|null $receptor
 * @property-read Collection<int, LeaveApproval> $aprobaciones
 */
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'tipo', 'motivo', 'fecha_desde', 'fecha_hasta', 'hora_desde', 'hora_hasta',
        'jornada', 'considerado_como', 'comentarios', 'estado', 'dias_totales', 'dias_habiles',
        'anios_servicio', 'dias_correspondientes', 'fecha_limite_vacaciones', 'firma_bpm_trabajador',
        'autorizado_por', 'fecha_aprobacion', 'recibido_por', 'fecha_recepcion',
    ];

    protected $casts = [
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'hora_desde' => 'datetime:H:i',
        'hora_hasta' => 'datetime:H:i',
        'fecha_limite_vacaciones' => 'date',
        'fecha_aprobacion' => 'datetime',
        'fecha_recepcion' => 'datetime',
        'dias_totales' => 'integer',
        'dias_habiles' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }

    /** @return BelongsTo<User, $this> */
    public function receptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    /** @return HasMany<LeaveApproval, $this> */
    public function aprobaciones(): HasMany
    {
        return $this->hasMany(LeaveApproval::class)->orderBy('orden');
    }

    public function aprobacionActual(): ?LeaveApproval
    {
        return $this->aprobaciones()->where('accion', 'pendiente')->first();
    }

    public function getAprobadorActual(): ?User
    {
        $aprobacion = $this->aprobacionActual();

        return $aprobacion?->aprobador;
    }

    public function puedeSerAprobadaPor(User $user): bool
    {
        $aprobacion = $this->aprobacionActual();

        if ($aprobacion && $aprobacion->aprobador_id === $user->id && $aprobacion->accion === 'pendiente') {
            return true;
        }

        return $user->puedeAprobarEnUniverso($this);
    }

    public function calcularDiasHabiles(): int
    {
        $dias = 0;
        $fecha = $this->fecha_desde->copy();
        $hasta = $this->fecha_hasta->copy();

        while ($fecha->lte($hasta)) {
            if (Holiday::esDiaHabil($fecha)) {
                $dias++;
            }
            $fecha = $fecha->addDay();
        }

        return $dias;
    }

    public function recalcularDias(): void
    {
        $this->dias_totales = (int) $this->fecha_desde->diffInDays($this->fecha_hasta) + 1;
        $this->dias_habiles = $this->calcularDiasHabiles();
        $this->save();
    }

    public function getSlaVencidoAttribute(): bool
    {
        if ($this->estado !== 'pendiente') {
            return false;
        }

        return $this->created_at->diffInHours(now()) > 48;
    }

    public function getHorasRestantesSlaAttribute(): int
    {
        if ($this->estado !== 'pendiente') {
            return 0;
        }

        return max(0, 48 - (int) $this->created_at->diffInHours(now()));
    }

    /**
     * Construye la cadena de aprobación según la jerarquía superior
     * y emite la solicitud como pendiente.
     *
     * @param  array<int, User|int>|null  $aprobadoresManuales
     */
    public function emitir(User $solicitante, ?array $aprobadoresManuales = null): void
    {
        if ($this->estado === 'pendiente') {
            throw new \Exception('La solicitud ya fue emitida.');
        }

        $this->user()->associate($solicitante);

        if ($this->tipo === 'vacacion') {
            $service = app(VacationService::class);
            $gestion = (int) $this->fecha_desde->year;
            $this->anios_servicio = $solicitante->anios_servicio;
            $this->dias_correspondientes = $service->diasCorrespondientes($solicitante, $gestion);
            $this->fecha_limite_vacaciones = $service->fechaLimitePara($solicitante, $gestion);
        }

        $this->estado = 'pendiente';
        $this->firma_bpm_trabajador = $solicitante->firma_bpm;

        $this->recalcularDias();

        $this->save();

        $aprobadores = $aprobadoresManuales ?: $this->determinarCadenaSugerida($solicitante);

        foreach ($aprobadores as $orden => $aprobador) {
            LeaveApproval::create([
                'leave_request_id' => $this->id,
                'aprobador_id' => $aprobador instanceof User ? $aprobador->id : $aprobador,
                'orden' => $orden + 1,
                'accion' => 'pendiente',
            ]);
        }

        // Notificar al primer aprobador
        $this->user->notify(new LeaveRequestSubmitted($this));
        $first = $this->aprobacionActual();
        if ($first) {
            $first->aprobador->notify(new LeaveRequestApprovalRequired($this, $first));
        }
    }

    /**
     * Cadena sugerida: usuario -> superior -> head de área -> head de área padre ... hasta raíz.
     *
     * @return array<int, User>
     */
    protected function determinarCadenaSugerida(User $usuario): array
    {
        return app(AreaHierarchyService::class)->cadenaAprobacionHibrida($usuario);
    }

    /**
     * @return Builder<LeaveRequest>
     */
    public function scopeVisibleFor(Builder $query, User $viewer): Builder
    {
        $service = app(AreaHierarchyService::class);

        return $service->scopeVisibleFor($query, $viewer);
    }

    public function aprobar(User $aprobador, ?string $credentialId = null, bool $fallback = false, string $comentario = ''): void
    {
        $aprobacion = $this->aprobacionActual();
        if (! $aprobacion || $aprobacion->aprobador_id !== $aprobador->id) {
            throw new \Exception('No autorizado para aprobar esta solicitud');
        }

        $aprobacion->update([
            'accion' => 'aprobado',
            'comentario' => $comentario,
            'credential_id' => $credentialId,
            'fallback_password' => $fallback,
            'fecha_accion' => now(),
        ]);

        $siguiente = $this->aprobaciones()->where('orden', $aprobacion->orden + 1)->first();
        if ($siguiente) {
            // Notificar al siguiente aprobador
            $this->notificarAprobacionRequerida($siguiente->aprobador);
        } else {
            // Aprobación final
            $this->finalizarAprobacion($aprobador);
        }
    }

    public function rechazar(User $aprobador, string $comentario = '', ?string $credentialId = null, bool $fallback = false): void
    {
        $aprobacion = $this->aprobacionActual();
        if (! $aprobacion || $aprobacion->aprobador_id !== $aprobador->id) {
            throw new \Exception('No autorizado para rechazar esta solicitud');
        }

        $aprobacion->update([
            'accion' => 'rechazado',
            'comentario' => $comentario,
            'credential_id' => $credentialId,
            'fallback_password' => $fallback,
            'fecha_accion' => now(),
        ]);

        $this->update(['estado' => 'rechazado']);
        $this->notificarRechazo($this->user);
    }

    protected function finalizarAprobacion(User $ultimoAprobador): void
    {
        $this->update([
            'estado' => 'aprobado',
            'autorizado_por' => $ultimoAprobador->id,
            'fecha_aprobacion' => now(),
        ]);

        // Si es vacación aprobada, registrar el consumo del saldo.
        if ($this->tipo === 'vacacion') {
            app(VacationService::class)->registrarConsumo(
                $this->user,
                (int) $this->fecha_desde->year,
                $this->dias_habiles ?: $this->dias_totales,
            );
        }

        // El receptor por defecto es el último aprobador, pero puede configurarse
        $receptor = $this->recibido_por ? User::find($this->recibido_por) : $ultimoAprobador;
        if ($receptor) {
            $this->notificarAprobacionFinal($receptor);
        }
    }

    protected function notificarAprobacionRequerida(User $aprobador): void
    {
        $aprobador->notify(new LeaveRequestApprovalRequired($this, $this->aprobacionActual()));
    }

    protected function notificarRechazo(User $solicitante): void
    {
        $solicitante->notify(new LeaveRequestRejected($this));
    }

    protected function notificarAprobacionFinal(User $receptor): void
    {
        $receptor->notify(new LeaveRequestApproved($this));
        $this->user->notify(new LeaveRequestApproved($this));
    }
}
