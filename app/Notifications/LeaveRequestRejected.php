<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestRejected extends Notification implements ShouldQueue
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
            ->subject('Solicitud rechazada')
            ->greeting("Hola {$notifiable->name},")
            ->line("Lamentablemente, tu solicitud de {$this->leaveRequest->tipo} ha sido rechazada.")
            ->line('Motivo de la solicitud: '.($this->leaveRequest->motivo ?: '—'))
            ->action('Ver solicitud', url(route('solicitudes.show', $this->leaveRequest, false)));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequest->id,
            'tipo' => $this->leaveRequest->tipo,
            'motivo' => $this->leaveRequest->motivo,
            'estado' => 'rechazado',
        ];
    }
}
