const dialog = document.querySelector('#task-dialog');
const membersDialog = document.querySelector('#members-dialog');
const shoppingDialog = document.querySelector('#shopping-dialog');
const mealDialog = document.querySelector('#meal-dialog');
const noteDialog = document.querySelector('#note-dialog');
const toast = document.querySelector('.toast');
const refreshLoading = document.querySelector('.refresh-loading');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
let appHiddenAt = null;
let appRefreshing = false;

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        appHiddenAt = Date.now();
        return;
    }

    if (appHiddenAt && Date.now() - appHiddenAt >= 5000) {
        refreshAppIfIdle();
    }
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted) refreshAppIfIdle();
});

async function refreshAppIfIdle() {
    const dialogOpen = document.querySelector('dialog[open]');
    const formSubmitting = document.querySelector('[data-submitting="true"]');

    if (dialogOpen || formSubmitting || appRefreshing) return;

    appRefreshing = true;
    refreshLoading?.classList.add('show');
    refreshLoading?.removeAttribute('aria-hidden');

    try {
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 8000);
        const response = await fetch(window.location.href, {
            cache: 'no-store',
            signal: controller.signal,
            headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        });
        window.clearTimeout(timeout);
        if (!response.ok) throw new Error();
        window.location.reload();
    } catch (error) {
        appRefreshing = false;
        refreshLoading?.classList.remove('show');
        refreshLoading?.setAttribute('aria-hidden', 'true');
        showToast('No se pudo actualizar. Comprueba tu conexión.');
    }
}

document.addEventListener('click', (event) => {
    const anchor = event.target.closest('a[href^="#"]');
    if (!anchor) return;

    event.preventDefault();
    const target = anchor.getAttribute('href') === '#'
        ? document.querySelector('#inicio')
        : document.querySelector(anchor.getAttribute('href'));
    target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-fetch-form]');
    if (!form) return;

    event.preventDefault();
    if (form.dataset.submitting === 'true') return;

    const submitButton = event.submitter || form.querySelector('button[type="submit"]');
    const submitButtons = [...form.querySelectorAll('button[type="submit"], input[type="submit"]')];
    if (submitButton && !submitButtons.includes(submitButton)) submitButtons.push(submitButton);
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    submitButtons.forEach((button) => {
        button.dataset.idleLabel = button.value || button.textContent;
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
    });
    if (submitButton?.dataset.submittingLabel) {
        const loadingLabel = submitButton.dataset.submittingLabel;
        if (submitButton.matches('input')) submitButton.value = loadingLabel;
        else submitButton.textContent = loadingLabel;
    }

    const formDialog = form.closest('#task-dialog, #shopping-dialog, #meal-dialog, #note-dialog');
    let optimisticEntry = null;
    let saved = false;

    try {
        optimisticEntry = formDialog ? insertOptimisticEntry(form, formDialog) : null;
        const responsePromise = fetch(form.action, {
            method: (form.method || 'POST').toUpperCase(),
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        });
        await wait(180);
        if (formDialog?.open) formDialog.close();
        const response = await responsePromise;
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validationMessage = Object.values(result.errors || {}).flat()[0];
            throw new Error(validationMessage || result.message || 'No se pudo guardar el cambio');
        }

        saved = true;
        await refreshFragments(result.refresh_url || window.location.href, form.dataset.refresh);
        form.reset();
        showToast(result.message || 'Cambio guardado.');
    } catch (error) {
        if (!saved) {
            optimisticEntry?.rollback();
            if (formDialog && !formDialog.open) formDialog.showModal();
        } else {
            form.reset();
        }
        showToast(error.message);
    } finally {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        submitButtons.forEach((button) => {
            button.disabled = false;
            button.removeAttribute('aria-disabled');
            if (button.matches('input')) button.value = button.dataset.idleLabel;
            else button.textContent = button.dataset.idleLabel;
            delete button.dataset.idleLabel;
        });
    }
});

function wait(milliseconds) {
    return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

function insertOptimisticEntry(form, formDialog) {
    const hiddenEmptyStates = [];
    const hideEmptyStates = (container) => {
        container?.querySelectorAll('.tasks-empty,.tool-empty,.notes-empty,.meal-slot-empty').forEach((element) => {
            if (!element.hidden) {
                element.hidden = true;
                hiddenEmptyStates.push(element);
            }
        });
    };
    const finish = (element) => ({
        rollback() {
            element?.remove();
            hiddenEmptyStates.forEach((emptyState) => { emptyState.hidden = false; });
        },
    });

    if (formDialog.id === 'task-dialog') {
        const container = document.querySelector('#tareas .task-list');
        if (!container) return null;
        hideEmptyStates(container);
        const icon = { home: '🏠', cleaning: '🧹', kitchen: '🍽️', plants: '🌿' }[form.elements.icon?.value] || '🏠';
        const assignee = form.elements.user_id?.selectedOptions[0]?.textContent.trim() || '';
        const entry = document.createElement('div');
        entry.className = 'task-row optimistic-entry';
        entry.innerHTML = `<span class="checkmark"></span><span class="task-icon">${icon}</span><span class="task-copy"><strong></strong><small>Guardando…</small></span><span class="avatar avatar-sage"></span>`;
        entry.querySelector('.task-copy strong').textContent = form.elements.title.value;
        entry.querySelector('.avatar').textContent = assignee.slice(0, 2).toUpperCase();
        container.prepend(entry);
        return finish(entry);
    }

    if (formDialog.id === 'shopping-dialog') {
        const container = document.querySelector('#compra .shopping-list');
        if (!container) return null;
        hideEmptyStates(container);
        const categories = { food: ['🍎', 'Comida'], cleaning: ['🧽', 'Productos de limpieza'], other: ['🛒', 'Otros'] };
        const [icon, category] = categories[form.elements.category.value] || categories.other;
        const entry = document.createElement('div');
        entry.className = 'shopping-row optimistic-entry';
        entry.innerHTML = `<span class="checkmark"></span><span class="shopping-icon">${icon}</span><span class="shopping-copy"><strong></strong><small></small></span><span class="optimistic-status">Guardando…</span>`;
        entry.querySelector('strong').textContent = form.elements.name.value;
        entry.querySelector('small').textContent = `${form.elements.quantity.value || 'Cantidad sin indicar'} · ${category}`;
        container.prepend(entry);
        return finish(entry);
    }

    if (formDialog.id === 'note-dialog') {
        const container = document.querySelector('.house-notes-list');
        if (!container) return null;
        hideEmptyStates(container);
        const entry = document.createElement('article');
        entry.className = 'optimistic-entry';
        entry.innerHTML = '<p></p><footer><small>Publicando…</small></footer>';
        entry.querySelector('p').textContent = form.elements.content.value;
        container.prepend(entry);
        return finish(entry);
    }

    if (formDialog.id === 'meal-dialog') {
        const trigger = document.querySelector(`[data-open-meal][data-date="${CSS.escape(form.elements.meal_date.value)}"][data-type="${CSS.escape(form.elements.meal_type.value)}"]`);
        const container = trigger?.closest('.meal-slot');
        if (!container) return null;
        hideEmptyStates(container);
        const entry = document.createElement('div');
        entry.className = 'meal-entry optimistic-entry';
        entry.innerHTML = '<span><strong></strong><small>Guardando…</small></span>';
        entry.querySelector('strong').textContent = form.elements.name.value;
        container.append(entry);
        return finish(entry);
    }

    return null;
}

async function refreshFragments(url, selectors) {
    if (!selectors) return;
    const response = await fetch(url, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
    if (!response.ok) throw new Error('El cambio se guardó, pero no se pudo actualizar la pantalla');

    const nextDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
    selectors.split(',').map((selector) => selector.trim()).forEach((selector) => {
        const currentFragment = document.querySelector(selector);
        const nextFragment = nextDocument.querySelector(selector);
        if (currentFragment && nextFragment) currentFragment.replaceWith(nextFragment);
    });

    if (selectors.includes('#menu')) {
        initializeMealSlots(document.querySelector('#menu'));
        initializeWeekNavigation('#menu', initializeMealSlots);
    }
}

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-open-task]')) dialog.showModal();
    if (event.target.closest('[data-close-task]')) dialog.close();
    if (event.target.closest('[data-open-members]')) membersDialog.showModal();
    if (event.target.closest('[data-close-members]')) membersDialog.close();
    if (event.target.closest('[data-open-shopping]')) shoppingDialog.showModal();
    if (event.target.closest('[data-close-shopping]')) shoppingDialog.close();
    if (event.target.closest('[data-close-meal]')) mealDialog.close();
    if (event.target.closest('[data-open-note]')) noteDialog.showModal();
    if (event.target.closest('[data-close-note]')) noteDialog.close();
});

const taskDateInput = dialog.querySelector('input[type="date"]');
const taskDateValue = dialog.querySelector('[data-date-value]');

taskDateInput.addEventListener('change', () => {
    if (!taskDateInput.value) {
        taskDateValue.textContent = 'Selecciona una fecha';
        return;
    }

    const [year, month, day] = taskDateInput.value.split('-');
    taskDateValue.textContent = `${day}/${month}/${year}`;
});

document.addEventListener('click', (event) => {
    const notificationsToggle = event.target.closest('[data-notifications-toggle]');
    if (notificationsToggle) {
        const notificationsPanel = document.querySelector('[data-notifications-panel]');
        const expanded = notificationsToggle.getAttribute('aria-expanded') !== 'true';
        notificationsToggle.setAttribute('aria-expanded', String(expanded));
        if (notificationsPanel) notificationsPanel.hidden = !expanded;
        return;
    }

    if (event.target.closest('[data-notification-link]')) {
        const tasksPanel = document.querySelector('.tasks-panel');
        if (tasksPanel) tasksPanel.classList.add('show-all-tasks');
        closeNotifications();
        return;
    }

    if (!event.target.closest('.notifications')) closeNotifications();
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeNotifications();
});

function closeNotifications() {
    const notificationsToggle = document.querySelector('[data-notifications-toggle]');
    const notificationsPanel = document.querySelector('[data-notifications-panel]');
    if (!notificationsToggle || !notificationsPanel) return;
    notificationsToggle.setAttribute('aria-expanded', 'false');
    notificationsPanel.hidden = true;
}

async function refreshNotifications() {
    try {
        await refreshFragments(window.location.href, '.notifications');
    } catch (error) {
        console.error('No se pudo actualizar el centro de notificaciones.', error);
    }
}

function initializeMealSlots(container = document) {
    container.querySelectorAll('[data-open-meal]').forEach((slot) => {
        slot.addEventListener('click', () => {
            const form = mealDialog.querySelector('form');
            const mealId = slot.dataset.mealId;
            form.action = mealId ? `${form.dataset.updateAction}/${mealId}` : form.dataset.storeAction;
            form.querySelector('[data-meal-method]').value = mealId ? 'PUT' : 'POST';
            form.elements.meal_date.value = slot.dataset.date;
            form.elements.meal_type.value = slot.dataset.type;
            form.elements.name.value = slot.dataset.name || '';
            form.elements.notes.value = slot.dataset.notes || '';
            form.elements.ingredients_text.value = slot.dataset.ingredients || '';
            mealDialog.querySelector('[data-meal-dialog-title]').textContent = `${mealId ? 'Editar' : 'Añadir'} ${slot.dataset.type === 'lunch' ? 'comida' : 'cena'} del ${formatLocalDate(slot.dataset.date)}`;
            mealDialog.showModal();
        });
    });
}

initializeMealSlots();

document.addEventListener('change', async (event) => {
    const input = event.target.closest('.shopping-row[data-shopping-id] input');
    if (input) {
        const row = input.closest('.shopping-row');
        row.classList.remove('hiding');
        input.disabled = true;
        try {
            const response = await fetch(`/shopping-items/${row.dataset.shoppingId}/toggle`, {
                method: 'PATCH',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            });
            if (!response.ok) throw new Error('No se pudo actualizar la compra');
            const result = await response.json();
            row.classList.toggle('purchased', result.purchased);
            showToast(result.purchased ? 'Comprado · puedes deshacerlo durante 5 segundos' : 'Devuelto a pendientes');

            if (result.purchased) {
                window.setTimeout(() => {
                    row.classList.add('hiding');
                    window.setTimeout(() => row.remove(), 300);
                }, 5000);
            }
        } catch (error) {
            input.checked = !input.checked;
            showToast(error.message);
        } finally {
            input.disabled = false;
        }
        return;
    }

    const taskInput = event.target.closest('.task-row[data-task-id] input[type="checkbox"]');
    if (taskInput) {
        const row = taskInput.closest('.task-row');
        taskInput.disabled = true;

        try {
            const response = await fetch(`/tasks/${row.dataset.taskId}/toggle`, {
                method: 'PATCH',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            });

            if (!response.ok) throw new Error('No se pudo actualizar la tarea');

            const result = await response.json();
            row.classList.toggle('done', result.completed);
            await refreshNotifications();
            const message = result.next_due_date
                ? `Tarea completada · siguiente: ${formatLocalDate(result.next_due_date)}`
                : (result.completed ? 'Tarea completada. ¡Buen trabajo!' : 'Tarea marcada como pendiente');
            showToast(message);
        } catch (error) {
            taskInput.checked = !taskInput.checked;
            showToast(error.message);
        } finally {
            taskInput.disabled = false;
        }
        return;
    }

    const select = event.target.closest('[data-task-assignee]');
    if (select) {
        const row = select.closest('[data-task-id]');
        select.disabled = true;
        try {
            const response = await fetch(`/tasks/${row.dataset.taskId}/reassign`, {
                method: 'PATCH',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ user_id: select.value }),
            });
            if (!response.ok) throw new Error('No se pudo reasignar la tarea');
            const result = await response.json();
            const avatar = row.querySelector('[data-task-avatar]');
            avatar.className = `avatar avatar-${result.color}`;
            avatar.textContent = result.initials;
            avatar.title = result.name;
            await refreshNotifications();
            showToast(`Tarea asignada a ${result.name}`);
        } catch (error) {
            showToast(error.message);
        } finally {
            select.disabled = false;
        }
        return;
    }

    const postponeSelect = event.target.closest('[data-task-postpone]');
    if (postponeSelect) {
        const select = postponeSelect;
        if (!select.value) return;
        const row = select.closest('[data-task-id]');
        select.disabled = true;
        try {
            const response = await fetch(`/tasks/${row.dataset.taskId}/postpone`, {
                method: 'PATCH',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ until: select.value }),
            });
            if (!response.ok) throw new Error('No se pudo posponer la tarea');
            const result = await response.json();
            row.querySelector('[data-task-date]').textContent = result.label;
            await refreshNotifications();
            showToast(`Tarea pospuesta al ${result.label}`);
        } catch (error) {
            showToast(error.message);
        } finally {
            select.value = '';
            select.disabled = false;
        }
    }
});

document.addEventListener('click', (event) => {
    const tasksToggleButton = event.target.closest('[data-toggle-tasks]');
    if (tasksToggleButton) {
        const expanded = tasksToggleButton.getAttribute('aria-expanded') !== 'true';
        document.querySelector('.tasks-panel').classList.toggle('show-all-tasks', expanded);
        tasksToggleButton.setAttribute('aria-expanded', String(expanded));
        tasksToggleButton.firstChild.textContent = expanded ? 'Ver menos ' : 'Ver todas ';
        tasksToggleButton.querySelector('span').textContent = expanded ? '↑' : '↓';
    }
});

if (window.taskFormHasErrors) dialog.showModal();
if (toast.textContent.trim()) window.setTimeout(() => toast.classList.remove('show'), 2600);

function initializeCalendar() {
    const calendarDataElement = document.querySelector('#calendar-events-data');
    if (!calendarDataElement) return;

    const calendarEvents = JSON.parse(calendarDataElement.textContent);
    const eventsContainer = document.querySelector('[data-calendar-events]');
    const calendarTitle = document.querySelector('[data-calendar-title]');
    const upcomingButton = document.querySelector('[data-calendar-upcoming]');
    const dayLinks = document.querySelectorAll('[data-calendar-date]');

    dayLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const date = link.dataset.calendarDate;

            setSelectedDay(link);
            upcomingButton.classList.remove('active');
            calendarTitle.textContent = `Eventos del ${link.dataset.calendarLabel}`;
            renderCalendarEvents(calendarEvents.filter((item) => item.date === date), true);
        });
    });

    upcomingButton.addEventListener('click', () => {
        dayLinks.forEach((link) => {
            link.classList.remove('selected');
            link.removeAttribute('aria-current');
        });
        upcomingButton.classList.add('active');
        calendarTitle.textContent = 'Próximos eventos';
        renderCalendarEvents(calendarEvents.filter((item) => item.is_upcoming).slice(0, 5), false);
    });

    function setSelectedDay(selectedLink) {
        dayLinks.forEach((link) => {
            const selected = link === selectedLink;
            link.classList.toggle('selected', selected);
            if (selected) link.setAttribute('aria-current', 'date');
            else link.removeAttribute('aria-current');
        });
    }

    function renderCalendarEvents(events, filteredByDay) {
        eventsContainer.replaceChildren();

        if (!events.length) {
            const empty = document.createElement('div');
            empty.className = 'calendar-empty';
            const title = document.createElement('strong');
            title.textContent = filteredByDay ? 'No hay eventos este día' : 'No hay próximos eventos';
            const copy = document.createElement('small');
            copy.textContent = filteredByDay ? 'Elige otro día de la semana o disfruta del hueco.' : 'Tu agenda está despejada.';
            empty.append(title, copy);
            eventsContainer.append(empty);
            return;
        }

        const groupedEvents = Object.groupBy
            ? Object.groupBy(events, (event) => event.date)
            : events.reduce((groups, event) => ({ ...groups, [event.date]: [...(groups[event.date] || []), event] }), {});

        Object.values(groupedEvents).forEach((dayEvents) => {
            const heading = document.createElement('h3');
            heading.className = 'events-day-heading';
            heading.textContent = dayEvents[0].date_label;
            eventsContainer.append(heading);

            dayEvents.forEach((event, index) => {
            const article = document.createElement('article');
            article.className = `event-item ${['sage-event', 'clay-event', 'gold-event'][index % 3]}`;
            const time = document.createElement('time');
            const start = document.createElement('strong');
            start.textContent = event.start;
            const end = document.createElement('small');
            end.textContent = event.end;
            time.append(start, end);

            const details = document.createElement('div');
            const source = document.createElement('span');
            source.className = 'source';
            source.textContent = `${eventsContainer.dataset.calendarName} · ${event.date_label}`;
            const title = document.createElement('strong');
            title.textContent = event.title;
            const location = document.createElement('small');
            location.textContent = event.location;
            details.append(source, title, location);

            const status = document.createElement('span');
            status.className = 'event-status';
            status.title = 'Sincronizado con Google';
            status.textContent = 'G';
            article.append(time, details, status);
            eventsContainer.append(article);
            });
        });
    }
}

initializeCalendar();

initializeWeekNavigation('#menu', initializeMealSlots);
initializeWeekNavigation('#calendario', initializeCalendar);

function initializeWeekNavigation(sectionSelector, initializeSection) {
    const section = document.querySelector(sectionSelector);
    if (!section) return;

    const navigationSelector = sectionSelector === '#menu'
        ? '.menu-week-controls a, .menu-week-controls [data-week-today-url]'
        : '.calendar-week-nav a, .calendar-week-nav [data-week-today-url]';
    section.querySelectorAll(navigationSelector).forEach((control) => {
        control.addEventListener('click', async (event) => {
            event.preventDefault();
            if (section.getAttribute('aria-busy') === 'true') return;

            section.setAttribute('aria-busy', 'true');
            section.querySelectorAll(navigationSelector).forEach((navigationControl) => {
                navigationControl.setAttribute('aria-disabled', 'true');
            });

            try {
                const targetUrl = control.href || control.dataset.weekTodayUrl;
                const response = await fetch(targetUrl, {
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) throw new Error('No se pudo cambiar de semana');

                const nextDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
                const nextSection = nextDocument.querySelector(sectionSelector);
                if (!nextSection) throw new Error('La respuesta no contiene el calendario solicitado');

                if (sectionSelector === '#calendario') {
                    const nextCalendarData = nextDocument.querySelector('#calendar-events-data');
                    const currentCalendarData = document.querySelector('#calendar-events-data');
                    if (!nextCalendarData || !currentCalendarData) {
                        throw new Error('La respuesta no contiene los datos del calendario');
                    }
                    currentCalendarData.replaceWith(nextCalendarData);
                }

                section.replaceWith(nextSection);
                initializeSection(nextSection);
                initializeWeekNavigation(sectionSelector, initializeSection);
            } catch (error) {
                section.removeAttribute('aria-busy');
                section.querySelectorAll(navigationSelector).forEach((navigationControl) => {
                    navigationControl.removeAttribute('aria-disabled');
                });
                showToast(error.message);
            }
        });
    });
}

function showToast(message) {
    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(window.toastTimeout);
    window.toastTimeout = window.setTimeout(() => toast.classList.remove('show'), 2600);
}

function formatLocalDate(date) {
    return new Intl.DateTimeFormat('es-ES', { day: 'numeric', month: 'long' })
        .format(new Date(`${date}T12:00:00`));
}

initializeWebPush();

async function initializeWebPush() {
    const settings = document.querySelector('[data-push-settings]');
    if (!settings) return;

    const button = settings.querySelector('[data-push-toggle]');
    const title = settings.querySelector('[data-push-title]');
    const description = settings.querySelector('[data-push-description]');
    const publicKey = settings.dataset.vapidPublicKey;

    if (!publicKey) {
        button.disabled = true;
        button.textContent = 'No disponible';
        description.textContent = 'El servidor todavía no tiene configuradas las claves de notificación.';
        return;
    }

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        button.disabled = true;
        button.textContent = 'No compatible';
        description.textContent = 'En iPhone, abre esta web desde el icono añadido a la pantalla de inicio.';
        return;
    }

    try {
        const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        const subscription = await registration.pushManager.getSubscription();
        updatePushSettings(Boolean(subscription), Notification.permission);

        button.addEventListener('click', async () => {
            if (button.disabled) return;
            button.disabled = true;

            try {
                const currentSubscription = await registration.pushManager.getSubscription();
                if (currentSubscription) {
                    const response = await fetch(settings.dataset.unsubscribeUrl, {
                        method: 'DELETE',
                        headers: jsonHeaders(),
                        body: JSON.stringify({ endpoint: currentSubscription.endpoint }),
                    });
                    if (!response.ok) throw new Error('No se pudo desactivar la notificación');

                    await currentSubscription.unsubscribe();
                    updatePushSettings(false, Notification.permission);
                    showToast('Notificaciones desactivadas.');
                    return;
                }

                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    updatePushSettings(false, permission);
                    throw new Error('No se concedió permiso para enviar notificaciones');
                }

                const newSubscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicKey),
                });
                const subscriptionData = newSubscription.toJSON();
                subscriptionData.contentEncoding = PushManager.supportedContentEncodings?.[0] || 'aes128gcm';

                const response = await fetch(settings.dataset.subscribeUrl, {
                    method: 'POST',
                    headers: jsonHeaders(),
                    body: JSON.stringify(subscriptionData),
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) {
                    await newSubscription.unsubscribe();
                    throw new Error(result.message || 'No se pudo guardar la suscripción');
                }

                updatePushSettings(true, permission);
                showToast('Notificaciones activadas.');
            } catch (error) {
                showToast(error.message);
            } finally {
                button.disabled = Notification.permission === 'denied';
            }
        });
    } catch {
        button.disabled = true;
        button.textContent = 'No disponible';
        description.textContent = 'No se pudo preparar el servicio de notificaciones.';
    }

    function updatePushSettings(subscribed, permission) {
        settings.classList.toggle('is-enabled', subscribed);
        title.textContent = subscribed ? 'Notificaciones activadas' : 'Notificaciones del iPhone';
        button.textContent = subscribed ? 'Desactivar' : 'Activar';

        if (permission === 'denied') {
            description.textContent = 'El permiso está bloqueado. Actívalo desde los ajustes del iPhone.';
            button.textContent = 'Bloqueadas';
            button.disabled = true;
        } else {
            description.textContent = subscribed
                ? 'Te avisaremos de tareas asignadas y nuevas notas familiares.'
                : 'Recibe avisos de tareas asignadas y nuevas notas familiares.';
        }
    }
}

function jsonHeaders() {
    return {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function urlBase64ToUint8Array(value) {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);

    return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)));
}
