<?php
/**
 * Dashboard tab: revenue/stock stat cards, monthly sales chart, top products, order-status breakdown. Included by admin.php. Expects the $stat*, $monthlyRevenue, $monthNames, $maxMonthlyRevenue, and $topProducts variables to be set by the caller.
 */
?>
      <section class="dash-section" data-panel="overview"<?php echo $activePanel === 'overview' ? '' : ' hidden'; ?>>
        <div class="dash-cards">
          <div class="dash-card">
            <div class="dash-card-label">Total Revenue</div>
            <div class="dash-card-value">₱<?php echo number_format($statTotalRevenue); ?></div>
            <div class="dash-card-sub"><?php echo (int) $statOrderCount; ?> total orders</div>
          </div>
          <div class="dash-card">
            <div class="dash-card-label">Delivered Revenue</div>
            <div class="dash-card-value">₱<?php echo number_format($statDeliveredRevenue); ?></div>
            <div class="dash-card-sub"><?php echo (int) $statStatusCounts['delivered']; ?> delivered</div>
          </div>
          <div class="dash-card">
            <div class="dash-card-label">Total Stock</div>
            <div class="dash-card-value"><?php echo (int) $statTotalStock; ?></div>
            <div class="dash-card-sub"><?php echo (int) $statProductCount; ?> products</div>
          </div>
          <div class="dash-card dash-card-accent">
            <div class="dash-card-label">Low Stock Alert</div>
            <div class="dash-card-value"><?php echo (int) $statLowStockCount; ?></div>
            <div class="dash-card-sub"><?php echo (int) $statUserCount; ?> registered customers</div>
          </div>
        </div>

        <div class="dash-panel">
          <div class="dash-panel-head">
            <div class="dash-panel-title">Store Sales Trend — <?php echo date('Y'); ?></div>
          </div>
          <div class="dash-chart-row">
            <div class="dash-chart">
              <?php foreach ($monthNames as $index => $monthName): $revenueValue = $monthlyRevenue[$index + 1]; $barHeight = $revenueValue > 0 ? max(6, round(($revenueValue / $maxMonthlyRevenue) * 100)) : 2; ?>
                <div class="dash-chart-col">
                  <div class="dash-chart-bar" style="height: <?php echo $barHeight; ?>%;" title="₱<?php echo number_format($revenueValue); ?>"></div>
                  <span class="dash-chart-label"><?php echo $monthName; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="dash-ranking">
              <div class="dash-ranking-title">Top Products</div>
              <?php if (!$topProducts): ?>
                <p class="dash-empty">No sales yet.</p>
              <?php else: ?>
                <?php foreach ($topProducts as $rank => $product): ?>
                  <div class="dash-ranking-row">
                    <span class="dash-rank-badge"><?php echo $rank + 1; ?></span>
                    <span class="dash-rank-name"><?php echo htmlspecialchars($product['product_name']); ?></span>
                    <span class="dash-rank-value">₱<?php echo number_format($product['revenue']); ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="dash-panel">
          <div class="dash-panel-head">
            <div class="dash-panel-title">Orders by Status</div>
          </div>
          <div class="dash-status-row">
            <div class="dash-status-chip status-pending">Pending<span><?php echo (int) $statStatusCounts['pending']; ?></span></div>
            <div class="dash-status-chip status-processing">Processing<span><?php echo (int) $statStatusCounts['processing']; ?></span></div>
            <div class="dash-status-chip status-shipped">Shipped<span><?php echo (int) $statStatusCounts['shipped']; ?></span></div>
            <div class="dash-status-chip status-delivered">Delivered<span><?php echo (int) $statStatusCounts['delivered']; ?></span></div>
            <div class="dash-status-chip status-failed">Failed<span><?php echo (int) $statStatusCounts['failed']; ?></span></div>
          </div>
        </div>
      </section>
