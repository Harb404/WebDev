<?php
/**
 * Refunds tab: customer-submitted refund requests on delivered orders,
 * with Approve / Deny actions. Approving marks the order refunded,
 * restores its items' stock (logged in stock_history), and excludes it
 * from the revenue stats on the Dashboard tab. Included by admin.php.
 * Expects $refundRequests to be set by the caller (see admin-data.php).
 */
$refundRequests = $refundRequests ?? [];
?>
      <section class="dash-section" data-panel="refunds"<?php echo $activePanel === 'refunds' ? '' : ' hidden'; ?>>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Refund Requests</div></div>
          <?php if (!$refundRequests): ?>
            <p class="dash-empty">No refund requests yet.</p>
          <?php else: ?>
            <div class="dash-table">
              <?php foreach ($refundRequests as $orderId => $order): ?>
                <?php
                  $refundStatus = $order['refund_status'];
                  $refundStatusLabels = ['requested' => 'Pending Review', 'approved' => 'Refunded', 'denied' => 'Denied'];
                  $refundStatusClasses = ['requested' => 'status-pending', 'approved' => 'status-delivered', 'denied' => 'status-failed'];
                  $refundRequestedAt = $order['refund_requested_at'] ? date('M j, Y g:i A', strtotime($order['refund_requested_at'])) : '—';
                  $refundedAt = $order['refunded_at'] ? date('M j, Y g:i A', strtotime($order['refunded_at'])) : null;
                ?>
                <div class="dash-order-row">
                  <div>
                    <strong>Order #<?php echo (int) $orderId; ?></strong>
                    <span><?php echo htmlspecialchars($order['buyer_name']); ?> · <?php echo htmlspecialchars($order['buyer_email']); ?></span>
                    <span><?php echo htmlspecialchars(implode(', ', $order['items'])); ?></span>
                    <span class="dash-order-time">Requested: <?php echo htmlspecialchars($refundRequestedAt); ?></span>
                  </div>
                  <div>
                    <strong>₱<?php echo number_format($order['total']); ?></strong>
                    <span>Reason: <?php echo $order['refund_reason'] !== '' ? htmlspecialchars($order['refund_reason']) : '—'; ?></span>
                    <?php if ($refundedAt): ?><span class="dash-order-time">Refunded: <?php echo htmlspecialchars($refundedAt); ?></span><?php endif; ?>
                  </div>
                  <div class="dash-order-status-panel">
                    <span class="dash-status-chip <?php echo htmlspecialchars($refundStatusClasses[$refundStatus] ?? ''); ?>"><?php echo htmlspecialchars($refundStatusLabels[$refundStatus] ?? ucfirst($refundStatus)); ?></span>
                    <?php if ($refundStatus === 'requested'): ?>
                      <div class="dash-customer-actions">
                        <form method="post" onsubmit="return confirm('Approve this refund? ₱<?php echo number_format($order['total']); ?> will be marked refunded and the order\'s stock restored.');">
                          <input type="hidden" name="action" value="approve_refund">
                          <input type="hidden" name="panel" value="refunds">
                          <input type="hidden" name="order_id" value="<?php echo (int) $orderId; ?>">
                          <button class="dash-btn-secondary" type="submit">Approve</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Deny this refund request?');">
                          <input type="hidden" name="action" value="deny_refund">
                          <input type="hidden" name="panel" value="refunds">
                          <input type="hidden" name="order_id" value="<?php echo (int) $orderId; ?>">
                          <button class="dash-btn-danger" type="submit">Deny</button>
                        </form>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>