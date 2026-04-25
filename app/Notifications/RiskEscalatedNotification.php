<?php

namespace App\Notifications;

use App\Models\ClaimRiskScore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RiskEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ClaimRiskScore $score) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $contract  = $this->score->contract;
        $level     = strtoupper($this->score->score_level);
        $value     = $this->score->score_value;

        $levelLabels = [
            'alto'    => '🟠 ALTO',
            'critico' => '🔴 CRÍTICO',
        ];
        $label = $levelLabels[$this->score->score_level] ?? $level;

        $message = (new MailMessage)
            ->subject("ClaimGuard — Riesgo de claim {$label} en contrato {$contract->number}")
            ->greeting("Hola {$notifiable->name},")
            ->line("El contrato **{$contract->number} — {$contract->name}** ha alcanzado nivel de riesgo **{$label}** ({$value}/100).")
            ->line('**Factores activos:**');

        foreach ($this->score->factors as $factor) {
            if (($factor['points'] ?? 0) > 0) {
                $message->line("• {$factor['label']}: {$factor['points']}/{$factor['max']} pts");
            }
        }

        return $message
            ->line('Se recomienda revisar el estado del contrato y tomar acción preventiva.')
            ->action('Ver Contrato', url("/contracts/{$contract->id}"))
            ->salutation('ClaimGuard');
    }
}
