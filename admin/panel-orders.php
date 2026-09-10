<?php
/**
 * Orders tab: full order list with delivery-status update form per order. Included by admin.php. Expects $orders, $deliveryStatuses, and $paymentMethodLabels to be set by the caller.
 */
?>
      <section class="dash-section" data-panel="orders"<?php echo $activePanel === 'orders' ? '' : ' hidden'; ?>>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Orders</div></div>
          <?php if (!$orders): ?>
            <p class="dash-empty">No orders yet.</p>
          <?php else: ?>
            <div class="dash-table">
              <?php foreach ($orders as $orderId => $order): $status = $order['delivery_status'] ?: 'pending'; $orderedAt = $order['created_at'] ? date('M j, Y g:i A', strtotime($order['created_at'])) : '—'; $estimatedDeliveryValue = $order['estimated_delivery'] ? date('Y-m-d\TH:i', strtotime($order['estimated_delivery'])) : ''; $estimatedDeliveryDisplay = $order['estimated_delivery'] ? date('M j, Y g:i A', strtotime($order['estimated_delivery'])) : 'Not set'; ?>
                <div class="dash-order-row">
                  <div>
                    <strong>Order #<?php echo (int) $orderId; ?></strong>
                    <span><?php echo htmlspecialchars($order['buyer_name']); ?> · <?php echo htmlspecialchars($order['buyer_email']); ?></span>
                    <span><?php echo htmlspecialchars(implode(', ', $order['items'])); ?></span>
                    <?php if (!empty($order['receipt_number'])): ?><span class="dash-receipt-number">Receipt: <?php echo htmlspecialchars($order['receipt_number']); ?></span><?php endif; ?>
                    <span class="dash-order-time">Ordered: <?php echo htmlspecialchars($orderedAt); ?></span>
                  </div>
                  <div>
                    <strong>₱<?php echo number_format($order['total']); ?></strong>
                    <span><?php echo htmlspecialchars($order['address'] . ', ' . $order['location'] . ' ' . $order['zip']); ?></span>
                    <?php if (!empty($order['phone'])): ?><span>Phone: <?php echo htmlspecialchars($order['phone']); ?></span><?php endif; ?>
                    <span>Payment: <?php
                      $adminPaymentLabel = $paymentMethodLabels[$order['payment_method'] ?? 'cod'] ?? 'Cash on Delivery';
                      if (($order['payment_method'] ?? 'cod') === 'ewallet' && !empty($order['ewallet_provider'])) {
                        $adminPaymentLabel .= ' (' . ($ewalletProviderLabels[$order['ewallet_provider']] ?? ucfirst($order['ewallet_provider'])) . ')';
                      }
                      echo htmlspecialchars($adminPaymentLabel);
                    ?></span>
                    <?php if ($order['comments'] !== ''): ?><span>Note: <?php echo htmlspecialchars($order['comments']); ?></span><?php endif; ?>
                  </div>
                  <div class="dash-order-status-panel">
                    <span class="dash-status-chip status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($deliveryStatuses[$status] ?? ucfirst($status)); ?></span>
                    <span class="dash-order-time">Expected: <?php echo htmlspecialchars($estimatedDeliveryDisplay); ?></span>
                    <form class="dash-inline-form" method="post">
                      <input type="hidden" name="action" value="update_delivery_status">
                      <input type="hidden" name="panel" value="orders">
                      <input type="hidden" name="order_id" value="<?php echo (int) $orderId; ?>">
                      <select name="delivery_status">
                        <?php foreach ($deliveryStatuses as $value => $label): ?>
                          <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $status === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <input type="datetime-local" name="estimated_delivery" value="<?php echo htmlspecialchars($estimatedDeliveryValue); ?>">
                      <button class="dash-btn-secondary" type="submit">Update</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>