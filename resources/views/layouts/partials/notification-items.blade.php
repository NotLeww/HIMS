@foreach($notifications as $notification)
    @php
        $priority = \App\Enums\NotificationPriority::tryFrom((string) ($notification->data['priority'] ?? ''))
            ?? \App\Enums\NotificationPriority::Info;
        $isUnread = $notification->read_at === null;
        $accent = match($priority) {
            \App\Enums\NotificationPriority::Critical => 'bg-rose-500',
            \App\Enums\NotificationPriority::Warning => 'bg-amber-400',
            default => 'bg-primary-400',
        };
        $timestamp = $notification->created_at->diffInSeconds(now()) < 45
            ? 'Just now'
            : $notification->created_at->diffForHumans();
    @endphp
    <div class="grid w-full min-w-0 max-w-full grid-cols-[3px_minmax(0,1fr)_2.25rem] overflow-hidden border-b border-neutral-100 last:border-b-0 dark:border-neutral-800/80
                {{ $isUnread ? 'bg-primary-50/55 dark:bg-primary-950/20' : 'bg-white dark:bg-neutral-900' }}">
        <span class="{{ $accent }}" aria-hidden="true"></span>
        <a href="{{ route('notifications.open', $notification->id) }}"
           class="min-w-0 overflow-hidden px-3 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-800/60 focus-visible:outline-none focus-visible:ring-2
                  focus-visible:ring-inset focus-visible:ring-primary-500">
            <div class="flex items-start justify-between gap-2">
                <p class="truncate text-sm {{ $isUnread ? 'font-semibold text-neutral-950 dark:text-neutral-50' : 'font-medium text-neutral-800 dark:text-neutral-200' }}">
                    {{ $notification->data['title'] ?? 'HIMS notification' }}
                </p>
                @if($priority === \App\Enums\NotificationPriority::Critical)
                    <span class="shrink-0 rounded-full bg-rose-100 dark:bg-rose-950/60 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-700 dark:text-rose-300">
                        Critical
                    </span>
                @endif
            </div>
            <p class="mt-0.5 line-clamp-2 break-words text-xs leading-5 text-neutral-600 [overflow-wrap:anywhere] dark:text-neutral-400">
                {{ $notification->data['message'] ?? '' }}
            </p>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $timestamp }}</time>
                @if($isUnread)
                    <span class="inline-flex items-center gap-1 font-medium text-primary-700 dark:text-primary-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-primary-500" aria-hidden="true"></span>
                        Unread
                    </span>
                @else
                    <span>Read</span>
                @endif
            </div>
        </a>
        <div class="flex min-w-0 items-start justify-center pt-3">
            @if($isUnread)
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="rounded-md p-1.5 text-neutral-400 hover:bg-white hover:text-primary-700 dark:text-neutral-500 dark:hover:bg-neutral-800 dark:hover:text-primary-400
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            title="Mark as read">
                        <span class="sr-only">Mark {{ $notification->data['title'] ?? 'notification' }} as read</span>
                        <x-ui.icon name="check" class="h-4 w-4" />
                    </button>
                </form>
            @endif
        </div>
    </div>
@endforeach
