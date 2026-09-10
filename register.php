<?php
session_start();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/validation.php';

if (!empty($_SESSION['user_id'])) {
  header('Location: account.php');
  exit;
}

function safeNextUrl(?string $next): ?string
{
  if (!$next) {
    return null;
  }
  // Only allow relative links to local .php pages — no scheme, no host, no protocol-relative //.
  if (preg_match('~^(?!//)(?!https?:)[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_\-=&%.]*)?(#[A-Za-z0-9_\-]*)?$~', $next)) {
    return $next;
  }
  return null;
}

$nextUrl = safeNextUrl($_GET['next'] ?? $_POST['next'] ?? null);

$database = getDatabase();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = $_POST['password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';
  $address = '';
  $location = '';
  $zip = '';
  $phone = '';

  $error = validateRegistrationFields($name, $email, $password, $address, $location, $zip, $phone, true, false);
  if ($error === '' && $password !== $confirmPassword) {
    $error = 'Passwords do not match.';
  }
  if ($error === '') {
    try {
      $statement = $database->prepare('INSERT INTO users (name, email, password, address, location, zip, phone) VALUES (?, ?, ?, ?, ?, ?, ?)');
      $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $address, $location, $zip, $phone]);
      // A brand-new customer account should never inherit an admin flag left
      // over from an earlier session in the same browser/tab.
      unset($_SESSION['is_admin']);
      $_SESSION['user_id'] = (int) $database->lastInsertId();
      $_SESSION['user_name'] = $name;
      header('Location: ' . ($nextUrl ?: 'index.php'));
      exit;
    } catch (PDOException $exception) {
      $error = 'That email is already registered.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — Masalihit Luxe</title>
<link rel="stylesheet" href="style.css?v=<?php echo file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time(); ?>">
<script src="script.js?v=<?php echo file_exists(__DIR__ . '/script.js') ? filemtime(__DIR__ . '/script.js') : time(); ?>" defer></script>
</head>
<body>
<main class="auth-page register-page">
  <form class="auth-card" method="post">
    <div class="eyebrow">Masalihit Luxe</div>
    <h1 class="display">Create Account</h1>
    <p class="form-intro"></p>
    <?php if ($error): ?><p class="form-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
    <?php if ($nextUrl): ?><input type="hidden" name="next" value="<?php echo htmlspecialchars($nextUrl); ?>"><?php endif; ?>
    <label>Name<input type="text" name="name" required autocomplete="name"></label>
    <label>Email<input type="email" name="email" required autocomplete="email"></label>
    <label>Password
      <div class="password-input-wrap">
        <input type="password" name="password" id="register-password" minlength="6" required autocomplete="new-password">
        <button type="button" class="password-toggle" data-toggle-for="register-password" aria-label="Show password" aria-pressed="false">
          <svg class="icon-eye" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg>
          <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M3 3l18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </button>
      </div>
    </label>
    <label>Confirm Password
      <div class="password-input-wrap">
        <input type="password" name="confirm_password" id="register-confirm-password" minlength="6" required autocomplete="new-password">
        <button type="button" class="password-toggle" data-toggle-for="register-confirm-password" aria-label="Show password" aria-pressed="false">
          <svg class="icon-eye" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg>
          <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M3 3l18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </button>
      </div>
      <span class="form-error password-match-error" id="confirm-password-error" hidden>Passwords do not match.</span>
    </label>
    <p class="form-note"></p>
    <button class="cart-button" type="submit">Create Account</button>
    <a class="back-link" href="login.php">Already have an account? Log in</a>
    <a class="back-link" href="index.php">Back to store</a>
  </form>
</main>
</body>
</html>