<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Complaint;
use App\Models\DeliveryEvent;
use App\Models\DispatcherConnection;
use App\Models\FreightLoad;
use App\Models\FreightNotification;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewFreightAdmin', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'user_role' => ['nullable', 'in:admin,shipper,carrier,dispatcher'],
            'company_status' => ['nullable', 'in:not_verified,pending,verified,rejected'],
            'load_status' => ['nullable', 'in:draft,active,in_progress,completed,cancelled,archived'],
            'vehicle_state' => ['nullable', 'in:available,hidden,online,offline'],
            'complaint_status' => ['nullable', 'in:new,in_review,resolved,rejected'],
        ]);

        $search = $filters['q'] ?? null;

        $users = User::query()
            ->when($search, fn ($query) => $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            }))
            ->when($filters['user_role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->latest()
            ->limit(100)
            ->get();

        $companies = Company::with('user')
            ->when($search, fn ($query) => $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('inn', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->when($filters['company_status'] ?? null, fn ($query, $status) => $query->where('verification_status', $status))
            ->latest()
            ->limit(100)
            ->get();

        $loads = FreightLoad::with('company')
            ->when($search, fn ($query) => $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('loading_city', 'like', '%'.$search.'%')
                    ->orWhere('unloading_city', 'like', '%'.$search.'%');
            }))
            ->when($filters['load_status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->limit(100)
            ->get();

        $vehicles = Vehicle::with('company')
            ->when($search, fn ($query) => $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('registration_number', 'like', '%'.$search.'%')
                    ->orWhere('current_city', 'like', '%'.$search.'%')
                    ->orWhereHas('company', fn ($company) => $company->where('name', 'like', '%'.$search.'%'));
            }))
            ->when($filters['vehicle_state'] ?? null, function ($query, $state) {
                match ($state) {
                    'available' => $query->where('is_available', true),
                    'hidden' => $query->where('is_location_visible', false),
                    'online' => $query->where('is_online', true),
                    'offline' => $query->where('is_online', false),
                };
            })
            ->latest()
            ->limit(100)
            ->get();

        $complaints = Complaint::with(['reporter', 'targetUser', 'freightLoad'])
            ->when($search, fn ($query) => $query->where(function ($builder) use ($search) {
                $builder->where('message', 'like', '%'.$search.'%')
                    ->orWhere('admin_comment', 'like', '%'.$search.'%')
                    ->orWhereHas('reporter', fn ($reporter) => $reporter->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
            }))
            ->when($filters['complaint_status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('Freight/Admin/Index', [
            'filters' => $filters,
            'stats' => [
                'users' => User::count(),
                'companies' => Company::count(),
                'loads' => FreightLoad::count(),
                'vehicles' => Vehicle::count(),
                'connections' => DispatcherConnection::count(),
                'complaints' => Complaint::count(),
                'openComplaints' => Complaint::whereIn('status', ['new', 'in_review'])->count(),
            ],
            'users' => $users,
            'companies' => $companies,
            'loads' => $loads,
            'vehicles' => $vehicles,
            'connections' => DispatcherConnection::with(['freightLoad', 'dispatcher', 'carrier'])->latest()->limit(50)->get(),
            'complaints' => $complaints,
        ]);
    }

    public function updateUser(Request $request, User $user, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('moderateFreight', $user);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9\s\-\(\)]{10,20}$/'],
            'role' => ['nullable', 'in:admin,shipper,carrier,dispatcher'],
            'is_blocked' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        abort_if($request->user()->id === $user->id && ($data['is_blocked'] ?? false), 422, 'Нельзя заблокировать самого себя.');

        $old = $user->only(['name', 'email', 'phone', 'role', 'is_blocked', 'is_active']);
        $user->update($data);
        $audit->record('user.updated', $user, $old, $user->only(['name', 'email', 'phone', 'role', 'is_blocked', 'is_active']));

        return back()->with('status', 'Пользователь обновлен.');
    }

    public function showUser(User $user): Response
    {
        Gate::authorize('viewFreightAdmin', User::class);

        return Inertia::render('Freight/Admin/EntityShow', [
            'type' => 'user',
            'title' => 'Пользователь '.$user->name,
            'entity' => $user->load([
                'company',
                'loads.company',
                'vehicles.company',
                'bids.freightLoad',
            ]),
            'auditLogs' => $this->auditLogsFor($user),
        ]);
    }

    public function updateCompany(Request $request, Company $company, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('moderate', $company);

        $data = $request->validate([
            'verification_status' => ['nullable', 'in:not_verified,pending,verified,rejected'],
            'verification_comment' => ['nullable', 'string', 'max:3000'],
            'is_blocked' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'string', 'max:20'],
            'kpp' => ['nullable', 'string', 'max:20'],
            'ogrn' => ['nullable', 'string', 'max:20'],
            'carrier_profile_type' => ['nullable', 'in:individual,company'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9\s\-\(\)]{10,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'legal_address' => ['nullable', 'string', 'max:255'],
            'actual_address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $old = $company->only(array_merge(array_keys($data), ['verified_at', 'rejected_at']));

        $dates = match ($data['verification_status'] ?? null) {
            'verified' => ['verified_at' => now(), 'rejected_at' => null],
            'rejected' => ['verified_at' => null, 'rejected_at' => now()],
            'pending', 'not_verified' => ['verified_at' => null, 'rejected_at' => null],
            default => [],
        };

        $company->update([...$data, ...$dates]);
        $audit->record(
            'company.updated',
            $company,
            $old,
            $company->only(['verification_status', 'verification_comment', 'is_blocked', 'verified_at', 'rejected_at']),
        );
        $this->notifyCompanyOwnerAboutModeration($company, $old);

        return back()->with('status', 'Компания обновлена.');
    }

    private function notifyCompanyOwnerAboutModeration(Company $company, array $old): void
    {
        if (! $company->user_id) {
            return;
        }

        $statusChanged = array_key_exists('verification_status', $old)
            && ($old['verification_status'] ?? null) !== $company->verification_status;
        $blockedChanged = array_key_exists('is_blocked', $old)
            && (bool) ($old['is_blocked'] ?? false) !== (bool) $company->is_blocked;

        if (! $statusChanged && ! $blockedChanged) {
            return;
        }

        $statusTitle = match ($company->verification_status) {
            'verified' => 'Профиль компании подтверждён',
            'rejected' => 'Профиль компании отклонён',
            'pending' => 'Профиль компании отправлен на проверку',
            default => 'Статус проверки компании изменён',
        };
        $blockText = $company->is_blocked ? ' Профиль заблокирован модератором.' : '';
        $commentText = $company->verification_comment ? ' Комментарий: '.$company->verification_comment : '';

        FreightNotification::create([
            'user_id' => $company->user_id,
            'type' => 'company_moderation',
            'title' => $company->is_blocked ? 'Профиль компании заблокирован' : $statusTitle,
            'message' => trim('Модерация обновила статус компании '.$company->name.'.'.$blockText.$commentText),
            'data_json' => ['company_id' => $company->id, 'action' => 'company'],
        ]);
    }

    public function showCompany(Company $company): Response
    {
        Gate::authorize('viewFreightAdmin', User::class);

        return Inertia::render('Freight/Admin/EntityShow', [
            'type' => 'company',
            'title' => 'Компания '.$company->name,
            'entity' => $company->load(['user', 'loads', 'vehicles']),
            'auditLogs' => $this->auditLogsFor($company),
        ]);
    }

    public function updateLoad(Request $request, FreightLoad $load, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('moderate', $load);

        $data = $request->validate([
            'status' => ['nullable', 'in:draft,active,in_progress,completed,cancelled,archived'],
            'is_urgent' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        $this->ensureAdminCanSetLoadStatus($load, $data['status'] ?? null);

        $old = $load->only(['status', 'is_urgent', 'is_featured']);
        $dates = match ($data['status'] ?? null) {
            'active' => ['published_at' => $load->published_at ?? now(), 'cancelled_at' => null],
            'cancelled' => ['cancelled_at' => $load->cancelled_at ?? now()],
            default => [],
        };

        $load->update([...$data, ...$dates]);

        if (($data['status'] ?? null) === 'cancelled') {
            $load->loadMissing('bids.vehicle');
            $this->cancelPendingBids($load);
            $this->releaseAcceptedVehicles($load);
            $load->update([
                'bids_count' => $load->bids()->whereIn('status', ['pending', 'accepted'])->count(),
            ]);
        }

        $audit->record('load.moderated', $load, $old, $load->only(['status', 'is_urgent', 'is_featured']));

        return back()->with('status', 'Груз обновлен.');
    }

    public function showLoad(FreightLoad $load): Response
    {
        Gate::authorize('viewFreightAdmin', User::class);

        return Inertia::render('Freight/Admin/EntityShow', [
            'type' => 'load',
            'title' => 'Груз '.$load->title,
            'entity' => $load->load([
                'company',
                'shipper.company',
                'bids.carrier.company',
                'bids.vehicle',
                'dispatcherConnections.dispatcher',
                'dispatcherConnections.carrier',
                'deliveryEvents.actor',
                'completionConfirmedBy',
            ]),
            'auditLogs' => $this->auditLogsFor($load),
        ]);
    }

    public function updateVehicle(Request $request, Vehicle $vehicle, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('moderate', $vehicle);

        $data = $request->validate([
            'is_available' => ['nullable', 'boolean'],
            'is_location_visible' => ['nullable', 'boolean'],
            'is_online' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'vehicle_type' => ['nullable', 'string', 'max:255'],
            'body_type' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'min:6', 'max:12'],
            'capacity_kg' => ['nullable', 'integer', 'min:1'],
            'volume_m3' => ['nullable', 'numeric', 'min:0'],
            'current_city' => ['nullable', 'string', 'max:255'],
            'current_region' => ['nullable', 'string', 'max:255'],
        ]);

        if (($data['is_available'] ?? null) === true && $vehicle->hasActiveDelivery()) {
            throw ValidationException::withMessages([
                'is_available' => 'Транспорт назначен на активную перевозку и не может быть отмечен доступным.',
            ]);
        }

        $old = $vehicle->only(array_keys($data));
        $vehicle->update($data);
        $audit->record('vehicle.moderated', $vehicle, $old, $vehicle->only(array_keys($data)));

        return back()->with('status', 'Транспорт обновлен.');
    }

    public function showVehicle(Vehicle $vehicle): Response
    {
        Gate::authorize('viewFreightAdmin', User::class);

        return Inertia::render('Freight/Admin/EntityShow', [
            'type' => 'vehicle',
            'title' => 'Транспорт '.$vehicle->title,
            'entity' => $vehicle->load(['company', 'carrier.company', 'assignedDriver', 'bids.freightLoad.company']),
            'auditLogs' => $this->auditLogsFor($vehicle),
        ]);
    }

    public function updateComplaint(Request $request, Complaint $complaint, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('update', $complaint);

        $data = $request->validate([
            'status' => ['required', 'in:new,in_review,resolved,rejected'],
            'admin_comment' => ['nullable', 'string', 'max:3000'],
        ]);

        $old = $complaint->only(['status', 'admin_comment']);
        $complaint->update($data);
        $audit->record('complaint.updated', $complaint, $old, $complaint->only(['status', 'admin_comment']));

        return back()->with('status', 'Жалоба обновлена.');
    }

    public function hideVehicle(Request $request, Vehicle $vehicle, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('moderate', $vehicle);

        $old = $vehicle->only(['is_location_visible']);
        $vehicle->update(['is_location_visible' => $request->boolean('is_location_visible')]);
        $audit->record('vehicle.map_visibility_updated', $vehicle, $old, $vehicle->only(['is_location_visible']));

        return back()->with('status', 'Видимость транспорта обновлена.');
    }

    private function auditLogsFor(object $entity)
    {
        return AuditLog::with('actor')
            ->where('entity_type', $entity::class)
            ->where('entity_id', $entity->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    private function ensureAdminCanSetLoadStatus(FreightLoad $load, ?string $status): void
    {
        if (! $status || $status === $load->status) {
            return;
        }

        $hasAcceptedBid = $load->bids()->where('status', 'accepted')->exists();

        if ($status === 'in_progress') {
            throw ValidationException::withMessages([
                'status' => 'Груз переводится в работу только через выбор принятого отклика.',
            ]);
        }

        if ($status === 'completed') {
            throw ValidationException::withMessages([
                'status' => 'Груз завершается только через подтверждение доставки QR/кодом.',
            ]);
        }

        if ($status === 'active' && (! in_array($load->status, ['draft', 'cancelled'], true) || $hasAcceptedBid)) {
            throw ValidationException::withMessages([
                'status' => 'Опубликовать можно только черновик или отмененный груз без выбранного перевозчика.',
            ]);
        }

        if ($status === 'draft' && (! in_array($load->status, ['draft', 'active', 'cancelled'], true) || $hasAcceptedBid)) {
            throw ValidationException::withMessages([
                'status' => 'Вернуть в черновик можно только груз без выбранного перевозчика.',
            ]);
        }

        if ($status === 'cancelled') {
            if (in_array($load->status, ['completed', 'archived'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Завершенный или архивный груз нельзя отменить.',
                ]);
            }

            if ($load->status === 'in_progress' && ! DeliveryEvent::canCancelDelivery($load->delivery_stage)) {
                throw ValidationException::withMessages([
                    'status' => 'Груз в работе можно отменить только до фактической погрузки.',
                ]);
            }
        }

        if ($status === 'archived' && $load->status === 'in_progress') {
            throw ValidationException::withMessages([
                'status' => 'Активную перевозку нельзя архивировать до отмены или завершения.',
            ]);
        }
    }

    private function releaseAcceptedVehicles(FreightLoad $load): void
    {
        $load->bids
            ->where('status', 'accepted')
            ->pluck('vehicle')
            ->filter()
            ->unique('id')
            ->each(fn (Vehicle $vehicle) => $vehicle->update(['is_available' => true]));
    }

    private function cancelPendingBids(FreightLoad $load): void
    {
        $pendingBids = $load->bids->where('status', 'pending');

        if ($pendingBids->isEmpty()) {
            return;
        }

        $load->bids()
            ->whereKey($pendingBids->pluck('id'))
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

        $pendingBids->each(function (Bid $bid) use ($load): void {
            collect([$bid->carrier_id, $bid->vehicle?->assigned_driver_id])
                ->filter()
                ->unique()
                ->each(fn ($userId) => FreightNotification::create([
                    'user_id' => $userId,
                    'type' => 'load_cancelled',
                    'title' => 'Груз отменён',
                    'message' => 'Груз '.$load->title.', по которому был ваш отклик, отменён администратором.',
                    'data_json' => ['bid_id' => $bid->id, 'load_id' => $load->id, 'action' => 'bid'],
                ]));
        });
    }
}
