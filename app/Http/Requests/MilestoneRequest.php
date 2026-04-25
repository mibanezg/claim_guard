<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                   => ['required', 'string', 'max:255'],
            'description'            => ['nullable', 'string'],
            'planned_date'           => ['required', 'date'],
            'actual_date'            => ['nullable', 'date'],
            'progress_percentage'    => ['required', 'integer', 'min:0', 'max:100'],
            'is_critical'            => ['boolean'],
            'generates_notification' => ['boolean'],
            'status'                 => ['required', 'in:pendiente,en_progreso,completado,atrasado'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                   => 'nombre',
            'planned_date'           => 'fecha planificada',
            'actual_date'            => 'fecha real',
            'progress_percentage'    => 'avance',
            'is_critical'            => 'hito crítico',
            'generates_notification' => 'genera notificación',
            'status'                 => 'estado',
        ];
    }
}
