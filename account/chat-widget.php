<?php
/**
 * Live Chat card shown on the plain account page (not during checkout).
 * Styled like the admin panel's chat inbox (a header bar, a message log,
 * and a reply bar, each separated by a border) so it feels like part of
 * the same messaging experience. Talks to Chat.php exactly like before —
 * same element IDs — so script.js's setupChatForm()/startChatPolling()
 * wire it up automatically. The visitor is always logged in on this page,
 * so — unlike the old contact-page version — there's no guest "name" field.
 */
?>
    <?php if (!$isCheckout): ?><section class="profile-card chat-account-card">
      <div class="chat-panel-head">
        <div>
          <div class="eyebrow">Need Help?</div>
          <h2>Live Chat</h2>
        </div>
        <span class="chat-panel-status">We usually reply fast</span>
      </div>
      <div class="chat-messages" id="chat-messages"><p class="chat-empty">Say hello — we're happy to help.</p></div>
      <form class="chat-form" id="chat-form">
        <div class="chat-form-row">
          <input type="text" id="chat-message-input" placeholder="Type a message…" autocomplete="off" required>
          <button type="submit" aria-label="Send message">
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 12l16-7-6.5 16-2.8-6.7L4 12z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
          </button>
        </div>
      </form>
    </section><?php endif; ?>