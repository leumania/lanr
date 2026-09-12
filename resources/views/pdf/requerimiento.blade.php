<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { size: A4; margin: 12mm 8mm; }
    * { box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 7pt; color: #000; }

    .marco { border: 1.3pt solid #000; padding: 0; }

    .header { display: flex; border-bottom: 0.7pt solid #000; height: 25mm; }
    .header .logo { width: 34mm; border-right: 0.7pt solid #000; display: flex; align-items: center; justify-content: center; }
    .header .logo img { max-width: 30mm; max-height: 21mm; }
    .header .titulo { width: 106mm; border-right: 0.7pt solid #000; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 10pt; text-align: center; }
    .header .datos { flex: 1; display: flex; flex-direction: column; }
    .header .datos .fila { flex: 1; display: flex; border-bottom: 0.7pt solid #000; }
    .header .datos .fila:last-child { border-bottom: none; }
    .header .datos .etiqueta { width: 20mm; border-right: 0.7pt solid #000; display: flex; align-items: center; padding-left: 1.5mm; font-weight: bold; font-size: 5.2pt; }
    .header .datos .etiqueta.rojo { color: #E31B23; }
    .header .datos .valor { flex: 1; display: flex; align-items: center; justify-content: center; font-size: 5.8pt; }

    .proyecto { display: flex; border-bottom: 0.7pt solid #000; min-height: 13mm; }
    .proyecto .etiqueta { width: 34mm; background: #FBE6DB; border-right: 0.7pt solid #000; display: flex; align-items: center; padding-left: 1.5mm; font-weight: bold; font-size: 5.5pt; }
    .proyecto .valor { flex: 1; display: flex; align-items: center; padding: 1mm 2mm; font-size: 6pt; }

    .lugar { display: flex; border-bottom: 0.7pt solid #000; min-height: 8mm; }
    .lugar .etiqueta { width: 34mm; background: #D9EAF7; border-right: 0.7pt solid #000; display: flex; align-items: center; padding-left: 1.5mm; font-weight: bold; font-size: 5.5pt; }
    .lugar .valor { flex: 1; display: flex; align-items: center; padding: 1mm 2mm; font-size: 6pt; }

    table.items { width: 100%; border-collapse: collapse; font-size: 6pt; }
    table.items th { background: #D9EAF7; border: 0.7pt solid #000; padding: 1.5mm 1mm; font-size: 5.5pt; text-align: center; }
    table.items td { border: 0.7pt solid #000; padding: 1mm; text-align: center; }
    table.items td.desc { text-align: left; }
    table.items .seccion-header td { background: #FBE6DB; font-weight: bold; text-align: left; }

    .firmas { display: flex; border-top: 0.7pt solid #000; margin-top: 3mm; }
    .firmas .firma { flex: 1; border-right: 0.7pt solid #000; padding: 2mm; text-align: center; }
    .firmas .firma:last-child { border-right: none; }
    .firmas .firma .rol { font-weight: bold; font-size: 6pt; margin-bottom: 8mm; }
    .firmas .firma .linea { border-top: 0.7pt solid #000; padding-top: 1mm; font-size: 5.5pt; }
</style>
</head>
<body>
    <div class="marco">
        <div class="header">
            <div class="logo">
                @if (file_exists(public_path('images/logo-lanr.jpeg')))
                    <img src="{{ public_path('images/logo-lanr.jpeg') }}">
                @endif
            </div>
            <div class="titulo">REQUERIMIENTO DE MATERIALES</div>
            <div class="datos">
                <div class="fila">
                    <div class="etiqueta rojo">N°</div>
                    <div class="valor" style="font-weight:bold;">{{ $tramite->numero }}</div>
                </div>
                <div class="fila">
                    <div class="etiqueta">FECHA SOLICITUD</div>
                    <div class="valor">{{ $tramite->fecha->format('d/m/Y') }}</div>
                </div>
                <div class="fila">
                    <div class="etiqueta">PERIODO USO</div>
                    <div class="valor">{{ $periodo }}</div>
                </div>
            </div>
        </div>

        <div class="proyecto">
            <div class="etiqueta">NOMBRE DEL PROYECTO</div>
            <div class="valor">{{ $tramite->proyecto }}</div>
        </div>

        <div class="lugar">
            <div class="etiqueta">LUGAR</div>
            <div class="valor">{{ $tramite->lugar }}</div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:6%">N°</th>
                    <th style="width:44%">DESCRIPCIÓN</th>
                    <th style="width:10%">UND.</th>
                    <th style="width:10%">CANT.</th>
                    <th style="width:10%">STOCK</th>
                    <th style="width:10%">COMPRAR</th>
                    <th style="width:10%">JUSTIFICACIÓN</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tramite->items->groupBy('seccion') as $seccion => $itemsSeccion)
                    <tr class="seccion-header">
                        <td colspan="7">{{ strtoupper($seccion) }}</td>
                    </tr>
                    @foreach ($itemsSeccion as $item)
                        <tr>
                            <td>{{ $item->nro }}</td>
                            <td class="desc">{{ $item->descripcion }}</td>
                            <td>{{ $item->unidad }}</td>
                            <td>{{ number_format($item->cantidad, 2) }}</td>
                            <td>{{ number_format($item->stock, 2) }}</td>
                            <td>{{ number_format($item->comprar, 2) }}</td>
                            <td class="desc">{{ $item->justificacion }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

        <div class="firmas">
            @foreach ($tramite->approvals as $approval)
                <div class="firma">
                    <div class="rol">{{ strtoupper($approval->rol) }}</div>
                    <div class="linea">{{ $approval->usuario->name }}</div>
                </div>
            @endforeach
            <div class="firma">
                <div class="rol">SOLICITADO POR</div>
                <div class="linea">{{ $tramite->creador->name }}</div>
            </div>
        </div>
    </div>
</body>
</html>