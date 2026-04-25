<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $contractId = $this->route('contract')?->id;
        $reportId   = $this->route('dailyReport')?->id;

        return [
            'report_date'          => [
                'required', 'date', 'before_or_equal:today',
                Rule::unique('daily_reports')->where('contract_id', $contractId)
                    ->ignore($reportId)
                    ->whereNull('deleted_at'),
            ],
            'weather'              => ['required', Rule::in(array_keys(\App\Models\DailyReport::WEATHER_LABELS))],
            'temperature'          => ['nullable', 'integer', 'between:-30,60'],
            'work_executed'        => ['required', 'string', 'min:10'],
            'personnel_on_site'    => ['nullable', 'array'],
            'personnel_on_site.*.trade' => ['required_with:personnel_on_site', 'string', 'max:100'],
            'personnel_on_site.*.count' => ['required_with:personnel_on_site', 'integer', 'min:0'],
            'equipment_on_site'    => ['nullable', 'array'],
            'equipment_on_site.*.name'     => ['required_with:equipment_on_site', 'string', 'max:100'],
            'equipment_on_site.*.quantity' => ['required_with:equipment_on_site', 'integer', 'min:0'],
            'materials_received'   => ['nullable', 'string'],
            'instructions_received'=> ['nullable', 'string'],
            'issues_encountered'   => ['nullable', 'string'],
            'safety_incidents'     => ['nullable', 'string'],
            'visitors'             => ['nullable', 'string'],
            'general_notes'        => ['nullable', 'string'],
            'event_ids'            => ['nullable', 'array'],
            'event_ids.*'          => ['integer', 'exists:contractual_events,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'report_date.required'      => 'La fecha del reporte es obligatoria.',
            'report_date.before_or_equal' => 'No se pueden registrar reportes con fecha futura.',
            'report_date.unique'        => 'Ya existe un reporte para esta fecha en el contrato.',
            'work_executed.required'    => 'El trabajo ejecutado es obligatorio.',
            'work_executed.min'         => 'Describe el trabajo con al menos 10 caracteres.',
        ];
    }
}
