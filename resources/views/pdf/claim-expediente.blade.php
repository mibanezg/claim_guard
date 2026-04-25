<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expediente de Claim — {{ $contract->number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
            font-size: 10pt;
            color: #1a1a2e;
            line-height: 1.6;
        }

        /* ---- Portada ---- */
        .cover {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            background: #f8fafc;
            border-left: 6px solid #2a6496;
        }

        .cover-tag {
            font-size: 9pt;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #2a6496;
            margin-bottom: 24px;
        }

        .cover-title {
            font-size: 28pt;
            font-weight: 800;
            color: #1a1a2e;
            line-height: 1.2;
            margin-bottom: 8px;
        }

        .cover-subtitle {
            font-size: 14pt;
            color: #4a4a6a;
            margin-bottom: 48px;
        }

        .cover-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            max-width: 600px;
        }

        .cover-meta-item label {
            display: block;
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #7c7c9a;
            margin-bottom: 3px;
        }

        .cover-meta-item span {
            font-size: 10pt;
            font-weight: 600;
            color: #1a1a2e;
        }

        .cover-footer {
            margin-top: auto;
            padding-top: 40px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cover-footer .brand {
            font-size: 9pt;
            font-weight: 700;
            color: #2a6496;
        }

        .cover-footer .date {
            font-size: 9pt;
            color: #7c7c9a;
        }

        /* ---- Riesgo badge ---- */
        .risk-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 999px;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 32px;
        }
        .risk-critico { background: #fee2e2; color: #b91c1c; }
        .risk-alto    { background: #ffedd5; color: #c2410c; }
        .risk-medio   { background: #fef9c3; color: #854d0e; }
        .risk-bajo    { background: #dcfce7; color: #166534; }

        /* ---- Secciones ---- */
        .page-break { page-break-before: always; }

        .section {
            padding: 40px 60px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-number {
            width: 32px;
            height: 32px;
            background: #2a6496;
            color: #fff;
            font-size: 12pt;
            font-weight: 800;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-title {
            font-size: 15pt;
            font-weight: 700;
            color: #1a1a2e;
        }

        /* ---- Resumen ejecutivo ---- */
        .summary-text {
            font-size: 10pt;
            line-height: 1.8;
            color: #2d2d4a;
        }

        .summary-text p {
            margin-bottom: 12px;
            color: #2d2d4a;
        }

        .summary-text h1,
        .summary-text h2 {
            font-size: 11pt;
            font-weight: 700;
            color: #2a6496;
            margin-top: 22px;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
            letter-spacing: 0.04em;
        }

        .summary-text h3,
        .summary-text h4 {
            font-size: 10pt;
            font-weight: 700;
            color: #1a1a2e;
            margin-top: 16px;
            margin-bottom: 6px;
        }

        .summary-text strong {
            font-weight: 700;
            color: #1a1a2e;
        }

        .summary-text em {
            font-style: italic;
        }

        .summary-text ul,
        .summary-text ol {
            margin: 8px 0 12px 20px;
            padding: 0;
        }

        .summary-text li {
            margin-bottom: 5px;
            color: #2d2d4a;
        }

        .summary-text hr {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 16px 0;
        }

        /* Tablas generadas por Markdown */
        .summary-text table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin: 12px 0 20px 0;
        }

        .summary-text table th {
            background: #f1f5f9;
            padding: 7px 10px;
            text-align: left;
            font-weight: 700;
            color: #4a4a6a;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 2px solid #cbd5e1;
        }

        .summary-text table td {
            padding: 7px 10px;
            border-bottom: 1px solid #e2e8f0;
            color: #2d2d4a;
            vertical-align: top;
        }

        .summary-text table tr:last-child td {
            border-bottom: none;
        }

        /* ---- Tablas ---- */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 20px;
        }

        thead th {
            background: #f1f5f9;
            padding: 8px 12px;
            text-align: left;
            font-weight: 700;
            color: #4a4a6a;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #cbd5e1;
        }

        tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #2d2d4a;
            vertical-align: top;
        }

        tbody tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 7.5pt;
            font-weight: 600;
        }

        .badge-blue   { background: #dbeafe; color: #1d4ed8; }
        .badge-green  { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-red    { background: #fee2e2; color: #b91c1c; }
        .badge-gray   { background: #f1f5f9; color: #4a4a6a; }
        .badge-purple { background: #ede9fe; color: #6d28d9; }

        /* ---- KPIs de impacto ---- */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
        }

        .kpi-value {
            font-size: 20pt;
            font-weight: 800;
            color: #2a6496;
            line-height: 1;
        }

        .kpi-label {
            font-size: 8pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #7c7c9a;
            margin-top: 6px;
        }

        /* ---- Pie de página ---- */
        .page-footer {
            position: fixed;
            bottom: 20px;
            left: 60px;
            right: 60px;
            display: flex;
            justify-content: space-between;
            font-size: 7.5pt;
            color: #7c7c9a;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
    </style>
</head>
<body>

{{-- ========================================================= --}}
{{-- PORTADA                                                   --}}
{{-- ========================================================= --}}
<div class="cover">
    <div class="cover-tag">Expediente de Claim Contractual</div>

    <div class="cover-title">{{ $contract->name }}</div>
    <div class="cover-subtitle">{{ $contract->number }}</div>

    @if($riskScore)
        <div class="risk-badge risk-{{ $riskScore->score_level }}">
            Riesgo {{ ucfirst($riskScore->score_level) }} — {{ $riskScore->score_value }}/100
        </div>
    @endif

    <div class="cover-meta">
        <div class="cover-meta-item">
            <label>Mandante</label>
            <span>{{ $contract->mandante?->name ?? '—' }}</span>
        </div>
        <div class="cover-meta-item">
            <label>Contratista</label>
            <span>{{ $contract->contractor?->name ?? '—' }}</span>
        </div>
        <div class="cover-meta-item">
            <label>Fecha inicio contractual</label>
            <span>{{ $contract->contractual_start_date?->format('d/m/Y') ?? '—' }}</span>
        </div>
        <div class="cover-meta-item">
            <label>Fecha término contractual</label>
            <span>{{ $contract->contractual_end_date?->format('d/m/Y') ?? '—' }}</span>
        </div>
        <div class="cover-meta-item">
            <label>Monto original</label>
            <span>{{ $contract->currency }} {{ number_format($contract->original_amount / 100, 0, ',', '.') }}</span>
        </div>
        <div class="cover-meta-item">
            <label>Monto vigente</label>
            <span>{{ $contract->currency }} {{ number_format($contract->current_amount / 100, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="cover-footer">
        <div class="brand">Claim Guard</div>
        <div class="date">Generado: {{ now()->setTimezone('America/Santiago')->format('d/m/Y H:i') }}</div>
    </div>
</div>

{{-- ========================================================= --}}
{{-- SECCIÓN 1 — RESUMEN EJECUTIVO                            --}}
{{-- ========================================================= --}}
<div class="section page-break">
    <div class="section-header">
        <div class="section-number">1</div>
        <div class="section-title">Resumen Ejecutivo</div>
    </div>

    @if($summaryHtml)
        <div class="summary-text">{!! $summaryHtml !!}</div>
    @elseif($contract->claim_summary)
        <div class="summary-text" style="white-space: pre-wrap;">{{ $contract->claim_summary }}</div>
    @else
        <p style="color: #7c7c9a; font-style: italic;">
            El resumen ejecutivo será generado por IA y estará disponible en la próxima versión del expediente.
        </p>
    @endif
</div>

{{-- ========================================================= --}}
{{-- SECCIÓN 2 — IMPACTO ACUMULADO                            --}}
{{-- ========================================================= --}}
<div class="section page-break">
    <div class="section-header">
        <div class="section-number">2</div>
        <div class="section-title">Impacto Acumulado en Plazo y Costo</div>
    </div>

    @php
        $totalDays = $contract->events->sum('schedule_impact_days') + $contract->changeOrders->where('status', 'aprobada')->sum('schedule_impact_days');
        $totalCost = $contract->events->sum('cost_impact') + $contract->changeOrders->where('status', 'aprobada')->sum('cost_impact');
        $pendingOc = $contract->changeOrders->whereIn('status', ['solicitada', 'evaluacion'])->sum('cost_impact');
    @endphp

    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-value">{{ number_format($totalDays) }}</div>
            <div class="kpi-label">Días de impacto total</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value">{{ $contract->currency }} {{ number_format(abs($totalCost) / 100, 0, ',', '.') }}</div>
            <div class="kpi-label">Impacto costo aprobado</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value">{{ $contract->currency }} {{ number_format(abs($pendingOc) / 100, 0, ',', '.') }}</div>
            <div class="kpi-label">OC pendientes de aprobación</div>
        </div>
    </div>

    @if($contract->projected_end_date && $contract->contractual_end_date)
        @php
            $desvio = $contract->contractual_end_date->diffInDays($contract->projected_end_date, false);
        @endphp
        <p style="color: {{ $desvio > 0 ? '#b91c1c' : '#166534' }}; font-weight: 600; margin-bottom: 16px;">
            @if($desvio > 0)
                La fecha término proyectada ({{ $contract->projected_end_date->format('d/m/Y') }})
                supera la contractual en {{ $desvio }} días corridos.
            @else
                El contrato se proyecta dentro del plazo contractual.
            @endif
        </p>
    @endif
</div>

{{-- ========================================================= --}}
{{-- SECCIÓN 3 — LÍNEA DE TIEMPO DE EVENTOS                   --}}
{{-- ========================================================= --}}
<div class="section page-break">
    <div class="section-header">
        <div class="section-number">3</div>
        <div class="section-title">Línea de Tiempo de Eventos Contractuales</div>
    </div>

    @if($contract->events->isEmpty())
        <p style="color: #7c7c9a; font-style: italic;">No hay eventos registrados.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 90px;">Fecha</th>
                    <th style="width: 120px;">Tipo</th>
                    <th>Descripción</th>
                    <th style="width: 100px;">Responsable</th>
                    <th style="width: 80px;">Impacto días</th>
                    <th style="width: 80px;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contract->events->sortBy('occurred_at') as $event)
                <tr>
                    <td>{{ $event->occurred_at->format('d/m/Y') }}</td>
                    <td>
                        <span class="badge badge-blue">
                            {{ \App\Models\ContractualEvent::TYPE_LABELS[$event->type] ?? $event->type }}
                        </span>
                    </td>
                    <td>{{ \Str::limit($event->description, 120) }}</td>
                    <td>{{ ucfirst($event->responsible_party) }}</td>
                    <td style="text-align: center;">
                        @if($event->schedule_impact_days != 0)
                            <span style="color: {{ $event->schedule_impact_days > 0 ? '#b91c1c' : '#166534' }}; font-weight: 700;">
                                {{ $event->schedule_impact_days > 0 ? '+' : '' }}{{ $event->schedule_impact_days }}
                            </span>
                        @else
                            <span style="color: #7c7c9a;">—</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusColors = [
                                'pendiente'   => 'badge-yellow',
                                'negociacion' => 'badge-blue',
                                'resuelto'    => 'badge-green',
                                'escalado'    => 'badge-red',
                            ];
                        @endphp
                        <span class="badge {{ $statusColors[$event->resolution_status] ?? 'badge-gray' }}">
                            {{ ucfirst($event->resolution_status) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ========================================================= --}}
{{-- SECCIÓN 4 — CORRESPONDENCIA                              --}}
{{-- ========================================================= --}}
<div class="section page-break">
    <div class="section-header">
        <div class="section-number">4</div>
        <div class="section-title">Registro de Correspondencia</div>
    </div>

    @if($contract->letters->isEmpty())
        <p style="color: #7c7c9a; font-style: italic;">No hay cartas registradas.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 120px;">N° Carta</th>
                    <th style="width: 100px;">Tipo</th>
                    <th>Asunto</th>
                    <th style="width: 90px;">Emitida</th>
                    <th style="width: 90px;">Vence</th>
                    <th style="width: 80px;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contract->letters->sortBy('issued_at') as $letter)
                <tr>
                    <td style="font-weight: 600; font-family: monospace;">{{ $letter->letter_number }}</td>
                    <td>
                        @php
                            $typeColors = [
                                'notificacion'     => 'badge-blue',
                                'reserva_derechos' => 'badge-purple',
                                'respuesta'        => 'badge-green',
                                'cobranza'         => 'badge-yellow',
                                'acta_reunion'     => 'badge-gray',
                                'memorando'        => 'badge-gray',
                            ];
                        @endphp
                        <span class="badge {{ $typeColors[$letter->type] ?? 'badge-gray' }}">
                            {{ \App\Models\ContractLetter::TYPE_LABELS[$letter->type] ?? $letter->type }}
                        </span>
                    </td>
                    <td>{{ \Str::limit($letter->subject, 80) }}</td>
                    <td>{{ $letter->issued_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $letter->response_deadline?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        @php
                            $statusColors = [
                                'borrador'    => 'badge-gray',
                                'emitida'     => 'badge-blue',
                                'recibida'    => 'badge-blue',
                                'respondida'  => 'badge-green',
                                'vencida'     => 'badge-red',
                            ];
                        @endphp
                        <span class="badge {{ $statusColors[$letter->status] ?? 'badge-gray' }}">
                            {{ ucfirst($letter->status) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ========================================================= --}}
{{-- SECCIÓN 5 — ÓRDENES DE CAMBIO                            --}}
{{-- ========================================================= --}}
<div class="section page-break">
    <div class="section-header">
        <div class="section-number">5</div>
        <div class="section-title">Órdenes de Cambio</div>
    </div>

    @if($contract->changeOrders->isEmpty())
        <p style="color: #7c7c9a; font-style: italic;">No hay órdenes de cambio registradas.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 100px;">N° OC</th>
                    <th>Descripción</th>
                    <th style="width: 80px;">Solicitante</th>
                    <th style="width: 90px;">Impacto días</th>
                    <th style="width: 110px;">Impacto costo</th>
                    <th style="width: 90px;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contract->changeOrders as $oc)
                <tr>
                    <td style="font-weight: 600; font-family: monospace;">{{ $oc->request_number }}</td>
                    <td>{{ \Str::limit($oc->description, 100) }}</td>
                    <td>{{ ucfirst($oc->requested_by_party) }}</td>
                    <td style="text-align: center;">
                        @if($oc->schedule_impact_days != 0)
                            <span style="color: {{ $oc->schedule_impact_days > 0 ? '#b91c1c' : '#166534' }}; font-weight: 700;">
                                {{ $oc->schedule_impact_days > 0 ? '+' : '' }}{{ $oc->schedule_impact_days }}
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td style="text-align: right;">
                        {{ $contract->currency }} {{ number_format(abs($oc->cost_impact) / 100, 0, ',', '.') }}
                    </td>
                    <td>
                        @php
                            $ocStatusColors = [
                                'solicitada'            => 'badge-blue',
                                'evaluacion'            => 'badge-yellow',
                                'aprobada'              => 'badge-green',
                                'rechazada'             => 'badge-red',
                                'aprobada_parcialmente' => 'badge-yellow',
                            ];
                        @endphp
                        <span class="badge {{ $ocStatusColors[$oc->status] ?? 'badge-gray' }}">
                            {{ ucfirst(str_replace('_', ' ', $oc->status)) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ========================================================= --}}
{{-- SECCIÓN 6 — QUANTUM DE COSTOS                            --}}
{{-- ========================================================= --}}
<div class="section page-break">
    <div class="section-header">
        <div class="section-number">6</div>
        <div class="section-title">Quantum de Costos por Evento</div>
    </div>

    @php
        use App\Models\EventCostItem;
        $eventsWithQuantum = $contract->events->filter(fn($e) => $e->costItems->isNotEmpty());
        $totalQuantum = $contract->events->flatMap->costItems->sum('amount');
        $totalImpact  = $contract->events->sum('cost_impact');
        $reconciled   = abs($totalQuantum - $totalImpact) < 100;
    @endphp

    @if($eventsWithQuantum->isEmpty())
        <p style="color: #7c7c9a; font-style: italic;">No hay quantum de costos documentado para los eventos de este contrato.</p>
    @else
        {{-- KPI resumen --}}
        <div class="kpi-row" style="grid-template-columns: repeat(3,1fr); margin-bottom: 24px;">
            <div class="kpi-card">
                <div class="kpi-value" style="font-size: 14pt;">
                    {{ $contract->currency }} {{ number_format($totalQuantum / 100, 0, ',', '.') }}
                </div>
                <div class="kpi-label">Total quantum reclamado</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="font-size: 14pt;">
                    {{ $contract->currency }} {{ number_format($totalImpact / 100, 0, ',', '.') }}
                </div>
                <div class="kpi-label">Impacto costo registrado</div>
            </div>
            <div class="kpi-card" style="background: {{ $reconciled ? '#dcfce7' : '#fef9c3' }}; border-color: {{ $reconciled ? '#86efac' : '#fde047' }};">
                <div class="kpi-value" style="font-size: 14pt; color: {{ $reconciled ? '#166534' : '#854d0e' }};">
                    {{ $reconciled ? 'Conciliado' : $contract->currency . ' ' . number_format(abs($totalQuantum - $totalImpact) / 100, 0, ',', '.') }}
                </div>
                <div class="kpi-label" style="color: {{ $reconciled ? '#166534' : '#854d0e' }};">
                    {{ $reconciled ? '✓ Quantum = impacto' : 'Diferencia pendiente' }}
                </div>
            </div>
        </div>

        {{-- Tabla resumen por evento --}}
        <table>
            <thead>
                <tr>
                    <th>Evento</th>
                    <th style="width: 80px;">Fecha</th>
                    <th style="width: 110px; text-align: right;">Directo</th>
                    <th style="width: 110px; text-align: right;">Indirecto</th>
                    <th style="width: 110px; text-align: right;">Utilidad</th>
                    <th style="width: 120px; text-align: right; font-weight: 800;">Total quantum</th>
                    <th style="width: 70px; text-align: center;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($eventsWithQuantum->sortBy('occurred_at') as $ev)
                @php
                    $qDirect   = $ev->costItems->whereIn('cost_category', EventCostItem::DIRECT_CATEGORIES)->sum('amount');
                    $qIndirect = $ev->costItems->whereIn('cost_category', EventCostItem::INDIRECT_CATEGORIES)->sum('amount');
                    $qProfit   = $ev->costItems->where('cost_category', 'profit')->sum('amount');
                    $qTotal    = $ev->costItems->sum('amount');
                    $qRecon    = abs($qTotal - $ev->cost_impact) < 100;
                @endphp
                <tr>
                    <td>
                        <span style="font-weight: 600;">{{ \App\Models\ContractualEvent::TYPE_LABELS[$ev->type] ?? $ev->type }}</span>
                        <br><span style="font-size: 8pt; color: #7c7c9a;">{{ \Str::limit($ev->description, 80) }}</span>
                    </td>
                    <td>{{ $ev->occurred_at->format('d/m/Y') }}</td>
                    <td style="text-align: right; font-family: monospace;">
                        {{ number_format($qDirect / 100, 0, ',', '.') }}
                    </td>
                    <td style="text-align: right; font-family: monospace;">
                        {{ number_format($qIndirect / 100, 0, ',', '.') }}
                    </td>
                    <td style="text-align: right; font-family: monospace;">
                        {{ $qProfit > 0 ? number_format($qProfit / 100, 0, ',', '.') : '—' }}
                    </td>
                    <td style="text-align: right; font-family: monospace; font-weight: 700; color: #2a6496;">
                        {{ number_format($qTotal / 100, 0, ',', '.') }}
                    </td>
                    <td style="text-align: center;">
                        <span class="badge {{ $qRecon ? 'badge-green' : 'badge-yellow' }}">
                            {{ $qRecon ? 'OK' : 'Diferencia' }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ========================================================= --}}
{{-- SECCIÓN 7 — ANÁLISIS DE PLAZO (CPM)                      --}}
{{-- ========================================================= --}}
<div class="section page-break">
    <div class="section-header">
        <div class="section-number">7</div>
        <div class="section-title">Análisis de Plazo — Metodología CPM</div>
    </div>

    @php
        use App\Models\EventDelayAnalysis;
        $eventsWithCpm = $contract->events->filter(fn($e) => !is_null($e->delayAnalysis));
        $totalDelayDays = $eventsWithCpm->sum(fn($e) => $e->delayAnalysis->delay_days ?? 0);
        $criticalCount  = $eventsWithCpm->filter(fn($e) => $e->delayAnalysis->is_critical_path)->count();
        $compensableCount = $eventsWithCpm->filter(fn($e) => $e->delayAnalysis->delay_type === 'compensable')->count();
    @endphp

    @if($eventsWithCpm->isEmpty())
        <p style="color: #7c7c9a; font-style: italic;">No hay análisis de plazo CPM documentado para los eventos de este contrato.</p>
    @else
        {{-- KPIs --}}
        <div class="kpi-row" style="grid-template-columns: repeat(3,1fr); margin-bottom: 24px;">
            <div class="kpi-card">
                <div class="kpi-value">{{ $totalDelayDays }}</div>
                <div class="kpi-label">Días de atraso analizados</div>
            </div>
            <div class="kpi-card" style="background: #fee2e2; border-color: #fca5a5;">
                <div class="kpi-value" style="color: #b91c1c;">{{ $criticalCount }}</div>
                <div class="kpi-label" style="color: #b91c1c;">Eventos en ruta crítica</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color: #166534;">{{ $compensableCount }}</div>
                <div class="kpi-label">Atrasos compensables</div>
            </div>
        </div>

        {{-- Tabla de análisis --}}
        <table>
            <thead>
                <tr>
                    <th>Evento</th>
                    <th style="width: 80px;">Fecha</th>
                    <th style="width: 110px;">Tipo atraso</th>
                    <th style="width: 120px;">Método CPM</th>
                    <th style="width: 70px; text-align: center;">Días</th>
                    <th style="width: 70px; text-align: center;">Ruta crítica</th>
                </tr>
            </thead>
            <tbody>
                @foreach($eventsWithCpm->sortBy('occurred_at') as $ev)
                @php
                    $analysis = $ev->delayAnalysis;
                    $delayTypeColors = [
                        'compensable'  => 'badge-red',
                        'excusable'    => 'badge-blue',
                        'no_excusable' => 'badge-yellow',
                        'concurrente'  => 'badge-purple',
                    ];
                @endphp
                <tr>
                    <td>
                        <span style="font-weight: 600;">{{ \App\Models\ContractualEvent::TYPE_LABELS[$ev->type] ?? $ev->type }}</span>
                        <br><span style="font-size: 8pt; color: #7c7c9a;">{{ \Str::limit($ev->description, 80) }}</span>
                    </td>
                    <td>{{ $ev->occurred_at->format('d/m/Y') }}</td>
                    <td>
                        <span class="badge {{ $delayTypeColors[$analysis->delay_type] ?? 'badge-gray' }}">
                            {{ EventDelayAnalysis::DELAY_TYPE_LABELS[$analysis->delay_type] ?? $analysis->delay_type }}
                        </span>
                    </td>
                    <td style="font-size: 8pt; color: #4a4a6a;">
                        {{ EventDelayAnalysis::METHOD_LABELS[$analysis->analysis_method] ?? $analysis->analysis_method }}
                    </td>
                    <td style="text-align: center; font-weight: 700; color: {{ ($analysis->delay_days ?? 0) > 0 ? '#b91c1c' : '#166534' }};">
                        {{ $analysis->delay_days !== null ? ($analysis->delay_days > 0 ? '+' : '') . $analysis->delay_days : '—' }}
                    </td>
                    <td style="text-align: center;">
                        @if($analysis->is_critical_path)
                            <span class="badge badge-red">Sí</span>
                        @else
                            <span class="badge badge-gray">No</span>
                        @endif
                    </td>
                </tr>
                {{-- Narrativa técnica --}}
                @if($analysis->narrative)
                <tr style="background: #f8fafc;">
                    <td colspan="6" style="padding: 8px 12px 12px 24px; color: #4a4a6a; font-size: 8.5pt; font-style: italic; border-bottom: 2px solid #e2e8f0;">
                        <span style="font-weight: 700; font-style: normal; color: #2a6496;">Narrativa técnica: </span>
                        {{ \Str::limit($analysis->narrative, 400) }}
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ========================================================= --}}
{{-- SECCIÓN 8 — ÍNDICE DE DOCUMENTOS                         --}}
{{-- ========================================================= --}}
<div class="section page-break">
    <div class="section-header">
        <div class="section-number">8</div>
        <div class="section-title">Índice de Documentos</div>
    </div>

    @if($contract->documents->isEmpty())
        <p style="color: #7c7c9a; font-style: italic;">No hay documentos registrados en el expediente.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th style="width: 120px;">Categoría</th>
                    <th style="width: 80px;">Tipo</th>
                    <th style="width: 80px;">Tamaño</th>
                    <th style="width: 90px;">Fecha</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contract->documents as $doc)
                <tr>
                    <td>{{ $doc->name }}</td>
                    <td>
                        <span class="badge badge-gray">
                            {{ \App\Models\ContractDocument::CATEGORY_LABELS[$doc->category] ?? $doc->category }}
                        </span>
                    </td>
                    <td style="font-size: 8pt; color: #7c7c9a;">{{ $doc->file_type }}</td>
                    <td style="text-align: right;">{{ $doc->file_size_human }}</td>
                    <td>{{ $doc->created_at->format('d/m/Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- Pie de página fijo --}}
<div class="page-footer">
    <span>{{ $contract->number }} — {{ $contract->name }}</span>
    <span>Expediente de Claim | Claim Guard | Confidencial</span>
</div>

</body>
</html>
