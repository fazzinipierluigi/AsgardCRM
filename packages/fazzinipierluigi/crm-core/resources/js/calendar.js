import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import { Modal } from 'bootstrap';

document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('calendar');

    if (!calendarEl) {
        return;
    }

    var routes = window.CALENDAR_ROUTES;
    var canCreate = window.CALENDAR_CAN_CREATE === true;

    var modalEl = document.getElementById('calendar-event-modal');
    var modal = new Modal(modalEl);
    var form = document.getElementById('calendar-event-form');
    var idInput = document.getElementById('calendar-event-id');
    var titleInput = document.getElementById('calendar-event-title');
    var descriptionInput = document.getElementById('calendar-event-description');
    var startInput = document.getElementById('calendar-event-start');
    var endInput = document.getElementById('calendar-event-end');
    var showAsInput = document.getElementById('calendar-event-show-as');
    var statusInput = document.getElementById('calendar-event-status');
    var relatableTypeSelect = document.getElementById('calendar-event-relatable-type');
    var relatableIdSelect = document.getElementById('calendar-event-relatable-id');
    var deleteBtn = document.getElementById('calendar-event-delete-btn');
    var newEventBtn = document.getElementById('calendar-new-event-btn');
    var modalTitle = document.getElementById('calendar-event-modal-title');

    var relatableTypeTom = window.tomSelect(relatableTypeSelect);
    var relatableIdTom = window.tomSelect(relatableIdSelect);

    function toLocalInputValue(date) {
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate())
            + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    function clearFieldErrors() {
        form.querySelectorAll('[data-field-error]').forEach(function (el) {
            el.textContent = '';
            el.classList.add('d-none');
        });
    }

    function showFieldErrors(errors) {
        Object.keys(errors || {}).forEach(function (field) {
            var el = form.querySelector('[data-field-error="' + field + '"]');
            if (el) {
                el.textContent = errors[field][0];
                el.classList.remove('d-none');
            }
        });
    }

    function loadRelatables(type, selectedId) {
        relatableIdTom.clearOptions();

        if (!type) {
            return;
        }

        fetch(routes.relatables + '?type=' + encodeURIComponent(type), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (options) {
                options.forEach(function (option) {
                    relatableIdTom.addOption({ value: option.id, text: option.label });
                });
                relatableIdTom.refreshOptions(false);
                if (selectedId) {
                    relatableIdTom.setValue(String(selectedId));
                }
            });
    }

    relatableTypeSelect.addEventListener('change', function () {
        loadRelatables(relatableTypeSelect.value, null);
    });

    function resetForm() {
        form.reset();
        clearFieldErrors();
        idInput.value = '';
        deleteBtn.classList.add('d-none');
        relatableTypeTom.setValue('');
        relatableIdTom.clearOptions();
    }

    function openCreateModal(start, end) {
        resetForm();
        modalTitle.textContent = modalTitle.dataset.newLabel;
        if (start) {
            startInput.value = toLocalInputValue(start);
        }
        if (end) {
            endInput.value = toLocalInputValue(end);
        }
        modal.show();
    }

    function openEditModal(event) {
        resetForm();
        modalTitle.textContent = modalTitle.dataset.editLabel;
        var props = event.extendedProps;

        idInput.value = event.id;
        titleInput.value = event.title || '';
        descriptionInput.value = props.description || '';
        startInput.value = event.start ? toLocalInputValue(event.start) : '';
        endInput.value = event.end ? toLocalInputValue(event.end) : '';
        showAsInput.value = props.show_as || 'busy';
        statusInput.value = props.status || 'confirmed';

        if (props.relatable_type) {
            relatableTypeTom.setValue(props.relatable_type);
            loadRelatables(props.relatable_type, props.relatable_id);
        }

        if (props.can_delete) {
            deleteBtn.classList.remove('d-none');
        }

        form.dataset.editable = event.startEditable ? '1' : '0';
        titleInput.disabled = !event.startEditable;

        modal.show();
    }

    var calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        height: 'auto',
        selectable: canCreate,
        editable: true,
        events: function (info, successCallback, failureCallback) {
            fetch(routes.events + '?start=' + encodeURIComponent(info.startStr) + '&end=' + encodeURIComponent(info.endStr), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            })
                .then(function (response) { return response.json(); })
                .then(successCallback)
                .catch(failureCallback);
        },
        select: function (selectionInfo) {
            if (canCreate) {
                openCreateModal(selectionInfo.start, selectionInfo.end);
            }
            calendar.unselect();
        },
        eventClick: function (clickInfo) {
            openEditModal(clickInfo.event);
        },
        eventDrop: function (dropInfo) {
            saveEventTiming(dropInfo.event, dropInfo.revert);
        },
        eventResize: function (resizeInfo) {
            saveEventTiming(resizeInfo.event, resizeInfo.revert);
        },
    });

    calendar.render();

    function saveEventTiming(event, revert) {
        fetch(routes.update.replace('__ID__', event.id), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': window.CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                title: event.title,
                description: event.extendedProps.description,
                show_as: event.extendedProps.show_as,
                status: event.extendedProps.status,
                start_datetime: event.start.toISOString().slice(0, 19).replace('T', ' '),
                end_datetime: (event.end || event.start).toISOString().slice(0, 19).replace('T', ' '),
                relatable_type: event.extendedProps.relatable_type,
                relatable_id: event.extendedProps.relatable_id,
            }),
        }).then(function (response) {
            if (!response.ok) {
                revert();
            }
        }).catch(revert);
    }

    if (newEventBtn) {
        newEventBtn.addEventListener('click', function () {
            openCreateModal(new Date(), null);
        });
    }

    deleteBtn.addEventListener('click', function () {
        if (!idInput.value) {
            return;
        }

        fetch(routes.destroy.replace('__ID__', idInput.value), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': window.CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        }).then(function () {
            modal.hide();
            calendar.refetchEvents();
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearFieldErrors();

        var id = idInput.value;
        var payload = {
            title: titleInput.value,
            description: descriptionInput.value,
            start_datetime: startInput.value.replace('T', ' '),
            end_datetime: endInput.value.replace('T', ' '),
            show_as: showAsInput.value,
            status: statusInput.value,
            relatable_type: relatableTypeTom.getValue() || null,
            relatable_id: relatableIdTom.getValue() || null,
        };

        var url = id ? routes.update.replace('__ID__', id) : routes.store;
        var method = id ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': window.CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        }).then(function (response) {
            if (response.status === 422) {
                return response.json().then(function (data) { showFieldErrors(data.errors); });
            }

            if (!response.ok) {
                throw new Error('Request failed');
            }

            modal.hide();
            calendar.refetchEvents();
        });
    });
});
