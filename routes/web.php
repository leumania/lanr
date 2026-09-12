<?php

use App\Models\Tramite;
use App\Models\Attachment;
use App\Models\ArchivoLogistica;
use App\Models\ReembolsoAdjunto;
use App\Models\SpPagoMultiple;
use App\Models\CotizacionArchivo;
use App\Models\ItemImagen;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use App\Http\Controllers\TramiteSearchController;


Route::get('/', fn () => redirect()->route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('ayuda', 'help')->name('help');
    Route::view('nuevo-tramite', 'tramites.create')->name('tramites.create');
    Route::view('nuevo-requerimiento', 'tramites.create-requirement')->name('tramites.create-requirement');
    Route::view('mis-tramites', 'tramites.index')->name('tramites.index');
    Route::view('nueva-solicitud-pago', 'tramites.create-sp')->name('tramites.create-sp');
    Route::get('tramites/{tramite}/editar', function (Tramite $tramite) {
        abort_unless($tramite->obra_id === auth()->user()->obra_activa_id, 404);
        abort_unless($tramite->creador_id === auth()->id(), 403);
        abort_unless($tramite->estado === 'Pendiente de mi revisión', 400);

        return view('tramites.edit', compact('tramite'));
    })->name('tramites.edit');
    Route::view('usuarios', 'admin.users')->name('admin.users');
    Route::view('unidades', 'admin.units')->name('admin.units');
    Route::view('reembolsos', 'admin.reimbursements')->name('admin.reimbursements');
    Route::view('tesoreria', 'admin.treasury')->name('admin.treasury');
    Route::view('ordenes', 'admin.orders')->name('admin.orders');
    Route::view('regularizaciones', 'admin.regularizations')->name('admin.regularizations');
    Route::get('api/tramites/search', TramiteSearchController::class)->name('tramites.search.api');
    Route::get('obras', function () {
        abort_unless(auth()->user()->hasAnyRole(['Gerencia General', 'Sistemas', 'Gerencia de Obra']), 403);

        return view('admin.obras');
    })->name('admin.obras');
    
    Route::get('tramites/{tramite}/cotizaciones', function (Tramite $tramite) {
        auth()->user()->can('view', $tramite) || abort(404);
        abort_unless($tramite->tipo === 'REQ', 404);

        return view('tramites.quotations', compact('tramite'));
    })->name('tramites.quotations');

    Route::get('tramites/{tramite}/pago-avanzado', function (Tramite $tramite) {
        auth()->user()->can('view', $tramite) || abort(404);
        abort_unless($tramite->tipo === 'SP', 404);
        return view('tramites.sp-advanced', compact('tramite'));
    })->name('tramites.sp-advanced');

    Route::get('tramites/{tramite}', function (Tramite $tramite) {
        auth()->user()->can('view', $tramite) || abort(404);

        return view('tramites.show', compact('tramite'));
    })
        ->name('tramites.show');

    Route::get('tramites/{tramite}/seguimiento', function (Tramite $tramite) {
        auth()->user()->can('view', $tramite) || abort(404);
        $tramite->load(['history.usuario', 'approvals.usuario']);

        return view('tramites.tracking', compact('tramite'));
    })->name('tramites.tracking');

    Route::get('tramites/{tramite}/pdf', function (Tramite $tramite) {
        auth()->user()->can('view', $tramite) || abort(404);
        abort_unless(in_array($tramite->tipo, ['REQ', 'SP'], true), 400);

        $tramite->load(['items', 'approvals.usuario', 'creador']);

        if ($tramite->tipo === 'SP') {
            return Pdf::view('pdf.solicitud-pago', compact('tramite'))
                ->format('a4')
                ->download("{$tramite->tracking}.pdf");
        }

        $secciones = $tramite->items->pluck('seccion')->unique()->values();

        $periodo = match (true) {
            $secciones->count() === 1 && $secciones[0] === 'Ejecución de Obra' => 'EJECUCIÓN',
            $secciones->count() === 1 && $secciones[0] === 'Ing. de Seguridad' => 'ING. SEGURIDAD',
            $secciones->count() > 1 => 'EJECUCIÓN',
            default => strtoupper($secciones->first() ?? ''),
        };

        return Pdf::view('pdf.requerimiento', compact('tramite', 'periodo'))
            ->format('a4')
            ->download("{$tramite->tracking}.pdf");
    })->name('tramites.pdf');

    Route::get('tramites/{tramite}/adjuntos/{attachment}', function (Tramite $tramite, Attachment $attachment) {
        auth()->user()->can('view', $tramite) || abort(404);
        abort_unless($attachment->tramite_id === $tramite->id, 404);

        return Storage::disk('public')->download($attachment->nombre_archivo, $attachment->nombre_original);
    })->name('tramites.attachments.download');

    Route::get('items/{item}/imagenes/{imagen}', function (\App\Models\Item $item, ItemImagen $imagen) {
        abort_unless($imagen->item_id === $item->id, 404);
        abort_unless(auth()->user()->can('view', $item->tramite), 404);

        return Storage::disk('public')->download($imagen->nombre_archivo, $imagen->nombre_original);
    })->name('items.images.download');

    Route::get('ordenes-archivos/{archivo}/descargar', function (\App\Models\OrdenAdjunto $archivo) {
        abort_unless($archivo->orden->obra_id === auth()->user()->obra_activa_id, 404);
        return Storage::disk('public')->download($archivo->nombre_archivo, $archivo->nombre_original);
    })->name('orders.files.download');

    Route::get('archivos-logistica/{archivo}/descargar', function (ArchivoLogistica $archivo) {
        abort_unless(auth()->user()->can('view', $archivo->tramite), 404);
        return Storage::disk('public')->download($archivo->nombre_archivo, $archivo->nombre_original);
    })->name('logistica.files.download');

    Route::get('cotizaciones-archivos/{archivo}/descargar', function (CotizacionArchivo $archivo) {
        abort_unless(auth()->user()->can('view', $archivo->cotizacion->tramite), 404);
        return Storage::disk('public')->download($archivo->nombre_archivo, $archivo->nombre_original);
    })->name('quotations.files.download');

    Route::get('reembolsos-archivos/{archivo}/descargar', function (ReembolsoAdjunto $archivo) {
        abort_unless(auth()->user()->obra_activa_id === $archivo->reembolso->obra_id, 404);
        return Storage::disk('public')->download($archivo->nombre_archivo, $archivo->nombre_original);
    })->name('reimbursements.files.download');

    Route::get('sp-pagos/{pago}/descargar', function (SpPagoMultiple $pago) {
        abort_unless(auth()->user()->can('view', $pago->gestion->tramite), 404);
        abort_unless($pago->nombre_archivo, 404);
        return Storage::disk('public')->download($pago->nombre_archivo, $pago->nombre_original);
    })->name('sp.payments.download');

    Route::get('pagos-tesoreria/{pago}/descargar', function (\App\Models\PagoTesoreria $pago) {
        abort_unless(auth()->user()->hasAnyRole(['Tesorería', 'Logística', 'Sistemas']), 404);
        abort_unless($pago->solicitud->tramite->obra_id === auth()->user()->obra_activa_id, 404);
        abort_unless($pago->nombre_archivo, 404);
        return Storage::disk('public')->download($pago->nombre_archivo, $pago->nombre_original);
    })->name('tesoreria.pagos.download');
});

require __DIR__.'/settings.php';