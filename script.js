function scrollProducts(direction) {
    var row = document.getElementById('products-row');
    if (!row) return;
    var amount = Math.round(row.clientWidth * 0.8);
    if (typeof row.scrollBy === 'function') {
        row.scrollBy({ left: direction * amount, top: 0, behavior: 'smooth' });
    } else {
        row.scrollLeft = row.scrollLeft + (direction * amount);
    }
}

function scrollTestimonials(direction) {
    var row = document.getElementById('t-row');
    if (!row) return;
    var amount = Math.round(row.clientWidth * 0.8);
    if (typeof row.scrollBy === 'function') {
        row.scrollBy({ left: direction * amount, top: 0, behavior: 'smooth' });
    } else {
        row.scrollLeft = row.scrollLeft + (direction * amount);
    }
}

function setupInfiniteProducts() {
    var row = document.getElementById('products-row');
    if (!row) return;
    var originalCards = Array.from(row.children);
    if (originalCards.length === 0) return;

    originalCards.forEach(function(card) {
        row.insertBefore(card.cloneNode(true), row.firstChild);
    });
    originalCards.forEach(function(card) {
        row.appendChild(card.cloneNode(true));
    });

    var setWidth = 0;

    function measure() {
        setWidth = row.scrollWidth / 3;
        var behavior = row.style.scrollBehavior;
        row.style.scrollBehavior = 'auto';
        row.scrollLeft = setWidth;
        row.style.scrollBehavior = behavior;
    }

    requestAnimationFrame(measure);
    window.addEventListener('resize', measure);

    row.addEventListener('scroll', function() {
        if (!setWidth) return;
        var buffer = 4;
        if (row.scrollLeft <= buffer) {
            row.style.scrollBehavior = 'auto';
            row.scrollLeft += setWidth;
            row.style.scrollBehavior = '';
        } else if (row.scrollLeft >= setWidth * 2 - buffer) {
            row.style.scrollBehavior = 'auto';
            row.scrollLeft -= setWidth;
            row.style.scrollBehavior = '';
        }
    });
}

function setupInfiniteTestimonials() {
    var row = document.getElementById('t-row');
    if (!row) return;
    var originalCards = Array.from(row.children);
    if (originalCards.length === 0) return;

    originalCards.forEach(function(card) {
        row.insertBefore(card.cloneNode(true), row.firstChild);
    });
    originalCards.forEach(function(card) {
        row.appendChild(card.cloneNode(true));
    });

    var setWidth = 0;

    function measure() {
        setWidth = row.scrollWidth / 3;
        var behavior = row.style.scrollBehavior;
        row.style.scrollBehavior = 'auto';
        row.scrollLeft = setWidth;
        row.style.scrollBehavior = behavior;
    }

    requestAnimationFrame(measure);
    window.addEventListener('resize', measure);

    row.addEventListener('scroll', function() {
        if (!setWidth) return;
        var buffer = 4;
        if (row.scrollLeft <= buffer) {
            row.style.scrollBehavior = 'auto';
            row.scrollLeft += setWidth;
            row.style.scrollBehavior = '';
        } else if (row.scrollLeft >= setWidth * 2 - buffer) {
            row.style.scrollBehavior = 'auto';
            row.scrollLeft -= setWidth;
            row.style.scrollBehavior = '';
        }
    });
}

function setupActiveProductCard() {
    var row = document.getElementById('products-row');
    if (!row) return;

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            entry.target.classList.toggle('is-active', entry.intersectionRatio >= 0.65);
        });
    }, { root: row, threshold: [0, 0.25, 0.5, 0.65, 0.75, 1] });

    Array.from(row.children).forEach(function(card) {
        observer.observe(card);
    });
}

const navLinks = Array.from(document.querySelectorAll('.nav-links span'));

function setActiveNav(id) {
    navLinks.forEach(function(link) {
        link.classList.toggle('active', link.dataset.target === id);
    });
}

function smoothScrollTo(targetY) {
    var startY = window.scrollY;
    var distance = targetY - startY;
    var duration = 800;
    var start = null;

    function step(timestamp) {
        if (!start) start = timestamp;
        var progress = Math.min((timestamp - start) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        // behavior: 'auto' is required here — without it, the CSS
        // `scroll-behavior: smooth` on <html> makes the browser smooth-animate
        // *each* of these per-frame jumps too, stacking on top of this
        // function's own easing and producing a visible shake/jitter.
        window.scrollTo({ top: startY + (distance * eased), left: 0, behavior: 'auto' });

        if (progress < 1) {
            requestAnimationFrame(step);
        }
    }

    requestAnimationFrame(step);
}

function setupScrollSpy() {
    // "top" lives on <main>, which wraps every section on the page, so it
    // can't be used as the scroll target for Home. Use the actual hero
    // section as its stand-in instead.
    var heroSection = document.querySelector('.hero');

    var sections = [];
    navLinks.forEach(function(link) {
        var targetId = link.dataset.target;
        if (!targetId) return;
        var el = targetId === 'top' ? heroSection : document.getElementById(targetId);
        if (el && !sections.some(function(s) { return s.el === el; })) {
            sections.push({ id: targetId, el: el });
        }
    });
    // sections is built in nav order (Home, About Me, Featured, Contact Us),
    // which already matches their top-to-bottom order in the page markup.

    if (!sections.length) return;

    var headerOffset = 96; // sticky header height + a little buffer

    function updateActiveSection() {
        // The active section is the last one whose top has scrolled past the
        // header — checked directly against current position, so there's no
        // ambiguity from multiple sections reporting "intersecting" at once.
        var current = sections[0].id;
        for (var i = 0; i < sections.length; i++) {
            if (sections[i].el.getBoundingClientRect().top <= headerOffset) {
                current = sections[i].id;
            } else {
                break;
            }
        }
        setActiveNav(current);
    }

    var ticking = false;
    window.addEventListener('scroll', function() {
        if (!ticking) {
            window.requestAnimationFrame(function() {
                updateActiveSection();
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    updateActiveSection();
}

function goTo(id) {
    var el = document.getElementById(id);
    if (el) {
        var headerOffset = 82;
        var targetY = el.getBoundingClientRect().top + window.scrollY - headerOffset;
        smoothScrollTo(Math.max(0, targetY));
    }
    setActiveNav(id);
}

/* ---------- Live chat (storefront widget) ---------- */

var chatPollTimer = null;

function escapeChatHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderChatMessages(messages) {
    var container = document.getElementById('chat-messages');
    if (!container) return;
    if (!messages || !messages.length) {
        container.innerHTML = '<p class="chat-empty">Say hello — we\'re happy to help.</p>';
        return;
    }
    var wasNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 40;
    container.innerHTML = messages.map(function(m) {
        var cls = m.sender === 'admin' ? 'from-admin' : 'from-client';
        return '<div class="chat-bubble ' + cls + '">' + escapeChatHtml(m.message) +
            '<span class="chat-bubble-time">' + m.time + '</span></div>';
    }).join('');
    if (wasNearBottom) {
        container.scrollTop = container.scrollHeight;
    }
}

function pollChatMessages() {
    fetch('Chat.php?action=poll')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data && data.success) {
                renderChatMessages(data.messages);
            }
        })
        .catch(function() {});
}

function startChatPolling() {
    pollChatMessages();
    stopChatPolling();
    chatPollTimer = setInterval(pollChatMessages, 5000);
}

function stopChatPolling() {
    if (chatPollTimer) {
        clearInterval(chatPollTimer);
        chatPollTimer = null;
    }
}

function setupChatForm() {
    var form = document.getElementById('chat-form');
    if (!form) return;
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        var messageInput = document.getElementById('chat-message-input');
        var nameInput = document.getElementById('chat-name-input');
        var message = messageInput.value.trim();
        if (!message) return;

        var formData = new FormData();
        formData.set('action', 'send');
        formData.set('message', message);
        if (nameInput && !nameInput.hidden) {
            formData.set('name', nameInput.value.trim());
        }
        messageInput.value = '';

        fetch('Chat.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.success) {
                    pollChatMessages();
                }
            })
            .catch(function() {});
    });
}

function toggleCart() {
    var cartPanel = document.getElementById('cart-panel');
    if (!cartPanel) return;

    var isOpen = cartPanel.classList.toggle('open');
    cartPanel.setAttribute('aria-hidden', String(!isOpen));
}

/* ---------- Order status notification bell ---------- */

var notifToastedIds = {};

function showOrderToast(message) {
    if (!message) return;
    var toast = document.getElementById('order-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'order-toast';
        toast.className = 'cart-toast order-toast';
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    clearTimeout(toast._hideTimer);
    requestAnimationFrame(function() {
        toast.classList.add('show');
    });
    toast._hideTimer = setTimeout(function() {
        toast.classList.remove('show');
    }, 3200);
}

function renderNotifications(notifications) {
    var list = document.getElementById('notif-list');
    if (!list) return;

    if (!notifications || !notifications.length) {
        list.innerHTML = '<p class="notif-empty">No order updates yet.</p>';
        return;
    }

    list.innerHTML = notifications.map(function(notification) {
        var orderText = notification.status === 'delivered' ? 'Your order was successful' :
            notification.status === 'failed' ? 'Your order failed' :
            'Your order';
        var metaParts = [orderText];
        if (notification.orderedAt) metaParts.push(notification.orderedAt);
        return '<div class="notif-item notif-' + notification.status + (notification.unread ? ' notif-unread' : '') + '" data-order-id="' + notification.orderId + '" role="button" tabindex="0">' +
            '<span class="notif-item-meta">' + metaParts.join(' · ') + '</span>' +
            '<span class="notif-item-status">' + notification.statusLabel + '</span>' +
            '</div>';
    }).join('');

    list.querySelectorAll('.notif-item').forEach(function(item) {
        function goToOrder() {
            var panel = document.getElementById('notif-panel');
            var toggleBtn = document.getElementById('notif-toggle-btn');
            if (panel) {
                panel.classList.remove('open');
                panel.setAttribute('aria-hidden', 'true');
            }
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
            window.location.href = 'account.php#order-' + item.dataset.orderId;
        }
        item.addEventListener('click', goToOrder);
        item.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                goToOrder();
            }
        });
    });
}

function updateNotifBadge(count) {
    var badge = document.getElementById('notif-count');
    var toggleBtn = document.getElementById('notif-toggle-btn');
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count > 9 ? '9+' : String(count);
        badge.style.display = '';
        if (toggleBtn) {
            toggleBtn.classList.remove('bump');
            void toggleBtn.offsetWidth;
            toggleBtn.classList.add('bump');
        }
    } else {
        badge.style.display = 'none';
    }
}

function pollNotifications() {
    if (!document.getElementById('notif-toggle-btn')) return;
    fetch('Notification.php?action=poll&_=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' })
        .then(function(response) {
            if (!response.ok) throw new Error('Notification.php poll failed: ' + response.status);
            return response.json();
        })
        .then(function(data) {
            if (!data.success) {
                console.error('Notification poll error:', data.message);
                return;
            }
            renderNotifications(data.notifications);
            updateNotifBadge(data.unreadCount);

            (data.notifications || []).forEach(function(notification) {
                if (notification.unread && !notifToastedIds[notification.orderId]) {
                    notifToastedIds[notification.orderId] = true;
                    var toastText = notification.status === 'delivered' ? 'Your order was successful.' :
                        notification.status === 'failed' ? 'Your order failed.' :
                        'Your order is now ' + notification.statusLabel + '.';
                    showOrderToast(toastText);
                }
            });
        })
        .catch(function(error) {
            console.error('Notification poll failed:', error);
        });
}

function toggleNotifications() {
    var panel = document.getElementById('notif-panel');
    var toggleBtn = document.getElementById('notif-toggle-btn');
    if (!panel || !toggleBtn) return;

    var isOpen = panel.classList.toggle('open');
    panel.setAttribute('aria-hidden', String(!isOpen));
    toggleBtn.setAttribute('aria-expanded', String(isOpen));

    if (isOpen) {
        fetch('Notification.php?action=mark_read&_=' + Date.now(), { method: 'POST', credentials: 'same-origin', cache: 'no-store' })
            .then(function(response) {
                if (!response.ok) throw new Error('Notification.php mark_read failed: ' + response.status);
                return response.json();
            })
            .then(function(data) {
                if (!data.success) {
                    console.error('Notification mark_read error:', data.message);
                    return;
                }
                renderNotifications(data.notifications);
                updateNotifBadge(0);
            })
            .catch(function(error) {
                console.error('Notification mark_read failed:', error);
            });
    }
}

function setupNotifications() {
    var toggleBtn = document.getElementById('notif-toggle-btn');
    if (!toggleBtn) return;

    pollNotifications();
    setInterval(pollNotifications, 10000);

    document.addEventListener('click', function(event) {
        var wrap = document.querySelector('.notif-wrap');
        var panel = document.getElementById('notif-panel');
        if (!wrap || !panel || !panel.classList.contains('open')) return;
        if (!wrap.contains(event.target)) {
            panel.classList.remove('open');
            panel.setAttribute('aria-hidden', 'true');
            toggleBtn.setAttribute('aria-expanded', 'false');
        }
    });
}

/* ---------- Prevent scroll-wheel from changing focused number inputs ---------- */

document.addEventListener('wheel', function(event) {
    var active = document.activeElement;
    if (active && active.tagName === 'INPUT' && active.type === 'number') {
        active.blur();
    }
}, { passive: true });

/* ---------- Password visibility toggle ---------- */

function setupPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(function(button) {
        button.addEventListener('click', function() {
            var input = document.getElementById(button.dataset.toggleFor);
            if (!input) return;
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.classList.toggle('is-showing', !showing);
            button.setAttribute('aria-pressed', String(!showing));
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            input.focus();
        });
    });
}

/* ---------- Confirm-password match check (register form) ---------- */

function setupPasswordConfirmCheck() {
    var password = document.getElementById('register-password');
    var confirm = document.getElementById('register-confirm-password');
    var errorEl = document.getElementById('confirm-password-error');
    if (!password || !confirm || !errorEl) return;

    function validateMatch() {
        var mismatch = confirm.value !== '' && confirm.value !== password.value;
        errorEl.hidden = !mismatch;
        confirm.setCustomValidity(mismatch ? 'Passwords do not match.' : '');
        return !mismatch;
    }

    password.addEventListener('input', validateMatch);
    confirm.addEventListener('input', validateMatch);

    var form = confirm.closest('form');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!validateMatch()) {
                event.preventDefault();
                confirm.focus();
            }
        });
    }
}

/* ---------- Guest sign-up / login modal ---------- */

function openAuthModal() {
    var backdrop = document.getElementById('auth-modal-backdrop');
    var modal = document.getElementById('auth-modal');
    if (!backdrop || !modal) return;
    backdrop.classList.add('open');
    modal.classList.add('open');
}

function closeAuthModal() {
    var backdrop = document.getElementById('auth-modal-backdrop');
    var modal = document.getElementById('auth-modal');
    if (!backdrop || !modal) return;
    backdrop.classList.remove('open');
    modal.classList.remove('open');
}

/* ---------- AJAX Cart (no page reload) ---------- */

function getCartCountEl() {
    return document.getElementById('cart-count');
}

function updateCartBadge(count) {
    var countEl = getCartCountEl();
    var toggleBtn = document.getElementById('cart-toggle-btn');
    if (!countEl) return;
    countEl.textContent = count;
    countEl.style.display = count > 0 ? '' : 'none';
    countEl.classList.remove('pop');
    void countEl.offsetWidth;
    countEl.classList.add('pop');
    if (toggleBtn) {
        toggleBtn.classList.remove('bump');
        void toggleBtn.offsetWidth;
        toggleBtn.classList.add('bump');
    }
}

function showCartToast(message) {
    if (!message) return;
    var toast = document.getElementById('cart-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'cart-toast';
        toast.className = 'cart-toast';
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    clearTimeout(toast._hideTimer);
    requestAnimationFrame(function() {
        toast.classList.add('show');
    });
    toast._hideTimer = setTimeout(function() {
        toast.classList.remove('show');
    }, 2200);
}

function flyToCart(sourceImg) {
    if (!sourceImg) return;
    var target = document.getElementById('cart-toggle-btn');
    if (!target) return;

    var startRect = sourceImg.getBoundingClientRect();
    var endRect = target.getBoundingClientRect();

    var clone = sourceImg.cloneNode(true);
    clone.className = 'fly-to-cart';
    clone.style.left = startRect.left + 'px';
    clone.style.top = startRect.top + 'px';
    clone.style.width = startRect.width + 'px';
    clone.style.height = startRect.height + 'px';
    clone.style.opacity = '1';
    document.body.appendChild(clone);

    var endX = endRect.left + endRect.width / 2 - (startRect.left + startRect.width / 2);
    var endY = endRect.top + endRect.height / 2 - (startRect.top + startRect.height / 2);

    requestAnimationFrame(function() {
        requestAnimationFrame(function() {
            clone.style.transform = 'translate(' + endX + 'px, ' + endY + 'px) scale(0.12)';
            clone.style.opacity = '0.15';
            clone.style.width = startRect.width * 0.5 + 'px';
            clone.style.height = startRect.height * 0.5 + 'px';
        });
    });

    clone.addEventListener('transitionend', function() {
        clone.remove();
    }, { once: true });

    setTimeout(function() {
        if (clone.parentNode) clone.remove();
    }, 900);
}

function refreshCartPanel(cartHtml) {
    var cartContent = document.getElementById('cart-content');
    if (cartContent && typeof cartHtml === 'string') {
        cartContent.innerHTML = cartHtml;
    }
}

function syncProductCards(productId, stock, cartQuantity) {
    if (!productId || stock === null || stock === undefined) return;
    var cards = document.querySelectorAll('.product-card[data-product-id="' + CSS.escape(String(productId)) + '"]');
    cards.forEach(function(card) {
        var button = card.querySelector('.cart-button');
        var label = card.querySelector('[data-stock-label]');
        var outOfStock = stock < 1;
        var atMax = !outOfStock && cartQuantity >= stock;

        if (label) {
            label.textContent = outOfStock ? 'No stock' : ('Stock: ' + stock);
            label.classList.toggle('out-of-stock', outOfStock);
        }
        if (button) {
            button.disabled = outOfStock || atMax;
            button.classList.toggle('out-of-stock-button', outOfStock || atMax);
            button.textContent = outOfStock ? 'No stock' : (atMax ? 'Max in Cart' : 'Add to Cart');
        }
    });
}

function handleCartFormSubmit(form, submitter) {
    var actionField = form.querySelector('[name="action"]');
    var action = actionField ? actionField.value : '';

    if (action === 'add_to_cart' && !window.IS_LOGGED_IN) {
        openAuthModal();
        return;
    }

    var formData = new FormData(form);
    if (submitter && submitter.name) {
        formData.set(submitter.name, submitter.value);
    }

    var sourceImg = null;
    if (action === 'add_to_cart') {
        var card = form.closest('.product-card');
        sourceImg = card ? card.querySelector('.product-thumb img') : null;
    }

    if (submitter) submitter.disabled = true;

    fetch('Cart.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data || !data.success) {
                if (data && data.requiresLogin) {
                    openAuthModal();
                    return;
                }
                if (data && data.atMaxStock) {
                    syncProductCards(data.productId, data.productStock, data.cartQuantity);
                    showCartToast(data.message);
                }
                return;
            }
            refreshCartPanel(data.cartHtml);
            syncProductCards(data.productId, data.productStock, data.cartQuantity);
            if (action === 'add_to_cart' && sourceImg) {
                flyToCart(sourceImg);
                showCartToast(data.message);
                setTimeout(function() { updateCartBadge(data.cartCount); }, 550);
            } else {
                updateCartBadge(data.cartCount);
            }
        })
        .catch(function() {
            /* Fail silently in the UI; the form still works as a normal submit if JS/network fails */
        })
        .finally(function() {
            if (submitter) submitter.disabled = false;
        });
}

function setupCartAjax() {
    document.body.addEventListener('submit', function(event) {
        var form = event.target.closest('form[data-cart-form]');
        if (!form) return;
        event.preventDefault();
        var submitter = event.submitter || form.querySelector('button[type="submit"]');
        handleCartFormSubmit(form, submitter);
    });
}

/* ---------- Buy Now confirmation + receipt modal (checkout page) ---------- */

function closeBuyConfirm() {
    var backdrop = document.getElementById('buy-confirm-backdrop');
    var modal = document.getElementById('buy-confirm-modal');
    if (backdrop) backdrop.classList.remove('open');
    if (modal) modal.classList.remove('open');
}

function setupBuyConfirmation() {
    var form = document.getElementById('buy-form');
    var backdrop = document.getElementById('buy-confirm-backdrop');
    var modal = document.getElementById('buy-confirm-modal');
    var totalEl = document.getElementById('buy-confirm-total');
    var confirmBtn = document.getElementById('buy-confirm-submit');
    if (!form || !backdrop || !modal || !confirmBtn) return;

    var confirmed = false;

    form.addEventListener('submit', function(event) {
        if (confirmed) return;
        event.preventDefault();
        if (totalEl) totalEl.textContent = form.dataset.total || 'this amount';
        backdrop.classList.add('open');
        modal.classList.add('open');
    });

    confirmBtn.addEventListener('click', function() {
        confirmed = true;
        closeBuyConfirm();
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });

    backdrop.addEventListener('click', closeBuyConfirm);
}

function closeReceiptModal() {
    var backdrop = document.getElementById('receipt-modal-backdrop');
    var modal = document.getElementById('receipt-modal');
    if (backdrop) backdrop.classList.remove('open');
    if (modal) modal.classList.remove('open');
}

function setupReceiptModal() {
    var backdrop = document.getElementById('receipt-modal-backdrop');
    var modal = document.getElementById('receipt-modal');
    if (!backdrop || !modal || !modal.dataset.autoOpen) return;
    backdrop.classList.add('open');
    modal.classList.add('open');
    backdrop.addEventListener('click', closeReceiptModal);
}

function copyReceiptNumber() {
    var el = document.getElementById('receipt-number-value');
    var btn = document.querySelector('.receipt-copy-btn');
    if (!el) return;
    var text = el.textContent.trim();

    function showCopied() {
        if (!btn) return;
        var original = btn.dataset.originalLabel || btn.textContent;
        btn.dataset.originalLabel = original;
        btn.textContent = 'Copied!';
        clearTimeout(btn._resetTimer);
        btn._resetTimer = setTimeout(function() {
            btn.textContent = original;
        }, 1500);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(showCopied).catch(function() {});
    } else {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            showCopied();
        } catch (err) {}
        document.body.removeChild(textarea);
    }
}

window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        window.location.reload();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    if (new URLSearchParams(window.location.search).has('cart')) {
        toggleCart();
    }

    setupInfiniteProducts();
    setupInfiniteTestimonials();
    setupActiveProductCard();
    setupCartAjax();
    setupChatForm();
    setupPasswordToggles();
    setupPasswordConfirmCheck();
    setupBuyConfirmation();
    setupReceiptModal();
    setupNotifications();
    setupScrollSpy();

    if (document.getElementById('chat-messages')) {
        startChatPolling();
    }

    navLinks.forEach(function(link) {
        link.classList.remove('active');
        link.addEventListener('click', function() {
            setActiveNav(link.dataset.target || 'top');
        });
    });

    const revealItems = document.querySelectorAll('.reveal');
    const revealObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    revealItems.forEach(function(item) {
        revealObserver.observe(item);
    });
});