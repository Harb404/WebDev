<?php
/**
 * History tab: recent stock-change log. Included by admin.php. Expects $stockHistory and $stockHistoryReasonLabels to be set by the caller.
 */
?>
      <section class="dash-section" data-panel="history"<?php echo $activePanel === 'history' ? '' : ' hidden'; ?>>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Recent Stock Changes</div></div>
          <?php if (!$stockHistory): ?>
            <p class="dash-empty">No stock changes recorded yet.</p>
          <?php else: ?>
            <div class="dash-table">
              <?php foreach ($stockHistory as $entry): $isIncrease = $entry['change_amount'] >= 0; ?>
                <div class="dash-table-row dash-history-row">
                  <div>
                    <strong><?php echo htmlspecialchars($entry['product_name']); ?></strong>
                    <span class="dash-order-time"><?php echo htmlspecialchars($stockHistoryReasonLabels[$entry['reason']] ?? ucfirst($entry['reason'])); ?> · <?php echo htmlspecialchars(date('M j, g:i A', strtotime($entry['created_at']))); ?></span>
                  </div>
                  <span class="<?php echo $isIncrease ? 'dash-history-up' : 'dash-history-down'; ?>">
                    <?php echo (int) $entry['previous_stock']; ?> → <?php echo (int) $entry['new_stock']; ?>
                    (<?php echo $isIncrease ? '+' : ''; ?><?php echo (int) $entry['change_amount']; ?>)
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
