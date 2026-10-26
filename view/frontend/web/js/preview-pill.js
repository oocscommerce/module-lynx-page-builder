(function () {
    'use strict';

    var pill = document.querySelector('.lynx-preview-pill'),
        left,
        until,
        drag = null,
        dragged = false,
        moved = false,
        leaving = false;

    /**
     * Fill %1, %2… of a translated format
     *
     * @param {String} format
     * @param {Array} values
     * @return {String}
     */
    function fill(format, values) {
        return format.replace(/%(\d)/g, function (_, i) {
            return values[Number(i) - 1];
        });
    }

    /**
     * Leave through Exit: a reload would bring back the preview's cache entry, which the vary cookie still points at
     */
    function leave() {
        var key = 'lynx_preview_left',
            last = 0;

        // Back from Exit within seconds: a cache entry the preview polluted, which leaving again would only loop on
        try {
            last = Number(window.sessionStorage.getItem(key)) || 0;
            window.sessionStorage.setItem(key, String(Date.now()));
        } catch (e) {
            return;
        }

        if (Date.now() - last > 10000) {
            window.location.replace(pill.querySelector('a').href);
        }
    }

    /**
     * Show the time left, or leave once it runs out
     */
    function drawLeft() {
        var remaining = until - Date.now(),
            minutes,
            hours,
            amount;

        if (remaining <= 0) {
            leave();

            return;
        }
        minutes = Math.ceil(remaining / 60000);
        hours = Math.floor(minutes / 60);
        amount = hours > 0 ?
            fill(left.dataset.hm, [hours, minutes % 60]) :
            fill(left.dataset.m, [minutes]);
        left.textContent = fill(left.dataset.left, [amount]);
        left.dateTime = new Date(until).toISOString();
        left.hidden = false;
    }

    /**
     * Move the pill to x, y, kept inside the viewport
     *
     * @param {Number} x
     * @param {Number} y
     */
    function place(x, y) {
        var maxLeft = window.innerWidth - pill.offsetWidth - 4,
            maxTop = window.innerHeight - pill.offsetHeight - 4;

        pill.style.left = Math.min(Math.max(x, 4), Math.max(maxLeft, 4)) + 'px';
        pill.style.top = Math.min(Math.max(y, 4), Math.max(maxTop, 4)) + 'px';
        pill.style.bottom = 'auto';
        // The centring transform would shift every position by half a pill
        pill.style.transform = 'none';
        moved = true;
    }

    if (!pill) {
        return;
    }

    // Not in the editor's frame: an Exit there would be undone by the next draft
    if (window.self !== window.top) {
        pill.remove();

        return;
    }

    // Countdown from Model\PreviewMode's cookie; reloads when it runs out
    left = pill.querySelector('.lynx-preview-left');
    until = (function () {
        var match = document.cookie.match(/(?:^|;\s*)lynx_preview_until=(\d+)/);

        return match ? Number(match[1]) * 1000 : 0;
    })();

    // No cookie: the preview ran out, and this page is a cached copy from it
    if (!until) {
        leave();

        return;
    }

    if (left) {
        drawLeft();
        setInterval(drawLeft, 30000);
    }

    pill.addEventListener('pointerdown', function (e) {
        var box = pill.getBoundingClientRect();

        drag = {
            id: e.pointerId,
            x: e.clientX,
            y: e.clientY,
            dx: e.clientX - box.left,
            dy: e.clientY - box.top
        };
        dragged = false;
    });

    pill.addEventListener('pointermove', function (e) {
        if (!drag) {
            return;
        }

        // A few pixels of slop, so a click on Exit stays a click
        if (!dragged && Math.abs(e.clientX - drag.x) + Math.abs(e.clientY - drag.y) < 4) {
            return;
        }

        if (!dragged) {
            dragged = true;
            pill.classList.add('_dragging');
            // Capture only once moved: capture retargets the click away from Exit
            pill.setPointerCapture(drag.id);
        }

        place(e.clientX - drag.dx, e.clientY - drag.dy);
    });

    pill.addEventListener('pointerup', function (e) {
        if (!drag) {
            return;
        }

        if (dragged && pill.hasPointerCapture(e.pointerId)) {
            pill.releasePointerCapture(e.pointerId);
        }
        drag = null;
        pill.classList.remove('_dragging');
    });

    pill.addEventListener('click', function (e) {
        if (dragged || leaving) {
            e.preventDefault();
            e.stopPropagation();

            return;
        }

        if (e.target.closest('a')) {
            leaving = true;
            pill.classList.add('_leaving');
        }
    }, true);

    // Back/forward cache restores the leaving state
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) {
            return;
        }

        leaving = false;
        pill.classList.remove('_leaving');
    });

    window.addEventListener('resize', function () {
        var box;

        // An untouched pill stays centred by CSS
        if (!moved) {
            return;
        }

        box = pill.getBoundingClientRect();
        place(box.left, box.top);
    });
})();
