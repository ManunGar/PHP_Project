# InscripcionesController — Documentación detallada

> Archivo generado automáticamente con una explicación pedagógica, función por función, para un lector sin conocimientos de PHP.

## Índice

- Introducción
- __construct
- indexAction
- inscripcionAction
- eventoinfantilAction
- individualAction
- empresaAction
- resumeninscripcionAction
- confirmacionAction
- cancelarAction
- xaAction (endpoints AJAX/validaciones)
- Notas de seguridad y recomendaciones

---

## Introducción

Este documento describe cada acción (método que termina en `Action`) del controlador `InscripcionesController` localizado en `module/Backend/src/Backend/Controller/InscripcionesController.php`.

Para cada función se especifica:
- Para qué sirve (propósito)
- A qué ruta o acción corresponde (nombreAction)
- Si existen restricciones de rol (por ejemplo acceso solo a usuarios autenticados/administradores)
- Un flujo detallado paso a paso: entradas, validaciones, operaciones sobre entidades (Empresa, Usuario, Inscripcion, Inscrito, Participante, Menor), respuestas (render, redirect, JSON), y efectos secundarios (envío de emails, generación de archivos, cambios de estado)
- Inputs esperados y formatos
- Outputs / efectos secundarios
- Notas de seguridad, riesgos y recomendaciones concretas

---

## __construct

Propósito
- Inicializar dependencias del controlador: servicios como `SendMail` (servicio de envío de correos), `AuthenticationService`, contenedor de sesión y utilidades usadas posteriormente.

Ruta / Acción
- No es una action pública; se ejecuta cuando se instancia el controlador.

Restricción de roles
- No aplica por sí mismo, pero prepara objetos que luego se usarán en actions con restricciones.

Flujo detallado
1. Se reciben (vía inyección o local) servicios clave: el servicio de envío de correos (`SendMail`), autenticación (`AuthenticationService`) y posiblemente repositorios o mappers para entidades.
2. Se guarda/obtiene un contenedor de sesión para persistencia temporal (filtros, búsquedas, estado entre peticiones).
3. Se inicializan banderas o valores por defecto que usarán las actions.

Inputs
- Servicios del framework/DI.

Outputs / Efectos secundarios
- El controlador queda listo para procesar peticiones.

Notas de seguridad
- Asegurarse de no almacenar datos sensibles sin cifrar en la sesión.

---

## indexAction

Propósito
- Página principal de gestión/listado de inscripciones: muestra filtros, lista paginada y permite acceder a operaciones (crear, editar, exportar).

Ruta / Acción
- `indexAction` — normalmente corresponde a `/backend/inscripciones` o similar según rutas del módulo.

Restricción de roles
- Acceso típico: usuarios autenticados con rol administrativo o de gestión.

Flujo detallado
1. Leer parámetros de consulta (GET) y/o de sesión para aplicar filtros de búsqueda (curso, estado, nombre, email, fecha, etc.).
2. Si la petición incluye parámetros para limpiar filtros, resetear el contenedor de sesión correspondiente.
3. Construir condiciones de búsqueda (por ejemplo con `Utilidades::generaCondicion`) a partir de los filtros.
4. Consultar el modelo `Inscripciones`/repositorio para obtener la lista paginada de inscripciones aplicando condiciones y orden.
5. Preparar variables para la vista: lista de inscripciones, paginador, filtros actuales, enlaces para acciones (editar, exportar, borrar).
6. Devolver un `ViewModel` que renderiza la plantilla con la tabla/paginación.

Inputs
- GET: filtros (p.ej. curso_id, estado, nombre), parámetros de paginación (page), parámetros para limpiar filtros.

Outputs / Efectos secundarios
- Renderizado HTML con la lista. No hay cambios en BBDD si solo se muestran datos.

Notas de seguridad
- Evitar concatenar directamente valores de filtros en SQL. Usar consultas parametrizadas.
- Validar/sanitizar valores de paginación y límites.

---

## inscripcionAction

Propósito
- Crear o editar una inscripción. Gestiona la lógica de creación de Empresas/Usuarios si la inscripción es a nombre de una empresa, y la actualización de información personal.

Ruta / Acción
- `inscripcionAction` — ruta tipo `/backend/inscripciones/inscripcion[/:id]`.

Restricción de roles
- Acceso restringido a usuarios autenticados con permisos de gestión.

Flujo detallado (casos GET y POST)

GET (mostrar formulario):
1. Si se recibe un `id` (parámetro de ruta), cargar la `Inscripcion` correspondiente y sus datos relacionados (empresa, usuario, inscritos, participantes, menores).
2. Preparar los datos para el formulario: opciones de cursos, tarifas, datos pre-llenados del usuario/empresa.
3. Renderizar la vista del formulario.

POST (procesar envío del formulario):
1. Recoger datos enviados del formulario (datos de contacto, empresa, tipo de inscripción, inscritos/participantes, método de pago, etc.).
2. Validar los campos obligatorios (nombre, email, NIF/CIF, curso, importes) y validadores específicos (email válido, formato NIF/CIF, límites de campos).
3. Si la inscripción es a nombre de una empresa y se proporciona CIF/CIF normalizado:
   - Buscar si existe la `Empresa` por CIF; si existe, actualizar su información básica si procede; si no existe, crearla.
   - Vincular o crear un `Usuario` (trabajador/administrador) asociado a la empresa si el flujo lo requiere.
4. Crear o actualizar la entidad `Inscripcion` con los datos recibidos.
5. Para cada inscrito/participante enviado:
   - Crear o actualizar `Inscrito`/`Participante`/`Menor` según los datos (nombre, DNI, fecha nacimiento, tarifas aplicadas).
   - Calcular/importes: sumar precios individuales, aplicar descuentos/bonificaciones según reglas (por ejemplo, sitcol).
6. Guardar todo en la base de datos dentro de transacción si el sistema lo soporta (evita medias-inscripciones en caso de error).
7. Determinar el estado final de la inscripción (pendiente de pago, confirmada, incompleta) y persistirlo.
8. Enviar notificaciones: email de confirmación al solicitante y/o a la empresa (usando `SendMail`). Las plantillas y reemplazos deben usarse con cuidado.
9. Redirigir al usuario a `resumeninscripcionAction` o a la lista con un código de resultado (p.ej. `v2=ok` o `v3=error`).

Inputs
- POST: datos de inscripción (curso_id, usuario: nombre/email/tel, empresa:CIF/nombre, lista de inscritos, forma_pago, etc.).

Outputs / Efectos secundarios
- Inserciones/actualizaciones en tablas: `empresas`, `usuarios`, `inscripciones`, `inscritos`, `participantes`, `menores`.
- Envío de emails.
- Redirección al resumen u otra página.

Notas de seguridad
- Normalizar y validar NIF/CIF antes de búsquedas/creaciones.
- Evitar inyección SQL: usar mappers/queries parametrizadas.
- Proteger el flujo contra CSRF en formularios.
- Escapar cualquier dato que se muestre en vistas.

---

## eventoinfantilAction

Propósito
- Registrar inscripciones específicas para eventos infantiles (puede incluir alta de menores y tutores), con validaciones específicas (edad, autorización, datos del tutor).

Ruta / Acción
- `eventoinfantilAction` — ruta tipo `/backend/inscripciones/eventoinfantil`.

Restricción de roles
- Habitualmente usuarios del área de inscripciones o administración.

Flujo detallado
1. Mostrar formulario adaptado para eventos infantiles (campos dedicados para menores: fecha de nacimiento, alergias, autorizaciones, datos del tutor).
2. Al recibir POST:
   - Validar edad del menor contra límites del evento.
   - Validar campos obligatorios del tutor (nombre, teléfono, email) y autorizaciones (firmadas/checkboxes).
   - Crear `Menor` y asociar al `Inscrito` o a la `Inscripcion` según la estructura de datos.
   - Calcular importes y aplicar posibles descuentos para menores o familias.
   - Guardar la inscripción y enviar comprobantes si corresponde.
3. Redirigir a resumen o mostrar errores en el formulario.

Inputs
- Campos del menor (nombre, fecha_nac), datos tutor, datos de contacto y pago.

Outputs / Efectos secundarios
- Creación de registros en `menores` y `inscripciones`.
- Posible generación de autorización en PDF o envío de email.

Notas de seguridad
- Tratar datos sensibles de menores con especial cuidado; no exponer en reportes públicos.
- Validar límites de tamaño y sanitizar textos libres (alergias, observaciones).

---

## individualAction

Propósito
- Flujo de inscripción para un participante individual (no empresa). Similar a `inscripcionAction` pero simplificada para usuarios particulares.

Ruta / Acción
- `individualAction` — ruta tipo `/backend/inscripciones/individual`.

Restricción de roles
- Acceso para personal administrativo; en algunos casos puede estar abierto a usuarios autenticados desde frontend.

Flujo detallado
1. Mostrar formulario para inscripción individual con campos básicos (nombre, dni, email, curso, pago).
2. Al recibir POST:
   - Validar datos personales y del curso.
   - Buscar/crear `Usuario` si corresponde (por email/dni).
   - Crear `Inscripcion` vinculada al usuario.
   - Crear `Inscrito` con los datos del participante.
   - Calcular importes y marcar estado.
   - Enviar confirmación por email.
3. Redirigir al resumen.

Inputs
- POST: datos del participante, curso, forma de pago.

Outputs / Efectos secundarios
- Inserciones en `usuarios`, `inscripciones`, `inscritos`.
- Email de confirmación.

Notas de seguridad
- Verificar duplicados por email/dni para evitar cuentas duplicadas.

---

## empresaAction

Propósito
- Gestionar inscripciones realizadas por empresas (alumnos/trabajadores inscritos por la empresa). Incluye creación/actualización de `Empresa` y usuarios vinculados.

Ruta / Acción
- `empresaAction` — ruta tipo `/backend/inscripciones/empresa`.

Restricción de roles
- Acceso restringido a usuarios con permisos para gestionar inscripciones empresariales.

Flujo detallado
1. Mostrar formulario donde se puede seleccionar una `Empresa` existente (por CIF) o crear una nueva.
2. En POST:
   - Validar CIF y datos de la empresa; normalizar el CIF para búsquedas.
   - Si la empresa no existe, crearla; si existe, actualizar sus datos básicos.
   - Crear/actualizar `Usuario` administrador o contacto de la empresa si es necesario.
   - Procesar la lista de trabajadores a inscribir: para cada trabajador (nombre, dni, email, tarifa): crear `Inscrito` y vincularlo a `Inscripcion` y `Empresa`.
   - Calcular el total y aplicar condiciones de facturación por empresa.
   - Guardar y notificar por email a la empresa (factura/recibo o confirmación).
3. Redirigir a `resumeninscripcionAction` u otra vista de detalle.

Inputs
- POST: datos empresa (CIF, nombre), lista de trabajadores (arrays), curso.

Outputs / Efectos secundarios
- Creación/actualización en `empresas`, `usuarios`, `inscripciones`, `inscritos`.
- Envío de emails y posible generación de facturas.

Notas de seguridad
- Validar y normalizar CIF antes de buscar en BBDD.
- Controlar permisos para que solo usuarios autorizados creen/editen empresas.

---

## resumeninscripcionAction

Propósito
- Mostrar un resumen detallado de una inscripción concreta: datos del solicitante, empresa (si aplica), inscritos, importes, estado, y acciones disponibles (confirmar, cancelar, imprimir justificante).

Ruta / Acción
- `resumeninscripcionAction` — ruta tipo `/backend/inscripciones/resumen/:id`.

Restricción de roles
- Acceso a personal autorizado; en algunos casos el usuario que creó la inscripción también puede verla.

Flujo detallado
1. Recibir `id` de la inscripción desde la ruta o query.
2. Cargar la `Inscripcion` y entidades relacionadas (`Inscrito`, `Participante`, `Empresa`, `Usuario`), y datos calculados (total, descuentos, pagos realizados).
3. Generar una representación para la vista: lista de participantes, importes con desglose, estado y botones de acción.
4. Si la petición incluye acciones (por ejemplo confirmar pago desde la misma vista), procesarlas con las mismas validaciones que en `inscripcionAction`.

Inputs
- GET/route: `id` de la inscripción.
- POST (opcional): acciones sobre la inscripción (confirmar, marcar como pagado).

Outputs / Efectos secundarios
- Renderizado del resumen; si se procesa una acción, actualización de estado y envío de notificaciones.

Notas de seguridad
- Comprobar permisos para ver/editar la inscripción; no exponer datos de otras empresas o usuarios sin autorización.

---

## confirmacionAction

Propósito
- Acción que procesa la confirmación de una inscripción (por ejemplo, tras recibir pago o comprobación administrativa) y notifica al inscripto/empresa.

Ruta / Acción
- `confirmacionAction` — ruta tipo `/backend/inscripciones/confirmacion/:id`.

Restricción de roles
- Solo personal con permiso para cambiar estados (confirmar inscripciones).

Flujo detallado
1. Recibir `id` de la inscripción y (posiblemente) parámetros sobre el método de confirmación.
2. Verificar que la inscripción existe y que su estado actual permite la confirmación.
3. Actualizar el estado de la inscripción (por ejemplo, a `confirmada`).
4. Registrar información de pago si aplica (fecha, referencia, importe).
5. Enviar notificaciones: email de confirmación al usuario/empresa, con enlaces a justificantes o facturas.
6. Redirigir a `resumeninscripcionAction` con un código de resultado para mostrar mensaje en la interfaz.

Inputs
- Route param: `id`; POST opcional con datos de pago.

Outputs / Efectos secundarios
- Actualización del estado en la base de datos.
- Envío de email(s).

Notas de seguridad
- Registrar quién realiza la confirmación (usuario autenticado) para auditoría.

---

## cancelarAction

Propósito
- Marcar una inscripción como cancelada o anularla según políticas internas.

Ruta / Acción
- `cancelarAction` — ruta tipo `/backend/inscripciones/cancelar/:id`.

Restricción de roles
- Solo usuarios con permisos administrativos.

Flujo detallado
1. Recibir `id` de la inscripción a cancelar.
2. Comprobar que existe y que su estado permite cancelación (por ejemplo no estar ya confirmada y facturada irreversiblemente).
3. Actualizar estado de la inscripción a un código de cancelado (p.ej. `5` según convención interna).
4. Ejecutar acciones adicionales: devolución de plazas, anulación de cargos/pagos si aplica, envío de notificación al solicitante.
5. Redirigir a la lista con un código de resultado para mostrar mensaje.

Inputs
- Route param: `id`.

Outputs / Efectos secundarios
- Cambio de estado en base de datos; notificaciones.

Notas de seguridad
- Comprobar permisos y mantener registro de la acción (usuario, fecha, motivo de cancelación).

---

## xaAction (endpoints AJAX / validaciones)

Propósito
- Punto de entrada usado por llamadas AJAX para validaciones rápidas o pequeñas operaciones (p. ej. validar email, NIF/CIF, buscar empresa por CIF, comprobar disponibilidad de plazas).

Ruta / Acción
- `xaAction` — ruta tipo `/backend/inscripciones/xa` con parámetros por query (`fromQuery`) o POST dependiendo de la llamada.

Restricción de roles
- Normalmente protegido pero usado por la interfaz interna via AJAX; puede requerir autenticación.

Flujo detallado
1. Leer parámetro `accion` (p.ej. `validar_email`, `validar_nif`, `buscar_empresa`) desde la query/POST.
2. Según la acción:
   - `validar_email`: comprobar si el email ya existe en usuarios/inscripciones; devolver JSON con `{valid: true/false, msg: '...'}`.
   - `validar_nif`: normalizar y validar NIF/CIF; buscar coincidencias en `empresas` y devolver resultado.
   - `buscar_empresa`: recibir `cif`, normalizar, buscar empresa y devolver datos básicos JSON.
3. Construir y devolver una respuesta JSON (codificada con `Zend\Json\Json::encode` o similar).

Inputs
- Query/POST: `accion` y parámetros asociados (email, nif, cif, etc.).

Outputs / Efectos secundarios
- Respuesta JSON inmediata. No debe producir cambios persistentes en la base de datos a menos que la acción explícitamente lo requiera.

Notas de seguridad
- Evitar usar `$_GET`/`$_POST` directamente; usar el API del framework (`$this->params()->fromQuery()`/`fromPost()`) y validar tipos.
- Rate-limit y autenticar las llamadas AJAX para evitar abusos.
- Evitar exponer información sensible por JSON (p.ej. detalles de usuarios no necesarios).

---

## Notas de seguridad y recomendaciones generales

1. Validación y parametrización de consultas
- Donde el código construya condiciones SQL usando concatenación (p.ej. `Utilidades::generaCondicion`), revisar que se usen consultas parametrizadas para prevenir inyección SQL.

2. Evitar uso de `die()`/`exit` en exports
- Las acciones de exportación (Excel, CSV) deben devolver una respuesta tipo `StreamResponse` o su equivalente en Zend, y no abandonar el flujo con `die()`; esto facilita testing y manejo por middleware.

3. CSRF y validación de formularios
- Asegurar tokens CSRF en formularios POST que modifican datos (creación/edición de inscripciones) y validar en el servidor.

4. Manejo de archivos y validadores
- Si se aceptan justificantes o documentos (PDF, imágenes), validar tamaño y extensión y almacenar con rutas controladas evitando traversal y validando MIME-type.

5. Auditoría y trazabilidad
- Registrar quién realiza cambios significativos (confirmaciones, cancelaciones) y por qué (motivo) para auditoría.

6. Protección de datos personales
- Minimizar exposición de datos personales en listados exportables; aplicar políticas de retención y cifrado cuando proceda (especialmente para menores).

7. Pruebas y pruebas unitarias
- Añadir tests que cubran flujos críticos: creación de inscripción individual, empresa, evento infantil, confirmación y cancelación. Mockear servicios externos como `SendMail`.

---

## Archivo generado
- Ruta: `docs/InscripcionesController_Documentation.md`
- Fecha de generación: 14 de noviembre de 2025

Si quieres, puedo:
- Volcar también los comentarios línea a línea que añadimos dentro del fichero PHP a este Markdown (por ejemplo extrayendo bloques de comentarios y mostrándolos como secciones detalladas),
- Generar un archivo de ayuda corto en inglés o preparar un checklist de refactor prioritario (parametrización de queries, reemplazo de die/exit, protección CSRF).

Fin del documento.
