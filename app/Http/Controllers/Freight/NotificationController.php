<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\DispatcherConnection;
use App\Models\FreightNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', FreightNotification::class);

        $filters = $request->validate([
            'status' => ['nullable', 'in:all,unread,read'],
            'type' => ['nullable', 'string', 'max:80'],
        ]);

        $status = $filters['status'] ?? 'unread';
        $type = $filters['type'] ?? null;
        $baseQuery = $request->user()->freightNotifications();
        $query = (clone $baseQuery)->latest();

        if ($status === 'unread') {
            $query->where('is_read', false);
        } elseif ($status === 'read') {
            $query->where('is_read', true);
        }

        if ($type) {
            $query->where('type', $type);
        }

        return Inertia::render('Freight/Notifications', [
            'notifications' => $query->paginate(30)->withQueryString()->through(fn (FreightNotification $notification) => $this->payload($notification, $request)),
            'filters' => ['status' => $status, 'type' => $type],
            'stats' => [
                'all' => (clone $baseQuery)->count(),
                'unread' => (clone $baseQuery)->where('is_read', false)->count(),
                'read' => (clone $baseQuery)->where('is_read', true)->count(),
            ],
            'types' => (clone $baseQuery)
                ->select('type')
                ->distinct()
                ->orderBy('type')
                ->pluck('type')
                ->values(),
        ]);
    }

    public function read(Request $request, FreightNotification $notification): RedirectResponse
    {
        Gate::authorize('update', $notification);

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', FreightNotification::class);

        $request->user()->freightNotifications()
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return back();
    }

    private function payload(FreightNotification $notification, Request $request): array
    {
        $data = $notification->data_json ?? [];
        $actionUrl = $this->actionUrl($data, $request);

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'is_read' => $notification->is_read,
            'created_at' => $notification->created_at?->format('d.m.Y H:i'),
            'read_at' => $notification->read_at?->format('d.m.Y H:i'),
            'action_url' => $actionUrl,
            'action_label' => $actionUrl ? $this->actionLabel($data, $request) : null,
            'data' => $data,
        ];
    }

    private function actionLabel(array $data, Request $request): string
    {
        return match ($data['action'] ?? null) {
            'delivery' => 'Открыть рейс',
            'shipper_delivery' => 'Контролировать доставку',
            'bids' => 'Разобрать отклики',
            'bid' => 'Открыть мои отклики',
            'load' => 'Открыть груз',
            default => isset($data['dispatcher_connection_id'])
                ? (($request->user()?->isDispatcher() || $request->user()?->isAdmin()) ? 'Открыть подбор' : 'Открыть груз')
                : 'Открыть',
        };
    }

    private function actionUrl(array $data, Request $request): ?string
    {
        if (($data['action'] ?? null) === 'delivery' && isset($data['bid_id'])) {
            return route('carrier.deliveries.show', $data['bid_id']);
        }

        if (($data['action'] ?? null) === 'shipper_delivery' && isset($data['load_id'])) {
            return route('loads.delivery', $data['load_id']);
        }

        if (($data['action'] ?? null) === 'bids' && isset($data['load_id'])) {
            return route('loads.bids', $data['load_id']);
        }

        if (($data['action'] ?? null) === 'bid') {
            return route('bids.mine');
        }

        if (($data['action'] ?? null) === 'load' && isset($data['load_id'])) {
            return route('loads.show', $data['load_id']);
        }

        if (isset($data['dispatcher_connection_id'])) {
            $connection = DispatcherConnection::query()->find($data['dispatcher_connection_id']);

            if (! $connection) {
                return null;
            }

            if ($request->user()?->isDispatcher() || $request->user()?->isAdmin()) {
                return route('dispatcher.connections.show', $connection);
            }

            return $connection->load_id ? route('loads.show', $connection->load_id) : null;
        }

        return isset($data['load_id']) ? route('loads.show', $data['load_id']) : null;
    }
}
