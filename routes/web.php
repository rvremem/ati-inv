<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReceivingController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\DefectiveController;
use App\Http\Controllers\DefectivePartController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;

// Root route - landing page for guests, dashboard for authenticated users
Route::get('/', function () {
    return Auth::check() ? redirect('/dashboard') : view('landing');
})->name('home');

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

// Email verification.
//
// Deliberately in NEITHER the 'guest' nor the 'auth' group. Not 'auth', because
// the link is opened from a mail client — routinely in a different browser, or
// a different device, from the one that registered — so requiring a session
// would break the flow for exactly the people it serves. Not 'guest' either:
// that middleware runs before 'signed' and would bounce an already-signed-in
// visitor to the dashboard without ever verifying them.
//
// What secures the verify route is 'signed': the URL carries an expiring HMAC
// over the path, id and hash, so it cannot be forged or replayed once it
// lapses. Throttling blunts brute-forcing the id/hash pair.
Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');
Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:6,1')
    ->name('verification.send');

// Password reset. Same middleware reasoning as email verification above:
// the confirmation link is opened from a mail client, so neither 'auth'
// nor 'guest' can wrap it, and 'signed' is what secures it.
//
// The POST is throttled harder than the rest: it sends mail to an address
// the submitter never has to prove they own, so without a limit it is a
// way to bombard a colleague's inbox.
Route::get('/password/reset', [PasswordResetController::class, 'showRequestForm'])
    ->middleware('guest')->name('password.request');
Route::post('/password/reset', [PasswordResetController::class, 'sendVerification'])
    ->middleware(['guest', 'throttle:5,1'])->name('password.send');
Route::get('/password/reset/sent', [PasswordResetController::class, 'notice'])
    ->middleware('guest')->name('password.notice');
Route::get('/password/reset/{id}/{hash}', [PasswordResetController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])->name('password.verify');

// Protected routes (require authentication)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Inventory Management
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('items', [InventoryController::class, 'items'])->name('items');
        Route::get('items/data', [InventoryController::class, 'itemsData'])->name('items-data');
        Route::get('items/create', [InventoryController::class, 'itemCreate'])->name('item-create');
        Route::post('items', [InventoryController::class, 'itemStore'])->name('item-store');
        Route::get('items/sample-csv', [InventoryController::class, 'itemSampleCsv'])->name('item-sample-csv');
        Route::get('items/serials-csv', [InventoryController::class, 'itemSerialSampleCsv'])->name('item-serials-csv');
        Route::get('items/batch-upload', [InventoryController::class, 'itemBatchUpload'])->name('item-batch-upload');
        Route::post('items/batch-upload', [InventoryController::class, 'itemBatchStore'])->name('item-batch-store');
        Route::get('items/{item}/edit', [InventoryController::class, 'itemEdit'])->name('item-edit');
        Route::put('items/{item}', [InventoryController::class, 'itemUpdate'])->name('item-update');
        Route::delete('items/{item}', [InventoryController::class, 'itemDelete'])->name('item-delete');

        Route::get('suppliers', [InventoryController::class, 'suppliers'])->name('suppliers');
        Route::post('suppliers', [InventoryController::class, 'supplierStore'])->name('supplier-store');
        Route::put('suppliers/{supplier}', [InventoryController::class, 'supplierUpdate'])->name('supplier-update');
        Route::delete('suppliers/{supplier}', [InventoryController::class, 'supplierDelete'])->name('supplier-delete');

        Route::get('locations', [InventoryController::class, 'locations'])->name('locations');
        Route::get('locations/create', [InventoryController::class, 'locationCreate'])->name('location-create');
        Route::post('locations', [InventoryController::class, 'locationStore'])->name('location-store');
        Route::get('locations/sample-csv', [InventoryController::class, 'locationSampleCsv'])->name('location-sample-csv');
        Route::get('locations/batch-upload', [InventoryController::class, 'locationBatchUpload'])->name('location-batch-upload');
        Route::post('locations/batch-upload', [InventoryController::class, 'locationBatchStore'])->name('location-batch-store');
        Route::get('locations/{location}/edit', [InventoryController::class, 'locationEdit'])->name('location-edit');
        Route::put('locations/{location}', [InventoryController::class, 'locationUpdate'])->name('location-update');
        Route::delete('locations/{location}', [InventoryController::class, 'locationDelete'])->name('location-delete');

        Route::get('stocks', [InventoryController::class, 'stocks'])->name('stocks');
        Route::get('stocks/data', [InventoryController::class, 'stocksData'])->name('stocks-data');
        

        Route::get('parts-location', [InventoryController::class, 'partsLocation'])->name('parts-location');
        Route::get('parts-location/data', [InventoryController::class, 'partsLocationData'])->name('parts-location-data');
        Route::get('parts-location/sample-csv', [InventoryController::class, 'partsLocationSampleCsv'])->name('parts-location-sample-csv');
        Route::get('parts-location/batch-upload', [InventoryController::class, 'partsLocationBatchUpload'])->name('parts-location-batch-upload');
        Route::post('parts-location/batch-upload', [InventoryController::class, 'partsLocationBatchStore'])->name('parts-location-batch-store');
        Route::post('parts-location/move', [InventoryController::class, 'partsLocationMove'])->name('parts-location-move');
        Route::get('edit-parts-location', [InventoryController::class, 'editPartsLocation'])->name('edit-parts-location');
        Route::post('edit-parts-location', [InventoryController::class, 'updatePartsLocation'])->name('update-parts-location');
    });

    // Receiving Operations
    Route::prefix('receiving')->name('receiving.')->group(function () {
        Route::get('items', [ReceivingController::class, 'itemReceiving'])->name('items');
        Route::get('items/create', [ReceivingController::class, 'itemReceivingCreate'])->name('item-create');
        Route::post('items', [ReceivingController::class, 'itemReceivingStore'])->name('item-store');
        Route::post('items/batch', [ReceivingController::class, 'itemReceivingBatchStore'])->name('items-batch-store');
        Route::get('items/{receiving}/edit', [ReceivingController::class, 'itemReceivingEdit'])->name('item-edit');
        Route::put('items/{receiving}', [ReceivingController::class, 'itemReceivingUpdate'])->name('item-update');

        Route::get('machines', [ReceivingController::class, 'machineReceiving'])->name('machines');
        Route::get('machines/create', [ReceivingController::class, 'machineReceivingCreate'])->name('machine-create');
        Route::post('machines', [ReceivingController::class, 'machineReceivingStore'])->name('machine-store');
        Route::get('machines/{machine}/edit', [ReceivingController::class, 'machineReceivingEdit'])->name('machine-edit');
        Route::put('machines/{machine}', [ReceivingController::class, 'machineReceivingUpdate'])->name('machine-update');

        Route::get('return-parts', [ReceivingController::class, 'returnParts'])->name('return-parts');
        Route::post('return-parts', [ReceivingController::class, 'returnPartsStore'])->name('return-parts-store');
        Route::get('return-machines', [ReceivingController::class, 'returnMachines'])->name('return-machines');
        Route::post('return-machines', [ReceivingController::class, 'returnMachinesStore'])->name('return-machines-store');
    });

    // Requests
    Route::prefix('requests')->name('requests.')->group(function () {
        Route::get('pending', [RequestController::class, 'pendingApprovals'])->name('pending');
        Route::get('my', [RequestController::class, 'myRequests'])->name('my');

        Route::get('parts', [RequestController::class, 'partRequests'])->name('parts');
        Route::get('parts/create', [RequestController::class, 'partRequestCreate'])->name('part-create');
        Route::post('parts', [RequestController::class, 'partRequestStore'])->name('part-store');
        Route::get('parts/{partRequest}/edit', [RequestController::class, 'partRequestEdit'])->name('part-edit');
        Route::put('parts/{partRequest}', [RequestController::class, 'partRequestUpdate'])->name('part-update');
        Route::post('parts/{partRequest}/approve', [RequestController::class, 'partRequestApprove'])->name('part-approve');
        Route::post('parts/{partRequest}/decline', [RequestController::class, 'partRequestDecline'])->name('part-decline');

        Route::get('machines', [RequestController::class, 'machineRequests'])->name('machines');
        Route::get('machines/create', [RequestController::class, 'machineRequestCreate'])->name('machine-create');
        Route::post('machines', [RequestController::class, 'machineRequestStore'])->name('machine-store');
        Route::get('machines/{machineRequest}/edit', [RequestController::class, 'machineRequestEdit'])->name('machine-edit');
        Route::put('machines/{machineRequest}', [RequestController::class, 'machineRequestUpdate'])->name('machine-update');
        Route::post('machines/{machineRequest}/approve', [RequestController::class, 'machineRequestApprove'])->name('machine-approve');
        Route::post('machines/{machineRequest}/decline', [RequestController::class, 'machineRequestDecline'])->name('machine-decline');

        Route::get('machine-parts', [RequestController::class, 'machinePartRequests'])->name('machine-parts');
        Route::get('machine-parts/create', [RequestController::class, 'machinePartRequestCreate'])->name('machine-parts-create');
        Route::post('machine-parts', [RequestController::class, 'machinePartRequestStore'])->name('machine-parts-store');
        Route::post('machine-parts/{machinePart}/approve', [RequestController::class, 'machinePartApprove'])->name('machine-parts-approve');
        Route::post('machine-parts/{machinePart}/decline', [RequestController::class, 'machinePartDecline'])->name('machine-parts-decline');
    });

    // Defective Items
    Route::prefix('defective')->name('defective.')->group(function () {
        Route::get('/', [DefectiveController::class, 'index'])->name('index');
        Route::get('create', [DefectiveController::class, 'create'])->name('create');
        Route::post('/', [DefectiveController::class, 'store'])->name('store');
        Route::get('{defectiveItem}/edit', [DefectiveController::class, 'edit'])->name('edit');
        Route::put('{defectiveItem}', [DefectiveController::class, 'update'])->name('update');
        Route::delete('{defectiveItem}', [DefectiveController::class, 'destroy'])->name('destroy');
        Route::get('report', [DefectiveController::class, 'report'])->name('report');
    });

    // Defective Parts
    // `show` excluded: the controller has no show() and there is no detail
    // view, so the generated defective-parts/{id} route was a 500 waiting to
    // happen. Nothing links to it.
    Route::resource('defective-parts', DefectivePartController::class)->except(['show']);

    // Maintenance
    Route::prefix('maintenance')->name('maintenance.')->group(function () {
        Route::get('/', [MaintenanceController::class, 'index'])->name('index');
        Route::get('data', [MaintenanceController::class, 'contractsData'])->name('data');
        Route::get('create', [MaintenanceController::class, 'create'])->name('create');
        Route::post('/', [MaintenanceController::class, 'store'])->name('store');
        Route::get('{contract}/edit', [MaintenanceController::class, 'edit'])->name('edit');
        Route::put('{contract}', [MaintenanceController::class, 'update'])->name('update');
        Route::delete('{contract}', [MaintenanceController::class, 'destroy'])->name('destroy');
        Route::get('report', [MaintenanceController::class, 'report'])->name('report');
        Route::get('report/data', [MaintenanceController::class, 'reportData'])->name('report-data');
        Route::get('report/csv', [MaintenanceController::class, 'reportCsv'])->name('report-csv');
        Route::get('sample-csv', [MaintenanceController::class, 'sampleCsv'])->name('sample-csv');
        Route::get('batch-upload', [MaintenanceController::class, 'batchUpload'])->name('batch-upload');
        Route::post('batch-upload', [MaintenanceController::class, 'batchStore'])->name('batch-store');
    });

    // Machines
    Route::prefix('machines')->name('machines.')->group(function () {
        Route::get('/', [MachineController::class, 'index'])->name('index');
        Route::get('create', [MachineController::class, 'create'])->name('create');
        Route::post('/', [MachineController::class, 'store'])->name('store');
        // NOTE: static routes must be registered before the {machine} wildcard
        // below, or e.g. "onhand" gets swallowed as a {machine} value instead.
        Route::get('onhand', [MachineController::class, 'onhand'])->name('onhand');
        Route::get('batch-upload', [MachineController::class, 'batchUpload'])->name('batch-upload');
        Route::post('batch-upload', [MachineController::class, 'batchStore'])->name('batch-store');
        Route::get('batch-upload/machines-template', [MachineController::class, 'machineTemplateCsv'])->name('machines-template');
        Route::get('batch-upload/parts-template', [MachineController::class, 'machinePartTemplateCsv'])->name('parts-template');
        Route::delete('parts/{part}', [MachineController::class, 'removePart'])->name('remove-part');
        Route::get('{machine}', [MachineController::class, 'show'])->name('show');
        Route::get('{machine}/edit', [MachineController::class, 'edit'])->name('edit');
        Route::put('{machine}', [MachineController::class, 'update'])->name('update');
        Route::delete('{machine}', [MachineController::class, 'destroy'])->name('destroy');
        Route::get('{machine}/add-part', [MachineController::class, 'addPart'])->name('add-part');
        Route::post('{machine}/add-part', [MachineController::class, 'storePartForm'])->name('store-part');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('receiving', [ReportController::class, 'receivingReport'])->name('receiving');
        Route::get('receiving/data', [ReportController::class, 'receivingData'])->name('receiving-data');
        Route::get('outbound', [ReportController::class, 'outboundReport'])->name('outbound');
        Route::get('outbound/data', [ReportController::class, 'outboundData'])->name('outbound-data');
        Route::get('defective', [ReportController::class, 'defectiveReport'])->name('defective');
        Route::get('defective/data', [ReportController::class, 'defectiveData'])->name('defective-data');
        Route::get('stock', [ReportController::class, 'stockReport'])->name('stock');
        // Gated to SA/Developer inside the controller, not here, so the
        // refusal is enforced even if the link is reached directly.
        Route::get('cost', [ReportController::class, 'costReport'])->name('cost');
        Route::get('cost/csv', [ReportController::class, 'costReportCsv'])->name('cost-csv');
        Route::get('machines', [ReportController::class, 'machineReport'])->name('machines');
        Route::get('machines/data', [ReportController::class, 'machineData'])->name('machines-data');
        Route::get('machine-inbound', [ReportController::class, 'machineInboundReport'])->name('machine-inbound');
        Route::get('machine-inbound/data', [ReportController::class, 'machineInboundData'])->name('machine-inbound-data');
        Route::get('machine-outbound', [ReportController::class, 'machineOutboundReport'])->name('machine-outbound');
        Route::get('machine-outbound/data', [ReportController::class, 'machineOutboundData'])->name('machine-outbound-data');
        Route::get('machine-parts', [ReportController::class, 'machinePartsReport'])->name('machine-parts');
        Route::get('machine-parts/data', [ReportController::class, 'machinePartsData'])->name('machine-parts-data');
        Route::get('maintenance', [ReportController::class, 'maintenanceReport'])->name('maintenance');
        Route::get('maintenance/data', [ReportController::class, 'maintenanceData'])->name('maintenance-data');
        Route::get('activity-log', [ReportController::class, 'activityLog'])->name('activity-log');
        Route::get('activity-log/data', [ReportController::class, 'activityLogData'])->name('activity-log-data');
        Route::get('activity-log/csv', [ReportController::class, 'activityLogCsv'])->name('activity-log-csv');
        Route::get('stock/csv', [ReportController::class, 'stockReportCsv'])->name('stock-csv');
        Route::get('request-status', [ReportController::class, 'requestStatus'])->name('request-status');
        Route::get('request-status/csv', [ReportController::class, 'requestStatusCsv'])->name('request-status-csv');

        // One CSV endpoint for every DataTables-backed report; the slug
        // picks which one, and unknown slugs 404. Registered last so it
        // cannot shadow a named route above.
        Route::get('{report}/csv', [ReportController::class, 'exportCsv'])
            ->whereIn('report', array_keys(ReportController::CSV_REPORTS))
            ->name('export-csv');
    });

    // Settings (Master Data)
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('suppliers', [SettingsController::class, 'suppliers'])->name('suppliers');
        Route::get('suppliers/create', [SettingsController::class, 'supplierCreate'])->name('supplier-create');
        Route::post('suppliers', [SettingsController::class, 'supplierStore'])->name('supplier-store');
        Route::get('suppliers/{supplier}/edit', [SettingsController::class, 'supplierEdit'])->name('supplier-edit');
        Route::put('suppliers/{supplier}', [SettingsController::class, 'supplierUpdate'])->name('supplier-update');
        Route::delete('suppliers/{supplier}', [SettingsController::class, 'supplierDelete'])->name('supplier-delete');

        Route::get('customers', [SettingsController::class, 'customers'])->name('customers');
        Route::get('customers/create', [SettingsController::class, 'customerCreate'])->name('customer-create');
        Route::post('customers', [SettingsController::class, 'customerStore'])->name('customer-store');
        Route::get('customers/sample-csv', [SettingsController::class, 'customerSampleCsv'])->name('customer-sample-csv');
        Route::get('customers/batch-upload', [SettingsController::class, 'customerBatchUpload'])->name('customer-batch-upload');
        Route::post('customers/batch-upload', [SettingsController::class, 'customerBatchStore'])->name('customer-batch-store');
        Route::get('customers/{customer}/edit', [SettingsController::class, 'customerEdit'])->name('customer-edit');
        Route::put('customers/{customer}', [SettingsController::class, 'customerUpdate'])->name('customer-update');
        Route::delete('customers/{customer}', [SettingsController::class, 'customerDelete'])->name('customer-delete');

        Route::get('categories', [SettingsController::class, 'categories'])->name('categories');
        Route::get('categories/create', [SettingsController::class, 'categoryCreate'])->name('category-create');
        Route::post('categories', [SettingsController::class, 'categoryStore'])->name('category-store');
        Route::get('categories/{category}/edit', [SettingsController::class, 'categoryEdit'])->name('category-edit');
        Route::put('categories/{category}', [SettingsController::class, 'categoryUpdate'])->name('category-update');
        Route::delete('categories/{category}', [SettingsController::class, 'categoryDelete'])->name('category-delete');
    });

    // Users
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('{user}', [UserController::class, 'update'])->name('update');
        Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
        Route::get('profile', [UserController::class, 'profile'])->name('profile');
        Route::put('profile', [UserController::class, 'updateProfile'])->name('profile-update');
    });

    // API Routes
    Route::prefix('api')->group(function () {
        Route::get('item/{itemId}/available-serials', [InventoryController::class, 'getAvailableSerials']);
        Route::get('item/{itemId}/available-quantity', [InventoryController::class, 'getPartOnHandQuantity']);
        Route::get('serial/{serial}/location', [InventoryController::class, 'getSerialLocation']);
        Route::get('item/{itemId}/location', [InventoryController::class, 'getPartLocation']);
        Route::get('returnable-serials', [InventoryController::class, 'getReturnableSerials']);
        Route::get('serial/{serial}/part', [InventoryController::class, 'getPartBySerial']);
        Route::get('returnable-parts-with-serials', [InventoryController::class, 'getReturnablePartsWithSerials']);
        Route::get('all-parts', [InventoryController::class, 'getAllParts']);
        Route::get('item/{itemPartId}/returnable-serials', [InventoryController::class, 'getSerialsByItemPart']);
        Route::get('returnable-machines', [InventoryController::class, 'getReturnableMachines']);
        Route::get('machine/{machineId}/available-parts', [InventoryController::class, 'getMachineAvailableParts']);
    });
});
