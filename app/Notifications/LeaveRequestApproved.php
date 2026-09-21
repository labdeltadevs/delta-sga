<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected LeaveRequest $leaveRequest,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Solicitud aprobada')
            ->greeting("Hola {$notifiable->name},")
            ->line("Tu solicitud de {$this->leaveRequest->tipo} ha sido aprobada.")
            ->line('Motivo: '.($this->leaveRequest->motivo ?: '—'))
            ->line('Del '.$this->leaveRequest->fecha_desde->format('d/m/Y').' al '.$this->leaveRequest->fecha_hasta->format('d/m/Y'))
            ->action('Ver solicitud', url(route('solicitudes.show', $this->leaveRequest, false)));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequest->id,
            'tipo' => $this->leaveRequest->tipo,
            'motivo' => $this->leaveRequest->motivo,
            'estado' => 'aprobado',
            'fecha_desde' => $this->leaveRequest->fecha_desde->format('Y-m-d'),
            'fecha_hasta' => $this->leaveRequest->fecha_hasta->format('Y-m-d'),
        ];
    }
}
