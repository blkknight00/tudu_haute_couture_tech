# 📘 TuDu - Guía de Usuario y Administración

Bienvenido a **TuDu**, tu sistema colaborativo de gestión de tareas e ideas. Esta guía te ayudará a entender las funcionalidades clave, la instalación y la configuración avanzada.

---

## 🚀 1. Primeros Pasos

### Inicio de Sesión
El sistema ofrece dos métodos de acceso:
1.  **Credenciales Locales**: Usuario y contraseña administrados por el sistema.
2.  **Google Login**: Botón "Continuar con Google" para acceso rápido y seguro (requiere que tu correo esté registrado o que el sistema permita nuevos registros).

### El Dashboard (Tablero Principal)
Al entrar, verás tu centro de mando:
*   **Splash Screen (Resumen Diario)**: Una pantalla inicial que aparece una vez por sesión mostrándote una gráfica de progreso y tareas que vencen hoy.
*   **KPIs**: Contadores en la parte superior para ver rápidamente cuántas tareas están Pendientes, En Progreso o Completadas.
*   **Filtros**: Barra de herramientas para filtrar por Proyecto, Estado, Usuario, Prioridad o Fecha de Vencimiento.
*   **Buscador**: Lupa para encontrar tareas por título o descripción en tiempo real.
*   **Exportar a Excel**: Botón de Excel <i class="bi bi-file-earmark-excel"></i> para descargar la vista actual de tareas.
*   **Vistas**: Alterna entre **Lista** y **Tablero (Kanban)** <i class="bi bi-kanban"></i> para gestionar tus tareas visualmente arrastrando y soltando tarjetas.
*   **Modo Oscuro**: Alterna entre tema claro y oscuro con el icono de sol/luna en la barra superior.

---

## 🛠 2. Funcionalidades Principales

### 📝 Gestión de Tareas (Ideas)
*   **Crear Idea**: Usa el botón **"+"** flotante (móvil) o "Nueva Idea" (escritorio).
    *   **Visibilidad**: Puedes marcar una tarea como **Pública** (todo el equipo la ve) o **Personal** (candado 🔒, solo tú la ves).
    *   **IA Integrada**: Presiona **"Sugerir"** ✨ para que la IA redacte la descripción, o **"Estimar"** ⏳ para que analice la complejidad y sugiera un tiempo.
    *   **Asignación Múltiple**: Asigna una tarea a varios usuarios manteniendo presionada la tecla `Ctrl` (o `Cmd`) en el selector.
    *   **Subtareas (Checklists)**: Divide tareas grandes en pasos pequeños.
    *   **Archivos Adjuntos**: Sube imágenes, documentos o comprimidos. Haz clic en las imágenes para una vista previa rápida.
    *   **Etiquetas (Tags)**: Categoriza tus tareas usando etiquetas de colores.
*   **Estados**:
    *   🔴 **Pendiente**: Tarea por hacer.
    *   🟠 **En Progreso**: Se está trabajando en ella.
    *   🟢 **Completado**: Al marcarla, escucharás un sonido de éxito 🎵 y la tarea quedará lista para archivar.
*   **Comentarios en Tiempo Real**: Agrega notas y ve las respuestas de tus compañeros al instante sin recargar la página.

### 🤝 Colaboración Externa
*   **Compartir con Cliente**: En cada tarea, usa el botón de "Compartir" <i class="bi bi-share-fill"></i> para generar un enlace público seguro.
    *   El cliente podrá ver el progreso, dejar comentarios y subir archivos sin necesidad de registrarse.
    *   Puedes enviar este enlace directamente por WhatsApp desde el mismo menú.

### 📱 Integración con WhatsApp
En cada tarjeta de tarea verás un icono de WhatsApp. Al hacer clic, se despliega una lista de usuarios con teléfono registrado para enviarles un mensaje rápido sobre esa tarea.

### 🔔 Menciones y Notificaciones
*   **Menciones (@)**: En los comentarios, escribe **`@`** para mencionar a un compañero.
*   **Centro de Notificaciones**: El icono de campana 🔔 te avisa de menciones y tareas próximas a vencer.
*   **Alertas de Vencimiento**: El sistema te notificará si tienes tareas que vencen hoy o mañana.

---

## ⚙️ 3. Administración y Configuración

*(Secciones exclusivas para usuarios con rol de Administrador)*

### 👥 Gestión de Usuarios
*   Accede desde el menú de usuario -> **Gestionar Usuarios**.
*   Puedes crear, editar o eliminar usuarios.
*   **Roles**:
    *   `Usuario`: Acceso estándar a tareas públicas y propias.
    *   `Admin`: Gestión de usuarios y proyectos de la organización.
    *   `Super Admin`: Control total del sistema y licencias.

### 📂 Gestión de Proyectos
*   Crea proyectos para organizar las tareas.
*   Los proyectos pueden ser **Públicos** (para toda la organización) o **Privados** (solo para el creador).
*   Los administradores pueden ver y gestionar todos los proyectos, incluso los privados, para auditoría.

### 🤖 Configuración de IA
*   Accede desde el menú -> **Configurar IA**.
*   Vincula tu **API Key de DeepSeek** y personaliza el "Prompt" del sistema.

### 🔒 Auditoría
*   Registro detallado de acciones sensibles (eliminación de tareas, cambios de estado, accesos).
*   Permite rastrear quién hizo qué y cuándo.

### 🔑 Panel de Licencia (Super Admin)
*   Controla el límite máximo de usuarios permitidos en el sistema.
*   Interruptor maestro para activar/desactivar el acceso a la aplicación.

---

## 💻 4. Guía Técnica (Instalación)

### Requisitos del Servidor
*   PHP 7.4 o superior.
*   MySQL / MariaDB.
*   Extensión `curl` habilitada (para Google Login y IA).

### Configuración (`config.php`)
El sistema detecta automáticamente si está en entorno local (XAMPP) o producción. Asegúrate de configurar las credenciales de base de datos correctamente.

### Configuración de Google Login
1.  Edita `login_google.php`.
2.  Coloca tu `CLIENT_ID` y `CLIENT_SECRET` obtenidos en Google Cloud Console.
3.  Asegúrate de que las URIs de redirección en Google coincidan exactamente con tu dominio (ej. `https://tudominio.com/tudu/login_google.php`).

---

## ❓ Preguntas Frecuentes (FAQ)

### ¿Cómo restaurar una tarea eliminada por error?
Si la **archivaste**, ve a "Archivo" en el menú de usuario y pulsa "Restaurar". Si la **eliminaste** permanentemente, no se puede recuperar (pero quedará registro en Auditoría).

### ¿Cómo funciona el enlace público?
El enlace generado es único para esa tarea y expira en 7 días por seguridad. Permite a personas externas ver la tarea y colaborar de forma limitada.

### Solución de problemas de sincronización
El sistema se actualiza en tiempo real cada pocos segundos. Si notas retrasos, verifica tu conexión a internet.

### ¿Cómo reinicio el tour de bienvenida?
Ve al menú de tu perfil (arriba a la derecha) y selecciona **"Reiniciar Tour"**.

---
*TuDu v2.0 - Gestión de Tareas Inteligente.*