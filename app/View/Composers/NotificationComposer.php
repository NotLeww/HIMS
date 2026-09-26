<?php

namespace App\View\Composers;

use App\Models\User;
use Illuminate\View\View;

class NotificationComposer
{
    public function compose(View $view): void
    {
        $user = request()->user();

        if (! $user instanceof User) {
            $view->with(['topbarNotifications' => collect(), 'topbarUnreadCount' => 0]);

            return;
        }

        $notifications = $user->notifications()
            ->select('notifications.*')
            ->selectRaw('SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) OVER () AS total_unread_count')
            ->latest()
            ->limit(8)
            ->get();

        $view->with([
            'topbarNotifications' => $notifications,
            'topbarUnreadCount' => (int) ($notifications->first()?->total_unread_count ?? 0),
        ]);
    }
}
