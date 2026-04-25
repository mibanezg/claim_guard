<?php

use App\Jobs\ImportMicrosoftProjectJob;
use App\Jobs\ImportPrimaveraJob;
use App\Models\Contract;
use App\Models\ContractMilestone;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

describe('ImportMicrosoftProjectJob', function () {

    it('importa hitos desde XML de MS Project', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        // Copiar fixture al storage falso
        Storage::put('imports/test_project.xml', file_get_contents(
            base_path('tests/Fixtures/sample_ms_project.xml')
        ));

        // QUEUE_CONNECTION=sync → ejecuta en línea
        ImportMicrosoftProjectJob::dispatch($contract, 'imports/test_project.xml', $this->adminUser->id);

        // El XML tiene 4 tareas (UID 0 se ignora) → 3 hitos importados
        expect(ContractMilestone::where('contract_id', $contract->id)->count())->toBe(3);
    });

    it('no duplica hitos al reimportar el mismo archivo', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);
        $xml      = file_get_contents(base_path('tests/Fixtures/sample_ms_project.xml'));

        Storage::put('imports/test_project.xml', $xml);
        ImportMicrosoftProjectJob::dispatch($contract, 'imports/test_project.xml', $this->adminUser->id);

        // Segunda importación con el mismo external_id → actualiza, no duplica
        Storage::put('imports/test_project2.xml', $xml);
        ImportMicrosoftProjectJob::dispatch($contract, 'imports/test_project2.xml', $this->adminUser->id);

        expect(ContractMilestone::where('contract_id', $contract->id)->count())->toBe(3);
    });

    it('actualiza ms_project_imported_at en el contrato', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        Storage::put('imports/test_project.xml', file_get_contents(
            base_path('tests/Fixtures/sample_ms_project.xml')
        ));

        ImportMicrosoftProjectJob::dispatch($contract, 'imports/test_project.xml', $this->adminUser->id);

        expect($contract->fresh()->ms_project_imported_at)->not->toBeNull();
    });

    it('ignora la tarea raíz con UID=0', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        Storage::put('imports/test_project.xml', file_get_contents(
            base_path('tests/Fixtures/sample_ms_project.xml')
        ));

        ImportMicrosoftProjectJob::dispatch($contract, 'imports/test_project.xml', $this->adminUser->id);

        // Ningún hito debe tener external_id = '0'
        expect(
            ContractMilestone::where('contract_id', $contract->id)
                ->where('external_id', '0')
                ->exists()
        )->toBeFalse();
    });

    it('gestiona sin error si el archivo no existe', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        // No lanza excepción — simplemente registra el error y continúa
        expect(function () use ($contract) {
            ImportMicrosoftProjectJob::dispatch($contract, 'imports/no_existe.xml', $this->adminUser->id);
        })->not->toThrow(\Exception::class);
    });

});

describe('ImportPrimaveraJob', function () {

    it('importa actividades desde archivo XER de Primavera', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        Storage::put('imports/test_primavera.xer', file_get_contents(
            base_path('tests/Fixtures/sample_primavera.xer')
        ));

        ImportPrimaveraJob::dispatch($contract, 'imports/test_primavera.xer', $this->adminUser->id);

        // El XER tiene 2 actividades con fechas válidas
        expect(ContractMilestone::where('contract_id', $contract->id)->count())->toBe(2);
    });

    it('las actividades importadas usan source=primavera', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        Storage::put('imports/test_primavera.xer', file_get_contents(
            base_path('tests/Fixtures/sample_primavera.xer')
        ));

        ImportPrimaveraJob::dispatch($contract, 'imports/test_primavera.xer', $this->adminUser->id);

        $allPrimavera = ContractMilestone::where('contract_id', $contract->id)
            ->where('source', 'primavera')
            ->count();

        expect($allPrimavera)->toBe(2);
    });

    it('no duplica actividades al reimportar el mismo XER', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);
        $xer      = file_get_contents(base_path('tests/Fixtures/sample_primavera.xer'));

        Storage::put('imports/prim1.xer', $xer);
        ImportPrimaveraJob::dispatch($contract, 'imports/prim1.xer', $this->adminUser->id);

        Storage::put('imports/prim2.xer', $xer);
        ImportPrimaveraJob::dispatch($contract, 'imports/prim2.xer', $this->adminUser->id);

        expect(ContractMilestone::where('contract_id', $contract->id)->count())->toBe(2);
    });

    it('actualiza primavera_imported_at en el contrato', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        Storage::put('imports/test_primavera.xer', file_get_contents(
            base_path('tests/Fixtures/sample_primavera.xer')
        ));

        ImportPrimaveraJob::dispatch($contract, 'imports/test_primavera.xer', $this->adminUser->id);

        expect($contract->fresh()->primavera_imported_at)->not->toBeNull();
    });

});
