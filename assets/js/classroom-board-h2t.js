/**
 * Handwriting → real editable Arial text for the classroom whiteboard.
 * window.CKBoardH2T.mount(api)
 *
 * Flow: select tool → draw marquee → OCR → preview → delete ink + create text object
 * (same tool:'text' payload as the Aa text tool — never image/SVG/path groups)
 */
(function (global) {
    'use strict';

    var CANVAS_W = 900;
    var CANVAS_H = 560;
    var INK_TOOLS = { pen: 1, highlight: 1 };
    var DEFAULT_FONT = 'arial';
    var DEFAULT_SIZE = 24;

    var SCIENTIFIC_TERMS = [
        'photosynthesis', 'stoichiometry', 'mitochondria', 'acceleration',
        'differentiation', 'integration', 'hydrochloric', 'chloride',
        'oxidation', 'equilibrium', 'chlorophyll', 'respiration',
        'velocity', 'momentum', 'enthalpy', 'entropy', 'alkane', 'alkene',
        'electron', 'neutron', 'proton', 'isotope', 'molecule', 'compound',
        'catalyst', 'precipitate', 'titration', 'molarity', 'molal',
        'quadratic', 'polynomial', 'hypotenuse', 'perpendicular', 'parallel',
        'circumference', 'diameter', 'radius', 'theorem', 'coefficient'
    ];

    var SUB_MAP = { '0': '₀', '1': '₁', '2': '₂', '3': '₃', '4': '₄', '5': '₅', '6': '₆', '7': '₇', '8': '₈', '9': '₉' };
    var SUP_MAP = { '0': '⁰', '1': '¹', '2': '²', '3': '³', '4': '⁴', '5': '⁵', '6': '⁶', '7': '⁷', '8': '⁸', '9': '⁹', '+': '⁺', '-': '⁻', 'n': 'ⁿ' };

    function MathRecognitionHandler() {}
    MathRecognitionHandler.prototype.enhance = function (text, latex) {
        var t = String(text || '');
        t = t.replace(/\b([a-zA-Z])\^2\b/g, '$1²');
        t = t.replace(/\b([a-zA-Z])\^3\b/g, '$1³');
        t = t.replace(/\b([a-zA-Z])\^(\d+)\b/g, function (_, base, n) {
            return base + String(n).split('').map(function (c) { return SUP_MAP[c] || c; }).join('');
        });
        t = t.replace(/\bsqrt\(([^)]+)\)/gi, '√($1)');
        t = t.replace(/\bpi\b/gi, 'π');
        t = t.replace(/\btheta\b/gi, 'θ');
        t = t.replace(/\bdelta\b/gi, 'Δ');
        t = t.replace(/\binfinity\b/gi, '∞');
        t = t.replace(/<=/g, '≤').replace(/>=/g, '≥').replace(/!=/g, '≠').replace(/->/g, '→');
        return { text: t, latex: latex || t };
    };
    MathRecognitionHandler.prototype.looksLikeMath = function (text) {
        var t = String(text || '');
        return /[=∑∫√πθΔ∞≤≥≠→²³ⁿ]|[a-zA-Z]\^[0-9]|[xy]\s*[+\-]\s*\d/i.test(t)
            || /\b(sin|cos|tan|log|ln|dx|dy)\b/i.test(t);
    };

    function ChemistryRecognitionHandler() {}
    ChemistryRecognitionHandler.prototype.formatFormula = function (raw) {
        return String(raw || '').replace(/([A-Z][a-z]?)(\d+)/g, function (_, el, n) {
            return el + String(n).split('').map(function (c) { return SUB_MAP[c] || c; }).join('');
        }).replace(/->/g, '→').replace(/=>/g, '→');
    };
    ChemistryRecognitionHandler.prototype.enhance = function (text, latex) {
        var t = this.formatFormula(text);
        return { text: t, latex: latex || String(text || '') };
    };
    ChemistryRecognitionHandler.prototype.looksLikeChem = function (text) {
        var t = String(text || '');
        return /\b(H2O|CO2|NaCl|H2SO4|CaCO3|NH3|O2|N2|Fe2O3)\b/i.test(t)
            || /([A-Z][a-z]?\d+)+\s*([+=→\-]|->)/.test(t)
            || (/[A-Z][a-z]?\d/.test(t) && /→|->|=/.test(t));
    };

    function TextCorrectionService() {
        this.math = new MathRecognitionHandler();
        this.chem = new ChemistryRecognitionHandler();
    }
    TextCorrectionService.prototype.preserveScientific = function (raw, corrected) {
        var out = String(corrected || raw || '');
        var lowerRaw = String(raw || '').toLowerCase();
        SCIENTIFIC_TERMS.forEach(function (term) {
            if (lowerRaw.indexOf(term) >= 0) {
                var re = new RegExp('\\b' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'ig');
                out = out.replace(re, function (m) {
                    if (m[0] === m[0].toUpperCase() && m.slice(1) === m.slice(1).toLowerCase()) {
                        return term.charAt(0).toUpperCase() + term.slice(1);
                    }
                    return term;
                });
            }
        });
        return out;
    };
    TextCorrectionService.prototype.lightPolish = function (text) {
        var t = String(text || '').replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
        t = t.replace(/\s+([,.;:!?])/g, '$1');
        t = t.replace(/([.!?])\s*([a-z])/g, function (_, p, c) { return p + ' ' + c.toUpperCase(); });
        if (t && /^[a-z]/.test(t)) t = t.charAt(0).toUpperCase() + t.slice(1);
        return t;
    };
    TextCorrectionService.prototype.process = function (result, subject) {
        result = result || {};
        var type = result.type || 'text';
        var raw = String(result.raw_text || '');
        var corrected = this.preserveScientific(raw, result.corrected_text || raw);
        corrected = this.lightPolish(corrected);
        var latex = String(result.latex || '');

        if (type === 'chemistry' || subject === 'chem' || this.chem.looksLikeChem(corrected) || this.chem.looksLikeChem(raw)) {
            var c = this.chem.enhance(corrected, latex || raw);
            corrected = c.text;
            latex = c.latex;
            type = 'chemistry';
        } else if (type === 'math' || subject === 'math' || this.math.looksLikeMath(corrected) || this.math.looksLikeMath(raw)) {
            var m = this.math.enhance(corrected, latex || corrected);
            corrected = m.text;
            latex = m.latex;
            type = type === 'physics' ? 'physics' : 'math';
        }

        return {
            raw_text: raw,
            corrected_text: corrected,
            confidence: typeof result.confidence === 'number' ? result.confidence : 0.7,
            type: type,
            latex: latex,
            alternatives: Array.isArray(result.alternatives) ? result.alternatives : [],
            corrections: Array.isArray(result.corrections) ? result.corrections : [],
            uncertain: !!result.uncertain || (result.confidence != null && result.confidence < 0.65)
        };
    };

    function HandwritingRecognitionService(api) {
        this.api = api;
        this.corrector = new TextCorrectionService();
    }
    HandwritingRecognitionService.prototype.recognize = function (imageDataUrl, subject, hint) {
        var self = this;
        var call = this.api.apiCall || null;
        if (!call) {
            return Promise.resolve({ ok: false, error: 'Recognition API unavailable.' });
        }
        return call('handwriting.php', {
            image: imageDataUrl,
            subject: subject || 'general',
            hint: hint || ''
        }).then(function (data) {
            if (!data || !data.ok) {
                return { ok: false, error: (data && data.error) || 'Recognition failed.' };
            }
            var processed = self.corrector.process(data, subject || 'general');
            processed.ok = true;
            return processed;
        }).catch(function () {
            return { ok: false, error: 'Could not reach recognition service.' };
        });
    };

    function RecognitionPreview() {
        this.el = null;
        this._onApply = null;
        this._onCancel = null;
        this._state = null;
    }
    RecognitionPreview.prototype.ensure = function () {
        if (this.el) return this.el;
        var el = document.createElement('div');
        el.className = 'ck-h2t-preview';
        el.hidden = true;
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-label', 'Handwriting recognition preview');
        el.innerHTML = [
            '<div class="ck-h2t-preview-card">',
            '  <div class="ck-h2t-preview-head">',
            '    <strong>Recognized text</strong>',
            '    <button type="button" class="ck-h2t-close" aria-label="Cancel">&times;</button>',
            '  </div>',
            '  <div class="ck-h2t-preview-body">',
            '    <label class="ck-h2t-label">OCR result</label>',
            '    <div class="ck-h2t-raw" data-role="raw"></div>',
            '    <label class="ck-h2t-label">Suggested correction (Arial text)</label>',
            '    <textarea class="ck-h2t-edit" data-role="edit" rows="4" maxlength="800"></textarea>',
            '    <div class="ck-h2t-meta" data-role="meta"></div>',
            '    <div class="ck-h2t-alts" data-role="alts" hidden></div>',
            '    <p class="ck-h2t-note">Apply removes the handwriting and places editable Arial text in the same spot.</p>',
            '  </div>',
            '  <div class="ck-h2t-preview-foot">',
            '    <button type="button" class="ck-h2t-btn ck-h2t-apply" data-role="apply">Apply</button>',
            '    <button type="button" class="ck-h2t-btn" data-role="edit">Edit</button>',
            '    <button type="button" class="ck-h2t-btn" data-role="cancel">Cancel</button>',
            '  </div>',
            '</div>'
        ].join('');
        var host = document.getElementById('ckBoardStage') || document.body;
        host.appendChild(el);
        this.el = el;
        var self = this;
        function cancel() {
            self.hide();
            if (self._onCancel) self._onCancel();
        }
        el.querySelector('.ck-h2t-close').addEventListener('click', cancel);
        el.querySelector('[data-role="cancel"]').addEventListener('click', cancel);
        el.querySelector('[data-role="apply"]').addEventListener('click', function () {
            var st = self.snapshot();
            self.hide();
            if (self._onApply) self._onApply(st);
        });
        el.querySelector('[data-role="edit"]').addEventListener('click', function () {
            var ta = el.querySelector('[data-role="edit"]');
            if (ta) {
                ta.focus();
                ta.select();
            }
        });
        el.addEventListener('click', function (ev) {
            if (ev.target === el) cancel();
        });
        return el;
    };
    RecognitionPreview.prototype.snapshot = function () {
        var el = this.ensure();
        var edit = el.querySelector('[data-role="edit"]');
        return {
            text: edit ? String(edit.value || '').trim() : '',
            state: this._state
        };
    };
    RecognitionPreview.prototype.show = function (processed, handlers) {
        var el = this.ensure();
        this._state = processed;
        this._onApply = handlers && handlers.onApply;
        this._onCancel = handlers && handlers.onCancel;
        var raw = el.querySelector('[data-role="raw"]');
        var edit = el.querySelector('[data-role="edit"]');
        var meta = el.querySelector('[data-role="meta"]');
        var alts = el.querySelector('[data-role="alts"]');
        if (raw) raw.textContent = processed.raw_text || '—';
        if (edit) edit.value = processed.corrected_text || processed.raw_text || '';
        var confPct = Math.round((processed.confidence || 0) * 100);
        var bits = ['Confidence ' + confPct + '%', 'Font: Arial'];
        if (processed.type && processed.type !== 'text') bits.push('Detected: ' + processed.type);
        if (processed.uncertain) bits.push('Low confidence — please review');
        if (meta) meta.textContent = bits.join(' · ');
        if (alts) {
            alts.innerHTML = '';
            var list = processed.alternatives || [];
            if (processed.uncertain && list.length) {
                alts.hidden = false;
                var title = document.createElement('div');
                title.className = 'ck-h2t-alts-title';
                title.textContent = 'Did you mean…';
                alts.appendChild(title);
                list.forEach(function (alt) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'ck-h2t-alt';
                    b.textContent = alt.text + (alt.confidence != null ? ' (' + Math.round(alt.confidence * 100) + '%)' : '');
                    b.addEventListener('click', function () {
                        if (edit) edit.value = alt.text;
                    });
                    alts.appendChild(b);
                });
            } else {
                alts.hidden = true;
            }
        }
        el.hidden = false;
        if (edit) {
            setTimeout(function () { try { edit.focus(); } catch (e) {} }, 40);
        }
    };
    RecognitionPreview.prototype.hide = function () {
        if (this.el) this.el.hidden = true;
    };
    RecognitionPreview.prototype.isOpen = function () {
        return !!(this.el && !this.el.hidden);
    };

    function unionBounds(api, objects) {
        var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        (objects || []).forEach(function (o) {
            var b = api.boundsOf(o);
            if (!b) return;
            minX = Math.min(minX, b.x);
            minY = Math.min(minY, b.y);
            maxX = Math.max(maxX, b.x + b.w);
            maxY = Math.max(maxY, b.y + b.h);
        });
        if (!isFinite(minX)) return null;
        return { x: minX, y: minY, w: Math.max(8, maxX - minX), h: Math.max(8, maxY - minY) };
    }

    function inkInRect(api, x1, y1, x2, y2) {
        var scene = api.getScene();
        var page = api.getPage();
        var ids = [];
        var objects = [];
        scene.order.forEach(function (id) {
            var o = scene.objects[id];
            if (!o || !INK_TOOLS[o.tool] || o.hidden) return;
            if (typeof o.page === 'number' && o.page !== page) return;
            var b = api.boundsOf(o);
            if (!b) return;
            if (b.x + b.w < x1 || b.x > x2 || b.y + b.h < y1 || b.y > y2) return;
            ids.push(id);
            objects.push(o);
        });
        return { ids: ids, objects: objects };
    }

    function renderInkCrop(api, objects) {
        if (!objects || !objects.length) return null;
        var b = unionBounds(api, objects);
        if (!b) return null;
        var pad = 16;
        var minX = Math.max(0, b.x - pad);
        var minY = Math.max(0, b.y - pad);
        var maxX = Math.min(CANVAS_W, b.x + b.w + pad);
        var maxY = Math.min(CANVAS_H, b.y + b.h + pad);
        var w = Math.max(8, Math.ceil(maxX - minX));
        var h = Math.max(8, Math.ceil(maxY - minY));
        var scale = Math.min(2.5, Math.max(1.25, 1000 / Math.max(w, h)));
        var c = document.createElement('canvas');
        c.width = Math.round(w * scale);
        c.height = Math.round(h * scale);
        var ctx = c.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.save();
        ctx.scale(scale, scale);
        ctx.translate(-minX, -minY);
        objects.forEach(function (o) {
            // Draw ink only (drawObject skips hidden/op/wrong page)
            api.drawObject(ctx, o);
        });
        ctx.restore();
        try {
            return {
                dataUrl: c.toDataURL('image/png'),
                bounds: { x: b.x, y: b.y, w: b.w, h: b.h }
            };
        } catch (e) {
            return null;
        }
    }

    function estimateFontSize(bounds, text) {
        var lines = Math.max(1, String(text || '').split('\n').length);
        var fromH = Math.round((bounds.h || 40) / lines * 0.72);
        return Math.max(16, Math.min(36, fromH || DEFAULT_SIZE));
    }

    function mount(api) {
        api = api || {};
        var tipEl = null;
        var busyEl = null;
        var highlightEl = null;
        var busy = false;
        var dragStart = null;
        var marquee = null;
        var recognizer = new HandwritingRecognitionService(api);
        var preview = new RecognitionPreview();
        var pendingBounds = null;
        var pendingObjects = null;

        function toast(msg) {
            if (api.toast) api.toast(msg);
        }

        function subjectMode() {
            var el = document.getElementById('ckBoardSubject');
            return (el && el.value) || 'general';
        }

        function ensureTip() {
            if (tipEl) return tipEl;
            tipEl = document.createElement('div');
            tipEl.className = 'ck-h2t-tip';
            tipEl.hidden = true;
            tipEl.textContent = 'Draw a box around handwriting. It will be recognized and replaced with editable Arial text.';
            var stage = document.getElementById('ckBoardStage');
            if (stage) stage.appendChild(tipEl);
            return tipEl;
        }

        function ensureBusy() {
            if (busyEl) return busyEl;
            busyEl = document.createElement('div');
            busyEl.className = 'ck-h2t-busy';
            busyEl.hidden = true;
            busyEl.innerHTML = '<div class="ck-h2t-busy-card">Recognizing handwriting…</div>';
            var stage = document.getElementById('ckBoardStage');
            if (stage) stage.appendChild(busyEl);
            return busyEl;
        }

        function setBusy(on) {
            busy = !!on;
            ensureBusy().hidden = !on;
        }

        function clearHighlight() {
            if (highlightEl && highlightEl.parentNode) highlightEl.parentNode.removeChild(highlightEl);
            highlightEl = null;
        }

        function showHighlight(bounds) {
            clearHighlight();
            if (!bounds) return;
            var overlay = document.getElementById('ckBoardOverlay');
            if (!overlay) return;
            highlightEl = document.createElement('div');
            highlightEl.className = 'ck-h2t-region';
            highlightEl.style.left = (bounds.x / CANVAS_W * 100) + '%';
            highlightEl.style.top = (bounds.y / CANVAS_H * 100) + '%';
            highlightEl.style.width = (bounds.w / CANVAS_W * 100) + '%';
            highlightEl.style.height = (bounds.h / CANVAS_H * 100) + '%';
            overlay.appendChild(highlightEl);
        }

        function clearMarquee() {
            if (marquee && marquee.parentNode) marquee.parentNode.removeChild(marquee);
            marquee = null;
            dragStart = null;
        }

        function setToolUi(active) {
            var tip = ensureTip();
            tip.hidden = !active;
            if (!active) {
                clearMarquee();
                clearHighlight();
                preview.hide();
                setBusy(false);
                pendingBounds = null;
                pendingObjects = null;
            }
        }

        function runRecognize(objects) {
            if (busy) return;
            if (!api.canDraw || !api.canDraw()) {
                toast('You cannot draw on the board right now.');
                return;
            }
            if (!objects || !objects.length) {
                toast('No handwriting found in that selection. Draw a box around pen strokes.');
                return;
            }
            var crop = renderInkCrop(api, objects);
            if (!crop || !crop.dataUrl) {
                toast('Could not capture that handwriting.');
                return;
            }
            showHighlight(crop.bounds);
            setBusy(true);
            toast('Recognizing handwriting…');
            recognizer.recognize(crop.dataUrl, subjectMode(), '').then(function (res) {
                setBusy(false);
                if (!res.ok) {
                    clearHighlight();
                    toast(res.error || 'Recognition failed. Check that handwriting recognition is configured.');
                    return;
                }
                pendingBounds = crop.bounds;
                pendingObjects = objects.map(function (o) {
                    return api.deepClone ? api.deepClone(o) : JSON.parse(JSON.stringify(o));
                });
                preview.show(res, {
                    onApply: function (snap) { applyConversion(snap); },
                    onCancel: function () {
                        pendingBounds = null;
                        pendingObjects = null;
                        clearHighlight();
                    }
                });
            });
        }

        function applyConversion(snap) {
            var text = String((snap && snap.text) || '').trim();
            if (!text) {
                toast('Nothing to place.');
                clearHighlight();
                return;
            }
            var state = (snap && snap.state) || {};
            var bounds = pendingBounds || { x: 40, y: 40, w: 200, h: 60 };
            var ink = pendingObjects || [];
            pendingBounds = null;
            pendingObjects = null;
            clearHighlight();

            var type = state.type || 'text';
            var size = estimateFontSize(bounds, text);
            var created = null;
            var page = api.getPage ? api.getPage() : 0;
            var textId = api.uid ? api.uid() : ('o' + Date.now());

            // Prefer genuine Aa-style text. Only use edu equation objects for clear math/chem.
            if (type === 'chemistry' && api.placeEdu) {
                var chemText = new ChemistryRecognitionHandler().enhance(text, state.latex || text).text;
                created = api.placeEdu({
                    kind: 'chem-eq',
                    x: bounds.x,
                    y: bounds.y,
                    w: Math.max(160, bounds.w),
                    h: Math.max(56, Math.min(140, bounds.h + 12)),
                    data: { text: chemText, latex: state.latex || text },
                    page: page
                }, { skipUndo: true });
            } else if ((type === 'math' || type === 'physics') && api.placeEdu) {
                var math = new MathRecognitionHandler().enhance(text, state.latex || text);
                created = api.placeEdu({
                    kind: type === 'physics' ? 'phys-eq' : 'equation',
                    x: bounds.x,
                    y: bounds.y,
                    w: Math.max(160, bounds.w),
                    h: Math.max(56, Math.min(140, bounds.h + 12)),
                    data: { text: math.text, latex: math.latex },
                    page: page
                }, { skipUndo: true });
            } else {
                // Same shape as the existing Text (Aa) tool — real string text object.
                created = api.commitTextObject({
                    tool: 'text',
                    id: textId,
                    x: bounds.x + 2,
                    y: bounds.y + size,
                    text: text,
                    color: '#111827',
                    size: size,
                    font: DEFAULT_FONT,
                    style: 'normal',
                    align: 'left',
                    underline: false,
                    lineHeight: 1.35,
                    w: Math.max(80, bounds.w),
                    h: Math.max(24, bounds.h),
                    page: page
                }, { skipUndo: true });
            }

            if (!created || !created.id) {
                toast('Could not create the text object.');
                return;
            }

            var inkIds = ink.map(function (o) { return o.id; }).filter(Boolean);
            var undoInk = ink.map(function (o) {
                return api.deepClone ? api.deepClone(o) : JSON.parse(JSON.stringify(o));
            });

            // Always remove original handwriting — never leave strokes underneath.
            inkIds.forEach(function (id) {
                api.commitOp({ tool: 'op', op: 'delete', id: id }, null, { skipUndo: true });
            });

            api.pushUndo({
                kind: 'h2t',
                textId: created.id,
                textStroke: api.deepClone ? api.deepClone(created) : JSON.parse(JSON.stringify(created)),
                inkMode: 'replace',
                inkIds: inkIds,
                inkStrokes: undoInk
            });

            if (api.selectId) api.selectId(created.id);
            if (api.setTool) api.setTool('select');
            toast('Converted to editable Arial text.');
        }

        function start(ev, p) {
            if (!api.canDraw || !api.canDraw()) return false;
            if (busy || preview.isOpen()) return true;
            clearHighlight();
            dragStart = p;
            var overlay = document.getElementById('ckBoardOverlay');
            if (overlay) {
                clearMarquee();
                dragStart = p;
                marquee = document.createElement('div');
                marquee.className = 'ck-h2t-marquee';
                overlay.appendChild(marquee);
                move(ev, p);
            }
            return true;
        }

        function move(ev, p) {
            if (!dragStart || !marquee) return false;
            var x1 = Math.min(dragStart.x, p.x);
            var y1 = Math.min(dragStart.y, p.y);
            var x2 = Math.max(dragStart.x, p.x);
            var y2 = Math.max(dragStart.y, p.y);
            marquee.style.left = (x1 / CANVAS_W * 100) + '%';
            marquee.style.top = (y1 / CANVAS_H * 100) + '%';
            marquee.style.width = ((x2 - x1) / CANVAS_W * 100) + '%';
            marquee.style.height = ((y2 - y1) / CANVAS_H * 100) + '%';
            return true;
        }

        function end(ev, p) {
            if (!dragStart) return false;
            var x1 = Math.min(dragStart.x, p.x);
            var y1 = Math.min(dragStart.y, p.y);
            var x2 = Math.max(dragStart.x, p.x);
            var y2 = Math.max(dragStart.y, p.y);
            var tiny = (x2 - x1) < 8 && (y2 - y1) < 8;
            clearMarquee();

            var hit;
            if (tiny) {
                // Tap a stroke: convert that stroke only (still OCR → text, not keep as path).
                hit = api.hitTestInk ? api.hitTestInk(p.x, p.y) : null;
                if (!hit) {
                    toast('Draw a box around the handwriting to convert.');
                    return true;
                }
                var scene = api.getScene();
                var obj = scene.objects[hit];
                if (!obj) return true;
                runRecognize([obj]);
                return true;
            }

            var found = inkInRect(api, x1, y1, x2, y2);
            runRecognize(found.objects);
            return true;
        }

        return {
            onToolChange: function (tool) {
                setToolUi(tool === 'h2t');
            },
            start: start,
            move: move,
            end: end,
            repaint: function () {},
            isBusy: function () { return busy || preview.isOpen(); },
            clearSelection: function () {
                clearMarquee();
                clearHighlight();
            },
            convertSelected: function () {},
            getSelectedIds: function () { return []; }
        };
    }

    global.CKBoardH2T = { mount: mount };
})(typeof window !== 'undefined' ? window : this);
