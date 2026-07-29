import '@maxgraph/core/css/common.css';
import { Graph, InternalEvent, getDefaultPlugins } from '@maxgraph/core';

/**
 * Read-only rendering of one workflow instance's graph for the
 * "Flussi" tab on an entity record's detail page — deliberately not the
 * full workflow-builder.js canvas (no editing, no per-type styling to
 * keep in sync): a plain box per node, colored by execution status,
 * clicking one opens its per-iteration execution log fetched alongside
 * the graph itself.
 */
document.addEventListener('DOMContentLoaded', function () {
    var select = document.getElementById('workflow-instance-select');
    var canvasContainer = document.getElementById('workflow-instance-canvas');
    var logPanel = document.getElementById('workflow-instance-log-panel');
    var logPanelTitle = document.getElementById('workflow-instance-log-panel-title');
    var logPanelBody = document.getElementById('workflow-instance-log-panel-body');
    var emptyState = document.getElementById('workflow-instance-empty-state');

    if (!select || !canvasContainer) {
        return;
    }

    var STATUS_COLOR = {
        completed: '#2fb344',
        waiting: '#f59f00',
        none: '#adb5bd',
    };

    var NODE_SIZE = { w: 160, h: 56 };

    function renderGraph(data) {
        canvasContainer.innerHTML = '';
        logPanel.classList.add('d-none');

        var graph = new Graph(canvasContainer, undefined, getDefaultPlugins());
        graph.setEnabled(false);
        graph.setPanning(true);
        graph.panningHandler.useLeftButtonForPanning = true;

        Object.assign(graph.getStylesheet().getDefaultEdgeStyle(), {
            edgeStyle: 'orthogonalEdgeStyle',
            rounded: true,
        });

        var parent = graph.getDefaultParent();
        var cellsByNodeId = {};

        graph.batchUpdate(function () {
            data.nodes.forEach(function (node) {
                var color = STATUS_COLOR[node.status] || STATUS_COLOR.none;
                var cell = graph.insertVertex({
                    parent: parent,
                    value: node.name,
                    position: [node.pos_x || 0, node.pos_y || 0],
                    size: [NODE_SIZE.w, NODE_SIZE.h],
                    style: {
                        shape: 'rectangle',
                        rounded: true,
                        fillColor: '#ffffff',
                        strokeColor: color,
                        strokeWidth: node.status === 'none' ? 1 : 4,
                        fontColor: '#212529',
                        fontSize: 12,
                        whiteSpace: 'wrap',
                    },
                });
                cell.workflowNode = node;
                cellsByNodeId[node.id] = cell;
            });

            data.edges.forEach(function (edge) {
                var source = cellsByNodeId[edge.source_id];
                var target = cellsByNodeId[edge.target_id];
                if (!source || !target) {
                    return;
                }

                graph.insertEdge({
                    parent: parent,
                    source: source,
                    target: target,
                    value: edge.label || '',
                    style: {
                        strokeColor: edge.executed ? STATUS_COLOR.completed : '#adb5bd',
                        strokeWidth: edge.executed ? 3 : 1,
                    },
                });
            });
        });

        graph.addListener(InternalEvent.CLICK, function (sender, evt) {
            var cell = evt.getProperty('cell');
            if (cell && cell.workflowNode) {
                showLog(cell.workflowNode, data.logs[cell.workflowNode.id] || []);
            }
        });
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function showLog(node, entries) {
        logPanelTitle.textContent = node.name;
        logPanelBody.innerHTML = '';

        if (entries.length === 0) {
            logPanelBody.innerHTML = '<p class="text-secondary">' + escapeHtml(logPanel.getAttribute('data-no-executions-text')) + '</p>';
        }

        entries.forEach(function (entry) {
            var block = document.createElement('div');
            block.className = 'mb-3 pb-3 border-bottom';

            var header = document.createElement('div');
            header.className = 'd-flex justify-content-between text-secondary small mb-1';
            header.innerHTML =
                '<span>#' + entry.iteration + ' &middot; ' + escapeHtml(entry.status) + '</span>' +
                '<span>' + escapeHtml(entry.entered_at) + (entry.exited_at ? ' &rarr; ' + escapeHtml(entry.exited_at) : '') + '</span>';
            block.appendChild(header);

            var vars = document.createElement('pre');
            vars.className = 'small bg-body-tertiary p-2 rounded mb-1';
            vars.style.whiteSpace = 'pre-wrap';
            vars.textContent = JSON.stringify(entry.variables_snapshot, null, 2);
            block.appendChild(vars);

            if (entry.user_task) {
                var taskInfo = document.createElement('div');
                taskInfo.className = 'small';
                var completedLine = entry.user_task.completed_by
                    ? escapeHtml(entry.user_task.completed_by) + ' &middot; ' + escapeHtml(entry.user_task.completed_at)
                    : escapeHtml(entry.user_task.status);
                taskInfo.innerHTML = '<div class="mb-1">' + completedLine + '</div><pre class="small bg-body-tertiary p-2 rounded">' + escapeHtml(JSON.stringify(entry.user_task.form_data, null, 2)) + '</pre>';
                block.appendChild(taskInfo);
            }

            logPanelBody.appendChild(block);
        });

        logPanel.classList.remove('d-none');
    }

    function loadInstance(url) {
        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (emptyState) emptyState.classList.add('d-none');
                canvasContainer.classList.remove('d-none');
                renderGraph(data);
            });
    }

    select.addEventListener('change', function () {
        var option = select.options[select.selectedIndex];
        var url = option ? option.getAttribute('data-url') : null;

        if (url) {
            loadInstance(url);
        } else {
            canvasContainer.classList.add('d-none');
            logPanel.classList.add('d-none');
            if (emptyState) emptyState.classList.remove('d-none');
        }
    });

    if (select.value) {
        select.dispatchEvent(new Event('change'));
    }
});
