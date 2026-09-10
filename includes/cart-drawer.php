<?php
/**
 * Shared cart drawer, included by index.php and Contact.php.
 * Expects $cart, $products, and $buyUrl to already be set by the caller.
 */
?>
<aside class="cart-drawer" id="cart-panel" aria-hidden="true">
  <div class="cart-drawer-header">
    <h2>Shopping Cart</h2>
    <button class="cart-close" type="button" onclick="toggleCart()" aria-label="Close cart">×</button>
  </div>
  <div id="cart-content">
  <?php if (!$cart): ?>
    <p class="empty-cart">Your cart is empty.</p>
  <?php else: ?>
    <div class="cart-list">
      <?php foreach ($cart as $productId => $quantity): if (!isset($products[$productId])) continue; $product = $products[$productId]; $lineTotal = $product['price'] * $quantity; ?>
        <div class="cart-row">
          <span><?php echo htmlspecialchars($product['name']); ?></span>
          <strong>₱<?php echo number_format($lineTotal); ?></strong>
          <form class="quantity-controls" method="post" data-cart-form><input type="hidden" name="action" value="change_cart_quantity"><input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId); ?>"><button type="submit" name="change" value="-1" aria-label="Decrease quantity">−</button><span><?php echo (int) $quantity; ?></span><button type="submit" name="change" value="1" aria-label="Increase quantity" <?php echo $quantity >= $product['stock'] ? 'disabled' : ''; ?>>+</button></form>
          <form method="post" data-cart-form><input type="hidden" name="action" value="remove_from_cart"><input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId); ?>"><button class="remove-button" type="submit">Remove</button></form>
        </div>
      <?php endforeach; ?>
      <div class="cart-total">Total: ₱<?php echo number_format($cartTotal); ?></div>
      <a class="buy-now-btn" href="<?php echo htmlspecialchars($buyUrl); ?>"><span class="buy-now-icon" aria-hidden="true">⚡</span>Buy Now</a>
    </div>
  <?php endif; ?>
  </div>
</aside>
