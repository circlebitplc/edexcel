/**
 * Educational tools layer for LiveKit classroom whiteboard.
 * Attaches via window.CKBoardEdu.mount(api, hooks) — does not rewrite the board engine.
 */
(function (global) {
    'use strict';

    var FAV_KEY = 'ck-edu-favs';
    var RECENT_KEY = 'ck-edu-recent';
    var SNAP_PREFIX = 'ck-board-snap-';
    var SUBJECT_ORDER = {
        general: ['teach', 'util', 'math', 'chem', 'phys'],
        math: ['math', 'teach', 'util', 'phys', 'chem'],
        chem: ['chem', 'teach', 'util', 'math', 'phys'],
        phys: ['phys', 'teach', 'util', 'math', 'chem']
    };
    var CAT_META = {
        math: { id: 'math', label: 'Mathematics' },
        chem: { id: 'chem', label: 'Chemistry' },
        phys: { id: 'phys', label: 'Physics' },
        teach: { id: 'teach', label: 'Teaching' },
        util: { id: 'util', label: 'Utilities' }
    };

    var PERIODIC = [
        { z: 1, s: 'H', n: 'Hydrogen', g: 1, p: 1 },
        { z: 2, s: 'He', n: 'Helium', g: 18, p: 1 },
        { z: 3, s: 'Li', n: 'Lithium', g: 1, p: 2 },
        { z: 4, s: 'Be', n: 'Beryllium', g: 2, p: 2 },
        { z: 5, s: 'B', n: 'Boron', g: 13, p: 2 },
        { z: 6, s: 'C', n: 'Carbon', g: 14, p: 2 },
        { z: 7, s: 'N', n: 'Nitrogen', g: 15, p: 2 },
        { z: 8, s: 'O', n: 'Oxygen', g: 16, p: 2 },
        { z: 9, s: 'F', n: 'Fluorine', g: 17, p: 2 },
        { z: 10, s: 'Ne', n: 'Neon', g: 18, p: 2 },
        { z: 11, s: 'Na', n: 'Sodium', g: 1, p: 3 },
        { z: 12, s: 'Mg', n: 'Magnesium', g: 2, p: 3 },
        { z: 13, s: 'Al', n: 'Aluminium', g: 13, p: 3 },
        { z: 14, s: 'Si', n: 'Silicon', g: 14, p: 3 },
        { z: 15, s: 'P', n: 'Phosphorus', g: 15, p: 3 },
        { z: 16, s: 'S', n: 'Sulfur', g: 16, p: 3 },
        { z: 17, s: 'Cl', n: 'Chlorine', g: 17, p: 3 },
        { z: 18, s: 'Ar', n: 'Argon', g: 18, p: 3 },
        { z: 19, s: 'K', n: 'Potassium', g: 1, p: 4 },
        { z: 20, s: 'Ca', n: 'Calcium', g: 2, p: 4 },
        { z: 26, s: 'Fe', n: 'Iron', g: 8, p: 4 },
        { z: 29, s: 'Cu', n: 'Copper', g: 11, p: 4 },
        { z: 30, s: 'Zn', n: 'Zinc', g: 12, p: 4 },
        { z: 35, s: 'Br', n: 'Bromine', g: 17, p: 4 },
        { z: 47, s: 'Ag', n: 'Silver', g: 11, p: 5 },
        { z: 53, s: 'I', n: 'Iodine', g: 17, p: 5 },
        { z: 79, s: 'Au', n: 'Gold', g: 11, p: 6 }
    ];

    var ATOMIC_MASS = {
        H: 1.008, He: 4.003, Li: 6.94, Be: 9.012, B: 10.81, C: 12.01, N: 14.01, O: 16.00,
        F: 19.00, Ne: 20.18, Na: 22.99, Mg: 24.31, Al: 26.98, Si: 28.09, P: 30.97, S: 32.07,
        Cl: 35.45, Ar: 39.95, K: 39.10, Ca: 40.08, Fe: 55.85, Cu: 63.55, Zn: 65.38,
        Br: 79.90, Ag: 107.87, I: 126.90, Au: 196.97
    };

    var MATH_SYMBOLS = [
        '±', '×', '÷', '√', '∞', '≈', '≠', '≤', '≥', '°', 'π', 'θ', 'Δ', 'Σ', '∫',
        '∂', 'α', 'β', 'γ', 'λ', 'μ', 'ω', '∈', '∉', '⊂', '∪', '∩', '→', '⇒', '⇔',
        '²', '³', '₀', '₁', '₂', '½', '⅓', '¼', '∠', '⊥', '∥', '∴', '∵'
    ];

    function el(tag, cls, attrs) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'text') n.textContent = attrs[k];
                else if (k === 'html') n.innerHTML = attrs[k];
                else n.setAttribute(k, attrs[k]);
            });
        }
        return n;
    }

    function readJson(key, fallback) {
        try {
            var raw = localStorage.getItem(key);
            if (!raw) return fallback;
            var v = JSON.parse(raw);
            return v == null ? fallback : v;
        } catch (e) {
            return fallback;
        }
    }

    function writeJson(key, val) {
        try { localStorage.setItem(key, JSON.stringify(val)); } catch (e) { /* ignore */ }
    }

    function clamp(n, a, b) {
        return Math.max(a, Math.min(b, n));
    }

    function toastSafe(api, msg) {
        if (api && typeof api.toast === 'function') api.toast(msg);
    }

    function canPlace(api) {
        if (!api) return false;
        if (typeof api.canDraw === 'function') return !!api.canDraw();
        return true;
    }

    function centerPlace(api, kind, w, h, data, extra) {
        if (!canPlace(api)) {
            toastSafe(api, 'Drawing is locked');
            return null;
        }
        var page = typeof api.getPage === 'function' ? api.getPage() : 0;
        var obj = {
            tool: 'edu',
            kind: kind,
            id: api.uid ? api.uid() : ('e' + Date.now()),
            x: 80 + Math.round(Math.random() * 120),
            y: 60 + Math.round(Math.random() * 80),
            w: w || 160,
            h: h || 100,
            color: (api.getColor && api.getColor()) || '#111827',
            data: data || {},
            page: page
        };
        if (extra) {
            Object.keys(extra).forEach(function (k) { obj[k] = extra[k]; });
        }
        if (typeof api.placeEdu === 'function') api.placeEdu(obj);
        else if (typeof api.commitOp === 'function') api.commitOp(obj);
        return obj;
    }

    function pushRecent(id) {
        var list = readJson(RECENT_KEY, []);
        list = list.filter(function (x) { return x !== id; });
        list.unshift(id);
        if (list.length > 12) list = list.slice(0, 12);
        writeJson(RECENT_KEY, list);
    }

    function toggleFav(id) {
        var list = readJson(FAV_KEY, []);
        var i = list.indexOf(id);
        if (i >= 0) list.splice(i, 1);
        else list.unshift(id);
        writeJson(FAV_KEY, list);
        return list;
    }

    function isFav(id) {
        return readJson(FAV_KEY, []).indexOf(id) >= 0;
    }

    /* ---------- expression / graph helpers ---------- */

    function normalizeExpr(s) {
        s = String(s || '').toLowerCase().replace(/\s+/g, '');
        s = s.replace(/\^/g, '**');
        s = s.replace(/(\d)(x)/g, '$1*$2');
        s = s.replace(/sin/g, 'Math.sin').replace(/cos/g, 'Math.cos')
            .replace(/tan/g, 'Math.tan').replace(/abs/g, 'Math.abs')
            .replace(/sqrt/g, 'Math.sqrt');
        return s;
    }

    function evalExpr(expr, x) {
        try {
            var fn = new Function('x', 'return (' + expr + ');');
            var y = fn(x);
            if (typeof y !== 'number' || !isFinite(y)) return null;
            return y;
        } catch (e) {
            return null;
        }
    }

    function sampleGraph(exprRaw, n) {
        var expr = normalizeExpr(exprRaw);
        var pts = [];
        var i;
        for (i = 0; i <= n; i++) {
            var x = -5 + (10 * i) / n;
            var y = evalExpr(expr, x);
            pts.push({ x: x, y: y });
        }
        return pts;
    }

    /* ---------- chem helpers ---------- */

    function formatChemEq(raw) {
        return String(raw || '').replace(/(\d+)/g, function (m) {
            var map = { '0': '₀', '1': '₁', '2': '₂', '3': '₃', '4': '₄', '5': '₅', '6': '₆', '7': '₇', '8': '₈', '9': '₉' };
            return m.split('').map(function (c) { return map[c] || c; }).join('');
        });
    }

    function parseMolarMass(formula) {
        var re = /([A-Z][a-z]?)(\d*)/g;
        var m;
        var total = 0;
        var ok = false;
        while ((m = re.exec(formula))) {
            var mass = ATOMIC_MASS[m[1]];
            if (mass == null) return null;
            total += mass * (m[2] ? parseInt(m[2], 10) : 1);
            ok = true;
        }
        return ok ? Math.round(total * 1000) / 1000 : null;
    }

    function tryBalanceTip(eq) {
        var s = String(eq || '').replace(/\s+/g, '');
        if (/H2\+O2->H2O|H2\+O2=H2O/i.test(s)) {
            return { balanced: '2H₂ + O₂ → 2H₂O', tip: 'Balance O by doubling H₂O, then double H₂.' };
        }
        if (/N2\+H2->NH3|N2\+H2=NH3/i.test(s)) {
            return { balanced: 'N₂ + 3H₂ → 2NH₃', tip: 'Balance N first, then H.' };
        }
        if (/Fe\+O2->Fe2O3|Fe\+O2=Fe2O3/i.test(s)) {
            return { balanced: '4Fe + 3O₂ → 2Fe₂O₃', tip: 'Balance Fe and O with lowest common multiples.' };
        }
        return {
            balanced: null,
            tip: 'Count atoms on each side. Adjust coefficients (not subscripts). Start with elements that appear once.'
        };
    }

    /* ---------- canvas drawing ---------- */

    function roundRect(ctx, x, y, w, h, r) {
        r = Math.min(r || 6, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function cardBg(ctx, obj, fill) {
        var x = obj.x || 0;
        var y = obj.y || 0;
        var w = obj.w || 120;
        var h = obj.h || 80;
        ctx.save();
        roundRect(ctx, x, y, w, h, 8);
        ctx.fillStyle = fill || '#ffffff';
        ctx.fill();
        ctx.strokeStyle = obj.color || '#334155';
        ctx.lineWidth = 1.5;
        ctx.stroke();
        ctx.restore();
        return { x: x, y: y, w: w, h: h };
    }

    function drawLabel(ctx, text, x, y, opts) {
        opts = opts || {};
        ctx.save();
        ctx.fillStyle = opts.color || '#0f172a';
        ctx.font = (opts.bold ? '600 ' : '') + (opts.size || 13) + 'px "Segoe UI", system-ui, sans-serif';
        ctx.textAlign = opts.align || 'left';
        ctx.textBaseline = opts.base || 'top';
        ctx.fillText(String(text || ''), x, y);
        ctx.restore();
    }

    function drawHiddenPlaceholder(ctx, obj, api) {
        var b = cardBg(ctx, obj, '#f1f5f9');
        ctx.save();
        if (api && api.isHost && api.isHost()) {
            ctx.setLineDash([6, 4]);
            ctx.strokeStyle = '#64748b';
            ctx.strokeRect(b.x + 2, b.y + 2, b.w - 4, b.h - 4);
            ctx.globalAlpha = 0.35;
            drawEduContent(ctx, obj, api, true);
            ctx.globalAlpha = 1;
            drawLabel(ctx, 'Hidden (host)', b.x + 8, b.y + 8, { size: 11, color: '#475569', bold: true });
        } else {
            ctx.fillStyle = 'rgba(148,163,184,0.55)';
            ctx.fillRect(b.x + 4, b.y + 4, b.w - 8, b.h - 8);
            drawLabel(ctx, 'Hidden', b.x + b.w / 2, b.y + b.h / 2 - 8, {
                size: 14, color: '#475569', bold: true, align: 'center', base: 'middle'
            });
        }
        ctx.restore();
    }

    function drawAxesIn(ctx, x, y, w, h) {
        ctx.save();
        ctx.strokeStyle = '#94a3b8';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(x + 8, y + h / 2);
        ctx.lineTo(x + w - 8, y + h / 2);
        ctx.moveTo(x + w / 2, y + 8);
        ctx.lineTo(x + w / 2, y + h - 8);
        ctx.stroke();
        ctx.restore();
    }

    function drawGraphKind(ctx, obj) {
        var b = cardBg(ctx, obj, '#fafafa');
        var expr = (obj.data && obj.data.expr) || 'x';
        drawLabel(ctx, 'y = ' + expr, b.x + 8, b.y + 6, { size: 11, color: '#64748b' });
        var gx = b.x + 6;
        var gy = b.y + 22;
        var gw = b.w - 12;
        var gh = b.h - 30;
        drawAxesIn(ctx, gx, gy, gw, gh);
        var pts = sampleGraph(expr, 80);
        var i;
        var started = false;
        ctx.save();
        ctx.beginPath();
        ctx.strokeStyle = obj.color || '#2563eb';
        ctx.lineWidth = 2;
        for (i = 0; i < pts.length; i++) {
            if (pts[i].y == null) { started = false; continue; }
            var px = gx + ((pts[i].x + 5) / 10) * gw;
            var py = gy + gh / 2 - (pts[i].y / 10) * gh;
            py = clamp(py, gy + 2, gy + gh - 2);
            if (!started) { ctx.moveTo(px, py); started = true; }
            else ctx.lineTo(px, py);
        }
        ctx.stroke();
        ctx.restore();
    }

    function drawCoords(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        drawAxesIn(ctx, b.x, b.y, b.w, b.h);
        drawLabel(ctx, 'x', b.x + b.w - 16, b.y + b.h / 2 + 4, { size: 11, color: '#64748b' });
        drawLabel(ctx, 'y', b.x + b.w / 2 + 4, b.y + 8, { size: 11, color: '#64748b' });
    }

    function drawNumberline(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        var y = b.y + b.h / 2;
        var x0 = b.x + 16;
        var x1 = b.x + b.w - 16;
        ctx.save();
        ctx.strokeStyle = obj.color || '#0f172a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(x0, y);
        ctx.lineTo(x1, y);
        ctx.stroke();
        var min = (obj.data && obj.data.min != null) ? obj.data.min : -5;
        var max = (obj.data && obj.data.max != null) ? obj.data.max : 5;
        var n = max - min;
        var i;
        for (i = 0; i <= n; i++) {
            var t = i / n;
            var x = x0 + t * (x1 - x0);
            ctx.beginPath();
            ctx.moveTo(x, y - 8);
            ctx.lineTo(x, y + 8);
            ctx.stroke();
            drawLabel(ctx, String(min + i), x, y + 12, { size: 10, align: 'center', color: '#475569' });
        }
        ctx.restore();
    }

    function drawRuler(ctx, obj) {
        var b = cardBg(ctx, obj, '#fefce8');
        ctx.save();
        ctx.strokeStyle = obj.color || '#854d0e';
        ctx.lineWidth = 1;
        var i;
        var ticks = 10;
        for (i = 0; i <= ticks; i++) {
            var x = b.x + 10 + (i / ticks) * (b.w - 20);
            var h = i % 5 === 0 ? 18 : 10;
            ctx.beginPath();
            ctx.moveTo(x, b.y + 8);
            ctx.lineTo(x, b.y + 8 + h);
            ctx.stroke();
            if (i % 5 === 0) drawLabel(ctx, String(i), x, b.y + 30, { size: 10, align: 'center' });
        }
        drawLabel(ctx, 'Ruler', b.x + 8, b.y + b.h - 18, { size: 11, color: '#a16207' });
        ctx.restore();
    }

    function drawProtractor(ctx, obj) {
        var b = cardBg(ctx, obj, '#eff6ff');
        var cx = b.x + b.w / 2;
        var cy = b.y + b.h - 14;
        var r = Math.min(b.w, b.h) * 0.42;
        ctx.save();
        ctx.strokeStyle = obj.color || '#1d4ed8';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(cx, cy, r, Math.PI, 0, false);
        ctx.stroke();
        var a;
        for (a = 0; a <= 180; a += 15) {
            var rad = Math.PI + (a * Math.PI) / 180;
            var len = a % 30 === 0 ? 12 : 7;
            ctx.beginPath();
            ctx.moveTo(cx + Math.cos(rad) * r, cy + Math.sin(rad) * r);
            ctx.lineTo(cx + Math.cos(rad) * (r - len), cy + Math.sin(rad) * (r - len));
            ctx.stroke();
        }
        drawLabel(ctx, 'Protractor', b.x + 8, b.y + 6, { size: 11, color: '#1e40af' });
        ctx.restore();
    }

    function drawCompass(ctx, obj) {
        var b = cardBg(ctx, obj, '#f0fdf4');
        var cx = b.x + b.w / 2;
        var cy = b.y + b.h / 2;
        ctx.save();
        ctx.strokeStyle = obj.color || '#15803d';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(cx, cy, Math.min(b.w, b.h) * 0.28, 0, Math.PI * 2);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.lineTo(cx + 28, cy + 36);
        ctx.stroke();
        drawLabel(ctx, 'Compass', b.x + 8, b.y + 6, { size: 11, color: '#166534' });
        ctx.restore();
    }

    function drawTriangle(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        ctx.save();
        ctx.strokeStyle = obj.color || '#0f172a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(b.x + b.w / 2, b.y + 16);
        ctx.lineTo(b.x + 16, b.y + b.h - 16);
        ctx.lineTo(b.x + b.w - 16, b.y + b.h - 16);
        ctx.closePath();
        ctx.stroke();
        ctx.restore();
    }

    function drawPolygon(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        var sides = (obj.data && obj.data.sides) || 5;
        var cx = b.x + b.w / 2;
        var cy = b.y + b.h / 2;
        var r = Math.min(b.w, b.h) * 0.32;
        ctx.save();
        ctx.strokeStyle = obj.color || '#0f172a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        var i;
        for (i = 0; i < sides; i++) {
            var a = -Math.PI / 2 + (i * 2 * Math.PI) / sides;
            var px = cx + Math.cos(a) * r;
            var py = cy + Math.sin(a) * r;
            if (i === 0) ctx.moveTo(px, py);
            else ctx.lineTo(px, py);
        }
        ctx.closePath();
        ctx.stroke();
        ctx.restore();
    }

    function drawAngle(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        var deg = (obj.data && obj.data.deg) || 45;
        var cx = b.x + 28;
        var cy = b.y + b.h - 24;
        ctx.save();
        ctx.strokeStyle = obj.color || '#0f172a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(cx + 70, cy);
        ctx.lineTo(cx, cy);
        ctx.lineTo(cx + 70 * Math.cos((-deg * Math.PI) / 180), cy + 70 * Math.sin((-deg * Math.PI) / 180));
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(cx, cy, 18, 0, (-deg * Math.PI) / 180, true);
        ctx.stroke();
        drawLabel(ctx, deg + '°', cx + 24, cy - 28, { size: 12 });
        ctx.restore();
    }

    function drawFraction(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff7ed');
        var a = (obj.data && obj.data.num) || 1;
        var d = (obj.data && obj.data.den) || 2;
        drawLabel(ctx, String(a), b.x + b.w / 2, b.y + b.h / 2 - 18, {
            size: 22, bold: true, align: 'center', base: 'middle'
        });
        ctx.save();
        ctx.strokeStyle = obj.color || '#9a3412';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(b.x + b.w * 0.3, b.y + b.h / 2);
        ctx.lineTo(b.x + b.w * 0.7, b.y + b.h / 2);
        ctx.stroke();
        ctx.restore();
        drawLabel(ctx, String(d), b.x + b.w / 2, b.y + b.h / 2 + 18, {
            size: 22, bold: true, align: 'center', base: 'middle'
        });
    }

    function drawTable(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        var rows = (obj.data && obj.data.rows) || 3;
        var cols = (obj.data && obj.data.cols) || 3;
        ctx.save();
        ctx.strokeStyle = obj.color || '#64748b';
        ctx.lineWidth = 1;
        var r, c;
        for (r = 0; r <= rows; r++) {
            var y = b.y + 10 + (r / rows) * (b.h - 20);
            ctx.beginPath();
            ctx.moveTo(b.x + 10, y);
            ctx.lineTo(b.x + b.w - 10, y);
            ctx.stroke();
        }
        for (c = 0; c <= cols; c++) {
            var x = b.x + 10 + (c / cols) * (b.w - 20);
            ctx.beginPath();
            ctx.moveTo(x, b.y + 10);
            ctx.lineTo(x, b.y + b.h - 10);
            ctx.stroke();
        }
        ctx.restore();
    }

    function drawResultCard(ctx, obj, title) {
        var b = cardBg(ctx, obj, '#f8fafc');
        drawLabel(ctx, title || ((obj.data && obj.data.title) || 'Result'), b.x + 10, b.y + 8, {
            size: 11, color: '#64748b', bold: true
        });
        drawLabel(ctx, (obj.data && obj.data.text) || '', b.x + 10, b.y + 28, {
            size: 14, color: obj.color || '#0f172a'
        });
        if (obj.data && obj.data.sub) {
            drawLabel(ctx, obj.data.sub, b.x + 10, b.y + 50, { size: 11, color: '#64748b' });
        }
    }

    function drawEquation(ctx, obj) {
        var b = cardBg(ctx, obj, '#fefefe');
        drawLabel(ctx, (obj.data && obj.data.latex) || (obj.data && obj.data.text) || 'y = …', b.x + 12, b.y + b.h / 2 - 8, {
            size: 16, bold: true, color: obj.color || '#0f172a', base: 'middle'
        });
    }

    function drawElement(ctx, obj) {
        var d = obj.data || {};
        var b = cardBg(ctx, obj, '#ecfeff');
        drawLabel(ctx, String(d.z || ''), b.x + 8, b.y + 6, { size: 10, color: '#0e7490' });
        drawLabel(ctx, d.symbol || '?', b.x + b.w / 2, b.y + b.h / 2 - 4, {
            size: 28, bold: true, align: 'center', base: 'middle', color: obj.color || '#155e75'
        });
        drawLabel(ctx, d.name || '', b.x + b.w / 2, b.y + b.h - 18, {
            size: 11, align: 'center', color: '#0e7490'
        });
    }

    function drawMolecule(ctx, obj) {
        var b = cardBg(ctx, obj, '#f0fdfa');
        var name = (obj.data && obj.data.formula) || 'H₂O';
        drawLabel(ctx, name, b.x + b.w / 2, b.y + b.h / 2 - 6, {
            size: 22, bold: true, align: 'center', base: 'middle', color: obj.color || '#0f766e'
        });
        drawLabel(ctx, (obj.data && obj.data.label) || 'Molecule', b.x + b.w / 2, b.y + b.h - 18, {
            size: 11, align: 'center', color: '#115e59'
        });
    }

    function drawLewis(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        var sym = (obj.data && obj.data.symbol) || 'C';
        drawLabel(ctx, '·  ' + sym + '  ·', b.x + b.w / 2, b.y + b.h / 2, {
            size: 18, bold: true, align: 'center', base: 'middle'
        });
        drawLabel(ctx, 'Lewis', b.x + 8, b.y + 6, { size: 10, color: '#64748b' });
    }

    function drawBond(ctx, obj) {
        var b = { x: obj.x || 0, y: obj.y || 0, w: obj.w || 80, h: obj.h || 24 };
        var kind = (obj.data && obj.data.bond) || 'single';
        ctx.save();
        ctx.strokeStyle = obj.color || '#0f172a';
        ctx.lineWidth = 2;
        var mid = b.y + b.h / 2;
        var x0 = b.x + 8;
        var x1 = b.x + b.w - 8;
        function line(oy) {
            ctx.beginPath();
            ctx.moveTo(x0, mid + oy);
            ctx.lineTo(x1, mid + oy);
            ctx.stroke();
        }
        if (kind === 'triple') { line(-5); line(0); line(5); }
        else if (kind === 'double') { line(-3); line(3); }
        else if (kind === 'wedge') {
            ctx.beginPath();
            ctx.moveTo(x0, mid);
            ctx.lineTo(x1, mid - 8);
            ctx.lineTo(x1, mid + 8);
            ctx.closePath();
            ctx.fillStyle = obj.color || '#0f172a';
            ctx.fill();
        } else if (kind === 'dash') {
            ctx.setLineDash([4, 4]);
            line(0);
        } else line(0);
        ctx.restore();
    }

    function drawBenzene(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        var cx = b.x + b.w / 2;
        var cy = b.y + b.h / 2;
        var r = Math.min(b.w, b.h) * 0.3;
        ctx.save();
        ctx.strokeStyle = obj.color || '#0f172a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        var i;
        for (i = 0; i < 6; i++) {
            var a = -Math.PI / 2 + (i * Math.PI) / 3;
            var px = cx + Math.cos(a) * r;
            var py = cy + Math.sin(a) * r;
            if (i === 0) ctx.moveTo(px, py);
            else ctx.lineTo(px, py);
        }
        ctx.closePath();
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(cx, cy, r * 0.55, 0, Math.PI * 2);
        ctx.stroke();
        ctx.restore();
    }

    function drawReactionArrow(ctx, obj) {
        var x = obj.x || 0;
        var y = (obj.y || 0) + (obj.h || 24) / 2;
        var w = obj.w || 90;
        ctx.save();
        ctx.strokeStyle = obj.color || '#0f172a';
        ctx.fillStyle = obj.color || '#0f172a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x + w - 10, y);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(x + w - 10, y - 6);
        ctx.lineTo(x + w, y);
        ctx.lineTo(x + w - 10, y + 6);
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }

    function drawCharge(ctx, obj) {
        var b = cardBg(ctx, obj, '#fef2f2');
        drawLabel(ctx, (obj.data && obj.data.charge) || '+', b.x + b.w / 2, b.y + b.h / 2, {
            size: 22, bold: true, align: 'center', base: 'middle', color: '#b91c1c'
        });
    }

    function drawPhScale(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        var colors = ['#dc2626', '#ea580c', '#f59e0b', '#84cc16', '#22c55e', '#14b8a6', '#0ea5e9', '#3b82f6', '#6366f1', '#7c3aed', '#a855f7', '#db2777', '#be123c', '#881337'];
        var i;
        var seg = (b.w - 16) / colors.length;
        for (i = 0; i < colors.length; i++) {
            ctx.fillStyle = colors[i];
            ctx.fillRect(b.x + 8 + i * seg, b.y + b.h / 2 - 10, seg - 1, 20);
        }
        drawLabel(ctx, 'pH 0 → 14', b.x + 8, b.y + 8, { size: 11, color: '#64748b' });
    }

    function drawLab(ctx, obj) {
        var kind = (obj.data && obj.data.equip) || obj.kind || 'beaker';
        var b = cardBg(ctx, obj, '#f8fafc');
        ctx.save();
        ctx.strokeStyle = obj.color || '#334155';
        ctx.lineWidth = 2;
        var x = b.x + b.w * 0.25;
        var y = b.y + 20;
        var w = b.w * 0.5;
        var h = b.h - 40;
        if (kind === 'testtube') {
            roundRect(ctx, x + w * 0.3, y, w * 0.4, h, 8);
            ctx.stroke();
        } else if (kind === 'flask') {
            ctx.beginPath();
            ctx.moveTo(x + w * 0.35, y);
            ctx.lineTo(x + w * 0.65, y);
            ctx.lineTo(x + w, y + h);
            ctx.lineTo(x, y + h);
            ctx.closePath();
            ctx.stroke();
        } else if (kind === 'burette' || kind === 'pipette' || kind === 'cylinder') {
            ctx.strokeRect(x + w * 0.35, y, w * 0.3, h);
            ctx.beginPath();
            ctx.moveTo(x + w * 0.35, y + h * 0.3);
            ctx.lineTo(x + w * 0.65, y + h * 0.3);
            ctx.stroke();
        } else {
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x + w, y);
            ctx.lineTo(x + w * 0.9, y + h);
            ctx.lineTo(x + w * 0.1, y + h);
            ctx.closePath();
            ctx.stroke();
        }
        drawLabel(ctx, kind, b.x + 8, b.y + 4, { size: 10, color: '#64748b' });
        ctx.restore();
    }

    function drawVector(ctx, obj) {
        var x = obj.x || 0;
        var y = obj.y || 0;
        var w = obj.w || 100;
        var h = obj.h || 40;
        ctx.save();
        ctx.strokeStyle = obj.color || '#2563eb';
        ctx.fillStyle = obj.color || '#2563eb';
        ctx.lineWidth = 2.5;
        ctx.beginPath();
        ctx.moveTo(x, y + h);
        ctx.lineTo(x + w - 12, y + 8);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(x + w - 12, y + 8);
        ctx.lineTo(x + w - 22, y + 18);
        ctx.lineTo(x + w - 4, y + 14);
        ctx.closePath();
        ctx.fill();
        drawLabel(ctx, (obj.data && obj.data.label) || 'F', x + w - 8, y, { size: 12, color: obj.color || '#1d4ed8' });
        ctx.restore();
    }

    function drawFbd(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        var cx = b.x + b.w / 2;
        var cy = b.y + b.h / 2;
        ctx.save();
        ctx.fillStyle = '#e2e8f0';
        ctx.fillRect(cx - 18, cy - 18, 36, 36);
        ctx.strokeStyle = obj.color || '#0f172a';
        ctx.strokeRect(cx - 18, cy - 18, 36, 36);
        function arrow(x2, y2, label) {
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.lineTo(x2, y2);
            ctx.stroke();
            drawLabel(ctx, label, x2, y2 - 4, { size: 10 });
        }
        arrow(cx, cy - 50, 'N');
        arrow(cx, cy + 50, 'W');
        arrow(cx + 50, cy, 'F');
        drawLabel(ctx, 'FBD', b.x + 8, b.y + 6, { size: 11, color: '#64748b' });
        ctx.restore();
    }

    function drawMotion(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        ctx.save();
        ctx.strokeStyle = obj.color || '#7c3aed';
        ctx.lineWidth = 2;
        ctx.beginPath();
        var i;
        for (i = 0; i <= 40; i++) {
            var t = i / 40;
            var px = b.x + 12 + t * (b.w - 24);
            var py = b.y + b.h - 16 - Math.sin(t * Math.PI) * (b.h - 36);
            if (i === 0) ctx.moveTo(px, py);
            else ctx.lineTo(px, py);
        }
        ctx.stroke();
        drawLabel(ctx, 'Trajectory', b.x + 8, b.y + 6, { size: 11, color: '#6d28d9' });
        ctx.restore();
    }

    function drawWave(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        ctx.save();
        ctx.strokeStyle = obj.color || '#0891b2';
        ctx.lineWidth = 2;
        ctx.beginPath();
        var i;
        for (i = 0; i <= 60; i++) {
            var t = i / 60;
            var px = b.x + 10 + t * (b.w - 20);
            var py = b.y + b.h / 2 + Math.sin(t * Math.PI * 4) * (b.h * 0.28);
            if (i === 0) ctx.moveTo(px, py);
            else ctx.lineTo(px, py);
        }
        ctx.stroke();
        ctx.restore();
    }

    function drawInclined(ctx, obj) {
        var b = cardBg(ctx, obj, '#fff');
        ctx.save();
        ctx.strokeStyle = obj.color || '#0f172a';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(b.x + 16, b.y + b.h - 16);
        ctx.lineTo(b.x + b.w - 16, b.y + b.h - 16);
        ctx.lineTo(b.x + b.w - 16, b.y + 24);
        ctx.closePath();
        ctx.stroke();
        drawLabel(ctx, 'θ', b.x + b.w - 40, b.y + b.h - 28, { size: 12 });
        ctx.restore();
    }

    function drawTimer(ctx, obj) {
        var b = cardBg(ctx, obj, '#111827');
        var start = (obj.data && obj.data.start) || Date.now();
        var elapsed = Math.max(0, Math.floor((Date.now() - start) / 1000));
        if (obj.data && obj.data.paused) elapsed = obj.data.elapsed || 0;
        var mm = String(Math.floor(elapsed / 60)).padStart ? String(Math.floor(elapsed / 60)).padStart(2, '0') : ('0' + Math.floor(elapsed / 60)).slice(-2);
        var ss = String(elapsed % 60).padStart ? String(elapsed % 60).padStart(2, '0') : ('0' + (elapsed % 60)).slice(-2);
        drawLabel(ctx, mm + ':' + ss, b.x + b.w / 2, b.y + b.h / 2, {
            size: 22, bold: true, align: 'center', base: 'middle', color: '#f8fafc'
        });
    }

    function drawCircuit(ctx, obj) {
        var kind = (obj.data && obj.data.comp) || 'resistor';
        var b = cardBg(ctx, obj, '#fff');
        ctx.save();
        ctx.strokeStyle = obj.color || '#0f172a';
        ctx.lineWidth = 2;
        var y = b.y + b.h / 2;
        ctx.beginPath();
        ctx.moveTo(b.x + 8, y);
        ctx.lineTo(b.x + b.w - 8, y);
        ctx.stroke();
        if (kind === 'battery') {
            ctx.beginPath();
            ctx.moveTo(b.x + b.w / 2 - 6, y - 14);
            ctx.lineTo(b.x + b.w / 2 - 6, y + 14);
            ctx.moveTo(b.x + b.w / 2 + 6, y - 8);
            ctx.lineTo(b.x + b.w / 2 + 6, y + 8);
            ctx.stroke();
        } else if (kind === 'resistor') {
            ctx.beginPath();
            var x0 = b.x + b.w / 2 - 24;
            ctx.moveTo(x0, y);
            var zig;
            for (zig = 0; zig < 4; zig++) {
                ctx.lineTo(x0 + 6 + zig * 12, y + (zig % 2 ? 10 : -10));
            }
            ctx.lineTo(x0 + 48, y);
            ctx.stroke();
        } else if (kind === 'capacitor') {
            ctx.beginPath();
            ctx.moveTo(b.x + b.w / 2 - 4, y - 14);
            ctx.lineTo(b.x + b.w / 2 - 4, y + 14);
            ctx.moveTo(b.x + b.w / 2 + 4, y - 14);
            ctx.lineTo(b.x + b.w / 2 + 4, y + 14);
            ctx.stroke();
        } else if (kind === 'bulb') {
            ctx.beginPath();
            ctx.arc(b.x + b.w / 2, y, 12, 0, Math.PI * 2);
            ctx.stroke();
        } else if (kind === 'switch') {
            ctx.beginPath();
            ctx.arc(b.x + b.w / 2 - 10, y, 3, 0, Math.PI * 2);
            ctx.moveTo(b.x + b.w / 2 - 10, y);
            ctx.lineTo(b.x + b.w / 2 + 12, y - 12);
            ctx.stroke();
        } else if (kind === 'ammeter' || kind === 'voltmeter') {
            ctx.beginPath();
            ctx.arc(b.x + b.w / 2, y, 14, 0, Math.PI * 2);
            ctx.stroke();
            drawLabel(ctx, kind === 'ammeter' ? 'A' : 'V', b.x + b.w / 2, y, {
                size: 12, align: 'center', base: 'middle', bold: true
            });
        }
        drawLabel(ctx, kind, b.x + 8, b.y + 4, { size: 10, color: '#64748b' });
        ctx.restore();
    }

    function drawReveal(ctx, obj) {
        var revealed = !!(obj.data && obj.data.revealed);
        var b = cardBg(ctx, obj, revealed ? '#ecfdf5' : '#fef3c7');
        if (!revealed) {
            drawLabel(ctx, '???', b.x + b.w / 2, b.y + b.h / 2, {
                size: 28, bold: true, align: 'center', base: 'middle', color: '#b45309'
            });
            drawLabel(ctx, 'Reveal answer', b.x + 8, b.y + 6, { size: 10, color: '#92400e' });
        } else {
            drawLabel(ctx, (obj.data && obj.data.answer) || 'Answer', b.x + 10, b.y + b.h / 2 - 8, {
                size: 14, color: '#065f46', base: 'middle'
            });
        }
    }

    function drawWorkspace(ctx, obj) {
        var b = cardBg(ctx, obj, 'rgba(59,130,246,0.06)');
        ctx.save();
        ctx.setLineDash([6, 4]);
        ctx.strokeStyle = '#3b82f6';
        ctx.strokeRect(b.x + 3, b.y + 3, b.w - 6, b.h - 6);
        ctx.restore();
        drawLabel(ctx, (obj.data && obj.data.label) || 'Student workspace', b.x + 10, b.y + 8, {
            size: 12, color: '#1d4ed8', bold: true
        });
    }

    function drawTransform(ctx, obj) {
        drawResultCard(ctx, {
            x: obj.x, y: obj.y, w: obj.w, h: obj.h, color: obj.color,
            data: {
                title: 'Transform',
                text: (obj.data && obj.data.text) || 'a·f(x) → stretch',
                sub: (obj.data && obj.data.sub) || 'f(x)→f(x−b) shift'
            }
        });
    }

    function drawGenericEdu(ctx, obj) {
        var b = cardBg(ctx, obj, '#f8fafc');
        drawLabel(ctx, obj.kind || 'edu', b.x + 10, b.y + 10, { size: 12, bold: true, color: '#475569' });
        if (obj.data && obj.data.text) {
            drawLabel(ctx, obj.data.text, b.x + 10, b.y + 32, { size: 13 });
        }
    }

    function drawEduContent(ctx, obj, api, skipHidden) {
        var kind = obj.kind || '';
        if (kind === 'equation' || kind === 'symbols' || kind === 'phys-eq' || kind === 'chem-eq') return drawEquation(ctx, obj);
        if (kind === 'graph') return drawGraphKind(ctx, obj);
        if (kind === 'coords') return drawCoords(ctx, obj);
        if (kind === 'numberline') return drawNumberline(ctx, obj);
        if (kind === 'ruler') return drawRuler(ctx, obj);
        if (kind === 'protractor') return drawProtractor(ctx, obj);
        if (kind === 'compass') return drawCompass(ctx, obj);
        if (kind === 'triangle') return drawTriangle(ctx, obj);
        if (kind === 'polygon') return drawPolygon(ctx, obj);
        if (kind === 'angle') return drawAngle(ctx, obj);
        if (kind === 'fraction') return drawFraction(ctx, obj);
        if (kind === 'table') return drawTable(ctx, obj);
        if (kind === 'calculator' || kind === 'molar-mass' || kind === 'molarity' || kind === 'dilution' || kind === 'unit' || kind === 'balancer' || kind === 'poll') {
            return drawResultCard(ctx, obj, kind);
        }
        if (kind === 'transform') return drawTransform(ctx, obj);
        if (kind === 'element') return drawElement(ctx, obj);
        if (kind === 'molecule') return drawMolecule(ctx, obj);
        if (kind === 'lewis') return drawLewis(ctx, obj);
        if (kind === 'bond') return drawBond(ctx, obj);
        if (kind === 'benzene' || kind === 'rings') return drawBenzene(ctx, obj);
        if (kind === 'reaction') return drawReactionArrow(ctx, obj);
        if (kind === 'charge') return drawCharge(ctx, obj);
        if (kind === 'ph') return drawPhScale(ctx, obj);
        if (kind === 'beaker' || kind === 'testtube' || kind === 'flask' || kind === 'burette' || kind === 'pipette' || kind === 'cylinder' || kind === 'lab') {
            return drawLab(ctx, obj);
        }
        if (kind === 'vector' || kind === 'force') return drawVector(ctx, obj);
        if (kind === 'fbd') return drawFbd(ctx, obj);
        if (kind === 'motion' || kind === 'trajectory') return drawMotion(ctx, obj);
        if (kind === 'wave') return drawWave(ctx, obj);
        if (kind === 'inclined') return drawInclined(ctx, obj);
        if (kind === 'timer') return drawTimer(ctx, obj);
        if (kind === 'battery' || kind === 'resistor' || kind === 'capacitor' || kind === 'switch' || kind === 'bulb' || kind === 'wire' || kind === 'ammeter' || kind === 'voltmeter' || kind === 'circuit') {
            return drawCircuit(ctx, obj);
        }
        if (kind === 'reveal') return drawReveal(ctx, obj);
        if (kind === 'workspace') return drawWorkspace(ctx, obj);
        if (kind === 'textstamp') {
            var b = cardBg(ctx, obj, '#fff');
            drawLabel(ctx, (obj.data && obj.data.text) || '', b.x + 10, b.y + 12, { size: 14 });
            return;
        }
        drawGenericEdu(ctx, obj);
    }

    function drawEdu(ctx, obj, api) {
        if (!ctx || !obj) return;
        if (obj.hidden && !(api && api.isHost && api.isHost())) {
            drawHiddenPlaceholder(ctx, obj, api);
            return;
        }
        if (obj.hidden && api && api.isHost && api.isHost()) {
            drawHiddenPlaceholder(ctx, obj, api);
            return;
        }
        if (obj.kind === 'reveal' && !(obj.data && obj.data.revealed) && !(api && api.isHost && api.isHost())) {
            drawReveal(ctx, obj);
            return;
        }
        drawEduContent(ctx, obj, api, false);
    }

    /* ---------- tool catalog ---------- */

    function buildCatalog(state) {
        function t(id, cat, label, keywords, icon, action) {
            return { id: id, cat: cat, label: label, keywords: keywords, icon: icon, action: action };
        }
        var api = function () { return state.api; };
        var hooks = function () { return state.hooks || {}; };

        function placeKind(kind, w, h, data, extra) {
            return centerPlace(api(), kind, w, h, data, extra);
        }

        function promptPlace(kind, title, def, w, h, mapFn) {
            var v = window.prompt(title, def || '');
            if (v == null || v === '') return;
            var data = mapFn ? mapFn(v) : { text: v };
            placeKind(kind, w, h, data);
        }

        return [
            t('equation', 'math', 'Equation', ['latex', 'math', 'formula'], 'bi-superscript', function () {
                promptPlace('equation', 'Equation / LaTeX', 'y = mx + c', 220, 70, function (v) {
                    return { text: v, latex: v };
                });
            }),
            t('symbols', 'math', 'Symbols', ['keyboard', 'pi', 'theta'], 'bi-keyboard', function () {
                state.openSymbols && state.openSymbols();
            }),
            t('graph', 'math', 'Graph', ['plot', 'sin', 'parabola'], 'bi-graph-up', function () {
                promptPlace('graph', 'Expression (e.g. x^2, sin(x))', 'x^2', 220, 160, function (v) {
                    return { expr: v };
                });
            }),
            t('coords', 'math', 'Coordinate axes', ['axes', 'xy'], 'bi-plus-lg', function () {
                placeKind('coords', 180, 180, {});
            }),
            t('numberline', 'math', 'Number line', ['line', 'integers'], 'bi-distribute-horizontal', function () {
                placeKind('numberline', 280, 70, { min: -5, max: 5 });
            }),
            t('ruler', 'math', 'Ruler', ['measure', 'cm'], 'bi-rulers', function () {
                placeKind('ruler', 260, 60, {});
            }),
            t('protractor', 'math', 'Protractor', ['angle', 'degrees'], 'bi-fan', function () {
                placeKind('protractor', 200, 120, {});
            }),
            t('compass', 'math', 'Compass', ['circle', 'geometry'], 'bi-bullseye', function () {
                placeKind('compass', 140, 140, {});
            }),
            t('triangle', 'math', 'Triangle', ['geometry', 'shape'], 'bi-triangle', function () {
                placeKind('triangle', 140, 120, {});
            }),
            t('polygon', 'math', 'Polygon', ['pentagon', 'hexagon'], 'bi-pentagon', function () {
                var n = window.prompt('Number of sides', '5');
                if (!n) return;
                placeKind('polygon', 140, 140, { sides: clamp(parseInt(n, 10) || 5, 3, 12) });
            }),
            t('angle', 'math', 'Angle', ['degree', 'geometry'], 'bi-activity', function () {
                var n = window.prompt('Angle degrees', '45');
                if (!n) return;
                placeKind('angle', 160, 120, { deg: clamp(parseInt(n, 10) || 45, 1, 179) });
            }),
            t('fraction', 'math', 'Fraction', ['numerator', 'denominator'], 'bi-hr', function () {
                var v = window.prompt('Fraction a/b', '1/2');
                if (!v) return;
                var parts = String(v).split('/');
                placeKind('fraction', 100, 100, {
                    num: parseInt(parts[0], 10) || 1,
                    den: parseInt(parts[1], 10) || 2
                });
            }),
            t('table', 'math', 'Table', ['grid', 'cells'], 'bi-grid-3x3', function () {
                placeKind('table', 180, 140, { rows: 3, cols: 3 });
            }),
            t('calculator', 'math', 'Calculator', ['scientific', 'calc'], 'bi-calculator', function () {
                state.openCalculator && state.openCalculator();
            }),
            t('transform', 'math', 'Transform', ['stretch', 'shift', 'af(x)'], 'bi-arrows-angle-expand', function () {
                placeKind('transform', 200, 90, {
                    text: 'a·f(x) → vertical stretch',
                    sub: 'f(x−b) → horizontal shift'
                });
            }),

            t('periodic', 'chem', 'Periodic table', ['element', 'atom'], 'bi-grid', function () {
                state.openPeriodic && state.openPeriodic();
            }),
            t('chem-eq', 'chem', 'Chem equation', ['reaction', 'subscript'], 'bi-eyedropper', function () {
                promptPlace('chem-eq', 'Chemical equation', 'H2 + O2 -> H2O', 260, 70, function (v) {
                    return { text: formatChemEq(v), latex: v };
                });
            }),
            t('balancer', 'chem', 'Balancer', ['balance', 'stoich'], 'bi-balance-scale', function () {
                state.openBalancer && state.openBalancer();
            }),
            t('molecule', 'chem', 'Molecule', ['H2O', 'CO2', 'CH4'], 'bi-flower2', function () {
                state.openMolecules && state.openMolecules();
            }),
            t('lewis', 'chem', 'Lewis structure', ['dots', 'valence'], 'bi-asterisk', function () {
                var s = window.prompt('Element symbol', 'C');
                if (!s) return;
                placeKind('lewis', 120, 90, { symbol: s });
            }),
            t('bond-single', 'chem', 'Single bond', ['bond'], 'bi-dash', function () {
                placeKind('bond', 90, 28, { bond: 'single' });
            }),
            t('bond-double', 'chem', 'Double bond', ['bond'], 'bi-dash-lg', function () {
                placeKind('bond', 90, 28, { bond: 'double' });
            }),
            t('bond-triple', 'chem', 'Triple bond', ['bond'], 'bi-three-dots', function () {
                placeKind('bond', 90, 28, { bond: 'triple' });
            }),
            t('bond-wedge', 'chem', 'Wedge bond', ['3d', 'stereo'], 'bi-triangle-fill', function () {
                placeKind('bond', 90, 28, { bond: 'wedge' });
            }),
            t('bond-dash', 'chem', 'Dash bond', ['3d', 'stereo'], 'bi-three-dots-vertical', function () {
                placeKind('bond', 90, 28, { bond: 'dash' });
            }),
            t('benzene', 'chem', 'Benzene', ['aromatic', 'ring'], 'bi-hexagon', function () {
                placeKind('benzene', 120, 120, {});
            }),
            t('rings', 'chem', 'Ring', ['cyclo', 'hexagon'], 'bi-octagon', function () {
                placeKind('rings', 120, 120, {});
            }),
            t('reaction', 'chem', 'Reaction arrow', ['yields', 'arrow'], 'bi-arrow-right', function () {
                placeKind('reaction', 100, 28, {});
            }),
            t('charge', 'chem', 'Charge label', ['ion', 'plus', 'minus'], 'bi-plus-slash-minus', function () {
                var c = window.prompt('Charge', '+');
                if (c == null) return;
                placeKind('charge', 60, 50, { charge: c });
            }),
            t('molar-mass', 'chem', 'Molar mass', ['formula weight'], 'bi-speedometer2', function () {
                state.openMolarMass && state.openMolarMass();
            }),
            t('molarity', 'chem', 'Molarity', ['concentration', 'M'], 'bi-droplet', function () {
                state.openMolarity && state.openMolarity();
            }),
            t('dilution', 'chem', 'Dilution', ['C1V1', 'C2V2'], 'bi-moisture', function () {
                state.openDilution && state.openDilution();
            }),
            t('unit', 'chem', 'Unit converter', ['convert', 'si'], 'bi-arrow-left-right', function () {
                state.openUnitConverter && state.openUnitConverter();
            }),
            t('ph', 'chem', 'pH scale', ['acid', 'base'], 'bi-thermometer-half', function () {
                placeKind('ph', 260, 70, {});
            }),
            t('beaker', 'chem', 'Beaker', ['lab', 'glassware'], 'bi-cup-straw', function () {
                placeKind('beaker', 100, 120, { equip: 'beaker' });
            }),
            t('testtube', 'chem', 'Test tube', ['lab'], 'bi-eyedropper', function () {
                placeKind('testtube', 80, 140, { equip: 'testtube' });
            }),
            t('flask', 'chem', 'Flask', ['erlenmeyer', 'lab'], 'bi-droplet-half', function () {
                placeKind('flask', 110, 130, { equip: 'flask' });
            }),
            t('burette', 'chem', 'Burette', ['titration'], 'bi-funnel', function () {
                placeKind('burette', 80, 160, { equip: 'burette' });
            }),
            t('pipette', 'chem', 'Pipette', ['volume'], 'bi-eyedropper', function () {
                placeKind('pipette', 70, 150, { equip: 'pipette' });
            }),
            t('cylinder', 'chem', 'Graduated cylinder', ['volume', 'lab'], 'bi-database', function () {
                placeKind('cylinder', 90, 150, { equip: 'cylinder' });
            }),

            t('vector', 'phys', 'Vector', ['arrow', 'direction'], 'bi-arrow-up-right', function () {
                placeKind('vector', 120, 60, { label: 'v' });
            }),
            t('force', 'phys', 'Force', ['newton', 'F'], 'bi-lightning', function () {
                placeKind('force', 120, 60, { label: 'F' });
            }),
            t('fbd', 'phys', 'Free-body diagram', ['forces', 'body'], 'bi-box', function () {
                placeKind('fbd', 180, 160, {});
            }),
            t('motion', 'phys', 'Motion / trajectory', ['projectile', 'path'], 'bi-bezier2', function () {
                placeKind('motion', 200, 120, {});
            }),
            t('phys-eq', 'phys', 'Physics equation', ['formula', 'F=ma'], 'bi-journal-code', function () {
                promptPlace('phys-eq', 'Physics equation', 'F = ma', 200, 70, function (v) {
                    return { text: v, latex: v };
                });
            }),
            t('wave', 'phys', 'Wave', ['sine', 'amplitude'], 'bi-soundwave', function () {
                placeKind('wave', 200, 100, {});
            }),
            t('inclined', 'phys', 'Inclined plane', ['ramp', 'theta'], 'bi-triangle-half', function () {
                placeKind('inclined', 180, 120, {});
            }),
            t('timer', 'phys', 'Stopwatch', ['timer', 'clock'], 'bi-stopwatch', function () {
                placeKind('timer', 120, 70, { start: Date.now() });
            }),
            t('battery', 'phys', 'Battery', ['circuit', 'cell'], 'bi-battery-full', function () {
                placeKind('battery', 120, 60, { comp: 'battery' });
            }),
            t('resistor', 'phys', 'Resistor', ['ohm', 'circuit'], 'bi-slash-lg', function () {
                placeKind('resistor', 140, 50, { comp: 'resistor' });
            }),
            t('capacitor', 'phys', 'Capacitor', ['farad', 'circuit'], 'bi-pause', function () {
                placeKind('capacitor', 120, 60, { comp: 'capacitor' });
            }),
            t('switch', 'phys', 'Switch', ['circuit'], 'bi-toggle-on', function () {
                placeKind('switch', 120, 50, { comp: 'switch' });
            }),
            t('bulb', 'phys', 'Bulb', ['lamp', 'circuit'], 'bi-lightbulb', function () {
                placeKind('bulb', 100, 70, { comp: 'bulb' });
            }),
            t('wire', 'phys', 'Wire', ['circuit', 'lead'], 'bi-dash', function () {
                placeKind('wire', 140, 40, { comp: 'wire' });
            }),
            t('ammeter', 'phys', 'Ammeter', ['current', 'A'], 'bi-speedometer', function () {
                placeKind('ammeter', 100, 70, { comp: 'ammeter' });
            }),
            t('voltmeter', 'phys', 'Voltmeter', ['voltage', 'V'], 'bi-speedometer2', function () {
                placeKind('voltmeter', 100, 70, { comp: 'voltmeter' });
            }),
            t('unit-phys', 'phys', 'Unit converter', ['si', 'convert'], 'bi-arrow-left-right', function () {
                state.openUnitConverter && state.openUnitConverter();
            }),
            t('ruler-phys', 'phys', 'Ruler', ['measure'], 'bi-rulers', function () {
                placeKind('ruler', 260, 60, {});
            }),
            t('protractor-phys', 'phys', 'Protractor', ['angle'], 'bi-fan', function () {
                placeKind('protractor', 200, 120, {});
            }),

            t('reveal', 'teach', 'Reveal card', ['answer', 'hide'], 'bi-eye', function () {
                var ans = window.prompt('Answer to hide', '');
                if (ans == null) return;
                placeKind('reveal', 180, 90, { answer: ans, revealed: false });
            }),
            t('spotlight', 'teach', 'Spotlight', ['focus', 'laser'], 'bi-brightness-high', function () {
                state.toggleSpotlight && state.toggleSpotlight();
            }),
            t('present', 'teach', 'Present mode', ['presentation', 'fullscreen'], 'bi-easel', function () {
                var h = hooks();
                if (h.onPresent) h.onPresent(!state.presenting);
                state.presenting = !state.presenting;
                toastSafe(api(), state.presenting ? 'Present mode on' : 'Present mode off');
                state.refreshChrome && state.refreshChrome();
            }),
            t('workspace', 'teach', 'Student workspace', ['frame', 'label'], 'bi-window', function () {
                var label = window.prompt('Workspace label', 'Student workspace');
                if (label == null) return;
                placeKind('workspace', 280, 180, { label: label });
            }),
            t('follow', 'teach', 'Follow teacher', ['sync', 'view'], 'bi-people', function () {
                var h = hooks();
                if (h.onFollow) h.onFollow(true);
                toastSafe(api(), 'Follow teacher requested');
            }),
            t('lock', 'teach', 'Lock selection', ['freeze'], 'bi-lock', function () {
                var a = api();
                if (!a || !a.getSelected) return;
                var sel = a.getSelected();
                if (!sel) { toastSafe(a, 'Select an object first'); return; }
                if (a.patchSelected) a.patchSelected({ locked: true });
                toastSafe(a, 'Selection locked');
            }),
            t('poll', 'teach', 'Quick poll', ['vote', 'question'], 'bi-bar-chart', function () {
                var q = window.prompt('Poll question', 'True or false?');
                if (!q) return;
                placeKind('poll', 220, 90, { title: 'Poll', text: q, sub: 'A / B / C / D' });
            }),
            t('snapshot', 'teach', 'Snapshot save', ['save', 'restore'], 'bi-camera', function () {
                state.saveSnapshot && state.saveSnapshot();
            }),
            t('snapshot-restore', 'teach', 'Snapshot restore', ['load'], 'bi-arrow-counterclockwise', function () {
                state.restoreSnapshot && state.restoreSnapshot();
            }),
            t('upload-pdf', 'teach', 'Upload PDF', ['pdf', 'document', 'slides', 'book', 'file'], 'bi-file-earmark-pdf', function () {
                if (window.CKPdf && typeof window.CKPdf.triggerUpload === 'function') {
                    window.CKPdf.triggerUpload();
                } else {
                    var inp = document.getElementById('ckPdfFileInput');
                    if (inp) { inp.value = ''; inp.click(); }
                }
            }),
            t('export-png', 'teach', 'Export PNG', ['image', 'download'], 'bi-image', function () {
                var a = api();
                if (a && a.exportPng) a.exportPng();
                else toastSafe(a, 'PNG export unavailable');
            }),
            t('export-json', 'teach', 'Export JSON', ['data', 'log'], 'bi-filetype-json', function () {
                state.exportJson && state.exportJson();
            }),
            t('export-pdf', 'teach', 'Print / PDF', ['print'], 'bi-printer', function () {
                window.print();
            }),
            t('page-add', 'teach', 'Add page', ['page', 'new'], 'bi-file-earmark-plus', function () {
                var a = api();
                if (!a || !a.getPage || !a.setPage) return;
                var n = (a.getPage() || 0) + 1;
                a.setPage(n, { create: true });
                state.refreshChrome && state.refreshChrome();
            }),
            t('page-prev', 'teach', 'Previous page', ['page'], 'bi-chevron-left', function () {
                var a = api();
                if (!a || !a.getPage || !a.setPage) return;
                a.setPage(Math.max(0, (a.getPage() || 0) - 1));
                state.refreshChrome && state.refreshChrome();
            }),
            t('page-next', 'teach', 'Next page', ['page'], 'bi-chevron-right', function () {
                var a = api();
                if (!a || !a.getPage || !a.setPage) return;
                a.setPage((a.getPage() || 0) + 1);
                state.refreshChrome && state.refreshChrome();
            }),

            t('clear-sel', 'util', 'Deselect', ['select'], 'bi-x-circle', function () {
                var a = api();
                if (a && a.selectId) a.selectId(null);
            }),
            t('hide-obj', 'util', 'Hide / unhide', ['hidden'], 'bi-eye-slash', function () {
                var a = api();
                var sel = a && a.getSelected && a.getSelected();
                if (!sel) { toastSafe(a, 'Select an object'); return; }
                if (a.patchSelected) a.patchSelected({ hidden: !sel.hidden });
            }),
            t('reveal-sel', 'util', 'Toggle reveal', ['answer'], 'bi-eye', function () {
                var a = api();
                var sel = a && a.getSelected && a.getSelected();
                if (!sel || sel.kind !== 'reveal') { toastSafe(a, 'Select a reveal card'); return; }
                var revealed = !(sel.data && sel.data.revealed);
                var data = {};
                var k;
                if (sel.data) for (k in sel.data) if (sel.data.hasOwnProperty(k)) data[k] = sel.data[k];
                data.revealed = revealed;
                if (a.patchSelected) a.patchSelected({ data: data });
            })
        ];
    }

    /* ---------- mount / UI ---------- */

    function ensureChrome(stage, api) {
        var $ = (api && api.$) || function (id) { return document.getElementById(id); };
        stage.classList.add('ck-board-chrome');

        var legacy = $('ckBoardTools');
        if (legacy) legacy.classList.add('ck-board-tools-legacy');

        var rail = $('ckBoardRail');
        if (!rail) {
            rail = el('div', 'ck-board-rail', { id: 'ckBoardRail' });
            stage.insertBefore(rail, stage.firstChild);
        }

        var top = $('ckBoardTop');
        if (!top) {
            top = el('div', 'ck-board-top', { id: 'ckBoardTop' });
            if (rail.nextSibling) stage.insertBefore(top, rail.nextSibling);
            else stage.appendChild(top);
        }

        var panel = $('ckEduPanel');
        if (!panel) {
            panel = el('aside', 'ck-edu-panel', { id: 'ckEduPanel' });
            stage.appendChild(panel);
        }

        var ctx = $('ckBoardCtx');
        if (!ctx) {
            ctx = el('div', 'ck-board-ctx', { id: 'ckBoardCtx' });
            stage.appendChild(ctx);
        }

        var search = $('ckEduSearch');
        if (!search) {
            search = el('input', 'ck-edu-search', {
                id: 'ckEduSearch',
                type: 'search',
                placeholder: 'Search tools…',
                autocomplete: 'off'
            });
            panel.insertBefore(search, panel.firstChild);
        }

        var modal = $('ckEduModal');
        if (!modal) {
            modal = el('div', 'ck-edu-modal', { id: 'ckEduModal', hidden: 'hidden' });
            stage.appendChild(modal);
        }

        var spot = $('ckEduSpotlight');
        if (!spot) {
            spot = el('div', 'ck-edu-spotlight', { id: 'ckEduSpotlight', hidden: 'hidden' });
            stage.appendChild(spot);
        }

        return { rail: rail, top: top, panel: panel, ctx: ctx, search: search, modal: modal, spot: spot, legacy: legacy };
    }

    function primaryToolBtn(tool, label, icon) {
        var b = el('button', 'ck-rail-tool', {
            type: 'button',
            'data-tool': tool,
            title: label,
            'aria-label': label
        });
        b.innerHTML = '<i class="bi ' + icon + '" aria-hidden="true"></i>';
        return b;
    }

    function mount(api, hooks) {
        hooks = hooks || {};
        api = api || {};
        var $ = api.$ || function (id) { return document.getElementById(id); };
        var stage = $('ckBoardStage') || document.querySelector('.ck-board-stage');
        if (!stage) {
            toastSafe(api, 'Board stage not found');
            return { draw: drawEdu, refresh: function () {}, openCategory: function () {} };
        }

        var state = {
            api: api,
            hooks: hooks,
            subject: 'general',
            presenting: false,
            spotlightOn: false,
            toolsOpen: false,
            catalog: null
        };

        var chrome = ensureChrome(stage, api);
        state.catalog = buildCatalog(state);

        function byId(id) {
            var i;
            for (i = 0; i < state.catalog.length; i++) {
                if (state.catalog[i].id === id) return state.catalog[i];
            }
            return null;
        }

        function openModal(title, bodyNode, footerBtns) {
            var modal = chrome.modal;
            modal.innerHTML = '';
            modal.hidden = false;
            modal.classList.add('ck-is-open');
            var card = el('div', 'ck-edu-modal-card');
            var head = el('div', 'ck-edu-modal-head');
            head.appendChild(el('strong', '', { text: title }));
            var close = el('button', 'ck-edu-modal-close', { type: 'button', text: '×', 'aria-label': 'Close' });
            close.onclick = closeModal;
            head.appendChild(close);
            card.appendChild(head);
            var body = el('div', 'ck-edu-modal-body');
            if (typeof bodyNode === 'string') body.innerHTML = bodyNode;
            else if (bodyNode) body.appendChild(bodyNode);
            card.appendChild(body);
            if (footerBtns && footerBtns.length) {
                var foot = el('div', 'ck-edu-modal-foot');
                footerBtns.forEach(function (b) { foot.appendChild(b); });
                card.appendChild(foot);
            }
            modal.appendChild(card);
            modal.onclick = function (ev) {
                if (ev.target === modal) closeModal();
            };
        }

        function closeModal() {
            chrome.modal.classList.remove('ck-is-open');
            chrome.modal.hidden = true;
            chrome.modal.innerHTML = '';
        }

        state.openSymbols = function () {
            var wrap = el('div', 'ck-edu-symbols');
            var preview = el('input', '', { type: 'text', value: '', placeholder: 'Build equation…' });
            wrap.appendChild(preview);
            var grid = el('div', 'ck-edu-symbol-grid');
            MATH_SYMBOLS.forEach(function (sym) {
                var b = el('button', '', { type: 'button', text: sym });
                b.onclick = function () { preview.value += sym; };
                grid.appendChild(b);
            });
            wrap.appendChild(grid);
            var place = el('button', 'ck-edu-btn', { type: 'button', text: 'Place on board' });
            place.onclick = function () {
                centerPlace(api, 'equation', 220, 70, { text: preview.value, latex: preview.value });
                closeModal();
            };
            openModal('Symbol keyboard', wrap, [place]);
        };

        state.openCalculator = function () {
            var wrap = el('div', 'ck-edu-calc');
            var display = el('input', '', { type: 'text', value: '0' });
            wrap.appendChild(display);
            var keys = ['7', '8', '9', '/', '4', '5', '6', '*', '1', '2', '3', '-', '0', '.', '(', ')', 'sin', 'cos', 'tan', '+', '√', '^', 'π', 'C', '='];
            var grid = el('div', 'ck-edu-calc-grid');
            keys.forEach(function (k) {
                var b = el('button', '', { type: 'button', text: k });
                b.onclick = function () {
                    if (k === 'C') display.value = '0';
                    else if (k === '=') {
                        try {
                            var expr = display.value.replace(/π/g, 'Math.PI').replace(/√/g, 'Math.sqrt')
                                .replace(/sin/g, 'Math.sin').replace(/cos/g, 'Math.cos').replace(/tan/g, 'Math.tan')
                                .replace(/\^/g, '**');
                            display.value = String(Function('"use strict"; return (' + expr + ');')());
                        } catch (e) {
                            display.value = 'Error';
                        }
                    } else if (display.value === '0' || display.value === 'Error') display.value = k;
                    else display.value += k;
                };
                grid.appendChild(b);
            });
            wrap.appendChild(grid);
            var place = el('button', 'ck-edu-btn', { type: 'button', text: 'Place result' });
            place.onclick = function () {
                centerPlace(api, 'calculator', 180, 80, { title: 'Calculator', text: display.value });
                closeModal();
            };
            openModal('Scientific calculator', wrap, [place]);
        };

        state.openPeriodic = function () {
            var wrap = el('div', 'ck-edu-periodic');
            PERIODIC.forEach(function (elDef) {
                var b = el('button', 'ck-edu-element', {
                    type: 'button',
                    title: elDef.n,
                    text: elDef.s
                });
                b.onclick = function () {
                    centerPlace(api, 'element', 90, 100, {
                        z: elDef.z, symbol: elDef.s, name: elDef.n
                    });
                    closeModal();
                };
                wrap.appendChild(b);
            });
            openModal('Periodic table', wrap);
        };

        state.openMolecules = function () {
            var wrap = el('div', 'ck-edu-mols');
            ['H2O', 'CO2', 'CH4', 'NH3', 'O2'].forEach(function (f) {
                var b = el('button', 'ck-edu-btn', { type: 'button', text: f });
                b.onclick = function () {
                    centerPlace(api, 'molecule', 120, 90, {
                        formula: formatChemEq(f),
                        label: f
                    });
                    closeModal();
                };
                wrap.appendChild(b);
            });
            openModal('Molecule templates', wrap);
        };

        state.openBalancer = function () {
            var wrap = el('div', '');
            var input = el('input', '', { type: 'text', value: 'H2 + O2 -> H2O', style: 'width:100%' });
            var out = el('pre', '');
            wrap.appendChild(input);
            wrap.appendChild(out);
            var run = el('button', 'ck-edu-btn', { type: 'button', text: 'Balance tip' });
            run.onclick = function () {
                var tip = tryBalanceTip(input.value);
                out.textContent = (tip.balanced ? tip.balanced + '\n\n' : '') + tip.tip;
            };
            var place = el('button', 'ck-edu-btn', { type: 'button', text: 'Place on board' });
            place.onclick = function () {
                var tip = tryBalanceTip(input.value);
                centerPlace(api, 'balancer', 260, 110, {
                    title: 'Balancer',
                    text: tip.balanced || formatChemEq(input.value),
                    sub: tip.tip
                });
                closeModal();
            };
            openModal('Equation balancer', wrap, [run, place]);
        };

        state.openMolarMass = function () {
            var wrap = el('div', '');
            var input = el('input', '', { type: 'text', value: 'H2O', placeholder: 'Formula' });
            var out = el('div', '');
            wrap.appendChild(input);
            wrap.appendChild(out);
            var go = el('button', 'ck-edu-btn', { type: 'button', text: 'Calculate' });
            go.onclick = function () {
                var m = parseMolarMass(input.value);
                out.textContent = m == null ? 'Unknown formula' : (m + ' g/mol');
            };
            var place = el('button', 'ck-edu-btn', { type: 'button', text: 'Place' });
            place.onclick = function () {
                var m = parseMolarMass(input.value);
                centerPlace(api, 'molar-mass', 180, 80, {
                    title: 'Molar mass',
                    text: input.value + ' = ' + (m == null ? '?' : m + ' g/mol')
                });
                closeModal();
            };
            openModal('Molar mass', wrap, [go, place]);
        };

        state.openMolarity = function () {
            var wrap = el('div', '');
            wrap.innerHTML = '<label>moles <input id="ckMolN" type="number" step="any" value="0.1"></label>' +
                '<label>litres <input id="ckMolV" type="number" step="any" value="0.5"></label>' +
                '<div id="ckMolOut"></div>';
            var place = el('button', 'ck-edu-btn', { type: 'button', text: 'Place' });
            place.onclick = function () {
                var n = parseFloat((wrap.querySelector('#ckMolN') || {}).value);
                var v = parseFloat((wrap.querySelector('#ckMolV') || {}).value);
                var M = v ? (n / v) : NaN;
                centerPlace(api, 'molarity', 200, 90, {
                    title: 'Molarity',
                    text: 'M = n/V = ' + (isFinite(M) ? (Math.round(M * 1000) / 1000) + ' mol/L' : '?'),
                    sub: 'n=' + n + ', V=' + v
                });
                closeModal();
            };
            openModal('Molarity calculator', wrap, [place]);
        };

        state.openDilution = function () {
            var wrap = el('div', '');
            wrap.innerHTML = '<label>C1 <input id="ckC1" type="number" step="any" value="1"></label>' +
                '<label>V1 <input id="ckV1" type="number" step="any" value="10"></label>' +
                '<label>C2 <input id="ckC2" type="number" step="any" value="0.1"></label>' +
                '<div>V2 = C1·V1 / C2</div>';
            var place = el('button', 'ck-edu-btn', { type: 'button', text: 'Place' });
            place.onclick = function () {
                var c1 = parseFloat((wrap.querySelector('#ckC1') || {}).value);
                var v1 = parseFloat((wrap.querySelector('#ckV1') || {}).value);
                var c2 = parseFloat((wrap.querySelector('#ckC2') || {}).value);
                var v2 = c2 ? (c1 * v1) / c2 : NaN;
                centerPlace(api, 'dilution', 220, 100, {
                    title: 'Dilution',
                    text: 'V2 = ' + (isFinite(v2) ? (Math.round(v2 * 1000) / 1000) : '?'),
                    sub: 'C1V1=C2V2'
                });
                closeModal();
            };
            openModal('Dilution (C1V1=C2V2)', wrap, [place]);
        };

        state.openUnitConverter = function () {
            var wrap = el('div', '');
            wrap.innerHTML = '<select id="ckUnitMode">' +
                '<option value="cm-m">cm → m</option>' +
                '<option value="m-cm">m → cm</option>' +
                '<option value="g-kg">g → kg</option>' +
                '<option value="kg-g">kg → g</option>' +
                '<option value="c-k">°C → K</option>' +
                '<option value="k-c">K → °C</option>' +
                '</select> <input id="ckUnitVal" type="number" step="any" value="100">';
            var place = el('button', 'ck-edu-btn', { type: 'button', text: 'Place' });
            place.onclick = function () {
                var mode = (wrap.querySelector('#ckUnitMode') || {}).value || 'cm-m';
                var val = parseFloat((wrap.querySelector('#ckUnitVal') || {}).value);
                var out = val;
                var label = mode;
                if (mode === 'cm-m') { out = val / 100; label = val + ' cm = ' + out + ' m'; }
                else if (mode === 'm-cm') { out = val * 100; label = val + ' m = ' + out + ' cm'; }
                else if (mode === 'g-kg') { out = val / 1000; label = val + ' g = ' + out + ' kg'; }
                else if (mode === 'kg-g') { out = val * 1000; label = val + ' kg = ' + out + ' g'; }
                else if (mode === 'c-k') { out = val + 273.15; label = val + ' °C = ' + out + ' K'; }
                else if (mode === 'k-c') { out = val - 273.15; label = val + ' K = ' + out + ' °C'; }
                centerPlace(api, 'unit', 220, 80, { title: 'Unit convert', text: label });
                closeModal();
            };
            openModal('Unit converter', wrap, [place]);
        };

        state.toggleSpotlight = function () {
            state.spotlightOn = !state.spotlightOn;
            chrome.spot.hidden = !state.spotlightOn;
            if (typeof api.publishData === 'function') {
                api.publishData({ t: 'wb-spotlight', x: 0.5, y: 0.5, on: state.spotlightOn });
            }
            toastSafe(api, state.spotlightOn ? 'Spotlight on' : 'Spotlight off');
        };

        state.saveSnapshot = function () {
            var lesson = (hooks.lessonId || hooks.lesson || 'default');
            var payload = {
                log: api.getLog ? api.getLog() : null,
                scene: api.getScene ? api.getScene() : null,
                page: api.getPage ? api.getPage() : 0,
                ts: Date.now()
            };
            writeJson(SNAP_PREFIX + lesson, payload);
            toastSafe(api, 'Snapshot saved');
        };

        state.restoreSnapshot = function () {
            var lesson = (hooks.lessonId || hooks.lesson || 'default');
            var payload = readJson(SNAP_PREFIX + lesson, null);
            if (!payload) { toastSafe(api, 'No snapshot'); return; }
            if (hooks.onRestoreSnapshot) hooks.onRestoreSnapshot(payload);
            else if (api.replay && payload.log) {
                toastSafe(api, 'Snapshot found — use host restore hook');
            } else toastSafe(api, 'Snapshot restore needs board hook');
        };

        state.exportJson = function () {
            var data = {
                scene: api.getScene ? api.getScene() : null,
                log: api.getLog ? api.getLog() : null,
                page: api.getPage ? api.getPage() : 0
            };
            var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'board-export.json';
            a.click();
            setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
        };

        function renderRail() {
            var rail = chrome.rail;
            rail.innerHTML = '';
            var tools = [
                ['select', 'Select', 'bi-cursor'],
                ['pen', 'Pen', 'bi-pencil'],
                ['highlight', 'Highlighter', 'bi-highlighter'],
                ['erase', 'Eraser', 'bi-eraser'],
                ['shapes', 'Shapes', 'bi-square'],
                ['line', 'Line', 'bi-slash-lg'],
                ['arrow', 'Arrow', 'bi-arrow-up-right'],
                ['text', 'Text', 'bi-type'],
                ['sticky', 'Sticky', 'bi-sticky'],
                ['laser', 'Laser', 'bi-record-circle'],
                ['pan', 'Pan', 'bi-arrows-move']
            ];
            var group = el('div', 'ck-rail-primary');
            tools.forEach(function (row) {
                var b = primaryToolBtn(row[0], row[1], row[2]);
                b.onclick = function () {
                    if (row[0] === 'shapes') {
                        var menu = window.prompt('Shape: rect or ellipse', 'rect');
                        if (menu === 'ellipse' || menu === 'rect') api.setTool && api.setTool(menu);
                        return;
                    }
                    if (api.setTool) api.setTool(row[0]);
                    refreshCtx();
                    syncRailActive();
                };
                group.appendChild(b);
            });
            rail.appendChild(group);
            syncRailActive();
        }

        state.refreshRecent = function () { renderRail(); };

        function syncRailActive() {
            var cur = api.getTool ? api.getTool() : '';
            chrome.rail.querySelectorAll('[data-tool]').forEach(function (b) {
                if (b.getAttribute('data-tool') === cur) b.classList.add('is-on');
                else b.classList.remove('is-on');
            });
        }

        function runTool(tool) {
            if (!tool) return;
            pushRecent(tool.id);
            if (tool.action) tool.action();
            renderRail();
            renderPanel();
        }

        function renderTop() {
            var top = chrome.top;
            if (!(api.isHost && api.isHost())) {
                if (top && top.parentNode) top.parentNode.removeChild(top);
                chrome.top = null;
                return;
            }
            if (!top) return;
            if (top._ckBuilt) {
                var pageNameEl = top.querySelector('.ck-board-page-name');
                var page = api.getPage ? api.getPage() : 0;
                if (pageNameEl) pageNameEl.textContent = 'Page ' + ((page || 0) + 1);
                return;
            }
            top._ckBuilt = true;
            top.innerHTML = '';

            var pageName = el('div', 'ck-board-page-name', { id: 'ckBoardPageName' });
            var page = api.getPage ? api.getPage() : 0;
            pageName.textContent = 'Page ' + ((page || 0) + 1);
            top.appendChild(pageName);

            function adopt(id) {
                var node = $(id);
                if (!node) return false;
                // Prefer the control itself; if it's wrapped in a label, move the label.
                var move = (node.tagName === 'INPUT' || node.tagName === 'SELECT') && node.parentNode && node.parentNode.tagName === 'LABEL'
                    ? node.parentNode
                    : node;
                var wrap = el('span', 'ck-top-slot');
                if (move.parentNode) move.parentNode.removeChild(move);
                wrap.appendChild(move);
                top.appendChild(wrap);
                return true;
            }

            adopt('ckBoardUndo');
            adopt('ckBoardRedo');
            adopt('ckBoardZoomOut');
            adopt('ckBoardZoomIn');
            adopt('ckBoardZoomReset');
            adopt('ckBoardGrid');
            adopt('ckBoardBg');

            var subj = el('select', 'ck-board-subject', { id: 'ckBoardSubject', 'aria-label': 'Subject' });
            ['general', 'math', 'chem', 'phys'].forEach(function (s) {
                var o = el('option', '', { value: s, text: s === 'chem' ? 'Chem' : (s.charAt(0).toUpperCase() + s.slice(1)) });
                if (s === state.subject) o.selected = true;
                subj.appendChild(o);
            });
            subj.onchange = function () {
                state.subject = subj.value;
                openCategory(SUBJECT_ORDER[state.subject][0], true);
                renderPanel();
            };
            top.appendChild(subj);

            var presentBtn = el('button', 'ck-top-btn', { type: 'button', text: 'Present' });
            presentBtn.onclick = function () {
                var tool = byId('present');
                if (tool) runTool(tool);
            };
            top.appendChild(presentBtn);



            var saveBtn = el('button', 'ck-top-btn', { type: 'button', text: 'Save' });
            saveBtn.onclick = function () { state.saveSnapshot(); };
            top.appendChild(saveBtn);

            var expBtn = el('button', 'ck-top-btn', { type: 'button', text: 'Export' });
            expBtn.onclick = function () {
                if (api.exportPng) api.exportPng();
                else state.exportJson();
            };
            top.appendChild(expBtn);

            adopt('ckPdfToolsGroup');

            if (api.isHost && api.isHost()) {
                adopt('ckBoardClear');
                adopt('ckAllowDrawWrap') || adopt('ckAllowDraw');
            }
            adopt('ckBoardFs');

            if (chrome.legacy) {
                // Keep props in legacy but mark chrome present
                chrome.legacy.classList.add('ck-board-tools-legacy');
            }
        }

        function toolMatches(tool, q) {
            if (!q) return true;
            q = q.toLowerCase();
            if (tool.label.toLowerCase().indexOf(q) >= 0) return true;
            if (tool.id.toLowerCase().indexOf(q) >= 0) return true;
            var i;
            for (i = 0; i < (tool.keywords || []).length; i++) {
                if (String(tool.keywords[i]).toLowerCase().indexOf(q) >= 0) return true;
            }
            return false;
        }

        function renderPanel() {
            var panel = chrome.panel;
            var search = chrome.search;
            // keep search as first child
            var kids = Array.prototype.slice.call(panel.children);
            kids.forEach(function (c) {
                if (c !== search) panel.removeChild(c);
            });
            if (search.parentNode !== panel) panel.insertBefore(search, panel.firstChild);

            panel.classList.toggle('ck-is-open', state.toolsOpen);
            var q = (search.value || '').trim();
            var order = SUBJECT_ORDER[state.subject] || SUBJECT_ORDER.general;

            if (q) {
                var list = el('div', 'ck-edu-search-results');
                state.catalog.forEach(function (tool) {
                    if (!toolMatches(tool, q)) return;
                    list.appendChild(makeToolButton(tool));
                });
                if (!list.children.length) list.appendChild(el('div', 'ck-rail-empty', { text: 'No tools found' }));
                panel.appendChild(list);
                return;
            }

            var catsWrap = el('div', 'ck-edu-cats');
            panel.appendChild(catsWrap);
            order.forEach(function (catId, idx) {
                var meta = CAT_META[catId];
                if (!meta) return;
                var cat = el('div', 'ck-edu-cat' + (idx === 0 ? ' ck-is-open' : ''), { 'data-cat': catId });
                var head = el('button', 'ck-edu-cat-head', { type: 'button' });
                head.innerHTML = '<span>' + meta.label + '</span><i class="bi bi-chevron-down" aria-hidden="true"></i>';
                head.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    var open = cat.classList.toggle('ck-is-open');
                    head.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                head.setAttribute('aria-expanded', idx === 0 ? 'true' : 'false');
                cat.appendChild(head);
                var body = el('div', 'ck-edu-cat-body');
                state.catalog.forEach(function (tool) {
                    if (tool.cat !== catId) return;
                    body.appendChild(makeToolButton(tool));
                });
                cat.appendChild(body);
                catsWrap.appendChild(cat);
            });
        }

        function makeToolButton(tool) {
            var b = el('button', 'ck-edu-tool' + (isFav(tool.id) ? ' ck-fav' : ''), {
                type: 'button',
                title: tool.label,
                'data-tool-id': tool.id
            });
            var star = el('span', 'ck-edu-star', { title: 'Favorite' });
            star.innerHTML = isFav(tool.id) ? '★' : '☆';
            star.onclick = function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                toggleFav(tool.id);
                renderRail();
                renderPanel();
            };
            b.appendChild(star);
            var icon = el('i', 'bi ' + tool.icon);
            b.appendChild(icon);
            b.appendChild(el('span', '', { text: tool.label }));
            b.onclick = function () { runTool(tool); };
            return b;
        }

        function refreshCtx() {
            var ctx = chrome.ctx;
            ctx.innerHTML = '';
            var tool = api.getTool ? api.getTool() : '';
            var sel = api.getSelected ? api.getSelected() : null;
            var showDraw = ['pen', 'highlight', 'erase', 'line', 'arrow', 'rect', 'ellipse', 'text', 'sticky'].indexOf(tool) >= 0;

            function moveProp(id) {
                var n = $(id);
                if (!n) return;
                var wrap = n.closest ? n.closest('label') || n.parentNode : n.parentNode;
                if (wrap && wrap.className && String(wrap.className).indexOf('ck-bt-') >= 0) {
                    ctx.appendChild(wrap);
                } else if (n) ctx.appendChild(n);
            }

            if (showDraw) {
                moveProp('ckBoardColor');
                moveProp('ckBoardSize');
                if (tool === 'highlight') moveProp('ckBoardOpacity');
                if (tool === 'text' || tool === 'sticky') {
                    moveProp('ckBoardFont');
                    moveProp('ckBoardStyle');
                }
            }

            if (sel && (sel.tool === 'text' || sel.tool === 'sticky')) {
                var editTxt = el('button', 'ck-ctx-btn', { type: 'button', text: 'Edit text' });
                editTxt.onclick = function () {
                    if (api.editTextObject) api.editTextObject(sel);
                };
                ctx.appendChild(editTxt);
                ctx.classList.add('ck-is-open');
            }

            if (sel && sel.tool === 'edu') {
                var edit = el('button', 'ck-ctx-btn', { type: 'button', text: 'Edit' });
                edit.onclick = function () {
                    if (sel.kind === 'equation' || sel.kind === 'chem-eq' || sel.kind === 'phys-eq') {
                        var v = window.prompt('Edit text', (sel.data && (sel.data.latex || sel.data.text)) || '');
                        if (v == null) return;
                        var data = {};
                        var k;
                        if (sel.data) for (k in sel.data) if (sel.data.hasOwnProperty(k)) data[k] = sel.data[k];
                        data.text = v;
                        data.latex = v;
                        api.patchSelected && api.patchSelected({ data: data });
                    } else if (sel.kind === 'graph') {
                        var e = window.prompt('Expression', (sel.data && sel.data.expr) || 'x');
                        if (e == null) return;
                        api.patchSelected && api.patchSelected({ data: { expr: e } });
                    } else toastSafe(api, 'Use properties on the object');
                };
                ctx.appendChild(edit);

                var lock = el('button', 'ck-ctx-btn', { type: 'button', text: sel.locked ? 'Unlock' : 'Lock' });
                lock.onclick = function () {
                    api.patchSelected && api.patchSelected({ locked: !sel.locked });
                    refreshCtx();
                };
                ctx.appendChild(lock);

                if (sel.kind === 'reveal') {
                    var rev = el('button', 'ck-ctx-btn', { type: 'button', text: (sel.data && sel.data.revealed) ? 'Hide' : 'Reveal' });
                    rev.onclick = function () {
                        var data = {};
                        var k;
                        if (sel.data) for (k in sel.data) if (sel.data.hasOwnProperty(k)) data[k] = sel.data[k];
                        data.revealed = !data.revealed;
                        api.patchSelected && api.patchSelected({ data: data });
                        refreshCtx();
                    };
                    ctx.appendChild(rev);
                }

                var hide = el('button', 'ck-ctx-btn', { type: 'button', text: sel.hidden ? 'Unhide' : 'Hide' });
                hide.onclick = function () {
                    api.patchSelected && api.patchSelected({ hidden: !sel.hidden });
                    refreshCtx();
                };
                ctx.appendChild(hide);
            }

            ctx.hidden = !ctx.children.length;
        }

        function openCategory(catId, expandOnly) {
            renderPanel();
            var cat = chrome.panel.querySelector('.ck-edu-cat[data-cat="' + catId + '"]');
            if (!cat) return;
            if (!expandOnly) {
                chrome.panel.querySelectorAll('.ck-edu-cat').forEach(function (c) {
                    c.classList.remove('ck-is-open');
                });
            }
            cat.classList.add('ck-is-open');
            cat.scrollIntoView({ block: 'nearest' });
        }

        function refresh() {
            renderTop();
            renderRail();
            renderPanel();
            refreshCtx();
            syncRailActive();
        }

        state.refreshChrome = refresh;

        chrome.search.addEventListener('input', function () {
            renderPanel();
        });

        if (chrome.spot) {
            stage.addEventListener('mousemove', function (ev) {
                if (!state.spotlightOn) return;
                var r = stage.getBoundingClientRect();
                var x = ((ev.clientX - r.left) / r.width) * 100;
                var y = ((ev.clientY - r.top) / r.height) * 100;
                chrome.spot.style.setProperty('--sx', x + '%');
                chrome.spot.style.setProperty('--sy', y + '%');
                if (typeof api.publishData === 'function') {
                    // throttle lightly via last ts
                    var now = Date.now();
                    if (!state._spotTs || now - state._spotTs > 80) {
                        state._spotTs = now;
                        api.publishData({ t: 'wb-spotlight', x: x / 100, y: y / 100, on: true });
                    }
                }
            });
        }

        if (typeof api.on === 'function') {
            api.on('select', refreshCtx);
            api.on('tool', function () { syncRailActive(); refreshCtx(); });
            api.on('page', function () { renderTop(); });
        }

        // Initial paint
        chrome.panel.classList.add('ck-is-open');
        refresh();
        openCategory((SUBJECT_ORDER[state.subject] || SUBJECT_ORDER.general)[0], true);

        return {
            draw: function (ctx, obj) { drawEdu(ctx, obj, api); },
            refresh: refresh,
            openCategory: openCategory
        };
    }

    global.CKBoardEdu = {
        mount: mount,
        draw: function (ctx, obj, api) { drawEdu(ctx, obj, api || {}); }
    };
})(window);
