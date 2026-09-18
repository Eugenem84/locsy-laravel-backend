<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Письмо после смены пароля — страховка на случай, если смену инициировал
 * не владелец аккаунта.
 */
class PasswordChangedNotification extends Notification
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // SPA работает в hash-режиме, поэтому путь идёт после «#»
        $loginUrl = rtrim((string) config('app.frontend_url'), '/').'/#/login';

        $message = (new MailMessage)
            ->subject('getlocsy: пароль изменён')
            ->greeting('Пароль изменён')
            ->line('Пароль к вашему аккаунту в getlocsy успешно изменён.')
            ->line('Если это были вы — ничего делать не нужно.')
            ->action('Войти в getlocsy', $loginUrl)
            ->line('Если вы не меняли пароль, срочно восстановите доступ через «Забыли пароль?» на странице входа.')
            ->salutation('getlocsy');

        // Ответы приходят на живой ящик, а не на no-reply
        $replyTo = (string) config('mail.reply_to.address');
        if ($replyTo !== '') {
            $message->replyTo($replyTo, (string) config('mail.reply_to.name'));
        }

        return $message;
    }
}
