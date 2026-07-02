<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\Complaint;
use App\Models\DispatcherConnection;
use App\Models\FreightLoad;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ComplaintController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Complaint::class);

        return Inertia::render('Freight/Complaints', [
            'complaints' => Complaint::query()
                ->with(['targetUser', 'freightLoad', 'bid.freightLoad', 'dispatcherConnection.freightLoad'])
                ->where('reporter_id', $request->user()->id)
                ->latest()
                ->paginate(30)
                ->through(fn (Complaint $complaint) => $this->complaintPayload($complaint)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Complaint::class);

        $data = $request->validate([
            'target_user_id' => ['nullable', 'exists:users,id'],
            'load_id' => ['nullable', 'exists:loads,id'],
            'bid_id' => ['nullable', 'exists:bids,id'],
            'dispatcher_connection_id' => ['nullable', 'exists:dispatcher_connections,id'],
            'type' => ['required', 'in:fraud,spam,wrong_contacts,no_show,payment_issue,rude_behavior,other'],
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $this->ensureComplaintContextAllowed($request->user(), $data);

        Complaint::create([
            ...$data,
            'reporter_id' => $request->user()->id,
            'status' => 'new',
        ]);

        return back()->with('status', 'Жалоба отправлена.');
    }

    private function complaintPayload(Complaint $complaint): array
    {
        $load = $complaint->freightLoad
            ?: $complaint->bid?->freightLoad
            ?: $complaint->dispatcherConnection?->freightLoad;

        return [
            'id' => $complaint->id,
            'type' => $complaint->type,
            'status' => $complaint->status,
            'message' => $complaint->message,
            'admin_comment' => $complaint->admin_comment,
            'created_at' => $this->formatDateTime($complaint->created_at),
            'target_user' => $complaint->targetUser ? [
                'id' => $complaint->targetUser->id,
                'name' => $complaint->targetUser->name,
                'role' => $complaint->targetUser->role,
            ] : null,
            'context' => [
                'load_id' => $load?->id,
                'load_title' => $load?->title,
                'load_url' => $load ? route('loads.show', $load) : null,
                'bid_id' => $complaint->bid_id,
                'dispatcher_connection_id' => $complaint->dispatcher_connection_id,
            ],
        ];
    }

    private function formatDateTime($date): ?string
    {
        return $date ? $date->format('d.m.Y H:i') : null;
    }

    private function ensureComplaintContextAllowed(User $user, array $data): void
    {
        $load = isset($data['load_id']) ? FreightLoad::query()->find($data['load_id']) : null;
        $bid = isset($data['bid_id']) ? Bid::with(['freightLoad', 'vehicle'])->find($data['bid_id']) : null;
        $connection = isset($data['dispatcher_connection_id'])
            ? DispatcherConnection::with(['freightLoad', 'bid.vehicle', 'vehicle'])->find($data['dispatcher_connection_id'])
            : null;

        if ($bid) {
            $load ??= $bid->freightLoad;

            if ($load && (int) $bid->load_id !== (int) $load->id) {
                throw ValidationException::withMessages([
                    'bid_id' => 'Отклик не относится к указанному грузу.',
                ]);
            }
        }

        if ($connection) {
            $load ??= $connection->freightLoad;

            if ($load && (int) $connection->load_id !== (int) $load->id) {
                throw ValidationException::withMessages([
                    'dispatcher_connection_id' => 'Диспетчерское соединение не относится к указанному грузу.',
                ]);
            }

            if ($bid && $connection->bid_id && (int) $connection->bid_id !== (int) $bid->id) {
                throw ValidationException::withMessages([
                    'dispatcher_connection_id' => 'Диспетчерское соединение связано с другим откликом.',
                ]);
            }
        }

        if ($load && ! $this->userCanAccessLoadContext($user, $load)) {
            throw ValidationException::withMessages([
                'load_id' => 'Нельзя создать жалобу по чужому грузу.',
            ]);
        }

        if ($bid && ! $this->userCanAccessBidContext($user, $bid)) {
            throw ValidationException::withMessages([
                'bid_id' => 'Нельзя создать жалобу по чужому отклику.',
            ]);
        }

        if ($connection && ! $this->userCanAccessConnectionContext($user, $connection)) {
            throw ValidationException::withMessages([
                'dispatcher_connection_id' => 'Нельзя создать жалобу по чужому диспетчерскому соединению.',
            ]);
        }

        if (! empty($data['target_user_id'])) {
            if ((int) $data['target_user_id'] === (int) $user->id) {
                throw ValidationException::withMessages([
                    'target_user_id' => 'Нельзя создать жалобу на самого себя.',
                ]);
            }

            if (! $load && ! $bid && ! $connection) {
                throw ValidationException::withMessages([
                    'target_user_id' => 'Для жалобы на пользователя укажите связанный груз, отклик или диспетчерское соединение.',
                ]);
            }

            if (! $this->targetUserBelongsToContext((int) $data['target_user_id'], $load, $bid, $connection)) {
                throw ValidationException::withMessages([
                    'target_user_id' => 'Пользователь не является участником указанного процесса.',
                ]);
            }
        }
    }

    private function userCanAccessLoadContext(User $user, FreightLoad $load): bool
    {
        if ($user->isDispatcher()) {
            return $load->status === 'active'
                || $load->dispatcherConnections()->where('dispatcher_id', $user->id)->exists();
        }

        if ($user->isShipper()) {
            return (int) $load->shipper_id === (int) $user->id;
        }

        if (! $user->isCarrier()) {
            return false;
        }

        return Bid::query()
            ->where('load_id', $load->id)
            ->where(fn ($query) => $this->scopeBidAccessForCarrier($query, $user))
            ->exists();
    }

    private function userCanAccessBidContext(User $user, Bid $bid): bool
    {
        if ($user->isDispatcher()) {
            return DispatcherConnection::query()
                ->where('dispatcher_id', $user->id)
                ->where(fn ($query) => $query
                    ->where('bid_id', $bid->id)
                    ->orWhere('load_id', $bid->load_id)
                )
                ->exists();
        }

        if ($user->isShipper()) {
            return (int) $bid->freightLoad?->shipper_id === (int) $user->id;
        }

        if (! $user->isCarrier()) {
            return false;
        }

        return $this->carrierCanAccessBid($user, $bid);
    }

    private function userCanAccessConnectionContext(User $user, DispatcherConnection $connection): bool
    {
        if ($user->isDispatcher()) {
            return (int) $connection->dispatcher_id === (int) $user->id;
        }

        if ($user->isShipper()) {
            return (int) $connection->shipper_id === (int) $user->id;
        }

        if (! $user->isCarrier()) {
            return false;
        }

        if ((int) $connection->carrier_id === (int) $user->id) {
            return true;
        }

        if ($connection->vehicle?->assigned_driver_id && (int) $connection->vehicle->assigned_driver_id === (int) $user->id) {
            return true;
        }

        if ($user->isCarrierCompanyDriver()) {
            return false;
        }

        $companyId = $user->activeCarrierCompany()?->id;

        return $companyId && (int) $connection->carrier_company_id === (int) $companyId;
    }

    private function carrierCanAccessBid(User $user, Bid $bid): bool
    {
        if ((int) $bid->carrier_id === (int) $user->id) {
            return true;
        }

        if ($bid->vehicle?->assigned_driver_id && (int) $bid->vehicle->assigned_driver_id === (int) $user->id) {
            return true;
        }

        if ($user->isCarrierCompanyDriver()) {
            return false;
        }

        $companyId = $user->activeCarrierCompany()?->id;

        return $companyId && (int) $bid->company_id === (int) $companyId;
    }

    private function scopeBidAccessForCarrier($query, User $user): void
    {
        if ($user->isCarrierCompanyDriver()) {
            $query->where('carrier_id', $user->id)
                ->orWhereHas('vehicle', fn ($vehicle) => $vehicle->where('assigned_driver_id', $user->id));

            return;
        }

        $query->where('carrier_id', $user->id)
            ->orWhereHas('vehicle', fn ($vehicle) => $vehicle->where('assigned_driver_id', $user->id));

        if ($companyId = $user->activeCarrierCompany()?->id) {
            $query->orWhere('company_id', $companyId);
        }
    }

    private function targetUserBelongsToContext(
        int $targetUserId,
        ?FreightLoad $load,
        ?Bid $bid,
        ?DispatcherConnection $connection,
    ): bool {
        if ($load && (int) $load->shipper_id === $targetUserId) {
            return true;
        }

        if ($bid && (int) $bid->carrier_id === $targetUserId) {
            return true;
        }

        if ($bid?->vehicle?->assigned_driver_id && (int) $bid->vehicle->assigned_driver_id === $targetUserId) {
            return true;
        }

        if ($connection && in_array($targetUserId, [
            (int) $connection->dispatcher_id,
            (int) $connection->shipper_id,
            (int) $connection->carrier_id,
        ], true)) {
            return true;
        }

        return (bool) ($connection?->vehicle?->assigned_driver_id
            && (int) $connection->vehicle->assigned_driver_id === $targetUserId);
    }
}
