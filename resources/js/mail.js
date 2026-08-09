import { initRichText, syncRichText } from './hugerte.js';

/**
 * Webmail UI (resources/views/mail/index.blade.php): folders and
 * message bodies are always fetched live from MailController. A
 * folder's first page of messages is stale-while-revalidate instead —
 * MailController::messages() returns `cached: true` when it served the
 * list straight from its persisted header cache (see
 * App\Services\Mail\MailMessageHeaderCache), and loadMessages() below
 * immediately fires a second, silent `refresh=1` request afterwards to
 * bring it back in sync, re-rendering only if the user hasn't already
 * navigated away by the time it lands (see requestSeq). "Allega a
 * record" only creates the entity_email bookmark row (MailController::
 * attach()); the actual N:M link to a Ticket/Preventivo/etc. is done
 * on that record's own page, through the app's existing generic
 * "Relazioni" card (resources/js/entity-relations.js) — reused
 * unmodified, this file never talks to entities.relations.* directly.
 */

/**
 * fetch() that throws with the server's own message on a non-2xx
 * response, instead of quietly resolving to whatever JSON body came
 * back — MailController wraps every mail-server call in a try/catch
 * that returns {error: "..."} with a 502 (see its withReaderErrorHandling()),
 * specifically so a real failure (wrong password, a provider blocking
 * plain IMAP login, a dropped connection) surfaces here as visible
 * text instead of rendering as an empty folder/message list.
 */
function fetchJson(url, options) {
    return fetch(url, options).then(function (response) {
        return response.json().catch(function () { return null; }).then(function (data) {
            if (!response.ok) {
                throw new Error((data && data.error) || 'HTTP ' + response.status);
            }

            return data;
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('mail-app');

    if (!root) {
        return;
    }

    var accounts = JSON.parse(root.dataset.accounts || '[]');
    var i18n = JSON.parse(root.dataset.i18n || '{}');
    var folderIcons = JSON.parse(root.dataset.folderIcons || '{}');
    var emailRecordUrlBase = root.dataset.emailRecordUrlBase;
    var composeUrl = root.dataset.composeUrl;

    var accountSelect = document.getElementById('mail-account-select');
    var folderTree = document.getElementById('mail-folder-tree');
    var messageList = document.getElementById('mail-message-list');
    var readingPane = document.getElementById('mail-reading-pane');

    var current = { account: null, folder: null, uid: null };

    // Bumped on every loadFolders()/loadMessages()/showMessage() call and
    // captured by closure in each fetch's .then()/.catch() — a folder
    // switch fired while a previous request for the same pane is still
    // in flight must not let that stale response (success OR error) land
    // on top of the newer one once it resolves out of order, which is
    // what used to flash a stray "Error 500" over the message list when
    // clicking through folders quickly.
    var requestSeq = { folders: 0, messages: 0, message: 0 };

    // A fixed, restrained rotation of Tabler's own "-lt" tokens — same
    // avatar treatment idea as the topbar user menu (see layouts/app.blade.php),
    // just with a hashed color per sender so the message list is
    // scannable at a glance instead of a wall of identical grey circles.
    var AVATAR_COLORS = ['bg-blue-lt', 'bg-azure-lt', 'bg-teal-lt', 'bg-green-lt', 'bg-orange-lt', 'bg-red-lt', 'bg-purple-lt', 'bg-pink-lt'];

    function headers() {
        return { 'X-CSRF-TOKEN': window.CSRF_TOKEN, Accept: 'application/json' };
    }

    function accountById(id) {
        return accounts.filter(function (a) { return String(a.id) === String(id); })[0];
    }

    function initials(name) {
        var trimmed = (name || '').trim();

        if (!trimmed) {
            return '?';
        }

        var parts = trimmed.split(/\s+/);

        return parts.length > 1
            ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
            : trimmed.substring(0, 2).toUpperCase();
    }

    function avatarColor(seed) {
        var hash = 0;

        for (var i = 0; i < (seed || '').length; i++) {
            hash = (hash * 31 + seed.charCodeAt(i)) % AVATAR_COLORS.length;
        }

        return AVATAR_COLORS[Math.abs(hash)];
    }

    function avatar(name, email, size) {
        var span = document.createElement('span');
        span.className = 'avatar ' + (size || 'avatar-sm') + ' rounded-circle ' + avatarColor(email || name || '');
        span.textContent = initials(name || email);

        return span;
    }

    function folderIcon(name) {
        var key = (name || '').toLowerCase();

        if (/inbox|arrivo/.test(key)) {
            return folderIcons.inbox;
        }

        if (/sent|inviat/.test(key)) {
            return folderIcons.sent;
        }

        if (/draft|bozz/.test(key)) {
            return folderIcons.drafts;
        }

        if (/trash|cestino|delet|elimin/.test(key)) {
            return folderIcons.trash;
        }

        return folderIcons.default;
    }

    // Tabler's own icon SVGs ship at a fixed 24x24 — fine inline in a
    // folder row, too big sitting next to a line of text. Shrinking the
    // two size attributes is simpler than teaching the icon() helper a
    // size parameter for this one inline case.
    function smallIcon(svgMarkup, className) {
        var span = document.createElement('span');
        span.className = 'd-inline-flex ' + (className || '');
        span.innerHTML = (svgMarkup || '').replace('width="24"', 'width="14"').replace('height="24"', 'height="14"');

        return span;
    }

    function formatWhen(iso) {
        if (!iso) {
            return '';
        }

        var date = new Date(iso);
        var now = new Date();
        var sameDay = date.toDateString() === now.toDateString();

        return sameDay
            ? date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
            : date.toLocaleDateString(undefined, { day: '2-digit', month: 'short' });
    }

    function formatWhenLong(iso) {
        return iso ? new Date(iso).toLocaleString() : '';
    }

    function showEmptyReadingPane(text) {
        var bigIcon = (folderIcons.read || '').replace('width="24"', 'width="48"').replace('height="24"', 'height="48"');
        readingPane.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-secondary p-4 text-center" data-testid="mail-reading-pane-empty">'
            + '<div>' + bigIcon + '<div class="mt-2">' + text + '</div></div></div>';
    }

    function renderAccounts() {
        accountSelect.innerHTML = '';

        if (!accounts.length) {
            var option = document.createElement('option');
            option.textContent = i18n.noAccounts;
            accountSelect.appendChild(option);

            return;
        }

        accounts.forEach(function (account) {
            var option = document.createElement('option');
            option.value = account.id;
            option.textContent = account.name + ' (' + account.email + ')';
            accountSelect.appendChild(option);
        });

        // data-tom-select-manual (see the blade) opts this select out of
        // the app-wide auto-init sweep, which runs at DOMContentLoaded —
        // before this function has had a chance to populate any options —
        // and would otherwise wrap an empty select, leaving Tom Select's
        // own UI permanently empty even once we add options here.
        if (window.tomSelect) {
            window.tomSelect(accountSelect);
        }
    }

    function loadFolders(account) {
        var requestId = ++requestSeq.folders;
        requestSeq.messages++;
        requestSeq.message++;

        folderTree.innerHTML = '';
        messageList.innerHTML = '';
        showEmptyReadingPane(i18n.noMessageSelected);

        fetchJson(account.folders_url, { headers: headers() })
            .then(function (data) {
                if (requestId === requestSeq.folders) {
                    renderFolders(account, data.folders || []);
                }
            })
            .catch(function (error) {
                if (requestId === requestSeq.folders) {
                    folderTree.innerHTML = '<div class="p-3 text-danger small">' + (error.message || i18n.loadError) + '</div>';
                }
            });
    }

    // Folders can nest (a subfolder under Inbox/Sent/etc — see
    // MailFolderDTO's parentPath), so the flat list from the server is
    // first grouped by parent before rendering, same "group once, walk
    // recursively" shape as resources/views/documents/_folder_tree.blade.php
    // uses server-side for the Documenti entity's own folder tree.
    var folderTreeNodeSeq = 0;

    function renderFolders(account, folders) {
        folderTree.innerHTML = '';

        var byParent = {};
        folders.forEach(function (folder) {
            var key = folder.parentPath || '';
            (byParent[key] = byParent[key] || []).push(folder);
        });

        function renderNode(folder, container) {
            var children = byParent[folder.path] || [];
            var hasChildren = children.length > 0;

            var node = document.createElement('div');
            node.className = 'mail-folder-node';

            var row = document.createElement('div');
            row.className = 'd-flex align-items-center mail-folder-row';
            node.appendChild(row);

            if (hasChildren) {
                var collapseId = 'mail-folder-children-' + (folderTreeNodeSeq++);

                var toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'mail-folder-toggle collapsed';
                toggle.setAttribute('data-bs-toggle', 'collapse');
                toggle.setAttribute('data-bs-target', '#' + collapseId);
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('data-testid', 'mail-folder-toggle');
                toggle.innerHTML = folderIcons.chevron;
                row.appendChild(toggle);
            } else {
                var spacer = document.createElement('span');
                spacer.className = 'mail-folder-spacer';
                row.appendChild(spacer);
            }

            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'mail-folder-link flex-fill d-flex align-items-center gap-2 text-truncate';
            item.dataset.path = folder.path;

            var iconWrap = document.createElement('span');
            iconWrap.className = 'text-secondary d-inline-flex';
            iconWrap.innerHTML = folderIcon(folder.name);
            item.appendChild(iconWrap);

            var label = document.createElement('span');
            label.className = 'text-truncate';
            label.textContent = folder.name;
            item.appendChild(label);

            item.addEventListener('click', function () { loadMessages(account, folder.path); });
            row.appendChild(item);

            if (hasChildren) {
                var childrenWrap = document.createElement('div');
                childrenWrap.className = 'collapse ps-4';
                childrenWrap.id = collapseId;
                node.appendChild(childrenWrap);

                children.forEach(function (child) { renderNode(child, childrenWrap); });
            }

            container.appendChild(node);
        }

        var roots = byParent[''] || [];
        roots.forEach(function (folder) { renderNode(folder, folderTree); });

        if (roots.length) {
            loadMessages(account, roots[0].path);
        }
    }

    function loadMessages(account, folder) {
        var requestId = ++requestSeq.messages;
        requestSeq.message++;

        current.account = account;
        current.folder = folder;
        current.uid = null;
        showEmptyReadingPane(i18n.noMessageSelected);

        Array.prototype.forEach.call(folderTree.querySelectorAll('[data-path]'), function (item) {
            item.classList.toggle('active', item.dataset.path === folder);
        });

        var url = account.messages_url + '?folder=' + encodeURIComponent(folder);

        messageList.innerHTML = '<div class="p-3 text-center text-secondary small" data-testid="mail-messages-loading">' + i18n.loading + '</div>';

        fetchJson(url, { headers: headers() })
            .then(function (data) {
                if (requestId !== requestSeq.messages) {
                    return;
                }

                renderMessages(account, folder, data.items || []);

                // Server painted this from its persisted header cache
                // (see MailMessageHeaderCache) rather than a live fetch
                // — kick a silent refresh straight away so the list
                // catches up with the real mailbox without making the
                // user wait for it up front.
                if (data.cached) {
                    refreshMessages(account, folder, requestId);
                }
            })
            .catch(function (error) {
                if (requestId === requestSeq.messages) {
                    messageList.innerHTML = '<div class="p-3 text-danger small">' + (error.message || i18n.loadError) + '</div>';
                }
            });
    }

    function refreshMessages(account, folder, requestId) {
        var url = account.messages_url + '?folder=' + encodeURIComponent(folder) + '&refresh=1';

        fetchJson(url, { headers: headers() })
            .then(function (data) {
                if (requestId === requestSeq.messages) {
                    renderMessages(account, folder, data.items || []);
                }
            })
            .catch(function () {
                // Silent by design — the list is already showing the
                // (possibly stale) cached page, and a failed background
                // refresh isn't worth interrupting the user over.
            });
    }

    function renderMessages(account, folder, items) {
        messageList.innerHTML = '';

        if (!items.length) {
            messageList.innerHTML = '<div class="p-4 text-center text-secondary small" data-testid="mail-messages-empty">' + i18n.noMessages + '</div>';

            return;
        }

        items.forEach(function (message) {
            var senderLabel = message.from_name || message.from_address || '';

            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action d-flex align-items-start gap-2 py-2' + (message.uid === current.uid ? ' active' : '');
            item.dataset.uid = message.uid;

            item.appendChild(avatar(senderLabel, message.from_address, 'avatar-sm'));

            var body = document.createElement('div');
            body.className = 'flex-fill';
            body.style.minWidth = '0';

            var topRow = document.createElement('div');
            topRow.className = 'd-flex justify-content-between align-items-baseline gap-2';

            var sender = document.createElement('span');
            sender.className = 'text-truncate ' + (message.is_read ? '' : 'fw-bold');
            sender.textContent = senderLabel || i18n.noSubject;
            topRow.appendChild(sender);

            var when = document.createElement('span');
            when.className = 'text-secondary small flex-shrink-0';
            when.textContent = formatWhen(message.date);
            topRow.appendChild(when);

            body.appendChild(topRow);

            var subjectRow = document.createElement('div');
            subjectRow.className = 'd-flex align-items-center gap-1 text-truncate';

            var subject = document.createElement('span');
            subject.className = 'text-truncate ' + (message.is_read ? 'text-secondary' : 'fw-bold');
            subject.textContent = message.subject || i18n.noSubject;
            subjectRow.appendChild(subject);

            if (message.has_attachments) {
                subjectRow.appendChild(smallIcon(folderIcons.attachment, 'text-secondary flex-shrink-0'));
            }

            body.appendChild(subjectRow);
            item.appendChild(body);

            item.addEventListener('click', function () { showMessage(account, folder, message.uid); });
            messageList.appendChild(item);
        });
    }

    function showMessage(account, folder, uid) {
        var requestId = ++requestSeq.message;

        current.uid = uid;

        Array.prototype.forEach.call(messageList.children, function (item) {
            item.classList.toggle('active', item.dataset.uid === uid);
        });

        var url = account.show_url + '?folder=' + encodeURIComponent(folder) + '&uid=' + encodeURIComponent(uid);

        showEmptyReadingPane(i18n.loading);

        fetchJson(url, { headers: headers() })
            .then(function (message) {
                if (requestId === requestSeq.message) {
                    renderMessage(account, folder, message);
                }
            })
            .catch(function (error) {
                if (requestId === requestSeq.message) {
                    readingPane.innerHTML = '<div class="p-3 text-danger small">' + (error.message || i18n.loadError) + '</div>';
                }
            });
    }

    function toolbarButton(tag, label, testid, onClick) {
        var button = document.createElement(tag);

        if (tag === 'button') {
            button.type = 'button';
        }

        button.className = 'btn btn-sm btn-outline-secondary';
        button.textContent = label;
        button.setAttribute('data-testid', testid);

        if (onClick) {
            button.addEventListener('click', onClick);
        }

        return button;
    }

    // Sizes a sandboxed message-body iframe to its content. Deliberately
    // does NOT wait for the iframe's own "load" event — that only fires
    // once every last subresource (every remote image, including dead
    // tracking pixels on slow/unreachable hosts) has finished loading
    // or errored, which on a typical HTML marketing email left the pane
    // stuck at its 400px placeholder for a very long time before ever
    // resizing. Polling for contentDocument.body instead (available
    // within a frame or two, well before images finish) lets it size
    // itself to the *text* layout almost immediately; the ResizeObserver
    // then keeps it in sync as images stream in afterwards.
    function autoSizeFrame(frame) {
        var attempts = 0;
        var maxAttempts = 200; // ~a few seconds of polling at 60fps, then give up quietly
        var observing = false;

        function measure(doc) {
            var height = Math.max(
                doc.documentElement.scrollHeight,
                doc.documentElement.offsetHeight,
                doc.body ? doc.body.scrollHeight : 0,
                doc.body ? doc.body.offsetHeight : 0
            );
            frame.style.height = height + 'px';
        }

        function tick() {
            var doc;

            try {
                doc = frame.contentDocument;
            } catch (e) {
                return; // cross-origin somehow — leave the 400px fallback in place.
            }

            if (doc && doc.body) {
                measure(doc);

                if (!observing && window.ResizeObserver) {
                    observing = true;
                    new ResizeObserver(function () { measure(doc); }).observe(doc.body);
                }

                if (doc.readyState === 'complete') {
                    return;
                }
            }

            attempts++;

            if (attempts < maxAttempts) {
                requestAnimationFrame(tick);
            }
        }

        tick();
    }

    function renderMessage(account, folder, message) {
        readingPane.innerHTML = '';

        var header = document.createElement('div');
        header.className = 'd-flex align-items-start gap-3 p-3 border-bottom';

        var senderLabel = message.from_name || message.from_address || '';
        header.appendChild(avatar(senderLabel, message.from_address, 'avatar-md'));

        var headerText = document.createElement('div');
        headerText.className = 'flex-fill';
        headerText.style.minWidth = '0';

        var subject = document.createElement('h2');
        subject.className = 'mb-1';
        subject.style.fontSize = '1.25rem';
        subject.textContent = message.subject || i18n.noSubject;
        headerText.appendChild(subject);

        var fromLine = document.createElement('div');
        fromLine.className = 'text-truncate';
        fromLine.textContent = senderLabel + (message.from_address && senderLabel !== message.from_address ? ' <' + message.from_address + '>' : '');
        headerText.appendChild(fromLine);

        var dateLine = document.createElement('div');
        dateLine.className = 'text-secondary small';
        dateLine.textContent = formatWhenLong(message.date);
        headerText.appendChild(dateLine);

        header.appendChild(headerText);
        readingPane.appendChild(header);

        var toolbar = document.createElement('div');
        toolbar.className = 'btn-list p-3 border-bottom';

        var replyBtn = toolbarButton('a', i18n.reply, 'mail-reply-btn');
        replyBtn.href = composeUrl + '?mode=reply&account=' + encodeURIComponent(account.id) + '&folder=' + encodeURIComponent(folder) + '&uid=' + encodeURIComponent(message.uid);
        toolbar.appendChild(replyBtn);

        var forwardBtn = toolbarButton('a', i18n.forward, 'mail-forward-btn');
        forwardBtn.href = composeUrl + '?mode=forward&account=' + encodeURIComponent(account.id) + '&folder=' + encodeURIComponent(folder) + '&uid=' + encodeURIComponent(message.uid);
        toolbar.appendChild(forwardBtn);

        var attachBtn = toolbarButton('button', i18n.attachToRecord, 'mail-attach-btn', function () {
            attachMessage(account, folder, message.uid, attachBtn);
        });
        attachBtn.classList.remove('btn-outline-secondary');
        attachBtn.classList.add('btn-outline-primary');
        toolbar.appendChild(attachBtn);

        readingPane.appendChild(toolbar);

        var bodyWrap = document.createElement('div');
        bodyWrap.className = 'p-3';
        readingPane.appendChild(bodyWrap);

        // Email HTML is untrusted content — never innerHTML it into the
        // page (a message can carry arbitrary <script>/event handlers
        // that would then run with this origin's session). A sandboxed
        // iframe renders it visually without allowing script execution,
        // form submission, or top-level navigation — same technique real
        // webmail clients use. "allow-same-origin" (with no
        // "allow-scripts" alongside it — that specific combination is
        // what would let sandboxed content break out, so it's
        // deliberately never added) is required even so: without it the
        // frame's document is cross-origin to the parent and reading
        // contentWindow.document below throws a SecurityError on every
        // single HTML message, silently falling back to a fixed 400px —
        // which is exactly why the reading pane used to look far too
        // short for any real HTML email.
        if (message.html_body) {
            var frame = document.createElement('iframe');
            frame.className = 'w-100';
            frame.style.border = 'none';
            frame.setAttribute('sandbox', 'allow-same-origin');
            frame.setAttribute('data-testid', 'mail-message-body-frame');
            bodyWrap.appendChild(frame);
            frame.style.height = '400px';
            frame.srcdoc = message.html_body;
            autoSizeFrame(frame);
        } else {
            var textBody = document.createElement('div');
            textBody.style.whiteSpace = 'pre-wrap';
            textBody.textContent = message.text_body || '';
            bodyWrap.appendChild(textBody);
        }

        if (message.attachments && message.attachments.length) {
            var attachmentsTitle = document.createElement('h4');
            attachmentsTitle.className = 'mt-4';
            attachmentsTitle.textContent = i18n.attachments;
            bodyWrap.appendChild(attachmentsTitle);

            var list = document.createElement('div');
            list.className = 'btn-list mt-2';

            message.attachments.forEach(function (attachment) {
                var link = document.createElement('a');
                link.className = 'btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1';
                link.href = account.attachment_url + '?folder=' + encodeURIComponent(folder) + '&uid=' + encodeURIComponent(message.uid) + '&attachment_id=' + encodeURIComponent(attachment.id);
                link.appendChild(smallIcon(folderIcons.attachment));
                link.appendChild(document.createTextNode(attachment.filename));
                list.appendChild(link);
            });

            bodyWrap.appendChild(list);
        }
    }

    function attachMessage(account, folder, uid, button) {
        button.disabled = true;
        button.textContent = i18n.attaching;

        fetchJson(account.attach_url, {
            method: 'POST',
            headers: Object.assign(headers(), { 'Content-Type': 'application/json' }),
            body: JSON.stringify({ folder: folder, uid: uid }),
        })
            .then(function (data) {
                window.location = emailRecordUrlBase.replace(/\/0$/, '/' + data.record_id);
            })
            .catch(function (error) {
                button.disabled = false;
                button.textContent = i18n.attachToRecord;
                window.Swal.fire({ text: error.message || i18n.loadError, icon: 'error' });
            });
    }

    renderAccounts();

    accountSelect.addEventListener('change', function () {
        var account = accountById(accountSelect.value);

        if (account) {
            loadFolders(account);
        }
    });

    if (accounts.length) {
        loadFolders(accounts[0]);
    } else {
        showEmptyReadingPane(i18n.noAccounts);
    }
});

/**
 * Compose page (resources/views/mail/compose.blade.php) — separate
 * DOMContentLoaded block, guarded by its own root element, so this one
 * file covers both pages without either block interfering with the
 * other's markup.
 */
document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('mail-compose-app');

    if (!root) {
        return;
    }

    var accounts = JSON.parse(root.dataset.accounts || '[]');
    var i18n = JSON.parse(root.dataset.i18n || '{}');
    var replyUrlBase = root.dataset.replyUrlBase;
    var forwardUrlBase = root.dataset.forwardUrlBase;
    var sendUrl = root.dataset.sendUrl;
    var indexUrl = root.dataset.indexUrl;

    var form = document.getElementById('mail-compose-form');
    var accountSelect = document.getElementById('mail_account_id');
    var subjectInput = document.getElementById('subject');
    var inReplyToInput = document.getElementById('in_reply_to');
    var referencesInput = document.getElementById('references');
    var errorBox = document.getElementById('mail-compose-error');
    var submitBtn = document.querySelector('[data-testid="mail-compose-submit"]');
    var editor = null;

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    // A native <input type="file"> can't accumulate across separate
    // picks/drops — every new selection replaces its whole FileList —
    // so the actually-attached files live in this array instead, and
    // the input is just one way (click-to-browse) of adding to it; drag
    // & drop onto the dropzone is the other. Each attached file gets
    // its own remove button rendered in #mail-compose-attachment-list.
    var dropzone = document.getElementById('mail-compose-dropzone');
    var attachmentInput = document.getElementById('mail-compose-attachment-input');
    var attachmentList = document.getElementById('mail-compose-attachment-list');
    var attachedFiles = [];

    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return bytes + ' B';
        }

        if (bytes < 1024 * 1024) {
            return (bytes / 1024).toFixed(1) + ' KB';
        }

        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function renderAttachmentList() {
        attachmentList.innerHTML = '';

        attachedFiles.forEach(function (file, index) {
            var item = document.createElement('div');
            item.className = 'list-group-item d-flex align-items-center gap-2';
            item.setAttribute('data-testid', 'mail-compose-attachment-item');

            var label = document.createElement('span');
            label.className = 'flex-fill text-truncate';
            label.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
            item.appendChild(label);

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-outline-danger';
            removeBtn.textContent = '×';
            removeBtn.setAttribute('aria-label', i18n.removeAttachment);
            removeBtn.setAttribute('data-testid', 'mail-compose-attachment-remove');
            removeBtn.addEventListener('click', function () {
                attachedFiles.splice(index, 1);
                renderAttachmentList();
            });
            item.appendChild(removeBtn);

            attachmentList.appendChild(item);
        });
    }

    function addAttachedFiles(fileList) {
        Array.prototype.forEach.call(fileList, function (file) {
            var isDuplicate = attachedFiles.some(function (existing) {
                return existing.name === file.name && existing.size === file.size;
            });

            if (!isDuplicate) {
                attachedFiles.push(file);
            }
        });

        renderAttachmentList();
    }

    dropzone.addEventListener('click', function () { attachmentInput.click(); });

    attachmentInput.addEventListener('change', function () {
        addAttachedFiles(attachmentInput.files);
        // Reset so picking the exact same file again still fires
        // "change" (a no-op re-selection otherwise wouldn't).
        attachmentInput.value = '';
    });

    ['dragover', 'dragenter'].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (event) {
            event.preventDefault();
            dropzone.classList.add('mail-compose-dropzone-active');
        });
    });

    ['dragleave', 'drop'].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (event) {
            event.preventDefault();
            dropzone.classList.remove('mail-compose-dropzone-active');
        });
    });

    dropzone.addEventListener('drop', function (event) {
        if (event.dataTransfer && event.dataTransfer.files) {
            addAttachedFiles(event.dataTransfer.files);
        }
    });

    // To/Cc/Bcc are tag inputs, not free-text CSV fields: an empty
    // <select multiple> upgraded by Tom Select with create:true, so
    // typing an address then a comma (Tom Select's own delimiter
    // handling) or pressing Enter/blurring commits it as a tag —
    // create() rejecting anything that doesn't look like an email
    // address is what keeps a stray typo from silently becoming a
    // "recipient" that then just bounces.
    function initAddressField(id) {
        var el = document.getElementById(id);

        return window.tomSelect(el, {
            create: function (input) {
                var value = input.trim();

                return isValidEmail(value) ? { value: value, text: value } : false;
            },
            createOnBlur: true,
            persist: false,
            delimiter: ',',
            maxOptions: null,
            placeholder: i18n.addressPlaceholder,
        });
    }

    var toField = initAddressField('to');
    var ccField = initAddressField('cc');
    var bccField = initAddressField('bcc');

    function accountById(id) {
        return accounts.filter(function (a) { return String(a.id) === String(id); })[0];
    }

    function currentSignatureHtml() {
        var account = accountById(accountSelect.value);

        return account && account.signature_html ? account.signature_html : '';
    }

    // Wraps the signature in a marker div so a later account switch
    // can swap just that block back out (see applySignature()) without
    // disturbing whatever the user has already typed around it, or —
    // for a reply/forward — the quoted original below it.
    function composeBody(signatureHtml, quoteHtml) {
        var block = signatureHtml ? '<div id="mail-signature-block">' + signatureHtml + '</div>' : '';

        return '<p><br></p>' + block + (quoteHtml || '');
    }

    function applySignature() {
        if (!editor) {
            return;
        }

        var html = currentSignatureHtml();
        var body = editor.getBody();
        var existing = body.querySelector('#mail-signature-block');

        if (existing) {
            if (html) {
                existing.innerHTML = html;
            } else {
                existing.parentNode.removeChild(existing);
            }

            return;
        }

        if (html) {
            body.appendChild(editor.dom.create('div', { id: 'mail-signature-block' }, html));
        }
    }

    function prefill(url) {
        fetchJson(url, { headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN, Accept: 'application/json' } })
            .then(function (data) {
                (data.to || []).forEach(function (address) { toField.addItem(address); });
                subjectInput.value = data.subject || '';
                editor.setContent(composeBody(currentSignatureHtml(), data.body_html || ''));
                inReplyToInput.value = data.in_reply_to || '';
                referencesInput.value = data.references || '';
            })
            .catch(function (error) {
                errorBox.textContent = error.message || i18n.sendError;
                errorBox.classList.remove('d-none');
            });
    }

    var params = new URLSearchParams(window.location.search);
    var mode = params.get('mode');
    var accountId = params.get('account');
    var folder = params.get('folder');
    var uid = params.get('uid');

    if (accountId) {
        // accountSelect is auto-wrapped by Tom Select (see tom-select.js) —
        // writing .value directly only updates the hidden native <select>,
        // not Tom Select's own rendered UI, so this must go through the
        // shared helper instead.
        window.setSelectValue(accountSelect, accountId);
    }

    initRichText('#mail-compose-body').then(function (created) {
        editor = created;

        if ((mode === 'reply' || mode === 'forward') && accountId && folder && uid) {
            var base = mode === 'reply' ? replyUrlBase : forwardUrlBase;
            var url = base.replace(/\/0\//, '/' + accountId + '/') + '?folder=' + encodeURIComponent(folder) + '&uid=' + encodeURIComponent(uid);
            editor.setContent(composeBody('', ''));
            prefill(url);
        } else {
            editor.setContent(composeBody(currentSignatureHtml(), ''));
        }
    });

    accountSelect.addEventListener('change', applySignature);

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        syncRichText(editor);
        errorBox.classList.add('d-none');

        var formData = new FormData(form);
        formData.delete('to');
        formData.delete('cc');
        formData.delete('bcc');
        (toField.items || []).forEach(function (address) { formData.append('to[]', address); });
        (ccField.items || []).forEach(function (address) { formData.append('cc[]', address); });
        (bccField.items || []).forEach(function (address) { formData.append('bcc[]', address); });
        attachedFiles.forEach(function (file) { formData.append('attachments[]', file); });

        submitBtn.disabled = true;
        submitBtn.textContent = i18n.sending;

        fetchJson(sendUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN, Accept: 'application/json' },
            body: formData,
        })
            .then(function () {
                window.location = indexUrl;
            })
            .catch(function (error) {
                errorBox.textContent = error.message || i18n.sendError;
                errorBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.textContent = i18n.send;
            });
    });
});
