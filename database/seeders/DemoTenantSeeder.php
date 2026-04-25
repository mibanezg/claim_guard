<?php

namespace Database\Seeders;

use App\Models\ChangeOrder;
use App\Models\ClaimRiskScore;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractLetter;
use App\Models\ContractMilestone;
use App\Models\ContractPriceItem;
use App\Models\ContractualEvent;
use App\Models\DailyReport;
use App\Models\EventCostItem;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    private const PASSWORD = 'ClaimDemo2024!';

    public function run(): void
    {
        [$tenant, $users] = $this->seedLandlord();

        // Crea la DB del tenant si no existe y corre sus migraciones
        $dbName = $tenant->getDatabaseName();
        DB::connection('landlord')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        $tenant->makeCurrent();
        Artisan::call('migrate', [
            '--path'     => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--force'    => true,
        ]);
        Tenant::forgetCurrent();

        $tenant->execute(function () use ($users) {
            $this->call(RolesAndPermissionsSeeder::class);

            [$admin, $jefe, $terreno, $gerente] = $users;
            $admin->assignRole('tenant_admin');
            $jefe->assignRole('contract_admin');
            $terreno->assignRole('field_engineer');
            $gerente->assignRole('manager');

            [$mandante, $contratista1, $contratista2] = $this->seedCompanies();

            $this->seedContractChancado($admin, $jefe, $terreno, $mandante, $contratista1);
            $this->seedContractCintas($admin, $jefe, $terreno, $mandante, $contratista2);
            $this->seedContractObrasCiviles($admin, $jefe, $terreno, $mandante, $contratista1);
        });

        $this->command->info('');
        $this->command->info('  ✓ Demo "Ingeniería Austral SpA" creado');
        $this->command->info('  ─────────────────────────────────────────');
        $this->command->info('  admin@austral.cl          tenant_admin');
        $this->command->info('  valentina@austral.cl      contract_admin');
        $this->command->info('  cristobal@austral.cl      field_engineer');
        $this->command->info('  patricia@austral.cl       manager');
        $this->command->info('  Password: ' . self::PASSWORD);
        $this->command->info('  ─────────────────────────────────────────');
        $this->command->info('  Subdominio: austral.claimguard.cl');
        $this->command->info('');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Landlord: tenant + usuarios
    // ─────────────────────────────────────────────────────────────────────────

    private function seedLandlord(): array
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'austral'],
            [
                'name'      => 'Ingeniería Austral SpA',
                'slug'      => 'austral',
                'domain'    => 'austral',
                'database'  => 'claimguard_austral',
                'email'     => 'admin@austral.cl',
                'is_active' => true,
            ]
        );

        // Super admin global (si no existe aún)
        User::firstOrCreate(
            ['email' => 'superadmin@claimguard.cl'],
            [
                'tenant_id'      => null,
                'name'           => 'Super Admin',
                'password'       => Hash::make(self::PASSWORD),
                'is_super_admin' => true,
            ]
        );

        $admin = User::firstOrCreate(['email' => 'admin@austral.cl'], [
            'tenant_id' => $tenant->id,
            'name'      => 'Roberto Fuentes',
            'password'  => Hash::make(self::PASSWORD),
        ]);

        $jefe = User::firstOrCreate(['email' => 'valentina@austral.cl'], [
            'tenant_id' => $tenant->id,
            'name'      => 'Valentina Soto',
            'password'  => Hash::make(self::PASSWORD),
        ]);

        $terreno = User::firstOrCreate(['email' => 'cristobal@austral.cl'], [
            'tenant_id' => $tenant->id,
            'name'      => 'Cristóbal Mena',
            'password'  => Hash::make(self::PASSWORD),
        ]);

        $gerente = User::firstOrCreate(['email' => 'patricia@austral.cl'], [
            'tenant_id' => $tenant->id,
            'name'      => 'Patricia Araya',
            'password'  => Hash::make(self::PASSWORD),
        ]);

        return [$tenant, [$admin, $jefe, $terreno, $gerente]];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Empresas del tenant
    // ─────────────────────────────────────────────────────────────────────────

    private function seedCompanies(): array
    {
        $mandante = Company::firstOrCreate(['rut' => '76.100.200-K'], [
            'name'          => 'Minera Cordillera S.A.',
            'type'          => 'mandante',
            'address'       => 'Av. Apoquindo 3600, Las Condes, Santiago',
            'contact_name'  => 'Ing. Andrés Villablanca',
            'contact_email' => 'avillablanca@minera-cordillera.cl',
        ]);

        $contratista1 = Company::firstOrCreate(['rut' => '93.456.000-1'], [
            'name'          => 'Constructora SCI Ingeniería Ltda.',
            'type'          => 'contratista',
            'address'       => 'Los Conquistadores 1700, Providencia, Santiago',
            'contact_name'  => 'Felipe Bravo',
            'contact_email' => 'f.bravo@sci-ingenieria.cl',
        ]);

        $contratista2 = Company::firstOrCreate(['rut' => '81.234.500-8'], [
            'name'          => 'Servicios Industriales Norte S.A.',
            'type'          => 'contratista',
            'address'       => 'Arturo Prat 1020, Antofagasta',
            'contact_name'  => 'Lorena Tapia',
            'contact_email' => 'ltapia@si-norte.cl',
        ]);

        return [$mandante, $contratista1, $contratista2];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Contrato 1 — Planta de Chancado N°2  (vigente, riesgo ALTO)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedContractChancado(
        User $admin, User $jefe, User $terreno,
        Company $mandante, Company $contratista
    ): void {
        if (Contract::where('number', '2024-CHN-001')->exists()) return;

        $start = now()->subMonths(18)->toDateString();
        $end   = now()->addMonths(6)->toDateString();

        $contract = Contract::create([
            'name'                  => 'Construcción Planta de Chancado N°2',
            'number'                => '2024-CHN-001',
            'type'                  => 'obra',
            'status'                => 'vigente',
            'mandante_company_id'   => $mandante->id,
            'contractor_company_id' => $contratista->id,
            'original_amount'       => 1_250_000_000_000,   // CLP 12.500 M
            'current_amount'        => 1_295_000_000_000,   // + OC aprobada
            'currency'              => 'CLP',
            'contractual_start_date' => $start,
            'contractual_end_date'   => $end,
            'actual_start_date'      => $start,
            'projected_end_date'     => now()->addMonths(9)->toDateString(),
            'notification_days'      => 10,
            'description'            => 'Construcción de nueva línea de chancado primario y secundario en Sector Norte. Capacidad nominal 5.000 t/h.',
            'created_by'             => $admin->id,
        ]);

        // Hitos
        $this->createMilestone($contract, 'Ingeniería de Detalle', now()->subMonths(14), now()->subMonths(13), 100, 'completado');
        $this->createMilestone($contract, 'Movimiento de Tierras y Excavaciones', now()->subMonths(10), now()->subMonths(9), 100, 'completado');
        $this->createMilestone($contract, 'Obras Civiles Fundaciones', now()->subMonths(4), null, 60, 'atrasado', true);
        $this->createMilestone($contract, 'Montaje Estructura Metálica', now()->subMonths(1), null, 25, 'en_progreso', true);
        $this->createMilestone($contract, 'Montaje Equipos y Chancadores', now()->addMonths(3), null, 0, 'pendiente');
        $this->createMilestone($contract, 'Pruebas y Puesta en Marcha', now()->addMonths(5), null, 0, 'pendiente', true);

        // Eventos — 3 sin resolver > 15 días
        $e1 = ContractualEvent::create([
            'contract_id'       => $contract->id,
            'type'              => 'atraso_mandante',
            'occurred_at'       => now()->subDays(45),
            'description'       => 'Mandante no entregó frente de trabajo Sector A conforme al programa contractual. La demora acumula 45 días calendario.',
            'responsible_party' => 'mandante',
            'schedule_impact_days' => 45,
            'cost_impact'       => 28_500_000_00,  // $285M
            'resolution_status' => 'escalado',
            'created_by'        => $terreno->id,
        ]);

        $e2 = ContractualEvent::create([
            'contract_id'       => $contract->id,
            'type'              => 'condicion_imprevista',
            'occurred_at'       => now()->subDays(30),
            'description'       => 'Detección de napas subterráneas no previstas en estudio geotécnico de base. Requiere sistema de bombeo adicional.',
            'responsible_party' => 'fuerza_mayor',
            'schedule_impact_days' => 30,
            'cost_impact'       => 45_000_000_00,  // $450M
            'resolution_status' => 'negociacion',
            'created_by'        => $terreno->id,
        ]);

        ContractualEvent::create([
            'contract_id'       => $contract->id,
            'type'              => 'atraso_contratista',
            'occurred_at'       => now()->subDays(20),
            'description'       => 'Grúa Liebherr 600t sufrió falla en sistema hidráulico. Equipos de reemplazo no disponibles en plaza.',
            'responsible_party' => 'contratista',
            'schedule_impact_days' => 15,
            'cost_impact'       => 0,
            'resolution_status' => 'pendiente',
            'created_by'        => $terreno->id,
        ]);

        // Evento resuelto (background)
        ContractualEvent::create([
            'contract_id'       => $contract->id,
            'type'              => 'no_conformidad',
            'occurred_at'       => now()->subMonths(3),
            'description'       => 'No conformidad en soldaduras de planchas basales. Corregido mediante rework.',
            'responsible_party' => 'contratista',
            'schedule_impact_days' => 5,
            'cost_impact'       => 0,
            'resolution_status' => 'resuelto',
            'resolution_notes'  => 'Rework completado y aprobado por ITO. NCR cerrada.',
            'created_by'        => $jefe->id,
        ]);

        // Cartas — 2 vencidas
        ContractLetter::create([
            'contract_id'             => $contract->id,
            'contractual_event_id'    => $e1->id,
            'letter_number'           => 'CTR-2024-CHN-001-C-0001',
            'type'                    => 'notificacion',
            'subject'                 => 'Notificación de atraso en entrega de frente de trabajo Sector A',
            'from_company_id'         => $contratista->id,
            'to_company_id'           => $mandante->id,
            'issued_at'               => now()->subDays(40)->toDateString(),
            'response_deadline'       => now()->subDays(20)->toDateString(),
            'response_days'           => 10,
            'status'                  => 'vencida',
            'created_by'              => $jefe->id,
        ]);

        ContractLetter::create([
            'contract_id'             => $contract->id,
            'contractual_event_id'    => $e2->id,
            'letter_number'           => 'CTR-2024-CHN-001-C-0002',
            'type'                    => 'reserva_derechos',
            'subject'                 => 'Reserva de derechos por condiciones geotécnicas imprevistas — Sector Fundaciones',
            'from_company_id'         => $contratista->id,
            'to_company_id'           => $mandante->id,
            'issued_at'               => now()->subDays(28)->toDateString(),
            'response_deadline'       => now()->subDays(8)->toDateString(),
            'response_days'           => 10,
            'status'                  => 'vencida',
            'created_by'              => $jefe->id,
        ]);

        ContractLetter::create([
            'contract_id'       => $contract->id,
            'letter_number'     => 'CTR-2024-CHN-001-C-0003',
            'type'              => 'notificacion',
            'subject'           => 'Solicitud de extensión de plazo por eventos imputables al Mandante',
            'from_company_id'   => $contratista->id,
            'to_company_id'     => $mandante->id,
            'status'            => 'borrador',
            'response_days'     => 15,
            'created_by'        => $jefe->id,
        ]);

        // Órdenes de cambio
        ChangeOrder::create([
            'contract_id'           => $contract->id,
            'contractual_event_id'  => $e2->id,
            'request_number'        => '2024-CHN-OC-001',
            'requested_by_party'    => 'contratista',
            'description'           => 'Trabajos adicionales por condiciones geotécnicas imprevistas: sistema de bombeo, refuerzo de fundaciones y entibaciones.',
            'schedule_impact_days'  => 30,
            'cost_impact'           => 45_000_000_00,
            'status'                => 'evaluacion',
            'created_by'            => $jefe->id,
        ]);

        ChangeOrder::create([
            'contract_id'          => $contract->id,
            'contractual_event_id' => $e1->id,
            'request_number'       => '2024-CHN-OC-002',
            'requested_by_party'   => 'contratista',
            'description'          => 'Extensión de plazo contractual por atraso en entrega de frente Sector A, imputable al Mandante.',
            'schedule_impact_days' => 45,
            'cost_impact'          => 0,
            'status'               => 'solicitada',
            'created_by'           => $jefe->id,
        ]);

        ChangeOrder::create([
            'contract_id'          => $contract->id,
            'request_number'       => '2024-CHN-OC-003',
            'requested_by_party'   => 'contratista',
            'description'          => 'Trabajos adicionales de impermeabilización en pisos de sala de control.',
            'schedule_impact_days' => 0,
            'cost_impact'          => 8_500_000_00,
            'status'               => 'rechazada',
            'created_by'           => $jefe->id,
        ]);

        // OC aprobada (la que ya está en current_amount)
        ChangeOrder::create([
            'contract_id'          => $contract->id,
            'request_number'       => '2024-CHN-OC-000',
            'requested_by_party'   => 'mandante',
            'description'          => 'Ampliación de alcance: incorporación de sistema CCTV y control de acceso en planta.',
            'schedule_impact_days' => 0,
            'cost_impact'          => 45_000_000_00,
            'status'               => 'aprobada',
            'approved_by'          => $admin->id,
            'approved_at'          => now()->subMonths(2),
            'created_by'           => $jefe->id,
        ]);

        // Risk score ALTO
        ClaimRiskScore::create([
            'contract_id'   => $contract->id,
            'score_level'   => 'alto',
            'score_value'   => 58,
            'calculated_at' => now()->subHours(2),
            'factors'       => [
                'eventos_sin_resolver'  => ['label' => 'Eventos sin resolver > 15 días',     'count' => 3,   'points' => 15, 'max' => 20],
                'cartas_vencidas'       => ['label' => 'Cartas vencidas sin respuesta',       'count' => 2,   'points' => 15, 'max' => 20],
                'desvio_programa'       => ['label' => 'Desviación del programa',             'atrasados' => 2, 'dias_desvio' => 90, 'points' => 15, 'max' => 15],
                'oc_rechazadas'         => ['label' => 'OC rechazadas sin contraoferta',      'count' => 1,   'points' => 8,  'max' => 15],
                'monto_disputa'         => ['label' => 'Monto en disputa vs monto vigente',   'porcentaje' => 3.5, 'points' => 0, 'max' => 15],
                'concentracion_eventos' => ['label' => 'Concentración de responsabilidad',   'pct_max' => 50, 'points' => 0, 'max' => 15],
            ],
        ]);

        $cpu = $this->seedCPUChancado($contract);
        $this->seedCostItemsChancado($e1, $e2, $cpu);
        $this->seedDailyReportsChancado($contract, $terreno, $e1, $e2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Contrato 2 — Cintas Transportadoras  (vigente, riesgo BAJO)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedContractCintas(
        User $admin, User $jefe, User $terreno,
        Company $mandante, Company $contratista
    ): void {
        if (Contract::where('number', '2024-SCT-002')->exists()) return;

        $start = now()->subMonths(8)->toDateString();

        $contract = Contract::create([
            'name'                  => 'Suministro e Instalación Cintas Transportadoras',
            'number'                => '2024-SCT-002',
            'type'                  => 'suministro',
            'status'                => 'vigente',
            'mandante_company_id'   => $mandante->id,
            'contractor_company_id' => $contratista->id,
            'original_amount'       => 320_000_000_00,  // CLP 3.200 M
            'current_amount'        => 336_000_000_00,  // + OC aprobada
            'currency'              => 'CLP',
            'contractual_start_date' => $start,
            'contractual_end_date'   => now()->addMonths(4)->toDateString(),
            'actual_start_date'      => $start,
            'notification_days'      => 10,
            'description'            => 'Suministro, transporte, montaje y puesta en marcha de 3 cintas transportadoras de 1.200 m de longitud cada una.',
            'created_by'             => $admin->id,
        ]);

        $this->createMilestone($contract, 'Ingeniería y Fabricación',     now()->subMonths(5), now()->subMonths(4)->subDays(5), 100, 'completado');
        $this->createMilestone($contract, 'Despacho e Importación',       now()->subMonths(3), now()->subMonths(2)->subDays(10), 100, 'completado');
        $this->createMilestone($contract, 'Montaje Cinta CT-01',          now()->subMonths(1), null, 85, 'en_progreso', true);
        $this->createMilestone($contract, 'Montaje Cintas CT-02 y CT-03', now()->addMonths(2), null, 0, 'pendiente');
        $this->createMilestone($contract, 'Puesta en Marcha y FAT',       now()->addMonths(3)->addWeeks(2), null, 0, 'pendiente', true);

        $e1 = ContractualEvent::create([
            'contract_id'       => $contract->id,
            'type'              => 'otro',
            'occurred_at'       => now()->subMonths(2),
            'description'       => 'Demora en aduana por observación SAG a embalajes de madera. Liberado tras tratamiento fitosanitario.',
            'responsible_party' => 'tercero',
            'schedule_impact_days' => 10,
            'cost_impact'       => 1_600_000_00,
            'resolution_status' => 'resuelto',
            'resolution_notes'  => 'Tratamiento completado. Equipos en faena.',
            'created_by'        => $terreno->id,
        ]);

        ContractLetter::create([
            'contract_id'       => $contract->id,
            'contractual_event_id' => $e1->id,
            'letter_number'     => 'CTR-2024-SCT-002-C-0001',
            'type'              => 'notificacion',
            'subject'           => 'Notificación de demora en despacho — causa fuerza mayor (aduana SAG)',
            'from_company_id'   => $contratista->id,
            'to_company_id'     => $mandante->id,
            'issued_at'         => now()->subMonths(2)->toDateString(),
            'response_deadline' => now()->subMonths(2)->addDays(10)->toDateString(),
            'response_days'     => 10,
            'status'            => 'respondida',
            'created_by'        => $jefe->id,
        ]);

        ChangeOrder::create([
            'contract_id'          => $contract->id,
            'contractual_event_id' => $e1->id,
            'request_number'       => '2024-SCT-OC-001',
            'requested_by_party'   => 'contratista',
            'description'          => 'Reconocimiento de costos adicionales por tratamiento fitosanitario SAG y almacenaje en aduana.',
            'schedule_impact_days' => 10,
            'cost_impact'          => 16_000_000_00,
            'status'               => 'aprobada',
            'approved_by'          => $admin->id,
            'approved_at'          => now()->subMonths(1)->subWeeks(2),
            'created_by'           => $jefe->id,
        ]);

        ClaimRiskScore::create([
            'contract_id'   => $contract->id,
            'score_level'   => 'bajo',
            'score_value'   => 10,
            'calculated_at' => now()->subHour(),
            'factors'       => [
                'eventos_sin_resolver'  => ['label' => 'Eventos sin resolver > 15 días',   'count' => 0,   'points' => 0,  'max' => 20],
                'cartas_vencidas'       => ['label' => 'Cartas vencidas sin respuesta',     'count' => 0,   'points' => 0,  'max' => 20],
                'desvio_programa'       => ['label' => 'Desviación del programa',           'atrasados' => 0, 'dias_desvio' => 0, 'points' => 0, 'max' => 15],
                'oc_rechazadas'         => ['label' => 'OC rechazadas sin contraoferta',    'count' => 0,   'points' => 0,  'max' => 15],
                'monto_disputa'         => ['label' => 'Monto en disputa vs monto vigente', 'porcentaje' => 4.8, 'points' => 0, 'max' => 15],
                'concentracion_eventos' => ['label' => 'Concentración de responsabilidad', 'pct_max' => 0, 'points' => 0,  'max' => 15],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Contrato 3 — Obras Civiles Sector B  (en_disputa, riesgo CRÍTICO)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedContractObrasCiviles(
        User $admin, User $jefe, User $terreno,
        Company $mandante, Company $contratista
    ): void {
        if (Contract::where('number', '2023-OCI-001')->exists()) return;

        $start = now()->subMonths(24)->toDateString();

        $contract = Contract::create([
            'name'                  => 'Obras Civiles Infraestructura Sector B',
            'number'                => '2023-OCI-001',
            'type'                  => 'obra',
            'status'                => 'en_disputa',
            'mandante_company_id'   => $mandante->id,
            'contractor_company_id' => $contratista->id,
            'original_amount'       => 870_000_000_00,  // CLP 8.700 M
            'current_amount'        => 870_000_000_00,
            'currency'              => 'CLP',
            'contractual_start_date' => $start,
            'contractual_end_date'   => now()->subMonths(6)->toDateString(),
            'actual_start_date'      => $start,
            'projected_end_date'     => now()->addMonths(2)->toDateString(),
            'notification_days'      => 10,
            'description'            => 'Obras civiles de infraestructura en Sector B: caminos de acceso, instalaciones de faena, sistemas de agua y alcantarillado.',
            'created_by'             => $admin->id,
        ]);

        $this->createMilestone($contract, 'Caminos de Acceso',            now()->subMonths(20), now()->subMonths(18), 100, 'completado');
        $this->createMilestone($contract, 'Instalaciones de Faena',       now()->subMonths(16), now()->subMonths(14), 100, 'completado');
        $this->createMilestone($contract, 'Sistema de Agua Potable',      now()->subMonths(10), null, 70, 'atrasado', true);
        $this->createMilestone($contract, 'Sistema Alcantarillado',       now()->subMonths(8),  null, 40, 'atrasado', true);
        $this->createMilestone($contract, 'Pavimentación Interna',        now()->subMonths(6),  null, 10, 'atrasado', true);
        $this->createMilestone($contract, 'Recepciones y Permisos Finales', now()->subMonths(4), null, 0, 'atrasado', true);

        $events = [];

        $events[] = ContractualEvent::create([
            'contract_id'       => $contract->id, 'type' => 'disputa',
            'occurred_at'       => now()->subMonths(7),
            'description'       => 'Mandante desconoce OC-003 por supuesta falta de respaldo documental. Contratista mantiene que los trabajos fueron instruidos verbalmente y ejecutados conforme.',
            'responsible_party' => 'mandante',
            'schedule_impact_days' => 60, 'cost_impact' => 95_000_000_00,
            'resolution_status' => 'escalado', 'created_by' => $jefe->id,
        ]);

        $events[] = ContractualEvent::create([
            'contract_id'       => $contract->id, 'type' => 'disputa',
            'occurred_at'       => now()->subMonths(6),
            'description'       => 'Mandante desconoce extensión de plazo por lluvias excepcionales. Contratista presenta registros meteorológicos e informes de terreno.',
            'responsible_party' => 'mandante',
            'schedule_impact_days' => 45, 'cost_impact' => 42_000_000_00,
            'resolution_status' => 'escalado', 'created_by' => $jefe->id,
        ]);

        $events[] = ContractualEvent::create([
            'contract_id'       => $contract->id, 'type' => 'atraso_mandante',
            'occurred_at'       => now()->subMonths(5),
            'description'       => 'Atraso en entrega de planos definitivos de instalaciones sanitarias. Mandante reconoce demora de 30 días.',
            'responsible_party' => 'mandante',
            'schedule_impact_days' => 30, 'cost_impact' => 15_000_000_00,
            'resolution_status' => 'negociacion', 'created_by' => $terreno->id,
        ]);

        ContractualEvent::create([
            'contract_id'       => $contract->id, 'type' => 'disputa',
            'occurred_at'       => now()->subMonths(4),
            'description'       => 'Mandante aplica multas por atraso en contrato. Contratista impugna monto y alega compensación por eventos imputables al Mandante.',
            'responsible_party' => 'mandante',
            'schedule_impact_days' => 0, 'cost_impact' => 130_000_000_00,
            'resolution_status' => 'escalado', 'created_by' => $jefe->id,
        ]);

        ContractualEvent::create([
            'contract_id'       => $contract->id, 'type' => 'suspension',
            'occurred_at'       => now()->subMonths(2),
            'description'       => 'Paralización de obras por resolución judicial cautelar ante demanda arbitral presentada por contratista.',
            'responsible_party' => 'tercero',
            'schedule_impact_days' => 60, 'cost_impact' => 0,
            'resolution_status' => 'pendiente', 'created_by' => $admin->id,
        ]);

        ContractualEvent::create([
            'contract_id'       => $contract->id, 'type' => 'disputa',
            'occurred_at'       => now()->subMonths(1),
            'description'       => 'Contratista solicita arbitraje formal ante Cámara de Comercio de Santiago por monto total de $267M + IPC.',
            'responsible_party' => 'mandante',
            'schedule_impact_days' => 0, 'cost_impact' => 26_700_000_00,
            'resolution_status' => 'escalado', 'created_by' => $admin->id,
        ]);

        // 4 cartas vencidas
        foreach ([
            ['CTR-2023-OCI-001-C-0008', 'Notificación formal de término anticipado imputable al Mandante', now()->subMonths(6), now()->subMonths(5)->subWeeks(2)],
            ['CTR-2023-OCI-001-C-0009', 'Presentación de méritos — extensión de plazo por lluvias excepcionales', now()->subMonths(5)->subWeeks(1), now()->subMonths(4)->subWeeks(3)],
            ['CTR-2023-OCI-001-C-0010', 'Impugnación de multas aplicadas — cláusula 14.3 del contrato', now()->subMonths(3)->subWeeks(2), now()->subMonths(3)],
            ['CTR-2023-OCI-001-C-0011', 'Notificación de inicio de arbitraje — Cámara de Comercio de Santiago', now()->subMonths(1)->subWeeks(2), now()->subMonths(1)],
        ] as [$num, $subject, $issued, $deadline]) {
            ContractLetter::create([
                'contract_id'       => $contract->id,
                'letter_number'     => $num,
                'type'              => 'reserva_derechos',
                'subject'           => $subject,
                'from_company_id'   => $contratista->id,
                'to_company_id'     => $mandante->id,
                'issued_at'         => $issued->toDateString(),
                'response_deadline' => $deadline->toDateString(),
                'response_days'     => 15,
                'status'            => 'vencida',
                'created_by'        => $jefe->id,
            ]);
        }

        // OCs rechazadas
        ChangeOrder::create([
            'contract_id'          => $contract->id,
            'request_number'       => '2023-OCI-OC-003',
            'requested_by_party'   => 'contratista',
            'description'          => 'Trabajos adicionales instruidos verbalmente por ITO: modificación de trazados sanitarios, 3.200 m lineales.',
            'schedule_impact_days' => 60,
            'cost_impact'          => 95_000_000_00,
            'status'               => 'rechazada',
            'created_by'           => $jefe->id,
        ]);

        ChangeOrder::create([
            'contract_id'          => $contract->id,
            'request_number'       => '2023-OCI-OC-004',
            'requested_by_party'   => 'contratista',
            'description'          => 'Reconocimiento de mayores costos por lluvias excepcionales periodo julio-agosto 2024.',
            'schedule_impact_days' => 45,
            'cost_impact'          => 42_000_000_00,
            'status'               => 'rechazada',
            'created_by'           => $jefe->id,
        ]);

        ClaimRiskScore::create([
            'contract_id'   => $contract->id,
            'score_level'   => 'critico',
            'score_value'   => 100,
            'calculated_at' => now()->subMinutes(30),
            'factors'       => [
                'eventos_sin_resolver'  => ['label' => 'Eventos sin resolver > 15 días',   'count' => 6,    'points' => 20, 'max' => 20],
                'cartas_vencidas'       => ['label' => 'Cartas vencidas sin respuesta',     'count' => 4,    'points' => 20, 'max' => 20],
                'desvio_programa'       => ['label' => 'Desviación del programa',           'atrasados' => 4, 'dias_desvio' => 240, 'points' => 15, 'max' => 15],
                'oc_rechazadas'         => ['label' => 'OC rechazadas sin contraoferta',    'count' => 2,    'points' => 15, 'max' => 15],
                'monto_disputa'         => ['label' => 'Monto en disputa vs monto vigente', 'porcentaje' => 41.6, 'points' => 15, 'max' => 15],
                'concentracion_eventos' => ['label' => 'Concentración de responsabilidad', 'pct_max' => 83.3, 'points' => 15, 'max' => 15],
            ],
        ]);

        $cpu = $this->seedCPUObrasCiviles($contract);
        $this->seedCostItemsObrasCiviles($events[0], $events[1], $events[2], $cpu);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CPU — Chancado
    // ─────────────────────────────────────────────────────────────────────────

    private function seedCPUChancado(Contract $contract): array
    {
        $items = [
            ['MO-001', 'Capataz de Montaje',                   'hr',  1_700_000, 'mano_obra'],
            ['MO-002', 'Operador Grúa Torre Liebherr',          'hr',  2_800_000, 'mano_obra'],
            ['MO-003', 'Soldador Certificado 3G/4G',            'hr',  2_500_000, 'mano_obra'],
            ['MO-004', 'Rigger / Aparejador',                   'hr',  2_100_000, 'mano_obra'],
            ['MO-005', 'Ayudante General',                      'hr',  1_200_000, 'mano_obra'],
            ['MAT-001', 'Hormigón H-30 premezclado',            'm3', 10_000_000, 'materiales'],
            ['MAT-002', 'Acero A63-42H en barras',              'kg',     90_000, 'materiales'],
            ['MAT-003', 'Planchas estructurales e=12mm',        'kg',    110_000, 'materiales'],
            ['MAT-004', 'Perno de anclaje c/tuerca y golilla',  'un',    850_000, 'materiales'],
            ['EQ-001',  'Grúa Liebherr 600t (arriendo+oper.)', 'hr', 28_500_000, 'equipos'],
            ['EQ-002',  'Camión Mixer 8m³',                     'hr',  6_500_000, 'equipos'],
            ['EQ-003',  'Excavadora CAT 320',                   'hr',  9_500_000, 'equipos'],
            ['SC-001',  'Montaje estructura metálica (SC)',      'kg',     85_000, 'subcontratos'],
            ['GG-001',  'Gastos generales de obra (mensual)',   'mes', 45_000_000_00, 'gastos_generales'],
        ];

        return $this->createPriceItems($contract, $items);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CPU — Obras Civiles
    // ─────────────────────────────────────────────────────────────────────────

    private function seedCPUObrasCiviles(Contract $contract): array
    {
        $items = [
            ['MO-001', 'Capataz de Obras Civiles',              'hr',  2_000_000, 'mano_obra'],
            ['MO-002', 'Operario Especializado',                 'hr',  1_500_000, 'mano_obra'],
            ['MO-003', 'Ayudante General',                       'hr',  1_100_000, 'mano_obra'],
            ['MAT-001', 'Hormigón H-25 en obras sanitarias',    'm3',  9_500_000, 'materiales'],
            ['MAT-002', 'Tubería HDPE DN315 PN10',               'm',  4_200_000, 'materiales'],
            ['MAT-003', 'Tubería PVC alcantarillado DN250',      'm',  2_800_000, 'materiales'],
            ['MAT-004', 'Árido seleccionado para relleno',      'm3',    950_000, 'materiales'],
            ['EQ-001',  'Retroexcavadora JD 310',               'hr',  7_500_000, 'equipos'],
            ['EQ-002',  'Rodillo compactador 10t',              'hr',  5_500_000, 'equipos'],
            ['EQ-003',  'Camión aljibe 10.000 lt',              'hr',  4_800_000, 'equipos'],
            ['SC-001',  'Pavimento asfalto e=5cm (SC)',         'm2',  2_500_000, 'subcontratos'],
            ['GG-001',  'Gastos generales de obra (mensual)',   'mes', 28_000_000_00, 'gastos_generales'],
        ];

        return $this->createPriceItems($contract, $items);
    }

    private function createPriceItems(Contract $contract, array $items): array
    {
        $created = [];
        foreach ($items as [$code, $description, $unit, $unitCost, $category]) {
            $created[$code] = ContractPriceItem::create([
                'contract_id' => $contract->id,
                'code'        => $code,
                'description' => $description,
                'unit'        => $unit,
                'unit_cost'   => $unitCost,
                'category'    => $category,
                'is_active'   => true,
            ]);
        }
        return $created;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ítems de costo — Chancado
    // ─────────────────────────────────────────────────────────────────────────

    private function seedCostItemsChancado(
        ContractualEvent $e1,
        ContractualEvent $e2,
        array $cpu
    ): void {
        // E1 — Atraso mandante: MO improductiva + gastos generales + grúa en standby
        $itemsE1 = [
            [$cpu['MO-001']->id, 'Capataz improductivo — espera entrega frente Sector A',    'hr',   480.0,  1_700_000, 'mano_obra_directa'],
            [$cpu['MO-002']->id, 'Operador grúa improductivo durante paralización',          'hr',   360.0,  2_800_000, 'mano_obra_directa'],
            [$cpu['MO-005']->id, 'Ayudantes improductivos en espera de frente',              'hr',  1_440.0, 1_200_000, 'mano_obra_directa'],
            [$cpu['EQ-001']->id, 'Grúa Liebherr 600t en standby por falta de frente',       'hr',   120.0, 28_500_000, 'equipos'],
            [null,               'Gastos generales — 45 días de paralización Sector A',      'gl',     1.0, 38_250_000_00, 'gastos_obra'],
        ];

        foreach ($itemsE1 as [$priceItemId, $description, $unit, $qty, $unitCost, $category]) {
            $amount = (int) round($qty * $unitCost);
            EventCostItem::create([
                'contractual_event_id'   => $e1->id,
                'contract_price_item_id' => $priceItemId,
                'description'            => $description,
                'unit'                   => $unit,
                'quantity'               => $qty,
                'unit_cost'              => $unitCost,
                'amount'                 => $amount,
                'cost_category'          => $category,
            ]);
        }

        // E2 — Condición imprevista: bombeo + refuerzo fundaciones + entibaciones + MO adicional
        $itemsE2 = [
            [null,               'Sistema bombeo aguas subterráneas — arriendo y operación 2 meses', 'mes',    2.0, 12_000_000_00, 'equipos'],
            [$cpu['MAT-001']->id,'Hormigón H-30 adicional en refuerzo de fundaciones',               'm3',   280.0, 10_000_000,    'materiales'],
            [null,               'Entibaciones metálicas — arriendo e instalación',                  'm2',   420.0,  2_500_000,    'subcontratos'],
            [$cpu['MO-003']->id, 'Soldadores para anclajes y refuerzos adicionales',                 'hr',   960.0,  2_500_000,    'mano_obra_directa'],
            [$cpu['MO-005']->id, 'Ayudantes en trabajos de refuerzo geotécnico',                     'hr',  1_200.0, 1_200_000,   'mano_obra_directa'],
            [null,               'Overhead sede por gestión de contingencia geotécnica',             'gl',     1.0,  8_500_000_00, 'overhead_sede'],
        ];

        foreach ($itemsE2 as [$priceItemId, $description, $unit, $qty, $unitCost, $category]) {
            $amount = (int) round($qty * $unitCost);
            EventCostItem::create([
                'contractual_event_id'   => $e2->id,
                'contract_price_item_id' => $priceItemId,
                'description'            => $description,
                'unit'                   => $unit,
                'quantity'               => $qty,
                'unit_cost'              => $unitCost,
                'amount'                 => $amount,
                'cost_category'          => $category,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ítems de costo — Obras Civiles (disputa)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedCostItemsObrasCiviles(
        ContractualEvent $eDisputa,
        ContractualEvent $eLluvias,
        ContractualEvent $eAtrasoPlanos,
        array $cpu
    ): void {
        // Disputa: modificación de trazados sanitarios instruidos verbalmente
        $itemsD = [
            [$cpu['EQ-001']->id, 'Retroexcavadora — excavación trazados modificados',            'hr',   380.0,  7_500_000, 'equipos'],
            [$cpu['MAT-002']->id,'Tubería HDPE DN315 — tramos adicionales por modificación',     'm',    850.0,  4_200_000, 'materiales'],
            [$cpu['MAT-004']->id,'Árido seleccionado — relleno de zanjas modificadas',           'm3',   620.0,    950_000, 'materiales'],
            [$cpu['MO-002']->id, 'MO operarios — instalación tuberías trazados nuevos',         'hr',  1_600.0, 1_500_000, 'mano_obra_directa'],
            [null,               'Gastos generales por modificación de diseño',                   'gl',    1.0,  9_500_000_00, 'gastos_obra'],
        ];

        foreach ($itemsD as [$priceItemId, $description, $unit, $qty, $unitCost, $category]) {
            EventCostItem::create([
                'contractual_event_id'   => $eDisputa->id,
                'contract_price_item_id' => $priceItemId,
                'description'            => $description,
                'unit'                   => $unit,
                'quantity'               => $qty,
                'unit_cost'              => $unitCost,
                'amount'                 => (int) round($qty * $unitCost),
                'cost_category'          => $category,
            ]);
        }

        // Lluvias excepcionales: paralización + reparación
        $itemsL = [
            [$cpu['MO-002']->id, 'Operarios improductivos — paralización por lluvias 22 días', 'día',   22.0,  8_500_000_00, 'mano_obra_directa'],
            [$cpu['MAT-004']->id,'Árido — reposición rellenos deteriorados por lluvia',        'm3',   750.0,    950_000, 'materiales'],
            [$cpu['EQ-002']->id, 'Rodillo compactador — recompactación post lluvias',          'hr',   180.0,  5_500_000, 'equipos'],
            [null,               'Gastos generales durante paralización climática',             'gl',     1.0,  6_500_000_00, 'gastos_obra'],
        ];

        foreach ($itemsL as [$priceItemId, $description, $unit, $qty, $unitCost, $category]) {
            EventCostItem::create([
                'contractual_event_id'   => $eLluvias->id,
                'contract_price_item_id' => $priceItemId,
                'description'            => $description,
                'unit'                   => $unit,
                'quantity'               => $qty,
                'unit_cost'              => $unitCost,
                'amount'                 => (int) round($qty * $unitCost),
                'cost_category'          => $category,
            ]);
        }

        // Atraso en planos: MO improductiva esperando diseño definitivo
        EventCostItem::create([
            'contractual_event_id'   => $eAtrasoPlanos->id,
            'contract_price_item_id' => $cpu['MO-002']->id,
            'description'            => 'MO improductiva — espera de planos definitivos sanitarios (30 días)',
            'unit'                   => 'hr',
            'quantity'               => 720.0,
            'unit_cost'              => 1_500_000,
            'amount'                 => 1_080_000_000,
            'cost_category'          => 'mano_obra_directa',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Diarios de obra — Chancado (10 registros)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedDailyReportsChancado(
        Contract $contract,
        User $terreno,
        ContractualEvent $e1,
        ContractualEvent $e2
    ): void {
        $reports = [
            [1,  'bueno',        24, 'Montaje estructura metálica nivel +12.00. Instalación de 48 columnas HEA300. Avance acumulado 72%. Sin novedades de seguridad.',
             null, null],
            [2,  'nublado',      19, 'Continuación soldadura de uniones en nivel +8.00. Se completaron 32 juntas de filete. Inspección de ITO sin observaciones.',
             null, null],
            [3,  'bueno',        22, 'Instalación de vigas secundarias en sectores A y B. Hormigonado de dados de fundación eje 4-4. Temperatura hormigón: 18°C.',
             null, null],
            [4,  'lluvia',       14, 'Paralización de actividades en altura por lluvia y viento. Personal en faenas cubiertas: limpieza y habilitación de áreas. Se registra inicio de paralización imputable a condiciones climáticas.',
             null, 'Lluvia intensa desde las 09:30. Se suspenden trabajos en altura por protocolo de seguridad. ITO notificado vía correo a las 10:15.'],
            [5,  'nublado',      17, 'Reinicio de actividades. Inspección de estructura post lluvia. Sin daños. Retiro de agua acumulada en excavaciones con equipo de bombeo.',
             null, null],
            [6,  'bueno',        21, 'Montaje grúa torre sector Chancador Primario. Izaje de 6 vigas principales HEB500. Peso máximo izado: 18,4 t.',
             null, null],
            [7,  'bueno',        23, 'Detección de napa subterránea en excavación Sector Fundaciones F-12. Se informa inmediatamente a ITO y se activa protocolo de contingencia geotécnica.',
             'Ing. Andrés Villablanca (ITO Mandante) — Instrucción verbal de suspender excavaciones Sector F-12 hasta nuevo aviso y presentar informe geotécnico en 48 hrs.',
             'Napa a 2,8m de profundidad, no prevista en estudio base. Caudal estimado 3 lt/min.'],
            [8,  'bueno',        20, 'Paralización excavaciones Sector F-12 conforme instrucción ITO. Continuación trabajos en sectores F-1 a F-11 sin novedad. Inicio de bombeo preliminar.',
             null, null],
            [9,  'nublado',      18, 'Llegada empresa especialista geotecnia para inspección napa. Toma de muestras de suelo y agua. Personal en espera de directrices.',
             'ITO confirma necesidad de rediseño de fundaciones Sector F-12. Solicita propuesta de solución en 5 días hábiles.',
             'Espera de resolución geotécnica. 45 operarios improductivos durante 6 horas.'],
            [10, 'bueno',        22, 'Presentación de informe geotécnico a Mandante. Propuesta de solución: sistema de wellpoints + refuerzo fundaciones con micropilotes. Mandante confirma aprobación preliminar.',
             null, null],
        ];

        foreach ($reports as $i => [$daysAgo, $weather, $temp, $work, $instructions, $issues]) {
            $date = now()->subDays($daysAgo)->toDateString();

            $report = DailyReport::create([
                'contract_id'          => $contract->id,
                'report_date'          => $date,
                'report_number'        => 'DO-2024-CHN-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'weather'              => $weather,
                'temperature'          => $temp,
                'work_executed'        => $work,
                'instructions_received'=> $instructions,
                'issues_encountered'   => $issues,
                'personnel_on_site'    => [
                    ['trade' => 'Capataz',          'count' => 1],
                    ['trade' => 'Operador Grúa',    'count' => 2],
                    ['trade' => 'Soldador 3G/4G',   'count' => 4],
                    ['trade' => 'Rigger',            'count' => 2],
                    ['trade' => 'Ayudante General', 'count' => 8 + ($daysAgo <= 3 ? 4 : 0)],
                ],
                'equipment_on_site' => [
                    ['name' => 'Grúa Liebherr 600t',  'quantity' => 1],
                    ['name' => 'Camión Mixer 8m³',     'quantity' => $daysAgo === 3 ? 2 : 0],
                    ['name' => 'Excavadora CAT 320',   'quantity' => $daysAgo >= 7 ? 0 : 1],
                ],
                'materials_received' => $daysAgo === 3
                    ? 'Despacho 28 m³ hormigón H-30 (O/C N°2024-CHN-MAT-042). Temperatura mezcla: 18°C.'
                    : null,
                'safety_incidents' => null,
                'visitors'         => $daysAgo === 7 ? 'Ing. Andrés Villablanca (ITO Mandante) — inspección rutinaria' : null,
                'created_by'       => $terreno->id,
            ]);

            // Vincular diarios al evento correspondiente
            if (in_array($daysAgo, [7, 8, 9, 10])) {
                $report->events()->attach($e2->id); // Condición geotécnica
            }
            if ($daysAgo === 4) {
                // Día de lluvia — no vinculado a evento formal (es nota interna)
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function createMilestone(
        Contract $contract,
        string $name,
        Carbon $planned,
        ?Carbon $actual,
        int $progress,
        string $status,
        bool $critical = false
    ): void {
        ContractMilestone::create([
            'contract_id'            => $contract->id,
            'name'                   => $name,
            'planned_date'           => $planned->toDateString(),
            'actual_date'            => $actual?->toDateString(),
            'progress_percentage'    => $progress,
            'status'                 => $status,
            'is_critical'            => $critical,
            'generates_notification' => $critical,
            'source'                 => 'manual',
        ]);
    }
}
