<?php
/**
 * Order History card: past orders with a delivery tracker, item
 * list, and a review form per delivered item. Also renders the
 * "Log out" link that sits below it while logged in.
 * Expects $purchaseHistory, $products, and $existingReviews to be
 * set by the caller.
 */
?>
    <?php if (!$isCheckout): ?><section class="history-card">
      <div class="history-card-head">
        <div>
          <div class="eyebrow">Your Purchases</div>
          <h2 class="display order-history-title">Order <span class="accent">History.</span></h2>
        </div>
        <?php if ($purchaseHistory): ?><span class="order-count-badge"><?php echo count($purchaseHistory); ?> ORDER<?php echo count($purchaseHistory) === 1 ? '' : 'S'; ?></span><?php endif; ?>
      </div>
      <?php if (!$purchaseHistory): ?>
        <p class="empty-cart">No purchases yet.</p>
      <?php else: ?>
        <div class="order-card-list">
          <?php foreach ($purchaseHistory as $orderId => $order): ?>
            <?php
              $historyStatus = $order['delivery_status'] ?: 'pending';
              $historyStatusLabels = ['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'failed' => 'Failed'];
              $historyOrderedAt = $order['created_at'] ? date('M j, Y · g:i A', strtotime($order['created_at'])) : '—';
              $historyEstimatedDelivery = $order['estimated_delivery'] ? date('M j, Y g:i A', strtotime($order['estimated_delivery'])) : null;
              $paymentMethodLabels = ['cod' => 'Cash on Delivery', 'ewallet' => 'E-Wallet'];
              $ewalletProviderLabels = ['gcash' => 'GCash', 'paypal' => 'PayPal', 'gotyme' => 'GoTyme', 'debit' => 'Debit Card'];
              $historyPaymentMethod = $paymentMethodLabels[$order['payment_method'] ?? 'cod'] ?? 'Cash on Delivery';
              if (($order['payment_method'] ?? 'cod') === 'ewallet' && !empty($order['ewallet_provider'])) {
                $historyPaymentMethod .= ' (' . ($ewalletProviderLabels[$order['ewallet_provider']] ?? ucfirst($order['ewallet_provider'])) . ')';
              }
              $trackerStages = [
                ['key' => 'pending', 'label' => 'Pending'],
                ['key' => 'processing', 'label' => 'Processing'],
                ['key' => 'shipped', 'label' => 'Shipped'],
                ['key' => 'delivered', 'label' => 'Delivered'],
              ];
              $stageOrder = ['pending', 'processing', 'shipped', 'delivered'];
              $isFailedOrder = $historyStatus === 'failed';
              $currentStageIndex = array_search($historyStatus, $stageOrder, true);
              if ($currentStageIndex === false) {
                $currentStageIndex = $isFailedOrder ? count($trackerStages) - 1 : 0;
              }
            ?>
            <div class="order-card" id="order-<?php echo (int) $orderId; ?>">
              <div class="order-card-head">
                <div>
                  <div class="order-card-number">ORDER #<?php echo (int) $orderId; ?></div>
                  <div class="order-card-date"><?php echo htmlspecialchars($historyOrderedAt); ?></div>
                </div>
                <span class="status-badge status-<?php echo htmlspecialchars($historyStatus); ?>"><?php echo htmlspecialchars($historyStatusLabels[$historyStatus] ?? ucfirst($historyStatus)); ?></span>
              </div>

              <div class="order-tracker <?php echo $isFailedOrder ? 'is-failed' : ''; ?>">
                <?php foreach ($trackerStages as $stageIndex => $stage): ?>
                  <?php
                    $stepClass = '';
                    $stepLabel = $stage['label'];
                    $isLastStage = $stageIndex === count($trackerStages) - 1;
                    if ($isFailedOrder && $isLastStage) {
                      $stepClass = 'is-failed';
                      $stepLabel = 'Failed';
                    } elseif ($isFailedOrder) {
                      $stepClass = 'is-complete';
                    } elseif ($stageIndex < $currentStageIndex) {
                      $stepClass = 'is-complete';
                    } elseif ($stageIndex === $currentStageIndex) {
                      $stepClass = 'is-active';
                    }
                  ?>
                  <div class="tracker-step <?php echo $stepClass; ?>">
                    <span class="tracker-dot"></span>
                    <span class="tracker-label"><?php echo htmlspecialchars($stepLabel); ?></span>
                  </div>
                  <?php if (!$isLastStage): ?>
                    <?php
                      $lineClass = '';
                      if ($isFailedOrder) {
                        $lineClass = ($stageIndex === count($trackerStages) - 2) ? 'is-failed' : 'is-complete';
                      } elseif ($stageIndex < $currentStageIndex) {
                        $lineClass = 'is-complete';
                      }
                    ?>
                    <div class="tracker-line <?php echo $lineClass; ?>"></div>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>

              <div class="order-info-grid">
                <div><span class="order-info-label">Customer</span><span class="order-info-value"><?php echo htmlspecialchars($profile['name'] ?? $_SESSION['user_name']); ?></span></div>
                <div><span class="order-info-label">Email</span><span class="order-info-value"><?php echo htmlspecialchars($profile['email'] ?? ''); ?></span></div>
                <div><span class="order-info-label">Phone</span><span class="order-info-value"><?php echo $order['phone'] !== '' ? htmlspecialchars($order['phone']) : '—'; ?></span></div>
                <div><span class="order-info-label">Delivery Address</span><span class="order-info-value"><?php echo htmlspecialchars($order['address'] . ', ' . $order['location'] . ' ' . $order['zip']); ?></span></div>
                <div><span class="order-info-label">Payment</span><span class="order-info-value"><?php echo htmlspecialchars($historyPaymentMethod); ?></span></div>
                <div><span class="order-info-label">Order Status</span><span class="order-info-value"><?php echo htmlspecialchars($historyStatusLabels[$historyStatus] ?? ucfirst($historyStatus)); ?></span></div>
                <?php if (!empty($order['receipt_number'])): ?>
                  <div><span class="order-info-label">Receipt Number</span><span class="order-info-value order-receipt-value"><?php echo htmlspecialchars($order['receipt_number']); ?></span></div>
                <?php endif; ?>
                <?php if ($historyEstimatedDelivery): ?>
                  <div><span class="order-info-label"><?php echo $historyStatus === 'delivered' ? 'Delivered' : 'Expected Delivery'; ?></span><span class="order-info-value"><?php echo htmlspecialchars($historyEstimatedDelivery); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($order['comments'])): ?>
                  <div><span class="order-info-label">Note</span><span class="order-info-value"><?php echo htmlspecialchars($order['comments']); ?></span></div>
                <?php endif; ?>
              </div>

              <div class="order-items-section">
                <div class="order-items-heading">Items in this Order</div>
                <div class="order-items-list">
                  <?php foreach ($order['items'] as $item): ?>
                    <?php
                      $reviewExisting = $existingReviews[$item['product_id']] ?? null;
                      $starGroup = 'rating-' . $orderId . '-' . preg_replace('/[^A-Za-z0-9]/', '', $item['product_id']);
                      $itemImage = $products[$item['product_id']]['image'] ?? null;
                      $itemLineTotal = $item['price'] * $item['quantity'];
                    ?>
                    <div class="order-item">
                      <div class="order-item-row">
                        <div class="order-item-thumb">
                          <?php if ($itemImage): ?>
                            <img src="<?php echo htmlspecialchars($itemImage); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                          <?php else: ?>
                            <span class="order-item-thumb-fallback" aria-hidden="true">?</span>
                          <?php endif; ?>
                        </div>
                        <div class="order-item-info">
                          <span class="order-item-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                          <span class="order-item-meta">Qty: <?php echo (int) $item['quantity']; ?> · ₱<?php echo number_format($item['price']); ?> each</span>
                        </div>
                        <strong class="order-item-price">₱<?php echo number_format($itemLineTotal); ?></strong>
                      </div>
                      <?php if ($historyStatus === 'delivered'): ?>
                        <div class="review-widget">
                          <form method="post" class="review-form">
                            <input type="hidden" name="action" value="submit_review">
                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($item['product_id']); ?>">
                            <input type="hidden" name="order_id" value="<?php echo (int) $orderId; ?>">
                            <div class="star-rating">
                              <?php for ($starValue = 5; $starValue >= 1; $starValue--): ?>
                                <input type="radio" id="<?php echo $starGroup . '-' . $starValue; ?>" name="rating" value="<?php echo $starValue; ?>" <?php echo ($reviewExisting && (int) $reviewExisting['rating'] === $starValue) ? 'checked' : ''; ?> required>
                                <label for="<?php echo $starGroup . '-' . $starValue; ?>" title="<?php echo $starValue; ?> star<?php echo $starValue > 1 ? 's' : ''; ?>">★</label>
                              <?php endfor; ?>
                            </div>
                            <input type="text" name="comment" maxlength="500" placeholder="Optional comment" value="<?php echo htmlspecialchars($reviewExisting['comment'] ?? ''); ?>">
                            <button class="dash-btn-secondary review-submit-btn" type="submit"><?php echo $reviewExisting ? 'Update Review' : 'Submit Review'; ?></button>
                          </form>
                        </div>
                      <?php elseif ($isFailedOrder): ?>
                        <p class="review-locked-note">This order wasn't delivered, so it can't be reviewed.</p>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <?php if ($historyStatus === 'delivered'): ?>
                <?php $refundStatus = $order['refund_status'] ?? 'none'; ?>
                <div class="refund-block">
                  <?php if ($refundStatus === 'none' || $refundStatus === 'denied'): ?>
                    <?php if ($refundStatus === 'denied'): ?>
                      <p class="review-locked-note">Your previous refund request for this order was denied. You can submit a new request below.</p>
                    <?php endif; ?>
                    <details class="refund-toggle">
                      <summary>Request a Refund</summary>
                      <form class="refund-form" method="post">
                        <input type="hidden" name="action" value="request_refund">
                        <input type="hidden" name="order_id" value="<?php echo (int) $orderId; ?>">
                        <label>Reason for refund<textarea name="refund_reason" rows="3" maxlength="500" placeholder="Tell us what went wrong…" required></textarea></label>
                        <button class="cart-button" type="submit">Submit Refund Request</button>
                      </form>
                    </details>
                  <?php elseif ($refundStatus === 'requested'): ?>
                    <span class="status-badge status-pending">Refund Requested</span>
                    <p class="review-locked-note">Submitted <?php echo htmlspecialchars($order['refund_requested_at'] ? date('M j, Y', strtotime($order['refund_requested_at'])) : ''); ?> — we'll review it shortly.</p>
                  <?php elseif ($refundStatus === 'approved'): ?>
                    <span class="status-badge status-delivered">Refunded</span>
                    <p class="review-locked-note">Refunded <?php echo htmlspecialchars($order['refunded_at'] ? date('M j, Y', strtotime($order['refunded_at'])) : ''); ?> — ₱<?php echo number_format($order['total']); ?> returned.</p>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php
                $historySubtotal = 0;
                foreach ($order['items'] as $item) {
                  $historySubtotal += $item['price'] * $item['quantity'];
                }
                $historyShippingFee = (int) ($order['shipping_fee'] ?? 0);
                // total = subtotal - discount + shipping, so back out the discount from those three known values.
                $historyDiscountAmount = !empty($order['discount_code']) ? max(0, $historySubtotal + $historyShippingFee - $order['total']) : 0;
              ?>
              <div class="checkout-totals">
                <div class="checkout-subtotal-line"><span>Subtotal</span><span>₱<?php echo number_format($historySubtotal); ?></span></div>
                <?php if ($historyDiscountAmount > 0): ?>
                  <div class="checkout-discount-line"><span>Discount (<?php echo (int) $order['discount_percent']; ?>% · <?php echo htmlspecialchars($order['discount_code']); ?>)</span><span>−₱<?php echo number_format($historyDiscountAmount); ?></span></div>
                <?php endif; ?>
                <div class="checkout-shipping-line"><span>Shipping</span><span>₱<?php echo number_format($historyShippingFee); ?></span></div>
              </div>
              <div class="order-card-footer">
                <span>Paid via <strong><?php echo htmlspecialchars($historyPaymentMethod); ?></strong></span>
                <span class="order-card-total">TOTAL: <strong>₱<?php echo number_format($order['total']); ?></strong></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section><?php endif; ?>
    <a class="back-link" href="logout.php">Log out</a>