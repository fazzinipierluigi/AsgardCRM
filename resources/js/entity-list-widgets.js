import Chart from 'chart.js/auto';

/**
 * Renders the two read-only list widgets (see resources/views/entities/
 * index.blade.php): a counter fetches a single number, a chart fetches
 * {labels, values, chart_type} and feeds a Chart.js instance. The
 * button widget shares [data-entity-button] with the per-record Button
 * field and needs no code here — see entity-button-field.js.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-counter-widget]').forEach(function (container) {
        fetch(container.dataset.url, { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                container.querySelector('[data-counter-value]').textContent = data.value;
            });
    });

    document.querySelectorAll('[data-chart-widget]').forEach(function (container) {
        fetch(container.dataset.url, { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                new Chart(container.querySelector('[data-chart-canvas]'), {
                    type: data.chart_type,
                    data: {
                        labels: data.labels,
                        datasets: [{ data: data.values }],
                    },
                });
            });
    });
});
