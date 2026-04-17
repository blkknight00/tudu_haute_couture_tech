function iniciarTour() {
    // Si ya completó el tour alguna vez, no lo mostramos
    if (localStorage.getItem('tourCompletado') === 'true') return;

    const tour = new Shepherd.Tour({
        useModalOverlay: true,
        defaultStepOptions: {
            classes: 'tour-step-custom',
            scrollTo: true,
            cancelIcon: { enabled: true },
        }
    });

    // --- BOTONES ---
    const defaultButtons = [
        {
            text: 'Saltar',
            action: function() { localStorage.setItem('tourCompletado', 'true'); this.cancel(); },
            classes: 'btn btn-sm btn-secondary me-2'
        },
        {
            text: 'Siguiente',
            action: function() { this.next(); },
            classes: 'btn btn-sm btn-primary'
        }
    ];

    const finalButton = [
        {
            text: 'Finalizar',
            action: function() { localStorage.setItem('tourCompletado', 'true'); this.complete(); },
            classes: 'btn btn-sm btn-success'
        }
    ];

    // --- HELPERS ---
    const ensureModalIsClosed = () => {
        return new Promise(resolve => {
            const modalEl = document.getElementById('modalTarea');
            if (!modalEl) return resolve();
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal && modal._isShown) {
                modalEl.addEventListener('hidden.bs.modal', resolve, { once: true });
                modal.hide();
            } else {
                resolve();
            }
        });
    };

    // Helper para deshabilitar el elemento del tour
    const blockTarget = {
        when: {
            show: function() {
                const target = this.getTarget();
                if (target) target.style.pointerEvents = 'none';
            },
            hide: function() {
                const target = this.getTarget();
                if (target) target.style.pointerEvents = 'auto';
            }
        }
    };

    // --- INICIO DEL TOUR ---

    tour.addStep({
        id: 'intro',
        text: '¡Bienvenido a TuDu! 👋<br>¿Te gustaría dar un recorrido rápido por las funciones principales?',
        buttons: [
            { text: 'No, gracias', action: function() { localStorage.setItem('tourCompletado', 'true'); this.cancel(); }, classes: 'btn btn-sm btn-secondary me-2' },
            { text: '¡Vamos!', action: function() { this.next(); }, classes: 'btn btn-sm btn-primary' }
        ]
    });

    tour.addStep({
        id: 'open-modal-step',
        text: 'Aquí comienza todo. Este botón abre la ventana para crear tu primera Idea. <strong>Haz clic en "Siguiente"</strong> para continuar.',
        attachTo: {
            element: () => document.querySelector(window.innerWidth < 992 ? '#btnNuevaIdeaMobile' : '#btnNuevaIdea'),
            on: 'auto'
        },
        beforeShowPromise: ensureModalIsClosed,
        scrollTo: false, // Evita el salto de página
        buttons: [
            {
                text: 'Siguiente',
                action: function() {
                    abrirModalTarea(); // Abrimos el modal programáticamente
                    this.next();
                },
                classes: 'btn btn-sm btn-primary'
            }
        ]
    });

    // --- PASOS DENTRO DEL MODAL ---

    tour.addStep({
        id: 'inside-modal-title',
        text: 'Primero, dale un <strong>Título</strong> claro y conciso a tu idea.',
        attachTo: { element: '#titulo', on: 'bottom' },
        buttons: defaultButtons,
        ...blockTarget,
        // Pequeña pausa para asegurar que el modal está listo
        beforeShowPromise: () => new Promise(resolve => setTimeout(resolve, 400))
    });

    tour.addStep({
        id: 'inside-modal-ia',
        text: '¿Sin inspiración? Usa la <strong>Inteligencia Artificial</strong> para que te ayude a describir mejor tu idea ✨ o a estimar el tiempo ⏳.',
        attachTo: { element: '#ask-gemini-btn', on: 'bottom' },
        buttons: defaultButtons,
        ...blockTarget
    });

    tour.addStep({
        id: 'inside-modal-tags',
        text: 'Usa <strong>Etiquetas</strong> para categorizar tus tareas. ¡Facilita mucho la búsqueda después!',
        attachTo: { element: '#tagsDropdownBtn', on: 'top' },
        scrollTo: false,
        beforeShowPromise: function() {
            return new Promise(resolve => {
                const el = document.querySelector('#tagsDropdownBtn');
                if (el) el.scrollIntoView({ behavior: 'auto', block: 'nearest' });
                setTimeout(resolve, 300);
            });
        },
        buttons: defaultButtons,
        ...blockTarget
    });

    tour.addStep({
        id: 'inside-modal-asignar',
        text: 'Si trabajas en equipo, puedes <strong>asignar</strong> la tarea a uno o más compañeros aquí.',
        attachTo: { element: '#usuarios_ids', on: 'top' },
        scrollTo: false,
        beforeShowPromise: function() {
            return new Promise(resolve => {
                const el = document.querySelector('#usuarios_ids');
                if (el) el.scrollIntoView({ behavior: 'auto', block: 'nearest' });
                setTimeout(resolve, 300);
            });
        },
        buttons: defaultButtons,
        ...blockTarget
    });

    tour.addStep({
        id: 'inside-modal-menciones',
        text: '¡Importante! Puedes <strong>mencionar</strong> a tus compañeros en los comentarios usando <strong>@</strong> (ej: @juan) para enviarles una notificación.',
        attachTo: { element: '#infoMenciones', on: 'top' },
        scrollTo: false,
        beforeShowPromise: function() {
            return new Promise(resolve => {
                const el = document.querySelector('#infoMenciones');
                if (el) el.scrollIntoView({ behavior: 'auto', block: 'nearest' });
                setTimeout(resolve, 300);
            });
        },
        buttons: defaultButtons,
        ...blockTarget
    });

    tour.addStep({
        id: 'close-modal-step',
        text: '¡Perfecto! Ahora <strong>cierra esta ventana</strong> para continuar con el tour por el dashboard.',
        attachTo: { element: '#modalTareaCloseBtn', on: 'bottom' },
        advanceOn: { selector: '#modalTarea', event: 'hidden.bs.modal' },
        buttons: [], // No necesita botones, avanza al cerrar
        scrollTo: false, // Evita el salto de página
        cancelIcon: { enabled: false } // Elimina la 'x' para evitar confusión
    });

    // --- PASOS DE VUELTA EN EL DASHBOARD ---

    tour.addStep({
        id: 'nuevo-proyecto',
        text: 'Puedes organizar tus Tareas en <strong>Proyectos</strong>. Haz clic aquí para crear tu primer proyecto.',
        attachTo: { element: '#btnNuevoProyecto', on: 'bottom' },
        when: { show: () => window.innerWidth >= 768 },
        buttons: defaultButtons
    });

    tour.addStep({
        id: 'filtros-principales',
        text: 'También puedes usar estos <strong>filtros</strong> para encontrar tareas por estado, usuario asignado o prioridad.',
        attachTo: { element: '#filtrosPrincipales', on: 'bottom' },
        buttons: defaultButtons
    });

    tour.addStep({
        id: 'vistas',
        text: '¿Prefieres lo visual? Cambia a la <strong>Vista Tablero</strong> para arrastrar tus tareas como si fueran post-its.',
        attachTo: { element: '#btnVistaTablero', on: 'bottom' },
        when: { show: () => window.innerWidth >= 768 },
        buttons: defaultButtons
    });

    tour.addStep({
        id: 'notificaciones',
        text: 'Recibirás <strong>notificaciones</strong> aquí cuando alguien te mencione en una nota o una tarea esté por vencer.',
        attachTo: { element: '#notifBell', on: 'bottom' },
        buttons: defaultButtons
    });
    
    tour.addStep({
        id: 'usuario',
        text: 'Finalmente, aquí puedes editar tu <strong>perfil</strong>, acceder a la <strong>guía</strong>, reiniciar este tour y <strong>cerrar sesión</strong>.',
        attachTo: { element: '.user-menu', on: 'left' },
        buttons: finalButton
    });
    tour.start();
}