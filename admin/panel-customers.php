<?php
/**
 * Customers tab: customer list with promote/demote/ban/unban/delete actions. Included by admin.php. Expects $users to be set by the caller.
 */
?>
      <section class="dash-section" data-panel="customers"<?php echo $activePanel === 'customers' ? '' : ' hidden'; ?>>
        <div class="dash-panel">
          <div class="dash-panel-head dash-panel-head-with-search">
            <div class="dash-panel-title">Customers</div>
            <input type="text" id="customer-search-input" class="dash-search-input" placeholder="Search by name or email…" autocomplete="off">
          </div>
          <div class="dash-table" id="customer-table">
            <?php $visibleUsers = array_filter($users, fn($u) => $u['email'] !== 'admin@masalihit.local'); ?>
            <?php if (!$visibleUsers): ?><p class="dash-empty">No customers yet.</p><?php endif; ?>
            <p class="dash-empty" id="customer-search-empty" hidden>No customers match your search.</p>
            <?php foreach ($visibleUsers as $user): ?>
              <?php $userStatus = $user['status'] ?? 'active'; ?>
              <div class="dash-table-row dash-table-row-simple dash-customer-row" data-customer-search="<?php echo htmlspecialchars(strtolower($user['name'] . ' ' . $user['email'])); ?>">
                <div>
                  <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                  <span class="dash-order-time">
                    <?php echo htmlspecialchars($user['email']); ?> ·
                    <span class="dash-mini-badge status-<?php echo $user['role'] === 'admin' ? 'delivered' : 'processing'; ?>"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span>
                    <span class="dash-mini-badge status-<?php echo $userStatus === 'banned' ? 'failed' : 'delivered'; ?>"><?php echo $userStatus === 'banned' ? 'Banned' : 'Active'; ?></span>
                  </span>
                  <span><?php echo htmlspecialchars(trim($user['address'] . ', ' . $user['location'] . ' ' . $user['zip'], ', ')); ?></span>
                </div>
                <div class="dash-customer-actions">
                  <form method="post" onsubmit="return confirm(<?php echo $userStatus === 'banned' ? "'Unban " . addslashes(htmlspecialchars($user['name'])) . "?'" : "'Ban " . addslashes(htmlspecialchars($user['name'])) . "? They won\\'t be able to log in.'"; ?>);">
                    <input type="hidden" name="action" value="<?php echo $userStatus === 'banned' ? 'unban_user' : 'ban_user'; ?>">
                    <input type="hidden" name="panel" value="customers">
                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                    <button class="dash-btn-secondary" type="submit"><?php echo $userStatus === 'banned' ? 'Unban' : 'Ban'; ?></button>
                  </form>
                  <form method="post" onsubmit="return confirm('Permanently delete <?php echo addslashes(htmlspecialchars($user['name'])); ?>? This can\'t be undone.');">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="panel" value="customers">
                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                    <button class="dash-btn-danger" type="submit">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
      <script>
        (function() {
          var searchInput = document.getElementById('customer-search-input');
          if (!searchInput) return;
          var rows = Array.prototype.slice.call(document.querySelectorAll('#customer-table .dash-customer-row'));
          var emptyMessage = document.getElementById('customer-search-empty');
          searchInput.addEventListener('input', function() {
            var query = searchInput.value.trim().toLowerCase();
            var visibleCount = 0;
            rows.forEach(function(row) {
              var matches = row.dataset.customerSearch.indexOf(query) !== -1;
              row.style.display = matches ? '' : 'none';
              if (matches) visibleCount++;
            });
            if (emptyMessage) emptyMessage.hidden = visibleCount !== 0 || rows.length === 0;
          });
        })();
      </script>