<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { size: A4; margin: 14mm 12mm; }
    * { box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #111; }
    .header { border: 1px solid #222; padding: 7mm; text-align: center; }
    .brand { font-size: 13pt; font-weight: bold; letter-spacing: 1pt; }
    .title { margin-top: 3mm; font-size: 14pt; font-weight: bold; }
    .meta { width: 100%; margin-top: 6mm; border-collapse: collapse; }
    .meta td, .items th, .items td { border: 1px solid #222; padding: 2.5mm; }
    .meta .label { width: 25%; background: #e8f2f7; font-weight: bold; }
    .items { width: 100%; margin-top: 7mm; border-collapse: collapse; }
    .items th { background: #d9eaf7; font-size: 8pt; text-align: center; }
    .items td { text-align: center; }
    .items td:first-child { text-align: left; }
    .total { margin-top: 4mm; text-align: right; font-size: 11pt; font-weight: bold; }
    .observaciones { margin-top: 8mm; min-height: 22mm; border: 1px solid #222; padding: 3mm; }
    .firmas { margin-top: 25mm; display: flex; gap: 12mm; }
    .firma { flex: 1; border-top: 1px solid #222; padding-top: 2mm; text-align: center; font-size: 8pt; }
</style>
</head>
<body>
    <div class="header">
        <div class="brand">LANR INVERSIONES</div>
        <div class="title">SOLICITUD DE PAGO</div>
    </div>
    <table class="meta">
        <tr><td class="label">Código</td><td>{{ $tramite->tracking }}</td><td class="label">N° solicitud</td><td>{{ $tramite->numero }}</td></tr>
        <tr><td class="label">Fecha</td><td>{{ $tramite->fecha->format('d/m/Y') }}</td><td class="label">Subtipo</td><td>{{ $tramite->subtipo }}</td></tr>
        <tr><td class="label">Beneficiario</td><td colspan="3">{{ $tramite->beneficiario }}</td></tr>
        <tr><td class="label">DNI / RUC</td><td>{{ $tramite->dni_ruc ?: '-' }}</td><td class="label">Modalidad</td><td>{{ $tramite->modalidad_pago }}</td></tr>
        <tr><td class="label">Proyecto</td><td colspan="3">{{ $tramite->proyecto }}</td></tr>
    </table>
    <table class="items">
        <thead><tr><th>Concepto</th><th style="width:15%">Unidad</th><th style="width:15%">Cantidad</th><th style="width:20%">Costo unitario</th><th style="width:20%">Monto</th></tr></thead>
        <tbody>
        @foreach ($tramite->items as $item)
            <tr><td>{{ $item->descripcion }}</td><td>{{ $item->unidad }}</td><td>{{ number_format($item->cantidad, 2) }}</td><td>S/ {{ number_format($item->costo, 2) }}</td><td>S/ {{ number_format($item->monto, 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <div class="total">TOTAL: S/ {{ number_format($tramite->abono, 2) }}</div>

    @if ($tramite->spCuentas->isNotEmpty())
        <table class="items">
            <thead><tr><th colspan="2">Cuentas bancarias</th></tr><tr><th style="width:50%">Banco</th><th style="width:50%">N° de cuenta / CCI</th></tr></thead>
            <tbody>
            @foreach ($tramite->spCuentas as $cuenta)
                <tr><td>{{ $cuenta->banco ?: '-' }}</td><td>{{ $cuenta->cuenta_cci ?: '-' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if ($tramite->spComprobantes->isNotEmpty())
        <table class="items">
            <thead><tr><th colspan="3">Comprobantes</th></tr><tr><th style="width:34%">Tipo</th><th style="width:33%">Número</th><th style="width:33%">Monto</th></tr></thead>
            <tbody>
            @foreach ($tramite->spComprobantes as $comprobante)
                <tr><td>{{ $comprobante->tipo ?: '-' }}</td><td>{{ $comprobante->numero ?: '-' }}</td><td>S/ {{ number_format($comprobante->monto, 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="observaciones"><strong>Observaciones:</strong><br>{{ $tramite->observaciones ?: 'Sin observaciones.' }}</div>
    <div class="firmas">
        @foreach ($tramite->approvals as $approval)<div class="firma">{{ strtoupper($approval->rol) }}<br>{{ $approval->usuario->name }}</div>@endforeach
        <div class="firma">SOLICITADO POR<br>{{ $tramite->creador->name }}</div>
    </div>
</body>
</html>
