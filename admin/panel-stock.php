<?php
/**
 * Add Stock tab: quick-add-stock form plus the current stock levels table. Included by admin.php. Expects $products to be set by the caller.
 */
?>
      <section class="dash-section" data-panel="stock"<?php echo $activePanel === 'stock' ? '' : ' hidden'; ?>>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Add Stock to a Product</div></div>
          <form class="dash-form" method="post">
            <input type="hidden" name="action" value="quick_add_stock">
            <input type="hidden" name="panel" value="stock">
            <div class="dash-form-row">
              <label>Product
                <select name="quick_product_id" required>
                  <option value="" disabled selected>Choose a product…</option>
                  <?php foreach ($products as $productId => $product): ?>
                    <option value="<?php echo htmlspecialchars($productId); ?>"><?php echo htmlspecialchars($product['name']); ?> (current: <?php echo (int) $product['stock']; ?>)</option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Quantity to Add<input type="number" name="add_amount" min="1" required></label>
            </div>
            <button class="dash-btn-primary" type="submit">Add Stock</button>
          </form>
        </div>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Current Stock Levels</div></div>
          <div class="dash-table">
            <?php foreach ($products as $product): ?>
              <div class="dash-table-row dash-table-row-simple">
                <span><?php echo htmlspecialchars($product['name']); ?></span>
                <span class="dash-stock-value<?php echo (int) $product['stock'] <= 5 ? ' dash-stock-low' : ''; ?>"><?php echo (int) $product['stock']; ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
