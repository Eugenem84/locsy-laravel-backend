<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Приветственное письмо после подтверждения почты.
 *
 * Приходит на адрес, указанный при регистрации. Подтверждение адреса в проекте
 * обязательное, поэтому письмо означает «аккаунт активирован»: фотографу
 * показываем его страницу, обычному пользователю — каталог локаций.
 */
class WelcomeNotification extends Notification
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
        $base = rtrim((string) config('app.frontend_url'), '/');

        $message = (new MailMessage)
            ->subject('getlocsy: почта подтверждена')
            ->greeting('Почта подтверждена, '.$notifiable->name.'!')
            ->line('Спасибо за регистрацию в getlocsy — сервисе фотолокаций: красивые места для съёмок, галереи снимков и фотографы в вашем городе.');

        if ($notifiable->is_photographer) {
            $message
                ->line('Ваш профиль фотографа уже создан — на него удобно отправлять клиентов.')
                ->action('Открыть мой профиль', $base.'/#/photographer/'.$notifiable->id);
        } else {
            $message->action('Найти фотолокации', $base.'/');
        }

        $message->salutation('getlocsy');

        // Ответы приходят на живой ящик, а не на no-reply
        $replyTo = (string) config('mail.reply_to.address');
        if ($replyTo !== '') {
            $message->replyTo($replyTo, (string) config('mail.reply_to.name'));
        }

        return $message;
    }
}
