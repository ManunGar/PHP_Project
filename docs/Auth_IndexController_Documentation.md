# Auth IndexController Documentation

## Índice
1. Visión general
2. `indexAction`
3. `__construct`
4. `getAuth`
5. `logoutAction`
6. `errorAction`
7. `passAction`
8. `requestpassAction`
9. Mensajes y estados (`msg_ok`, `msg_ko`, `msg_info`)
10. Seguridad y posibles mejoras

---
## 1. Visión general
El controlador `Auth\Controller\IndexController` centraliza tres áreas:
- Identificación: Login y (opcional) registro rápido de nuevos usuarios en el mismo formulario.
- Recuperación de contraseña: Solicitud de email para enviar enlace y posterior establecimiento de nueva clave.
- Gestión de sesión: Logout y redirecciones condicionales posteriores a login (inscripción en curso, sección a la que se quería acceder, etc.).

Toda la lógica se concentra principalmente en `indexAction`. El resto de métodos son auxiliares y cortos. Se usa **Zend Framework** (componentes MVC, sesión y autenticación DBTable) y entidades internas (`Usuario`, `Usuarios`).

---
## 2. `indexAction`
Propósito: Vista y procesamiento del formulario de identificación (login) y, cuando procede, registro básico.

Ruta:
- Nombre de ruta: `auth/default`
- Patrón típico: `/auth/index/index` (GET y POST)

Restricción de roles:
- Pública (sin restricción de roles). Si el usuario ya está autenticado y accede por GET, se le redirige a `logout` para evitar estados inconsistentes.

Flujo detallado:
`indexAction` sirve tanto GET como POST:
- GET: Muestra formulario. Detecta si el usuario ya está identificado y redirige a logout para evitar inconsistencias. Gestiona mensajes (éxito/error) según parámetro `v1` en la ruta.
- POST: Puede incluir datos de registro (si se envía `id_usu`) y/o credenciales de login.

### 2.1 Variables iniciales
- `msg_ok`, `msg_ko`, `msg_info`: contenedores de mensajes para la vista.
- `usuario`: instancia base `Usuario(0)` usada para rellenar datos de registro y luego potencialmente autenticar.
- `login`: bandera booleana. Si tras validar registro hay error, se pone a `false` para impedir intento de login automático al finalizar.

### 2.2 Registro
Se identifica porque llega `id_usu` en POST. Se construye `$data` con muchos campos por defecto (fecha alta, flags, etc.). Después:
1. Se filtran entradas para prevenir HTML simple (`StripTags`).
2. `set($data, 2)` asigna valores y realiza validaciones internas (devuelve conteo de vacíos).
3. `revisaEmailYNif()` valida duplicidad Email / NIF → retorna códigos 0 (OK), 1 (email duplicado), 2 (NIF duplicado).
4. Se comprueba aceptación de política de privacidad.
5. Si falla algo: se setea `msg_ko` y `login = false`.
6. Si todo OK: `save()` persiste y si retorna 0 se considera error operativo (`msg_info`).

### 2.3 Login
Sólo se ejecuta si `login === true` (registro no ha fallado). Pasos:
1. Filtrar `email` y `pwd`.
2. Determinar columna identidad: si no contiene `@`, se asume número de colegiado y se formatea con ceros a la izquierda.
3. Configurar `AuthAdapter` sobre tabla `usuarios` con contraseña cifrada mediante `AES_ENCRYPT` usando la llave obtenida de `Usuario`.
4. Ejecutar `authenticate()` → objeto `Result`.
5. Switch sobre `Result::getCode()`:
   - `FAILURE_IDENTITY_NOT_FOUND`: email/colegiado no existe.
   - `FAILURE_CREDENTIAL_INVALID`: credencial incorrecta.
   - `SUCCESS`: se escribe identidad en storage y opcionalmente `rememberMe()` (14 días). Se comprueba si el usuario está de baja (`baja !== null`).
6. Si baja: limpiar identidad y mostrar mensaje. Si activo:
   - `registro(0)` registra entrada.
   - Redirecciones condicionales: inscripción pendiente (`id_cur_inscripcion`), sección a la que se intentaba acceder (`redirect_section`), o dashboard backend.

### 2.4 Mensajes GET (`id_msg`)
Se interpreta parámetro `v1`:
- 1: Email recuperación enviado.
- 2: Error en envío de email.
- 3: Contraseña restablecida OK.
- 4: Email no registrado.

### 2.5 Return
Devuelve `ViewModel` con todos los mensajes y el objeto usuario (útil para re-rellenar formulario tras error).

---
## 3. `__construct`
Propósito: Inyectar dependencias y preparar contenedor de sesión.

Ruta: N/A (no es una acción)

Restricción de roles: N/A

Flujo detallado:
Inyecta `SendMail` (servicio para enviar emails de recuperación) y crea un `Container` de sesión con namespace `namespace`. Facilita acceso a variables de sesión usadas en redirecciones (ej. `id_cur_inscripcion`).

---
## 4. `getAuth`
Propósito: Obtener y cachear la instancia de `AuthenticationService`.

Ruta: N/A (no es una acción)

Restricción de roles: N/A

Flujo detallado:
Crea y almacena una instancia de `AuthenticationService` en `$this->auth` para reutilizar en operaciones de login / comprobación de sesión. Devuelve la instancia para encadenamiento rápido.

---
## 5. `logoutAction`
Propósito: Cerrar la sesión actual y limpiar persistencia.

Ruta:
- Nombre de ruta: `auth/default`
- Patrón típico: `/auth/index/logout`

Restricción de roles:
- Accesible aun sin rol explícito; su efecto depende de que haya una identidad activa. Si existe identidad, registra salida; en cualquier caso limpia la sesión.

Flujo detallado:
1. Si existe identidad, registra salida mediante `Usuario->registro(1)`.
2. Limpia identidad (`clearIdentity`).
3. Elimina persistencia de sesión prolongada (`forgetMe`).
4. Redirige a pantalla de login (`indexAction`).

Efecto secundario: actualiza registro de actividad del usuario.

---
## 6. `errorAction`
Propósito: Canalizar a la vista de error del backend.

Ruta:
- Nombre de ruta: `backend/default`
- Patrón típico: `/backend/index/error`

Restricción de roles:
- No se aplica lógica de rol aquí; simplemente redirige.

Flujo detallado:
Simplemente redirige a controlador `backend/index` acción `error` para compartir una pantalla global de incidencias.

---
## 7. `passAction`
Propósito: Iniciar proceso de recuperación de contraseña enviando email con enlace.

Ruta:
- Nombre de ruta: `auth/default`
- Patrón típico: `/auth/index/pass` (POST)

Restricción de roles:
- Pública (pensada para usuarios no autenticados que han olvidado la clave).

Flujo detallado:
Proceso inicial donde se introduce el email:
1. Si POST: filtra email y busca en `Usuarios` mediante `email LIKE`.
2. Si existe: llama `Notificaciones::recuperarClave($usuario, $sendMail)`. El método arma y envía email con hash codificado.
3. Setea `id_msg` para redirigir de vuelta a `indexAction` y mostrar feedback.
4. Si no existe el email: `id_msg = 4` (email no registrado).

Redirección final: `/auth/index/index/v1/{id_msg}`.

---
## 8. `requestpassAction`
Propósito: Establecer nueva contraseña a partir del enlace recibido por email.

Ruta:
- Nombre de ruta: `auth/default`
- Patrones típicos:
   - GET: `/auth/index/requestpass/:v1` (donde `v1` porta el hash codificado)
   - POST: `/auth/index/requestpass` (envía `hash`, `pwd`, `pwdrepeat`)

Restricción de roles:
- Pública para usuarios no autenticados. Si hay identidad activa, se redirige a `logout` para impedir conflictos.

Flujo detallado:
Se accede desde enlace del email (con hash codificado). Flujo:
1. GET: valida que el usuario no esté logueado; extrae `hash` de la ruta (`v1`).
2. POST: recibe `hash`, `pwd`, `pwdrepeat`.
3. Verifica coincidencia de ambas contraseñas.
4. Decodifica `hash` (`base64_decode` + `explode('h/h', ...)`) → partes: número codificado e email.
5. Calcula `id_usu` con fórmula inversa (hexadecimal / 7 / 11) y busca por email.
6. Si encuentra usuario: `setClave($pass)` y redirige con mensaje éxito (3). Si no: redirige con error (4).
7. Si contraseñas no coinciden: se mantiene en la vista con mensaje de error.

---
## 9. Mensajes y estados
- `msg_ok`: Confirmaciones (email enviado, contraseña restablecida, etc.).
- `msg_ko`: Errores (credenciales inválidas, baja, duplicidades, email inexistente, fallo email).
- `msg_info`: Información neutral (explicación previa a inscripción). Se usa sobre todo cuando se inicia proceso de inscripción tras login.

---
## 10. Seguridad y posibles mejoras
| Aspecto | Situación actual | Mejora sugerida |
|---------|------------------|-----------------|
| SQL concatenado | `email LIKE "..."` | Parametrizar consultas para evitar inyección accidental |
| Fuerza bruta | Sin límite de intentos | Contador por IP / usuario, bloqueo temporal |
| Almacenamiento contraseña | Comparación con AES_ENCRYPT | Migrar a `password_hash`/`password_verify` (bcrypt/argon2) |
| Recuperación hash | Sin expiración | Incorporar timestamp + caducidad y firma HMAC |
| Registro/login acoplados | Lógica mezclada | Separar rutas / acciones para claridad y control de validaciones |
| Sesión rememberMe | 14 días fijo | Configurable vía archivo de configuración |

---
## Resumen rápido
El controlador permite que un visitante se registre y se autentique en un único POST, gestionando de forma secuencial validaciones y, si todo sale bien, inicia sesión y redirige según contexto previo (inscripción o sección protegida). También ofrece mecanismos básicos de recuperación de contraseña basados en un hash codificado en email, aunque con oportunidades claras de refuerzo de seguridad.

---
## Próximos pasos sugeridos
1. Separar registro en `registerAction` para reducir complejidad de `indexAction`.
2. Implementar capa de repositorio con sentencias preparadas para evitar concatenaciones.
3. Migrar cifrado de contraseña a algoritmo moderno (bcrypt/argon2) y proceso de rotación.
4. Añadir expiración y firma al hash de recuperación.
5. Incluir contador de intentos fallidos y CAPTCHA opcional.

---
Documentación generada automáticamente con comentarios pedagógicos para facilitar comprensión a personas sin experiencia previa en PHP/Zend.
