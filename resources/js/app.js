const dialog = document.querySelector('#task-dialog');
const membersDialog = document.querySelector('#members-dialog');
const shoppingDialog = document.querySelector('#shopping-dialog');
const mealDialog = document.querySelector('#meal-dialog');
const toast = document.querySelector('.toast');
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

document.querySelectorAll('[data-open-task]').forEach((button) => {
    button.addEventListener('click', () => dialog.showModal());
});

document.querySelector('[data-close-task]').addEventListener('click', () => dialog.close());
document.querySelector('[data-open-members]').addEventListener('click', () => membersDialog.showModal());
document.querySelector('[data-close-members]').addEventListener('click', () => membersDialog.close());
document.querySelector('[data-open-shopping]').addEventListener('click', () => shoppingDialog.showModal());
document.querySelector('[data-close-shopping]').addEventListener('click', () => shoppingDialog.close());
document.querySelector('[data-close-meal]').addEventListener('click', () => mealDialog.close());

document.querySelectorAll('[data-open-meal]').forEach((slot) => {
    slot.addEventListener('click', () => {
        const form = mealDialog.querySelector('form');
        form.elements.meal_date.value = slot.dataset.date;
        form.elements.meal_type.value = slot.dataset.type;
        form.elements.name.value = slot.dataset.name || '';
        form.elements.notes.value = slot.dataset.notes || '';
        mealDialog.querySelector('[data-meal-dialog-title]').textContent = `${slot.dataset.type === 'lunch' ? 'Comida' : 'Cena'} del ${formatLocalDate(slot.dataset.date)}`;
        mealDialog.showModal();
    });
});

document.querySelectorAll('.shopping-row[data-shopping-id] input').forEach((input) => {
    input.addEventListener('change', async () => {
        const row = input.closest('.shopping-row');
        input.disabled = true;
        try {
            const response = await fetch(`/shopping-items/${row.dataset.shoppingId}/toggle`, {
                method: 'PATCH',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            });
            if (!response.ok) throw new Error('No se pudo actualizar la compra');
            const result = await response.json();
            row.classList.toggle('purchased', result.purchased);
            showToast(result.purchased ? 'Marcado como comprado' : 'Devuelto a pendientes');
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
            showToast(result.completed ? 'Tarea completada. ¡Buen trabajo!' : 'Tarea marcada como pendiente');
        } catch (error) {
            input.checked = !input.checked;
            showToast(error.message);
        } finally {
            input.disabled = false;
        }
    });
});

if (window.taskFormHasErrors) dialog.showModal();
if (toast.textContent.trim()) window.setTimeout(() => toast.classList.remove('show'), 2600);

const calendarDataElement = document.querySelector('#calendar-events-data');

if (calendarDataElement) {
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
            updateCalendarUrl(date);
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
        updateCalendarUrl(null);
    });

    function setSelectedDay(selectedLink) {
        dayLinks.forEach((link) => {
            const selected = link === selectedLink;
            link.classList.toggle('selected', selected);
            if (selected) link.setAttribute('aria-current', 'date');
            else link.removeAttribute('aria-current');
        });
    }

    function updateCalendarUrl(date) {
        const url = new URL(window.location.href);
        if (date) url.searchParams.set('date', date);
        else url.searchParams.delete('date');
        window.history.pushState({ calendarDate: date }, '', `${url.pathname}${url.search}#calendario`);
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

        events.forEach((event, index) => {
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
    }
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
