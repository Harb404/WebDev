<?php
/**
 * Messages tab: live-chat inbox shell (populated client-side by admin/chat-widget.js via Chat.php). Included by admin.php.
 */
?>
      <section class="dash-section" data-panel="messages"<?php echo $activePanel === 'messages' ? '' : ' hidden'; ?>>
        <div class="dash-panel-head"><div class="dash-panel-title">Client Messages</div></div>
        <div class="dash-chat-shell">
          <div class="dash-chat-threads-col">
            <input type="text" id="chat-thread-search" class="dash-search-input dash-chat-search-input" placeholder="Search by name…" autocomplete="off">
            <div class="dash-chat-threads" id="chat-thread-list">
              <p class="dash-empty">Loading conversations…</p>
            </div>
          </div>
          <div class="dash-chat-panel">
            <div class="dash-chat-panel-head" id="chat-panel-head">Select a conversation</div>
            <div class="dash-chat-log" id="chat-log">
              <div class="dash-chat-empty">Pick a client on the left to view the conversation.</div>
            </div>
            <form class="dash-chat-reply" id="chat-reply-form" hidden>
              <input type="text" id="chat-reply-input" placeholder="Type a reply…" autocomplete="off" required>
              <button class="dash-btn-primary" type="submit">Send</button>
            </form>
          </div>
        </div>
      </section>