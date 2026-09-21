<?php

namespace App\Models;

use Database\Factories\LeaveApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $leave_request_id
 * @property int $aprobador_id
 * @property int $orden
 * @property string $accion
 * @property string|null $comentario
 * @property string|null $credential_id
 * @property bool $fallback_password
 * @property Carbon|null $fecha_accion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LeaveRequest $leaveRequest
 * @property-read User $aprobador
 */
class LeaveApproval extends Model
{
    /** @use HasFactory<LeaveApprovalFactory> */
    use HasFactory;

    protected $fillable = [
        'leave_request_id', 'aprobador_id', 'orden', 'accion', 'comentario',
        'credential_id', 'fallback_password', 'fecha_accion',
    ];

    protected $casts = [
        'fecha_accion' => 'datetime',
        'fallback_password' => 'boolean',
    ];

    /** @return BelongsTo<LeaveRequest, $this> */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobador_id');
    }
}
