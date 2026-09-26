<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FinanceMessage extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $href,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'href' => $this->href,
        ];
    }
}
