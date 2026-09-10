<?php
session_start();
require_once __DIR__ . '/database.php';

if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$database = getDatabase();
$userId = (int) $_SESSION['user_id'];

$allowedImageTypes = [
  'image/jpeg' => 'jpg',
  'image/png' => 'png',
  'image/gif' => 'gif',
  'image/webp' => 'webp',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_avatar') {
  $file = $_FILES['avatar'] ?? null;

  if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_message'] = 'The image could not be uploaded.';
    header('Location: account.php');
    exit;
  }

  $mimeType = mime_content_type($file['tmp_name']);
  if (!isset($allowedImageTypes[$mimeType])) {
    $_SESSION['flash_message'] = 'Please upload a JPG, PNG, GIF, or WEBP image.';
    header('Location: account.php');
    exit;
  }

  // 5MB cap, generous enough for a phone photo without letting someone fill the disk.
  if ($file['size'] > 5 * 1024 * 1024) {
    $_SESSION['flash_message'] = 'That image is too large — please use one under 5MB.';
    header('Location: account.php');
    exit;
  }

  $extension = $allowedImageTypes[$mimeType];
  $filename = 'user-' . $userId . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $extension;

  $targetDir = __DIR__ . '/Pictures/avatars';
  if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
  }
  $targetPath = $targetDir . '/' . $filename;

  if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    $_SESSION['flash_message'] = 'The image could not be uploaded.';
    header('Location: account.php');
    exit;
  }

  // Clean up the old avatar file so uploads don't pile up unused images.
  $oldAvatarStatement = $database->prepare('SELECT avatar FROM users WHERE id = ?');
  $oldAvatarStatement->execute([$userId]);
  $oldAvatar = $oldAvatarStatement->fetchColumn();
  if ($oldAvatar && file_exists(__DIR__ . '/' . $oldAvatar)) {
    unlink(__DIR__ . '/' . $oldAvatar);
  }

  $newAvatarPath = 'Pictures/avatars/' . $filename;
  $database->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$newAvatarPath, $userId]);

  $_SESSION['flash_message'] = 'Profile photo updated.';
  header('Location: account.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_avatar') {
  $oldAvatarStatement = $database->prepare('SELECT avatar FROM users WHERE id = ?');
  $oldAvatarStatement->execute([$userId]);
  $oldAvatar = $oldAvatarStatement->fetchColumn();
  if ($oldAvatar && file_exists(__DIR__ . '/' . $oldAvatar)) {
    unlink(__DIR__ . '/' . $oldAvatar);
  }
  $database->prepare("UPDATE users SET avatar = '' WHERE id = ?")->execute([$userId]);
  $_SESSION['flash_message'] = 'Profile photo removed.';
  header('Location: account.php');
  exit;
}

header('Location: account.php');
exit;