<?php
/**
 * Discounts tab: create one-time discount codes and view their status.
 * Included by admin.php. Expects $discountCodes to be set by the caller.
 */
?>
      <section class="dash-section" data-panel="discounts"<?php echo $activePanel === 'discounts' ? '' : ' hidden'; ?>>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Create Discount Code</div></div>
          <form class="dash-form" method="post">
            <input type="hidden" name="action" value="create_discount_code">
            <input type="hidden" name="panel" value="discounts">
            <div class="dash-form-row">
              <label>Code<input type="text" name="code" maxlength="50" placeholder="e.g. SAVE2" style="text-transform:uppercase;" required></label>
              <label>Discount %<input type="number" name="percent" min="1" max="100" placeholder="e.g. 2" required></label>
              <label>Max Uses<input type="number" name="max_uses" min="1" max="1000" value="1" placeholder="e.g. 2" required></label>
            </div>
            <button class="dash-btn-primary" type="submit">Create Code</button>
          </form>
        </div>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Discount Codes</div></div>
          <div class="dash-table">
            <?php if (!$discountCodes): ?>
              <p class="dash-empty">No discount codes yet.</p>
            <?php endif; ?>
            <?php foreach ($discountCodes as $code): ?>
              <?php $codeMaxUses = (int) $code['max_uses']; $codeUsedCount = (int) $code['used_count']; $codeIsExhausted = $codeUsedCount >= $codeMaxUses; ?>
              <div class="dash-table-row dash-table-row-simple">
                <div>
                  <strong><?php echo htmlspecialchars($code['code']); ?></strong>
                  <span class="dash-order-time">
                    <?php echo (int) $code['percent']; ?>% off ·
                    <span class="dash-mini-badge status-<?php echo $codeIsExhausted ? 'failed' : 'delivered'; ?>"><?php echo $codeIsExhausted ? 'Fully Used' : 'Available'; ?></span>
                    · Used <?php echo $codeUsedCount; ?> of <?php echo $codeMaxUses; ?>
                    <?php if ($code['uses']): ?>
                      · Used on
                      <?php
                        $useDescriptions = array_map(function ($use) {
                          return 'Order #' . (int) $use['order_id'] . ($use['used_by_name'] ? ' by ' . htmlspecialchars($use['used_by_name']) : '');
                        }, $code['uses']);
                        echo implode(', ', $useDescriptions);
                      ?>
                    <?php endif; ?>
                  </span>
                </div>
                <?php if (!$codeIsExhausted): ?>
                  <form method="post" onsubmit="return confirm('Delete discount code <?php echo addslashes(htmlspecialchars($code['code'])); ?>?');">
                    <input type="hidden" name="action" value="delete_discount_code">
                    <input type="hidden" name="panel" value="discounts">
                    <input type="hidden" name="code_id" value="<?php echo (int) $code['id']; ?>">
                    <button class="dash-btn-danger" type="submit">Delete</button>
                  </form>
                <?php else: ?>
                  <div class="dash-customer-actions">
                    <form method="post" onsubmit="return confirm('Make <?php echo addslashes(htmlspecialchars($code['code'])); ?> available again for <?php echo $codeMaxUses; ?> more use<?php echo $codeMaxUses === 1 ? '' : 's'; ?>?');">
                      <input type="hidden" name="action" value="reactivate_discount_code">
                      <input type="hidden" name="panel" value="discounts">
                      <input type="hidden" name="code_id" value="<?php echo (int) $code['id']; ?>">
                      <button class="dash-btn-secondary" type="submit">Reactivate</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Permanently delete <?php echo addslashes(htmlspecialchars($code['code'])); ?>? Its usage history will be gone for good — the orders that redeemed it keep their own totals.');">
                      <input type="hidden" name="action" value="delete_discount_code">
                      <input type="hidden" name="panel" value="discounts">
                      <input type="hidden" name="code_id" value="<?php echo (int) $code['id']; ?>">
                      <button class="dash-btn-danger" type="submit">Delete</button>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>