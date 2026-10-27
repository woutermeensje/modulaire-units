<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * Same reset mail as for customers, but the link points to /admin/wachtwoord-reset/{token}.
 */
class AdminResetPassword extends ResetPassword
{
    /**
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        return route('admin.password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
    }
}
