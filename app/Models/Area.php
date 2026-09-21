<?php

namespace App\Models;

use App\Services\AreaHierarchyService;
use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string $codigo
 * @property int|null $parent_id
 * @property int|null $head_user_id
 * @property string $tipo
 * @property int $orden
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Area|null $parent
 * @property-read Collection<int, Area> $children
 * @property-read User|null $head
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, User> $jefes
 */
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected $fillable = [
        'nombre', 'codigo', 'parent_id', 'head_user_id', 'tipo', 'orden', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    /** @return BelongsTo<Area, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'parent_id');
    }

    /** @return HasMany<Area, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Area::class, 'parent_id')->orderBy('orden');
    }

    /** @return BelongsTo<User, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'area_user')
            ->withPivot('es_jefe')
            ->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function jefes(): BelongsToMany
    {
        return $this->users()->wherePivot('es_jefe', true);
    }

    /** @return HasMany<Area, $this> */
    public function getAllChildren(): HasMany
    {
        return $this->hasMany(Area::class, 'parent_id')->with('children');
    }

    public function isAncestorOf(Area $area): bool
    {
        $current = $area->parent;
        while ($current) {
            if ($current->id === $this->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    public function getFullPath(): string
    {
        $path = [$this->nombre];
        $current = $this->parent;
        while ($current) {
            array_unshift($path, $current->nombre);
            $current = $current->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * @return array<int> ids de todos los descendientes.
     */
    public function descendantIds(): array
    {
        return app(AreaHierarchyService::class)->descendantIds($this->id);
    }

    /**
     * @return array<int> ids del área + todos los descendientes.
     */
    public function selfAndDescendantIds(): array
    {
        return app(AreaHierarchyService::class)->selfAndDescendantIds($this->id);
    }

    /**
     * @return array<int> ids de todos los ancestros.
     */
    public function ancestorIds(): array
    {
        return app(AreaHierarchyService::class)->ancestorIds($this->id);
    }

    /**
     * @return array<int> ids del área + todos los ancestros.
     */
    public function selfAndAncestorIds(): array
    {
        return app(AreaHierarchyService::class)->selfAndAncestorIds($this->id);
    }

    /**
     * @return array<int> ids de usuarios en el subárbol de esta área.
     */
    public function usersInSubtree(): array
    {
        return app(AreaHierarchyService::class)->userIdsInSubtree($this->id);
    }

    /**
     * Invalida el cache de jerarquía tras cambios.
     */
    public static function invalidateHierarchyCache(): void
    {
        app(AreaHierarchyService::class)->invalidate();
    }
}
