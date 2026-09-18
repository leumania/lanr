<?php

namespace App\Support;

/**
 * Roles centralizados que determinan quién puede crear cada tipo de trámite,
 * equivalente a las constantes TRAMITE_CREATOR_ROLES, OFFICE_REQUIREMENT_ROLES
 * y PAYMENT_REQUEST_CREATOR_ROLES de V11 (Version-Escritorio/V11/app.py).
 */
class TramiteRoles
{
    /**
     * Roles de obra que pueden crear cualquier requerimiento (todas las secciones).
     */
    public const TRAMITE_CREATOR_ROLES = [
        'Gerencia de Obra',
        'Control y Planeamiento',
    ];

    /**
     * Roles de oficina que solo pueden crear requerimientos de Útiles de Oficina.
     */
    public const OFFICE_REQUIREMENT_ROLES = [
        'Administración',
        'Logística',
        'Tesorería',
        'Sistemas',
    ];

    /**
     * Roles que pueden crear solicitudes de pago.
     */
    public const PAYMENT_REQUEST_CREATOR_ROLES = [
        'Gerencia de Obra',
        'Control y Planeamiento',
        'Contabilidad',
        'Administración',
    ];

    /**
     * Roles habilitados para crear requerimientos (de obra u oficina).
     *
     * @return array<int, string>
     */
    public static function requirementCreatorRoles(): array
    {
        return array_values(array_unique([
            ...self::TRAMITE_CREATOR_ROLES,
            ...self::OFFICE_REQUIREMENT_ROLES,
        ]));
    }
}
