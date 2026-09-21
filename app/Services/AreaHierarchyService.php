<?php

namespace App\Services;

use App\Models\Area;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AreaHierarchyService
{
    /**
     * @var array<int, array<int>>|null
     */
    protected ?array $descendantsCache = null;

    /**
     * @var array<int, array<int>>|null
     */
    protected ?array $ancestorsCache = null;

    public function __construct()
    {
        $this->warm();
    }

    /**
     * Precalcular todos los descendientes y ancestros desde el cache o DB.
     */
    public function warm(): self
    {
        $cacheKey = 'area_hierarchy_v1';
        $cached = Cache::get($cacheKey);

        if ($cached) {
            $this->descendantsCache = $cached['descendants'] ?? [];
            $this->ancestorsCache = $cached['ancestors'] ?? [];

            return $this;
        }

        $areas = Area::with('parent')->get(['id', 'parent_id']);
        $descendants = [];
        $ancestors = [];
        $visited = [];

        foreach ($areas as $area) {
            $descendants[$area->id] = $this->collectDescendants($area->id, $areas, $visited);
            $ancestors[$area->id] = $this->collectAncestors($area->id, $areas, $visited);
        }

        $data = ['descendants' => $descendants, 'ancestors' => $ancestors];
        Cache::put($cacheKey, $data, now()->addMinutes(60));

        $this->descendantsCache = $descendants;
        $this->ancestorsCache = $ancestors;

        return $this;
    }

    /**
     * @return array<int> ids de todos los descendientes (niveles inferiores).
     */
    public function descendantIds(int $areaId): array
    {
        return $this->descendantsCache[$areaId] ?? [];
    }

    /**
     * @return array<int> ids del área + todos los descendientes.
     */
    public function selfAndDescendantIds(int $areaId): array
    {
        return [$areaId, ...($this->descendantsCache[$areaId] ?? [])];
    }

    /**
     * @return array<int> ids de todos los ancestros (superiores).
     */
    public function ancestorIds(int $areaId): array
    {
        return $this->ancestorsCache[$areaId] ?? [];
    }

    /**
     * @return array<int> ids del área + todos los ancestros.
     */
    public function selfAndAncestorIds(int $areaId): array
    {
        return [$areaId, ...($this->ancestorsCache[$areaId] ?? [])];
    }

    /**
     * Devuelve los ids de usuarios cuyo área (o subárea) pertenece al universo de $areaId.
     *
     * @return array<int>
     */
    public function userIdsInSubtree(int $areaId): array
    {
        $ids = $this->selfAndDescendantIds($areaId);

        return User::whereIn('area_id', $ids)->pluck('id')->toArray();
    }

    /**
     * @return array<int> ids de usuarios en el universo completo del área (incluye jefes de áreas hijas por pivot si aplica).
     */
    public function universeUserIds(int $areaId): array
    {
        $userIds = $this->userIdsInSubtree($areaId);
        $headIds = Area::whereIn('id', $this->selfAndDescendantIds($areaId))
            ->whereNotNull('head_user_id')
            ->pluck('head_user_id')
            ->toArray();

        return array_values(array_unique(array_merge($userIds, $headIds)));
    }

    /**
     * Determina la cadena de aprobación jerárquica híbrida para un usuario.
     *
     * 1. Recorre superior_id (anti-ciclo).
     * 2. Si quedan niveles por cubrir, completa con heads de área hasta raíz.
     * 3. Deduplica y excluye al solicitante.
     *
     * @return array<int, User>
     */
    public function cadenaAprobacionHibrida(User $usuario, int $maxNiveles = 4): array
    {
        $cadena = [];
        $vistos = [$usuario->id];
        $actual = $usuario->superior;
        $nivel = 0;

        // Fase 1: superior_id
        while ($actual && $nivel < $maxNiveles && ! in_array($actual->id, $vistos, true)) {
            $cadena[] = $actual;
            $vistos[] = $actual->id;
            $actual = $actual->superior;
            $nivel++;
        }

        // Fase 2: completar con heads de área hasta raíz
        $areaActual = $usuario->area;
        while ($areaActual && count($cadena) < $maxNiveles) {
            $head = $areaActual->head;
            if ($head && ! in_array($head->id, $vistos, true)) {
                $cadena[] = $head;
                $vistos[] = $head->id;
            }

            if (! $areaActual->parent || in_array($areaActual->parent->id, $vistos, true)) {
                break;
            }

            $areaActual = $areaActual->parent;
        }

        return $cadena;
    }

    /**
     * Verifica si $user puede aprobar $solicitud considerando escalo por jerarquía de área.
     */
    public function puedeAprobarEnUniverso(User $user, LeaveRequest $solicitud): bool
    {
        $aprobacion = $solicitud->aprobacionActual();
        if (! $aprobacion || $aprobacion->accion !== 'pendiente') {
            return false;
        }

        // Caso 1: es el aprobador directo actual
        if ($aprobacion->aprobador_id === $user->id) {
            return true;
        }

        // Caso 2: el aprobador actual está en el universo del usuario y el usuario
        // es jefe de área (o superior de rama) — escalado automático
        $solicitanteAreaId = $solicitud->user->area_id;
        if (! $solicitanteAreaId) {
            return false;
        }

        $universeIds = $this->selfAndAncestorIds($solicitanteAreaId);
        if (! in_array($user->area_id, $universeIds, true) && ! in_array($solicitanteAreaId, $universeIds, true)) {
            return false;
        }

        // Verificar que el usuario es jefe de alguna área en el rango
        return $this->esJefeEnRama($user, $solicitanteAreaId);
    }

    /**
     * @return Builder<LeaveRequest>
     */
    public function scopeVisibleFor(Builder $query, User $viewer): Builder
    {
        if ($viewer->es_superadmin) {
            return $query;
        }

        if ($this->isGlobalViewer($viewer)) {
            /** @var Builder<LeaveRequest> $query */
            return $query->whereIn('user_id', $this->universeUserIds($viewer->area_id));
        }

        /** @var Builder<LeaveRequest> $query */
        return $query->where('user_id', $viewer->id);
    }

    public function isGlobalViewer(User $user): bool
    {
        return $user->hasPermissionTo('solicitudes.view-all') || $user->hasRole(['admin', 'superadmin']);
    }

    protected function esJefeEnRama(User $user, int $areaId): bool
    {
        foreach ($this->selfAndAncestorIds($areaId) as $id) {
            if ($user->areas()->where('area_id', $id)->wherePivot('es_jefe', true)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int>
     */
    private function collectDescendants(int $areaId, Collection $areas, array &$visited): array
    {
        $result = [];
        $children = $areas->where('parent_id', $areaId);

        foreach ($children as $child) {
            if (in_array($child->id, $visited, true)) {
                continue;
            }
            $visited[] = $child->id;
            $result[] = $child->id;
            $result = array_merge($result, $this->collectDescendants($child->id, $areas, $visited));
        }

        return $result;
    }

    /**
     * @return array<int>
     */
    private function collectAncestors(int $areaId, Collection $areas, array &$visited): array
    {
        $area = $areas->where('id', $areaId)->first();
        if (! $area || ! $area->parent_id || in_array($area->parent_id, $visited, true)) {
            return [];
        }

        $visited[] = $area->parent_id;

        return [$area->parent_id, ...$this->collectAncestors($area->parent_id, $areas, $visited)];
    }

    /**
     * Invalidar cache tras cambios de jerarquía.
     */
    public function invalidate(): void
    {
        Cache::forget('area_hierarchy_v1');
        $this->descendantsCache = null;
        $this->ancestorsCache = null;
    }
}
