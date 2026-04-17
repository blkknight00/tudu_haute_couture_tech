# Tareas de Expansión y Mejoras Futuras para TuDu

Este documento recopila Tareas hipotéticas y estrategias técnicas para la evolución de TuDu.

## 1. Login con Google (OAuth 2.0)

Permitir a los usuarios iniciar sesión utilizando sus cuentas de Google para reducir la fricción de registro y mejorar la seguridad.

### Requisitos Técnicos
1.  **Google Cloud Console:** Crear un proyecto, configurar la pantalla de consentimiento OAuth y obtener `CLIENT_ID` y `CLIENT_SECRET`.
2.  **Base de Datos:**
    *   Agregar columna `google_id` (VARCHAR) a la tabla `usuarios`.
    *   Hacer que el campo `password` sea nullable (opcional), ya que los usuarios de Google no tendrán contraseña local.
3.  **Librería:** Utilizar `google/apiclient` vía Composer es lo recomendado.

### Flujo de Implementación
1.  Botón "Entrar con Google" en `login.php`.
2.  Redirección a Google para autenticación.
3.  Callback a un archivo `google_callback.php`.
4.  Verificar si el email existe en la BD:
    *   **Sí:** Iniciar sesión.
    *   **No:** Crear usuario automáticamente con los datos de Google.

---

## 2. Transformación a SaaS / Red Social (Multi-tenancy)

Evolucionar la aplicación de una instalación única a una plataforma donde múltiples organizaciones pueden coexistir, y los usuarios pueden tener perfiles globales.

### Concepto
*   **Multi-tenant:** Separación lógica de datos por organización.
*   **Red Social:** Perfil único de usuario que puede pertenecer a múltiples "espacios de trabajo" o tener tareas personales independientes.

### Cambios en Base de Datos
1.  **Tabla `organizaciones` (Tenants):** ID, nombre, plan de suscripción.
2.  **Tabla `usuarios` (Global):** ID, email, password, foto (Identidad).
3.  **Tabla `miembros_organizacion`:** Relaciona `usuario_id` con `organizacion_id` y `rol`.
4.  **Tablas de Datos (`proyectos`, `tareas`, `notas`):** Agregar columna `organizacion_id`.
    *   *Crucial:* Todas las consultas SQL deben incluir `WHERE organizacion_id = ?` para seguridad.

### Modelo de Negocio (SaaS)
*   Super Admin para gestión de pagos (Stripe/PayPal).
*   Límites por plan (ej. 5 usuarios gratis, ilimitados Pro).

---

## 3. API para conectar TuDu con LexData

Conectar el CRM legal (LexData) con el gestor de tareas (TuDu) para automatizar flujos de trabajo, como alertas de expedientes por vencer.

### Estrategia
LexData actúa como el **Servidor** (proveedor de datos) y TuDu como el **Cliente** (consumidor).

### Paso A: Endpoint en LexData (`api_externa.php`)
Crear este archivo en la carpeta de LexData para exponer los expedientes próximos a vencer.

```php
<?php
// c:\xampp\htdocs\InterData\lexdata\api_externa.php
header('Content-Type: application/json');
require_once 'db.php';

// 1. SEGURIDAD: API KEY SIMPLE
$api_key_esperada = "CLAVE_SECRETA_TuDu_123";
$headers = getallheaders();
$api_key_recibida = $headers['X-API-KEY'] ?? $_GET['api_key'] ?? '';

if ($api_key_recibida !== $api_key_esperada) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado. API Key inválida.']);
    exit;
}

// 2. OBTENER DATOS (Ejemplo: Expedientes por vencer en 15 días)
try {
    $stmt = $pdo->query("
        SELECT id, nombre, numero_expediente, fecha_termino, juzgado 
        FROM expedientes 
        WHERE estatus = 'Activo' 
        AND fecha_eliminado IS NULL 
        AND fecha_termino BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)
        ORDER BY fecha_termino ASC
    ");
    
    $expedientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'origen' => 'LexData CRM', 'data' => $expedientes]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos en LexData']);
}
?>
```

### Paso B: Consumo desde TuDu (Ejemplo conceptual)
Código PHP para obtener estos datos.

```php
$url = 'http://localhost/InterData/lexdata/api_externa.php';
$opts = ["http" => ["header" => "X-API-KEY: CLAVE_SECRETA_TuDu_123"]];
$context = stream_context_create($opts);
$json = file_get_contents($url, false, $context);
$datos_lexdata = json_decode($json, true);
// Procesar $datos_lexdata['data']...
```

---

## 4. Colaboración Avanzada

### @Menciones y Notificaciones

**Concepto:** Permitir que los usuarios se mencionen entre sí en las notas (`@username`) y que el sistema envíe una notificación al usuario mencionado.

**Implementación:**
1.  **Base de Datos:** Crear una tabla `notificaciones` para almacenar las alertas.
    ```sql
    CREATE TABLE `notificaciones` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `usuario_id_destino` int(11) NOT NULL,
      `usuario_id_origen` int(11) NOT NULL,
      `tarea_id` int(11) DEFAULT NULL,
      `tipo` enum('mencion','asignacion') NOT NULL,
      `mensaje` varchar(255) NOT NULL,
      `leido` tinyint(1) NOT NULL DEFAULT 0,
      `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    );
    ```
2.  **Backend (`agregar_nota.php`):** Al guardar una nota, parsear el texto con una expresión regular (`/@(\w+)/`) para encontrar menciones, buscar el ID del usuario y crear la notificación en la nueva tabla.
3.  **Frontend (`dashboard.php`):** Agregar un icono de campana 🔔 en el header que, mediante AJAX, consulte periódicamente si hay notificaciones no leídas y muestre un contador.

### Comentarios en Tiempo Real (WebSockets)

**Concepto:** Cuando un usuario añade un comentario, este aparece instantáneamente en las pantallas de otros usuarios que ven la misma tarea, sin necesidad de recargar la página.

**Implementación (Recomendada):**
*   **Servicio de Terceros (Pusher, Ably):** Es la forma más sencilla y escalable de implementar WebSockets sin gestionar un servidor propio.
    1.  **Backend (`agregar_nota.php`):** Después de guardar la nota en la BD, se utiliza la librería del servicio (ej. Pusher para PHP) para "emitir" un evento en un canal específico (ej. `canal-tarea-123`).
    2.  **Frontend (`dashboard.php`):** Se utiliza la librería JavaScript del servicio para "suscribirse" a ese canal. Cuando se recibe el evento, una función de callback se encarga de añadir el nuevo comentario al DOM dinámicamente.
*   **Alternativa (Compleja):** Montar un servidor de WebSockets propio con tecnologías como **Ratchet (PHP)** o **Socket.IO (Node.js)**, lo cual requiere gestión de procesos en el servidor y es más difícil de desplegar en hostings compartidos.
```