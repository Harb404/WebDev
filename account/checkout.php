<?php
/**
 * Checkout card: delivery-details form + payment method + order
 * summary with discount code + Place Order button. Shown only when
 * $isCheckout. Expects $profile, $address, $location, $zip, $phone,
 * $comments, $paymentMethod, $ewalletProvider, $cart, $products, $cartTotal,
 * $discountAmount, $discountPercent, $appliedDiscountCode, $shippingFee, and
 * $checkoutTotal to be set by the caller.
 */
?>
    <?php if ($isCheckout): ?><section class="checkout-card">
      <div class="checkout-delivery-panel">
        <div class="eyebrow">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
        <h2>Delivery Details</h2>
        <div class="delivery-details">
          <label>Recipient Name<input type="text" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? $_SESSION['user_name']); ?>" form="buy-form" required autocomplete="name"></label>
          <label>Address<input type="text" name="address" value="<?php echo htmlspecialchars($address); ?>" form="buy-form" required autocomplete="street-address"></label>
          <label>Location<input type="text" name="location" value="<?php echo htmlspecialchars($location); ?>" form="buy-form" required autocomplete="address-level2"></label>
          <label>ZIP Code<input type="text" name="zip" value="<?php echo htmlspecialchars($zip); ?>" form="buy-form" required autocomplete="postal-code"></label>
          <label>Phone Number<input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" form="buy-form" pattern="09[0-9]{9}" maxlength="11" inputmode="numeric" placeholder="09XXXXXXXXX" title="11 digits starting with 09, e.g. 09171234567" required autocomplete="tel"></label>
          <label>Delivery Comments<textarea name="comments" form="buy-form" rows="3" placeholder="Optional instructions"><?php echo htmlspecialchars($comments); ?></textarea></label>
        </div>
        <div class="payment-method-group">
          <span class="payment-method-label">Payment Method</span>
          <div class="payment-options">
            <label class="payment-option">
              <input type="radio" name="payment_method" value="cod" form="buy-form" <?php echo $paymentMethod !== 'ewallet' ? 'checked' : ''; ?>>
              <span class="payment-option-title">Cash on Delivery</span>
              <span class="payment-option-sub">Pay in cash when your order arrives</span>
            </label>
            <div class="payment-option-wrap">
              <label class="payment-option">
                <input type="radio" name="payment_method" value="ewallet" form="buy-form" <?php echo $paymentMethod === 'ewallet' ? 'checked' : ''; ?>>
                <span class="payment-option-title">E-Wallet</span>
                <span class="payment-option-sub">Choose a provider below</span>
              </label>
              <div class="wallet-provider-options">
                <label class="wallet-provider-option">
                  <input type="radio" name="ewallet_provider" value="gcash" form="buy-form" <?php echo $ewalletProvider !== 'paypal' && $ewalletProvider !== 'gotyme' && $ewalletProvider !== 'debit' ? 'checked' : ''; ?>>
                  <span>GCash</span>
                </label>
                <label class="wallet-provider-option">
                  <input type="radio" name="ewallet_provider" value="paypal" form="buy-form" <?php echo $ewalletProvider === 'paypal' ? 'checked' : ''; ?>>
                  <span>PayPal</span>
                </label>
                <label class="wallet-provider-option">
                  <input type="radio" name="ewallet_provider" value="gotyme" form="buy-form" <?php echo $ewalletProvider === 'gotyme' ? 'checked' : ''; ?>>
                  <span>GoTyme</span>
                </label>
                <label class="wallet-provider-option">
                  <input type="radio" name="ewallet_provider" value="debit" form="buy-form" <?php echo $ewalletProvider === 'debit' ? 'checked' : ''; ?>>
                  <span>Debit Card</span>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="checkout-summary-panel">
        <div class="summary-heading">Order Summary</div>
        <?php if (!$cart): ?>
          <p class="empty-cart">Your cart is empty. Add something from the collection to get started.</p>
          <a class="buy-now-btn checkout-store-link" href="index.php#features"><span class="buy-now-icon" aria-hidden="true">⚡</span>Browse collection</a>
        <?php else: ?>
          <div class="checkout-items">
            <?php foreach ($cart as $productId => $quantity): if (!isset($products[$productId])) continue; ?><div><span><?php echo htmlspecialchars($products[$productId]['name']); ?> × <?php echo (int) $quantity; ?></span><strong>₱<?php echo number_format($products[$productId]['price'] * $quantity); ?></strong></div><?php endforeach; ?>
          </div>

          <div class="discount-code-block">
            <?php if ($appliedDiscountCode): ?>
              <div class="discount-applied-chip">
                <span class="discount-applied-icon" aria-hidden="true">✓</span>
                <span class="discount-applied-text">Code <strong><?php echo htmlspecialchars($appliedDiscountCode['code']); ?></strong> applied — <?php echo (int) $appliedDiscountCode['percent']; ?>% off</span>
                <form method="post" class="discount-remove-form">
                  <input type="hidden" name="action" value="remove_discount">
                  <button type="submit" class="discount-remove-btn">Remove</button>
                </form>
              </div>
            <?php else: ?>
              <label class="discount-code-label" for="discount-code-input">Have a discount code?</label>
              <form method="post" class="discount-apply-form">
                <input type="hidden" name="action" value="apply_discount">
                <div class="discount-input-row">
                  <input type="text" id="discount-code-input" name="discount_code" placeholder="Enter code" maxlength="50" autocomplete="off">
                  <button type="submit" class="discount-apply-btn">Apply</button>
                </div>
              </form>
            <?php endif; ?>
            <?php if ($discountMessage): ?><p class="discount-feedback discount-feedback-success"><?php echo htmlspecialchars($discountMessage); ?></p><?php endif; ?>
            <?php if ($discountError): ?><p class="discount-feedback discount-feedback-error"><?php echo htmlspecialchars($discountError); ?></p><?php endif; ?>
          </div>

          <div class="checkout-totals">
            <div class="checkout-subtotal-line"><span>Subtotal</span><span>₱<?php echo number_format($cartTotal); ?></span></div>
            <?php if ($discountAmount > 0): ?>
              <div class="checkout-discount-line"><span>Discount (<?php echo (int) $discountPercent; ?>%)</span><span>−₱<?php echo number_format($discountAmount); ?></span></div>
            <?php endif; ?>
            <div class="checkout-shipping-line"><span>Shipping</span><span>₱<?php echo number_format($shippingFee); ?></span></div>
            <div class="checkout-total"><span>Total</span><span>₱<?php echo number_format($checkoutTotal); ?></span></div>
          </div>

          <form method="post" id="buy-form" data-total="₱<?php echo number_format($checkoutTotal); ?>">
            <input type="hidden" name="action" value="buy">
            <button class="buy-now-btn checkout-submit" type="submit" id="buy-form-submit"><span class="buy-now-icon" aria-hidden="true">⚡</span>Place Order</button>
          </form>
          <p class="checkout-secure-note">🔒 Your order details stay private and are only used for delivery.</p>
        <?php endif; ?>
      </div>
    </section><?php endif; ?>