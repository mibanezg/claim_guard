<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractResource;
use App\Models\Contract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RightsController extends Controller
{
    public function index(Request $request): Response
    {
        $contractId = $request->input('contract_id');
        $contracts  = Contract::with(['mandante', 'contractor'])
            ->orderBy('name')->get();

        $selectedContract = $contractId
            ? $contracts->firstWhere('id', $contractId)
            : $contracts->first();

        $events = [];
        $stats  = ['formal' => 0, 'informal' => 0, 'none' => 0, 'na' => 0];

        if ($selectedContract) {
            $rawEvents = $selectedContract->events()
                ->withCount('rightsLetter')
                ->orderByDesc('occurred_at')
                ->get();

            foreach ($rawEvents as $e) {
                $status = $this->resolveStatus($e);
                $stats[$status]++;

                $events[] = [
                    'id'                  => $e->id,
                    'type_label'          => $e->type_label,
                    'occurred_at'         => $e->occurred_at?->format('d/m/Y'),
                    'description'         => $e->description,
                    'responsible_party'   => $e->responsible_party,
                    'party_label'         => $e->party_label,
                    'schedule_impact_days'=> $e->schedule_impact_days,
                    'cost_impact'         => $e->cost_impact / 100,
                    'resolution_status'   => $e->resolution_status,
                    'rights_reserved'     => $e->rights_reserved,
                    'rights_reserved_at'  => $e->rights_reserved_at?->format('d/m/Y'),
                    'rights_letters_count'=> $e->rights_letter_count,
                    'rights_status'       => $status,
                    'notice_deadline'     => $e->notice_deadline?->format('d/m/Y'),
                    'is_notice_overdue'   => $e->is_notice_overdue,
                ];
            }
        }

        return Inertia::render('Rights/Index', [
            'contracts'        => ContractResource::collection($contracts),
            'selectedContract' => $selectedContract ? ContractResource::make($selectedContract)->resolve() : null,
            'events'           => $events,
            'stats'            => $stats,
            'flash'            => session()->only(['success', 'error']),
            'typeLabels'       => \App\Models\ContractualEvent::TYPE_LABELS,
            'partyLabels'      => \App\Models\ContractualEvent::PARTY_LABELS,
        ]);
    }

    private function resolveStatus($event): string
    {
        if ($event->responsible_party === 'contratista') return 'na';
        if (!$event->rights_reserved) return 'none';
        return $event->rights_letter_count > 0 ? 'formal' : 'informal';
    }
}
