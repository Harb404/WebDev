<?php
/**
 * Products tab: add-product form plus the editable inventory table. Included by admin.php. Expects $products to be set by the caller.
 */
?>
      <section class="dash-section" data-panel="products"<?php echo $activePanel === 'products' ? '' : ' hidden'; ?>>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Add Product</div></div>
          <form class="dash-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_product">
            <input type="hidden" name="panel" value="products">
            <div class="dash-form-row">
              <label>Product Name<input type="text" name="name" required></label>
              <label>Price<input type="number" name="price" min="0" required></label>
              <label>Stock<input type="number" name="stock" min="0" required></label>
            </div>
            <label>Product Image<input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" required></label>
            <button class="dash-btn-primary" type="submit">Add Product</button>
          </form>
        </div>
        <div class="dash-panel">
          <div class="dash-panel-head"><div class="dash-panel-title">Inventory</div></div>
          <div class="dash-table">
            <?php foreach ($products as $productId => $product): ?>
              <div class="dash-table-row dash-inventory-row">
                <div class="dash-table-main">
                  <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                  <span>Current: ₱<?php echo number_format($product['price']); ?> ·
                    <span class="dash-stock-value<?php echo (int) $product['stock'] <= 5 ? ' dash-stock-low' : ''; ?>"><?php echo (int) $product['stock']; ?> in stock</span>
                  </span>
                </div>

                <form class="dash-inline-form dash-inventory-edit-form" method="post">
                  <input type="hidden" name="action" value="update_product">
                  <input type="hidden" name="panel" value="products">
                  <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId); ?>">

                  <label class="dash-inline-label">
                    Name
                    <input type="text" name="name"
                           value="<?php echo htmlspecialchars($product['name']); ?>" required>
                  </label>

                  <label class="dash-inline-label">
                    Price
                    <input type="number" name="price" min="0" step="1"
                           value="<?php echo (int) $product['price']; ?>" required>
                  </label>

                  <label class="dash-inline-label">
                    Stock
                    <input type="number" name="stock" min="0" step="1"
                           value="<?php echo (int) $product['stock']; ?>" required>
                  </label>

                  <button class="dash-btn-secondary" type="submit">Save</button>
                </form>

                <form class="dash-inline-form" method="post">
                  <input type="hidden" name="action" value="delete_product">
                  <input type="hidden" name="panel" value="products">
                  <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productId); ?>">
                  <button class="dash-btn-danger" type="submit" onclick="return confirm('Delete this product?');">Delete</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>