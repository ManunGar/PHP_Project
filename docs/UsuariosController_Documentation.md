# UsuariosController — Documentación detallada

> Archivo generado automáticamente con una explicación pedagógica, función por función, para un lector sin conocimientos de PHP.

## Índice

- Introducción
- __construct
- indexAction
- fichaAction
- borrarAction
- cvAction
- xlsAction
- importarcolegiadosAction
- actualizacolAction
- fotoAction
- menoresAction
- borrarmenorAction
- carpetasAction
- borrarcarpetaAction
- permisosAction
- xaAction
- Notas de seguridad y recomendaciones

---

## Introducción

Este documento explica cada acción (método que termina en `Action`) del controlador `UsuariosController` localizado en `module/Backend/src/Backend/Controller/UsuariosController.php`.

Para cada función se especifica:
- Para qué sirve (propósito)
- A qué ruta o acción corresponde (nombreAction)
- Si existen restricciones de rol (por ejemplo acceso solo a usuarios autenticados/administradores)
- Un flujo detallado paso a paso: entradas, validaciones, operaciones sobre entidades (Usuario, Usuarios, Inscripciones, Menor, Carpetas, Permisos), respuestas (render, redirect, JSON), y efectos secundarios (subida de archivos, importaciones, exportaciones)
- Inputs esperados y formatos
- Outputs / efectos secundarios
- Notas de seguridad y recomendaciones concretas

---

## __construct

Propósito
- Inicializar dependencias del controlador: obtener la identidad del usuario autenticado y cargar la entidad `Usuario` asociada; crear un contenedor de sesión para guardar filtros/paginación.

Ruta / Acción
- No es una action pública; se ejecuta al instanciar el controlador.

Restricción de roles
- No aplica; configura estado interno para las actions que requieren autenticación.

Flujo detallado
1. Crear un `AuthenticationService` para consultar si hay un usuario autenticado.
2. Llamar a `getIdentity()` para obtener la identidad (información del login actual).
3. Si `hasIdentity()` es true, instanciar la entidad `Usuario` con `id_usu` de la identidad.
4. Guardar el objeto `Usuario` en `$this->_usuario` para que las demás actions lo usen.
5. Crear un `Container('namespace')` y guardarlo en `$this->_container` para persistir datos entre peticiones (filtros, páginas).

Inputs
- Servicios del framework y la identidad activa.

Outputs / Efectos secundarios
- Variables internas inicializadas para uso por otras acciones.

Notas de seguridad
- No guardar datos sensibles sin cifrar en sesión.

---

## indexAction

Propósito
- Mostrar listado de usuarios con filtros y paginación.

Ruta / Acción
- `indexAction` — normalmente `/backend/usuarios`.

Restricción de roles
- Acceso: personal autenticado del backend (habitualmente administradores o gestores).

Flujo detallado
1. Ajusta título del layout a "Usuarios".
2. Lee parámetro de ruta `v2` para códigos de mensaje (por ejemplo, mostrar "usuario borrado").
3. Inicializa variables de mensajes (`$msg_ok`, `$msg_error`).
4. Instancia `Usuarios` (mapper) y define orden por defecto.
5. Si la petición es POST, lee el formulario de filtros:
   - Usar `StripTags` para sanear campos de texto.
   - Recoger `id_usu`, `nif`, `colegiado`, `telefono`, `email`, `sitcol`, `rol`, `autorizado`.
   - Guardar el array de filtros en `$this->_container->usua_buscador` y resetear página.
6. Si no es POST, comprobar si `v2 == 114` para limpiar filtros en sesión.
7. Obtener parámetro de ruta `v1` para la página; si no viene, usar el valor guardado en sesión o inicializar a 1. Calcular offset con 50 resultados por página.
8. Si existen filtros en sesión, llamar `Utilidades::generaCondicion('usuarios', $buscador)` para obtener `$where`.
   - NOTA: `generaCondicion` puede construir SQL concatenado; revisar en refactor.
9. Consultar `$db_usuarios->get($where,$orderby,50,$offset)` para obtener lista y `$db_usuarios->num($where)` para total.
10. Preparar array `$view` con variables para la plantilla y devolver `ViewModel`.

Inputs
- GET/route: `v1` (page), `v2` (mensaje), POST: filtros.

Outputs / Efectos secundarios
- Renderizado HTML con listado y paginación.

Notas de seguridad
- Validar y parametrizar consultas generadas por filtros.
- Limitar tamaño de página y sanitizar entradas.

---

## fichaAction

Propósito
- Mostrar y procesar el formulario de ficha de usuario (crear/editar), además de gestionar pestañas relacionadas (inscripciones, inscritos, menores, permisos).

Ruta / Acción
- `fichaAction` — ruta tipo `/backend/usuarios/ficha[/:v1]`.

Restricción de roles
- Normalmente administradores o el propio usuario autenticado.

Flujo detallado
1. Ajusta título del layout.
2. Inicializa mensajes y variables auxiliares (`$msg_ok`, `$msg_error`, `$array_errores`, `$clave`, `$tab`, `$set`).
3. Lee parámetros de ruta `v6` y `v5` para códigos de resultado (mensajes de acciones relacionadas como subir CV, borrar menor) y ajusta `$tab` para enfocar la pestaña correcta en la vista.
4. Si la petición es POST:
   - Crear array `$data` y `StripTags` para sanear.
   - Leer `set` (controla sub-flujos):
     - Si `set == 1`: rama principal — guardar/crear usuario.
       - Determina `id_usu` según rol del usuario autenticado: si es `4dmin` puede editar cualquier usuario; si no, solo su propia ficha.
       - Si el editor es admin (`4dmin`), preparar `$case = 2` y permitir campos administrativos (observaciones, pago_pendiente, sincro, id_emp, autorizado, rol, colegiado, nif, alta, sitcol, delegacion, baja, acceso_gestor_documental).
       - Si no admin, `$case = 4` y se carga `Usuario($id)` para edición parcial.
       - Recoger campos comunes: nombre, apellidos, teléfono, email, sexo, nacimiento, clave, cv, sitlab, titulacion, master, empleo, experiencia, especialidad, jornada, dirección profesional, cp, población, provincia.
       - Llamar `$usuario->set($data,$case)` y luego `$usuario->revisaEmailYNif()` para validar duplicados.
       - Manejar errores: campos obligatorios vacíos, email o NIF duplicados; si todo ok, `$usuario->save()` y asignar mensaje de creación/actualización.
     - Si `set != 1`: rama para cambiar contraseña únicamente.
       - Leer `clave` de POST, cargar `Usuario`, llamar a `validaClave()` y si pasa, `setClave()`.
5. Si no es POST: determinar `id` a mostrar (admin puede pasar por ruta, si no, mostrar el del usuario autenticado) y cargar `$usuario`.
6. Si `$id > 0`, cargar pestañas relacionadas con paginaciones propias para `inscripciones`, `inscritos`, `candidaturas`, `menores`.
   - En cada caso se calcula offset y se llama a métodos del modelo como `getInscripciones('id_usu = '.$id...)` — ojo al uso de concatenación de SQL.
7. También cargar `carpetas` y `permisos` asociando permisos por carpeta para mostrar control documental.
8. Preparar `$view` con todas las variables y devolver `ViewModel`.

Inputs
- POST: muchos campos de usuario; GET/route: `v1` (id), `v2..v6` códigos de resultado y paginación.

Outputs / Efectos secundarios
- Creación/actualización de `Usuario`; mensajes de resultado; carga de listas relacionadas para la vista.

Notas de seguridad
- Validar duplicados de email/NIF; proteger contra CSRF; parametrizar consultas en los mappers.
- Manejar correctamente permisos: evitar que usuarios sin rol editen campos administrativos.

---

## borrarAction

Propósito
- Eliminar un usuario por `id` y redirigir al listado con un código de resultado.

Ruta / Acción
- `borrarAction` — ruta `/backend/usuarios/borrar/:v1`.

Restricción de roles
- Solo administradores o usuarios con permiso de borrado.

Flujo detallado
1. Leer `v1` desde la ruta y parsear a entero.
2. Instanciar `Usuario($id)` y llamar `remove()`.
3. `remove()` devuelve un código de resultado que se usa para mostrar mensajes en el listado.
4. Redirigir a la ruta `backend/default` con controlador `usuarios`, acción `index`, pasando `v1` (página actual desde sesión) y `v2` (código resultado).

Inputs
- Route: `v1` (id de usuario).

Outputs / Efectos secundarios
- Eliminación del registro (según la lógica de la entidad) y redirección con código.

Notas de seguridad
- Antes de eliminar, revisar restricciones de integridad referencial y autorizar la acción.

---

## cvAction

Propósito
- Subir o eliminar el currículum (CV) asociado a un usuario.

Ruta / Acción
- `cvAction` — ruta `/backend/usuarios/cv` o `/backend/usuarios/cv/:v1`.

Restricción de roles
- Edición por admin o por el propio usuario según permisos.

Flujo detallado
1. Inicializa `$msg` con código por defecto (550 = error genérico).
2. Si la petición es POST:
   - Leer `id_usu` de POST y cargar `Usuario`.
   - Obtener `FILE_DIRECTORY_CV` de la entidad para conocer el destino.
   - Configurar `Zend\File\Transfer\Adapter\Http` y validadores: tamaño máximo 10MB y extensiones permitidas (`pdf`, `doc`, `docx`).
   - Obtener información del fichero enviado (`getFileInfo('cv')`).
   - Si pasa validadores:
     - Extraer nombre original, calcular extensión, generar nombre único con `time()` y extensión.
     - Añadir filtro `filerename` para mover y renombrar fichero al destino.
     - Llamar `receive()` para mover el fichero; si OK, llamar `$usuario->setCv($fichero)` y marcar `$msg = 548`.
   - Si no pasa validaciones, `$msg = 549`.
3. Si no es POST se interpreta como petición de borrado de CV:
   - Leer `v1`, instanciar `Usuario`, llamar `removeCv()` y marcar `$msg = 551`.
4. Redirigir a `ficha` pasando `$msg` en `v6`.

Inputs
- POST: campo file `cv`, id_usu; route v1 para borrado.

Outputs / Efectos secundarios
- Archivo guardado en disco y referencia actualizada en la entidad, o fichero borrado.

Notas de seguridad
- Validar MIME-type además de extensión; controlar rutas de destino y evitar path traversal; restringir tamaño.

---

## xlsAction

Propósito
- Exportar la lista de usuarios a un fichero Excel descargable.

Ruta / Acción
- `xlsAction` — ruta `/backend/usuarios/xls`.

Restricción de roles
- Usuarios autorizados para exportar datos (normalmente administradores).

Flujo detallado
1. Obtener `$where` llamando `Utilidades::generaCondicion('usuarios', $this->_container->usua_buscador)`.
2. Instanciar `Usuarios` y obtener objetos con `$db->get($where,['apellidos','nombre'])`.
3. Llamar `Exportar::usuarios($objects)` para obtener un objeto `PHPExcel` con los datos.
4. Enviar headers HTTP para forzar descarga de tipo Excel (content-type, content-disposition, pragma, expires).
5. Crear `PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5')` y guardar a `php://output`.
6. Terminar ejecución con `die()` (nota: poco recomendable; preferir StreamResponse para control y test).

Inputs
- Sesión: `$this->_container->usua_buscador`.

Outputs / Efectos secundarios
- Descarga inmediata al navegador del fichero Excel.

Notas de seguridad
- Evitar exponer datos sensibles en exportaciones; preferir StreamResponse a `die()`.

---

## importarcolegiadosAction

Propósito
- Leer un Excel predefinido y crear usuarios/colegiados a partir de sus filas.

Ruta / Acción
- `importarcolegiadosAction` — puede estar pensada para ejecución interna o desde una tarea (no necesariamente accesible públicamente).

Restricción de roles
- Debe estar protegida: sólo administradores o procesos batch.

Flujo detallado
1. Definir ruta del fichero de import `./data/import/ListadoGenerado_Definitivo.xlsx`.
2. Identificar tipo de fichero y cargarlo con PHPExcel.
3. Leer la primera hoja y recorrer filas desde la 2 hasta `highestRow`.
4. Para cada fila mapear columnas a campos: nombre, apellidos, colegiado, teléfono, email, nif, sexo, nacimiento, clave, columnas booleanas para `sitcol`.
5. Detectar algunos valores y normalizarlos (sexo, fecha de nacimiento con `Utilidades::giraFecha`).
6. Construir array `$data` con valores por defecto para campos no incluidos.
7. Crear `Usuario(0)`, `set($data,2)` y `save()` para insertar la cuenta.
8. Contabilizar importados y, al final, hacer `echo` con resumen y `die()`.

Inputs
- Fichero Excel con estructura conocida.

Outputs / Efectos secundarios
- Creación masiva de usuarios; salida por pantalla con resumen.

Notas de seguridad
- Validar contenido del Excel antes de ejecutar; soportar dry-run; evitar ejecución desde web pública si no está protegida.

---

## actualizacolAction

Propósito
- Actualizar datos de colegiados desde ficheros Excel distintos (precol o col), actualizando registros existentes o creando nuevos.

Ruta / Acción
- `actualizacolAction` — ruta con parámetro `v1` que indica si es precol.

Restricción de roles
- Debe estar protegida para administradores.

Flujo detallado
1. Leer `v1` para decidir fichero (`ListadoPrecol.xlsx` o `ListadoCol.xlsx`).
2. Detectar y cargar fichero con PHPExcel.
3. Iterar filas del Excel y para cada fila:
   - Obtener `colegiado` y buscar usuario existente vía `$db_usu->getByColegiado($colegiado)`.
   - Mapear columnas (sexo, delegación, observaciones, banderas de sitcol, pago pendiente).
   - Si usuario existe: `$usuario->set($data,3)` y `save()`.
   - Si no existe: construir `$data` con más columnas (nombre, apellidos, nif, teléfono, email, nacimiento), fijar valores por defecto y crear con `set($data,2)` y `save()`.
4. Mostrar mensajes/contador y `die()` al finalizar.

Inputs
- Ruta `v1` y fichero en `./data/import/`.

Outputs / Efectos secundarios
- Creación/actualización masiva de usuarios.

Notas de seguridad
- Validar datos; registrar cambios; preferir ejecución desde CLI para evitar timeouts.

---

## fotoAction

Propósito
- Actualmente es un placeholder: acción vacía pensada para gestionar la foto de usuario.

Ruta / Acción
- `fotoAction` — ruta presente pero sin implementación en código actual.

Restricción de roles
- Si se implementa, deberá protegerse y validar uploads.

Notas
- Recomiendo implementar validaciones similares a `cvAction` y almacenar imágenes con límites y validación de MIME.

---

## menoresAction

Propósito
- Añadir o editar menores asociados a un usuario. Permite guardar todos los menores enviados en arrays o uno por uno.

Ruta / Acción
- `menoresAction` — llamada desde la ficha de usuario (POST desde formulario de menores).

Restricción de roles
- Acceso por usuario dueño o administradores.

Flujo detallado
1. Verifica que la petición es POST.
2. Lee `boton` para distinguir entre `guardar-todos` o guardado individual (índice).
3. Lee arrays: `id_men[]`, `nombre[]`, `apellidos[]`, `observaciones[]`, `nacimiento[]` y `id_usu`.
4. Si `guardar-todos`: iterar `id_mens` y para cada posición crear un `Menor(0)` con los datos y `save()` si hay nombre.
5. Si no: usar índice `boton` para guardar solo una fila.
6. Redirigir a la ficha con código `v5 = 547` indicando éxito.

Inputs
- POST: `boton`, `id_usu`, arrays de menores.

Outputs / Efectos secundarios
- Creación/actualización de registros en `menores`.

Notas de seguridad
- Validar fechas de nacimiento y campos; evitar inyección por campos libres.

---

## borrarmenorAction

Propósito
- Eliminar un menor y redirigir a la ficha del usuario propietario.

Ruta / Acción
- `borrarmenorAction` — `/backend/usuarios/borrarmenor/:v1`.

Restricción de roles
- Solo administradores o el usuario dueño.

Flujo detallado
1. Leer `v1` desde la ruta, instanciar `Menor($id)` y llamar `remove()`.
2. Redirigir a `ficha` del usuario asociado usando `object->get('id_usu')` y pasando el código de resultado en `v5`.

---

## carpetasAction

Propósito
- Gestionar carpetas del gestor documental: listar, buscar y actualizar en lote.

Ruta / Acción
- `carpetasAction` — `/backend/usuarios/carpetas`.

Restricción de roles
- Usuarios con permisos de gestor documental.

Flujo detallado
1. Ajusta título y lee `v2` para códigos de resultado.
2. Si POST: lee `boton` (buscar / guardar-todos / índice). Dependiendo del botón:
   - `buscar`: guardar filtro `id_car` en sesión.
   - `guardar-todos`: iterar arrays `id_car[]` y `nombre[]` y crear/actualizar `Carpeta` por cada entrada.
   - índice: actualizar una sola carpeta según índice.
3. Si no POST, interpretar `v2 == 114` como limpiar filtros y eliminarlos de sesión.
4. Manejar paginación y construir `$where` si hay buscador en sesión.
5. Obtener datos con `$db->get($where,$orderby,50,$offset)` y preparar vista.

Inputs
- POST: `boton`, `id_car[]`, `nombre[]`; route v1/v2 para paginación y mensajes.

Outputs / Efectos secundarios
- Creación/actualización de carpetas; variables para la vista.

Notas de seguridad
- Validar entradas y sanitizar nombres; controlar permisos para edición.

---

## borrarcarpetaAction

Propósito
- Eliminar carpeta por id.

Ruta / Acción
- `borrarcarpetaAction` — `/backend/usuarios/borrarcarpeta/:v1`.

Flujo detallado
1. Leer `v1`, instanciar `Carpeta`, `remove()` y redirigir a `carpetas` con código.

---

## permisosAction

Propósito
- Guardar permisos de acceso a carpetas para un usuario; soporta guardado en lote y individual.

Ruta / Acción
- `permisosAction` — llamada desde ficha de usuario.

Flujo detallado
1. Si POST: leer `boton`.
   - `guardar-todos`: leer arrays `id_per[]`, `id_car[]`, `permiso[]` y `id_usu`; iterar y crear `Permiso` por cada carpeta.
   - índice: guardar permiso individual usando índices.
2. Establecer `$ok = 552` como código de resultado.
3. Redirigir a `ficha` del usuario pasando `v6 = $ok`.

Notas de seguridad
- Validar que quien guarda permisos tiene autorización; evitar elevación de privilegios.

---

## xaAction

Propósito
- Endpoint AJAX para autocompletar y búsquedas pequeñas (por ejemplo, búsqueda de usuario por texto o por id).

Ruta / Acción
- `xaAction` — `/backend/usuarios/xa/:v1` con query `q`.

Restricción de roles
- Requerir autenticación; proteger contra abusos (rate limit) si está expuesto.

Flujo detallado
1. Leer `v1` para distinguir operación:
   - `1`: búsqueda por término (autocomplete). Leer `q` de la query con `$this->params()->fromQuery('q')`.
     - Ejecutar consulta sobre `Usuarios` (actualmente construida por concatenación) y devolver array de objetos `{id, text}` en JSON.
   - `2`: buscar por id (convertir `q` a int), instanciar `Usuario($id)` y devolver `{id, text}`.
2. Devolver JSON encodificado.

Notas de seguridad
- Evitar usar `$_GET` directamente; usar `fromQuery`.
- Parametrizar consultas para evitar inyección SQL.
- No exponer datos sensibles por JSON.

---

## Notas de seguridad y recomendaciones generales

- Parametrizar consultas en mappers y evitar concatenaciones directas con valores del usuario.
- Sustituir el uso de `die()`/`exit` en exportaciones por `StreamResponse` o respuestas controladas por el framework.
- Añadir CSRF a formularios que modifican datos.
- Validar MIME-type en uploads y restringir tamaño y rutas de almacenamiento.
- Proteger endpoints de importación/exportación para que no sean accesibles públicamente sin autenticación.
- Añadir pruebas automatizadas para flujos críticos (creación/edición de usuario, subida CV, exportación).

---

**Archivo generado**: `docs/UsuariosController_Documentation.md`

Fecha de generación: 14 de noviembre de 2025

Si quieres, puedo:
- Extraer y añadir al MD los comentarios línea a línea exactamente tal como los insertamos en el PHP (muy verboso),
- Generar un resumen ejecutivo con los riesgos prioritarios y parches sugeridos para un PR pequeño,
- O proceder a documentar otro controlador del módulo Backend.

Fin del documento.
