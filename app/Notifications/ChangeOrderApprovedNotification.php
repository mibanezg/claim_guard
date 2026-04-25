<?php

namespace App\Notifications;

use App\Models\ChangeOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChangeOrderApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ChangeOrder $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order    = $this->order;
        $contract = $order->contract;
        $monto    = number_format($order->cost_impact / 100, 0, ',', '.');
        $currency = $contract->currency ?? 'CLP';
        $status   = $order->status === 'aprobada_parcialmente' ? 'aprobada parcialmente' : 'aprobada';

        $message = (new MailMessage)
            ->subject("ClaimGuard — OC {$order->request_number} {$status} — Contrato {$contract->number}")
            ->greeting("Hola {$notifiable->name},")
            ->line("La orden de cambio **{$order->request_number}** del contrato **{$contract->number} — {$contract->name}** ha sido **{$status}**.")
            ->line("**Descripción:** {$order->description}")
            ->line("**Impacto en costo:** {$currency} {$monto}");

        if ($order->schedule_impact_days !== 0) {
            $message->line("**Impacto en plazo:** {$order->schedule_impact_days} día(s)");
        }

        if ($contract->projected_end_date) {
            $message->line("**Nueva fecha proyectada:** " . $contract->projected_end_date->format('d/m/Y'));
        }

        return $message
            ->action('Ver Orden de Cambio', url("/change-orders?contract_id={$contract->id}"))
            ->salutation('ClaimGuard');
    }
}
