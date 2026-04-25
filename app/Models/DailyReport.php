<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class DailyReport extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $connection = 'tenant';

    protected $fillable = [
        'contract_id',
        'report_date',
        'report_number',
        'weather',
        'temperature',
        'work_executed',
        'personnel_on_site',
        'equipment_on_site',
        'materials_received',
        'instructions_received',
        'issues_encountered',
        'safety_incidents',
        'visitors',
        'general_notes',
        'created_by',
    ];

    protected $casts = [
        'report_date'       => 'date',
        'personnel_on_site' => 'array',
        'equipment_on_site' => 'array',
    ];

    const WEATHER_LABELS = [
        'bueno'        => 'Bueno',
        'nublado'      => 'Nublado',
        'lluvia'       => 'Lluvia',
        'viento_fuerte'=> 'Viento fuerte',
        'nevada'       => 'Nevada',
        'otro'         => 'Otro',
    ];

    const WEATHER_ICONS = [
        'bueno'        => 'sunny',
        'nublado'      => 'cloud',
        'lluvia'       => 'rainy',
        'viento_fuerte'=> 'air',
        'nevada'       => 'ac_unit',
        'otro'         => 'device_thermostat',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(ContractualEvent::class, 'daily_report_event', 'daily_report_id', 'contractual_event_id');
    }

    public function getWeatherLabelAttribute(): string
    {
        return self::WEATHER_LABELS[$this->weather] ?? $this->weather;
    }

    public function getTotalPersonnelAttribute(): int
    {
        return collect($this->personnel_on_site ?? [])->sum('count');
    }
}
