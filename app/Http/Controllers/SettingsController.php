<?php

namespace App\Http\Controllers;

use App\Models\TenantIntegration;
use App\Models\TenantSetting;
use App\Services\AiService;
use App\Services\DropboxService;
use App\Services\GoogleDriveService;
use App\Services\MicrosoftGraphService;
use App\Services\OneDrivePersonalService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Multitenancy\Models\Tenant;

class SettingsController extends Controller
{
    private const COLOR_KEYS = [
        'color_primary',
        'color_primary_dim',
        'color_secondary',
        'color_sidebar_bg',
        'color_text_primary',
    ];

    private const COLOR_DEFAULTS = [
        'color_primary'      => '#2a6496',
        'color_primary_dim'  => '#1a4a76',
        'color_secondary'    => '#4a7a5a',
        'color_sidebar_bg'   => '#0f172a',
        'color_text_primary' => '#1e293b',
    ];

    // Umbrales de riesgo configurables
    private const THRESHOLD_KEYS = [
        'risk_threshold_high'        => 50,   // score mínimo para nivel "alto"
        'risk_threshold_critical'    => 75,   // score mínimo para nivel "crítico"
        'alert_days_overdue_events'  => 15,   // días sin resolver para contar evento
        'alert_days_letter_response' => 5,    // días hábiles plazo respuesta default
        'notification_email_enabled' => '1',  // enviar emails de alerta
    ];

    public function index(): Response
    {
        $colors     = $this->loadColors();
        $thresholds = $this->loadThresholds();
        $integration    = TenantIntegration::forService('microsoft_graph');
        $aiIntegration  = TenantIntegration::forService('ai');
        $gdriveInt      = TenantIntegration::forService('google_drive');
        $dropboxInt     = TenantIntegration::forService('dropbox');

        return Inertia::render('Settings/Index', [
            'colors'      => $colors,
            'defaults'    => self::COLOR_DEFAULTS,
            'thresholds'  => $thresholds,
            'integration' => $integration ? [
                'client_id'       => $integration->client_id,
                'tenant_azure_id' => $integration->tenant_azure_id,
                'site_id'         => $integration->site_id,
                'is_active'       => $integration->is_active,
                'has_secret'      => !is_null($integration->client_secret_encrypted),
            ] : null,
            'sharepoint_configured' => app(MicrosoftGraphService::class)->isConfigured(),
            'ai_integration' => $aiIntegration ? [
                'provider'   => $aiIntegration->client_id,
                'model'      => $aiIntegration->site_id,
                'is_active'  => $aiIntegration->is_active,
                'has_key'    => !is_null($aiIntegration->client_secret_encrypted),
            ] : null,
            'ai_providers'   => AiService::PROVIDERS,
            'ai_configured'  => app(AiService::class)->isConfigured(),
            'gdrive_integration' => $gdriveInt ? [
                'service_account_email' => $gdriveInt->client_id,
                'folder_id'             => $gdriveInt->site_id,
                'is_active'             => $gdriveInt->is_active,
                'has_key'               => !is_null($gdriveInt->client_secret_encrypted),
            ] : null,
            'gdrive_configured'  => app(GoogleDriveService::class)->isConfigured(),
            'dropbox_integration' => $dropboxInt ? [
                'app_key'   => $dropboxInt->client_id,
                'base_path' => $dropboxInt->site_id,
                'is_active' => $dropboxInt->is_active,
                'has_token' => !is_null($dropboxInt->client_secret_encrypted),
            ] : null,
            'dropbox_configured' => app(DropboxService::class)->isConfigured(),
            'active_storage'     => app(\App\Services\DocumentStorageService::class)->activeProvider(),
            'flash' => session()->only(['success', 'error']),
        ]);
    }

    // ── Colores ───────────────────────────────────────────────────────────────

    public function saveColors(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'color_primary'      => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_primary_dim'  => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_secondary'    => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_sidebar_bg'   => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_text_primary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        foreach ($validated as $key => $value) {
            TenantSetting::set($key, $value);
        }

        $this->clearColorCache();

        return back()->with('success', 'Colores guardados correctamente.');
    }

    public function resetColors(): RedirectResponse
    {
        foreach (self::COLOR_DEFAULTS as $key => $value) {
            TenantSetting::where('key', $key)->delete();
        }

        $this->clearColorCache();

        return back()->with('success', 'Colores restaurados a los valores predeterminados.');
    }

    // ── Umbrales de riesgo ────────────────────────────────────────────────────

    public function saveThresholds(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'risk_threshold_high'        => ['required', 'integer', 'min:10', 'max:90'],
            'risk_threshold_critical'    => ['required', 'integer', 'min:20', 'max:99'],
            'alert_days_overdue_events'  => ['required', 'integer', 'min:1', 'max:90'],
            'alert_days_letter_response' => ['required', 'integer', 'min:1', 'max:60'],
            'notification_email_enabled' => ['boolean'],
        ]);

        // Validación cruzada: crítico debe ser mayor que alto
        if ($validated['risk_threshold_critical'] <= $validated['risk_threshold_high']) {
            return back()->withErrors(['risk_threshold_critical' => 'El umbral crítico debe ser mayor que el umbral alto.']);
        }

        foreach ($validated as $key => $value) {
            TenantSetting::set($key, (string) $value);
        }

        return back()->with('success', 'Umbrales de riesgo actualizados.');
    }

    // ── Integración Microsoft 365 ────────────────────────────────────────────

    public function saveIntegration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'       => ['required', 'string', 'max:100'],
            'tenant_azure_id' => ['required', 'string', 'max:100'],
            'client_secret'   => ['nullable', 'string', 'max:255'],
            'site_id'         => ['required', 'string', 'max:200'],
            'is_active'       => ['boolean'],
        ]);

        $integration = TenantIntegration::firstOrNew(['service' => 'microsoft_graph']);
        $integration->client_id       = $validated['client_id'];
        $integration->tenant_azure_id = $validated['tenant_azure_id'];
        $integration->site_id         = $validated['site_id'];
        $integration->is_active       = $validated['is_active'] ?? false;

        if (!empty($validated['client_secret'])) {
            $integration->client_secret = $validated['client_secret'];
        }

        $integration->save();

        // Limpia caché de token de acceso
        Cache::forget('microsoft_graph_access_token');

        return back()->with('success', 'Integración con Microsoft 365 guardada.');
    }

    public function testIntegration(): RedirectResponse
    {
        try {
            $token = app(MicrosoftGraphService::class)->getAccessToken();
            return back()->with('success', 'Conexión exitosa con Microsoft Graph.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error de conexión: ' . $e->getMessage());
        }
    }

    // ── Integración IA ───────────────────────────────────────────────────────

    public function saveAiIntegration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider'  => ['required', 'in:anthropic,openai,google,deepseek'],
            'model'     => ['required', 'string', 'max:80'],
            'api_key'   => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $integration = TenantIntegration::firstOrNew(['service' => 'ai']);
        $integration->client_id  = $validated['provider'];
        $integration->site_id    = $validated['model'];
        $integration->is_active  = $validated['is_active'] ?? false;

        if (!empty($validated['api_key'])) {
            $integration->client_secret = $validated['api_key'];
        }

        $integration->save();

        return back()->with('success', 'Configuración de IA guardada correctamente.');
    }

    public function testAiIntegration(): RedirectResponse
    {
        try {
            app(AiService::class)->testConnection();
            return back()->with('success', 'Conexión con el proveedor de IA exitosa.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error de conexión: ' . $e->getMessage());
        }
    }

    // ── Google Drive ─────────────────────────────────────────────────────────

    public function saveGoogleDrive(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_account_email' => ['required', 'email', 'max:200'],
            'private_key'           => ['nullable', 'string'],
            'folder_id'             => ['nullable', 'string', 'max:200'],
            'is_active'             => ['boolean'],
        ]);

        $integration = TenantIntegration::firstOrNew(['service' => 'google_drive']);
        $integration->client_id  = $validated['service_account_email'];
        $integration->site_id    = $validated['folder_id'] ?? null;
        $integration->is_active  = $validated['is_active'] ?? false;

        if (!empty($validated['private_key'])) {
            $integration->client_secret = $validated['private_key'];
        }

        $integration->save();
        Cache::forget('gdrive_token_' . $integration->id);

        return back()->with('success', 'Configuración de Google Drive guardada.');
    }

    public function testGoogleDrive(): RedirectResponse
    {
        try {
            app(GoogleDriveService::class)->testConnection();
            return back()->with('success', 'Conexión con Google Drive exitosa.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error de conexión: ' . $e->getMessage());
        }
    }

    // ── Dropbox ───────────────────────────────────────────────────────────────

    public function saveDropbox(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_key'      => ['nullable', 'string', 'max:100'],
            'access_token' => ['nullable', 'string', 'max:500'],
            'base_path'    => ['nullable', 'string', 'max:200'],
            'is_active'    => ['boolean'],
        ]);

        $integration = TenantIntegration::firstOrNew(['service' => 'dropbox']);
        $integration->client_id = $validated['app_key'] ?? null;
        $integration->site_id   = $validated['base_path'] ?? '/ClaimGuard';
        $integration->is_active = $validated['is_active'] ?? false;

        if (!empty($validated['access_token'])) {
            $integration->client_secret = $validated['access_token'];
        }

        $integration->save();

        return back()->with('success', 'Configuración de Dropbox guardada.');
    }

    public function testDropbox(): RedirectResponse
    {
        try {
            app(DropboxService::class)->testConnection();
            return back()->with('success', 'Conexión con Dropbox exitosa.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error de conexión: ' . $e->getMessage());
        }
    }

    // ── OneDrive Personal (OAuth delegado) ────────────────────────────────────

    public function saveOneDrivePersonal(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'     => ['required', 'string', 'max:100'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'is_active'     => ['boolean'],
        ]);

        $integration = TenantIntegration::firstOrNew(['service' => 'onedrive_personal']);
        $integration->client_id = $validated['client_id'];
        $integration->is_active = $validated['is_active'] ?? false;

        if (!empty($validated['client_secret'])) {
            // Guardamos el client_secret de la app en tenant_azure_id (encriptado manualmente)
            $integration->tenant_azure_id = Crypt::encryptString($validated['client_secret']);
        }

        $integration->save();

        return back()->with('success', 'Credenciales de OneDrive guardadas. Ahora autoriza el acceso.');
    }

    public function authorizeOneDrive(): \Symfony\Component\HttpFoundation\Response
    {
        $integration = TenantIntegration::forService('onedrive_personal');
        if (!$integration || !$integration->client_id) {
            return redirect()->route('settings.tenant')
                ->with('error', 'Guarda primero el Client ID antes de autorizar.');
        }

        $state       = bin2hex(random_bytes(16));
        $redirectUri = route('settings.onedrive-personal.callback');
        session(['onedrive_oauth_state' => $state]);

        $url = app(OneDrivePersonalService::class)
            ->getAuthorizationUrl($integration->client_id, $redirectUri, $state);

        return redirect($url);
    }

    public function oneDriveCallback(Request $request): RedirectResponse
    {
        if ($request->get('state') !== session('onedrive_oauth_state')) {
            return redirect()->route('settings.tenant')
                ->with('error', 'State inválido. Intenta autorizar nuevamente.');
        }

        if ($request->has('error')) {
            return redirect()->route('settings.tenant')
                ->with('error', 'Autorización rechazada: ' . $request->get('error_description'));
        }

        try {
            app(OneDrivePersonalService::class)
                ->exchangeCode($request->get('code'), route('settings.onedrive-personal.callback'));

            return redirect()->route('settings.tenant')
                ->with('success', 'OneDrive Personal conectado correctamente.');
        } catch (\Throwable $e) {
            return redirect()->route('settings.tenant')
                ->with('error', 'Error al conectar OneDrive: ' . $e->getMessage());
        }
    }

    public function testOneDrivePersonal(): RedirectResponse
    {
        try {
            app(OneDrivePersonalService::class)->testConnection();
            return back()->with('success', 'Conexión con OneDrive Personal exitosa.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function disconnectOneDrive(): RedirectResponse
    {
        TenantIntegration::where('service', 'onedrive_personal')->delete();
        Cache::forget('onedrive_personal_token_*');
        return back()->with('success', 'OneDrive Personal desconectado.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function loadColors(): array
    {
        $colors = [];
        foreach (self::COLOR_KEYS as $key) {
            $colors[$key] = TenantSetting::get($key, self::COLOR_DEFAULTS[$key]);
        }
        return $colors;
    }

    private function loadThresholds(): array
    {
        $thresholds = [];
        foreach (self::THRESHOLD_KEYS as $key => $default) {
            $val = TenantSetting::get($key, $default);
            $thresholds[$key] = is_numeric($val) ? (int) $val : $val;
        }
        return $thresholds;
    }

    private function clearColorCache(): void
    {
        $tenant = Tenant::current();
        if ($tenant) {
            Cache::forget("tenant_{$tenant->id}_colors");
        }
    }
}
