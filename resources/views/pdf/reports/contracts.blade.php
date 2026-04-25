<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado General de Contratos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 9pt; color: #1a1a2e; line-height: 1.5; }

        .header { background: #2a6496; color: #fff; padding: 18px 28px; margin-bottom: 18px; }
        .header h1 { font-size: 16pt; font-weight: 800; }
        .header p  { font-size: 8pt; opacity: 0.8; margin-top: 3px; }

        /* KPI row — tabla porque dompdf no soporta CSS Grid */
        .kpi-wrap { padding: 0 28px 16px; }
        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 8px 0; }
        .kpi-cell { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
                    padding: 10px 8px; text-align: center; width: 25%; }
        .kpi-val { font-size: 16pt; font-weight: 800; color: #2a6496; }
        .kpi-lbl { font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.06em;
                   color: #7c7c9a; margin-top: 2px; }

        /* Tabla de contratos */
        .tbl-wrap { padding: 0 28px; }
        table.data { width: 100%; border-collapse: collapse; font-size: 8pt; }
        table.data thead th { background: #f1f5f9; padding: 6px 8px; text-align: left;
                               font-weight: 700; color: #4a4a6a; font-size: 7pt;
                               text-transform: uppercase; border-bottom: 2px solid #cbd5e1; }
        table.data tbody td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        table.data tbody tr:nth-child(even) td { background: #fafafa; }

        .badge { display: inline; padding: 2px 6px; border-radius: 4px; font-size: 7pt; font-weight: 600; }
        .b-vigente    { background: #dcfce7; color: #166534; }
        .b-suspendido { background: #fef9c3; color: #854d0e; }
        .b-en_disputa { background: #fee2e2; color: #b91c1c; }
        .b-terminado  { background: #f1f5f9; color: #4a4a6a; }
        .b-bajo    { background: #dcfce7; color: #166534; }
        .b-medio   { background: #fef9c3; color: #854d0e; }
        .b-alto    { background: #ffedd5; color: #c2410c; }
        .b-critico { background: #fee2e2; color: #b91c1c; }

        .footer { position: fixed; bottom: 12px; left: 28px; right: 28px;
                  border-top: 1px solid #e2e8f0; padding-top: 5px;
                  font-size: 7pt; color: #7c7c9a; }
        .footer-inner { width: 100%; }
        .footer-inner td:last-child { text-align: right; }
    </style>
</head>
<body>

<div class="header">
    <h1>Estado General de Contratos</h1>
    <p>Generado: {{ now()->setTimezone('America/Santiago')->format('d/m/Y H:i') }}</p>
</div>

{{-- KPIs en fila horizontal usando tabla (dompdf no soporta grid/flex) --}}
<div class="kpi-wrap">
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-val">{{ $contracts->count() }}</div>
                <div class="kpi-lbl">Total contratos</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-val">{{ $contracts->where('status','vigente')->count() }}</div>
                <div class="kpi-lbl">Vigentes</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-val">{{ $contracts->where('status','en_disputa')->count() }}</div>
                <div class="kpi-lbl">En disputa</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-val" style="font-size: 12pt;">
                    CLP {{ number_format($contracts->where('currency','CLP')->sum('current_amount') / 100, 0, ',', '.') }}
                </div>
                <div class="kpi-lbl">Monto vigente CLP</div>
            </td>
        </tr>
    </table>
</div>

{{-- Tabla de contratos --}}
<div class="tbl-wrap">
    <table class="data">
        <thead>
            <tr>
                <th style="width:12%">N° Contrato</th>
                <th style="width:22%">Nombre</th>
                <th style="width:18%">Mandante</th>
                <th style="width:16%; text-align:right">Monto vigente</th>
                <th style="width:12%; text-align:center">Término</th>
                <th style="width:10%; text-align:center">Estado</th>
                <th style="width:10%; text-align:center">Riesgo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($contracts as $c)
            <tr>
                <td style="font-family: monospace; font-weight: 600;">{{ $c->number }}</td>
                <td>{{ \Str::limit($c->name, 38) }}</td>
                <td>{{ $c->mandante?->name }}</td>
                <td style="text-align: right;">
                    {{ $c->currency }} {{ number_format($c->current_amount / 100, 0, ',', '.') }}
                </td>
                <td style="text-align: center;">{{ $c->contractual_end_date?->format('d/m/Y') }}</td>
                <td style="text-align: center;">
                    <span class="badge b-{{ $c->status }}">
                        {{ \App\Models\Contract::STATUS_LABELS[$c->status] ?? $c->status }}
                    </span>
                </td>
                <td style="text-align: center;">
                    @if($c->latestRiskScore)
                        <span class="badge b-{{ $c->latestRiskScore->score_level }}">
                            {{ ucfirst($c->latestRiskScore->score_level) }} ({{ $c->latestRiskScore->score_value }})
                        </span>
                    @else
                        <span style="color:#7c7c9a">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="footer">
    <table class="footer-inner">
        <tr>
            <td>Claim Guard — Reporte Confidencial</td>
            <td>{{ now()->setTimezone('America/Santiago')->format('d/m/Y') }}</td>
        </tr>
    </table>
</div>

</body>
</html>
