<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * @param  string  $token  Токен сброса пароля из брокера Laravel
     */
    public function __construct(public string $token)
    {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Ссылка ведёт на SPA: роутер работает в hash-режиме, поэтому путь идёт
        // после «#». Плюс: токен остаётся во фрагменте и не попадает в логи nginx.
        $url = rtrim((string) config('app.frontend_url'), '/')
            .'/#/reset-password'
            .'?token='.$this->token
            .'&email='.urlencode((string) $notifiable->email);

        $message = (new MailMessage)
            ->subject('getlocsy: сброс пароля')
            ->greeting('Сброс пароля в getlocsy')
            ->line('Вы запросили сброс пароля. Чтобы задать новый, нажмите кнопку ниже.')
            ->action('Задать новый пароль', $url)
            ->line('Ссылка действует 60 минут.')
            ->line('Если вы не запрашивали сброс пароля — просто проигнорируйте это письмо, пароль останется прежним.')
            ->salutation('getlocsy');

        // Ответы приходят на живой ящик, а не на no-reply
        $replyTo = (string) config('mail.reply_to.address');
        if ($replyTo !== '') {
            $message->replyTo($replyTo, (string) config('mail.reply_to.name'));
        }

        return $message;
    }
}
