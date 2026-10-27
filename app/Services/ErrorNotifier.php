<?php

namespace App\Services;

use App\Mail\ServerErrorOccurred;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ErrorNotifier
{
    /**
     * The same error is mailed at most once per this many seconds.
     */
    public const THROTTLE_SECONDS = 900;

    /**
     * Mail a reported server error to the error recipient, in production only.
     */
    public function notify(Throwable $exception): void
    {
        if (! app()->isProduction()) {
            return;
        }

        try {
            $fingerprint = 'error-notification:'.sha1($exception::class.'|'.$exception->getFile().'|'.$exception->getLine());

            if (! Cache::add($fingerprint, true, self::THROTTLE_SECONDS)) {
                return;
            }

            $request = app()->runningInConsole() ? null : request();

            Mail::to($this->recipient())->send(new ServerErrorOccurred(
                exceptionClass: $exception::class,
                exceptionMessage: $exception->getMessage(),
                location: $exception->getFile().':'.$exception->getLine(),
                trace: mb_substr($exception->getTraceAsString(), 0, 5000),
                url: $request?->fullUrl(),
                method: $request?->method(),
                ip: $request?->ip(),
            ));
        } catch (Throwable $mailException) {
            Log::warning('Foutmelding kon niet worden gemaild: '.$mailException->getMessage());
        }
    }

    private function recipient(): string
    {
        return Setting::get('error_notification_email') ?: Setting::get('notification_email');
    }
}
