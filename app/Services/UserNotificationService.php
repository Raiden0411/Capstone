<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserNotificationService
{
    /**
     * Create a single notification row.
     *
     * The `scope` key is REQUIRED — it isolates a user's tourist
     * notifications from their business notifications and vice versa.
     * Rule 165: every read on the notifications table filters by
     * (user_id, scope).
     *
     * @param  array{
     *     scope: string,
     *     type: string,
     *     title: string,
     *     message: string,
     *     url?: string|null,
     *     icon?: string,
     *     color?: string,
     * }  $payload
     */
    public function notify(User $user, array $payload): ?UserNotification
    {
        try {
            $scope = $payload['scope'] ?? UserNotification::SCOPE_PLATFORM;

            return UserNotification::create([
                'user_id' => $user->id,
                'scope'   => $scope,
                'type'    => $payload['type']    ?? 'system',
                'title'   => $payload['title'],
                'message' => $payload['message'],
                'url'     => $payload['url']     ?? null,
                'icon'    => $payload['icon']    ?? 'inbox',
                'color'   => $payload['color']   ?? 'slate',
            ]);
        } catch (Throwable $e) {
            // Notification failure must never break the primary action.
            Log::warning('UserNotificationService::notify failed', [
                'user_id' => $user->id,
                'scope'   => $payload['scope'] ?? null,
                'type'    => $payload['type'] ?? 'system',
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Broadcast the same payload to many users. Every recipient gets a
     * row in the same scope.
     *
     * @param  iterable<int, User>  $users
     * @return int                  Number of rows created.
     */
    public function notifyMany(iterable $users, array $payload): int
    {
        $count = 0;
        foreach ($users as $user) {
            if ($this->notify($user, $payload) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Unread count for the bell badge — filtered to a single scope.
     */
    public function unreadCount(User $user, string $scope): int
    {
        return UserNotification::query()
            ->forUser($user->id)
            ->forScope($scope)
            ->unread()
            ->count();
    }

    /**
     * Recent notifications for the bell dropdown — filtered to a scope.
     *
     * @return Collection<int, UserNotification>
     */
    public function recent(User $user, string $scope, int $limit = 6): Collection
    {
        return UserNotification::query()
            ->forUser($user->id)
            ->forScope($scope)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Paginated list for the full page — filtered to a scope.
     */
    public function paginate(
        User $user,
        string $scope,
        int $perPage = 20,
        bool $unreadOnly = false,
    ): LengthAwarePaginator {
        return UserNotification::query()
            ->forUser($user->id)
            ->forScope($scope)
            ->when($unreadOnly, fn ($q) => $q->unread())
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Mark a single notification as read. Scoped by user_id so a
     * tampered ID cannot mark another user's row.
     */
    public function markRead(User $user, int $notificationId): bool
    {
        return (bool) UserNotification::query()
            ->forUser($user->id)
            ->whereKey($notificationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Mark every unread notification in a scope as read.
     *
     * @return int  Number of rows updated.
     */
    public function markAllRead(User $user, string $scope): int
    {
        return UserNotification::query()
            ->forUser($user->id)
            ->forScope($scope)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Delete all notifications for a user in a scope. Useful when
     * accounts are purged or when the user wants to clear an inbox.
     *
     * @return int  Number of rows deleted.
     */
    public function clearAll(User $user, string $scope): int
    {
        return UserNotification::query()
            ->forUser($user->id)
            ->forScope($scope)
            ->delete();
    }
}