# Roadmap TuDu V2.0 (La Evolución Enterprise)

> *"Gestión integral directamente donde ya están tus conversaciones."*

Este documento recopila las funciones avanzadas basadas en la filosofía de sistemas de mensajería modernos (como Telegram), diseñadas para transformar a TuDu de un "SaaS funcional" a una plataforma de automatización fluida y libre de fricciones.

---

## 1. Copiloto Bot (TuDu Agent Avanzado) 🤖
*Actualmente contamos con el cerebro (API y base) pero el objetivo es evolucionar la interfaz.*
- **Tareas por Comandos Slash:** Permitir a los usuarios escribir `/tarea "Revisar contrato" @carlos jueves` y que se cree automáticamente la tarjeta Kanban en el proyecto en turno.
- **Recordatorios Proactivos:** Que el Bot te envíe un mensaje: *"Hola, estas 3 tareas vencen hoy"*, integrándose al flujo del chat como un usuario más.
- **Webhooks de Integración:** Un canal donde el bot avise, por ejemplo, cuando entra un pago de Stripe o cuando falla un despliegue en el servidor.

---

## 2. Mensajes Silenciosos y Programados 🌙
*Cuidando el derecho a la desconexión y el flujo de trabajo asíncrono.*
- **Mensaje Silencioso:** Poder presionar un botón para que el mensaje se envíe sin disparar notificaciones push ni sonidos (Ideal para jefes que recuerdan instrucciones a las 11 PM sin molestar a su equipo).
- **Programar Envío:** Escribir un mensaje y programarlo para enviarlo, por ejemplo, "Lunes a las 8:00 AM".

---

## 3. Temas / Foros en Grupos (Threads) 🗂️
*Evitar el caos en el chat.*
- Para no llenar el chat general del "Proyecto X" con información desordenada, permitir agrupar la conversación en **"Hilos" (Threads)** o **"Salas Temáticas"** (Ej. un foro de 'Diseño', otro de 'Ventas' y otro de 'Desarrollo' dentro del mismo chat del proyecto).

---

## 4. Canales de Difusión Corporativa 📢
*Comunicación vertical eficiente.*
- Canales "One-Way" (de una sola vía) donde únicamente los administradores (ej. Recursos Humanos, CEO) pueden escribir.
- Los empleados solo pueden leer y reaccionar (Likes, Emojis), evitando que los anuncios importantes se pierdan por felicitaciones o comentarios.

---

## 5. Mensajes Guardados (Tu Espacio Personal) 📥
*Nube y cuaderno de notas privado integrado.*
- Un chat del usuario consigo mismo para reenviarse mensajes importantes, adjuntar PDFs, fotos o crear tareas súper rápidas de "Pendientes Personales" que no pertenecen a ningún proyecto empresarial.

---

## Estrategia de Implementación
Recomendamos dejar este Roadmap en el *Backlog* hasta estabilizar y rentabilizar la V1.0. Luego, integrar estas funciones incrementalmente, comenzando con **Mensajes Guardados** y **Comandos Slash** por su alto impacto en la retención de usuarios.
