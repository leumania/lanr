<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class History extends Model
{
    protected $table = 'history';

    protected $fillable = ['tramite_id', 'usuario_id', 'accion'];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Eventos que no aportan valor de negocio al seguimiento (ediciones
     * menores sin cambio de estado) y por tanto se ocultan del timeline.
     */
    public function esRuido(): bool
    {
        return str_contains($this->accion, 'editado por el creador')
            || str_contains($this->accion, 'modificado durante la revisión');
    }

    /**
     * Traduce el texto crudo de la acción a un mensaje de negocio legible,
     * equivalente al comportamiento del prototipo (V11 request_tracking.html).
     * Si no reconoce el evento, devuelve la acción original como fallback.
     */
    public function mensajeHumanizado(): string
    {
        $accion = $this->accion;

        return match (true) {
            str_contains($accion, 'Requerimiento registrado') => 'Requerimiento registrado en obra.',
            str_contains($accion, 'Solicitud registrada') => 'Solicitud registrada en obra.',
            str_contains($accion, 'Visto bueno') || str_contains($accion, 'Revisión final completada') => 'Dio visto bueno al trámite.',
            str_contains($accion, 'Requerimiento recibido por Logística') => 'Logística recibió el requerimiento.',
            str_contains($accion, 'Compra registrada por Logística') || str_contains($accion, 'Cotización creada') => 'Logística registró la compra del requerimiento.',
            str_contains($accion, 'Cotización enviada a Administración') => 'La cotización fue enviada a Administración para su autorización.',
            str_contains($accion, 'Cotización autorizada') => 'Administración autorizó la compra.',
            str_contains($accion, 'Autorización de compra anulada') => 'Se anuló la autorización de compra.',
            str_contains($accion, 'Material enviado a obra') => 'Logística envió los materiales a obra.',
            str_contains($accion, 'Recepción confirmada en obra') => 'Obra confirmó la recepción de los materiales.',
            str_contains($accion, 'Pago de solicitud registrado') || str_contains($accion, 'Pago registrado por Tesorería') => 'Se registró el pago de la solicitud.',
            str_contains($accion, 'Conformidad') && str_contains($accion, 'finalizada') => 'Gerencia General dio conformidad final.',
            default => $accion,
        };
    }
}
