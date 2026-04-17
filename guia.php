<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

$usuario_actual_nombre = $_SESSION['usuario_nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guía de Uso - TuDu</title>
    <link rel="icon" type="image/png" href="icons/icon-96x96.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .guide-section { margin-bottom: 3rem; }
        .guide-icon { font-size: 1.5rem; margin-right: 0.5rem; color: var(--accent-color); }
        .card { border-radius: 15px; border: none; }
    </style>
</head>
<body>
    <!-- Header Fijo -->
    <header class="main-header">
        <div class="header-content container">
            <div class="header-left">
                <a class="header-logo" href="dashboard.php">
                    <img src="icons/logo_top.png" alt="TuDu Logo" style="height: 40px;">
                </a>
            </div>
            <div class="header-right d-flex align-items-center">
                 <a href="dashboard.php" class="btn btn-outline-light me-2 d-none d-md-inline-block">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <div class="dropdown user-menu">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($usuario_actual_nombre) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-power"></i> Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <div class="container my-5 pt-4">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="mb-4 text-center" style="color: var(--accent-color);"><i class="bi bi-book-half"></i> Guía de Usuario y Administración</h1>
                <p class="lead text-center text-muted mb-5">Bienvenido a <strong>TuDu</strong>. Esta guía te ayudará a sacar el máximo provecho de tu sistema colaborativo.</p>

                <hr class="my-5">

                <!-- 1. Primeros Pasos -->
                <div class="guide-section">
                    <h3 class="mb-3"><i class="bi bi-rocket-takeoff guide-icon"></i> 1. Primeros Pasos</h3>
                    
                    <h5 class="mt-4">Inicio de Sesión</h5>
                    <p>El sistema ofrece dos métodos de acceso:</p>
                    <ul>
                        <li><strong>Credenciales Locales:</strong> Usuario y contraseña administrados por el sistema.</li>
                        <li><strong>Google Login:</strong> Botón "Continuar con Google" para acceso rápido y seguro.</li>
                    </ul>

                    <h5 class="mt-4">El Dashboard (Tablero Principal)</h5>
                    <p>Al entrar, verás tu centro de mando:</p>
                    <ul>
                        <li><strong>Splash Screen:</strong> Resumen diario con gráfica de progreso (aparece una vez por sesión).</li>
                        <li><strong>Tour Guiado:</strong> La primera vez que inicies sesión, un tour te guiará por las funciones clave. Los administradores pueden reiniciarlo desde el menú de su perfil.</li>
                        <li><strong>Modo Oscuro:</strong> Cambia entre tema claro y oscuro usando el icono de la luna/sol <i class="bi bi-moon-stars-fill"></i> en la barra superior.</li>
                        <li><strong>KPIs:</strong> Contadores rápidos de tareas Pendientes, En Progreso y Completadas.</li>
                        <li><strong>Filtros:</strong> Barra para filtrar por Proyecto, Estado o Usuario.</li>
                        <li><strong>Buscador:</strong> Lupa para encontrar tareas en tiempo real.</li>
                        <li><strong>Exportar a Excel:</strong> Un botón <i class="bi bi-file-earmark-excel"></i> te permite descargar la vista de tareas que estás filtrando actualmente en formato Excel (.xlsx).</li>
                        <li><strong>Vistas:</strong> Alterna entre <strong>Lista</strong> y <strong>Tablero (Kanban)</strong> <i class="bi bi-kanban"></i> para gestionar tus tareas visualmente arrastrando y soltando tarjetas.</li>
                    </ul>
                </div>

                <hr>

                <!-- 2. Funcionalidades -->
                <div class="guide-section">
                    <h3 class="mb-3"><i class="bi bi-tools guide-icon"></i> 2. Funcionalidades Principales</h3>

                    <h5 class="mt-4">📝 Gestión de Tareas (Ideas)</h5>
                    <ul>
                        <li><strong>Crear Idea:</strong> Usa el botón <strong>"+"</strong> o "Nueva Idea".
                            <ul>
                                <li><strong>Visibilidad:</strong> Pública (equipo) o Personal (candado 🔒).</li>
                                <li><strong>IA Integrada:</strong> Presiona <strong>"Sugerir"</strong> ✨ para que la IA redacte la descripción, o <strong>"Estimar"</strong> ⏳ para que analice la complejidad y sugiera un tiempo de completado.</li>
                                <li><strong>Asignación Múltiple:</strong> (Solo Admin) Asigna tareas a varios usuarios usando <code>Ctrl</code> + Clic.</li>
                                <li><strong>Archivos Adjuntos:</strong> Sube imágenes, documentos o archivos comprimidos a tus tareas. Busca el icono de clip 📎 para verlos.</li>
                                <li><strong>Etiquetas:</strong> Organiza tareas usando un menú desplegable para seleccionar una o más etiquetas predefinidas.</li>
                            </ul>
                        </li>
                        <li><strong>Estados:</strong>
                            <ul>
                                <li>🔴 <strong>Pendiente</strong></li>
                                <li>🟠 <strong>En Progreso</strong></li>
                                <li>🟢 <strong>Completado</strong> (con sonido de éxito 🎵)</li>
                            </ul>
                        </li>
                        <li><strong>Comentarios en Tiempo Real:</strong> Agrega notas y ve las respuestas de tus compañeros al instante sin recargar la página.</li>
                    </ul>

                    <h5 class="mt-4">🤝 Colaboración Externa</h5>
                    <ul>
                        <li><strong>Compartir con Cliente:</strong> En cada tarea, usa el botón de "Compartir" <i class="bi bi-share-fill"></i> para generar un enlace público seguro.</li>
                        <li>El cliente podrá ver el progreso, dejar comentarios y subir archivos sin necesidad de registrarse.</li>
                        <li>Puedes enviar este enlace directamente por WhatsApp desde el mismo menú.</li>
                    </ul>

                    <h5 class="mt-4">📱 Integración con WhatsApp</h5>
                    <p>En cada tarjeta, el icono de WhatsApp despliega una lista de usuarios para enviarles un enlace directo a la tarea.</p>

                    <h5 class="mt-4">🔔 Menciones y Notificaciones</h5>
                    <ul>
                        <li><strong>Menciones (@):</strong> En las notas, escribe <strong>@</strong> para ver una lista de usuarios sugeridos. Al seleccionar uno, recibirán una alerta.</li>
                        <li><strong>Campana:</strong> Revisa tus menciones no leídas en el icono 🔔 de la barra superior.</li>
                        <li><strong>Recordatorios:</strong> Recibirás alertas automáticas para tareas que vencen hoy o mañana.</li>
                    </ul>
                </div>

                <hr>

                <!-- 3. Administración -->
                <div class="guide-section">
                    <h3 class="mb-3"><i class="bi bi-gear-fill guide-icon"></i> 3. Administración y Configuración</h3>
                    <p class="text-muted"><em>(Secciones exclusivas para usuarios con rol de Administrador)</em></p>

                    <h5 class="mt-4">👥 Gestión de Usuarios</h5>
                    <p>Accede desde el menú de usuario -> <strong>Gestionar Usuarios</strong>. Puedes crear, editar o eliminar usuarios y asignar roles (Admin/Usuario).</p>

                    <h5 class="mt-4">📂 Gestión de Proyectos</h5>
                    <p>Crea proyectos para organizar las tareas. Los proyectos pueden ser <strong>Públicos</strong> (para toda la organización) o <strong>Privados</strong> (solo para el creador). Los administradores pueden ver y gestionar todos los proyectos.</p>

                    <h5 class="mt-4">🤖 Configuración de IA</h5>
                    <p>Accede desde el menú -> <strong>Configurar IA</strong>. Vincula tu API Key de DeepSeek y personaliza el "Prompt" para ajustar cómo te ayuda la IA.</p>

                    <h5 class="mt-4">🔒 Auditoría</h5>
                    <p>El sistema registra acciones sensibles. Accede desde el menú -> <strong>Auditoría</strong> para ver el historial de quién hizo qué y cuándo.</p>

                    <h5 class="mt-4">🔑 Panel de Licencia (Super Admin)</h5>
                    <p>Controla el límite máximo de usuarios permitidos en el sistema y el interruptor maestro para activar/desactivar el acceso a la aplicación.</p>
                </div>

                <hr>

                <!-- 5. FAQ -->
                <div class="guide-section">
                    <h3 class="mb-3"><i class="bi bi-question-circle guide-icon"></i> 5. Preguntas Frecuentes (FAQ)</h3>
                    
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                    ¿Cómo restaurar una tarea eliminada por error?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Si has <strong>archivado</strong> una tarea, puedes recuperarla yendo al menú de usuario > <strong>Archivo</strong>. Allí encontrarás la opción "Restaurar".<br>
                                    <small class="text-danger">Nota: Si eliminaste la tarea permanentemente (icono de basura), no se puede recuperar.</small>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    ¿Cómo exportar mis tareas a Excel/PDF?
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Usa el botón de Excel <i class="bi bi-file-earmark-excel"></i> que se encuentra junto al contador de tareas en el dashboard. Esto descargará un archivo .xlsx con las tareas que estás viendo en pantalla, respetando los filtros aplicados. Para generar un PDF, puedes usar la función de impresión del navegador (<code>Ctrl + P</code>) sobre la página del dashboard.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingLink">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLink" aria-expanded="false" aria-controls="collapseLink">
                                    ¿Cómo funciona el enlace público?
                                </button>
                            </h2>
                            <div id="collapseLink" class="accordion-collapse collapse" aria-labelledby="headingLink" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    El enlace generado es único para esa tarea y expira en 7 días por seguridad. Permite a personas externas ver la tarea y colaborar de forma limitada.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    ¿Cómo configurar notificaciones por email?
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Las notificaciones actuales son internas (campana 🔔). La integración con correo electrónico está en desarrollo. Asegúrate de mantener tu email actualizado en tu perfil.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFour">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                    Solución de problemas de sincronización
                                </button>
                            </h2>
                            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    El sistema se actualiza en tiempo real cada pocos segundos. Si notas retrasos, verifica tu conexión a internet.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFive">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                                    ¿Cómo puedo volver a ver el tour guiado?
                                </button>
                            </h2>
                            <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    ¡Claro! Si eres administrador, haz clic en el menú de tu perfil (arriba a la derecha) y selecciona la opción "Reiniciar Tour". El tour se mostrará la próxima vez que cargues el dashboard.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-5">
                    <a href="dashboard.php" class="btn btn-primary btn-lg">Ir al Dashboard</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>