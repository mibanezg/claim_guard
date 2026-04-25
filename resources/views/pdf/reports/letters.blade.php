<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Correspondencia</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 9pt; color: #1a1a2e; line-height: 1.5; }

        .header { background: #2a6496; color: #fff; padding: 18px 28px; margin-bottom: 16px; }
        .header h1 { font-size: 16pt; font-weight: 800; }
        .header .sub { font-size: 10pt; opacity: 0.9; margin-top: 4px; font-weight: 600; }
        .header p  { font-size: 8pt; opacity: 0.8; margin-top: 3px; }

        /* KPI row usando tabla (dompdf no soporta grid/flex) */
        .kpi-wrap { padding: 0 28px 14px; }
        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 8px 0; }
        .kpi-cell { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
                    padding: 8px 6px; text-align: center; width: 20%; }
        .kpi-val { font-size: 14pt; font-weight: 800; color: #2a6496; }
        .kpi-lbl { font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.05em;
                   color: #7c7c9a; margin-top: 2px; }

        .tbl-wrap { padding: 0 28px; }
        table.data { width: 100%; border-collapse: collapse; font-size: 8pt; }
        table.data thead th { background: #f1f5f9; padding: 6px 7px; text-align: left;
                               font-weight: 700; color: #4a4a6a; font-size: 7pt;
                               text-transform: uppercase; border-bottom: 2px solid #cbd5e1; }
        table.data tbody td { padding: 5px 7px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        table.data tbody tr:nth-child(even) td { background: #fafafa; }

        .badge { display: inline; padding: 2px 5px; border-radius: 4px; font-size: 7pt; font-weight: 600; }
        .b-borrador   { background: #f1f5f9; color: #4a4a6a; }
        .b-emitida    { background: #dbeafe; color: #1d4ed8; }
        .b-recibida   { background: #dbeafe; color: #1d4ed8; }
        .b-respondida { background: #dcfce7; color: #166534; }
        .b-vencida    { background: #fee2e2; color: #b91c1c; }

        .footer { position: fixed; bottom: 12px; left: 28px; right: 28px;
                  border-top: 1px solid #e2e8f0; padding-top: 5px; font-size: 7pt; color: #7c7c9a; }
        .footer-inner { width: 100%; }
        .footer-inner td:last-child { text-align: right; }
    </style>
</head>
<body>

<div class="header">
    <h1>Registro de Correspondencia</h1>
    @if($contract)
        <div class="sub">{{ $contract->number }} — {{ $contract->name }}</div>
    @endif
    <p>Generado: {{ now()->setTimezone('America/Santiago')->format('d/m/Y H:i') }}</p>
</div>

{{-- KPIs en fila horizontal usando tabla --}}
<div class="kpi-wrap">
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-val">{{ $letters->count() }}</div>
                <div class="kpi-lbl">Total cartas</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-val">{{ $letters->where('status','emitida')->count() }}</div>
                <div class="kpi-lbl">Emitidas</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-val">{{ $letters->where('status','respondida')->count() }}</div>
                <div class="kpi-lbl">Respondidas</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-val" style="color: #b91c1c;">{{ $letters->where('status','vencida')->count() }}</div>
                <div class="kpi-lbl">Vencidas</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-val" style="color: #6d28d9;">{{ $letters->where('ai_generated', true)->count() }}</div>
                <div class="kpi-lbl">Generadas IA</div>
            </td>
        </tr>
    </table>
</div>

<div class="tbl-wrap">
    <table class="data">
        <thead>
            <tr>
                <th style="width:14%">N° Carta</th>
                @if(!$contract)<th style="width:10%">Contrato</th>@endif
                <th style="width:12%">Tipo</th>
                <th style="width:26%">Asunto</th>
                <th style="width:14%">De</th>
                <th style="width:14%">Para</th>
                <th style="width:10%; text-align:center">Emitida</th>
                <th style="width:10%; text-align:center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($letters as $l)
            <tr>
                <td style="font-family: monospace; font-weight: 600; white-space: nowrap;">{{ $l->letter_number }}</td>
                @if(!$contract)<td>{{ $l->contract?->number }}</td>@endif
                <td><span class="badge b-emitida">{{ \App\Models\ContractLetter::TYPE_LABELS[$l->type] ?? $l->type }}</span></td>
                <td>{{ \Str::limit($l->subject, 50) }}</td>
                <td>{{ \Str::limit($l->fromCompany?->name ?? '—', 22) }}</td>
                <td>{{ \Str::limit($l->toCompany?->name ?? '—', 22) }}</td>
                <td style="text-align: center; white-space: nowrap;">{{ $l->issued_at?->format('d/m/Y') ?? '—' }}</td>
                <td style="text-align: center;">
                    <span class="badge b-{{ $l->status }}">{{ ucfirst($l->status) }}</span>
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
