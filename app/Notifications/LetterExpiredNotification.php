<?php

namespace App\Notifications;

use App\Models\ContractLetter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class LetterExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param Collection<int, ContractLetter> $letters */
    public function __construct(private readonly Collection $letters) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->letters->count();

        $message = (new MailMessage)
            ->subject("ClaimGuard — {$count} carta(s) contractual(es) vencida(s) sin respuesta")
            ->greeting("Hola {$notifiable->name},")
            ->line("Se detectaron **{$count}** carta(s) emitida(s) cuyo plazo de respuesta ya venció:");

        foreach ($this->letters->take(10) as $letter) {
            $contractNum = $letter->contract->number ?? '—';
            $deadline    = $letter->response_deadline
                ? \Carbon\Carbon::parse($letter->response_deadline)->format('d/m/Y')
                : '—';
            $message->line("• **{$letter->letter_number}** — {$letter->subject} (Contrato {$contractNum}, plazo: {$deadline})");
        }

        if ($count > 10) {
            $message->line("... y " . ($count - 10) . " carta(s) más.");
        }

        return $message
            ->line('Por favor revise y gestione las respuestas pendientes.')
            ->action('Ver Cartas', url('/letters'))
            ->salutation('ClaimGuard');
    }
}
