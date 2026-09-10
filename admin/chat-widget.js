/**
 * Admin dashboard client-side behavior: sidebar tab switching, and the
 * live chat inbox (thread list, message view, reply form, unread badges).
 * Talks to Chat.php for all chat data. Loaded by admin.php.
 */
(function() {
    // Number inputs (stock, price, quantity-to-add) shouldn't change value just
    // because the admin scrolled the page while the cursor happened to be over
    // one. preventDefault stops the browser's built-in "scroll = step value"
    // behavior for that input, and blur() means the very next scroll just
    // scrolls the page normally instead of nudging the value again.
    document.addEventListener('wheel', function(event) {
        var active = document.activeElement;
        if (active && active.tagName === 'INPUT' && active.type === 'number') {
            event.preventDefault();
            active.blur();
        }
    }, { passive: false });

    var navButtons = document.querySelectorAll('.dash-nav-item');
    var sections = document.querySelectorAll('.dash-section');
    navButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            navButtons.forEach(function(b) { b.classList.remove('active'); });
            sections.forEach(function(s) { s.hidden = true; });
            btn.classList.add('active');
            var section = document.querySelector('.dash-section[data-panel="' + btn.dataset.section + '"]');
            if (section) section.hidden = false;

            // Keep the URL's ?panel= in sync with whichever tab is actually showing,
            // so a refresh reloads this same tab instead of falling back to
            // whatever panel a previous form submission last redirected to.
            var url = new URL(window.location.href);
            if (btn.dataset.section === 'overview') {
                url.searchParams.delete('panel');
            } else {
                url.searchParams.set('panel', btn.dataset.section);
            }
            window.history.replaceState(null, '', url);
        });
    });

    /* ---------- Live chat inbox ---------- */
    var threadListEl = document.getElementById('chat-thread-list');
    var chatLogEl = document.getElementById('chat-log');
    var chatPanelHead = document.getElementById('chat-panel-head');
    var replyForm = document.getElementById('chat-reply-form');
    var replyInput = document.getElementById('chat-reply-input');
    var navBadge = document.getElementById('chat-nav-badge');
    var topbarBell = document.getElementById('chat-topbar-bell');
    var topbarBadge = document.getElementById('chat-topbar-badge');
    var threadSearchInput = document.getElementById('chat-thread-search');
    if (threadSearchInput) {
        threadSearchInput.addEventListener('input', function() {
            threadSearchQuery = threadSearchInput.value.trim().toLowerCase();
            renderThreadList();
        });
    }
    var activeThreadId = null;
    var threadsCache = [];
    var threadSearchQuery = '';

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getFilteredThreads() {
        if (!threadSearchQuery) return threadsCache;
        return threadsCache.filter(function(thread) {
            return thread.name.toLowerCase().indexOf(threadSearchQuery) !== -1;
        });
    }

    function renderThreadList() {
        if (!threadsCache.length) {
            threadListEl.innerHTML = '<p class="dash-empty">No conversations yet.</p>';
            return;
        }
        var filteredThreads = getFilteredThreads();
        if (!filteredThreads.length) {
            threadListEl.innerHTML = '<p class="dash-empty">No conversations match your search.</p>';
            return;
        }
        threadListEl.innerHTML = filteredThreads.map(function(thread) {
            var activeClass = thread.id === activeThreadId ? ' active' : '';
            var unreadBadge = thread.unread > 0 ? '<span class="dash-chat-unread-dot">' + thread.unread + '</span>' : '';
            var preview = thread.lastMessage ? escapeHtml(thread.lastMessage) : 'No messages yet';
            return '<button type="button" class="dash-chat-thread' + activeClass + '" data-thread-id="' + thread.id + '">' +
                '<div class="dash-chat-thread-top"><span class="dash-chat-thread-name">' + escapeHtml(thread.name) + '</span>' + unreadBadge + '</div>' +
                '<span class="dash-chat-thread-preview">' + preview + '</span>' +
                '<span class="dash-chat-thread-time">' + thread.time + '</span></button>';
        }).join('');

        threadListEl.querySelectorAll('.dash-chat-thread').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openThread(parseInt(btn.dataset.threadId, 10));
            });
        });
    }

    function refreshThreadList() {
        fetch('Chat.php?action=admin_threads')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data || !data.success) return;
                threadsCache = data.threads;
                renderThreadList();
                renderChatNotifDropdown();
            })
            .catch(function() {});
    }

    function refreshUnreadBadge() {
        fetch('Chat.php?action=admin_unread_total')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data || !data.success) return;
                var count = data.unreadThreads;
                if (count > 0) {
                    navBadge.textContent = count;
                    navBadge.hidden = false;
                    topbarBadge.textContent = count;
                    topbarBadge.hidden = false;
                } else {
                    navBadge.hidden = true;
                    topbarBadge.hidden = true;
                }
            })
            .catch(function() {});
    }

    function renderChatNotifDropdown() {
        var listEl = document.getElementById('chat-notif-list');
        if (!listEl) return;
        var unread = threadsCache.filter(function(t) { return t.unread > 0; });
        if (!unread.length) {
            listEl.innerHTML = '<p class="dash-empty">No unread messages.</p>';
            return;
        }
        listEl.innerHTML = unread.map(function(thread) {
            var preview = thread.lastMessage ? escapeHtml(thread.lastMessage) : 'No messages yet';
            return '<button type="button" class="dash-notif-item" data-thread-id="' + thread.id + '">' +
                '<span>' + escapeHtml(thread.name) + '</span>' +
                '<span class="dash-notif-item-count">' + preview + '</span></button>';
        }).join('');
        listEl.querySelectorAll('[data-thread-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                closeAllNotifDropdowns();
                var messagesNav = document.querySelector('.dash-nav-item[data-section="messages"]');
                if (messagesNav) messagesNav.click();
                openThread(parseInt(btn.dataset.threadId, 10));
            });
        });
    }

    function closeAllNotifDropdowns() {
        document.querySelectorAll('.dash-notif-dropdown').forEach(function(dropdown) {
            dropdown.hidden = true;
        });
    }

    function toggleNotifDropdown(dropdown) {
        var wasHidden = dropdown.hidden;
        closeAllNotifDropdowns();
        dropdown.hidden = !wasHidden;
    }

    function renderMessages(messages) {
        if (!messages.length) {
            chatLogEl.innerHTML = '<div class="dash-chat-empty">No messages in this conversation yet.</div>';
            return;
        }
        chatLogEl.innerHTML = messages.map(function(m) {
            var cls = m.sender === 'admin' ? 'from-admin' : 'from-client';
            return '<div class="dash-chat-bubble ' + cls + '">' + escapeHtml(m.message) +
                '<span class="dash-chat-bubble-time">' + m.time + '</span></div>';
        }).join('');
        chatLogEl.scrollTop = chatLogEl.scrollHeight;
    }

    function openThread(threadId) {
        activeThreadId = threadId;
        renderThreadList();
        var thread = threadsCache.find(function(t) { return t.id === threadId; });
        chatPanelHead.textContent = thread ? thread.name : 'Conversation';
        replyForm.hidden = false;
        fetch('Chat.php?action=admin_thread_messages&thread_id=' + threadId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data || !data.success) return;
                renderMessages(data.messages);
                refreshUnreadBadge();
                refreshThreadList();
            })
            .catch(function() {});
    }

    replyForm.addEventListener('submit', function(event) {
        event.preventDefault();
        var message = replyInput.value.trim();
        if (!message || !activeThreadId) return;
        var formData = new FormData();
        formData.set('action', 'admin_reply');
        formData.set('thread_id', activeThreadId);
        formData.set('message', message);
        replyInput.value = '';
        fetch('Chat.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function() {
                openThread(activeThreadId);
            })
            .catch(function() {});
    });

    topbarBell.addEventListener('click', function(event) {
        event.stopPropagation();
        var dropdown = document.getElementById('chat-notif-dropdown');
        if (dropdown) toggleNotifDropdown(dropdown);
    });

    var lowStockBell = document.getElementById('lowstock-topbar-bell');
    if (lowStockBell) {
        lowStockBell.addEventListener('click', function(event) {
            event.stopPropagation();
            var dropdown = document.getElementById('lowstock-notif-dropdown');
            if (dropdown) toggleNotifDropdown(dropdown);
        });
    }

    document.querySelectorAll('[data-lowstock-item]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            closeAllNotifDropdowns();
            var productsNav = document.querySelector('.dash-nav-item[data-section="products"]');
            if (productsNav) productsNav.click();
        });
    });

    document.addEventListener('click', function(event) {
        if (!event.target.closest('.dash-notif-wrap')) {
            closeAllNotifDropdowns();
        }
    });

    refreshThreadList();
    refreshUnreadBadge();
    setInterval(function() {
        refreshThreadList();
        refreshUnreadBadge();
        if (activeThreadId && document.querySelector('.dash-section[data-panel="messages"]') && !document.querySelector('.dash-section[data-panel="messages"]').hidden) {
            fetch('Chat.php?action=admin_thread_messages&thread_id=' + activeThreadId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) renderMessages(data.messages);
                })
                .catch(function() {});
        }
    }, 6000);
})();