<?php
/**
 * Profile photo block — paste this into account/profile-card.php.
 * Fetches the current user's avatar directly, so it works regardless of
 * what account-view-data.php already passes in.
 */
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