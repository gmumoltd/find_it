// =====================================================================
// FindPoint — chat page behaviour (chat.php)
// Polls chat-poll.php for new messages and sends new messages without
// a full page reload. If JS fails for any reason, the chat form still
// works as a normal POST (chat.php handles both cases).
// =====================================================================

document.addEventListener('DOMContentLoaded', function () {

    var thread = document.getElementById('chatThread');
    if (!thread) {
        return; // Not on the chat page — nothing to do.
    }

    var conversationId = thread.getAttribute('data-conversation-id');
    var form = document.getElementById('chatForm');
    var textarea = document.getElementById('chatMessageInput');
    var sendButton = document.getElementById('chatSendBtn');

    // Escapes text before it is inserted with innerHTML, so a message
    // containing "<script>" or similar is shown as plain text, not run.
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function lastRenderedId() {
        var bubbles = thread.querySelectorAll('[data-message-id]');
        if (bubbles.length === 0) return 0;
        var last = bubbles[bubbles.length - 1];
        return parseInt(last.getAttribute('data-message-id'), 10) || 0;
    }

    function scrollToBottom() {
        thread.scrollTop = thread.scrollHeight;
    }

    function appendBubble(msg) {
        // Skip if this message is already on screen (avoids duplicates
        // between the optimistic send and the next poll).
        if (thread.querySelector('[data-message-id="' + msg.id + '"]')) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'chat-bubble ' + (msg.is_mine ? 'sent' : 'received');
        wrap.setAttribute('data-message-id', msg.id);
        wrap.innerHTML = escapeHtml(msg.message).replace(/\n/g, '<br>') +
            '<span class="chat-time">' + escapeHtml(msg.time_label) + '</span>';
        thread.appendChild(wrap);
    }

    function removeEmptyState() {
        var empty = thread.querySelector('.empty-state');
        if (empty) empty.remove();
    }

    // -------------------------------------------------------------
    // Sending a message
    // -------------------------------------------------------------
    if (form && textarea) {
        form.addEventListener('submit', function (e) {
            var text = textarea.value.trim();
            if (text === '') {
                e.preventDefault();
                return;
            }

            e.preventDefault();
            if (sendButton) sendButton.disabled = true;

            var formData = new FormData();
            formData.append('message', text);
            formData.append('conversation_id', conversationId);

            fetch('chat.php?id=' + encodeURIComponent(conversationId), {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        removeEmptyState();
                        appendBubble(data.message);
                        textarea.value = '';
                        scrollToBottom();
                    } else {
                        alert(data.error || 'Could not send your message. Please try again.');
                    }
                })
                .catch(function () {
                    // Network hiccup — fall back to a real form submit so
                    // the message still goes through.
                    form.submit();
                })
                .finally(function () {
                    if (sendButton) sendButton.disabled = false;
                });
        });

        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit'));
            }
        });
    }

    // -------------------------------------------------------------
    // Polling for new messages from the other person
    // -------------------------------------------------------------
    function poll() {
        var afterId = lastRenderedId();
        fetch('chat-poll.php?id=' + encodeURIComponent(conversationId) + '&after_id=' + afterId)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.messages && data.messages.length > 0) {
                    removeEmptyState();
                    data.messages.forEach(appendBubble);
                    scrollToBottom();
                }
            })
            .catch(function () {
                // Silently ignore — we'll just try again on the next tick.
            });
    }

    scrollToBottom();
    setInterval(poll, 4000);

});
