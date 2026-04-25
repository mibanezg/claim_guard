<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ChangeOrder;
use App\Models\ClaimRiskScore;
use App\Models\ContractDocument;
use App\Models\ContractLetter;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name', 'number', 'description', 'type',
        'mandante_company_id', 'contractor_company_id',
        'original_amount', 'current_amount', 'currency',
        'contractual_start_date', 'contractual_end_date',
        'actual_start_date', 'projected_end_date',
        'notification_days', 'status',
        'clauses', 'applicable_law', 'jurisdiction',
        'created_by',
        'ms_project_imported_at', 'primavera_imported_at',
        'claim_summary', 'claim_pdf_path',
        'claim_pdf_sharepoint_id', 'claim_pdf_sharepoint_url',
        'claim_generated_at',
        'contract_text', 'contract_pdf_name',
    ];

    protected $casts = [
        'clauses'                 => 'array',
        'contractual_start_date'  => 'date',
        'contractual_end_date'    => 'date',
        'actual_start_date'       => 'date',
        'projected_end_date'      => 'date',
        'ms_project_imported_at'  => 'datetime',
        'primavera_imported_at'   => 'datetime',
        'claim_generated_at'      => 'datetime',
    ];

    // Transiciones de estado permitidas
    const TRANSITIONS = [
        'borrador'   => ['vigente', 'suspendido'],
        'vigente'    => ['suspendido', 'terminado', 'en_disputa'],
        'suspendido' => ['vigente', 'terminado'],
        'terminado'  => [],
        'en_disputa' => ['terminado'],
    ];

    const STATUS_LABELS = [
        'borrador'   => 'Borrador',
        'vigente'    => 'Vigente',
        'suspendido' => 'Suspendido',
        'terminado'  => 'Terminado',
        'en_disputa' => 'En Disputa',
    ];

    const TYPE_LABELS = [
        'obra'       => 'Obra',
        'suministro' => 'Suministro',
        'servicios'  => 'Servicios',
        'EPC'        => 'EPC',
        'mixto'      => 'Mixto',
    ];

    public function mandante(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'mandante_company_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'contractor_company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? []);
    }

    public function allowedTransitions(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ContractMilestone::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContractualEvent::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(ContractLetter::class);
    }

    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContractDocument::class);
    }

    public function latestRiskScore(): HasOne
    {
        return $this->hasOne(ClaimRiskScore::class)->latestOfMany('calculated_at');
    }

    public function riskScores(): HasMany
    {
        return $this->hasMany(ClaimRiskScore::class)->orderByDesc('calculated_at');
    }

    public function assignedUsers(): HasMany
    {
        return $this->hasMany(ContractUser::class);
    }

    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    public function priceItems(): HasMany
    {
        return $this->hasMany(ContractPriceItem::class);
    }
}
