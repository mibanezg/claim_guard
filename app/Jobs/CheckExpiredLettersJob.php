<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\LetterExpiredNotification;
use App\Services\LetterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Multitenancy\Models\Tenant;

class CheckExpiredLettersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $tenantId) {}

    public function handle(LetterService $service): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (!$tenant) return;

        $tenant->makeCurrent();

        try {
            $expired = $service->markExpired();

            if ($expired->isNotEmpty()) {
                Log::info("CheckExpiredLetters: {$expired->count()} carta(s) vencida(s) para tenant {$this->tenantId}.");

                $recipients = User::notifiableManagers($this->tenantId);

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new LetterExpiredNotification($expired));
                }
            }
        } finally {
            Tenant::forgetCurrent();
        }
    }
}
