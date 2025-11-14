## IndexController — Documentación detallada

Este documento contiene una explicación didáctica de cada función presente en
`module/Backend/src/Backend/Controller/IndexController.php`. Cada apartado incluye:
- Para qué sirve la función.
- A qué ruta corresponde (si tiene sufijo `Action`).
- Si hay restricciones de rol detectables en el código.
- Desarrollo altamente detallado del flujo de la función, explicando línea por línea.

### Índice

- __construct
- indexAction
- documentosAction
- errorAction
- permisoAction
- devAction

---

### __construct

- Para qué sirve: Cargar la identidad del usuario autenticado y preparar un contenedor de sesión.
- Ruta: No aplica (no es una Action).
- Restricciones de rol: No hay restricciones; el constructor simplemente carga identidad si existe.

Flujo detallado (línea a línea):

1. ` $auth = new AuthenticationService();`
   - Crea una instancia del servicio de autenticación de Zend Framework.
   - Este servicio permite consultar si hay un usuario autenticado y obtener su identidad.

2. ` $identity = $auth->getIdentity();`
   - Obtiene la identidad actual (si hay una sesión activa). `identity` normalmente contiene información del usuario como `id_usu`.

3. ` if ($auth->hasIdentity()) {`
   - Comprueba si efectivamente existe una identidad (usuario logueado).
   - Si no hay identidad, el bloque dentro del `if` no se ejecuta y las propiedades de la clase quedan sin inicializar.

4. ` $usuario = new Usuario($identity->id_usu);`
   - Crea la entidad `Usuario` usando el id del usuario autenticado.
   - Esta entidad suele proporcionar métodos para recuperar propiedades del usuario (rol, id, autorizado, etc.).

5. ` $this->_usuario = $usuario;`
   - Guarda la entidad `Usuario` en la propiedad `_usuario` de la clase para usarla en otras acciones.

6. ` $this->_container = new Container('namespace');`
   - Inicializa un contenedor de sesión (Zend Session Container) con el espacio de nombres `'namespace'`.
   - Este contenedor se puede usar para almacenar datos entre peticiones (por ejemplo, filtros, paginación persistente, etc.).

Notas: si no hay usuario autenticado, el controlador seguirá funcionando pero muchas acciones esperan que `_usuario` esté definida; en práctica, las rutas de backend deberían requerir autenticación.

---

### indexAction

- Para qué sirve: Preparar y devolver los datos para la página de inicio del backend: varias listas (inscripciones, candidaturas, cursos, ofertas, empresas, inscritos) según el rol del usuario.
- Ruta: `indexAction` — normalmente mapeada a la ruta raíz del módulo Backend (por ejemplo `/backend` o `/backend/index`).
- Restricciones de rol: El contenido que se muestra varía por rol. El código comprueba valores concretos del rol (`4dmin`, `c4legiado`, `us4ario`) y arma distintos filtros.

Flujo detallado (secciones y líneas clave):

1. `if($this->_usuario->get('rol') == '4dmin'){ Utilidades::ejecutaCronJobs(); }`
   - Si el rol del usuario es `4dmin` (administrador), se llama a `Utilidades::ejecutaCronJobs()`.
   - Esto ejecuta tareas programadas internas. No se pasa ningún parámetro aquí.

2. ` $this->layout()->title = 'Inicio';`
   - Establece el título que usará el layout de la plantilla; es información para la vista.

3. ` $num_autorizados_por_confirmar = 0;`
   - Inicializa la variable que contará usuarios pendientes de confirmar; se calcula después sólo si el rol es admin.

4. Bloque `if/else if` por rol (`4dmin`, `c4legiado`, `us4ario`):
   - Aquí se crean variables `where_*` (condiciones SQL en forma de cadena) y `titulo_*` que determinan qué listados se mostrarán y con qué títulos.
   - Ejemplo: para `4dmin` se crea `$where_inscripciones = 'estado = 1 OR estado = 2 OR estado = 6';`.
   - Nota importante: las condiciones se construyen por concatenación de cadenas. Si más adelante se usan valores tomados del usuario para componerlas, podría existir riesgo de inyección SQL. En este controlador las condiciones construidas aquí son estáticas o basadas en `id` del usuario.

5. ` $fecha_menos_7_dias = strtotime ( '-7 day' , strtotime ( date('Y-m-d H:i:s') ) ) ;` y ` $fecha_menos_7_dias = date ( 'Y-m-d  H:i:s' , $fecha_menos_7_dias );`
   - Calcula la fecha y hora correspondiente a hace 7 días y la formatea para su uso en la condición de candidaturas.

6. ` $db_usu = new \Application\Model\Entity\Usuarios(); $num_autorizados_por_confirmar = $db_usu->num('autorizado = 2');`
   - Crea el modelo/entidad de usuarios y cuenta cuántos usuarios tienen `autorizado = 2` (pendientes de confirmar). Sólo se ejecuta para admin.

7. Para el rol `c4legiado` y `us4ario` se configuran `where_inscritos` para limitar inscritos al propio usuario (usando `$this->_usuario->get('id')`).
   - Ejemplo: `'(id_usu = ' . $this->_usuario->get('id') . ' OR id_cre = ' . $this->_usuario->get('id') . ') AND cursoEstado != 3'`.
   - Aquí se concatena el id del usuario dentro de la condición; esto se considera aceptable si `get('id')` es un entero confiable. Aun así, es preferible parametrizar.

8. Después de configurar las condiciones, el controlador prepara cada lista consultando su modelo correspondiente sólo si la condición `where_*` existe:
   - Inscripciones: si `isset($where_inscripciones)` → instancia `Inscripciones` y llama a `getInscripciones($where, $orderby, 200)` y `numInscripciones($where)`.
   - Candidaturas: similar con `Candidaturas` y `getCandidaturas`.
   - Cursos: `Cursos->get($where_cursos, $orderby)` y `num`.
   - Ofertas y Empresas: llamadas a `Ofertas->getOfertas` / `Empresas->getEmpresas` y contadores.
   - Inscritos (inscripciones a cursos no terminados): `Inscripciones->getInscritos` y contador.

9. ` $view = array(...); return new ViewModel($view);`
   - Construye el array `$view` con todas las variables (listas, contadores y títulos) y lo pasa a la vista creando una `ViewModel`.
   - La vista (plantilla) usará estos datos para mostrar la página principal con sus secciones.

Notas finales: `indexAction` es la función más compleja del controlador porque decide qué mostrar según rol y orquesta varias llamadas a modelos. Las condiciones se transmiten como cadenas a los modelos; revisar esos métodos de modelo (por ejemplo `getInscripciones`) para ver cómo se usan internamente estas condiciones.

---

### documentosAction

- Para qué sirve: Preparar los datos necesarios para el gestor documental (lista de carpetas y permisos para el usuario) y pasar esa información al layout/vista.
- Ruta: `documentosAction`.
- Restricciones de rol: No hay comprobación explícita de rol; en su lugar se solicitan las carpetas a las que el usuario tiene permiso.

Flujo detallado:

1. ` $this->layout()->title = 'Gestor documental';` — establece título de la vista.
2. ` $msg_ok = null; $msg_error = null;` — inicializa mensajes.
3. ` $db_carpetas = new Carpetas();` — instancia el modelo de carpetas.
4. ` $permisos = $db_carpetas->getPermisosUsuarios('id_usu IS NULL OR id_usu = ' . $this->_usuario->get('id'), 'nombrea ASC');`
   - Solicita al modelo los permisos de usuario. La condición pide permisos globales (`id_usu IS NULL`) o específicos del usuario actual (`id_usu = <id>`).

5. ` $carpetas = []; foreach($permisos as $per):` — inicializa array y recorre cada permiso devuelto.
6. ` if(isset($per['permiso']) && $per['permiso'] > 0){ ... }` — si existe un permiso y es mayor que 0, construir la configuración de permisos.
   - Si `permiso == 1` → sólo lectura en raíz: `$arr_per = ['pattern' => '/^\/$/', 'read'=>true, 'write'=>false, ...]`.
   - Si `permiso != 1` → lectura y escritura permitidas.

7. ` $carpeta = new Carpeta($per['id_car']);` — crea la entidad Carpeta para obtener propiedades como hash.
8. ` $carpetas[] = ['id' => $carpeta->getHashFolder(), 'nombre' => $per['nombre'], 'permisos' => $arr_per];` — añade la entrada preparada al array.

9. Tras el `foreach`: si `count($carpetas) > 0` → ` $this->layout()->carpetas_permisos = urlencode(serialize($carpetas));`
   - Serializa y codifica la información para que la vista (o JavaScript) la recupere desde el layout.
   - Si no hay carpetas, se asigna `msg_error` con el texto 'No tiene permisos para este apartado.'

10. Finalmente devuelve `ViewModel` con `usuario` y `ko` (error si existe).

Notas: la serialización y `urlencode` se usan para transportar un array complejo al layout; en la vista se espera que haya código que lo deserialice para uso en el frontend.

---

### errorAction

- Para qué sirve: Renderizar una vista que indique un error de ejecución.
- Ruta: `errorAction`.
- Restricciones de rol: No hay comprobaciones; es una vista informativa.

Flujo línea a línea:

1. ` $this->layout()->title = 'Error de ejecución';` — fija el título.
2. ` $view = array('usuario' => $this->_usuario);` — prepara datos para la vista (pasando la entidad usuario).
3. ` return new ViewModel($view);` — devuelve la `ViewModel` para renderizar la plantilla.

---

### permisoAction

- Para qué sirve: Mostrar una vista que notifica falta de permisos.
- Ruta: `permisoAction`.
- Restricciones de rol: No hay comprobación explícita en este método; normalmente se llama cuando el sistema detecta que el usuario no tiene permiso.

Flujo a nivel de líneas:

1. ` $this->layout()->title = 'Error de permisos';`
2. ` $view = array('usuario' => $this->_usuario);`
3. ` return new ViewModel($view);`

---

### devAction

- Para qué sirve: Ejecutar scripts/tareas de mantenimiento según un parámetro de ruta `v1`.
- Ruta: `devAction`.
- Restricciones de rol: No hay comprobación de rol en este método. Advertencia: por seguridad debería restringirse a administradores.

Flujo detallado (línea a línea):

1. ` $tarea = (int) $this->params()->fromRoute('v1', 0);`
   - Lee el parámetro `v1` de la ruta y lo convierte a entero. Si no existe, toma 0.
   - Convertir a entero es una medida mínima para evitar inyección de datos en comparaciones posteriores.

2. ` if($tarea == 1){ $db_usu = new \Application\Model\Entity\Usuarios(); // $db_usu->rellenaNumColegiado(); }` 
   - Si la tarea es 1, instancia el modelo de usuarios y (comentado) ejecutaría un script para rellenar números de colegiado.

3. ` else if($tarea == 2){ $db = new Empresas(); // $db->eliminarDuplicados(); }`
   - Si la tarea es 2, instancia el modelo de empresas y (comentado) ejecutaría un script para eliminar duplicados.

4. ` if($tarea){ $msg = 'Script finalizado'; }else{ $msg = 'Debe indicar una tarea'; }`
   - Prepara el mensaje de salida según si se indicó una tarea válida o no.

5. ` die($msg);`
   - Finaliza la ejecución y devuelve el texto en crudo.
   - NOTA IMPORTANTE: `die()` detiene la ejecución inmediatamente. Para uso en producción es preferible devolver una `Response` o `ViewModel`, y sobre todo restringir el acceso a esta acción únicamente a administradores.

---

## Observaciones finales y recomendaciones de seguridad

- Evitar dejar acciones de administración sin protección (`devAction`) en entornos accesibles en producción.
- Evitar concatenar valores en condiciones SQL; preferir consultas parametrizadas en los modelos.
- Revisar en los modelos cómo se usan las condiciones (`where_*`) que aquí se construyen como cadenas. Asegurarse de que el model escape o parametrice valores cuando acepten input del usuario.

Si quieres, puedo:

1. Extraer fragmentos de código comentados y añadirlos como ejemplos literales en este mismo Markdown (con trozos de código y explicación junto a cada fragmento).
2. Generar un diff/PR con cambios para restringir `devAction` a administradores y reemplazar `die()` por una Response.
3. Continuar generando documentación MD para otro controlador del módulo Backend.

---

Fin de `docs/IndexController_Documentation.md`
