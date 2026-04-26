<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ChangeOrderController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\ClaimAnalysisController;
use App\Http\Controllers\ClaimStatusController;
use App\Http\Controllers\DelayAnalysisController;
use App\Http\Controllers\PriceItemController;
use App\Http\Controllers\QuantumController;
use App\Http\Controllers\RightsController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\LandlordController;
use App\Http\Controllers\Auth\LandlordLoginController;
use Illuminate\Support\Facades\Route;

// Raíz sin tenant — redirige al panel landlord o al onboarding
Route::get('/', fn () => redirect()->route('landlord.index'));

/*
|--------------------------------------------------------------------------
| Rutas del Panel Landlord (administración central)
|--------------------------------------------------------------------------
*/
Route::prefix('landlord')->name('landlord.')->group(function () {
    // Login público (solo invitados)
    Route::middleware('guest')->group(function () {
        Route::get('/login',  [LandlordLoginController::class, 'show'])->name('login');
        Route::post('/login', [LandlordLoginController::class, 'store'])->name('login.store');
    });
    Route::post('/logout', [LandlordLoginController::class, 'destroy'])->name('logout');

    // Panel protegido — requiere super_admin
    Route::middleware(\App\Http\Middleware\LandlordAuth::class)->group(function () {
        Route::get('/',                                      [LandlordController::class, 'index'])->name('index');
        Route::patch('/tenants/{tenant}/toggle',             [LandlordController::class, 'toggleActive'])->name('tenants.toggle');
        Route::post('/tenants/{tenant}/subscription',        [LandlordController::class, 'updateSubscription'])->name('tenants.subscription');
        Route::get('/plans',                                 [LandlordController::class, 'plansIndex'])->name('plans.index');
        Route::post('/plans',                                [LandlordController::class, 'plansStore'])->name('plans.store');
        Route::put('/plans/{plan}',                          [LandlordController::class, 'plansUpdate'])->name('plans.update');
    });
});

/*
|--------------------------------------------------------------------------
| Rutas de Onboarding (landlord — sin tenant activo)
|--------------------------------------------------------------------------
*/
Route::prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/',                         [OnboardingController::class, 'show'])->name('show');
    Route::post('/',                        [OnboardingController::class, 'store'])->name('store');
    Route::get('/success/{tenant}',         [OnboardingController::class, 'success'])->name('success');
    Route::get('/check-slug',               [OnboardingController::class, 'checkSlug'])->name('check-slug');
});

/*
|--------------------------------------------------------------------------
| Rutas del Tenant (requieren subdominio válido)
|--------------------------------------------------------------------------
*/
Route::middleware(['tenant', 'tenant.active'])->group(function () {

    // Solo para invitados
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    });

    // Callback OAuth OneDrive Personal (llega como redirect, no requiere auth)
    Route::get('/settings/microsoft/callback', [\App\Http\Controllers\SettingsController::class, 'oneDriveCallback'])->name('settings.onedrive-personal.callback');

    // Rutas protegidas
    Route::middleware(['auth', 'tenant.settings'])->group(function () {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/home', fn () => redirect()->route('dashboard'))->name('home');

        // Empresas (mandantes y contratistas)
        Route::resource('companies', CompanyController::class)
            ->except(['show']);

        // Contratos
        Route::resource('contracts', ContractController::class);
        Route::get('contracts/{contract}/claim-status', [ClaimStatusController::class, 'show'])->name('contracts.claim-status');
        Route::patch('contracts/{contract}/status', [ContractController::class, 'changeStatus'])
            ->name('contracts.changeStatus');
        Route::post('contracts/{contract}/upload-pdf', [ContractController::class, 'uploadPdf'])
            ->name('contracts.upload-pdf');
        Route::delete('contracts/{contract}/remove-pdf', [ContractController::class, 'removePdf'])
            ->name('contracts.remove-pdf');
        Route::post('contracts/{contract}/users', [ContractController::class, 'assignUser'])
            ->name('contracts.users.assign');
        Route::delete('contracts/{contract}/users/{contractUser}', [ContractController::class, 'removeUser'])
            ->name('contracts.users.remove');
        Route::post('contracts/{contract}/corpus', [ContractController::class, 'uploadCorpusDoc'])
            ->name('contracts.corpus.upload');
        Route::delete('contracts/{contract}/corpus/{document}', [ContractController::class, 'removeCorpusDoc'])
            ->name('contracts.corpus.remove');

        // Programa de trabajo (hitos)
        Route::get('milestones', [MilestoneController::class, 'index'])->name('milestones.index');
        Route::post('contracts/{contract}/milestones', [MilestoneController::class, 'store'])->name('milestones.store');
        Route::put('contracts/{contract}/milestones/{milestone}', [MilestoneController::class, 'update'])->name('milestones.update');
        Route::delete('contracts/{contract}/milestones/{milestone}', [MilestoneController::class, 'destroy'])->name('milestones.destroy');
        Route::post('contracts/{contract}/milestones/import', [MilestoneController::class, 'import'])->name('milestones.import');
        Route::post('contracts/{contract}/milestones/import-ms-project', [MilestoneController::class, 'importMsProject'])->name('milestones.import-ms-project');
        Route::post('contracts/{contract}/milestones/import-primavera', [MilestoneController::class, 'importPrimavera'])->name('milestones.import-primavera');

        // Eventos contractuales
        Route::get('events', [EventController::class, 'index'])->name('events.index');
        Route::post('contracts/{contract}/events', [EventController::class, 'store'])->name('events.store');
        Route::put('contracts/{contract}/events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::delete('contracts/{contract}/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

        // Cartas contractuales
        Route::get('letters', [LetterController::class, 'index'])->name('letters.index');
        Route::post('contracts/{contract}/letters', [LetterController::class, 'store'])->name('letters.store');
        Route::put('contracts/{contract}/letters/{letter}', [LetterController::class, 'update'])->name('letters.update');
        Route::post('contracts/{contract}/letters/{letter}/request-draft', [LetterController::class, 'requestDraft'])->name('letters.request-draft');
        Route::delete('contracts/{contract}/letters/{letter}', [LetterController::class, 'destroy'])->name('letters.destroy');

        // Órdenes de cambio
        Route::get('change-orders', [ChangeOrderController::class, 'index'])->name('change-orders.index');
        Route::post('contracts/{contract}/change-orders', [ChangeOrderController::class, 'store'])->name('change-orders.store');
        Route::put('contracts/{contract}/change-orders/{changeOrder}', [ChangeOrderController::class, 'update'])->name('change-orders.update');
        Route::delete('contracts/{contract}/change-orders/{changeOrder}', [ChangeOrderController::class, 'destroy'])->name('change-orders.destroy');

        // Diarios de obra
        Route::get('daily-reports', [DailyReportController::class, 'index'])->name('daily-reports.index');
        Route::get('daily-reports/create', [DailyReportController::class, 'create'])->name('daily-reports.create');
        Route::post('contracts/{contract}/daily-reports', [DailyReportController::class, 'store'])->name('daily-reports.store');
        Route::get('contracts/{contract}/daily-reports/{dailyReport}/edit', [DailyReportController::class, 'edit'])->name('daily-reports.edit');
        Route::put('contracts/{contract}/daily-reports/{dailyReport}', [DailyReportController::class, 'update'])->name('daily-reports.update');
        Route::delete('contracts/{contract}/daily-reports/{dailyReport}', [DailyReportController::class, 'destroy'])->name('daily-reports.destroy');

        // Documentos
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('contracts/{contract}/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::delete('contracts/{contract}/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

        // CPU — Cuadro de Precios Unitarios por contrato
        Route::post('contracts/{contract}/price-items', [PriceItemController::class, 'store'])->name('price-items.store');
        Route::put('contracts/{contract}/price-items/{priceItem}', [PriceItemController::class, 'update'])->name('price-items.update');
        Route::delete('contracts/{contract}/price-items/{priceItem}', [PriceItemController::class, 'destroy'])->name('price-items.destroy');
        Route::get('contracts/{contract}/price-items/template', [PriceItemController::class, 'template'])->name('price-items.template');
        Route::post('contracts/{contract}/price-items/import', [PriceItemController::class, 'import'])->name('price-items.import');

        // Quantum — desglose de costos por evento
        Route::get('quantum', [QuantumController::class, 'index'])->name('quantum.index');
        Route::get('contracts/{contract}/quantum/export', [QuantumController::class, 'exportContract'])->name('quantum.export.contract');
        Route::get('contracts/{contract}/events/{event}/quantum', [QuantumController::class, 'show'])->name('quantum.show');
        Route::get('contracts/{contract}/events/{event}/quantum/export', [QuantumController::class, 'exportEvent'])->name('quantum.export.event');
        Route::post('contracts/{contract}/events/{event}/quantum/items', [QuantumController::class, 'storeCostItem'])->name('quantum.items.store');
        Route::delete('contracts/{contract}/events/{event}/quantum/items/{costItem}', [QuantumController::class, 'destroyCostItem'])->name('quantum.items.destroy');

        // CPM — Análisis de plazo por evento
        Route::get('delay-analysis', [DelayAnalysisController::class, 'index'])->name('delay-analysis.index');
        Route::get('contracts/{contract}/events/{event}/delay-analysis', [DelayAnalysisController::class, 'show'])->name('delay-analysis.show');
        Route::post('contracts/{contract}/events/{event}/delay-analysis', [DelayAnalysisController::class, 'save'])->name('delay-analysis.save');

        // Reserva de derechos
        Route::get('rights', [RightsController::class, 'index'])->name('rights.index');

        // Análisis IA de exposición al claim
        Route::get('analysis', [ClaimAnalysisController::class, 'index'])->name('analysis.index');
        Route::post('contracts/{contract}/analysis/generate', [ClaimAnalysisController::class, 'generate'])->name('analysis.generate');
        Route::get('contracts/{contract}/analysis/status', [ClaimAnalysisController::class, 'status'])->name('analysis.status');

        // Indicador de riesgo de Claim
        Route::get('risk', [RiskController::class, 'index'])->name('risk.index');
        Route::get('risk/{contract}', [RiskController::class, 'show'])->name('risk.show');
        Route::post('risk/{contract}/recalculate', [RiskController::class, 'recalculate'])->name('risk.recalculate');

        // Reportes
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/contracts/excel', [ReportController::class, 'exportContractsExcel'])->name('reports.contracts.excel');
        Route::get('reports/contracts/pdf',   [ReportController::class, 'exportContractsPdf'])->name('reports.contracts.pdf');
        Route::get('reports/events/excel',    [ReportController::class, 'exportEventsExcel'])->name('reports.events.excel');
        Route::get('reports/letters/excel',   [ReportController::class, 'exportLettersExcel'])->name('reports.letters.excel');
        Route::get('reports/letters/pdf',     [ReportController::class, 'exportLettersPdf'])->name('reports.letters.pdf');
        Route::get('reports/change-orders/excel', [ReportController::class, 'exportChangeOrdersExcel'])->name('reports.change-orders.excel');
        Route::get('reports/curva-s/excel',   [ReportController::class, 'exportCurvaSExcel'])->name('reports.curva-s.excel');

        // Expediente de Claim
        Route::get('expediente', [ExpedienteController::class, 'index'])->name('expediente.index');
        Route::post('contracts/{contract}/expediente/generate', [ExpedienteController::class, 'generate'])->name('expediente.generate');
        Route::get('contracts/{contract}/expediente/download', [ExpedienteController::class, 'download'])->name('expediente.download');

        // Configuración del tenant
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.tenant');
        Route::post('settings/colors', [SettingsController::class, 'saveColors'])->name('settings.colors');
        Route::post('settings/colors/reset', [SettingsController::class, 'resetColors'])->name('settings.colors.reset');
        Route::post('settings/thresholds', [SettingsController::class, 'saveThresholds'])->name('settings.thresholds');
        Route::post('settings/integration', [SettingsController::class, 'saveIntegration'])->name('settings.integration');
        Route::post('settings/integration/test', [SettingsController::class, 'testIntegration'])->name('settings.integration.test');
        Route::post('settings/ai', [SettingsController::class, 'saveAiIntegration'])->name('settings.ai');
        Route::post('settings/ai/test', [SettingsController::class, 'testAiIntegration'])->name('settings.ai.test');
        Route::post('settings/google-drive', [SettingsController::class, 'saveGoogleDrive'])->name('settings.google-drive');
        Route::post('settings/google-drive/test', [SettingsController::class, 'testGoogleDrive'])->name('settings.google-drive.test');
        Route::post('settings/dropbox', [SettingsController::class, 'saveDropbox'])->name('settings.dropbox');
        Route::post('settings/dropbox/test', [SettingsController::class, 'testDropbox'])->name('settings.dropbox.test');
        Route::post('settings/onedrive-personal', [SettingsController::class, 'saveOneDrivePersonal'])->name('settings.onedrive-personal');
        Route::get('settings/onedrive-personal/authorize', [SettingsController::class, 'authorizeOneDrive'])->name('settings.onedrive-personal.authorize');
        Route::post('settings/onedrive-personal/test', [SettingsController::class, 'testOneDrivePersonal'])->name('settings.onedrive-personal.test');
        Route::post('settings/onedrive-personal/disconnect', [SettingsController::class, 'disconnectOneDrive'])->name('settings.onedrive-personal.disconnect');

        // Usuarios del tenant con roles
        Route::resource('users', UserController::class)
            ->except(['show']);
    });
});
