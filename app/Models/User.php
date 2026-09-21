<?php

namespace App\Models;

use App\Services\AreaHierarchyService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $nombre1
 * @property string|null $nombre2
 * @property string|null $apellido1
 * @property string|null $apellido2
 * @property string|null $genero
 * @property Carbon|null $fecha_nacimiento
 * @property Carbon|null $fecha_contrato
 * @property int|null $area_id
 * @property int|null $superior_id
 * @property string|null $firma_bpm
 * @property string|null $avatar
 * @property bool $es_superadmin
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Area|null $area
 * @property-read User|null $superior
 * @property-read Collection<int, User> $subordinados
 * @property-read Collection<int, LeaveRequest> $solicitudes
 * @property-read Collection<int, LeaveApproval> $aprobaciones
 * @property-read Collection<int, VacationBalance> $vacationBalances
 */
#[Fillable([
    'name', 'email', 'password', 'nombre1', 'nombre2', 'apellido1', 'apellido2',
    'genero', 'fecha_nacimiento', 'fecha_contrato', 'area_id', 'superior_id',
    'firma_bpm', 'avatar', 'es_superadmin',
])]
#[Hidden([
    'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token',
])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable;

    /** @var array<int>|null */
    protected static ?array $universeCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'fecha_nacimiento' => 'date',
            'fecha_contrato' => 'date',
            'es_superadmin' => 'boolean',
        ];
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return BelongsTo<User, $this> */
    public function superior(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superior_id');
    }

    /** @return HasMany<User, $this> */
    public function subordinados(): HasMany
    {
        return $this->hasMany(User::class, 'superior_id');
    }

    /** @return BelongsToMany<Area, $this> */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_user')
            ->withPivot('es_jefe')
            ->withTimestamps();
    }

    /** @return HasMany<LeaveRequest, $this> */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'user_id');
    }

    /** @return HasMany<LeaveApproval, $this> */
    public function aprobaciones(): HasMany
    {
        return $this->hasMany(LeaveApproval::class, 'aprobador_id');
    }

    /** @return HasMany<VacationBalance, $this> */
    public function vacationBalances(): HasMany
    {
        return $this->hasMany(VacationBalance::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        $partes = array_filter([
            $this->nombre1, $this->nombre2, $this->apellido1, $this->apellido2,
        ]);

        return implode(' ', $partes) ?: $this->name;
    }

    public function getInicialesAttribute(): string
    {
        $partes = array_filter([
            $this->nombre1, $this->apellido1,
        ]);
        $iniciales = Str::upper(implode('', array_map(fn ($p) => $p[0] ?? '', $partes)));

        return $iniciales ?: Str::upper($this->name[0] ?? 'U');
    }

    public function esJefeDeArea(Area $area): bool
    {
        return $this->areas()->where('area_id', $area->id)->wherePivot('es_jefe', true)->exists();
    }

    /**
     * @return array<int> ids de usuarios en el universo de la área del usuario (subárbol).
     */
    public function areaUniverseUserIds(): array
    {
        if ($this->es_superadmin) {
            return [];
        }

        return app(AreaHierarchyService::class)->universeUserIds($this->area_id ?? 0);
    }

    public function esJefeDeUniverso(): bool
    {
        return $this->hasPermissionTo('solicitudes.view-all') || $this->hasRole(['admin', 'superadmin']);
    }

    /**
     * @return array<int> ids del área + subáreas que forman el universo del usuario.
     */
    public function universeAreaIds(): array
    {
        if ($this->es_superadmin || $this->esJefeDeUniverso()) {
            return [];
        }

        return app(AreaHierarchyService::class)->selfAndDescendantIds($this->area_id ?? 0);
    }

    /**
     * @return bool puede aprobar escalando por jerarquía de área.
     */
    public function puedeAprobarEnUniverso(LeaveRequest $solicitud): bool
    {
        return app(AreaHierarchyService::class)->puedeAprobarEnUniverso($this, $solicitud);
    }

    public function getAniosServicioAttribute(): int
    {
        if (! $this->fecha_contrato) {
            return 0;
        }

        return (int) $this->fecha_contrato->diffInYears(now());
    }

    public function getDiasVacacionesCorrespondientesAttribute(): int
    {
        return $this->anios_servicio * 15;
    }
}
