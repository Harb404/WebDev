<?php
/**
 * Admin dashboard left sidebar nav. Included by admin.php. Expects $activePanel to be set by the caller.
 */
?>
  <aside class="dash-sidebar">
    <div class="dash-brand"><span class="dash-brand-mark"><img src="Pictures/logoh.png" alt="Masalihit Luxe logo"></span> Masalihit Luxe</div>
    <nav class="dash-nav">
      <button type="button" class="dash-nav-item<?php echo $activePanel === 'overview' ? ' active' : ''; ?>" data-section="overview">
        <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/></svg>
        Dashboard
      </button>
      <button type="button" class="dash-nav-item<?php echo $activePanel === 'products' ? ' active' : ''; ?>" data-section="products">
        <svg viewBox="0 0 24 24" fill="none"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M4 7.5L12 12l8-4.5M12 12v9" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
        Products
      </button>
      <button type="button" class="dash-nav-item<?php echo $activePanel === 'orders' ? ' active' : ''; ?>" data-section="orders">
        <svg viewBox="0 0 24 24" fill="none"><path d="M6 3h12l1 5H5l1-5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M5 8h14l-1.2 11.2a1 1 0 01-1 .8H7.2a1 1 0 01-1-.8L5 8z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
        Orders
      </button>
      <button type="button" class="dash-nav-item<?php echo $activePanel === 'refunds' ? ' active' : ''; ?>" data-section="refunds">
        <svg viewBox="0 0 24 24" fill="none"><path d="M4 12a8 8 0 1 1 2.3 5.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M4 12V7M4 12h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Refunds
        <?php if ($statRefundRequestCount > 0): ?><span class="dash-nav-badge"><?php echo (int) $statRefundRequestCount; ?></span><?php endif; ?>
      </button>
      <button type="button" class="dash-nav-item<?php echo $activePanel === 'history' ? ' active' : ''; ?>" data-section="history">
        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/><path d="M12 7.5V12l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        History
      </button>
      <button type="button" class="dash-nav-item<?php echo $activePanel === 'customers' ? ' active' : ''; ?>" data-section="customers">
        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.6"/><path d="M5 20c0-3.6 3.1-6.5 7-6.5s7 2.9 7 6.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        Customers
      </button>
      <button type="button" class="dash-nav-item<?php echo $activePanel === 'discounts' ? ' active' : ''; ?>" data-section="discounts">
        <svg viewBox="0 0 24 24" fill="none"><path d="M20.5 12.3 12.7 20a1.5 1.5 0 0 1-2.1 0l-7-7a1.5 1.5 0 0 1 0-2.1L11.4 3h6.6a2.5 2.5 0 0 1 2.5 2.5v6.8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="16" cy="8" r="1.4" fill="currentColor"/></svg>
        Discounts
      </button>
      <button type="button" class="dash-nav-item<?php echo $activePanel === 'messages' ? ' active' : ''; ?>" data-section="messages">
        <svg viewBox="0 0 24 24" fill="none"><path d="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H9l-4.5 3.5V17H4a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
        Messages
        <span class="dash-nav-badge" id="chat-nav-badge" hidden>0</span>
      </button>
    </nav>
    <div class="dash-sidebar-footer">
      <a href="index.php">View Store</a>
      <a href="logout.php">Logout</a>
    </div>
  </aside>