<?php

use App\Actions\SubmitFeedback;
use App\Actions\ZipToTimezone;
use App\Http\Controllers\Freight\AdminController as FreightAdminController;
use App\Http\Controllers\Freight\BidController as FreightBidController;
use App\Http\Controllers\Freight\CarrierDeliveryController as FreightCarrierDeliveryController;
use App\Http\Controllers\Freight\CompanyController as FreightCompanyController;
use App\Http\Controllers\Freight\ComplaintController as FreightComplaintController;
use App\Http\Controllers\Freight\DeliveryEventController as FreightDeliveryEventController;
use App\Http\Controllers\Freight\DispatcherController as FreightDispatcherController;
use App\Http\Controllers\Freight\GeocoderController as FreightGeocoderController;
use App\Http\Controllers\Freight\LegalController as FreightLegalController;
use App\Http\Controllers\Freight\LoadController as FreightLoadController;
use App\Http\Controllers\Freight\MapController as FreightMapController;
use App\Http\Controllers\Freight\NotificationController as FreightNotificationController;
use App\Http\Controllers\Freight\VehicleController as FreightVehicleController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TimezoneController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/feedback', SubmitFeedback::class)->name('feedback.submit');

Route::get('/', function () {
    return Inertia::render('Freight/Home', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'disclaimer' => config('freight.legal_disclaimer'),
    ]);
})->name('home');

Route::get('/loads', [FreightLoadController::class, 'index'])->name('loads.index');
Route::get('/loads/{load}', [FreightLoadController::class, 'show'])->whereNumber('load')->name('loads.show');
Route::get('/vehicles', [FreightVehicleController::class, 'catalog'])->name('vehicles.index');
Route::get('/vehicles/{vehicle}', [FreightVehicleController::class, 'show'])->whereNumber('vehicle')->name('vehicles.show');
Route::get('/map', [FreightMapController::class, 'page'])->name('map');
Route::get('/api/map/objects', [FreightMapController::class, 'objects'])->name('api.map.objects');
Route::get('/api/geocoder/suggest', [FreightGeocoderController::class, 'suggest'])
    ->middleware('throttle:30,1')
    ->name('api.geocoder.suggest');
Route::get('/legal/disclaimer', [FreightLegalController::class, 'disclaimer'])->name('legal.disclaimer');
Route::get('/legal/terms', [FreightLegalController::class, 'terms'])->name('legal.terms');

Route::middleware('auth')->group(function () {
    Route::get('/company', [FreightCompanyController::class, 'edit'])->name('freight.company.edit');
    Route::post('/company', [FreightCompanyController::class, 'update'])->name('freight.company.update');
    Route::post('/company/carriers', [FreightCompanyController::class, 'addCarrierMember'])
        ->middleware('freight.role:carrier')
        ->name('freight.company.carriers.store');

    Route::get('/loads/create', [FreightLoadController::class, 'create'])->middleware('freight.role:shipper')->name('loads.create');
    Route::post('/loads', [FreightLoadController::class, 'store'])->middleware('freight.role:shipper')->name('loads.store');
    Route::get('/my-loads', [FreightLoadController::class, 'mine'])->middleware('freight.role:shipper')->name('loads.mine');
    Route::get('/my-loads/{load}/bids', [FreightLoadController::class, 'bids'])->whereNumber('load')->middleware('freight.role:shipper')->name('loads.bids');
    Route::get('/my-loads/{load}/delivery', [FreightLoadController::class, 'delivery'])->whereNumber('load')->middleware('freight.role:shipper')->name('loads.delivery');
    Route::get('/loads/{load}/edit', [FreightLoadController::class, 'edit'])->whereNumber('load')->name('loads.edit');
    Route::get('/loads/{load}/contract', [FreightLoadController::class, 'contract'])->whereNumber('load')->name('loads.contract');
    Route::get('/loads/{load}/contract/download', [FreightLoadController::class, 'contractDownload'])->whereNumber('load')->name('loads.contract.download');
    Route::put('/loads/{load}', [FreightLoadController::class, 'update'])->whereNumber('load')->name('loads.update');
    Route::patch('/loads/{load}/publish', [FreightLoadController::class, 'publish'])->name('loads.publish');
    Route::patch('/loads/{load}/cancel', [FreightLoadController::class, 'cancel'])->name('loads.cancel');
    Route::patch('/loads/{load}/complete', [FreightLoadController::class, 'complete'])->name('loads.complete');
    Route::post('/loads/{load}/delivery-events', [FreightDeliveryEventController::class, 'store'])->name('loads.delivery-events.store');

    Route::get('/my-bids', [FreightBidController::class, 'index'])->middleware('freight.role:carrier')->name('bids.mine');
    Route::post('/loads/{load}/bids', [FreightBidController::class, 'store'])->middleware('freight.role:carrier')->name('bids.store');
    Route::patch('/bids/{bid}/accept', [FreightBidController::class, 'accept'])->name('bids.accept');
    Route::patch('/bids/{bid}/cancel', [FreightBidController::class, 'cancel'])->name('bids.cancel');
    Route::post('/bids/{bid}/carrier-photo', [FreightBidController::class, 'uploadCarrierCargoPhoto'])
        ->middleware('freight.role:carrier')
        ->name('bids.carrier-photo');

    Route::get('/my-deliveries', [FreightCarrierDeliveryController::class, 'index'])->middleware('freight.role:carrier')->name('carrier.deliveries.index');
    Route::get('/my-deliveries/{bid}', [FreightCarrierDeliveryController::class, 'show'])->whereNumber('bid')->middleware('freight.role:carrier')->name('carrier.deliveries.show');
    Route::get('/my-vehicles', [FreightVehicleController::class, 'index'])->middleware('freight.role:carrier')->name('vehicles.mine');
    Route::post('/vehicles', [FreightVehicleController::class, 'store'])->middleware('freight.role:carrier')->name('vehicles.store');
    Route::get('/vehicles/{vehicle}/edit', [FreightVehicleController::class, 'edit'])->whereNumber('vehicle')->name('vehicles.edit');
    Route::put('/vehicles/{vehicle}', [FreightVehicleController::class, 'update'])->middleware('freight.role:carrier,admin')->name('vehicles.update');
    Route::get('/carrier/location', [FreightVehicleController::class, 'location'])->middleware('freight.role:carrier')->name('carrier.location');
    Route::post('/vehicles/{vehicle}/location', [FreightVehicleController::class, 'updateLocation'])
        ->middleware('freight.role:carrier')
        ->name('vehicles.location.update');
    Route::get('/api/map/accepted-route/{load}', [FreightMapController::class, 'acceptedRoute'])
        ->whereNumber('load')
        ->name('api.map.accepted-route');

    Route::get('/notifications', [FreightNotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [FreightNotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [FreightNotificationController::class, 'read'])->name('notifications.read');
    Route::get('/complaints', [FreightComplaintController::class, 'index'])->name('complaints.index');
    Route::post('/complaints', [FreightComplaintController::class, 'store'])->name('complaints.store');

    Route::middleware('freight.role:dispatcher,admin')->group(function () {
        Route::get('/dispatcher', [FreightDispatcherController::class, 'index'])->name('dispatcher.index');
        Route::get('/dispatcher/map', [FreightMapController::class, 'page'])->name('dispatcher.map');
        Route::get('/dispatcher/connections', [FreightDispatcherController::class, 'connections'])->name('dispatcher.connections.index');
        Route::post('/dispatcher/connections', [FreightDispatcherController::class, 'storeConnection'])->name('dispatcher.connections.store');
        Route::get('/dispatcher/connections/{connection}', [FreightDispatcherController::class, 'show'])->name('dispatcher.connections.show');
        Route::patch('/dispatcher/connections/{connection}', [FreightDispatcherController::class, 'updateConnection'])->name('dispatcher.connections.update');
        Route::get('/dispatcher/loads/{load}/nearest-carriers', [FreightDispatcherController::class, 'nearestCarriers'])->name('dispatcher.loads.nearest-carriers');
    });

    Route::middleware('freight.role:admin')->group(function () {
        Route::get('/admin/freight', [FreightAdminController::class, 'index'])->name('admin.freight.index');
        Route::get('/admin/freight/users/{user}', [FreightAdminController::class, 'showUser'])->name('admin.freight.users.show');
        Route::patch('/admin/freight/users/{user}', [FreightAdminController::class, 'updateUser'])->name('admin.freight.users.update');
        Route::get('/admin/freight/companies/{company}', [FreightAdminController::class, 'showCompany'])->name('admin.freight.companies.show');
        Route::patch('/admin/freight/companies/{company}', [FreightAdminController::class, 'updateCompany'])->name('admin.freight.companies.update');
        Route::get('/admin/freight/loads/{load}', [FreightAdminController::class, 'showLoad'])->name('admin.freight.loads.show');
        Route::patch('/admin/freight/loads/{load}', [FreightAdminController::class, 'updateLoad'])->name('admin.freight.loads.update');
        Route::get('/admin/freight/vehicles/{vehicle}', [FreightAdminController::class, 'showVehicle'])->name('admin.freight.vehicles.show');
        Route::patch('/admin/freight/vehicles/{vehicle}', [FreightAdminController::class, 'updateVehicle'])->name('admin.freight.vehicles.update');
        Route::patch('/admin/freight/vehicles/{vehicle}/visibility', [FreightAdminController::class, 'hideVehicle'])->name('admin.freight.vehicles.visibility');
        Route::patch('/admin/freight/complaints/{complaint}', [FreightAdminController::class, 'updateComplaint'])->name('admin.freight.complaints.update');
    });

    Route::get('dashboard', function (Request $request) {
        $user = $request->user();

        if ($user?->isShipper()) {
            return redirect()->route('loads.mine');
        }

        if ($user?->isCarrier()) {
            return redirect()->route('carrier.deliveries.index');
        }

        if ($user?->isDispatcher()) {
            return redirect()->route('dispatcher.index');
        }

        if ($user?->isAdmin()) {
            return redirect()->route('admin.freight.index');
        }

        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/language', [ProfileController::class, 'updateLanguage'])->name('profile.language');
    Route::get('/profile-photos/{userId}/{filename}', [ProfileController::class, 'getPhoto'])->name('profile.photo');
    Route::get('/languages', [LanguageController::class, 'index'])->name('languages.index');
    Route::get('timezones/search', [TimezoneController::class, 'search'])->name('timezones.search');
    Route::get('timezones/zipcode', ZipToTimezone::class)->name('timezones.zipcode');
});

require __DIR__.'/auth.php';
