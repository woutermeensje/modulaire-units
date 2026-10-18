<?php

namespace App\Services;

use App\Mail\SubmissionReceived;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stores a form submission and notifies the site owner by e-mail.
 */
class SubmissionRecorder
{
    /**
     * @param  array{name: string, email: ?string, phone?: ?string, message?: ?string, data?: array<string, mixed>, items?: list<array<string, mixed>>, source_url?: ?string}  $attributes
     */
    public function record(string $type, array $attributes, Request $request): Submission
    {
        $submission = Submission::query()->create([
            'type' => $type,
            'name' => $attributes['name'],
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'message' => $attributes['message'] ?? null,
            'data' => $attributes['data'] ?? [],
            'items' => $attributes['items'] ?? null,
            'source_url' => $attributes['source_url'] ?? $request->headers->get('referer'),
            'ip_address' => $request->ip(),
        ]);

        try {
            Mail::to(Setting::get('notification_email'))->send(new SubmissionReceived($submission));
        } catch (Throwable $exception) {
            Log::error('Kon formulier-notificatie niet versturen', ['submission' => $submission->id, 'exception' => $exception->getMessage()]);
        }

        return $submission;
    }

    /**
     * Bots fill the hidden "website" field; such posts are silently ignored.
     */
    public function isSpam(Request $request): bool
    {
        return filled($request->input('website'));
    }
}
