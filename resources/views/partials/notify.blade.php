{{--
    Top-right notifications, the one way the app tells anyone anything.

    notify(message, type, title, link)
      type    success (green), warning or error (both red: either way it did
              not go through), info (blue)
      message a string, or an array of strings shown as a list — validation
              errors, say — under the optional title
      link    optional {url, text}, a link under the message ("View details"
              after a batch upload); the message then stays until closed

    Replaces the browser's alert() box, which blocks the page and reads as
    "localhost:8000 says". Plain DOM with no jQuery or Bootstrap JS, so it can
    sit in <head> and be called from any script on the page, including those
    that run before the layout loads its libraries. Bootstrap's CSS still
    supplies the colours, so these match the success message people know.
--}}
<style>
    .notify-stack {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 9999;
        width: min(400px, calc(100vw - 40px));
        display: flex;
        flex-direction: column;
        gap: 10px;
        /* The empty strip over the page must not swallow clicks. */
        pointer-events: none;
    }

    .notify-stack .alert {
        margin: 0;
        pointer-events: auto;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        animation: notifySlideIn 0.3s ease-out;
    }

    .notify-stack .alert.notify-leaving {
        animation: notifySlideOut 0.3s ease-out forwards;
    }

    /* Messages written with line breaks keep them. */
    .notify-stack .notify-text {
        white-space: pre-line;
    }

    @keyframes notifySlideIn {
        from { transform: translateX(450px); opacity: 0; }
        to   { transform: translateX(0);     opacity: 1; }
    }

    @keyframes notifySlideOut {
        from { transform: translateX(0);     opacity: 1; }
        to   { transform: translateX(450px); opacity: 0; }
    }
</style>

<script>
window.notify = (function () {
    // Success keeps the two seconds it always had. Anything that went wrong
    // stays longer, since it has to be read and acted on.
    const TYPES = {
        success: { cls: 'alert-success', icon: 'fa-check-circle', ms: 2000 },
        warning: { cls: 'alert-danger', icon: 'fa-exclamation-triangle', ms: 5000 },
        error: { cls: 'alert-danger', icon: 'fa-exclamation-circle', ms: 5000 },
        info: { cls: 'alert-info', icon: 'fa-info-circle', ms: 3000 },
    };

    function stack() {
        let el = document.getElementById('notifyStack');

        if (!el) {
            el = document.createElement('div');
            el.id = 'notifyStack';
            el.className = 'notify-stack';
            document.body.appendChild(el);
        }

        return el;
    }

    function dismiss(box) {
        if (box.classList.contains('notify-leaving')) {
            return;
        }

        box.classList.add('notify-leaving');
        setTimeout(() => box.remove(), 300);
    }

    return function notify(message, type = 'info', title = '', link = null) {
        const t = TYPES[type] || TYPES.info;

        const box = document.createElement('div');
        box.className = 'alert alert-dismissible ' + t.cls;
        box.setAttribute('role', t.cls === 'alert-danger' ? 'alert' : 'status');

        const icon = document.createElement('i');
        icon.className = 'fas me-1 ' + t.icon;

        // textContent throughout, never innerHTML: messages carry part numbers,
        // supplier names and server text that must not be read as markup.
        if (Array.isArray(message)) {
            const heading = document.createElement('strong');
            heading.append(icon, ' ' + title);

            const list = document.createElement('ul');
            list.className = 'mb-0 mt-2';
            message.forEach(m => {
                const li = document.createElement('li');
                li.textContent = m;
                list.appendChild(li);
            });

            box.append(heading, list);
        } else {
            const text = document.createElement('span');
            text.className = 'notify-text';
            text.textContent = message;
            box.append(icon, ' ', text);
        }

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('aria-label', 'Close');
        close.addEventListener('click', () => dismiss(box));
        box.appendChild(close);

        if (link && link.url) {
            const a = document.createElement('a');
            a.href = link.url;
            a.className = 'alert-link d-block mt-2';
            a.textContent = (link.text || 'View details') + ' →';
            box.appendChild(a);
        }

        stack().appendChild(box);

        // Something to click: leave it up until it is closed.
        if (link && link.url) {
            return box;
        }

        // A long message (a batch upload summary listing skipped rows, say)
        // gets time to be read: about 60ms a character, up to 20 seconds.
        // Hovering holds it open; it can always be closed with the ×.
        const length = Array.isArray(message) ? message.join(' ').length : String(message).length;
        const ms = Math.min(Math.max(t.ms, length * 60), 20000);
        let timer = setTimeout(() => dismiss(box), ms);
        box.addEventListener('mouseenter', () => clearTimeout(timer));
        box.addEventListener('mouseleave', () => { timer = setTimeout(() => dismiss(box), 2000); });

        return box;
    };
})();
</script>
