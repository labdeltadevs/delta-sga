<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeaveRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Determina si el usuario puede aprobar la solicitud (directo o por escalado de jerarquía).
     */
    public function approve(User $user, LeaveRequest $solicitud): bool
    {
        return $solicitud->puedeSerAprobadaPor($user);
    }

    /**
     * Determina si el usuario puede ver la solicitud dentro de su universo de área.
     */
    public function view(User $user, LeaveRequest $solicitud): bool
    {
        if ($user->es_superadmin) {
            return true;
        }

        if ($user->id === $solicitud->user_id) {
            return true;
        }

        if ($user->hasPermissionTo('solicitudes.view-all')) {
            return in_array($solicitud->user_id, $user->areaUniverseUserIds(), true);
        }

        return false;
    }
}
