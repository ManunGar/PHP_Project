# Documentación: EmpleoController (module/Backend)

Índice

- [Resumen general](#resumen-general)
- [indexAction](#indexaction)
- [ofertasAction](#ofertasaction)
- [ofertaAction](#ofertaaction)
- [ofertasempleoAction](#ofertasempleoaction)
- [presentarcandidaturaAction](#presentarcandidaturaaction)
- [borrarofertaAction](#borrarofertaaction)
- [xlsofertasAction](#xlsofertasaction)
- [candidaturasAction](#candidaturasaction)
- [candidaturaAction](#candidaturaaction)
- [borrarcandidaturaAction](#borrarcandidaturaaction)
- [xlscandidaturasAction](#xlscandidaturasaction)
- [sectoresAction](#sectoresaction)
- [borrarsectorAction](#borrarsectoraction)
- [xaAction](#xaaction)
- [Notas de seguridad y recomendaciones](#notas-de-seguridad-y-recomendaciones)

---

## Resumen general

El `EmpleoController` del módulo `Backend` gestiona ofertas de empleo, candidaturas y sectores desde la zona de administración. Sus acciones siguen la convención de Zend Framework: cada método terminado en `Action` puede mapearse a una ruta (en este proyecto se usa `backend/default` con `controller => 'empleo'` y `action => <nombre>` en muchos redireccionamientos).

En este documento cada sección detalla:
- Para qué sirve la función.
- Ruta de ejemplo (si aplica) y cómo suele invocarse.
- Restricciones de rol (si las hay).
- Desarrollo detallado del flujo de la función paso a paso (equivalente a una lectura línea a línea, pero explicada en lenguaje natural).

---

## indexAction

- Propósito: Redirige a la lista de ofertas (`ofertasAction`).
- Ruta de ejemplo: `/backend/empleo/index` (habitualmente `backend/default` con `controller=empleo` y `action=index`).
- Restricción de roles: no aplica explícitamente en esta acción (la redirección no comprueba permisos aquí).

Flujo detallado:
1. Llama a `$this->redirect()->toRoute('backend/default', [...])` construyendo una respuesta RedirectResponse.
2. Los parámetros enviados en la redirección son: `controller => 'empleo'`, `action => 'ofertas'`, `v1 => 1`.
3. El navegador / cliente recibirá la redirección y ejecutará la acción `ofertasAction` con `v1=1`.

Comentarios: Acción simple que delega la responsabilidad de mostrar la lista a `ofertasAction`.

---

## ofertasAction

- Propósito: Listar y filtrar ofertas de empleo desde el área de administración.
- Ruta de ejemplo: `/backend/empleo/ofertas`.
- Restricción de roles: no hay un chequeo directo al inicio; la función sirve tanto para administradores como para usuarios con rol empresarial, pero más adelante la lógica de edición/comprobación de permisos ocurre en `ofertaAction`.

Flujo detallado:
1. Ajusta el título del layout a "Ofertas" (`$this->layout()->title = 'Ofertas'`).
2. Lee `v2` de la ruta como `$idm` para interpretar códigos de resultado (por ejemplo, 525 = borrado correcto, 536 = fallo por dependencias). Inicializa `$msg_ok` y `$msg_error`.
3. Crea la instancia `$db_ofertas = new Ofertas()` para operar sobre la colección de ofertas y define `$orderby = 'id_ofe DESC'`.
4. Si la petición es POST:
   - Recoge campos del formulario (id_ofe, titulo, estado, fechaDesde, fechaHasta, id_emp, id_sec).
   - Usa `StripTags` para limpiar texto donde procede.
   - Guarda el array `$data` en la sesión `$this->_container->ofer_buscador` para persistir filtros al paginar.
   - Reinicia la página en sesión (`ofer_page = 0`).
5. Si no es POST y `v2 == 114`, elimina filtros en sesión (`unset($this->_container->ofer_buscador)`) y resetea página.
6. Determina la página actual:
   - Toma `v1` de la ruta; si no se pasa (`-1`) intenta usar `$this->_container->ofer_page` o inicializar a 1.
   - Guarda / actualiza `$this->_container->ofer_page`.
   - Calcula `$offset = 50 * ($page - 1)` para paginación.
7. Construye la condición WHERE:
   - Si hay `$this->_container->ofer_buscador`, llama a `Utilidades::generaCondicion('ofertas',$filtros)` para obtener `$where`.
   - Guarda `$where` concatenado con el orden en la sesión para depuración.
   - Si no hay filtros, define `$buscador` con valores por defecto y `$where = null`.
8. Consulta la base de datos: `$ofertas = $db_ofertas->getOfertas($where,$orderby,50,$offset)` y obtiene el número total con `numOfertas($where)`.
9. Si no hay resultados, establece mensajes en `$msg_error` dependiendo si se buscó o no.
10. Prepara y devuelve un `ViewModel` con: usuario, buscador, page, ok/ko (mensajes), num y ofertas.

Observaciones:
- Los filtros se guardan en sesión para mantener estado al navegar. La generación de la condición WHERE delega en `Utilidades::generaCondicion` (revisar ese método para detectar sanitización/parametrización).
- La paginación usa página fija de 50 items.

---

## ofertaAction

- Propósito: Crear o editar una oferta; mostrar detalle de oferta y sus candidaturas.
- Ruta de ejemplo: `/backend/empleo/oferta[/{v1}]` (v1 puede contener el id de la oferta si se edita).
- Restricción de roles: se aplica control de permisos si el usuario no es admin — si intenta editar una oferta de otra empresa sin autorización, se redirige a `ofertasempleoAction`.

Flujo detallado:
1. Ajusta título del layout a "Nueva | Oferta" por defecto.
2. Inicializa variables de mensaje (`$msg_ok`, `$msg_error`) y variables auxiliares (`$tab`, `$set`).
3. Si la petición es POST (guardado del formulario):
   - Limpia y recoge campos con `StripTags` y casteos (`id_ofe`, `id_emp` según rol, `titulo`, `descripcion`, `info`, `plazas`, `categoria`, `experiencia`, `estado`, `fecha`, `id_usu`).
   - Si el usuario no es administrador, forza `id_emp` a la empresa del usuario.
   - Crea una entidad `Oferta(0)`, llama a `set($data)` y si retorna > 0 significa que faltan campos obligatorios -> mensaje de error.
   - Si `set` es correcto, llama a `save()` en la entidad; si `id_ofe` era 0 muestra mensaje de creación, si no de actualización.
4. Si no es POST (mostrar formulario / detalle):
   - Lee `v1` de la ruta como id; crea `new Oferta($id)`.
   - Lee `v3` para interpretar mensajes de estado (p.ej. publicar/rechazar) y actualizar el estado en memoria.
5. Control de permisos para edición: si el usuario no es admin y la oferta existe y no pertenece a su empresa (o su cuenta no está autorizada) se redirige a `ofertasempleoAction`.
6. Define `$empresa` para mostrar en la vista (la empresa del usuario si no es admin).
7. Si `id > 0` (oferta existente):
   - Ajusta el título del layout con el título de la oferta.
   - Gestiona paginación de candidaturas asociadas (`v2` usado como página de candidaturas en la vista).
   - Carga candidaturas con `$db_can->getCandidaturas('id_ofe = '.$id, 'id_ofe DESC', 50, $offsetc)` y cuenta con `$db_can->num('id_ofe = '.$id)`.
8. Prepara y devuelve ViewModel con oferta, candidaturas, empresa, mensajes y pestaña activa.

Notas:
- El guardado delega gran parte de la validación/persistencia a la entidad `Oferta`.
- La verificación de permisos evita edición no autorizada.

---

## ofertasempleoAction

- Propósito: Listado público de ofertas orientado a candidatos (muestra sólo ofertas publicadas `estado = 1`).
- Ruta de ejemplo: `/backend/empleo/ofertasempleo`.
- Restricción de roles: no explícita; esta vista es la que usan candidatos/usuarios para postular.

Flujo detallado:
1. Ajusta título del layout a 'Ofertas de empleo'.
2. Lee parámetros de ruta: `v1` (oferta seleccionada), `v2` (código de mensaje para mostrar notificaciones como errores o éxito al presentar candidatura).
3. Interpreta `v2` para mensajes (100,101,200 se usan para fallos/éxitos al presentar candidatura).
4. Prepara `$db_ofertas = new Ofertas()` y condiciones: orden por `fecha DESC`, y `where = 'estado = 1'` para mostrar sólo ofertas publicadas.
5. Si la petición es POST recoge filtros (actualmente `categoria`) y guarda en sesión `$this->_container->ofer_empleo_buscador`.
6. Si se solicita limpiar filtros (`v2 == 114`) elimina el buscador en sesión.
7. Si existen filtros en sesión, concatena condiciones al WHERE (p.ej. `AND categoria = X`) y deja la condición en sesión para depuración.
8. Obtiene ofertas mediante `getOfertas($where,$orderby)` y cuenta con `numOfertas($where)`.
9. Carga candidaturas del usuario autenticado `$db_candidaturas->getCandidaturas('id_usu = ' . $this->_usuario->get('id'))` y construye un array simple con los ids de ofertas donde el usuario ya ha aplicado.
10. Prepara mensajes cuando no hay resultados y devuelve ViewModel con datos para la plantilla.

Observaciones:
- El buscador está limitado (por ahora) a categoría.
- La vista muestra la marca de las ofertas en las que el usuario ya se postuló, usando las candidaturas recuperadas.

---

## presentarcandidaturaAction

- Propósito: Procesar la presentación de una candidatura desde el formulario público (subida de CV y datos del candidato).
- Ruta de ejemplo: se invoca desde el formulario público de `ofertasempleoAction` (POST).
- Restricción de roles: no explícita; el flujo asume que el usuario autenticado tiene un id (se usa `$this->_usuario->get('id')`).

Flujo detallado:
1. Inicializa `$ok = 100` (código por defecto que indica error genérico).
2. Comprueba que la petición sea POST.
3. Crea `new Candidatura(0)` y obtiene el directorio destino `Candidatura::FILE_DIRECTORY_CV` para guardar CVs.
4. Prepara un adaptador `Zend\File\Transfer\Adapter\Http` y asigna validadores para tamaño (max 10MB) y extensión (`pdf`, `doc`, `docx`).
5. Obtiene la información del fichero enviado en `cv` y comprueba la validez con `$httpadapter->isValid()`.
6. Si el fichero es válido:
   - Genera un nombre único para el archivo (timestamp + extensión).
   - Añade filtro `Rename` y fija `setDestination` al directorio.
   - Llama a `$httpadapter->receive(...)` para mover el temporal al destino.
   - Si la recepción es correcta, construye `$data` con `id_can`, `id_usu` (usuario autenticado), `id_ofe` (post del form), `comentario` (limpiado), `fecha` (now), `cv` (nombre de fichero), `estado` = 1.
   - Llama a `$candidatura->set($data, 2)`; si devuelve 0 guarda con `$candidatura->save()` y asigna `$ok = 200`.
7. Si validación del fichero falla asigna `$ok = 101`.
8. Al final redirige a `ofertasempleo` pasando `v2 => $ok` para que la vista muestre el resultado.

Observaciones:
- La validación del fichero se realiza por el adaptador y validadores de Zend.
- Se asume que el directorio destino existe y tiene permisos de escritura.
- El uso de `time()` para renombrar es simple y efectivo, pero puede colisionar en escenarios de concurrencia alta; usar un identificador más robusto (ej. uniqid + random) sería más seguro.

---

## borrarofertaAction

- Propósito: Eliminar una oferta por id y redirigir al listado de ofertas.
- Ruta de ejemplo: `/backend/empleo/borraroferta/{v1}` donde `v1` es el id.
- Restricción de roles: no hay verificación explícita en esta acción; la entidad `Oferta->remove()` puede a su vez validar permisos o dependencias.

Flujo detallado:
1. Lee `v1` de la ruta y lo castea a entero (`$id`).
2. Crea la entidad `new Oferta($id)`.
3. Invoca `$object->remove()`; espera un código `$ok` que indique resultado (por convención 525 = OK, 536 = fallo por dependencias).
4. Redirige a `ofertas` incluyendo la página actual en sesión (`ofer_page`) y `v2 => $ok` para que la lista muestre mensajes.

Notas:
- La función no intenta borrar manualmente relaciones: confía en la entidad para manejar dependencias.

---

## xlsofertasAction

- Propósito: Exportar a Excel las ofertas resultantes de la búsqueda actual.
- Ruta de ejemplo: `/backend/empleo/xlsofertas` (invocada desde la UI de administración).
- Restricción de roles: no explícita aquí; normalmente se accede desde el área de administración.

Flujo detallado:
1. Genera la condición WHERE a partir del buscador en sesión: `Utilidades::generaCondicion('ofertas', $this->_container->ofer_buscador)`.
2. Obtiene las ofertas que cumplen la condición con `Ofertas->getOfertas($where,'titulo')`.
3. Llama a la utilidad `Exportar::ofertas($objects)` que devuelve un objeto PHPExcel preparado.
4. Envía headers HTTP (Content-type, Content-Disposition, Pragma, Expires) para forzar la descarga como `.xls`.
5. Crea un writer `PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5')` y escribe a `php://output`.
6. Finaliza la ejecución con `die('Excel generado'); exit;` para evitar que el framework intente renderizar una vista.

Observaciones:
- El uso de `die()`/`exit` es práctico pero interrumpe el ciclo normal del framework; es aceptable para descargas pero se debe documentar.
- Si se migrase a PHPSpreadsheet sería aconsejable actualizar la utilidad `Exportar`.

---

## candidaturasAction

- Propósito: Listar y filtrar candidaturas desde el área de administración.
- Ruta de ejemplo: `/backend/empleo/candidaturas`.
- Restricción de roles: si el usuario tiene rol `c4legiado`, la lista se restringe a las candidaturas del propio usuario (ver detalle en el flujo).

Flujo detallado:
1. Ajusta título del layout a 'Candidaturas'.
2. Lee `v2` como `$idm` para interpretar mensajes (por ejemplo 525 o 536) y prepara variables de mensaje.
3. Crea `$db_candidaturas = new Candidaturas()` y define `$orderby = 'usuariosNombre ASC'`.
4. Si la petición es POST:
   - Recoge filtros (id_can, id_usu, id_ofe, id_emp, fechaDesde, fechaHasta, candidaturasEstado) limpiando/convirtiendo según necesidad.
   - Guarda filtros en sesión `$this->_container->cand_buscador` y reinicia la página en sesión (`cand_page = 0`).
5. Si no es POST y `idm == 114`, limpia los filtros en sesión.
6. Determina página actual (usa `v1` o valor guardado en sesión) y calcula offset (50 items por página).
7. Construye `$where` con `Utilidades::generaCondicion('candidaturas',$filtros)` si hay filtros; guarda la condición en sesión para depuración.
8. Restricción por rol: si el usuario es `c4legiado`, concatena `AND id_usu = <id_usuario>` al WHERE para que vea solo sus candidaturas.
9. Llama a `getCandidaturas($where,$orderby,50,$offset)` y `numCandidaturas($where)`.
10. Si no hay resultados, prepara mensaje de error.
11. Devuelve ViewModel con datos para la plantilla.

Notas:
- La restricción por rol se aplica construyendo condiciones SQL; conviene validar que la generación de la condición escape correctamente los valores.

---

## candidaturaAction

- Propósito: Crear/editar una candidatura y mostrar su detalle.
- Ruta de ejemplo: `/backend/empleo/candidatura[/{v1}]`.
- Restricción de roles: hay control de acceso para editar/ver:
  - Los administradores (`4dmin`) pueden ver/editar en general.
  - Usuarios no administradores sólo pueden acceder si pertenecen a la empresa dueña de la oferta y están autorizados, o si son el propio candidato con rol `c4legiado`.

Flujo detallado:
1. Ajusta título por defecto a 'Nueva | Candidatura'.
2. Si es POST: recoge campos (`id_can`, `id_usu`, `id_ofe`, `comentario`, `fecha`, `estado`) limpiando con `StripTags` y casteando donde corresponde.
   - Crea `new Candidatura(0)`, llama a `set($data)` y si faltan campos muestra error; si no `save()` y prepara mensaje de creación/actualización.
3. Si no es POST: obtiene `v1` (id) y carga `Candidatura($id)`.
   - Lee `v2` para interpretar códigos de estado (preseleccionada, seleccionada, descartada) y aplica `setEstado` en memoria.
4. Obtiene la oferta asociada `$oferta = $candidatura->get('oferta')`.
5. Control de permisos: si el usuario no es admin, calcula `$allow`:
   - Permite si la candidatura existe y la empresa de la oferta coincide con la del usuario y además `autorizado == 1`.
   - O permite si el usuario tiene rol `c4legiado` y es el candidato (su id coincide con `id_usu` de la candidatura).
   - Si no se cumple, redirige al listado `candidaturas`.
6. Si se está editando (`id > 0`) ajusta el título con el id formateado.
7. Devuelve ViewModel con candidatura, cliente (candidato), oferta y mensajes.

---

## borrarcandidaturaAction

- Propósito: Borrar una candidatura por id y redirigir al listado.
- Ruta de ejemplo: `/backend/empleo/borrarcandidatura/{v1}`.
- Restricción de roles: no verificada explícitamente aquí; se delega en la entidad (si procede).

Flujo detallado:
1. Lee `v1` como `$id` (int), crea `new Candidatura($id)`.
2. Llama a `$object->remove()` que devuelve un código `$ok` con resultado.
3. Redirige a `candidaturas` incluyendo la página actual en sesión (`cand_page`) y `v2 => $ok`.

---

## xlscandidaturasAction

- Propósito: Exportar a Excel las candidaturas filtradas.
- Ruta: `/backend/empleo/xlscandidaturas`.
- Restricción de roles: normalmente admin (área de administración).

Flujo detallado:
1. Construye `$where` con `Utilidades::generaCondicion('candidaturas', $this->_container->cand_buscador)`.
2. Obtiene candidaturas: `$objects = $db->getCandidaturas($where, ['usuariosNombre','apellidos'])`.
3. Llama a `Exportar::candidaturas($objects)` para construir PHPExcel.
4. Envía headers para forzar descarga `.xls`.
5. Crea writer Excel5 y escribe a `php://output`.
6. Termina ejecución con `die('Excel generado'); exit;`.

---

## sectoresAction

- Propósito: Administrar sectores (buscar, editar, guardar múltiples, paginar).
- Ruta: `/backend/empleo/sectores`.
- Restricción de roles: no explícita; accesible desde backend.

Flujo detallado:
1. Ajusta layout title a 'Sectores'.
2. Lee `v2` (codigos de mensaje: 525 borrado correcto, 536 fallo por relaciones) y prepara mensajes.
3. Instancia `new Sectores()` y define orden `nombre ASC`.
4. Si la petición es POST gestiona distintos botones:
   - `buscar`: guarda filtros (`id_sec`) en sesión.
   - `guardar-todos`: recorre arrays `id_sec` y `nombre` y para cada uno no vacío crea/actualiza `Sector` y lo guarda.
   - else: trata el botón como índice `i` para actualizar un único sector por posici F3n.
   - Mantiene mensajes de éxito según operación.
5. Si no es POST y `v2 == 114` limpia filtros en sesión.
6. Calcula paginación (v1 o valor en sesión) y offset.
7. Construye `$where` con `Utilidades::generaCondicion('sectores', $this->_container->sec_buscador)` si existe buscador; guarda la condición en sesión.
8. Obtiene objetos con `$db->get($where, $orderby, 50, $offset)` y cuenta con `$db->num($where)`.
9. Si no hay resultados prepara mensajes y devuelve ViewModel con lista, buscador, page y mensajes.

---

## borrarsectorAction

- Propósito: Borrar un sector por id y redirigir a la lista.
- Ruta: `/backend/empleo/borrarsector/{v1}`.
- Restricción de roles: no verificada en el método (la entidad puede aplicar límites).

Flujo detallado:
1. Lee `v1` como id y crea `new Sector($id)`.
2. Llama a `$object->remove()` que devuelve un código `$ok`.
3. Redirige a `sectores` pasando `v1 => sec_page` (página en sesión) y `v2 => $ok`.

---

## xaAction

- Propósito: Endpoint AJAX multiuso usado por autocompletados y select2 en el frontend. Gestiona varias operaciones según el parámetro `v1`:
  - 1: búsqueda de ofertas por título (texto)
  - 2: obtener una oferta por id
  - 3: búsqueda de candidaturas por nombre/apellidos (solo admin)
  - 4: obtener candidatura por id (solo admin)
  - 5: búsqueda de sectores por nombre
  - 6: obtener sector por id

- Ruta de ejemplo: `/backend/empleo/xa/{v1}` con GET `q`.
- Restricciones de rol: las operaciones 3 y 4 comprueban `rol == '4dmin'` (solo admin puede usarlas).

Flujo detallado por operación:
1. Lee `v1` desde la ruta y lo castea a entero (`$ajax`). Inicializa `$answer = []`.
2. Operación 1 (`$ajax == 1`):
   - Lee `q` de `$_GET` como término de texto.
   - Crea `new Ofertas()` y construye `$where = 'titulo LIKE "%'.$term.'%"'`.
   - Si el usuario no es admin añade `AND id_emp = <id_emp>` para filtrar por empresa.
   - Llama a `$db->get($where,'titulo')` y por cada objeto construye elementos `{id, text}` usando `get('id')` y `get('titulo')`.
   - Si no hay resultados devuelve un elemento con id 0 y texto "No existen resultados.".
3. Operación 2 (`$ajax == 2`):
   - Interpreta `q` como id (castea a int), instancia `new Oferta(<id>)`, y si existe devuelve `{id, text}`.
4. Operación 3 (`$ajax == 3` y admin):
   - Usa `q` como texto y llama `getCandidaturas('nombre LIKE ... OR apellidos LIKE ...')`.
   - Construye resultados con `id_can` y texto (en el código actual concatena `nombre . ' ' . nombre` — probable bug).
5. Operación 4 (`$ajax == 4` y admin):
   - Interpreta `q` como id de candidatura y devuelve el registro correspondiente si existe.
6. Operación 5: búsqueda de sectores por nombre (LIKE) y devuelve `{id, nombre}`.
7. Operación 6: obtiene sector por id y devuelve `{id, nombre}` o vacío si no existe.
8. Finalmente codifica la respuesta con `Json::encode($answer)` y la coloca en la respuesta HTTP usando `getResponse()->setContent(...)`.

Notas y riesgos:
- El uso directo de `$_GET['q']` y la concatenación en cláusulas LIKE es un punto de riesgo (posible inyección o comportamiento inesperado). En varias ramas se fuerza casteo a entero cuando se espera id, lo cual disminuye el riesgo para esas operaciones.
- Las operaciones 3 y 4 están protegidas comprobando rol admin; conservación de la lógica original fue priorizada en la documentación.

---

## Notas de seguridad y recomendaciones

1. Sanitizar y parametrizar consultas:
   - Hay múltiples lugares donde se construyen condiciones SQL por concatenación (p.ej. `LIKE "%$term%"`, `id_can = $term`). Si la capa de acceso a la BD (`Ofertas`, `Candidaturas`, etc.) no parametriza internamente, esto puede conducir a inyección. Recomiendo revisar `Utilidades::generaCondicion` y los métodos `get`/`getOfertas`/`getCandidaturas` para garantizar consultas preparadas o al menos escapado seguro.

2. Evitar uso directo de `$_GET` y preferir `$this->params()->fromQuery('q')` que es más explícito y fácil de testear.

3. Endpoint de subida de ficheros (`presentarcandidaturaAction`):
   - Verificar permisos y tamaño y extensión ya se hace, pero valide también el tipo MIME detectado por servidor, y evite confiar únicamente en la extensión.
   - Use nombres de archivo únicos más robustos (ej. `bin2hex(random_bytes(8)) . '.' . $ext`).

4. Evitar `die()`/`exit` en acciones si es posible: para descargas, retornar una Response con un Body streaming es más limpio y compatible con middlewares.

5. Corrección ligera sugerida (no aplicada): en `xaAction` concatenación de candidato muestra `nombre . ' ' . nombre` en lugar de `nombre . ' ' . apellidos`.



