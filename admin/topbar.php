<?php
/**
 * Admin dashboard top bar: chat/low-stock notification bells. Included by admin.php. Expects $statLowStockCount and $products to be set by the caller.
 */
?>
    <header class="dash-topbar">
      <div class="dash-topbar-title">Admin Panel</div>
      <div class="dash-topbar-actions">
        <div class="dash-notif-wrap">
          <button type="button" class="dash-notif-chat" id="chat-topbar-bell" title="Client messages">
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H9l-4.5 3.5V17H4a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
            <span class="dash-notif-badge" id="chat-topbar-badge" hidden>0</span>
          </button>
          <div class="dash-notif-dropdown" id="chat-notif-dropdown" hidden>
            <div class="dash-notif-dropdown-head">Client Messages</div>
            <div class="dash-notif-dropdown-list" id="chat-notif-list">
              <p class="dash-empty">No unread messages.</p>
            </div>
          </div>
        </div>
        <?php if ($statLowStockCount > 0): ?>
          <div class="dash-notif-wrap">
            <button type="button" class="dash-notif-chat" id="lowstock-topbar-bell" title="<?php echo (int) $statLowStockCount; ?> product(s) low on stock">
              <svg viewBox="0 0 24 24" fill="none"><path d="M12 4a5 5 0 00-5 5v3.2c0 .5-.2 1-.5 1.4L5 15.5h14l-1.5-2A2 2 0 0117 12.2V9a5 5 0 00-5-5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 18a2.5 2.5 0 005 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
              <span class="dash-notif-badge"><?php echo (int) $statLowStockCount; ?></span>
            </button>
            <div class="dash-notif-dropdown" id="lowstock-notif-dropdown" hidden>
              <div class="dash-notif-dropdown-head">Low Stock</div>
              <div class="dash-notif-dropdown-list">
                <?php foreach ($products as $lowStockProductId => $lowStockProduct): if ((int) $lowStockProduct['stock'] > 5) continue; ?>
                  <button type="button" class="dash-notif-item" data-lowstock-item>
                    <span><?php echo htmlspecialchars($lowStockProduct['name']); ?></span>
                    <span class="dash-notif-item-count <?php echo $lowStockProduct['stock'] < 1 ? 'is-zero' : ''; ?>"><?php echo $lowStockProduct['stock'] < 1 ? 'No stock' : (int) $lowStockProduct['stock'] . ' left'; ?></span>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
        <div class="dash-user"><span class="dash-avatar">A</span> Admin</div>
      </div>
    </header>
