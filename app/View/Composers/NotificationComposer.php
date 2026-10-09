<?php

namespace App\View\Composers;

use App\Models\User;
use App\Services\HimsNotificationService;
use Illuminate\View\View;

class NotificationComposer
{
    public function __construct(private readonly HimsNotificationService $notifications) {}

    public function compose(View $view): void
    {
        $user = request()->user();

        if (! $user instanceof User) {
            $view->with([
                'topbarNotifications' => collect(),
                'topbarNotificationsNextUrl' => null,
                'topbarUnreadCount' => 0,
            ]);

            return;
        }

        $feed = $this->notifications->feedFor($user);
        $unreadCount = (clone $feed)->whereNull('read_at')->count();
        $notifications = $feed
            ->latest()
            ->orderByDesc('id')
            ->cursorPaginate(HimsNotificationService::FEED_BATCH_SIZE)
            ->withPath(route('notifications.index'));

        $view->with([
            'topbarNotifications' => $notifications->getCollection(),
            'topbarNotificationsNextUrl' => $notifications->nextPageUrl(),
            'topbarUnreadCount' => $unreadCount,
        ]);
    }
}
