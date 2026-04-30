/**
 * AI Shopping Assistant – Chat Widget
 * Renders chat UI with product card recommendations
 */
(function () {
    'use strict';

    var cfg, toggle, chatWindow, messagesEl, inputEl, sendBtn, headerName, closeBtn, fullscreenBtn, newChatBtn, historyBtn, attachBtn, fileInput, attachPreview;
    var tgLoginGate, tgLoginWidget;
    var history = [];
    var attachments = []; // { name, type, data (base64), previewUrl }
    var isOpen = false;
    var isSending = false;
    var isFullscreen = false;
    var tgSession = null; // { telegram_id, auth_date, session, first_name, photo_url }

    /* ── Chat History Persistence (localStorage, multi-session) ───────── */
    var HISTORY_KEY_PREFIX        = 'sai_chat_history_';
    var HISTORY_MAX_SESSIONS      = 5;   // keep last 5 chats per user
    var HISTORY_MAX_MSGS_PER_SESS = 50;  // cap messages within a session

    var currentSessionId = null; // set lazily on first save / on switch

    function getHistoryKey() {
        var uid = (tgSession && tgSession.telegram_id) ? tgSession.telegram_id : 'anon';
        return HISTORY_KEY_PREFIX + uid;
    }

    function readStore() {
        try {
            var raw = localStorage.getItem(getHistoryKey());
            if (!raw) return null;
            var data = JSON.parse(raw);
            if (!data || !Array.isArray(data.sessions)) return null;
            return data;
        } catch (e) { return null; }
    }

    function writeStore(data) {
        try { localStorage.setItem(getHistoryKey(), JSON.stringify(data)); }
        catch (e) { /* quota — fail silently */ }
    }

    function newSessionId() {
        return String(Date.now()) + '-' + Math.random().toString(36).slice(2, 8);
    }

    function saveHistory() {
        if (history.length === 0) return; // never store empty sessions
        var store = readStore() || { current_id: null, sessions: [] };

        // Find or create current session
        var session = null;
        if (currentSessionId) {
            for (var i = 0; i < store.sessions.length; i++) {
                if (store.sessions[i].id === currentSessionId) { session = store.sessions[i]; break; }
            }
        }
        if (!session) {
            session = { id: newSessionId(), title: '', timestamp: Date.now(), messages: [] };
            currentSessionId = session.id;
            store.sessions.unshift(session);
        }

        // Update messages + bump timestamp so this session bubbles to the top
        session.messages  = history.slice(-HISTORY_MAX_MSGS_PER_SESS);
        session.timestamp = Date.now();

        // Auto-title from the first user message
        if (!session.title) {
            for (var j = 0; j < session.messages.length; j++) {
                if (session.messages[j].role === 'user' && session.messages[j].text) {
                    session.title = session.messages[j].text.slice(0, 60);
                    break;
                }
            }
        }

        store.current_id = currentSessionId;
        // Sort newest first; keep only the last N sessions.
        store.sessions.sort(function (a, b) { return (b.timestamp || 0) - (a.timestamp || 0); });
        if (store.sessions.length > HISTORY_MAX_SESSIONS) {
            store.sessions = store.sessions.slice(0, HISTORY_MAX_SESSIONS);
        }
        writeStore(store);
    }

    function renderSessionMessages(session) {
        history = (session && session.messages) ? session.messages.slice() : [];
        messagesEl.innerHTML = '';
        for (var i = 0; i < history.length; i++) {
            var m = history[i];
            if (m.role === 'user') {
                appendUser(m.text || '', m.files || []);
            } else if (m.role === 'assistant') {
                appendBot(m.html || formatMessage(m.text || ''), m.products || []);
            }
        }
    }

    function loadHistory() {
        var store = readStore();
        if (!store || !store.sessions.length) return false;

        var session = null;
        if (store.current_id) {
            for (var i = 0; i < store.sessions.length; i++) {
                if (store.sessions[i].id === store.current_id) { session = store.sessions[i]; break; }
            }
        }
        if (!session) session = store.sessions[0];
        if (!session || !session.messages || !session.messages.length) return false;

        currentSessionId = session.id;
        renderSessionMessages(session);
        return true;
    }

    function listSessions() {
        var store = readStore();
        return store ? (store.sessions || []) : [];
    }

    function switchToSession(sessionId) {
        var store = readStore();
        if (!store) return;
        var found = null;
        for (var i = 0; i < store.sessions.length; i++) {
            if (store.sessions[i].id === sessionId) { found = store.sessions[i]; break; }
        }
        if (!found) return;
        currentSessionId   = sessionId;
        store.current_id   = sessionId;
        writeStore(store);
        renderSessionMessages(found);
        if (history.length === 0) showWelcome();
    }

    function deleteSession(sessionId) {
        var store = readStore();
        if (!store) return;
        store.sessions = store.sessions.filter(function (s) { return s.id !== sessionId; });
        var wasActive = (store.current_id === sessionId);
        if (wasActive) {
            store.current_id = store.sessions.length ? store.sessions[0].id : null;
        }
        writeStore(store);
        if (wasActive) {
            if (store.sessions.length) {
                switchToSession(store.sessions[0].id);
            } else {
                currentSessionId = null;
                history = [];
                messagesEl.innerHTML = '';
                showWelcome();
            }
        }
    }

    function formatRelativeTime(ts) {
        if (!ts) return '';
        var diff  = Date.now() - ts;
        var mins  = Math.floor(diff / 60000);
        var hours = Math.floor(diff / 3600000);
        var days  = Math.floor(diff / 86400000);
        if (mins  < 1)  return 'Just now';
        if (mins  < 60) return mins  + 'm ago';
        if (hours < 24) return hours + 'h ago';
        if (days  < 7)  return days  + 'd ago';
        return new Date(ts).toLocaleDateString();
    }

    function renderHistoryPanel() {
        var listEl = document.getElementById('sai-history-list');
        if (!listEl) return;
        var sessions = listSessions();
        if (!sessions.length) {
            listEl.innerHTML = '<div class="sai-history-empty">No previous chats yet.</div>';
            return;
        }
        var html = '';
        for (var i = 0; i < sessions.length; i++) {
            var s        = sessions[i];
            var isActive = s.id === currentSessionId;
            var title    = s.title || 'New chat';
            var msgCount = (s.messages || []).length;
            html += '<div class="sai-history-item' + (isActive ? ' sai-history-current' : '') + '" data-id="' + escAttr(s.id) + '">';
            html +=   '<div class="sai-history-item-info">';
            html +=     '<div class="sai-history-item-title">' + escHtml(title) + '</div>';
            html +=     '<div class="sai-history-item-meta">' + msgCount + ' msg · ' + formatRelativeTime(s.timestamp) + '</div>';
            html +=   '</div>';
            html +=   '<button class="sai-history-item-delete" data-id="' + escAttr(s.id) + '" aria-label="Delete chat" title="Delete">&times;</button>';
            html += '</div>';
        }
        listEl.innerHTML = html;

        // Click to switch
        var items = listEl.querySelectorAll('.sai-history-item');
        for (var k = 0; k < items.length; k++) {
            items[k].addEventListener('click', function (e) {
                if (e.target.classList && e.target.classList.contains('sai-history-item-delete')) return;
                switchToSession(this.getAttribute('data-id'));
                toggleHistoryPanel(false);
            });
        }
        // Click to delete
        var dels = listEl.querySelectorAll('.sai-history-item-delete');
        for (var d = 0; d < dels.length; d++) {
            dels[d].addEventListener('click', function (e) {
                e.stopPropagation();
                if (!confirm('Delete this chat?')) return;
                deleteSession(this.getAttribute('data-id'));
                renderHistoryPanel();
            });
        }
    }

    function toggleHistoryPanel(forceState) {
        var panel = document.getElementById('sai-history-panel');
        if (!panel) return;
        var shouldShow = (typeof forceState === 'boolean') ? forceState : (panel.style.display === 'none' || !panel.style.display);
        panel.style.display = shouldShow ? '' : 'none';
        if (shouldShow) renderHistoryPanel();
    }

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        if (typeof shopysAI === 'undefined') return;
        cfg = shopysAI;

        toggle     = document.getElementById('sai-toggle');
        chatWindow = document.getElementById('sai-window');
        messagesEl = document.getElementById('sai-messages');
        inputEl    = document.getElementById('sai-input');
        sendBtn    = document.getElementById('sai-send');
        headerName = document.getElementById('sai-header-name');
        closeBtn      = document.getElementById('sai-close');
        fullscreenBtn = document.getElementById('sai-fullscreen');
        newChatBtn    = document.getElementById('sai-new-chat');
        historyBtn    = document.getElementById('sai-history-btn');
        attachBtn     = document.getElementById('sai-attach-btn');
        fileInput     = document.getElementById('sai-file-input');
        attachPreview = document.getElementById('sai-attach-preview');

        tgLoginGate   = document.getElementById('sai-tg-login-gate');
        tgLoginWidget = document.getElementById('sai-tg-login-widget');

        if (!toggle || !chatWindow) return;

        headerName.textContent = cfg.bot_name;

        // Restore Telegram session from localStorage
        if (cfg.require_tg_login === '1') {
            var saved = localStorage.getItem('sai_tg_session');
            if (saved) {
                try { tgSession = JSON.parse(saved); } catch (e) { tgSession = null; }
            }
            initTelegramGate();
        }

        // Events
        toggle.addEventListener('click', toggleChat);
        closeBtn.addEventListener('click', toggleChat);
        fullscreenBtn.addEventListener('click', toggleFullscreen);
        newChatBtn.addEventListener('click', newChat);
        if (historyBtn) historyBtn.addEventListener('click', function () { toggleHistoryPanel(); });
        var historyCloseBtn = document.getElementById('sai-history-close');
        if (historyCloseBtn) historyCloseBtn.addEventListener('click', function () { toggleHistoryPanel(false); });
        sendBtn.addEventListener('click', sendMessage);
        attachBtn.addEventListener('click', function () { fileInput.click(); });
        fileInput.addEventListener('change', handleFileSelect);

        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Auto-resize textarea
        inputEl.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });

        // Drag & drop files onto chat window
        chatWindow.addEventListener('dragover', function (e) {
            e.preventDefault();
            chatWindow.classList.add('sai-dragover');
        });
        chatWindow.addEventListener('dragleave', function (e) {
            e.preventDefault();
            chatWindow.classList.remove('sai-dragover');
        });
        chatWindow.addEventListener('drop', function (e) {
            e.preventDefault();
            chatWindow.classList.remove('sai-dragover');
            if (e.dataTransfer && e.dataTransfer.files.length) {
                handleDroppedFiles(e.dataTransfer.files);
            }
        });

        // Hide attachment UI if attachments are disabled
        if (cfg.feat_attachments !== '1') {
            if (attachBtn) attachBtn.style.display = 'none';
            if (fileInput) fileInput.disabled = true;
        }

        // Update file input accept based on enabled features
        if (fileInput && cfg.feat_attachments === '1') {
            var acceptTypes = [];
            if (cfg.feat_image_search === '1') acceptTypes.push('image/*');
            if (cfg.feat_pdf_reading === '1') acceptTypes.push('.pdf');
            if (acceptTypes.length) {
                fileInput.setAttribute('accept', acceptTypes.join(','));
            } else {
                // No file types enabled — hide attach button
                if (attachBtn) attachBtn.style.display = 'none';
                fileInput.disabled = true;
            }
        }

        // Resize handles
        initResize();
    }

    /* ── Resize ───────────────────────────────────────── */

    function initResize() {
        var handles = chatWindow.querySelectorAll('.sai-resize-handle');
        for (var i = 0; i < handles.length; i++) {
            handles[i].addEventListener('mousedown', startResize);
            handles[i].addEventListener('touchstart', startResize, { passive: false });
        }
    }

    function startResize(e) {
        e.preventDefault();
        e.stopPropagation();

        var dir = this.getAttribute('data-resize');
        var startX = e.clientX || (e.touches && e.touches[0].clientX);
        var startY = e.clientY || (e.touches && e.touches[0].clientY);
        var startW = chatWindow.offsetWidth;
        var startH = chatWindow.offsetHeight;
        chatWindow.classList.add('sai-resizing');

        function onMove(ev) {
            var cx = ev.clientX || (ev.touches && ev.touches[0].clientX);
            var cy = ev.clientY || (ev.touches && ev.touches[0].clientY);
            var dx = startX - cx;
            var dy = startY - cy;

            if (dir === 'left' || dir === 'corner') {
                var newW = Math.max(300, Math.min(startW + dx, window.innerWidth - 32));
                chatWindow.style.width = newW + 'px';
            }
            if (dir === 'top' || dir === 'corner') {
                var newH = Math.max(350, Math.min(startH + dy, window.innerHeight - 140));
                chatWindow.style.height = newH + 'px';
            }
        }

        function onEnd() {
            chatWindow.classList.remove('sai-resizing');
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onEnd);
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('touchend', onEnd);
        }

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onEnd);
        document.addEventListener('touchmove', onMove, { passive: false });
        document.addEventListener('touchend', onEnd);
    }

    function newChat() {
        // Start a fresh session — old chats stay accessible via the History panel.
        history = [];
        messagesEl.innerHTML = '';
        clearAttachments();
        currentSessionId = null;
        var store = readStore();
        if (store) { store.current_id = null; writeStore(store); }
        toggleHistoryPanel(false);
        showWelcome();
        inputEl.focus();
    }

    function toggleFullscreen() {
        isFullscreen = !isFullscreen;
        chatWindow.classList.toggle('sai-fullscreen-mode', isFullscreen);
        scrollBottom();
    }

    /* ── Telegram Login Gate ──────────────────────────── */

    function initTelegramGate() {
        if (!tgLoginGate) return;

        if (tgSession) {
            // Already logged in — show chat, hide gate
            showChatUI();
        } else {
            // Show login gate, hide chat UI
            showLoginGate();
        }
    }

    function showLoginGate() {
        if (!tgLoginGate) return;
        tgLoginGate.style.display = 'flex';
        messagesEl.style.display = 'none';
        document.querySelector('.sai-toolbar').style.display = 'none';
        document.querySelector('.sai-attach-preview').style.display = 'none';
        document.querySelector('.sai-input-area').style.display = 'none';

        // Load Telegram widget
        if (tgLoginWidget && !tgLoginWidget.querySelector('script')) {
            var script = document.createElement('script');
            script.async = true;
            script.src = 'https://telegram.org/js/telegram-widget.js?22';
            script.setAttribute('data-telegram-login', cfg.tg_bot_username);
            script.setAttribute('data-size', 'large');
            script.setAttribute('data-radius', '8');
            script.setAttribute('data-onauth', 'shopysAI_onTelegramAuth(user)');
            script.setAttribute('data-request-access', 'write');
            tgLoginWidget.appendChild(script);
        }
    }

    function showChatUI() {
        if (tgLoginGate) tgLoginGate.style.display = 'none';
        var notMemberGate = document.getElementById('sai-not-member-gate');
        if (notMemberGate) notMemberGate.style.display = 'none';
        messagesEl.style.display = '';
        document.querySelector('.sai-toolbar').style.display = '';
        document.querySelector('.sai-attach-preview').style.display = '';
        document.querySelector('.sai-input-area').style.display = '';

        // Show user badge in header
        renderTgBadge();

        // Restore previous chat from localStorage so users resume where they left off.
        loadHistory();

        // Fetch and show daily limit status
        fetchUserStatus();
    }

    /* ── Limit bar helpers ──────────────────────────────────────────── */
    function renderLimitBar(used, limit, model) {
        var bar   = document.getElementById('sai-limit-bar');
        var text  = document.getElementById('sai-limit-text');
        var pill  = document.getElementById('sai-remaining-pill');
        var fill  = document.getElementById('sai-limit-fill');
        var track = document.getElementById('sai-limit-track');
        if (!bar) return;

        var modelLabels = {
            'claude-haiku-4-5-20251001': 'Haiku',
            'claude-sonnet-4-6': 'Sonnet',
            'claude-opus-4-6': 'Opus'
        };
        var modelName = modelLabels[model] || 'AI';

        if (limit === -1) {
            // Unlimited (admin)
            text.textContent = 'Unlimited messages — ' + modelName + ' model';
            pill.textContent = '∞ Unlimited';
            pill.className = 'sai-remaining-pill';
            if (track) track.style.display = 'none';
            bar.classList.add('sai-limit-visible');
            return;
        }

        var remaining = Math.max(0, limit - used);
        var pct       = limit > 0 ? Math.round((used / limit) * 100) : 0;

        // Text
        text.textContent = 'Daily limit: ' + used + ' of ' + limit + ' messages used (' + modelName + ' model)';

        // Pill
        pill.textContent = remaining + ' left';
        pill.className   = 'sai-remaining-pill';
        if (remaining <= 0) {
            pill.classList.add('sai-pill-danger');
        } else if (remaining <= 3) {
            pill.classList.add('sai-pill-warning');
        }

        // Progress bar
        if (track) track.style.display = '';
        fill.style.width = pct + '%';
        fill.className = 'sai-limit-fill';
        if (remaining <= 0) {
            fill.classList.add('sai-fill-danger');
        } else if (remaining <= 3) {
            fill.classList.add('sai-fill-warning');
        }

        bar.classList.add('sai-limit-visible');
    }

    function updateRemainingCount(remaining) {
        // Update the limit bar after each message sent
        // We re-read the current state from the bar text to get limit+used
        var text = document.getElementById('sai-limit-text');
        if (!text) return;

        // Parse current used/limit from text e.g. "Daily limit: 3 of 10 messages used (Haiku model)"
        var match = text.textContent.match(/(\d+) of (\d+)/);
        var model = 'claude-haiku-4-5-20251001';
        var mMatch = text.textContent.match(/\((\w+) model\)/);
        if (mMatch) {
            var mLabels = { 'Haiku': 'claude-haiku-4-5-20251001', 'Sonnet': 'claude-sonnet-4-6', 'Opus': 'claude-opus-4-6' };
            model = mLabels[mMatch[1]] || model;
        }
        var limit = match ? parseInt(match[2]) : -1;
        var used  = limit >= 0 ? Math.max(0, limit - remaining) : 0;
        renderLimitBar(used, limit, model);
    }

    function fetchUserStatus() {
        if (!tgSession) return;
        var fd = new FormData();
        fd.append('action', 'shopys_ai_get_status');
        fd.append('nonce', cfg.nonce);
        fd.append('tg_id', tgSession.telegram_id);
        fd.append('tg_auth_date', tgSession.auth_date);
        fd.append('tg_session', tgSession.session);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', cfg.ajax_url, true);
        xhr.onload = function () {
            if (xhr.status !== 200) return;
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success && resp.data) {
                    renderLimitBar(resp.data.used, resp.data.limit, resp.data.model);
                    updateModelBadge(resp.data.model);
                }
            } catch (e) {}
        };
        xhr.send(fd);
    }

    function updateModelBadge(modelId) {
        var badge = document.getElementById('sai-model-badge');
        if (!badge) return;
        var labels = {
            'claude-haiku-4-5-20251001': 'Haiku — Fast',
            'claude-sonnet-4-6': 'Sonnet — Smart',
            'claude-opus-4-6': 'Opus — Most Capable'
        };
        badge.textContent = labels[modelId] || modelId;
    }

    function showNotMemberGate(groupUsername) {
        var groupSlug = groupUsername ? groupUsername.replace('@', '') : 'VSComputer520';
        var groupUrl  = 'https://t.me/' + groupSlug;

        // Open the Telegram group in a new tab immediately
        window.open(groupUrl, '_blank');

        // Show a "refresh after joining" message inside the chatbot
        if (tgLoginGate) tgLoginGate.style.display = 'none';
        var notMemberGate = document.getElementById('sai-not-member-gate');
        if (notMemberGate) notMemberGate.style.display = 'none';
        messagesEl.style.display = '';
        document.querySelector('.sai-toolbar').style.display = 'none';
        document.querySelector('.sai-attach-preview').style.display = 'none';
        document.querySelector('.sai-input-area').style.display = 'none';

        // Clear messages and show instructions
        messagesEl.innerHTML = '';
        var msg = document.createElement('div');
        msg.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:28px 20px;text-align:center;height:100%;';
        msg.innerHTML =
            '<div style="font-size:40px;">📢</div>' +
            '<div style="font-weight:700;font-size:15px;color:#111;">One more step!</div>' +
            '<div style="font-size:13px;color:#555;line-height:1.6;">' +
            'We opened <strong>@' + groupSlug + '</strong> for you.<br>' +
            'Join the group in Telegram, then come back<br>and <strong>refresh this page</strong> to start chatting.' +
            '</div>' +
            '<button onclick="location.reload();" ' +
            'style="background:#00c44f;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">' +
            '↺ Refresh Page' +
            '</button>' +
            '<a href="' + groupUrl + '" target="_blank" rel="noopener" ' +
            'style="font-size:12px;color:#888;text-decoration:underline;">Open group again</a>';
        messagesEl.appendChild(msg);
    }

    function renderTgBadge() {
        // Remove existing badge
        var existing = document.querySelector('.sai-tg-user-badge');
        if (existing) existing.remove();
        if (!tgSession) return;

        var name    = tgSession.first_name || '';
        var initial = name.charAt(0).toUpperCase();

        // Avatar content: photo or initial letter
        var avatarInner = tgSession.photo_url
            ? '<img src="' + escAttr(tgSession.photo_url) + '" alt="" />'
            : '<span class="sai-tg-badge-initial">' + escHtml(initial) + '</span>';

        var badge = document.createElement('div');
        badge.className = 'sai-tg-user-badge';
        badge.innerHTML =
            '<button class="sai-tg-badge-btn" title="' + escAttr(name) + '">' + avatarInner + '</button>' +
            '<div class="sai-tg-badge-dropdown">' +
                '<div class="sai-tg-badge-profile">' +
                    '<div class="sai-tg-badge-avatar-lg">' + avatarInner + '</div>' +
                    '<div class="sai-tg-badge-info">' +
                        '<div class="sai-tg-badge-fullname">' + escHtml(name) + '</div>' +
                        '<div class="sai-tg-badge-id">Telegram</div>' +
                    '</div>' +
                '</div>' +
                '<button class="sai-tg-logout-btn">Logout</button>' +
            '</div>';

        // Insert as FIRST child of header actions (before +, ⛶, × buttons)
        var actions = document.getElementById('sai-header-actions');
        if (actions) actions.insertBefore(badge, actions.firstChild);

        // Toggle dropdown on avatar click
        var btn      = badge.querySelector('.sai-tg-badge-btn');
        var dropdown = badge.querySelector('.sai-tg-badge-dropdown');
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('sai-tg-badge-dd--open');
        });
        document.addEventListener('click', function closeDD() {
            dropdown.classList.remove('sai-tg-badge-dd--open');
        });

        badge.querySelector('.sai-tg-logout-btn').addEventListener('click', function () {
            tgSession = null;
            localStorage.removeItem('sai_tg_session');
            newChat();
            showLoginGate();
        });
    }

    // Global callback for Telegram widget
    window.shopysAI_onTelegramAuth = function (user) {
        // Verify with server and save session
        var formData = new FormData();
        formData.append('action', 'shopys_ai_tg_auth');
        formData.append('nonce', cfg.nonce);
        formData.append('tg_data', JSON.stringify(user));

        var xhr = new XMLHttpRequest();
        xhr.open('POST', cfg.ajax_url, true);
        xhr.onload = function () {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    tgSession = {
                        telegram_id: resp.data.telegram_id,
                        auth_date: user.auth_date,
                        session: resp.data.session,
                        first_name: resp.data.first_name,
                        photo_url: resp.data.photo_url || ''
                    };
                    localStorage.setItem('sai_tg_session', JSON.stringify(tgSession));

                    // Check group membership
                    if (resp.data.is_group_member === false) {
                        showNotMemberGate(resp.data.tg_group || '');
                    } else {
                        showChatUI();
                        showWelcome();
                    }
                } else {
                    alert(resp.data && resp.data.message ? resp.data.message : 'Login failed. Please try again.');
                }
            } catch (e) {
                alert('Login failed. Please try again.');
            }
        };
        xhr.send(formData);
    };

    function toggleChat() {
        isOpen = !isOpen;
        toggle.classList.toggle('sai-active', isOpen);
        chatWindow.classList.toggle('sai-open', isOpen);

        if (isOpen && messagesEl.children.length === 0) {
            // If Telegram login required and not logged in, don't show welcome
            if (cfg.require_tg_login === '1' && !tgSession) {
                // Gate will be shown by initTelegramGate
            } else {
                // Try to restore previous chat first; only show welcome if there's nothing to restore.
                var restored = loadHistory();
                if (!restored) showWelcome();
            }
        }

        if (isOpen) {
            setTimeout(function () { inputEl.focus(); }, 300);
        }
    }

    function showWelcome() {
        var lines = cfg.welcome_msg.split('\n');
        var html = '';
        for (var i = 0; i < lines.length; i++) {
            html += '<p>' + escHtml(lines[i]) + '</p>';
        }
        appendBot(html, []);
    }

    /* ── Send Message ─────────────────────────────────── */

    function sendMessage() {
        if (isSending) return;
        var text = inputEl.value.trim();
        var hasFiles = attachments.length > 0;
        if (!text && !hasFiles) return;

        // Show user message with attachment thumbnails
        appendUser(text, attachments.slice());
        inputEl.value = '';
        inputEl.style.height = 'auto';

        // Add to history (store file metadata only — base64 data is too large for localStorage).
        var fileMeta = attachments.map(function (f) {
            return { name: f.name, type: f.type };
        });
        history.push({ role: 'user', text: text || '(attached file)', files: fileMeta });
        saveHistory();

        // Grab current attachments and clear
        var filesToSend = attachments.slice();
        clearAttachments();

        // Show typing indicator
        var typingId = showTyping();
        isSending = true;
        sendBtn.classList.add('sai-disabled');

        // AJAX request
        var formData = new FormData();
        formData.append('action', 'shopys_ai_chat');
        formData.append('nonce', cfg.nonce);
        formData.append('message', text);
        formData.append('history', JSON.stringify(history.slice(-10)));
        // model is assigned server-side based on user tier
        formData.append('page_url', window.location.href);

        // Attach Telegram session if required
        if (cfg.require_tg_login === '1' && tgSession) {
            formData.append('tg_id', tgSession.telegram_id);
            formData.append('tg_auth_date', tgSession.auth_date);
            formData.append('tg_session', tgSession.session);
        }

        // Attach files as base64 JSON
        if (filesToSend.length > 0) {
            var fileData = [];
            for (var f = 0; f < filesToSend.length; f++) {
                fileData.push({
                    name: filesToSend[f].name,
                    type: filesToSend[f].type,
                    data: filesToSend[f].data
                });
            }
            formData.append('attachments', JSON.stringify(fileData));
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', cfg.ajax_url, true);
        xhr.onload = function () {
            removeTyping(typingId);
            isSending = false;
            sendBtn.classList.remove('sai-disabled');

            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success && resp.data) {
                        var msg = resp.data.message || 'Sorry, no response.';
                        var products = resp.data.products || [];

                        // Add AI response to history (with pre-rendered HTML + product cards
                        // so we can re-render exactly on next page load).
                        var formattedHtml = formatMessage(msg);
                        history.push({ role: 'assistant', text: msg, html: formattedHtml, products: products });
                        saveHistory();

                        appendBot(formattedHtml, products);

                        // Show remaining messages count
                        if (resp.data && typeof resp.data.remaining !== 'undefined' && resp.data.remaining >= 0) {
                            updateRemainingCount(resp.data.remaining);
                        }
                        // Update model badge
                        if (resp.data && resp.data.model_used) {
                            updateModelBadge(resp.data.model_used);
                        }
                    } else {
                        // If daily limit hit
                        if (resp.data && resp.data.limit_hit) {
                            updateRemainingCount(0);
                        }
                        // If server says login required (session expired), show login gate
                        if (resp.data && resp.data.require_login) {
                            tgSession = null;
                            localStorage.removeItem('sai_tg_session');
                            showLoginGate();
                        }
                        // If not a group member, show join gate
                        if (resp.data && resp.data.not_member) {
                            showNotMemberGate(resp.data.tg_group || '');
                            return;
                        }
                        var errMsg = (resp.data && resp.data.message) ? resp.data.message : 'Something went wrong.';
                        appendBot('<p>' + escHtml(errMsg) + '</p>', []);
                    }
                } catch (e) {
                    appendBot('<p>Sorry, I encountered an error. Please try again.</p>', []);
                }
            } else {
                appendBot('<p>Connection error. Please try again.</p>', []);
            }
        };
        xhr.onerror = function () {
            removeTyping(typingId);
            isSending = false;
            sendBtn.classList.remove('sai-disabled');
            appendBot('<p>Network error. Please check your connection.</p>', []);
        };
        xhr.send(formData);
    }

    /* ── Attachment Handling ──────────────────────────── */

    function handleDroppedFiles(files) {
        if (cfg.feat_attachments !== '1') return;
        var allowImage = cfg.feat_image_search === '1';
        var allowPdf = cfg.feat_pdf_reading === '1';
        for (var i = 0; i < files.length; i++) {
            var t = files[i].type;
            if (allowImage && /^image\/(jpeg|png|gif|webp)$/.test(t)) {
                processFile(files[i]);
            } else if (allowPdf && t === 'application/pdf') {
                processFile(files[i]);
            }
        }
    }

    function handleFileSelect(e) {
        var files = e.target.files;
        if (!files || !files.length) return;

        for (var i = 0; i < files.length; i++) {
            processFile(files[i]);
        }

        // Reset so same file can be re-selected
        fileInput.value = '';
    }

    function processFile(file) {
        // Max 5MB per file
        if (file.size > 5 * 1024 * 1024) {
            alert(file.name + ' is too large. Max 5MB.');
            return;
        }

        var reader = new FileReader();
        reader.onload = function (ev) {
            var base64 = ev.target.result.split(',')[1];
            attachments.push({
                name: file.name,
                type: file.type,
                data: base64,
                previewUrl: ev.target.result
            });
            renderAttachPreview();
        };
        reader.readAsDataURL(file);
    }

    function renderAttachPreview() {
        if (!attachments.length) {
            attachPreview.innerHTML = '';
            attachPreview.classList.remove('sai-has-files');
            return;
        }

        var html = '<div class="sai-attach-thumbs">';
        for (var i = 0; i < attachments.length; i++) {
            var a = attachments[i];
            var isImage = /^image\//.test(a.type);
            html += '<div class="sai-attach-item" data-index="' + i + '">';
            if (isImage) {
                html += '<img src="' + a.previewUrl + '" alt="' + escAttr(a.name) + '" />';
            } else {
                html += '<div class="sai-attach-file-icon">&#128196;</div>';
                html += '<span class="sai-attach-name">' + escHtml(a.name) + '</span>';
            }
            html += '<button class="sai-attach-remove" data-index="' + i + '" title="Remove">&times;</button>';
            html += '</div>';
        }
        html += '</div>';

        // Quick action buttons (conditional on feature flags)
        var hasImage = attachments.some(function (a) { return /^image\//.test(a.type); });
        var hasPdf = attachments.some(function (a) { return a.type === 'application/pdf'; });

        html += '<div class="sai-quick-actions">';
        if (hasImage && cfg.feat_image_search === '1') {
            html += '<button class="sai-quick-btn" data-cmd="find_product">&#x1F50D; Find Product</button>';
            html += '<button class="sai-quick-btn" data-cmd="read_image">&#x1F4D6; Read Text</button>';
            html += '<button class="sai-quick-btn" data-cmd="summarize_image">&#x2728; Summarize</button>';
        }
        if (hasPdf && cfg.feat_pdf_reading === '1') {
            html += '<button class="sai-quick-btn" data-cmd="summarize_pdf">&#x1F4C4; Summarize PDF</button>';
            html += '<button class="sai-quick-btn" data-cmd="read_pdf">&#x1F4D6; Read PDF</button>';
        }
        html += '</div>';

        attachPreview.innerHTML = html;
        attachPreview.classList.add('sai-has-files');

        // Bind remove buttons
        var removeBtns = attachPreview.querySelectorAll('.sai-attach-remove');
        for (var r = 0; r < removeBtns.length; r++) {
            removeBtns[r].addEventListener('click', function () {
                var idx = parseInt(this.getAttribute('data-index'), 10);
                attachments.splice(idx, 1);
                renderAttachPreview();
            });
        }

        // Bind quick action buttons
        var quickBtns = attachPreview.querySelectorAll('.sai-quick-btn');
        for (var q = 0; q < quickBtns.length; q++) {
            quickBtns[q].addEventListener('click', function () {
                var cmd = this.getAttribute('data-cmd');
                inputEl.value = getCommandText(cmd);
                sendMessage();
            });
        }
    }

    function getCommandText(cmd) {
        switch (cmd) {
            case 'find_product':  return '[FIND_PRODUCT] Find matching or similar products from the store based on this image.';
            case 'read_image':    return '[READ_TEXT] Read and extract all text from this image.';
            case 'summarize_image': return '[SUMMARIZE] Describe and summarize what is in this image.';
            case 'summarize_pdf': return '[SUMMARIZE] Summarize the key points of this PDF document.';
            case 'read_pdf':      return '[READ_TEXT] Read and extract the content of this PDF.';
            default:              return '';
        }
    }

    function clearAttachments() {
        attachments = [];
        renderAttachPreview();
    }

    /* ── Message Rendering ────────────────────────────── */

    function appendUser(text, files) {
        var div = document.createElement('div');
        div.className = 'sai-msg sai-msg-user';

        var inner = '';
        // Show attachment thumbnails in user bubble
        if (files && files.length) {
            inner += '<div class="sai-user-attachments">';
            for (var i = 0; i < files.length; i++) {
                // Only render <img> when we still have an in-memory previewUrl (fresh upload).
                // Restored history won't have the blob — fall back to a file chip.
                if (/^image\//.test(files[i].type) && files[i].previewUrl) {
                    inner += '<img class="sai-user-attach-img" src="' + files[i].previewUrl + '" alt="' + escAttr(files[i].name) + '" />';
                } else {
                    inner += '<div class="sai-user-attach-file">&#128196; ' + escHtml(files[i].name) + '</div>';
                }
            }
            inner += '</div>';
        }
        if (text) inner += escHtml(text);

        div.innerHTML = '<div class="sai-bubble sai-bubble-user">' + inner + '</div>';
        messagesEl.appendChild(div);
        scrollBottom();
    }

    function appendBot(html, products) {
        var div = document.createElement('div');
        div.className = 'sai-msg sai-msg-bot';

        var inner = '<div class="sai-bubble sai-bubble-bot">';
        inner += '<div class="sai-bot-text">' + html + '</div>';
        inner += '</div>';

        // Product grid (outside bubble, full width)
        if (products && products.length > 0) {
            inner += '<div class="sai-products-grid">';
            for (var i = 0; i < products.length; i++) {
                inner += renderProductCard(products[i]);
            }
            inner += '</div>';
        }

        div.innerHTML = inner;
        messagesEl.appendChild(div);

        // Syntax highlight code blocks
        if (typeof hljs !== 'undefined') {
            var codeBlocks = div.querySelectorAll('.sai-code-block code');
            for (var c = 0; c < codeBlocks.length; c++) {
                hljs.highlightElement(codeBlocks[c]);
            }
        }

        scrollBottom();
    }

    function renderProductCard(p) {
        var card = '<div class="sai-card">';

        // Image
        if (p.image) {
            card += '<a href="' + escAttr(p.url) + '" class="sai-card-img-link">';
            card += '<img class="sai-card-img" src="' + escAttr(p.image) + '" alt="' + escAttr(p.name) + '" loading="lazy" />';
            card += '</a>';
        }

        card += '<div class="sai-card-body">';

        // Category badge
        if (p.category) {
            card += '<span class="sai-card-cat">' + escHtml(p.category) + '</span>';
        }

        // Name
        card += '<a href="' + escAttr(p.url) + '" class="sai-card-name">' + escHtml(p.name) + '</a>';

        // Price
        if (p.price_html) {
            card += '<div class="sai-card-price">' + p.price_html + '</div>';
        }

        // Stock
        if (p.stock === 'outofstock') {
            card += '<span class="sai-card-stock sai-out">Out of Stock</span>';
        } else {
            card += '<span class="sai-card-stock sai-in">In Stock</span>';
        }

        // Buttons
        card += '<div class="sai-card-actions">';
        card += '<a href="' + escAttr(p.url) + '" class="sai-btn sai-btn-view">View</a>';
        if (p.stock !== 'outofstock' && p.type === 'simple') {
            card += '<a href="' + escAttr(p.add_to_cart) + '" class="sai-btn sai-btn-cart" data-product-id="' + p.id + '">Add to Cart</a>';
        }
        card += '</div>';

        card += '</div></div>';
        return card;
    }

    /* ── Typing Indicator ─────────────────────────────── */

    function showTyping() {
        var id = 'sai-typing-' + Date.now();
        var div = document.createElement('div');
        div.className = 'sai-msg sai-msg-bot';
        div.id = id;
        div.innerHTML = '<div class="sai-bubble sai-bubble-bot sai-typing">' +
            '<span class="sai-dot"></span><span class="sai-dot"></span><span class="sai-dot"></span>' +
            '</div>';
        messagesEl.appendChild(div);
        scrollBottom();
        return id;
    }

    function removeTyping(id) {
        var el = document.getElementById(id);
        if (el) el.remove();
    }

    /* ── Helpers ───────────────────────────────────────── */

    function formatMessage(text) {
        if (!text) return '';

        var lines = text.split('\n');
        var html = '';
        var i = 0;

        while (i < lines.length) {
            var line = lines[i];
            var trimmed = line.trim();

            // Empty line → skip
            if (!trimmed) { i++; continue; }

            // Fenced code block: ```
            if (/^```/.test(trimmed)) {
                var lang = trimmed.slice(3).trim();
                var codeLines = [];
                i++;
                while (i < lines.length && !/^```/.test(lines[i].trim())) {
                    codeLines.push(escHtml(lines[i]));
                    i++;
                }
                i++; // skip closing ```
                html += '<div class="sai-code-block">';
                if (lang) html += '<div class="sai-code-lang">' + escHtml(lang) + '</div>';
                var langClass = lang ? ' class="language-' + escAttr(lang) + '"' : '';
                html += '<pre><code' + langClass + '>' + codeLines.join('\n') + '</code></pre></div>';
                continue;
            }

            // Horizontal rule: --- or *** or ___
            if (/^[-*_]{3,}$/.test(trimmed)) {
                html += '<hr class="sai-hr">';
                i++; continue;
            }

            // Headers: # to ######
            var hMatch = trimmed.match(/^(#{1,6})\s+(.+)$/);
            if (hMatch) {
                var level = hMatch[1].length;
                html += '<h' + level + ' class="sai-h">' + inlineFormat(hMatch[2]) + '</h' + level + '>';
                i++; continue;
            }

            // Unordered list: - item, * item, • item
            if (/^[•●▪▸\-\*]\s+/.test(trimmed)) {
                html += '<ul class="sai-list">';
                while (i < lines.length && /^[•●▪▸\-\*]\s+/.test(lines[i].trim())) {
                    var liText = lines[i].trim().replace(/^[•●▪▸\-\*]\s+/, '');
                    html += '<li>' + inlineFormat(liText) + '</li>';
                    i++;
                }
                html += '</ul>';
                continue;
            }

            // Ordered list: 1. item or 1) item
            if (/^\d+[.)]\s+/.test(trimmed)) {
                html += '<ol class="sai-list sai-ol">';
                while (i < lines.length && /^\d+[.)]\s+/.test(lines[i].trim())) {
                    var olText = lines[i].trim().replace(/^\d+[.)]\s+/, '');
                    html += '<li>' + inlineFormat(olText) + '</li>';
                    i++;
                }
                html += '</ol>';
                continue;
            }

            // Blockquote: > text
            if (/^>\s*/.test(trimmed)) {
                var bqLines = [];
                while (i < lines.length && /^>\s*/.test(lines[i].trim())) {
                    bqLines.push(lines[i].trim().replace(/^>\s*/, ''));
                    i++;
                }
                html += '<blockquote class="sai-bq">' + inlineFormat(bqLines.join(' ')) + '</blockquote>';
                continue;
            }

            // Regular paragraph
            html += '<p>' + inlineFormat(trimmed) + '</p>';
            i++;
        }

        return html || '<p>' + escHtml(text) + '</p>';
    }

    function inlineFormat(text) {
        var s = escHtml(text);
        // Code inline: `text`
        s = s.replace(/`([^`]+?)`/g, '<code class="sai-inline-code">$1</code>');
        // Bold: **text**
        s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Italic: *text* (but not inside **)
        s = s.replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');
        // Strikethrough: ~~text~~
        s = s.replace(/~~(.+?)~~/g, '<del>$1</del>');
        // Linkify URLs
        s = linkifyUrls(s);
        return s;
    }

    function linkifyUrls(text) {
        // Match URLs: https://domain.com, http://domain.com, www.domain.com
        var urlRegex = /(https?:\/\/[^\s<>]+|www\.[^\s<>]+)/gi;
        return text.replace(urlRegex, function(url) {
            // Add protocol if only www
            var href = url;
            if (!href.match(/^https?:\/\//i)) {
                href = 'https://' + href;
            }
            // Create clickable link with target="_blank" for external links
            return '<a href="' + escAttr(href) + '" target="_blank" rel="noopener noreferrer" class="sai-link">' + escHtml(url) + '</a>';
        });
    }

    function scrollBottom() {
        setTimeout(function () {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }, 50);
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function escAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

})();
