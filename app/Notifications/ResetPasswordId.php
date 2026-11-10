<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordId extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Reset password akun Central Kitchen')
            ->greeting('Halo, '.$notifiable->name)
            ->line('Kami menerima permintaan untuk membuat password baru bagi akun Anda.')
            ->action('Buat password baru', $this->resetUrl($notifiable))
            ->line("Link ini berlaku {$minutes} menit dan hanya bisa dipakai sekali.")
            ->line('Jika Anda tidak merasa meminta reset password, abaikan email ini.')
            ->salutation('Seblak Teh Wanti Central Kitchen');
    }
}
