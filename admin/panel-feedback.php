<?php
/**
 * Feedback tab: every submitted product review, with a toggle to feature it
 * on the homepage testimonials section. Included by admin.php. Expects
 * $reviews to be set by includes/admin-data.php.
 */
?>
      <section class="dash-section" data-panel="feedback"<?php echo $activePanel === 'feedback' ? '' : ' hidden'; ?>>
        <div class="dash-panel">
          <div class="dash-panel-head">
            <div class="dash-panel-title">Customer Feedback</div>
            <p class="dash-feedback-hint">Feature a review to show it in the "Style your ideal" section on the homepage. A few featured reviews looks best.</p>
          </div>
          <div class="dash-table">
            <?php if (!$reviews): ?>
              <div class="dash-empty">No reviews yet.</div>
            <?php endif; ?>
            <?php foreach ($reviews as $review): ?>
              <div class="dash-table-row dash-feedback-row">
                <div class="dash-table-main">
                  <strong><?php echo htmlspecialchars($review['user_name']); ?></strong>
                  <span>
                    <?php if ($review['user_location']): ?><?php echo htmlspecialchars($review['user_location']); ?> · <?php endif; ?>
                    <?php echo htmlspecialchars($review['product_name']); ?>
                    · <span class="dash-feedback-stars"><?php echo str_repeat('★', (int) $review['rating']) . str_repeat('☆', 5 - (int) $review['rating']); ?></span>
                    <?php if ((int) $review['is_featured'] === 1): ?><span class="dash-badge-featured">Featured</span><?php endif; ?>
                  </span>
                  <?php if (trim($review['comment']) !== ''): ?>
                    <span class="dash-feedback-comment">"<?php echo htmlspecialchars($review['comment']); ?>"</span>
                  <?php endif; ?>
                </div>

                <form class="dash-inline-form" method="post">
                  <input type="hidden" name="action" value="toggle_featured_review">
                  <input type="hidden" name="panel" value="feedback">
                  <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                  <button class="<?php echo (int) $review['is_featured'] === 1 ? 'dash-btn-secondary' : 'dash-btn-primary'; ?>" type="submit">
                    <?php echo (int) $review['is_featured'] === 1 ? 'Remove from Homepage' : 'Feature on Homepage'; ?>
                  </button>
                </form>

                <form class="dash-inline-form" method="post">
                  <input type="hidden" name="action" value="delete_review">
                  <input type="hidden" name="panel" value="feedback">
                  <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                  <button class="dash-btn-danger" type="submit" onclick="return confirm('Delete this review?');">Delete</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
