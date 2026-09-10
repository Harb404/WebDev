<?php
/**
 * Login / Create Account forms shown to a signed-out visitor.
 * Expects $showRegister to be set by the caller.
 */
$showRegister = $showRegister ?? false;
?>
    <div class="account-forms">
      <form class="auth-card" method="post">
        <h2>Log In to continue</h2>
        <input type="hidden" name="action" value="login">
        <label>Email<input type="email" name="email" required autocomplete="email"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="cart-button" type="submit">Log In</button>
      </form>
      <details class="register-option" <?php echo $showRegister ? 'open' : ''; ?>>
        <summary>No account? Create one</summary>
        <form class="auth-card" method="post">
          <h2>Create Account</h2>
          <input type="hidden" name="action" value="register">
          <label>Name<input type="text" name="name" required autocomplete="name"></label>
          <label>Email<input type="email" name="email" required autocomplete="email"></label>
          <label>Password<input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
          <label>Delivery Address<input type="text" name="address" required autocomplete="street-address"></label>
          <label>City / Location<input type="text" name="location" required autocomplete="address-level2"></label>
          <label>ZIP Code<input type="text" name="zip" required autocomplete="postal-code"></label>
          <label>Phone Number<input type="tel" name="phone" pattern="09[0-9]{9}" maxlength="11" inputmode="numeric" placeholder="09XXXXXXXXX" title="11 digits starting with 09, e.g. 09171234567" required autocomplete="tel"></label>
          <p class="form-note">You can add delivery comments later, at checkout.</p>
          <button class="cart-button" type="submit">Create Account</button>
        </form>
      </details>
    </div>