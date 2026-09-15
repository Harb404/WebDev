<?php
/**
 * Profile Details card shown on the plain account page (not during
 * checkout): read-only summary plus an expandable edit form that
 * posts to the update_profile action.
 * Expects $profile, $name, $address, $location, $zip, $phone, and
 * $error to be set by the caller.
 */
?>
    <?php if (!$isCheckout): ?><section class="profile-card">
      <?php
        $avatarStatement = $database->prepare('SELECT name, avatar FROM users WHERE id = ?');
        $avatarStatement->execute([(int) $_SESSION['user_id']]);
        $avatarRow = $avatarStatement->fetch(PDO::FETCH_ASSOC);
        $currentAvatar = $avatarRow['avatar'] ?? '';
        $avatarInitial = $avatarRow['name'] ? strtoupper(substr($avatarRow['name'], 0, 1)) : '?';
      ?>
      <div class="profile-avatar-block">
        <?php if ($currentAvatar !== ''): ?>
          <img class="profile-avatar-img" src="<?php echo htmlspecialchars($currentAvatar); ?>" alt="Your profile photo">
        <?php else: ?>
          <div class="profile-avatar-placeholder"><?php echo htmlspecialchars($avatarInitial); ?></div>
        <?php endif; ?>

        <form method="post" action="avatar.php" enctype="multipart/form-data" class="profile-avatar-form">
          <input type="hidden" name="action" value="upload_avatar">
          <label class="profile-avatar-upload-btn">
            Change Photo
            <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" onchange="this.form.submit()" hidden>
          </label>
        </form>

        <?php if ($currentAvatar !== ''): ?>
          <form method="post" action="avatar.php" onsubmit="return confirm('Remove your profile photo?');">
            <input type="hidden" name="action" value="remove_avatar">
            <button type="submit" class="profile-avatar-remove-btn">Remove Photo</button>
          </form>
        <?php endif; ?>
      </div>
      <div class="eyebrow">Profile Details</div>
      <h2><?php echo htmlspecialchars($profile['name'] ?? $_SESSION['user_name']); ?></h2>
      <div class="profile-details">
        <span><?php echo htmlspecialchars($profile['email'] ?? ''); ?></span>
        <span><?php echo htmlspecialchars(rtrim(trim($address), ', ')); ?>, <?php echo htmlspecialchars($location); ?> <?php echo htmlspecialchars($zip); ?></span>
        <span>Phone: <?php echo $phone !== '' ? htmlspecialchars($phone) : '—'; ?></span>
      </div>
      <details class="edit-profile-toggle" <?php echo (($action ?? '') === 'update_profile' && $error !== '') ? 'open' : ''; ?>>
        <summary>Edit Details</summary>
        <form class="profile-edit-form" method="post">
        <input type="hidden" name="action" value="update_profile">
        <label>Name<input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required autocomplete="name"></label>
        <label>Address<input type="text" name="address" value="<?php echo htmlspecialchars($address); ?>" required autocomplete="street-address"></label>
        <label>City / Location<input type="text" name="location" value="<?php echo htmlspecialchars($location); ?>" required autocomplete="address-level2"></label>
        <label>ZIP Code<input type="text" name="zip" value="<?php echo htmlspecialchars($zip); ?>" required autocomplete="postal-code"></label>
        <label>Phone Number<input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" pattern="09[0-9]{9}" maxlength="11" inputmode="numeric" placeholder="09XXXXXXXXX" title="11 digits starting with 09, e.g. 09171234567" required autocomplete="tel"></label>
        <button class="cart-button" type="submit">Save Changes</button>
        </form>
      </details>
    </section><?php endif; ?>