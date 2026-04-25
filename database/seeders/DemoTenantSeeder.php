<?php

namespace Database\Seeders;

use App\Models\ChangeOrder;
use App\Models\ClaimRiskScore;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractLetter;
use App\Models\ContractMilestone;
use App\Models\ContractualEvent;
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
