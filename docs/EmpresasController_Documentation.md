## EmpresasController — Documentación detallada

Índice

- Resumen del controlador
- indexAction
- fichaAction
- borrarAction
- xlsAction
- usuariosAction
- xaAction
- importarAction

---

## Resumen del controlador

`EmpresasController` gestiona la administración de las empresas desde el módulo `Backend`.
Sus responsabilidades principales (reflejadas en las actions) son:

- Listar empresas con filtros y paginación (`indexAction`).
- Crear/editar la ficha de empresa y mostrar relaciones (`fichaAction`).
- Borrar empresas (`borrarAction`).
- Exportar empresas a Excel (`xlsAction`).
- Relacionar / desrelacionar usuarios con empresas (`usuariosAction`).
- Endpoints ligeros para autocompletar y consultas por AJAX (`xaAction`).
- Importar empresas desde una fuente externa (`importarAction`).

En el constructor se inicializa la identidad del usuario autenticado (`$_usuario`) y
un contenedor de sesión (`$_container`) usado para persistir filtros y páginas.

Nota general de seguridad y testing

- El controlador asume que existe una sesión/autenticación; la mayoría de las
  actions dependen de `$_usuario` y de la validación hecha en la capa de modelo.
- Se observan patrones inseguros o mejorables: concatenación directa en cláusulas
  SQL (ej.: "LIKE '%term%'") y uso directo de `$_GET` en `xaAction`. Recomendable
  parametrizar consultas y usar `$this->params()->fromQuery()` para lectura de query params.
- Varias actions usan `die()`/`exit` tras enviar salidas (xls/importar); esto reduce
  la testabilidad y la capacidad de manejo de errores. Mejor devolver un Response/Stream.

---

## indexAction

Propósito

- Listar empresas con posibilidad de filtros (id_emp, cif, id_sec, estado) y paginación.

Ruta

- Por convención en este proyecto las rutas usan `backend/default` con `controller=empresas`.
- Ruta ejemplo: backend/default?controller=empresas&action=index (ruta parametrizada vía v1, v2)

Restricciones de roles

- Requiere autenticación (se usa `$_usuario`). No hay comprobación explícita de rol en esta action,
  aunque la UI puede restringir el acceso según rol en otra capa.

Flujo detallado

1. Ajusta el título del layout: `$this->layout()->title = 'Empresas';`.
2. Lee el parámetro `v2` de la ruta para interpretar códigos de mensaje (por ejemplo 525 = borrado correcto, 536 = fallo por dependencias). Estos códigos permiten mostrar notificaciones generadas por otras acciones.
3. Instancia la capa de datos: `$db_empresas = new Empresas()` y define un orden por defecto (`empresasNombre ASC`).
4. Maneja filtros enviados por POST:
   - Si es POST, crea un array `$data`, usa `StripTags` para limpiar `cif` y castea a int los ids.
   - Guarda `$data` en `$this->_container->empr_buscador` (sesión) y reinicia la página (`empr_page = 0`).
5. Limpieza de filtros:
   - Si no es POST y `v2 == 114`, elimina los filtros guardados (`unset($this->_container->empr_buscador)`).
6. Paginación:
   - Lee `v1` desde la ruta como `page`. Si `v1` no está, usa la página guardada en sesión o inicializa a 1.
   - Calcula `offset = 50 * (page - 1)` (50 items por página).
7. Construye la cláusula WHERE:
   - Si existen filtros en sesión, llama `Utilidades::generaCondicion('empresas', $this->_container->empr_buscador)` para producir la condición SQL.
   - Almacena la condición en sesión para depuración (`empr_buscador['where']`).
8. Consulta y conteo:
   - Llama a `$db_empresas->getEmpresas($where, $orderby, 50, $offset)` para obtener el conjunto paginado.
   - Obtiene el número total con `$db_empresas->num($where)`.
   - Si `num == 0`, prepara mensajes informativos según si había filtros o no.
9. Prepara el `ViewModel` con los datos (`usuario`, `buscador`, `page`, `ok`/`ko`, `num`, `empresas`) y lo devuelve.

Puntos importantes

- `Utilidades::generaCondicion` construye cadenas SQL; revisar para evitar inyección si no usa parámetros.
- Mantener la lógica de paginación en sesión ayuda la UX, pero hace necesario limpiar manualmente los contenedores.

---

## fichaAction

Propósito

- Crear o editar una empresa; al editar, además muestra relaciones (ofertas, inscripciones, usuarios) y permite navegar entre ellas.

Ruta

- backend/default?controller=empresas&action=ficha with route params v1 (id), v2..v5 (subcódigos y páginas de tabs)

Restricciones de roles

- Requiere autenticación. La función contiene comprobaciones explícitas: si `$_usuario->get('rol') != '4dmin'` hay una serie de restricciones que pueden redirigir al usuario a su propia ficha o al dashboard si no está autorizado.

Flujo detallado

1. Prepara título y variables de mensajes (`$msg_ok`, `$msg_error`) y un identificador de pestaña `$tab`.
2. Si la petición es POST:
   - Crea `$data` y usa `StripTags` para limpiar texto.
   - Obtiene campos del formulario (id_emp, nombre, razonsocial, cif, id_sec, alta, web, dirección, cp, localidad, provincia, email, telefono).
   - Si el usuario tiene rol `4dmin`, permite cambiar el `estado` desde el formulario; si no, carga la empresa actual (`new Empresa($data['id_emp'])`) y obliga a mantener su estado actual.
   - Valida los valores mediante `$empresa = new Empresa(0); $algun_valor_vacio = $empresa->set($data);` y si faltan campos obligatorios (`$algun_valor_vacio > 0`) prepara `$msg_error`.
   - Si la validación pasa, guarda con `$empresa->save()`; si era creación (`id_emp == 0`) notifica y, si el usuario no es admin, asigna la nueva empresa al usuario (`$this->_usuario->setIdEmp($id)`).
3. Si no es POST:
   - Lee el id desde la ruta (`v1`) y carga `$empresa = new Empresa($id)`.
   - Lee `v5` para interpretar acciones rápidas (activación/rechazo/relación de usuario) y realiza efectos como `setEstado()` cuando corresponde.
4. Control de permisos para usuarios no administradores:
   - Obtiene la empresa asociada al usuario (`$empresaUser = $this->_usuario->get('empresa')`).
   - Computa `$allow` si el usuario está autorizado o si no tiene empresa (`id_emp == 0`).
   - Si intenta acceder a otra empresa y tiene permiso, lo redirige a su propia ficha; si no tiene permiso lo redirige al index del backend.
5. Si `id > 0` (edición): carga las relaciones para mostrarlas en pestañas:
   - Ofertas: paginación mediante param `v2`, consulta `$db_ofe->getOfertas('id_emp = '.$id, ...)` y conteo `$db_ofe->num('id_emp = '.$id)`.
   - Inscripciones: paginación `v3`, consulta `$db_ins->getInscripciones('id_emp = '.$id, ...)` y conteo.
   - Usuarios: paginación `v4`, consulta `$db_usu->get('id_emp = '.$id, ...)` y conteo.
   - Observación: las condiciones se construyen concatenando el id en strings; es sencillo pero podría mejorarse con parámetros en la capa de datos.
6. Si `id == 0` (nuevo), prepara arrays vacíos y contadores a 0 para las relaciones.
7. Devuelve un `ViewModel` con `usuario`, `empresa`, mensajes, listas de relaciones, páginas y flags de autorización.

Puntos importantes

- Control de permisos: la acción evita que usuarios no autorizados editen o vean fichas de otras empresas.
- Los filtros/paginación usan `$this->_container->uins_page` (mismo contenedor) — revisar colisiones si se comparte entre distintas secciones.

---

## borrarAction

Propósito

- Eliminar una empresa (delegando la lógica de borrado en la entidad `Empresa`).

Ruta

- backend/default?controller=empresas&action=borrar with v1 = id de empresa

Restricciones de roles

- Requiere autenticación; la propia action no valida explícitamente roles, la entidad `Empresa::remove()` puede aplicar comprobaciones internas.

Flujo detallado

1. Lee `v1` desde la ruta y castea a entero: `$id = (int)$this->params()->fromRoute('v1', 0)`.
2. Crea la entidad: `$object = new Empresa($id)`.
3. Llama a `$ok = $object->remove()`; el valor retornado `$ok` indica el resultado (por ejemplo 525 success o 536 fallo por dependencias).
4. Redirige al listado (`indexAction`) pasando la página actual desde sesión (`$this->_container->empr_page`) y el código `$ok` en `v2` para que `indexAction` muestre la notificación correspondiente.

Puntos importantes

- `Empresa::remove()` puede encapsular borrado lógico (marcar como inactiva) o borrar en cascada; revisar su implementación antes de modificar el comportamiento.
- La action no renderiza vista propia, siempre redirige.

---

## xlsAction

Propósito

- Exportar a Excel (formato .xls) las empresas que cumplen los filtros activos.

Ruta

- backend/default?controller=empresas&action=xls (usa filtros en sesión)

Restricciones de roles

- Requiere autenticación; no hay comprobación explícita de rol aquí.

Flujo detallado

1. Reconstruye la condición WHERE usando `$this->_container->empr_buscador` llamando `Utilidades::generaCondicion('empresas', ...)`.
2. Obtiene la colección completa sin paginar: `$objects = $db->getEmpresas($where, 'empresasNombre')`.
3. Llama a `Exportar::empresas($objects)` que devuelve un objeto `PHPExcel` con los datos preparados.
4. Envía headers HTTP para forzar descarga:
   - Content-type: application/vnd.ms-excel
   - Content-Disposition: attachment; filename=exportacion_<fecha>.xls
   - Pragma: no-cache, Expires: 0
5. Usa `PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5')` y `save('php://output')` para escribir el binario al output.
6. Termina la ejecución con `die('Excel generado')` / `exit`.

Puntos importantes y recomendaciones

- Uso de `die()`/`exit`: interrumpe la ejecución global y dificulta testing; mejor devolver un Response con Stream y status.
- Manejo de memoria: generar grandes exportaciones puede consumir mucha memoria; considerar streaming por chunks o limitar máximo de filas.

---

## usuariosAction

Propósito

- Asociar un usuario a una empresa por NIF (POST) o eliminar la relación (GET/route).

Ruta

- POST hacia backend/default?controller=empresas&action=usuarios (con campos nif e id_emp)
- GET hacia backend/default?controller=empresas&action=usuarios&v1=<id_usu>&v2=<id_emp> para eliminar la relación

Restricciones de roles

- Requiere autenticación; la action asume que quien la ejecuta tiene permisos administrativos o similares.

Flujo detallado

Modo POST (crear vínculo):
1. Limpia `nif` con `StripTags` y castea `id_emp`.
2. Usa `Usuarios()->getByNif($nif)` para localizar al usuario.
3. Si el usuario existe (`id > 0`) llama `$usuario->setEmpresa($id_emp)` para asociarlo y redirige a `fichaAction` con `v5 = 111` (éxito).
4. Si no existe, redirige a `fichaAction` con `v5 = 112` (usuario no encontrado).

Modo GET (eliminar vínculo):
1. Lee `v1 = id_usu` y `v2 = id_emp` desde la ruta.
2. Crea `new Usuario($id_usu)`. Si existe llama `$usuario->eliminaRelacionEmpresa()` y redirige con `v5 = 113`.
3. Si no existe, redirige con `v5 = 114` indicando fallo.

Puntos importantes

- `Usuarios::getByNif` y los métodos `setEmpresa` / `eliminaRelacionEmpresa` son responsables de la integridad de las relaciones; confirmar que aplican validaciones y permisos.
- Los códigos `v5` se usan como semáforo para notificaciones en `fichaAction`.

---

## xaAction

Propósito

- Proveer endpoints ligeros para peticiones AJAX, típicamente autocompletar o consulta por id.

Ruta

- backend/default?controller=empresas&action=xa&v1=<tipo>
- Parámetro de query `q` (ej. `?q=texto`) usado para búsqueda o id.

Restricciones de roles

- Requiere autenticación; no hay comprobación de rol en la action.

Flujo detallado

1. Lee `v1` como `$ajax` para diferenciador del tipo de consulta.
2. Inicializa el array `$answer = []` que contendrá la respuesta.
3. Si `$ajax == 1` (autocomplete por texto):
   - Lee `q` (actualmente desde `$_GET['q']`) y busca empresas con `empresasNombre LIKE "%q%"`.
   - Por cada resultado añade un elemento `['id' => id_emp, 'text' => empresasNombre]`.
   - Si no hay resultados devuelve `['0', 'No existen resultados.']`.
4. Si `$ajax == 2` (consulta por id):
   - Lee `q` como id numérico, crea `new Empresa($id)` y devuelve `['id' => id, 'text' => nombre]` o `['0','']` si no existe.
5. Devuelve JSON con `return $this->getResponse()->setContent(Json::encode($answer));`.

Puntos de seguridad y mejora

- Hoy se usa `$_GET['q']` y se concatena en la cláusula LIKE. Esto es funcional pero inseguro ante inyecciones o caracteres especiales.
- Recomendaciones:
  - Usar `$this->params()->fromQuery('q')` para lectura de parámetros.
  - Parametrizar la consulta en la capa `Empresas` (prepared statements) en vez de concatenar cadenas.
  - Escapar o normalizar `q` antes de usarlo en LIKE (ej.: añadir escapes para % y _ si procede).

---

## importarAction

Propósito

- Disparar la importación masiva de empresas delegando el proceso en la capa `Empresas`.

Ruta

- backend/default?controller=empresas&action=importar

Restricciones de roles

- Requiere autenticación; normalmente debería restringirse a administradores, pero la acción no lo impone en el controlador.

Flujo detallado

1. Instancia `$db = new Empresas()` y llama `$db->importarEmpresas()`.
2. Finaliza la petición con `die()` sin renderizar vista ni devolver JSON.

Puntos importantes

- Importar suele implicar subir ficheros, validar filas, tratar errores y hacer operaciones en lote. Todo esto debe estar implementado con transacciones y manejo de errores en la función `importarEmpresas`.
- El uso de `die()` impide reportar el estado del resultado al cliente. Recomendable refactorizar para devolver un `Response` con resumen del resultado (filas insertadas, errores, tiempo de ejecución).

---

## Recomendaciones generales y siguientes pasos

1. Revisar y parametrizar consultas que hoy se construyen por concatenación (evitar inyección SQL y problemas con caracteres especiales).
2. Sustituir usos directos de `$_GET` por `$this->params()->fromQuery()` y validar/castear siempre los inputs.
3. Evitar `die()`/`exit` en actions: devolver Responses o Streams que faciliten test y control de errores.
4. Añadir comprobaciones de roles más estrictas donde proceda (por ejemplo, `importarAction` y `xlsAction` deberían exigir rol administrador explícito).
5. Añadir tests unitarios/funcionales para las rutas principales y para los flujos críticos (crear empresa, borrar con dependencias, importar masivo, exportar Excel).

---

Archivo generado automáticamente por las labores de documentación del repositorio (`EmpresasController`).
