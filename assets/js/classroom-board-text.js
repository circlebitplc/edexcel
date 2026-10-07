/**
 * Inline text editor + speech-to-text for the classroom whiteboard.
 * Mounts via window.CKBoardText.mount(api).
 */
(function (global) {
    'use strict';

    var CANVAS_W = 900;
    var CANVAS_H = 560;
    var MAX_LEN = 800;

    function fontStack(font) {
        if (font === 'serif') return 'Georgia, "Times New Roman", serif';
        if (font === 'mono') return '"Cascadia Code", Consolas, monospace';
        if (font === 'arial') return 'Arial, Helvetica, sans-serif';
        return '"Segoe UI", system-ui, sans-serif';
    }

    function Speech() {
        var SR = global.SpeechRecognition || global.webkitSpeechRecognition;
        this.supported = !!SR;
        this.rec = null;
        this.active = false;
        this._onresult = null;
        this._onerror = null;
        this._onend = null;
        if (SR) {
            this.rec = new SR();
            this.rec.continuous = true;
            this.rec.interimResults = true;
            this.rec.maxAlternatives = 1;
            try {
                this.rec.lang = (navigator.language || 'en-US');
            } catch (e) {}
        }
    }

    Speech.prototype.start = function (onresult, onerror, onend) {
        var self = this;
        if (!this.supported || !this.rec) {
            if (onerror) onerror({ error: 'not-supported' });
            return false;
        }
        this._onresult = onresult;
        this._onerror = onerror;
        this._onend = onend;
        this.rec.onresult = function (ev) {
            var finalText = '';
            var interim = '';
            for (var i = ev.resultIndex; i < ev.results.length; i++) {
                var chunk = ev.results[i][0].transcript || '';
                if (ev.results[i].isFinal) finalText += chunk;
                else interim += chunk;
            }
            if (self._onresult) self._onresult(finalText, interim);
        };
        this.rec.onerror = function (ev) {
            if (self._onerror) self._onerror(ev);
        };
        this.rec.onend = function () {
            self.active = false;
            if (self._onend) self._onend();
        };
        try {
            this.rec.start();
            this.active = true;
            return true;
        } catch (e) {
            this.active = false;
            if (onerror) onerror({ error: 'start-failed', message: String(e && e.message || e) });
            return false;
        }
    };

    Speech.prototype.stop = function () {
        if (!this.rec || !this.active) return;
        try { this.rec.stop(); } catch (e) {}
        this.active = false;
    };

    function mount(api) {
        api = api || {};
        var $ = api.$ || function (id) { return document.getElementById(id); };
        var speech = new Speech();
        var state = {
            open: false,
            id: null,
            isNew: false,
            sticky: false,
            baseText: '',
            interim: '',
            listening: false,
            fmt: {
                color: '#111827',
                size: 22,
                font: 'sans',
                style: 'normal',
                underline: false,
                align: 'left',
                bg: '',
                lineHeight: 1.35
            }
        };
        var root = null;
        var ta = null;
        var fmtBar = null;
        var micBtn = null;
        var editingIdHide = null;

        function toast(msg) {
            if (api.toast) api.toast(msg);
        }

        function ensureDom() {
            if (root) return root;
            var world = $('ckBoardWorld') || $('ckBoardViewport');
            if (!world) return null;
            root = document.createElement('div');
            root.className = 'ck-text-editor';
            root.hidden = true;
            root.setAttribute('aria-label', 'Text editor');

            fmtBar = document.createElement('div');
            fmtBar.className = 'ck-text-fmt';
            fmtBar.innerHTML = [
                '<label class="ck-text-fmt-item" title="Font"><select data-fmt="font">',
                '<option value="arial">Arial</option><option value="sans">Sans</option><option value="serif">Serif</option><option value="mono">Mono</option>',
                '</select></label>',
                '<label class="ck-text-fmt-item" title="Size"><select data-fmt="size">',
                [14, 16, 18, 20, 22, 24, 28, 32, 36, 48, 64].map(function (n) {
                    return '<option value="' + n + '">' + n + '</option>';
                }).join(''),
                '</select></label>',
                '<button type="button" data-fmt="bold" title="Bold"><b>B</b></button>',
                '<button type="button" data-fmt="italic" title="Italic"><i>I</i></button>',
                '<button type="button" data-fmt="underline" title="Underline"><u>U</u></button>',
                '<label class="ck-text-fmt-item" title="Color"><input type="color" data-fmt="color" value="#111827"></label>',
                '<label class="ck-text-fmt-item" title="Background"><input type="color" data-fmt="bg" value="#ffffff"></label>',
                '<button type="button" data-fmt="bgclear" title="Clear background">BG∅</button>',
                '<button type="button" data-fmt="align" data-align="left" title="Align left">≣</button>',
                '<button type="button" data-fmt="align" data-align="center" title="Align center">≡</button>',
                '<button type="button" data-fmt="align" data-align="right" title="Align right">☰</button>',
                '<button type="button" class="ck-text-mic" data-fmt="mic" title="Speech to text">🎤 Speak</button>',
                '<button type="button" data-fmt="done" title="Done (Esc)">Done</button>'
            ].join('');

            ta = document.createElement('textarea');
            ta.className = 'ck-text-area';
            ta.rows = 1;
            ta.setAttribute('spellcheck', 'true');
            ta.setAttribute('autocomplete', 'off');
            ta.setAttribute('aria-label', 'Board text');
            ta.maxLength = MAX_LEN;

            root.appendChild(fmtBar);
            root.appendChild(ta);
            world.appendChild(root);

            fmtBar.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
            fmtBar.addEventListener('click', onFmtClick);
            fmtBar.addEventListener('change', onFmtChange);
            ta.addEventListener('input', function () {
                state.baseText = ta.value;
                state.interim = '';
                autoSize();
            });
            ta.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape') {
                    ev.preventDefault();
                    commit(true);
                }
                // Enter inserts newline (default). Ctrl/Cmd+Enter finishes.
                if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) {
                    ev.preventDefault();
                    commit(true);
                }
            });
            document.addEventListener('pointerdown', onDocPointer, true);
            return root;
        }

        function onDocPointer(ev) {
            if (!state.open || !root) return;
            if (root.contains(ev.target)) return;
            // Allow clicking board tools without force-commit mid-gesture on rail
            var stage = $('ckBoardStage');
            if (stage && stage.contains(ev.target) && !($('ckBoard') && $('ckBoard').contains(ev.target))) {
                return;
            }
            commit(true);
        }

        function applyFmtToArea() {
            if (!ta) return;
            var f = state.fmt;
            var italic = f.style.indexOf('italic') >= 0 ? 'italic' : 'normal';
            var weight = f.style.indexOf('bold') >= 0 ? '700' : '400';
            ta.style.fontFamily = fontStack(f.font);
            ta.style.fontSize = f.size + 'px';
            ta.style.fontStyle = italic;
            ta.style.fontWeight = weight;
            ta.style.textDecoration = f.underline ? 'underline' : 'none';
            ta.style.color = f.color || '#111827';
            ta.style.textAlign = f.align || 'left';
            ta.style.lineHeight = String(f.lineHeight || 1.35);
            ta.style.background = f.bg ? f.bg : (state.sticky ? '#fef08a' : 'rgba(255,255,255,0.96)');
            syncFmtButtons();
        }

        function syncFmtButtons() {
            if (!fmtBar) return;
            var f = state.fmt;
            var fontSel = fmtBar.querySelector('[data-fmt="font"]');
            var sizeSel = fmtBar.querySelector('[data-fmt="size"]');
            var color = fmtBar.querySelector('[data-fmt="color"]');
            var bg = fmtBar.querySelector('[data-fmt="bg"]');
            if (fontSel) fontSel.value = f.font;
            if (sizeSel) sizeSel.value = String(f.size);
            if (color) color.value = f.color || '#111827';
            if (bg) bg.value = f.bg || '#ffffff';
            fmtBar.querySelectorAll('[data-fmt="bold"]').forEach(function (b) {
                b.classList.toggle('is-on', f.style.indexOf('bold') >= 0);
            });
            fmtBar.querySelectorAll('[data-fmt="italic"]').forEach(function (b) {
                b.classList.toggle('is-on', f.style.indexOf('italic') >= 0);
            });
            fmtBar.querySelectorAll('[data-fmt="underline"]').forEach(function (b) {
                b.classList.toggle('is-on', !!f.underline);
            });
            fmtBar.querySelectorAll('[data-fmt="align"]').forEach(function (b) {
                b.classList.toggle('is-on', b.getAttribute('data-align') === f.align);
            });
            updateMicUi();
        }

        function toggleStyle(flag) {
            var hasBold = state.fmt.style.indexOf('bold') >= 0;
            var hasItalic = state.fmt.style.indexOf('italic') >= 0;
            if (flag === 'bold') hasBold = !hasBold;
            if (flag === 'italic') hasItalic = !hasItalic;
            if (hasBold && hasItalic) state.fmt.style = 'bolditalic';
            else if (hasBold) state.fmt.style = 'bold';
            else if (hasItalic) state.fmt.style = 'italic';
            else state.fmt.style = 'normal';
        }

        function onFmtClick(ev) {
            var btn = ev.target.closest('[data-fmt]');
            if (!btn || btn.tagName === 'SELECT' || btn.tagName === 'INPUT') return;
            var kind = btn.getAttribute('data-fmt');
            if (kind === 'bold') toggleStyle('bold');
            else if (kind === 'italic') toggleStyle('italic');
            else if (kind === 'underline') state.fmt.underline = !state.fmt.underline;
            else if (kind === 'align') state.fmt.align = btn.getAttribute('data-align') || 'left';
            else if (kind === 'bgclear') state.fmt.bg = '';
            else if (kind === 'mic') toggleMic();
            else if (kind === 'done') { commit(true); return; }
            applyFmtToArea();
            autoSize();
            ta.focus();
        }

        function onFmtChange(ev) {
            var el = ev.target;
            var kind = el.getAttribute('data-fmt');
            if (kind === 'font') state.fmt.font = el.value;
            if (kind === 'size') state.fmt.size = parseInt(el.value, 10) || 22;
            if (kind === 'color') state.fmt.color = el.value;
            if (kind === 'bg') state.fmt.bg = el.value;
            applyFmtToArea();
            autoSize();
            ta.focus();
        }

        function updateMicUi() {
            if (!micBtn) micBtn = fmtBar && fmtBar.querySelector('[data-fmt="mic"]');
            if (!micBtn) return;
            if (!speech.supported) {
                micBtn.disabled = true;
                micBtn.textContent = '🎤 N/A';
                micBtn.title = 'Speech recognition is not supported in this browser';
                micBtn.classList.remove('is-listening');
                return;
            }
            micBtn.disabled = false;
            if (state.listening) {
                micBtn.textContent = '🔴 Stop';
                micBtn.title = 'Stop listening';
                micBtn.classList.add('is-listening');
            } else {
                micBtn.textContent = '🎤 Speak';
                micBtn.title = 'Speech to text';
                micBtn.classList.remove('is-listening');
            }
        }

        function toggleMic() {
            if (state.listening) {
                speech.stop();
                state.listening = false;
                flushInterim();
                updateMicUi();
                return;
            }
            if (!speech.supported) {
                toast('Speech recognition is not available in this browser.');
                return;
            }
            var ok = speech.start(
                function (finalText, interim) {
                    if (finalText) {
                        appendSpeech(finalText);
                        state.interim = '';
                    }
                    if (interim) {
                        state.interim = interim;
                        showWithInterim();
                    } else if (!finalText) {
                        showWithInterim();
                    }
                },
                function (err) {
                    state.listening = false;
                    updateMicUi();
                    flushInterim();
                    var code = (err && err.error) || '';
                    if (code === 'not-allowed' || code === 'service-not-allowed') {
                        toast('Microphone permission was denied.');
                    } else if (code === 'not-supported') {
                        toast('Speech recognition is not supported here.');
                    } else if (code && code !== 'aborted' && code !== 'no-speech') {
                        toast('Speech recognition stopped.');
                    }
                },
                function () {
                    state.listening = false;
                    updateMicUi();
                    flushInterim();
                }
            );
            state.listening = !!ok;
            updateMicUi();
            if (ok) toast('Listening… speak now.');
        }

        function appendSpeech(chunk) {
            chunk = String(chunk || '').replace(/\s+/g, ' ').trim();
            if (!chunk) return;
            var cur = state.baseText || '';
            if (cur && !/\s$/.test(cur)) cur += ' ';
            // Capitalize sentence starts lightly
            if (!cur || /[.!?]\s*$/.test(cur)) {
                chunk = chunk.charAt(0).toUpperCase() + chunk.slice(1);
            }
            state.baseText = (cur + chunk).slice(0, MAX_LEN);
            ta.value = state.baseText;
            autoSize();
        }

        function showWithInterim() {
            var shown = state.baseText || '';
            if (state.interim) {
                if (shown && !/\s$/.test(shown)) shown += ' ';
                shown += state.interim;
            }
            ta.value = shown.slice(0, MAX_LEN);
            autoSize();
        }

        function flushInterim() {
            if (state.interim) {
                appendSpeech(state.interim);
                state.interim = '';
            }
            ta.value = state.baseText;
            autoSize();
        }

        function autoSize() {
            if (!ta || !root) return;
            ta.style.height = 'auto';
            var h = Math.max(state.fmt.size * 1.5, Math.min(CANVAS_H * 0.7, ta.scrollHeight + 4));
            ta.style.height = h + 'px';
            var minW = Math.max(140, state.fmt.size * 8);
            // Grow width with longest line, capped
            var lines = String(ta.value || ' ').split('\n');
            var longest = 0;
            for (var i = 0; i < lines.length; i++) longest = Math.max(longest, lines[i].length);
            var w = Math.min(CANVAS_W * 0.72, Math.max(minW, longest * state.fmt.size * 0.62 + 24));
            ta.style.width = w + 'px';
            root.style.width = Math.max(w, 220) + 'px';
        }

        function placeAt(x, y) {
            if (!root) return;
            var left = (x / CANVAS_W) * 100;
            var top = (y / CANVAS_H) * 100;
            // Keep editor on-board
            left = Math.max(1, Math.min(78, left));
            top = Math.max(1, Math.min(82, top));
            root.style.left = left + '%';
            root.style.top = top + '%';
            root.dataset.x = String(x);
            root.dataset.y = String(y);
        }

        function openEditor(opts) {
            opts = opts || {};
            if (!api.canDraw || !api.canDraw()) {
                toast('The board is view-only right now.');
                return;
            }
            ensureDom();
            if (!root) return;
            if (state.open) commit(false);

            state.open = true;
            state.id = opts.id || null;
            state.isNew = !opts.id;
            state.sticky = !!opts.sticky;
            state.baseText = opts.text || '';
            state.interim = '';
            state.listening = false;
            state.fmt = {
                color: opts.color || (api.getColor && api.getColor()) || '#111827',
                size: opts.size || 22,
                font: opts.font || 'sans',
                style: opts.style || 'normal',
                underline: !!opts.underline,
                align: opts.align || 'left',
                bg: opts.bg || '',
                lineHeight: opts.lineHeight || 1.35
            };
            editingIdHide = state.id;
            if (api.setEditingTextId) api.setEditingTextId(editingIdHide);
            placeAt(opts.x || 80, opts.y || 80);
            ta.value = state.baseText;
            root.hidden = false;
            root.classList.add('ck-is-open', 'ck-text-pop');
            applyFmtToArea();
            autoSize();
            setTimeout(function () {
                ta.focus();
                var len = ta.value.length;
                try { ta.setSelectionRange(len, len); } catch (e) {}
            }, 30);
            if (api.onTextEditOpen) api.onTextEditOpen(state);
        }

        function buildPayload() {
            var text = String(state.baseText || '').replace(/\r\n?/g, '\n').trim();
            if (!text) return null;
            var x = parseFloat(root.dataset.x || '80') || 80;
            var y = parseFloat(root.dataset.y || '80') || 80;
            // Canvas fillText uses baseline; store top-ish y for box top + ascent
            var payload = {
                tool: state.sticky ? 'sticky' : 'text',
                id: state.id || (api.uid ? api.uid() : ('o' + Date.now())),
                x: x,
                y: y,
                text: text.slice(0, MAX_LEN),
                color: state.fmt.color,
                size: state.fmt.size,
                font: state.fmt.font,
                style: state.fmt.style,
                align: state.fmt.align,
                underline: !!state.fmt.underline,
                lineHeight: state.fmt.lineHeight,
                page: api.getPage ? api.getPage() : 0
            };
            if (state.fmt.bg) payload.bg = state.fmt.bg;
            if (state.sticky) {
                payload.fill = state.fmt.bg || '#fef08a';
                payload.w = Math.max(120, parseFloat(ta.style.width) || 160);
                payload.h = Math.max(80, parseFloat(ta.style.height) || 100);
            } else {
                payload.w = Math.max(40, parseFloat(ta.style.width) || 160);
                payload.h = Math.max(24, parseFloat(ta.style.height) || 40);
            }
            return payload;
        }

        function commit(close) {
            if (!state.open) return;
            if (state.listening) {
                speech.stop();
                state.listening = false;
                flushInterim();
            }
            var payload = buildPayload();
            var wasNew = state.isNew;
            var id = state.id;
            if (close !== false) {
                root.hidden = true;
                root.classList.remove('ck-is-open', 'ck-text-pop');
                state.open = false;
                editingIdHide = null;
                if (api.setEditingTextId) api.setEditingTextId(null);
            }
            if (!payload) {
                if (wasNew) {
                    // discarded empty new text
                    if (api.replay) api.replay();
                }
                return;
            }
            if (wasNew || !id) {
                if (api.commitText) api.commitText(payload);
                else if (api.commitOp) api.commitOp(payload);
            } else {
                var patch = {
                    text: payload.text,
                    color: payload.color,
                    size: payload.size,
                    font: payload.font,
                    style: payload.style,
                    align: payload.align,
                    underline: payload.underline,
                    lineHeight: payload.lineHeight,
                    bg: payload.bg || '',
                    w: payload.w,
                    h: payload.h
                };
                if (api.patchObject) api.patchObject(id, patch);
                else if (api.commitOp) {
                    api.commitOp({ tool: 'op', op: 'patch', id: id, patch: patch });
                }
            }
            if (api.selectId) api.selectId(payload.id);
            if (api.setTool) api.setTool('select');
        }

        function cancel() {
            if (state.listening) speech.stop();
            state.listening = false;
            state.open = false;
            if (root) {
                root.hidden = true;
                root.classList.remove('ck-is-open');
            }
            editingIdHide = null;
            if (api.setEditingTextId) api.setEditingTextId(null);
            if (api.replay) api.replay();
        }

        return {
            openAt: function (x, y, sticky) {
                openEditor({
                    x: x,
                    y: y,
                    sticky: !!sticky,
                    color: api.getColor && api.getColor(),
                    size: sticky ? 16 : 22,
                    font: 'sans',
                    style: 'normal'
                });
            },
            editObject: function (obj) {
                if (!obj) return;
                openEditor({
                    id: obj.id,
                    x: obj.x,
                    y: obj.y - (obj.tool === 'text' ? 0 : 0),
                    text: obj.text || '',
                    color: obj.color,
                    size: obj.size,
                    font: obj.font,
                    style: obj.style,
                    underline: obj.underline,
                    align: obj.align,
                    bg: obj.bg || '',
                    lineHeight: obj.lineHeight,
                    sticky: obj.tool === 'sticky'
                });
            },
            commit: commit,
            cancel: cancel,
            isOpen: function () { return !!state.open; },
            editingId: function () { return editingIdHide; }
        };
    }

    /** Shared canvas text drawing for multiline formatted text */
    function drawTextObject(ctx, stroke, fontCssFn) {
        if (!stroke) return;
        var color = stroke.color || '#111';
        var size = stroke.size || 18;
        var lh = (stroke.lineHeight || 1.35) * size;
        var lines = String(stroke.text || '').split('\n');
        var x = stroke.x || 0;
        var y = stroke.y || 0;
        var align = stroke.align || 'left';
        var w = stroke.w || 0;

        ctx.save();
        ctx.globalAlpha = 1;
            if (stroke.bg) {
            var maxW = w;
            if (!maxW) {
                ctx.font = fontCssFn(stroke);
                for (var i = 0; i < lines.length; i++) {
                    maxW = Math.max(maxW, ctx.measureText(lines[i]).width);
                }
                maxW += 12;
            }
            var boxH = Math.max(stroke.h || 0, lines.length * lh + 8);
            ctx.fillStyle = stroke.bg;
            ctx.fillRect(x - 4, y - 4, maxW + 8, boxH);
        }
        ctx.fillStyle = color;
        ctx.font = fontCssFn(stroke);
        ctx.textAlign = align;
        ctx.textBaseline = 'top';
        var drawX = x;
        if (align === 'center' && w) drawX = x + w / 2;
        if (align === 'right' && w) drawX = x + w;
        for (var n = 0; n < lines.length; n++) {
            var line = lines[n];
            var ly = y + n * lh;
            ctx.fillText(line, drawX, ly);
            if (stroke.underline) {
                var tw = ctx.measureText(line).width;
                var ux = drawX;
                if (align === 'center') ux = drawX - tw / 2;
                if (align === 'right') ux = drawX - tw;
                ctx.beginPath();
                ctx.strokeStyle = color;
                ctx.lineWidth = Math.max(1, size / 14);
                ctx.moveTo(ux, ly + size + 1);
                ctx.lineTo(ux + tw, ly + size + 1);
                ctx.stroke();
            }
        }
        ctx.restore();
    }

    global.CKBoardText = {
        mount: mount,
        drawTextObject: drawTextObject,
        Speech: Speech
    };
})(window);
