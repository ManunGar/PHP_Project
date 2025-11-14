# FormacionController — Documentación detallada

Índice

- Introducción
- indexAction
- cursosAction
- cursoAction
- inscripcionesincompletasAction
- generacerfificadosAction
- borrarcursoAction
- sincronizacursoAction
- xlscursosAction
- inscripcionesAction
- inscripcionAction
- borrarinscripcionAction
- inscripcionjustificanteAction
- xlsinscripcionesAction
- xlsinscritosAction
- xlsparticipantesAction
- categoriasAction
- borrarcategoriaAction
- inscritosAction
- participantesAction
- borrarinscritoAction
- borrarparticipanteAction
- enviaremailpagoAction
- agregaparticipantesAction
- agregamenoresAction
- asistenciaAction
- certificadoAction
- xaAction

---

Introducción

Este documento volca la explicación detallada de cada función presente en `module/Backend/src/Backend/Controller/FormacionController.php`.
Cada sección incluye:
- Para qué sirve la función
- A qué ruta corresponde (si es una Action)
- Restricciones de rol encontradas en el código (si las hay)
- Desarrollo detallado del flujo de la función (pasos, entradas/salidas, efectos secundarios)
- Notas de seguridad y recomendaciones prácticas


### indexAction

- Propósito: punto de entrada del controlador; redirige al listado de cursos.
- Ruta/Action: `indexAction` — normalmente `backend/default` controller=formacion action=index
- Roles: no hay restricción explícita en la acción

Flujo detallado:
1. Redirige a la ruta `backend/default` con controller `formacion` y action `cursos`, pasando `v1 => 1` (página 1).
2. Evita tener una vista propia para index; centraliza lógica en `cursosAction`.

Efectos secundarios: ninguna otra aparte de la redirección.

Seguridad/recomendaciones: ninguna específica; es una redirección simple.


### cursosAction

- Propósito: mostrar un listado paginado de cursos con filtros aplicables desde un formulario.
- Ruta/Action: `cursosAction`.
- Roles: no hay control de rol explícito en la función; la protección puede venir de la configuración de rutas o middleware.

Flujo detallado:
1. Establece título del layout a "Cursos".
2. Lee un parámetro de la ruta `v2` para interpretar códigos (por ejemplo 525 o 536) y preparar mensajes de OK/KO.
3. Inicializa el objeto de datos `Cursos()` y establece orden por defecto `comienzo DESC`.
4. Si la petición es POST:
   - Usa `StripTags` para sanear entradas de texto procedentes del formulario.
   - Recoge campos: id_cur, nombre, tipo, estado, fecha inicio/fin, id_cat.
   - Guarda filtros en el contenedor de sesión `_container->curs_buscador` y resetea paginación `_container->curs_page`.
5. Si no es POST, permite limpiar filtros si `v2 == 114`.
6. Paginación:
   - Obtiene `v1` como página; si es -1 lee la página guardada en sesión; si no existe, fija página 1.
   - Calcula `offset = 50 * (page - 1)` (50 items por página).
7. Construye `WHERE` usando `Utilidades::generaCondicion('cursos', filtros)` si existen filtros en sesión. Nota: esto genera una cláusula SQL en texto.
8. Llama a `Cursos::get(where, orderby, limit, offset)` para obtener los cursos y `Cursos::num(where)` para contar.
9. Prepara el ViewModel con usuario, buscador, página, mensajes, número y lista de cursos.

Efectos secundarios: lectura de DB; guarda filtros/página en sesión.

Seguridad / recomendaciones:
- `Utilidades::generaCondicion` devuelve cadenas SQL: revisar que internamente parametriza o sanea correctamente para evitar inyección.
- `StripTags` limpia HTML pero no valida tipos ni longitudes; validar formatos y tipos en la capa de negocio.


### cursoAction

- Propósito: crear o editar un curso y mostrar su ficha con relaciones (inscripciones, certificados, etc.).
- Ruta: `cursoAction`.
- Roles: no hay restricción explícita; hay códigos `v3` que permiten acciones administrativas (sincronizar, subida de certificados, etc.).

Flujo detallado:
1. Establece título por defecto y lee `v3` para códigos de resultados/acciones subsidiarias.
2. Si la petición es POST:
   - Recoge muchos campos del formulario (id_cur, nombre, fechas, ubicación, precios, tipo, profesorado, precios, flags, contenido largo como descripciones) usando `StripTags` para la mayoría; algunos campos de texto largo se aceptan tal cual (HTML).
   - Crea un objeto `Curso(0)` y llama a `set($data)` para validar campos obligatorios. Si falta alguno, set() devuelve >0.
   - Si todo OK, llama a `save()` en la entidad Curso; si id_cur era 0 se trata de una creación.
3. Si no es POST, intenta cargar el curso por `v1` desde ruta.
4. Si existe el curso (id > 0), carga relaciones: inscripciones (pagadas/incompletas), calcula offset, número total y prepara datos para la vista.
5. Interpreta códigos `v3` para mostrar mensajes específicos (p. ej. sincronización OK/KO, errores de upload, etc.).
6. Prepara y retorna ViewModel con curso, inscripciones y otros datos (creador, empresa, paginación)

Efectos secundarios: creación/actualización de cursos en BD (vía la entidad), posible sincronización con WordPress (otra acción), manejo de cargas de certificados/justificantes en otras rutas.

Seguridad/recomendaciones:
- Los campos HTML largos deben procesarse con cuidado (XSS) si se muestran sin escapar en front.
- Validar formatos de fecha en el servidor, y normalizar valores monetarios.


### inscripcionesincompletasAction

- Propósito: enviar notificaciones a los usuarios con inscripciones incompletas (estado == 0) para un curso concreto.
- Ruta: `inscripcionesincompletasAction`.
- Roles: no explícito en la función.

Flujo detallado:
1. Lee `v1` desde la ruta para obtener el id del curso.
2. Crea `Inscripciones()` y obtiene todas las inscripciones con `id_cur = <id> AND estado = 0`.
3. Itera cada inscripción y llama a `Notificaciones::enviarInscripcionIncompleta($inscripcion, $this->sendMail)` delegando la lógica de notificación al helper/service.
4. Redirige a la ficha del curso con código `602` que indica notificaciones enviadas.

Efectos secundarios: envíos de correo (vía `sendMail`), posible actualización de logs si `Notificaciones` lo registra.

Seguridad/recomendaciones:
- Revisar límites de envío para evitar SPAM; procesar en background si son muchas notificaciones.


### generacerfificadosAction

- Propósito: generar y enviar certificados (diplomas) por lote para inscritos seleccionados.
- Ruta: `generacerfificadosAction` (solo POST)
- Roles: no explícito; operación potencialmente pesada.

Flujo detallado:
1. Solo procesa POST; lee `id_cur` desde POST.
2. Lee arrays `id_ui_generar` y `id_ui_enviar` con identificadores UI (ids de Inscrito) para generar y para enviar.
3. Para `id_ui_generar`: por cada id crea `Inscrito($ui)` y llama `Imprimir::certificadoPdf($inscrito, true)` para generar PDF; actualiza el `diploma` del Inscrito con el nombre de fichero.
4. Para `id_ui_enviar`: agrupa inscritos por `id_ins` (inscripción padre). Si no existe el diploma en disco, lo genera antes de enviar.
5. Para cada grupo de inscritos por `id_ins`, llama a `Notificaciones::enviarCertificaciones(new Inscripcion($id_ins), $this->sendMail, $ids_ui)`; registra fecha de envío en cada Inscrito si el envío fue exitoso.
6. Redirige a la ficha del curso con código `552` (certificados generados/enviados).

Efectos secundarios: generación de ficheros PDF, envíos de correo, actualización de la entidad Inscrito con nombre de diploma y fecha de envío.

Seguridad/recomendaciones:
- Asegurarse de espacio en disco y permisos; procesar en background si el volumen es grande.
- Verificar que `Imprimir::certificadoPdf` no permite path traversal.


### borrarcursoAction

- Propósito: borrar un curso y sincronizar el borrado con WordPress si está sincronizado.
- Ruta: `borrarcursoAction`.
- Roles: no explícito en la función; la acción puede devolver códigos (525 OK, 536 dependencias)

Flujo detallado:
1. Lee `v1` desde la ruta (id del curso) y crea `Curso($id)`.
2. Si el curso tiene `post_id > 0` (sincronizado con WP) prepara `data = $object->getMappingWP('remove')`.
3. Llama a `object->remove()` que devuelve un código de resultado.
4. Si había `post_id` y `ok == 525` (borrado OK) envía petición a WP vía `apiClientRest->send()` para eliminar la entrada en WP.
5. Redirige al listado de cursos con `v2 = $ok` y mantiene la página guardada en sesión.

Efectos secundarios: borrado del registro en BD; posible petición HTTP a WordPress.

Seguridad/recomendaciones:
- Revisar la política de borrado (soft delete vs hard delete) y cascadas en la entidad Curso.


### sincronizacursoAction

- Propósito: sincronizar curso con WordPress mediante API REST y actualizar metadatos de sincronización.
- Ruta: `sincronizacursoAction`.

Flujo detallado:
1. Lee `v1` para id del curso y carga `Curso($id)`.
2. Comprueba si la categoría asociada tiene `taxonomy_id`; si no, crea la categoría en WP y actualiza la fila local con `taxonomy_id` si WP devolvió id.
3. Prepara mapping (`$curso->getMappingWP('create')`) y envía a WP con `apiClientRest->send()`.
4. Guarda la respuesta en sesión (`_container->respuestaSincronizacionCurso`) para mostrar en UI.
5. Si WP devolvió `id` y el curso no tenía `post_id`, actualiza `post_id` y `publicacion` en BD y fija resultado `100 (OK)`; si no, `101`.
6. Actualiza la fila del curso y redirige a la ficha del curso con el resultado en `v3`.

Efectos secundarios: llamadas a API externas, actualizaciones en BD.

Seguridad/recomendaciones:
- Manejar errores de red/timeout y autenticar peticiones a WP; registrar respuestas de error para depuración.


### xlscursosAction

- Propósito: exportar los cursos filtrados a Excel (XLS)
- Ruta: `xlscursosAction`.

Flujo detallado:
1. Construye `where` usando `Utilidades::generaCondicion('cursos', $this->_container->curs_buscador)`.
2. Recupera objetos con `Cursos::get(where, 'nombre')` y delega la creación del PHPExcel en `Exportar::cursos($objects)`.
3. Envía headers HTTP apropiados para forzar descarga de Excel y usa `PHPExcel_IOFactory::createWriter(... 'Excel5')` para volcar a `php://output`.
4. Llama a `die('Excel generado')` y `exit` para terminar la ejecución.

Efectos secundarios: volcado directo a output y terminación del script.

Seguridad/recomendaciones:
- Reemplazar die()/exit por un StreamResponse para testabilidad y control sobre la respuesta.
- Controlar límites de memoria/tiempo si la exportación es grande.


### inscripcionesAction

- Propósito: listar inscripciones con filtros y paginación.
- Ruta: `inscripcionesAction`.

Flujo detallado:
1. Establece título, lee `v2` para códigos de mensajes y prepara `Inscripciones()`.
2. Si POST: recoge filtros (id_ins, id_usu, id_cur, fechas, tipo, estado, pago), sanea con `StripTags`, guarda en sesión y resetea paginación.
3. Paginación: lectura/almacenamiento de `v1` o valor en sesión; calcula offset.
4. Construcción de WHERE con `Utilidades::generaCondicion('inscripciones', filtros)` si hay filtros en sesión.
5. Recupera datos con `getInscripciones` y `numInscripciones`.
6. Prepara ViewModel con usuario, buscador, pagina, mensajes, número e inscripciones.

Efectos secundarios: lectura DB; modificación de la sesión.

Seguridad/recomendaciones:
- Revisar `Utilidades::generaCondicion` para parametrización.


### inscripcionAction

- Propósito: crear o editar una inscripción, gestionar sus relaciones (inscritos, participantes, justificantes).
- Ruta: `inscripcionAction`.
- Roles: existen comprobaciones internas condicionadas por `v3` y el rol del usuario (`$this->_usuario->get('rol') == '4dmin'`) para acciones como marcar cobrado, aceptar, rechazar y enviar notificaciones.

Flujo detallado:
1. Establece título y variables de mensajes; si POST:
   - Recoge multitud de campos (id_ins, fecha, ids, importes, datos de usuario y empresa, justificante, estado, pago, etc.) saneando con `StripTags` donde procede.
   - Crea `Inscripcion(0)` y llama `set($data)` para validar; si OK llama a `save()` y crea/actualiza la entrada.
2. Si no es POST, carga la inscripción por `v1`.
3. Interpreta `v3` para acciones administrativas (si rol == '4dmin'):
   - `idm == 2`: marcar como cobrado y enviar notificación de cobro.
   - `idm == 3`: aceptar inscripción y notificar.
   - `idm == 4`: rechazar inscripción.
   - `idm == 5`: enviar aviso de inscripción incompleta.
   - Otros idm: control de mensajes y pestañas.
4. Si la inscripción existe, carga inscritos (paginated), participantes (paginated), evita duplicados al mostrar listas, y carga menores/trabajadores disponibles para añadir.
5. Prepara ViewModel con todos los datos: inscrito, curso, creador, empresa, listas y paginaciones.

Efectos secundarios: creación/actualización en BD, envío de notificaciones si se solicita.

Seguridad/recomendaciones:
- Validar permisos para las acciones en `v3` (solo administradores deben poder cambiar estados y disparar envíos).
- Usar CSRF tokens en formularios de modificación.


### borrarinscripcionAction

- Propósito: eliminar una inscripción y redirigir según contexto (lista o ficha de usuario)
- Ruta: `borrarinscripcionAction`.

Flujo detallado:
1. Lee `v1` (id_ins) y `v2` (urlRedirect) de la ruta.
2. Crea `Inscripcion($id)` y llama a `remove()` en la entidad; `remove()` devuelve un código (`$ok`).
3. Si `urlRedirect > 0`, redirige a la ficha del usuario asociado con `v6 = $ok`; si no, redirige al listado de inscripciones con `v2 = $ok`.

Efectos secundarios: borrado (delegado a la entidad), posibles cascadas o comprobaciones internas en la entidad.

Seguridad/recomendaciones:
- `remove()` debe comprobar permisos y prevenir borrados accidentales (confirmación/soft delete si procede).


### inscripcionjustificanteAction

- Propósito: subir o borrar el justificante de pago asociado a una inscripción.
- Ruta: `inscripcionjustificanteAction`.

Flujo detallado:
1. Inicializa `$msg = 550` (error por defecto).
2. Si POST:
   - Lee `id_ins` y crea `Inscripcion($id_ins)`.
   - Determina `FILE_DIRECTORY_JUSTIFICANTE` desde la entidad.
   - Prepara adaptador HTTP y validadores (tamaño hasta 10MB, extensiones permitidas: pdf, doc, docx, jpg, png, jpeg).
   - Valida el fichero; si OK renombra con timestamp, mueve al destino, llama a `inscripcion->setJustificante($nombre)` y fija `$msg = 548`.
   - Si falla validación, `$msg = 549`.
3. Si no es POST: interpreta como borrado, llama a `inscripcion->removeJustificante()` y `$msg = 551`.
4. Redirige a la ficha de inscripción con `v3 = $msg`.

Efectos secundarios: almacenamiento/borrado de ficheros y actualización de la entidad Inscripcion.

Seguridad/recomendaciones:
- Validar MIME además de extensión; sanitizar nombres; controlar permisos y evitar path traversal.


### xlsinscripcionesAction

- Propósito: exportar inscripciones filtradas a Excel (XLS)
- Ruta: `xlsinscripcionesAction`.

Flujo detallado:
1. Construye `where` con `Utilidades::generaCondicion('inscripciones', filtros)`.
2. Recupera datos con `Inscripciones::getInscripciones(where, ...)`.
3. Genera el objeto PHPExcel con `Exportar::inscripciones(objects)`.
4. Envía headers y escribe el Excel a `php://output` y termina ejecución con `die()`/`exit`.

Efectos secundarios: descarga forzada de fichero y terminación del script.

Seguridad/recomendaciones:
- Preferible devolver un StreamResponse en lugar de `die()` para mejorar testabilidad.


### xlsinscritosAction

- Propósito: exportar inscritos a Excel. Soporta exportar inscritos de un curso concreto (si `v1` dado) o por filtros en sesión.
- Ruta: `xlsinscritosAction`.

Flujo detallado:
1. Si `v1` (id_cur) presente construye `where = 'id_cur = ' . id_cur`; si no usa filtros de sesión.
2. Recupera inscritos con `getInscritos`.
3. Llama `Exportar::inscritos(objects)`, envía headers y salva en output usando PHPExcel, y termina con `die()`/`exit`.

Efectos secundarios: volcado directo a output.

Seguridad/recomendaciones:
- Ver comentarios en `xlscursosAction`.


### xlsparticipantesAction

- Propósito: exportar participantes filtrados a Excel.
- Ruta: `xlsparticipantesAction`.

Flujo detallado:
1. Recupera `where` desde filtros `participantes` en sesión.
2. Llama a `Inscripciones::getParticipantes(where, ...)` y a `Exportar::participantes(objects)`.
3. Envía headers, escribe el Excel y termina con `die()`/`exit`.

Seguridad/recomendaciones:
- Igual que para otras exportaciones.


### categoriasAction

- Propósito: listar y editar categorías (búsqueda, editar todas, editar fila individual).
- Ruta: `categoriasAction`.

Flujo detallado:
1. Establece título y códigos de mensaje.
2. Si POST:
   - Si `boton == 'buscar'` guarda filtro `id_cat` en sesión.
   - Si `boton == 'guardar-todos'` itera arrays y actualiza cada categoría via `Categoria::set()` y `save()`.
   - Si el botón es un índice, guarda solo esa fila.
3. Si no POST, permite limpieza de filtros si `v2 == 114`.
4. Paginación y construcción de WHERE mediante `Utilidades::generaCondicion('categorias', filtros)`.
5. Recupera categorías y prepara ViewModel.

Efectos secundarios: actualizaciones en BD para categorías.

Seguridad/recomendaciones:
- Validar y sanear nombres; verificar integridad antes de actualizar taxonomy_id.


### borrarcategoriaAction

- Propósito: borrar una categoría y redirigir al listado, delegando la operación a la entidad.
- Ruta: `borrarcategoriaAction`.

Flujo detallado:
1. Lee `v1` como id_cat, carga `Categoria($id)` y llama `remove()`.
2. Redirige a `categorias` con `v2 = $ok` y mantiene página desde sesión.

Efectos secundarios: borrado en BD; `remove()` puede devolver distintos códigos (525, 536).

Seguridad/recomendaciones:
- Revisar `remove()` para gestión de dependencias (cursos asociados).


### inscritosAction

- Propósito: listar inscritos; para admin muestra todos, para usuarios normales solo sus registros.
- Ruta: `inscritosAction`.

Flujo detallado:
1. Título según rol (admin vs usuario).
2. Lectura/guardado de filtros via POST; paginación.
3. Construcción de WHERE y, para usuarios no-admin, añade restricción `(id_usu = X OR id_cre = X)` concatenada.
4. Recupera inscritos y cuenta; para usuarios no-admin también añade participantes y suma totales.
5. Prepara ViewModel.

Seguridad/recomendaciones:
- La concatenación de IDs en WHERE es práctica del proyecto pero debería parametrizarse.


### participantesAction

- Propósito: listar participantes (menores) con filtros y paginación.
- Ruta: `participantesAction`.

Flujo detallado:
1. Preparar título y mensajes.
2. Si POST: recoger filtros, guardarlos en sesión y resetear paginación.
3. Paginación y cálculo de offset.
4. Construir WHERE con `Utilidades::generaCondicion('participantes', filtros)` si aplica.
5. Recuperar participantes y número; preparar ViewModel.

Seguridad/recomendaciones:
- Revisar `generaCondicion` para inmune a inyección.


### borrarinscritoAction

- Propósito: borrar un Inscrito por id y redirigir al listado.
- Ruta: `borrarinscritoAction`.

Flujo detallado:
1. Lee `v1` (id_ui), crea `Inscrito($id)` y llama `remove()`.
2. Redirige a `inscritos` con `v2 = $ok`.


### borrarparticipanteAction

- Propósito: borrar un Participante (menor) y redirigir a la lista de participantes.
- Ruta: `borrarparticipanteAction`.

Flujo detallado:
1. Leer `v1` (id_par) y cargar `Participante($id)`.
2. Llamar `remove()` en la entidad y almacenar código de resultado.
3. Redirigir a `participantes` con `v2 = $ok`.

Seguridad/recomendaciones:
- Verificar que `remove()` gestiona integridad referencial.


### enviaremailpagoAction

- Propósito: enviar un email al creador de la inscripción con un enlace de pago si la inscripción tiene importe > 0.
- Ruta: `enviaremailpagoAction`.

Flujo detallado:
1. Leer `v1` (id_ins) y cargar `Inscripcion`, `Usuario` (creador) y `Curso`.
2. Validaciones: inscripción existente, usuario existente, curso existente; si alguna falla, setea códigos 400/401/402.
3. Si `importe > 0`:
   - Construye `baseUrl` a partir del Request y `getBasePath()`.
   - Carga plantilla con `Utilidades::getMessageEmailPayment()`.
   - Reemplaza marcadores `[tipo-curso]`, `[nombre-curso]`, `[user-name]` y crea enlace de pago con `Utilidades::encriptaIdCurso`.
   - Prepara destinatarios (creador) y llama a `$this->sendMail->sendMail()`.
   - Interpreta resultado: 200 (éxito) o 404 (fallo envío).
4. Si `importe <= 0` devuelve 403.
5. Redirige a la ficha de inscripción con `v3 = $msg`.

Seguridad/recomendaciones:
- Asegurarse de que `encriptaIdCurso` no expone datos sensibles y que la URL de pago es segura.


### agregaparticipantesAction

- Propósito: añadir trabajadores (usuarios) seleccionados como inscritos en una inscripción.
- Ruta: `agregaparticipantesAction`.

Flujo detallado:
1. Si POST: leer `id_ins` y cargar `Inscripcion`.
2. Leer array `id_usu` donde las claves son ids seleccionados; convertir a ints.
3. Validar que haya selección; si no, `msg = 400`.
4. Para cada id_tra:
   - Cargar `Usuario(id_tra)` para leer `sitcol` y `sitlab`.
   - Calcular `importe` según `sitcol` (precio_col o precio_otr del curso).
   - Preparar `data_inscrito` y crear `Inscrito(0)`, `set()` y `save()`.
5. Llamar `inscripcion->revisaImporte()` para recalcular totales.
6. `msg = 401` y redirigir a ficha de inscripción.

Seguridad/recomendaciones:
- Validar permisos: ¿quién puede añadir participantes? Verificar CSRF.


### agregamenoresAction

- Propósito: añadir menores seleccionados como participantes a una inscripción.
- Ruta: `agregamenoresAction`.

Flujo detallado:
1. Si POST: leer `id_ins`, cargar `Inscripcion`.
2. Leer array `id_men`, convertir claves a ints y validar no vacío (500 si vacío).
3. Para cada menor:
   - Cargar `Menor(id_men)` si es necesario.
   - Calcular `importe` (generalmente tarifa no colegiado).
   - Preparar `data_participante`, crear `Participante(0)`, `set()` y `save()`.
4. Llamar `inscripcion->revisaImporte()` y `msg = 501`.
5. Redirigir a ficha de inscripción.

Seguridad/recomendaciones:
- Validar que los menores pertenecen a la empresa/usuario correspondiente o que el usuario tiene permisos para añadirlos.


### asistenciaAction

- Propósito: marcar asistencia de los inscritos seleccionados (marcar `asistencia = 1`).
- Ruta: `asistenciaAction`.

Flujo detallado:
1. Si POST: leer `id_cur` y `asistencia` (array con claves = ids de Inscrito).
2. Convertir las claves a ints y validar no vacío (500 si vacío).
3. Para cada id_ins crear `Inscrito(id_ins)` y llamar a `setAsistencia(1)`.
4. `msg = 501` y redirigir a la ficha del curso.

Seguridad/recomendaciones:
- Considerar condiciones de carrera si múltiples usuarios marcan asistencia; implementar control de concurrencia si es necesario.


### certificadoAction

- Propósito: subir o borrar el certificado (diploma) de un Inscrito.
- Ruta: `certificadoAction`.

Flujo detallado:
1. Inicializa `msg = 550`.
2. Si POST:
   - Leer `id_cur` e `id_ins` y cargar `Inscrito`.
   - Determinar `FILE_DIRECTORY_DIPLOMA` desde la entidad.
   - Preparar `Zend\File\Transfer\Adapter\Http` y validadores (max 10MB, extensiones pdf/doc/docx).
   - Comprobar `isValid()`. Si OK construir nombre único (timestamp + extensión), crear directorio si no existe (0750), renombrar y mover el fichero, y llamar a `inscrito->setDiploma($nombre)`; `msg = 548`.
   - Si validación falla `msg = 549`.
3. Si no es POST:
   - Leer `v1` (id_cur) y `v2` (id_ins) de la ruta, cargar `Inscrito` y llamar a `removeDiploma()`; `msg = 551`.
4. Redirigir a la ficha del curso con `v3 = $msg`.

Seguridad/recomendaciones:
- Validar MIME-type y escanear ficheros si procede. Establecer permisos de fichero y evitar path traversal.


### xaAction

- Propósito: endpoint AJAX multiuso para autocompletes/select2 (cursos, inscripciones, categorías).
- Ruta: `xaAction` con `v1` que indica el tipo de búsqueda. Recibe `q` (query) por query-string.
- Casos soportados: 1..6 (buscar curso por nombre, curso por id, buscar inscripciones por nombre/apellidos, inscripcion por id, categorias por nombre con filtro formacion/empleo, categoria por id).

Flujo detallado resumido:
1. Lee `v1` para saber tipo de búsqueda.
2. Lee `q` desde query (usa `fromQuery('q')` en la versión documentada).
3. En función de `v1` realiza consultas en la capa de datos y construye un array `$answer` con objetos `{id, text}`.
4. Devuelve JSON con `Json::encode($answer)`.

Notas de seguridad importantes:
- Actualmente el código original usaba concatenaciones con `LIKE "%$term%"` que puede ser vulnerable a inyección si la capa de datos no lo parametriza.
- Se detectó uso directo de `$_GET['q']` en versiones antiguas; la versión documentada usa `fromQuery('q')` para coherencia.
- El texto devuelto debe escaparse/validarse en el cliente para evitar XSS.

---

Notas finales y recomendaciones globales

1. Parametrización de consultas: el uso repetido de `Utilidades::generaCondicion` sugiere que se construyen cláusulas SQL por concatenación. Revisar que la función internamente parametriza o sanitiza correctamente. Ideal: usar consultas preparadas (PDO con parámetros) o el mecanismo de la capa de datos del framework.

2. Reemplazar die()/exit en exportaciones: las acciones `xlscursosAction`, `xlsinscripcionesAction`, `xlsinscritosAction` y `xlsparticipantesAction` terminan la ejecución con `die()`/`exit` tras escribir el Excel. Es preferible devolver un StreamResponse o Response con el cuerpo ya preparado para mejorar testabilidad y control.

3. Validación de ficheros: además de validar extensiones, validar MIME type y aplicar escaneo/limpieza si el sistema lo requiere (antivirus o proxys de seguridad).

4. Protección de acciones críticas: algunas acciones ejecutan envíos de correo, sincronizaciones con WP y borrados. Asegurar que solo roles autorizados pueden invocarlas (comprobar configuración de rutas o agregar checks basados en `$this->_usuario->get('rol')`).

5. CSRF y permisos: los formularios de creación/edición y las operaciones que mutan estado deben protegerse con tokens CSRF y validación de permisos.


Fin del documento.
