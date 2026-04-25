<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $companies = $this->service->paginate($request->only('search', 'type'));

        return Inertia::render('Companies/Index', [
            'companies' => CompanyResource::collection($companies),
            'filters'   => $request->only('search', 'type'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Company::class);

        return Inertia::render('Companies/Form');
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $this->authorize('create', Company::class);

        $this->service->create($request->validated());

        return redirect()->route('companies.index')
            ->with('success', 'Empresa creada correctamente.');
    }

    public function edit(Company $company): Response
    {
        $this->authorize('update', $company);

        return Inertia::render('Companies/Form', [
            'company' => new CompanyResource($company),
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $this->service->update($company, $request->validated());

        return redirect()->route('companies.index')
            ->with('success', 'Empresa actualizada correctamente.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        $this->service->delete($company);

        return redirect()->route('companies.index')
            ->with('success', 'Empresa eliminada.');
    }
}
