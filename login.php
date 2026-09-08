<?php
session_start();
require_once __DIR__ . '/database.php';

if (!empty($_SESSION['is_admin'])) {
  header('Location: admin.php');
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
  $loginValue = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  $loginValue = strtolower($loginValue) === 'admin' ? 'admin@masalihit.local' : $loginValue;
  $statement = $database->prepare('SELECT id, name, password, role, status FROM users WHERE email = ? OR LOWER(name) = LOWER(?) LIMIT 1');
  $statement->execute([$loginValue, $loginValue]);
  $user = $statement->fetch(PDO::FETCH_ASSOC);

  if ($user && $user['role'] === 'admin' && password_verify($password, $user['password'])) {
    unset($_SESSION['user_id'], $_SESSION['user_name']);
    $_SESSION['is_admin'] = true;
    header('Location: admin.php');
    exit;
  }

  if ($user && $user['role'] === 'client' && password_verify($password, $user['password'])) {
    if (($user['status'] ?? 'active') === 'banned') {
      $error = 'This account has been suspended. Contact support if you think this is a mistake.';
    } else {
      unset($_SESSION['is_admin']);
      $_SESSION['user_id'] = (int) $user['id'];
      $_SESSION['user_name'] = $user['name'];
      if ($nextUrl) {
        header('Location: ' . $nextUrl);
      } else {
        header('Location: account.php' . (isset($_GET['checkout']) ? '?checkout=1' : ''));
      }
      exit;
    }
  }
  if ($error === '') {
    $error = 'Incorrect username/email or password.';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Masalihit Luxe</title>
<link rel="stylesheet" href="style.css?v=<?php echo file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time(); ?>">
<script src="script.js?v=<?php echo file_exists(__DIR__ . '/script.js') ? filemtime(__DIR__ . '/script.js') : time(); ?>" defer></script>
</head>
<body>
<main class="auth-page">
  <form class="auth-card" method="post">
    <h1 class="display">Login</h1>
    <?php if ($nextUrl && !$error): ?><p class="form-intro">Log in to continue — we'll take you right back to what you were doing.</p><?php endif; ?>
    <?php if ($error): ?><p class="form-error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
    <?php if ($nextUrl): ?><input type="hidden" name="next" value="<?php echo htmlspecialchars($nextUrl); ?>"><?php endif; ?>
    <label>Username or Email<input type="text" name="email" required autocomplete="username"></label>
    <label>Password
      <div class="password-input-wrap">
        <input type="password" name="password" id="login-password" required autocomplete="current-password">
        <button type="button" class="password-toggle" data-toggle-for="login-password" aria-label="Show password" aria-pressed="false">
          <svg class="icon-eye" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg>
          <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M3 3l18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </button>
      </div>
    </label>
    <button class="cart-button" type="submit">Log In</button>
    <a class="back-link" href="register.php">No account? Register here</a>
    <a class="back-link" href="index.php">Back to store</a>
  </form>
</main>
</body>
</html>