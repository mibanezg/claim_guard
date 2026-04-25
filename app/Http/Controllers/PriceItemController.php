<?php

namespace App\Http\Controllers;

use App\Exports\PriceItemsTemplateExport;
use App\Imports\PriceItemsImport;
use App\Models\Contract;
use App\Models\ContractPriceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PriceItemController extends Controller
{
    public function store(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $data = $request->validate([
            'code'        => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'unit'        => ['required', 'string', 'max:30'],
            'unit_cost'   => ['required', 'numeric', 'min:0.01'],
            'category'    => ['required', \Illuminate\Validation\Rule::in(array_keys(ContractPriceItem::CATEGORY_LABELS))],
        ]);

        $data['unit_cost']   = (int) round($data['unit_cost'] * 100);
        $data['contract_id'] = $contract->id;

        ContractPriceItem::create($data);

        return back()->with('success', 'Ítem agregado al cuadro de precios.');
    }

    public function update(Request $request, Contract $contract, ContractPriceItem $priceItem): RedirectResponse
    {
        $this->authorize('update', $contract);

        $data = $request->validate([
            'code'        => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'unit'        => ['required', 'string', 'max:30'],
            'unit_cost'   => ['required', 'numeric', 'min:0.01'],
            'category'    => ['required', \Illuminate\Validation\Rule::in(array_keys(ContractPriceItem::CATEGORY_LABELS))],
            'is_active'   => ['boolean'],
        ]);

        $data['unit_cost'] = (int) round($data['unit_cost'] * 100);
        $priceItem->update($data);

        return back()->with('success', 'Ítem actualizado.');
    }

    public function destroy(Contract $contract, ContractPriceItem $priceItem): RedirectResponse
    {
        $this->authorize('update', $contract);
        $priceItem->delete();

        return back()->with('success', 'Ítem eliminado del cuadro de precios.');
    }

    public function template(Contract $contract): BinaryFileResponse
    {
        $this->authorize('view', $contract);

        return Excel::download(
            new PriceItemsTemplateExport($contract->currency),
            "plantilla_cpu_{$contract->number}.xlsx"
        );
    }

    public function import(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $import = new PriceItemsImport($contract->id);
        Excel::import($import, $request->file('file'));

        $msg = "Se importaron {$import->imported} ítem(s) al cuadro de precios.";
        if (!empty($import->errors)) {
            $msg .= ' Advertencias: ' . implode(' | ', array_slice($import->errors, 0, 3));
        }

        return back()->with('success', $msg);
    }
}
