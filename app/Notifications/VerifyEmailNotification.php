<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Письмо с ссылкой на подтверждение почты при регистрации.
 *
 * Ссылка подписанная и с истечением, ведёт на API-роут `verification.verify`:
 * он помечает адрес подтверждённым и уводит пользователя на страницу SPA.
 */
class VerifyEmailNotification extends Notification
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
        $url = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        $message = (new MailMessage)
            ->subject('getlocsy: подтвердите почту')
            ->greeting('Добро пожаловать в getlocsy, '.$notifiable->name.'!')
            ->line('Осталось подтвердить почту — нажмите кнопку ниже.')
            ->line('Пока адрес не подтверждён, недоступны избранное, добавление локаций и фото, а также профиль.')
            ->action('Подтвердить почту', $url)
            ->line('Ссылка действует 60 минут.')
            ->line('Если вы не регистрировались в getlocsy — просто проигнорируйте это письмо.')
            ->salutation('getlocsy');

        // Ответы приходят на живой ящик, а не на no-reply
        $replyTo = (string) config('mail.reply_to.address');
        if ($replyTo !== '') {
            $message->replyTo($replyTo, (string) config('mail.reply_to.name'));
        }

        return $message;
    }
}
