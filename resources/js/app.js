const dialog = document.querySelector('#task-dialog');
const membersDialog = document.querySelector('#members-dialog');
const shoppingDialog = document.querySelector('#shopping-dialog');
const mealDialog = document.querySelector('#meal-dialog');
const noteDialog = document.querySelector('#note-dialog');
const toast = document.querySelector('.toast');
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

document.querySelectorAll('[data-open-task]').forEach((button) => {
    button.addEventListener('click', () => dialog.showModal());
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

document.querySelector('[data-close-task]').addEventListener('click', () => dialog.close());
document.querySelector('[data-open-members]').addEventListener('click', () => membersDialog.showModal());
document.querySelector('[data-close-members]').addEventListener('click', () => membersDialog.close());
document.querySelector('[data-open-shopping]').addEventListener('click', () => shoppingDialog.showModal());
document.querySelector('[data-close-shopping]').addEventListener('click', () => shoppingDialog.close());
document.querySelector('[data-close-meal]').addEventListener('click', () => mealDialog.close());
const openNoteButton = document.querySelector('[data-open-note]');
const closeNoteButton = document.querySelector('[data-close-note]');
if (openNoteButton) openNoteButton.addEventListener('click', () => noteDialog.showModal());
if (closeNoteButton) closeNoteButton.addEventListener('click', () => noteDialog.close());

const notificationsToggle = document.querySelector('[data-notifications-toggle]');
const notificationsPanel = document.querySelector('[data-notifications-panel]');

if (notificationsToggle && notificationsPanel) {
    notificationsToggle.addEventListener('click', () => {
        const expanded = notificationsToggle.getAttribute('aria-expanded') !== 'true';
        notificationsToggle.setAttribute('aria-expanded', String(expanded));
        notificationsPanel.hidden = !expanded;
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.notifications')) closeNotifications();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeNotifications();
    });
    notificationsPanel.querySelectorAll('[data-notification-link]').forEach((link) => {
        link.addEventListener('click', () => {
            const tasksPanel = document.querySelector('.tasks-panel');
            if (tasksPanel) tasksPanel.classList.add('show-all-tasks');
            closeNotifications();
        });
    });
}

function closeNotifications() {
    if (!notificationsToggle || !notificationsPanel) return;
    notificationsToggle.setAttribute('aria-expanded', 'false');
    notificationsPanel.hidden = true;
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

document.querySelectorAll('.shopping-row[data-shopping-id] input').forEach((input) => {
    let hideTimeout;

    input.addEventListener('change', async () => {
        const row = input.closest('.shopping-row');
        window.clearTimeout(hideTimeout);
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
                hideTimeout = window.setTimeout(() => {
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
    });
});

document.querySelectorAll('.task-row[data-task-id] input').forEach((input) => {
    input.addEventListener('change', async () => {
        const row = input.closest('.task-row');
        input.disabled = true;

        try {
            const response = await fetch(`/tasks/${row.dataset.taskId}/toggle`, {
                method: 'PATCH',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            });

            if (!response.ok) throw new Error('No se pudo actualizar la tarea');

            const result = await response.json();
            row.classList.toggle('done', result.completed);
            const message = result.next_due_date
                ? `Tarea completada · siguiente: ${formatLocalDate(result.next_due_date)}`
                : (result.completed ? 'Tarea completada. ¡Buen trabajo!' : 'Tarea marcada como pendiente');
            showToast(message);
        } catch (error) {
            input.checked = !input.checked;
            showToast(error.message);
        } finally {
            input.disabled = false;
        }
    });
});

document.querySelectorAll('[data-task-assignee]').forEach((select) => {
    select.addEventListener('change', async () => {
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
            showToast(`Tarea asignada a ${result.name}`);
        } catch (error) {
            showToast(error.message);
        } finally {
            select.disabled = false;
        }
    });
});

document.querySelectorAll('[data-task-postpone]').forEach((select) => {
    select.addEventListener('change', async () => {
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
            showToast(`Tarea pospuesta al ${result.label}`);
        } catch (error) {
            showToast(error.message);
        } finally {
            select.value = '';
            select.disabled = false;
        }
    });
});

const tasksToggleButton = document.querySelector('[data-toggle-tasks]');

if (tasksToggleButton) {
    tasksToggleButton.addEventListener('click', () => {
        const expanded = tasksToggleButton.getAttribute('aria-expanded') !== 'true';
        document.querySelector('.tasks-panel').classList.toggle('show-all-tasks', expanded);
        tasksToggleButton.setAttribute('aria-expanded', String(expanded));
        tasksToggleButton.firstChild.textContent = expanded ? 'Ver menos ' : 'Ver todas ';
        tasksToggleButton.querySelector('span').textContent = expanded ? '↑' : '↓';
    });
}

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
