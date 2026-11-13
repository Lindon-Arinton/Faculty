(function () {
    'use strict';

    var each = function (sel, fn, root) { Array.prototype.forEach.call((root || document).querySelectorAll(sel), fn); };
    var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---------- Mobile sidebar ----------
    var sidebar = document.getElementById('sidebar');
    function setSidebar(open) { sidebar.classList.toggle('is-open', open); }
    document.getElementById('menuBtn').addEventListener('click', function () { setSidebar(true); });
    document.getElementById('sidebarClose').addEventListener('click', function () { setSidebar(false); });
    document.getElementById('scrim').addEventListener('click', function () { setSidebar(false); });

    // ---------- Drop-down menus (account, notifications) ----------
    var openMenu = null;
    function closeMenus() {
        if (openMenu) { openMenu.hidden = true; openMenu = null; }
    }
    each('[data-menu-toggle]', function (btn) {
        btn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            var menu = document.getElementById(btn.getAttribute('data-menu-toggle'));
            var willOpen = menu.hidden;
            closeMenus();
            if (willOpen) {
                menu.hidden = false;
                openMenu = menu;
                setSidebar(false);
            }
        });
    });
    document.addEventListener('click', function (ev) {
        if (openMenu && !openMenu.contains(ev.target)) closeMenus();
    });

    // ---------- Filters: submit as soon as a dropdown changes ----------
    each('select[data-autosubmit]', function (sel) {
        sel.addEventListener('change', function () {
            if (sel.form) sel.form.submit();
        });
    });

    // ---------- Clickable rows, bars and funnel steps ----------
    function go(el, ev) {
        if (ev.target.closest('a, button, select, input, label')) return;
        ev.preventDefault();
        window.location.href = el.getAttribute('data-href');
    }
    each('[data-href]:not(circle)', function (row) {
        row.addEventListener('click', function (ev) { go(row, ev); });
        row.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && ev.target === row) go(row, ev);
        });
    });

    // ---------- Overview: recent applicant chips ----------
    var list = document.getElementById('applicantList');
    if (list) {
        var chips = document.querySelectorAll('.chip[data-filter]');
        var rows = list.querySelectorAll('.list-row');
        var empty = list.querySelector('.list-empty');
        var count = document.getElementById('applicantCount');
        each('.chip[data-filter]', function (chip) {
            chip.addEventListener('click', function () {
                each('.chip[data-filter]', function (c) { c.classList.toggle('is-active', c === chip); });
                var f = chip.dataset.filter, shown = 0;
                Array.prototype.forEach.call(rows, function (r) {
                    var match = f === 'All' || r.dataset.status === f;
                    r.hidden = !match;
                    if (match) shown++;
                });
                empty.hidden = shown > 0;
                count.textContent = shown;
            });
        });
        void chips;
    }

    // ---------- Search shortcut (Ctrl/⌘ + K) ----------
    var search = document.getElementById('globalSearch');
    document.addEventListener('keydown', function (ev) {
        if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'k') {
            ev.preventDefault();
            search.focus();
            search.select();
        }
        if (ev.key === 'Escape') {
            closeMenus();
            setSidebar(false);
        }
    });
    if (/Mac|iPhone|iPad/.test(navigator.platform || '')) {
        each('.search kbd', function (kbd) { kbd.textContent = '⌘ K'; });
    }

    // ---------- Pop-up forms ----------
    var meetRows = document.getElementById('meetRows');
    var meetTpl = document.getElementById('meetRowTpl');

    function addMeeting(m) {
        var row = meetTpl.content.firstElementChild.cloneNode(true);
        if (m) {
            row.querySelector('[name="meet_day[]"]').value = m.day;
            row.querySelector('[name="meet_start[]"]').value = m.start;
            row.querySelector('[name="meet_end[]"]').value = m.end;
            row.querySelector('[name="meet_room[]"]').value = m.room || '';
        }
        meetRows.appendChild(row);
    }
    function syncMode(dlg) {
        var sel = dlg.querySelector('[data-mode-select]');
        if (!sel) return;
        each('[data-show-mode]', function (n) { n.hidden = n.getAttribute('data-show-mode') !== sel.value; }, dlg);
        each('[data-hide-mode]', function (n) {
            var off = n.getAttribute('data-hide-mode') === sel.value;
            n.hidden = off;
            each('input, select', function (f) { f.disabled = off; }, n);
        }, dlg);
        if (meetRows) meetRows.classList.toggle('no-rooms', sel.value !== 'Onsite');
    }

    function openModal(id, btn) {
        var dlg = document.getElementById(id);
        if (!dlg) return;
        var form = dlg.querySelector('form');
        if (form && id !== 'confirmModal') {
            form.reset();
            var fill = btn && btn.dataset.fill ? JSON.parse(btn.dataset.fill) : {};
            var editing = fill.id !== undefined && fill.id !== '' && fill.id !== null;
            form.elements['id'].value = '';
            var title = dlg.querySelector('[data-modal-title]');
            if (title) title.textContent = (btn && btn.dataset.title) || title.dataset.default;

            Object.keys(fill).forEach(function (key) {
                var v = fill[key];
                if (key === 'meetings') return;
                if (Array.isArray(v)) {
                    var have = v.map(String);
                    each('[name="' + key + '[]"]', function (cb) { cb.checked = have.indexOf(cb.value) > -1; }, form);
                    return;
                }
                var el = form.elements[key];
                if (el && el.tagName) el.value = v === null ? '' : v;
                each('[data-fill-text="' + key + '"]', function (n) { n.textContent = v; }, dlg);
            });
            each('[data-lock-on-edit]', function (el) { el.disabled = editing; }, form);
            each('[data-edit-only]', function (n) { n.hidden = !editing; }, dlg);
            each('[data-add-only]', function (n) { n.hidden = editing; }, dlg);

            if (id === 'classModal' && meetRows) {
                meetRows.innerHTML = '';
                (fill.meetings || []).forEach(addMeeting);
                syncMode(dlg);
            }
        }
        closeMenus();
        dlg.showModal();
        var first = dlg.querySelector('.modal-body input:not([type=hidden]):not(:disabled), .modal-body select:not(:disabled), .modal-body textarea');
        if (first && form && id !== 'confirmModal') first.focus();
    }

    each('[data-modal]', function (btn) {
        btn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            openModal(btn.getAttribute('data-modal'), btn);
        });
    });
    each('dialog.modal', function (dlg) {
        each('[data-close]', function (b) { b.addEventListener('click', function () { dlg.close(); }); }, dlg);
        // A click on the dimmed area (outside the card) closes the dialog.
        dlg.addEventListener('mousedown', function (ev) { if (ev.target === dlg) dlg.close(); });
    });
    if (meetRows) {
        document.getElementById('meetAdd').addEventListener('click', function () {
            var last = meetRows.lastElementChild;
            addMeeting(last ? {
                day: last.querySelector('[name="meet_day[]"]').value,
                start: last.querySelector('[name="meet_start[]"]').value,
                end: last.querySelector('[name="meet_end[]"]').value,
                room: last.querySelector('[name="meet_room[]"]').value
            } : null);
            syncMode(document.getElementById('classModal'));
        });
        meetRows.addEventListener('click', function (ev) {
            var rm = ev.target.closest('[data-meet-remove]');
            if (rm) rm.closest('.meet-row').remove();
        });
        each('#classModal [data-mode-select]', function (sel) {
            sel.addEventListener('change', function () { syncMode(document.getElementById('classModal')); });
        });
    }

    // ---------- "Are you sure?" before deleting ----------
    var confirmDlg = document.getElementById('confirmModal');
    each('[data-confirm]', function (btn) {
        btn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            var form = confirmDlg.querySelector('form');
            form.elements['action'].value = btn.dataset.confirm;
            form.elements['id'].value = btn.dataset.id;
            form.elements['return'].value = btn.dataset.return || '';
            document.getElementById('confirmText').textContent = btn.dataset.text;
            document.getElementById('confirmLabel').textContent = /remove/.test(btn.dataset.confirm) ? 'Remove' : 'Delete';
            var extra = document.getElementById('confirmExtra');
            extra.innerHTML = '';
            var more = btn.dataset.extra ? JSON.parse(btn.dataset.extra) : {};
            Object.keys(more).forEach(function (k) {
                var input = document.createElement('input');
                input.type = 'hidden'; input.name = k; input.value = more[k];
                extra.appendChild(input);
            });
            confirmDlg.showModal();
        });
    });

    // ---------- Saving: show a spinner and block double clicks ----------
    each('form[action="actions.php"]', function (form) {
        form.addEventListener('submit', function (ev) {
            var btn = ev.submitter || form.querySelector('[type=submit]');
            window.onbeforeunload = null;
            setTimeout(function () {
                each('[type=submit]', function (b) { b.disabled = true; }, form);
                if (btn) btn.classList.add('is-busy');
            }, 0);
        });
    });

    // ---------- Grade sheet: flag unsaved changes ----------
    var gradesForm = document.getElementById('gradesForm');
    var unsaved = document.getElementById('unsaved');
    if (gradesForm && unsaved) {
        each('select.f-input--grade', function (sel) {
            var original = sel.value;
            sel.addEventListener('change', function () {
                sel.classList.toggle('is-changed', sel.value !== original);
                var dirty = !!gradesForm.querySelector('.is-changed');
                unsaved.hidden = !dirty;
                window.onbeforeunload = dirty ? function () { return true; } : null;
            });
        }, gradesForm);
    }

    // ---------- Messages ----------
    var toast = document.getElementById('toast');
    if (toast) {
        var hideToast = function () {
            toast.classList.add('is-leaving');
            setTimeout(function () { toast.remove(); }, 320);
        };
        var timer = setTimeout(hideToast, toast.classList.contains('toast--error') ? 9000 : 5000);
        toast.querySelector('[data-toast-close]').addEventListener('click', function () { clearTimeout(timer); hideToast(); });
        toast.addEventListener('mouseenter', function () { clearTimeout(timer); });
    }

    // ---------- Chart tooltips ----------
    var tip = document.getElementById('tip');
    function showTip(parts, x, y) {
        tip.innerHTML = '';
        parts.forEach(function (text, i) {
            if (!text) return;
            var node = document.createElement(i === 0 ? 'strong' : (/^Click /.test(text) ? 'em' : 'span'));
            node.textContent = text;
            tip.appendChild(node);
        });
        tip.hidden = false;
        var w = tip.offsetWidth, h = tip.offsetHeight;
        var left = Math.min(window.innerWidth - w - 8, Math.max(8, x + 14));
        var top = y - h - 12;
        if (top < 8) top = y + 18;
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
    }
    function hideTip() { tip.hidden = true; }

    document.addEventListener('mousemove', function (ev) {
        var el = ev.target.closest && ev.target.closest('[data-tip]');
        if (el) showTip(el.getAttribute('data-tip').split('|'), ev.clientX, ev.clientY);
        else if (!ev.target.closest('[data-line], [data-donut] svg')) hideTip();
    });
    document.addEventListener('focusin', function (ev) {
        var el = ev.target.closest && ev.target.closest('[data-tip]');
        if (!el) return;
        var r = el.getBoundingClientRect();
        showTip(el.getAttribute('data-tip').split('|'), r.left + r.width / 2, r.top);
    });
    document.addEventListener('focusout', hideTip);
    window.addEventListener('scroll', hideTip, true);

    // ---------- Donut charts: hover a slice, click to filter, click the legend to hide a series ----------
    each('[data-donut]', function (wrap) {
        var circles = Array.prototype.slice.call(wrap.querySelectorAll('circle'));
        var legend = Array.prototype.slice.call(wrap.querySelectorAll('.legend li'));
        var big = wrap.querySelector('.donut-center strong');
        var small = wrap.querySelector('.donut-center span');
        var off = {};

        function visibleTotal() {
            return circles.reduce(function (sum, c) { return sum + (off[c.dataset.i] ? 0 : +c.dataset.value); }, 0);
        }
        function layout() {
            var total = visibleTotal(), acc = 0;
            circles.forEach(function (c) {
                var len = off[c.dataset.i] || !total ? 0 : +c.dataset.value / total * 100;
                c.style.strokeDasharray = len + ' ' + (100 - len);
                c.style.strokeDashoffset = 25 - acc;
                acc += len;
            });
        }
        function center(i) {
            var total = visibleTotal();
            if (i === null) {
                var hidden = Object.keys(off).some(function (k) { return off[k]; });
                big.textContent = hidden ? total.toLocaleString() : big.dataset.default;
                small.textContent = hidden ? 'shown' : small.dataset.default;
            } else {
                var c = circles[i];
                big.textContent = (total ? Math.round(+c.dataset.value / total * 100) : 0) + '%';
                small.textContent = c.dataset.label;
            }
            circles.forEach(function (c, n) {
                c.classList.toggle('is-hot', n === i);
                c.classList.toggle('is-dim', i !== null && n !== i);
            });
            legend.forEach(function (li, n) { li.classList.toggle('is-hot', n === i); });
        }
        function toggle(i) {
            var visible = circles.filter(function (c) { return !off[c.dataset.i]; }).length;
            if (!off[i] && visible <= 1) return; // keep at least one slice
            off[i] = !off[i];
            legend[i].classList.toggle('is-off', off[i]);
            layout();
            center(null);
        }

        circles.forEach(function (c, i) {
            c.addEventListener('mouseenter', function (ev) {
                center(i);
                showTip([c.dataset.label, (+c.dataset.value).toLocaleString() + ' · ' + big.textContent + ' of total', c.dataset.href ? 'Click to filter' : ''], ev.clientX, ev.clientY);
            });
            c.addEventListener('mousemove', function (ev) {
                tip.style.left = Math.min(window.innerWidth - tip.offsetWidth - 8, ev.clientX + 14) + 'px';
                tip.style.top = Math.max(8, ev.clientY - tip.offsetHeight - 12) + 'px';
            });
            c.addEventListener('mouseleave', function () { center(null); hideTip(); });
            if (c.dataset.href) {
                c.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    window.location.href = c.dataset.href;
                });
            }
        });
        legend.forEach(function (li, i) {
            li.addEventListener('mouseenter', function () { if (!off[i]) center(i); });
            li.addEventListener('mouseleave', function () { center(null); });
            li.addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); toggle(i); });
            li.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); toggle(i); }
            });
        });

        // Draw-in animation
        if (!calm) {
            circles.forEach(function (c) { c.style.strokeDasharray = '0 100'; });
            requestAnimationFrame(function () { requestAnimationFrame(layout); });
        }
    });

    // ---------- Line charts: follow the pointer to the nearest month ----------
    each('[data-line]', function (chart) {
        var dots = Array.prototype.slice.call(chart.querySelectorAll('.line-dot'));
        var guide = chart.querySelector('.line-guide');
        var current = null;
        function point(dot) {
            if (dot === current) return;
            current = dot;
            dots.forEach(function (d) { d.classList.toggle('is-hot', d === dot); });
            guide.hidden = !dot;
            if (!dot) { hideTip(); return; }
            guide.style.left = dot.dataset.x + '%';
            var r = dot.getBoundingClientRect();
            showTip([dot.dataset.label, dot.dataset.value + ' at-risk students', dot.dataset.change], r.left + r.width / 2, r.top);
        }
        chart.addEventListener('mousemove', function (ev) {
            var r = chart.getBoundingClientRect();
            var x = (ev.clientX - r.left) / r.width * 100;
            var best = dots[0];
            dots.forEach(function (d) {
                if (Math.abs(+d.dataset.x - x) < Math.abs(+best.dataset.x - x)) best = d;
            });
            point(best);
        });
        chart.addEventListener('mouseleave', function () { point(null); });
    });

    // ---------- Stacked bars: legend buttons hide or show a series ----------
    each('[data-series-toggle]', function (btn) {
        btn.addEventListener('click', function () {
            var on = btn.getAttribute('aria-pressed') !== 'true';
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            var scope = btn.closest('.panel') || document;
            each('[data-series="' + btn.getAttribute('data-series-toggle') + '"]', function (seg) { seg.hidden = !on; }, scope);
        });
    });

    // ---------- Numbers count up when the page opens ----------
    if (!calm) {
        each('.stat-value, .kpi-value, .metric-value, .eval-stats strong, .day-tab strong', function (el) {
            var text = el.textContent.trim();
            var m = /^([\d,]+)(\.\d+)?(%?)$/.exec(text);
            if (!m) return;
            var target = parseFloat((m[1] + (m[2] || '')).replace(/,/g, ''));
            var decimals = m[2] ? m[2].length - 1 : 0;
            if (!target) return;
            var start = null;
            function frame(now) {
                start = start || now;
                var t = Math.min(1, (now - start) / 700);
                var eased = 1 - Math.pow(1 - t, 3);
                el.textContent = (target * eased).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + m[3];
                if (t < 1) requestAnimationFrame(frame);
                else el.textContent = text;
            }
            requestAnimationFrame(frame);
        });
    }
})();
