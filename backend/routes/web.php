<?php

use App\Http\Controllers\CardPaymentController;
use App\Http\Controllers\CardReconController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\CreditCustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DsrController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\OilSaleController;
use App\Http\Controllers\PosTransactionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\StockTransactionController;
use App\Http\Controllers\TankDipController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // Station selection (for owners)
    Route::get('/select-station', [DashboardController::class, 'selectStation'])->name('select-station');
    Route::post('/switch-station', [DashboardController::class, 'switchStation'])->name('station.switch');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/owner/dashboard', [DashboardController::class, 'ownerDashboard'])->name('owner.dashboard');

    // Station settings
    Route::get('/station/settings', [StationController::class, 'show'])->name('station.settings');
    Route::post('/station/products', [StationController::class, 'storeProduct'])->name('station.products.store');
    Route::put('/station/products/{product}', [StationController::class, 'updateProduct'])->name('station.products.update');
    Route::post('/station/tanks', [StationController::class, 'storeTank'])->name('station.tanks.store');
    Route::put('/station/tanks/{tank}', [StationController::class, 'updateTank'])->name('station.tanks.update');
    Route::post('/station/prices', [StationController::class, 'storePrice'])->name('station.prices.store');

    // Shifts
    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
    Route::post('/shifts/{shift}/generate-dsr', [ShiftController::class, 'generateDsr'])->name('shifts.generate-dsr');
    Route::patch('/shifts/{shift}/cash', [ShiftController::class, 'updateCash'])->name('shifts.update-cash');

    // Meter readings
    Route::post('/shifts/{shift}/meter-readings', [MeterReadingController::class, 'store'])->name('meter-readings.store');
    Route::put('/meter-readings/{meterReading}', [MeterReadingController::class, 'update'])->name('meter-readings.update');
    Route::delete('/meter-readings/{meterReading}', [MeterReadingController::class, 'destroy'])->name('meter-readings.destroy');

    // Tank dips
    Route::post('/shifts/{shift}/tank-dips', [TankDipController::class, 'store'])->name('tank-dips.store');
    Route::put('/tank-dips/{tankDip}', [TankDipController::class, 'update'])->name('tank-dips.update');

    // Deliveries
    Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
    Route::post('/deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
    Route::put('/deliveries/{delivery}', [DeliveryController::class, 'update'])->name('deliveries.update');
    Route::delete('/deliveries/{delivery}', [DeliveryController::class, 'destroy'])->name('deliveries.destroy');

    // Payments (global list)
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::put('/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    // Credit customers
    Route::get('/credits', [CreditCustomerController::class, 'index'])->name('credits.index');
    Route::post('/credits', [CreditCustomerController::class, 'store'])->name('credits.store');
    Route::get('/credits/{creditCustomer}', [CreditCustomerController::class, 'show'])->name('credits.show');
    Route::put('/credits/{creditCustomer}', [CreditCustomerController::class, 'update'])->name('credits.update');
    Route::post('/credit-sales', [CreditCustomerController::class, 'storeSale'])->name('credit-sales.store');
    Route::delete('/credit-sales/{creditSale}', [CreditCustomerController::class, 'destroySale'])->name('credit-sales.destroy');
    Route::post('/credits/{creditCustomer}/payments', [CreditCustomerController::class, 'storePayment'])->name('credits.payments.store');

    // Oil sales (lubricants/shop products per shift)
    Route::post('/shifts/{shift}/oil-sales', [OilSaleController::class, 'store'])->name('oil-sales.store');
    Route::delete('/oil-sales/{oilSale}', [OilSaleController::class, 'destroy'])->name('oil-sales.destroy');

    // Card payments
    Route::post('/shifts/{shift}/card-payments', [CardPaymentController::class, 'store'])->name('card-payments.store');
    Route::delete('/card-payments/{cardPayment}', [CardPaymentController::class, 'destroy'])->name('card-payments.destroy');

    // Card recons
    Route::get('/card-recons', [CardReconController::class, 'index'])->name('card-recons.index');
    Route::post('/card-recons', [CardReconController::class, 'store'])->name('card-recons.store');
    Route::put('/card-recons/{cardRecon}', [CardReconController::class, 'update'])->name('card-recons.update');
    Route::delete('/card-recons/{cardRecon}', [CardReconController::class, 'destroy'])->name('card-recons.destroy');
    Route::post('/card-recons/{cardRecon}/lines', [CardReconController::class, 'storeLine'])->name('card-recon-lines.store');
    Route::delete('/card-recon-lines/{cardReconLine}', [CardReconController::class, 'destroyLine'])->name('card-recon-lines.destroy');

    // POS transactions
    Route::get('/pos-account', [PosTransactionController::class, 'index'])->name('pos-account.index');
    Route::post('/shifts/{shift}/pos-transactions', [PosTransactionController::class, 'store'])->name('pos-transactions.store');
    Route::put('/pos-transactions/{posTransaction}', [PosTransactionController::class, 'update'])->name('pos-transactions.update');
    Route::delete('/pos-transactions/{posTransaction}', [PosTransactionController::class, 'destroy'])->name('pos-transactions.destroy');

    // Expenses
    Route::post('/shifts/{shift}/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // Station nozzle management
    Route::post('/station/nozzles', [StationController::class, 'storeNozzle'])->name('station.nozzles.store');
    Route::put('/station/nozzles/{nozzle}', [StationController::class, 'updateNozzle'])->name('station.nozzles.update');
    Route::delete('/station/nozzles/{nozzle}', [StationController::class, 'destroyNozzle'])->name('station.nozzles.destroy');
    Route::post('/station/shop-products', [StationController::class, 'storeShopProduct'])->name('station.shop-products.store');
    Route::put('/station/shop-products/{shopProduct}', [StationController::class, 'updateShopProduct'])->name('station.shop-products.update');
    Route::post('/station/shop-products/{shopProduct}/transactions', [StockTransactionController::class, 'store'])->name('stock-transactions.store');
    Route::delete('/station/stock-transactions/{stockTransaction}', [StockTransactionController::class, 'destroy'])->name('stock-transactions.destroy');

    // DSR
    Route::get('/dsr', [DsrController::class, 'index'])->name('dsr.index');
    Route::get('/dsr/{dsr}', [DsrController::class, 'show'])->name('dsr.show');
    Route::post('/dsr/{dsr}/approve', [DsrController::class, 'approve'])->name('dsr.approve');
    Route::post('/dsr/{dsr}/adjustments', [DsrController::class, 'storeAdjustment'])->name('dsr.adjustments.store');

    // Reports
    Route::get('/reports/wet-stock', [ReportController::class, 'wetStock'])->name('reports.wet-stock');
    Route::get('/reports/sales', [ReportController::class, 'salesSummary'])->name('reports.sales');
    Route::get('/reports/deliveries', [ReportController::class, 'deliveryHistory'])->name('reports.deliveries');
    Route::get('/reports/variance', [ReportController::class, 'varianceReport'])->name('reports.variance');
    Route::get('/reports/credit/{creditCustomer}', [ReportController::class, 'creditStatement'])->name('reports.credit-statement');

    // Profile (Breeze default)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
