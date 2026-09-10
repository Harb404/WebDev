<?php
/**
 * Shared site footer, included by index.php and Contact.php.
 * Set $onHomePage = true before including this file when the current
 * page IS index.php, so footer links scroll the page instead of
 * navigating to index.php's anchors.
 */
$onHomePage = $onHomePage ?? false;
?>
<footer>
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="logo">
          <span class="logo-mark"><img src="Pictures/logoh.png" alt="Masalihit Luxe logo"></span>
          Masalihit Luxe
        </div>
        <p>Next-generation design meets everyday utility. High-performance shoes, shirts, and rugged drinkware engineered for your active lifestyle.</p>
        <div class="footer-social">
          <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 8h-2c-.55 0-1 .45-1 1v2h3l-.4 3H12v7h-3v-7H7v-3h2V8.5C9 6.57 10.57 5 12.5 5H15v3Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></span>
          <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21 5.5c-.7.35-1.46.58-2.25.69a3.9 3.9 0 0 0 1.71-2.16c-.76.46-1.6.8-2.5.98A3.86 3.86 0 0 0 15.1 4c-2.15 0-3.9 1.78-3.9 3.98 0 .31.03.62.1.9-3.24-.17-6.11-1.75-8.03-4.17-.34.6-.53 1.29-.53 2.03 0 1.38.69 2.6 1.73 3.32-.64-.02-1.24-.2-1.77-.5v.05c0 1.93 1.34 3.54 3.13 3.9-.33.09-.67.14-1.03.14-.25 0-.5-.02-.73-.07.5 1.58 1.94 2.73 3.65 2.76A7.72 7.72 0 0 1 2 18.4a10.85 10.85 0 0 0 5.94 1.77c7.13 0 11.03-6.03 11.03-11.26l-.01-.51c.76-.56 1.42-1.26 1.94-2.06-.7.32-1.44.53-2.2.63Z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg></span>
          <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="3.5" width="17" height="17" rx="4.5" stroke="currentColor" stroke-width="1.4"/><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor"/></svg></span>
        </div>
      </div>
      <div class="footer-col">
        <h4>Company</h4>
        <ul>
          <li><span onclick="<?php echo $onHomePage ? "goTo('top')" : "window.location.href='index.php'"; ?>">Home</span></li>
          <li><span onclick="<?php echo $onHomePage ? "goTo('about')" : "window.location.href='index.php#about'"; ?>">About</span></li>
          <li><span onclick="<?php echo $onHomePage ? "goTo('features')" : "window.location.href='index.php#features'"; ?>">Featured</span></li>
          <li><span onclick="window.location.href='contact.php'">Contact</span></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Collection</h4>
        <ul>
          <li><span onclick="<?php echo $onHomePage ? "goTo('features')" : "window.location.href='index.php#features'"; ?>">Shoes</span></li>
          <li><span onclick="<?php echo $onHomePage ? "goTo('features')" : "window.location.href='index.php#features'"; ?>">Shirt</span></li>
          <li><span onclick="<?php echo $onHomePage ? "goTo('features')" : "window.location.href='index.php#features'"; ?>">Tumbler</span></li>
          <li><span onclick="<?php echo $onHomePage ? "goTo('features')" : "window.location.href='index.php#features'"; ?>">Cap</span></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Support</h4>
        <ul>
          <li><span onclick="<?php echo $onHomePage ? "goTo('contact')" : "window.location.href='contact.php'"; ?>">FAQ</span></li>
          <li><span onclick="<?php echo $onHomePage ? "goTo('contact')" : "window.location.href='contact.php'"; ?>">Shipping</span></li>
          <li><span onclick="<?php echo $onHomePage ? "goTo('contact')" : "window.location.href='contact.php'"; ?>">Returns</span></li>
          <li><span onclick="<?php echo $onHomePage ? "goTo('contact')" : "window.location.href='contact.php'"; ?>">Size Guide</span></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?php echo $year; ?> Masalihit Luxe. All rights reserved.</span>
      <span>Gear Shift Mode</span>
    </div>
  </div>
</footer>
