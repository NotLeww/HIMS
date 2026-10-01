<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\NotificationDestination;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AuthenticationPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $user = $this->user($request);
        $this->markAsRead($user, $this->notificationFor($request, $notification));

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        DB::transaction(function () use ($user): void {
            $count = $user->unreadNotifications()->update(['read_at' => now()]);

            if ($count > 0) {
                $this->auditLogger->record(
                    AuditAction::AcknowledgedNotification,
                    $user,
                    $user,
                    "Marked {$count} notifications as read.",
                    $user->name,
                    newValues: ['notifications_marked_read' => $count],
                );
            }
        });

        return back();
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $user = $this->user($request);
        $stored = $this->notificationFor($request, $notification);
        $this->markAsRead($user, $stored);

        $destination = NotificationDestination::tryFrom((string) ($stored->data['destination'] ?? ''));

        $parameters = $stored->data['route_parameters'] ?? [];
        $parameters = is_array($parameters) ? $parameters : [];

        if ($destination === null
            || ! $destination->isAuthorizedFor($user)
            || ! $destination->isAvailable($parameters)) {
            return redirect()
                ->route(AuthenticationPanel::forRole($user->role)->dashboardRoute())
                ->with('info', 'This notification is no longer available for your current access level.');
        }

        return redirect()->to($destination->url($user, $parameters));
    }

    private function notificationFor(Request $request, string $id): DatabaseNotification
    {
        return $this->user($request)->notifications()->findOrFail($id);
    }

    private function markAsRead(User $user, DatabaseNotification $notification): void
    {
        if ($notification->read_at !== null) {
            return;
        }

        DB::transaction(function () use ($user, $notification): void {
            $notification->markAsRead();
            $title = (string) ($notification->data['title'] ?? 'HIMS notification');

            $this->auditLogger->record(
                AuditAction::AcknowledgedNotification,
                $user,
                description: "Marked notification '{$title}' as read.",
                targetName: $title,
                newValues: [
                    'notification_id' => $notification->id,
                    'destination' => $notification->data['destination'] ?? null,
                ],
                targetType: 'Notification',
                targetId: $notification->id,
                targetReference: $title,
            );
        });
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
