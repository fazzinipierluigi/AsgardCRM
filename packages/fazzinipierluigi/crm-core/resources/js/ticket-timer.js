/**
 * Drives the Ticket record page's header timer (see
 * resources/views/entities/_ticket-timer-buttons.blade.php) — server
 * side already knows whether it's running (`timer_avviato_il`), so a
 * page reload never loses progress; this just ticks the on-screen clock
 * for the current session (elapsed since start, reset to 00:00:00 on
 * stop) and keeps it in sync with the server's response. The total ever
 * tracked (`tempo_tracciato_minuti`) is persisted server-side but not
 * shown here. A user can have this running on several tickets at once —
 * each `[data-ticket-timer]` root tracks its own state independently.
 */
function pad(value) {
    return String(value).padStart(2, '0');
}

function formatHms(totalSeconds) {
    const seconds = Math.max(0, Math.round(totalSeconds));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;

    return `${pad(hours)}:${pad(minutes)}:${pad(secs)}`;
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-ticket-timer]').forEach(function (root) {
        const toggle = root.querySelector('[data-ticket-timer-toggle]');
        const display = root.querySelector('[data-ticket-timer-display]');

        let running = root.dataset.running === '1';
        let startedAt = root.dataset.startedAt ? new Date(root.dataset.startedAt) : null;
        let intervalId = null;

        function currentSeconds() {
            if (running && startedAt) {
                return (Date.now() - startedAt.getTime()) / 1000;
            }

            return 0;
        }

        function render() {
            display.textContent = formatHms(currentSeconds());
        }

        function stopTicking() {
            if (intervalId) {
                clearInterval(intervalId);
                intervalId = null;
            }
        }

        function startTicking() {
            stopTicking();
            intervalId = setInterval(render, 1000);
        }

        function applyRunningState() {
            toggle.classList.toggle('btn-danger', running);
            toggle.classList.toggle('btn-primary', !running);

            const label = running ? window.TICKET_TIMER_I18N.stop : window.TICKET_TIMER_I18N.start;
            toggle.title = label;
            toggle.setAttribute('aria-label', label);
            toggle.innerHTML = running ? window.TICKET_TIMER_I18N.iconStop : window.TICKET_TIMER_I18N.iconPlay;

            if (running) {
                startTicking();
            } else {
                stopTicking();
            }

            render();
        }

        toggle.addEventListener('click', function () {
            const wasRunning = running;
            const url = wasRunning ? root.dataset.stopUrl : root.dataset.startUrl;

            toggle.disabled = true;

            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN, Accept: 'application/json' },
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        if (window.showEntityButtonToast) {
                            window.showEntityButtonToast(result.data.message, 'error');
                        }

                        return;
                    }

                    if (wasRunning) {
                        startedAt = null;
                        running = false;
                    } else {
                        startedAt = new Date(result.data.started_at);
                        running = true;
                    }

                    applyRunningState();
                })
                .finally(function () {
                    toggle.disabled = false;
                });
        });

        applyRunningState();
    });
});
