# LANR Inversiones: Sistema de gestion

## Version 2.0

Migracion del aplicativo local V1 (Flask + SQLite) a Laravel + Livewire + MySQL.

## Flujo principal

### Requerimiento de materiales

1. El usuario registra numero, fecha e items.
2. El sistema calcula `comprar = cantidad - stock`.
3. Control y Planeamiento y Gerencia de Obra registran los vistos buenos.
4. Logistica recibe el requerimiento.
5. Logistica registra la compra y el pago.
6. Logistica registra guia y envio a obra.
7. Gerencia de Obra o Control y Planeamiento confirma la recepcion.
8. El tramite pasa a `Cerrado`.

### Solicitud de pago

1. El usuario registra subtipo, beneficiario y conceptos.
2. El sistema calcula cada monto y el total.
3. Logistica y Administracion registran sus vistos buenos.
4. Gerencia General asigna el pago a Tesoreria o lo asume.
5. El pagador registra medio, monto, fecha, operacion y comprobante.
6. Gerencia General revisa y registra la conformidad final.
7. La solicitud pasa a `Cerrado`.

## Roles

- **Gerencia de Obra:** visto bueno y recepcion en obra.
- **Control y Planeamiento:** visto bueno y recepcion en obra.
- **Logistica:** recepcion, compra, pago logistico y envio.
- **Administracion:** visto bueno de solicitudes de pago.
- **Tesoreria:** pagos que le fueron asignados.
- **Gerencia General:** asignacion y conformidad final de solicitudes.
- **Sistemas:** administracion de usuarios y asistencia operativa.

## Estados

`Pendiente de aprobacion` -> `Aprobado` -> `Recibido por Logistica` -> `En gestion de compra` -> `Enviado a obra` -> `Cerrado`

`Pendiente de aprobacion` -> `Pendiente asignacion de pago` -> `Asignada a Tesoreria` o `Asignada a Gerencia General` -> `Pagada pendiente conformidad GG` -> `Cerrado`

## Ayuda para usuarios

La guia interactiva esta disponible en `/ayuda` y en el menu lateral como **Ayuda del sistema**. Las secciones se filtran por los roles del usuario autenticado.

## Versionado operativo

Cada cambio funcional debe actualizar:

1. Este documento, si modifica estados, permisos o datos.
2. La guia `/ayuda`, si modifica una accion visible para usuarios.
3. Las migraciones, modelos y pruebas correspondientes.

## Funcionalidades implementadas en la migracion

- Adjuntos generales para requerimientos y solicitudes de pago, con vista en linea y descarga protegida.
- Pago de compras de requerimientos por Tesoreria con comprobante (multiple), o por Caja Logistica / pago personal a reembolsar, con el umbral de S/ 1,900 que obliga a tramitar por Tesoreria por encima de ese monto.
- Regularizaciones de compra (documento y detalle obligatorios, deben cuadrar con los comprobantes) y de guia de remision (se abre automaticamente cuando el envio a obra queda con guia pendiente).
- Envio a obra con medio de transporte, responsable, costo, evidencia fotografica obligatoria y comprobantes de transporte opcionales.
- PDF de requerimiento y PDF de solicitud de pago.
- Flujo completo de reembolsos personales (registro, autorizacion de Administracion, atencion de Tesoreria con evidencia), con numero unico por obra.
- Notificaciones en cada transicion relevante del flujo (aprobaciones, asignacion y cierre de pagos SP, compra autorizada, pago de compra, envio y recepcion en obra); se marcan como leidas al abrir cada una.
- Multi-obra: numeracion de tracking y resolucion de aprobadores/roles siempre scoped a la obra activa, no al primer usuario global con ese rol.
- Bloqueo de cuentas desactivadas (login y sesion activa), y autorizacion de pagos SP por rol (Gerencia General / Tesoreria), no por cuentas especificas.

## Pendientes de paridad con V1 / mejoras conocidas

- Paridad visual exacta con todos los formatos de la V1 (la solicitud de pago en PDF aun no anexa comprobantes/adjuntos como un solo documento, ni replica moneda/amortizacion/cuentas bancarias del formato original).
- Los archivos subidos (adjuntos, comprobantes, regularizaciones) se sirven desde el disco `public` de Laravel: la ruta autenticada `tramites.attachments.download` valida acceso, pero el archivo tambien es alcanzable sin autenticacion via `/storage/...` si se conoce la ruta generada. Evaluar mover a un disco privado con streaming autenticado.
- El formulario de creacion de requerimientos no expone aun prioridad/fecha requerida por item ni el catalogo de unidades (si estan disponibles al editar).
- La politica de complejidad de contrasena (`Password::defaults`) solo se exige en produccion; fuera de produccion no hay ninguna regla minima.
