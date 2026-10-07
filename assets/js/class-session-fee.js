/**
 * Live preview of ClassSessionFeeCalculator.
 * Integer cents only, so the numbers match the PHP service.
 * The saved amount is always recalculated on the server.
 */
(function (root) {
    'use strict';

    function config() {
        var raw = root.CLASS_SESSION_FEE_CONFIG || {};
        var institute = parseInt(raw.instituteFeeCents, 10);
        var inCollege = parseInt(raw.inCollegeFeeCents, 10);
        var bps = parseInt(raw.rateBps, 10);
        if (!isFinite(institute) || institute < 0) {
            institute = 50000;
        }
        if (!isFinite(inCollege) || inCollege < 0) {
            inCollege = 50000;
        }
        if (!isFinite(bps) || bps < 0) {
            bps = 600;
        }
        if (bps > 10000) {
            bps = 10000;
        }
        return { instituteFeeCents: institute, inCollegeFeeCents: inCollege, rateBps: bps };
    }

    function toCents(value) {
        var raw = String(value == null ? '' : value).trim().replace(/,/g, '');
        if (raw === '' || !/^-?\d+(\.\d+)?$/.test(raw)) {
            return 0;
        }
        var negative = raw.charAt(0) === '-';
        if (negative) {
            raw = raw.slice(1);
        }
        var parts = raw.split('.');
        var whole = parts[0];
        var frac = (parts[1] || '0') + '000';
        frac = frac.slice(0, 3);
        var cents = (parseInt(whole, 10) * 100) + parseInt(frac.slice(0, 2), 10);
        if (parseInt(frac.charAt(2), 10) >= 5) {
            cents += 1;
        }
        return negative ? -cents : cents;
    }

    function applyRate(cents, bps) {
        if (cents <= 0 || bps <= 0) {
            return 0;
        }
        if (bps > 10000) {
            bps = 10000;
        }
        var numerator = cents * bps;
        var quotient = Math.trunc(numerator / 10000);
        var remainder = numerator % 10000;
        if (remainder * 2 >= 10000) {
            quotient += 1;
        }
        return quotient;
    }

    function formatRs(cents) {
        var negative = cents < 0;
        var abs = Math.abs(cents);
        var whole = Math.trunc(abs / 100);
        var frac = String(abs % 100).padStart(2, '0');
        var grouped = String(whole).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return (negative ? '-Rs. ' : 'Rs. ') + grouped + '.' + frac;
    }

    function rateLabel(bps) {
        var pct = bps / 100;
        var rounded = Math.round(pct * 100) / 100;
        return String(rounded);
    }

    function parseGross(raw) {
        var text = String(raw == null ? '' : raw).trim().replace(/,/g, '');
        if (text === '') {
            return { valid: true, cents: 0 };
        }
        if (text.charAt(0) === '-' || !/^\d+(\.\d+)?$/.test(text)) {
            return { valid: false, cents: 0 };
        }
        return { valid: true, cents: toCents(text) };
    }

    function quote(raw, mode) {
        var cfg = config();
        var parsed = parseGross(raw);
        mode = String(mode || '').toLowerCase();
        var online = mode === 'online';
        var physical = mode === 'physical';
        var gross = parsed.valid ? parsed.cents : 0;
        var institute = 0;
        var handling = 0;
        var net = 0;
        if (parsed.valid && online) {
            institute = cfg.instituteFeeCents;
            handling = applyRate(gross, cfg.rateBps);
            net = gross - institute - handling;
        } else if (parsed.valid && physical) {
            institute = cfg.inCollegeFeeCents;
            handling = 0;
            net = gross - institute;
        }
        return {
            valid: parsed.valid,
            online: online,
            physical: physical,
            show: online || physical,
            gross: formatRs(gross),
            institute: formatRs(institute),
            txn: formatRs(handling),
            net: formatRs(net),
            payable: formatRs(gross),
            rateLabel: rateLabel(cfg.rateBps),
            grossCents: gross,
            instituteCents: institute,
            txnCents: handling,
            netCents: net
        };
    }

    function setText(card, key, value) {
        var node = card.querySelector('[data-fee="' + key + '"]');
        if (node) {
            node.textContent = value;
        }
    }

    function storedView(card) {
        var grossCents = toCents(card.dataset.storedGross || '0');
        var txnCents = toCents(card.dataset.storedTxn || '0');
        var label = rateLabel(config().rateBps);
        if (grossCents > 0 && txnCents >= 0) {
            var derived = Math.round((txnCents * 10000) / grossCents);
            label = rateLabel(derived);
        }
        var netCents = toCents(card.dataset.storedNet || '0');
        return {
            valid: true,
            gross: formatRs(grossCents),
            institute: formatRs(toCents(card.dataset.storedInstitute || '0')),
            txn: formatRs(txnCents),
            net: formatRs(netCents),
            grossCents: grossCents,
            netCents: netCents,
            rateLabel: label
        };
    }

    function selectedTeacherId(options) {
        if (!options || !options.teacherId) {
            return 0;
        }
        var teacher = document.getElementById(options.teacherId);
        if (!teacher) {
            return parseInt(options.teacherFallback || '0', 10) || 0;
        }
        return parseInt(teacher.value || '0', 10) || 0;
    }

    function syncBankNotice(card, mode) {
        var options = card && card._bankOptions ? card._bankOptions : null;
        var missing = document.getElementById(options && options.bankNoticeId ? options.bankNoticeId : '');
        var ready = document.getElementById(options && options.bankReadyId ? options.bankReadyId : '');
        if (!missing && !ready) {
            return;
        }
        var online = mode === 'online';
        var teacherId = selectedTeacherId(options);
        var map = options && options.bankMap ? options.bankMap : {};
        var summary = map[teacherId] || map[String(teacherId)] || null;
        var complete = !!(summary && (summary === true || summary.complete));
        var alreadyOnline = !!(options && options.alreadyOnline);
        if (missing) {
            missing.hidden = !(online && !complete && !alreadyOnline);
        }
        if (ready) {
            ready.hidden = !(online && complete);
            if (complete && summary && typeof summary === 'object') {
                fillBankLines(ready, summary);
            }
        }
        [options && options.bankLinkId, options && options.bankEditId].forEach(function (linkId) {
            var link = linkId ? document.getElementById(linkId) : null;
            if (!link || !options) {
                return;
            }
            var base = options.bankUrl || link.getAttribute('href') || '#';
            if (options.bankUrlNeedsTeacher) {
                link.setAttribute('href', base + encodeURIComponent(String(teacherId)));
            }
        });
    }

    function fillBankLines(ready, summary) {
        var box = ready.querySelector('[data-bank-lines]');
        if (!box) {
            return;
        }
        var rows = [
            ['Bank', summary.bank_name],
            ['Account holder', summary.holder],
            ['Account', summary.account_mask],
            ['Branch', summary.branch],
            ['Branch code', summary.branch_code || 'Not provided'],
            ['Account type', summary.account_type]
        ];
        box.textContent = '';
        rows.forEach(function (row) {
            var line = document.createElement('div');
            var label = document.createElement('span');
            var amount = document.createElement('strong');
            label.textContent = row[0];
            amount.textContent = row[1] || '';
            line.appendChild(label);
            line.appendChild(amount);
            box.appendChild(line);
        });
    }

    function render(fee, modeEl, card) {
        if (!fee || !card) {
            return;
        }
        var mode = modeEl ? modeEl.value : (card.dataset.mode || 'physical');
        mode = String(mode || '').toLowerCase();
        var online = mode === 'online';
        var physical = mode === 'physical';
        card.hidden = !(online || physical);
        syncBankNotice(card, mode);
        if (!(online || physical)) {
            return;
        }
        var storedRule = physical ? 'in_college_v1' : 'online_v1';
        var unchanged = card.dataset.storedRule === storedRule
            && String(fee.value).trim() !== ''
            && toCents(fee.value) === toCents(card.dataset.storedGross || '')
            && String(mode) === String(card.dataset.storedMode || '');
        var view = unchanged ? storedView(card) : quote(fee.value, mode);
        view.online = online;
        view.physical = physical;
        var body = card.querySelector('[data-fee-body]');
        var empty = card.querySelector('[data-fee-empty]');
        var warn = card.querySelector('[data-fee="warn"]');
        var note = card.querySelector('[data-fee="note"]');
        var intro = card.querySelector('[data-fee="intro"]');
        var title = card.querySelector('[data-fee="title"]');
        if (title) {
            title.textContent = online ? 'Online Class Fee Breakdown' : 'In-college Class Fee Breakdown';
        }
        var instituteLabel = card.querySelector('[data-fee-label="institute"]');
        if (instituteLabel) {
            instituteLabel.textContent = online ? 'Institute Online Class Fee' : 'Institute Fee';
        }
        var txnRow = card.querySelector('[data-fee-row="txn"]');
        if (txnRow) {
            txnRow.hidden = !online;
            txnRow.style.display = online ? '' : 'none';
        }
        if (intro) {
            intro.textContent = online
                ? 'This is an online class. The institute fee and the ' + view.rateLabel + '% handling fee are taken from the class fee you enter. The student pays the class fee.'
                : 'This is an in-college class. The institute fee is taken from the class fee you enter. There is no transaction fee.';
        }
        if (!view.valid) {
            if (body) body.hidden = true;
            if (empty) empty.hidden = true;
            if (warn) {
                warn.hidden = false;
                warn.textContent = 'Enter a class fee of 0 or more. Letters and negative amounts are not used.';
            }
            if (note) note.hidden = true;
            return;
        }
        if (warn) warn.hidden = true;
        if (note) note.hidden = false;
        var grossCents = typeof view.grossCents === 'number' ? view.grossCents : toCents(view.gross);
        if (grossCents <= 0) {
            if (body) body.hidden = true;
            if (empty) empty.hidden = false;
            return;
        }
        if (empty) empty.hidden = true;
        if (body) body.hidden = false;
        setText(card, 'gross', view.gross);
        setText(card, 'institute', view.institute);
        setText(card, 'txn', view.txn);
        setText(card, 'net', view.net);
        var txnLabel = card.querySelector('[data-fee-label="txn"]');
        if (txnLabel) {
            txnLabel.textContent = 'Transaction & Handling Fee (' + view.rateLabel + '%)';
        }
        var netRow = card.querySelector('[data-fee-row="net"]');
        if (netRow) {
            netRow.classList.toggle('is-negative', (view.netCents || 0) < 0);
        }
    }

    function mount(options) {
        var fee = document.getElementById(options.feeId);
        var modeEl = options.modeId ? document.getElementById(options.modeId) : null;
        var card = document.getElementById(options.cardId);
        if (!fee || !card) {
            return null;
        }
        card._bankOptions = options || {};
        var draw = function () {
            render(fee, modeEl, card);
        };
        if (fee.dataset.feeBound !== '1') {
            fee.dataset.feeBound = '1';
            ['input', 'change', 'keyup', 'blur'].forEach(function (eventName) {
                fee.addEventListener(eventName, draw);
            });
            fee.addEventListener('paste', function () {
                setTimeout(draw, 0);
            });
            if (modeEl) {
                modeEl.addEventListener('change', draw);
                modeEl.addEventListener('input', draw);
            }
            var teacher = options.teacherId ? document.getElementById(options.teacherId) : null;
            if (teacher) {
                teacher.addEventListener('change', draw);
            }
        }
        draw();
        return { refresh: draw };
    }

    root.ClassSessionFee = {
        quote: quote,
        toCents: toCents,
        formatRs: formatRs,
        applyRate: applyRate,
        mount: mount
    };
}(typeof globalThis !== 'undefined' ? globalThis : this));
