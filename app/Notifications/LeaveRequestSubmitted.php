<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmitted extends Notification implements ShouldQueue
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
            ->subject("Nueva solicitud de {$this->leaveRequest->tipo} de {$this->leaveRequest->user->name}")
            ->greeting("Hola {$notifiable->name},")
            ->line("{$this->leaveRequest->user->name} ha emitido una solicitud de {$this->leaveRequest->tipo}.")
            ->line('Motivo: '.($this->leaveRequest->motivo ?: '—'))
            ->line('Del '.$this->leaveRequest->fecha_desde->format('d/m/Y').' al '.$this->leaveRequest->fecha_hasta->format('d/m/Y'))
            ->action('Revisar solicitud', url(route('solicitudes.show', $this->leaveRequest, false)))
            ->line('Recuerde que la solicitud debe aprobarse o rechazarse en un máximo de 48 horas.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => $this->leaveRequest->tipo,
            'motivo' => $this->leaveRequest->motivo,
            'user_name' => $this->leaveRequest->user->name,
            'leave_request_id' => $this->leaveRequest->id,
            'fecha_desde' => $this->leaveRequest->fecha_desde->format('Y-m-d'),
            'fecha_hasta' => $this->leaveRequest->fecha_hasta->format('Y-m-d'),
        ];
    }
}
