<?php

namespace App\Models;

use Database\Factories\LeaveRequestNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $leave_request_id
 * @property int $user_id
 * @property string $tipo
 * @property string $mensaje
 * @property bool $leida
 * @property Carbon|null $leida_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LeaveRequest $leaveRequest
 * @property-read User $user
 */
class LeaveRequestNotification extends Model
{
    /** @use HasFactory<LeaveRequestNotificationFactory> */
    use HasFactory;

    protected $fillable = [
        'leave_request_id', 'user_id', 'tipo', 'mensaje', 'leida', 'leida_at',
    ];

    protected $casts = [
        'leida' => 'boolean',
        'leida_at' => 'datetime',
    ];

    /** @return BelongsTo<LeaveRequest, $this> */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marcarComoLeida(): void
    {
        $this->update(['leida' => true, 'leida_at' => now()]);
    }
}
