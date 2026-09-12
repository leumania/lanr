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

- Adjuntos generales para requerimientos y solicitudes de pago.
- Descarga protegida de documentos desde el expediente.
- Pago de compras de requerimientos por Tesoreria con comprobante.
- Regularizaciones con documento obligatorio y fecha de cierre.
- PDF de requerimiento y PDF de solicitud de pago.
- Solicitudes de pago de compras y atención por Tesoreria con comprobante.
- Archivos de respaldo, comprobantes y guias vinculados al expediente.

## Pendientes de paridad con V1

- Adjuntos especificos de cada etapa logistica (cotizacion, comprobante y guia).
- Flujo completo de reembolsos personales.
- Paridad visual exacta con todos los formatos de la V1.
