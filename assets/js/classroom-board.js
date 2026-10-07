/**
 * Collaborative whiteboard engine for LiveKit classroom.
 * Syncs via { t: 'wb' } data packets + whiteboard.php persistence.
 * Freehand strokes also stream while the pen is down as { t: 'wb-ink' }
 * batches (pointerdown / pointermove), then one final { t: 'wb' } on pointerup.
 */
(function (global) {
    'use strict';

    var CANVAS_W = 900;
    var CANVAS_H = 560;
    var COLORS = ['#111827', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#2563eb', '#7c3aed', '#db2777', '#ffffff'];

    function uid() {
        return 'o' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
    }

    function clamp(n, a, b) {
        return Math.max(a, Math.min(b, n));
    }

    function deepClone(o) {
        return JSON.parse(JSON.stringify(o));
    }

    function fontStack(font) {
        if (font === 'serif') return 'Georgia, "Times New Roman", serif';
        if (font === 'mono') return '"Cascadia Code", Consolas, monospace';
        if (font === 'arial') return 'Arial, Helvetica, sans-serif';
        return '"Segoe UI", system-ui, sans-serif';
    }

    function fontCss(obj) {
        var size = obj.size || 18;
        var style = obj.style || 'normal';
        var italic = style.indexOf('italic') >= 0 ? 'italic ' : '';
        var weight = style.indexOf('bold') >= 0 ? '700 ' : '400 ';
        return italic + weight + size + 'px ' + fontStack(obj.font || 'sans');
    }

    function createBoard(hooks) {
        hooks = hooks || {};
        var $ = hooks.$;
        var api = hooks.api;
        var publishData = hooks.publishData;
        var toast = hooks.toast || function () {};
        var getCfg = hooks.getCfg || function () { return {}; };
        var boardCanDraw = hooks.boardCanDraw || function () { return false; };
        var onHostFocus = hooks.onHostFocus || function () {};
        var onStudentShow = hooks.onStudentShow || function () {};
        var isConnected = hooks.isConnected || function () { return false; };
        var localIdentity = hooks.localIdentity || function () { return 'local'; };

        var boardLog = [];
        var boardLiveWhileLoad = [];
        var boardFetchingFull = false;
        var boardLoadSeq = 0;
        var boardBusy = false;
        var boardPollTimer = null;
        var lastStrokeId = 0;
        var drawing = false;
        var points = [];
        var tool = 'pen';
        var boardSnap = null;
        var penColor = '#111827';
        var penWidth = 3;
        var highlightOpacity = 0.35;
        var textFont = 'sans';
        var textStyle = 'normal';
        var boardZoom = 1;
        var boardPanX = 0;
        var boardPanY = 0;
        var boardGrid = 'none';
        var boardBg = '#ffffff';
        var boardPage = 0;
        var pageNames = { 0: 'Page 1' };
        var drawPageFilter = null;
        var thumbPaintTimer = null;
        var eduUi = null;
        var textUi = null;
        var h2tUi = null;
        var editingTextId = null;
        var selectedId = null;
        var clipboard = null;
        var undoStack = [];
        var redoStack = [];
        var spacePan = false;
        var dragKind = '';
        var dragStart = null;
        var dragOrigin = null;
        var resizeCorner = '';
        var isPinching = false;
        var pinchStartDist = 0;
        var pinchStartZoom = 1;
        var pinchOrigin = { x: 0, y: 0 };
        var pinchStartMid = { x: 0, y: 0 };
        var lasers = {};
        var laserTimer = null;
        var laserNetAt = 0;
        var laserNetTimer = null;
        var laserNetPending = null;
        var wired = false;
        var toolsWired = false;
        var liveInk = null;
        var remoteInk = {};
        var finishedInkAt = {};
        var INK_MIN_GAP_MS = 28;

        function cfg() { return getCfg() || {}; }

        function canvas() { return $('ckBoard'); }

        function sceneFromLog() {
            var objects = {};
            var order = [];
            var bg = '#ffffff';
            var grid = 'none';
            boardLog.forEach(function (s, idx) {
                if (!s) return;
                if (s.tool === 'op') {
                    if (s.op === 'page') {
                        if (typeof s.page === 'number') {
                            // tracked outside; scene keeps all pages' objects
                        }
                        if (s.name && typeof s.page === 'number') {
                            // names applied by applyIncoming
                        }
                        return;
                    }
                    if (s.op === 'bg') {
                        if (s.bg) bg = s.bg;
                        if (s.grid) grid = s.grid;
                        return;
                    }
                    if (!s.id || !objects[s.id]) return;
                    if (s.op === 'delete') {
                        delete objects[s.id];
                        order = order.filter(function (id) { return id !== s.id; });
                        return;
                    }
                    if (s.op === 'patch' && s.patch) {
                        Object.keys(s.patch).forEach(function (k) {
                            objects[s.id][k] = s.patch[k];
                        });
                        return;
                    }
                    if (s.op === 'transform') {
                        var o = objects[s.id];
                        var dx = s.dx || 0;
                        var dy = s.dy || 0;
                        if (s.points && s.points.length) {
                            o.points = s.points;
                        } else if (o.points) {
                            o.points = o.points.map(function (p) {
                                return { x: p.x + dx, y: p.y + dy };
                            });
                        }
                        if (typeof s.x === 'number') o.x = s.x;
                        else if (typeof o.x === 'number') o.x += dx;
                        if (typeof s.y === 'number') o.y = s.y;
                        else if (typeof o.y === 'number') o.y += dy;
                        if (typeof s.w === 'number') o.w = s.w;
                        if (typeof s.h === 'number') o.h = s.h;
                    }
                    return;
                }
                var id = s.id || ('L' + idx);
                var obj = deepClone(s);
                obj.id = id;
                if (!objects[id]) order.push(id);
                objects[id] = obj;
            });
            return { objects: objects, order: order, bg: bg, grid: grid };
        }

        function boundsOf(obj) {
            if (!obj) return null;
            if (obj.tool === 'text') {
                var size = obj.size || 18;
                var lines = String(obj.text || '').split('\n');
                var lh = (obj.lineHeight || 1.35) * size;
                var maxChars = 1;
                for (var ti = 0; ti < lines.length; ti++) maxChars = Math.max(maxChars, lines[ti].length);
                var tw = obj.w || Math.min(700, Math.max(40, maxChars * size * 0.55));
                var th = obj.h || Math.max(lh, lines.length * lh);
                return { x: obj.x || 0, y: obj.y || 0, w: tw, h: th + 6 };
            }
            if (obj.tool === 'sticky' || obj.tool === 'edu') {
                return { x: obj.x || 0, y: obj.y || 0, w: obj.w || 160, h: obj.h || 120 };
            }
            var pts = obj.points || [];
            if (!pts.length) return null;
            var minX = pts[0].x; var maxX = pts[0].x;
            var minY = pts[0].y; var maxY = pts[0].y;
            for (var i = 1; i < pts.length; i++) {
                minX = Math.min(minX, pts[i].x);
                maxX = Math.max(maxX, pts[i].x);
                minY = Math.min(minY, pts[i].y);
                maxY = Math.max(maxY, pts[i].y);
            }
            var pad = (obj.width || 3) / 2 + 4;
            return { x: minX - pad, y: minY - pad, w: Math.max(8, maxX - minX + pad * 2), h: Math.max(8, maxY - minY + pad * 2) };
        }

        function hitTest(x, y) {
            var scene = sceneFromLog();
            for (var i = scene.order.length - 1; i >= 0; i--) {
                var id = scene.order[i];
                var obj = scene.objects[id];
                if (!obj || obj.tool === 'erase' || obj.hidden) continue;
                if (typeof obj.page === 'number' && obj.page !== boardPage) continue;
                var b = boundsOf(obj);
                if (!b) continue;
                if (x >= b.x && x <= b.x + b.w && y >= b.y && y <= b.y + b.h) {
                    return id;
                }
            }
            return null;
        }

        function hitTestInk(x, y) {
            var scene = sceneFromLog();
            for (var i = scene.order.length - 1; i >= 0; i--) {
                var id = scene.order[i];
                var obj = scene.objects[id];
                if (!obj || obj.hidden) continue;
                if (obj.tool !== 'pen' && obj.tool !== 'highlight') continue;
                if (typeof obj.page === 'number' && obj.page !== boardPage) continue;
                var b = boundsOf(obj);
                if (!b) continue;
                if (x >= b.x && x <= b.x + b.w && y >= b.y && y <= b.y + b.h) {
                    return id;
                }
            }
            return null;
        }

        function handleAt(x, y, b) {
            if (!b) return '';
            var hs = 8 / boardZoom;
            var corners = [
                { k: 'nw', x: b.x, y: b.y },
                { k: 'ne', x: b.x + b.w, y: b.y },
                { k: 'sw', x: b.x, y: b.y + b.h },
                { k: 'se', x: b.x + b.w, y: b.y + b.h }
            ];
            for (var i = 0; i < corners.length; i++) {
                var c = corners[i];
                if (Math.abs(x - c.x) <= hs && Math.abs(y - c.y) <= hs) return c.k;
            }
            return '';
        }

        function compactStroke(stroke) {
            if (!stroke) return null;
            if (stroke.tool === 'op') {
                var op = { tool: 'op', op: stroke.op };
                if (stroke.id) op.id = stroke.id;
                if (stroke.op === 'bg') {
                    op.grid = stroke.grid || 'none';
                    op.bg = stroke.bg || '#ffffff';
                    return op;
                }
                if (stroke.op === 'page') {
                    op.page = typeof stroke.page === 'number' ? stroke.page : 0;
                    if (stroke.name) op.name = String(stroke.name).slice(0, 40);
                    if (stroke.follow) op.follow = true;
                    return op;
                }
                if (stroke.op === 'delete') return stroke.id ? op : null;
                if (stroke.op === 'patch') {
                    if (!stroke.id || !stroke.patch) return null;
                    op.patch = stroke.patch;
                    return op;
                }
                if (stroke.op === 'transform') {
                    if (!stroke.id) return null;
                    op.dx = stroke.dx || 0;
                    op.dy = stroke.dy || 0;
                    if (stroke.points) op.points = stroke.points;
                    ['x', 'y', 'w', 'h'].forEach(function (k) {
                        if (typeof stroke[k] === 'number') op[k] = stroke[k];
                    });
                    return op;
                }
                return null;
            }
            if (stroke.tool === 'edu') {
                if (!stroke.kind) return null;
                var outE = {
                    tool: 'edu',
                    kind: stroke.kind,
                    x: stroke.x || 40,
                    y: stroke.y || 40,
                    w: stroke.w || 160,
                    h: stroke.h || 100,
                    color: stroke.color || '#111',
                    data: stroke.data || {},
                    page: typeof stroke.page === 'number' ? stroke.page : boardPage
                };
                if (stroke.id) outE.id = stroke.id;
                if (stroke.locked) outE.locked = true;
                if (stroke.hidden) outE.hidden = true;
                if (stroke.points && stroke.points.length) outE.points = stroke.points.slice(0, 40);
                return outE;
            }
            if (stroke.tool === 'text' || stroke.tool === 'sticky') {
                var t = String(stroke.text || '').replace(/\r\n?/g, '\n').trim().slice(0, stroke.tool === 'sticky' ? 400 : 800);
                if (!t) return null;
                var outT = {
                    tool: stroke.tool,
                    x: stroke.x,
                    y: stroke.y,
                    text: t,
                    color: stroke.color || '#111',
                    size: stroke.size || 18,
                    font: stroke.font || 'sans',
                    style: stroke.style || 'normal',
                    align: stroke.align || 'left',
                    lineHeight: stroke.lineHeight || 1.35,
                    page: typeof stroke.page === 'number' ? stroke.page : boardPage
                };
                if (stroke.id) outT.id = stroke.id;
                if (stroke.locked) outT.locked = true;
                if (stroke.hidden) outT.hidden = true;
                if (stroke.underline) outT.underline = true;
                if (stroke.bg) outT.bg = stroke.bg;
                if (typeof stroke.w === 'number') outT.w = stroke.w;
                if (typeof stroke.h === 'number') outT.h = stroke.h;
                if (stroke.tool === 'sticky') {
                    outT.fill = stroke.fill || '#fef08a';
                    outT.w = stroke.w || 160;
                    outT.h = stroke.h || 120;
                }
                return outT;
            }
            if (stroke.tool === 'rect' || stroke.tool === 'line' || stroke.tool === 'ellipse' || stroke.tool === 'arrow') {
                var pts = stroke.points || [];
                if (pts.length < 2) return null;
                var outS = {
                    tool: stroke.tool,
                    points: [pts[0], pts[pts.length - 1]],
                    color: stroke.color || '#111',
                    width: stroke.width || 3,
                    page: typeof stroke.page === 'number' ? stroke.page : boardPage
                };
                if (stroke.id) outS.id = stroke.id;
                if (stroke.opacity != null) outS.opacity = stroke.opacity;
                if (stroke.filled) outS.filled = true;
                if (stroke.locked) outS.locked = true;
                return outS;
            }
            var p = samplePoints(stroke.points || [], 200).map(roundPt);
            if (p.length < 2) return null;
            var out = {
                tool: stroke.tool || 'pen',
                points: p,
                color: stroke.color || '#111',
                width: stroke.width || 3,
                page: typeof stroke.page === 'number' ? stroke.page : boardPage
            };
            if (stroke.id) out.id = stroke.id;
            if (stroke.opacity != null) out.opacity = stroke.opacity;
            if (stroke.locked) out.locked = true;
            if (stroke.hidden) out.hidden = true;
            return out;
        }

        function pushUndo(entry) {
            if (!entry) return;
            undoStack.push(entry);
            if (undoStack.length > 80) undoStack.shift();
            redoStack = [];
            syncEditButtons();
        }

        function syncEditButtons() {
            var undoBtn = $('ckBoardUndo');
            var redoBtn = $('ckBoardRedo');
            var dupBtn = $('ckBoardDup');
            var delBtn = $('ckBoardDelete');
            var can = boardCanDraw();
            if (undoBtn) undoBtn.disabled = !can || !undoStack.length;
            if (redoBtn) redoBtn.disabled = !can || !redoStack.length;
            if (dupBtn) dupBtn.disabled = !can || !selectedId;
            if (delBtn) delBtn.disabled = !can || !selectedId;
        }

        function commitStroke(stroke, undoEntry, opts) {
            stroke = compactStroke(stroke);
            if (!stroke) return;
            opts = opts || {};
            boardLog.push(stroke);
            if (!opts.skipLocalDraw) replay();
            api('whiteboard.php', { action: 'stroke', stroke: stroke }).then(function (data) {
                if (!data.ok) toast(data.error || 'Could not save that drawing.');
                if (data && data.id) lastStrokeId = Math.max(lastStrokeId, parseInt(data.id, 10) || 0);
            });
            publishData({ t: 'wb', stroke: stroke });
            if (!opts.skipUndo) {
                if (undoEntry) pushUndo(undoEntry);
                else if (stroke.tool !== 'op' && stroke.id) {
                    pushUndo({ kind: 'delete', id: stroke.id, stroke: deepClone(stroke) });
                }
            }
            syncEditButtons();
        }

        function applyIncoming(stroke) {
            if (!stroke) return;
            if (!pushLoggedStroke(stroke)) {
                replay();
                return;
            }
            if (stroke.tool === 'op' && stroke.op === 'bg') {
                boardGrid = stroke.grid || boardGrid;
                boardBg = stroke.bg || boardBg;
                syncBgUi();
            }
            if (stroke.tool === 'op' && stroke.op === 'page') {
                if (typeof stroke.page === 'number') boardPage = stroke.page;
                if (stroke.name) pageNames[boardPage] = stroke.name;
                syncPageUi();
            }
            replay();
            if (eduUi && eduUi.refresh) eduUi.refresh();
        }

        function snapshotBoard() {
            var c = canvas();
            if (!c) return;
            try {
                boardSnap = c.getContext('2d').getImageData(0, 0, c.width, c.height);
            } catch (e) {
                boardSnap = null;
            }
        }

        function restoreBoard() {
            var c = canvas();
            if (!c || !boardSnap) return;
            c.getContext('2d').putImageData(boardSnap, 0, 0);
        }

        function drawGrid(ctx, grid, w, h) {
            if (!grid || grid === 'none') return;
            ctx.save();
            ctx.strokeStyle = 'rgba(15, 23, 42, 0.12)';
            ctx.fillStyle = 'rgba(15, 23, 42, 0.18)';
            ctx.lineWidth = 1;
            var step = 28;
            if (grid === 'coords') {
                var ox = w / 2, oy = h / 2;
                ctx.strokeStyle = 'rgba(37, 99, 235, 0.35)';
                ctx.beginPath();
                ctx.moveTo(0, oy); ctx.lineTo(w, oy);
                ctx.moveTo(ox, 0); ctx.lineTo(ox, h);
                ctx.stroke();
                ctx.strokeStyle = 'rgba(15, 23, 42, 0.1)';
                ctx.beginPath();
                for (var gx = ox % step; gx < w; gx += step) { ctx.moveTo(gx + 0.5, 0); ctx.lineTo(gx + 0.5, h); }
                for (var gy = oy % step; gy < h; gy += step) { ctx.moveTo(0, gy + 0.5); ctx.lineTo(w, gy + 0.5); }
                ctx.stroke();
                ctx.restore();
                return;
            }
            if (grid === 'dot') {
                for (var x = step; x < w; x += step) {
                    for (var y = step; y < h; y += step) {
                        ctx.beginPath();
                        ctx.arc(x, y, 1.1, 0, Math.PI * 2);
                        ctx.fill();
                    }
                }
            } else {
                ctx.beginPath();
                for (var gx = step; gx < w; gx += step) {
                    ctx.moveTo(gx + 0.5, 0);
                    ctx.lineTo(gx + 0.5, h);
                }
                for (var gy = step; gy < h; gy += step) {
                    ctx.moveTo(0, gy + 0.5);
                    ctx.lineTo(w, gy + 0.5);
                }
                ctx.stroke();
            }
            ctx.restore();
        }

        function drawArrowHead(ctx, from, to, width) {
            var angle = Math.atan2(to.y - from.y, to.x - from.x);
            var len = 10 + width * 1.5;
            ctx.beginPath();
            ctx.moveTo(to.x, to.y);
            ctx.lineTo(to.x - len * Math.cos(angle - 0.4), to.y - len * Math.sin(angle - 0.4));
            ctx.lineTo(to.x - len * Math.cos(angle + 0.4), to.y - len * Math.sin(angle + 0.4));
            ctx.closePath();
            ctx.fill();
        }

        function drawObject(ctx, stroke) {
            if (!stroke) return;
            var toolName = stroke.tool || 'pen';
            if (toolName === 'op') return;
            if (stroke.hidden) return;
            var pageFilter = drawPageFilter == null ? boardPage : drawPageFilter;
            if (typeof stroke.page === 'number' && stroke.page !== pageFilter) return;
            if (toolName === 'edu') {
                if (global.CKBoardEdu && typeof global.CKBoardEdu.draw === 'function') {
                    global.CKBoardEdu.draw(ctx, stroke, {
                        isHost: function () { return !!(cfg().isHost); }
                    });
                }
                return;
            }

            ctx.save();
            var opacity = stroke.opacity != null ? stroke.opacity : 1;
            if (toolName === 'highlight') opacity = stroke.opacity != null ? stroke.opacity : 0.35;
            ctx.globalAlpha = opacity;

            var color = toolName === 'erase' ? '#ffffff' : (stroke.color || '#111');
            ctx.strokeStyle = color;
            ctx.fillStyle = color;
            ctx.lineWidth = stroke.width || 3;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            if (toolName === 'highlight') ctx.globalCompositeOperation = 'multiply';
            if (toolName === 'erase') {
                ctx.globalCompositeOperation = 'destination-out';
                ctx.strokeStyle = 'rgba(0,0,0,1)';
            }

            if (toolName === 'text') {
                if (editingTextId && stroke.id && stroke.id === editingTextId) {
                    ctx.restore();
                    return;
                }
                if (global.CKBoardText && typeof global.CKBoardText.drawTextObject === 'function') {
                    global.CKBoardText.drawTextObject(ctx, stroke, fontCss);
                } else {
                    ctx.globalAlpha = 1;
                    ctx.fillStyle = color;
                    ctx.font = fontCss(stroke);
                    ctx.fillText(String(stroke.text || '').slice(0, 800), stroke.x || 0, stroke.y || 0);
                }
                ctx.restore();
                return;
            }

            if (toolName === 'sticky') {
                if (editingTextId && stroke.id && stroke.id === editingTextId) {
                    ctx.restore();
                    return;
                }
                ctx.globalAlpha = 1;
                var sx = stroke.x || 0;
                var sy = stroke.y || 0;
                var sw = stroke.w || 160;
                var sh = stroke.h || 120;
                ctx.fillStyle = stroke.fill || '#fef08a';
                ctx.strokeStyle = 'rgba(15,23,42,0.12)';
                ctx.lineWidth = 1;
                ctx.beginPath();
                if (ctx.roundRect) ctx.roundRect(sx, sy, sw, sh, 8);
                else ctx.rect(sx, sy, sw, sh);
                ctx.fill();
                ctx.stroke();
                ctx.fillStyle = stroke.color || '#111827';
                ctx.font = fontCss(stroke);
                wrapText(ctx, String(stroke.text || ''), sx + 12, sy + 28, sw - 24, (stroke.size || 16) * 1.35);
                ctx.restore();
                return;
            }

            var pts = stroke.points || [];
            if ((toolName === 'line' || toolName === 'arrow') && pts.length >= 2) {
                ctx.beginPath();
                ctx.moveTo(pts[0].x, pts[0].y);
                ctx.lineTo(pts[pts.length - 1].x, pts[pts.length - 1].y);
                ctx.stroke();
                if (toolName === 'arrow') {
                    ctx.globalAlpha = 1;
                    drawArrowHead(ctx, pts[0], pts[pts.length - 1], stroke.width || 3);
                }
                ctx.restore();
                return;
            }
            if (toolName === 'rect' && pts.length >= 2) {
                var x = Math.min(pts[0].x, pts[1].x);
                var y = Math.min(pts[0].y, pts[1].y);
                var rw = Math.abs(pts[1].x - pts[0].x);
                var rh = Math.abs(pts[1].y - pts[0].y);
                if (stroke.filled) ctx.fillRect(x, y, rw, rh);
                else ctx.strokeRect(x, y, rw, rh);
                ctx.restore();
                return;
            }
            if (toolName === 'ellipse' && pts.length >= 2) {
                var cx = (pts[0].x + pts[1].x) / 2;
                var cy = (pts[0].y + pts[1].y) / 2;
                var rx = Math.abs(pts[1].x - pts[0].x) / 2;
                var ry = Math.abs(pts[1].y - pts[0].y) / 2;
                ctx.beginPath();
                ctx.ellipse(cx, cy, Math.max(0.5, rx), Math.max(0.5, ry), 0, 0, Math.PI * 2);
                if (stroke.filled) ctx.fill();
                else ctx.stroke();
                ctx.restore();
                return;
            }
            if (pts.length < 2) {
                ctx.restore();
                return;
            }
            ctx.beginPath();
            ctx.moveTo(pts[0].x, pts[0].y);
            for (var i = 1; i < pts.length; i++) {
                ctx.lineTo(pts[i].x, pts[i].y);
            }
            ctx.stroke();
            ctx.restore();
        }

        function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
            var words = text.split(/\s+/);
            var line = '';
            for (var n = 0; n < words.length; n++) {
                var test = line ? line + ' ' + words[n] : words[n];
                if (ctx.measureText(test).width > maxWidth && line) {
                    ctx.fillText(line, x, y);
                    line = words[n];
                    y += lineHeight;
                } else {
                    line = test;
                }
            }
            if (line) ctx.fillText(line, x, y);
        }

        function clearBoardCanvas() {
            var c = canvas();
            if (!c) return;
            var ctx = c.getContext('2d');
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, c.width, c.height);
        }

        function replay() {
            var c = canvas();
            if (!c) return;
            var ctx = c.getContext('2d');
            var scene = sceneFromLog();
            boardBg = scene.bg || boardBg;
            boardGrid = scene.grid || boardGrid;
            clearBoardCanvas();
            var hasPdf = !!(global.CKPdf && global.CKPdf.active);
            if (!hasPdf) {
                ctx.fillStyle = boardBg || '#ffffff';
                ctx.fillRect(0, 0, c.width, c.height);
                drawGrid(ctx, boardGrid, c.width, c.height);
            }
            scene.order.forEach(function (id) {
                drawObject(ctx, scene.objects[id]);
            });
            paintRemoteInks(ctx);
            paintLocalGesture(ctx);
            paintSelection();
            applyViewTransform();
            scheduleThumbPaint();
            if (hasPdf) schedulePdfInkPaint();
        }

        var pdfInkQueued = false;
        function schedulePdfInkPaint() {
            if (pdfInkQueued) return;
            pdfInkQueued = true;
            requestAnimationFrame(function () {
                pdfInkQueued = false;
                paintPdfInkLayers();
            });
        }

        function paintPdfInkLayers() {
            if (!(global.CKPdf && global.CKPdf.active)) return;
            var sc = $('ckPdfScroller');
            var layers = document.querySelectorAll('.ck-pdf-sheet-ink');
            if (!layers.length) return;
            var scene = sceneFromLog();
            var viewTop = sc ? sc.scrollTop - 240 : 0;
            var viewBot = sc ? sc.scrollTop + sc.clientHeight + 240 : 1e9;
            layers.forEach(function (layer) {
                var sheet = layer.parentNode;
                if (!sheet || sheet.classList.contains('is-live')) return;
                if (sc) {
                    var top = sheet.offsetTop;
                    if (top + sheet.offsetHeight < viewTop || top > viewBot) return;
                }
                var pageIndex = parseInt(layer.getAttribute('data-page'), 10);
                if (isNaN(pageIndex)) return;
                var ictx = layer.getContext('2d');
                ictx.setTransform(1, 0, 0, 1, 0, 0);
                ictx.clearRect(0, 0, layer.width, layer.height);
                drawPageFilter = pageIndex;
                try {
                    scene.order.forEach(function (id) {
                        drawObject(ictx, scene.objects[id]);
                    });
                } finally {
                    drawPageFilter = null;
                }
            });
        }

        function paintSelection() {
            var overlay = $('ckBoardOverlay');
            if (!overlay) return;
            overlay.innerHTML = '';
            if (selectedId) {
                var scene = sceneFromLog();
                var obj = scene.objects[selectedId];
                var b = boundsOf(obj);
                if (b) {
                    var box = document.createElement('div');
                    box.className = 'ck-sel-box';
                    box.style.left = (b.x / CANVAS_W * 100) + '%';
                    box.style.top = (b.y / CANVAS_H * 100) + '%';
                    box.style.width = (b.w / CANVAS_W * 100) + '%';
                    box.style.height = (b.h / CANVAS_H * 100) + '%';
                    ['nw', 'ne', 'sw', 'se'].forEach(function (k) {
                        var h = document.createElement('span');
                        h.className = 'ck-sel-handle ck-sel-' + k;
                        box.appendChild(h);
                    });
                    overlay.appendChild(box);
                }
            }
            if (h2tUi && tool === 'h2t' && typeof h2tUi.repaint === 'function') h2tUi.repaint();
        }

        function applyViewTransform() {
            var world = $('ckBoardWorld');
            if (!world) return;
            world.style.transform = 'translate(' + boardPanX + 'px,' + boardPanY + 'px) scale(' + boardZoom + ')';
            var reset = $('ckBoardZoomReset');
            if (reset) reset.textContent = Math.round(boardZoom * 100) + '%';
        }

        function setZoom(z, cx, cy) {
            var prev = boardZoom;
            boardZoom = clamp(z, 0.4, 2.5);
            var viewport = $('ckBoardViewport');
            if (viewport && typeof cx === 'number') {
                var rect = viewport.getBoundingClientRect();
                var vx = cx - rect.left;
                var vy = cy - rect.top;
                boardPanX = vx - (vx - boardPanX) * (boardZoom / prev);
                boardPanY = vy - (vy - boardPanY) * (boardZoom / prev);
            }
            applyViewTransform();
        }

        function syncBgUi() {
            var gridEl = $('ckBoardGrid');
            var bgEl = $('ckBoardBg');
            if (gridEl) gridEl.value = boardGrid;
            if (bgEl) bgEl.value = boardBg;
        }

        function eraserWidthFor(base) {
            var n = base == null ? penWidth : base;
            return clamp(Math.round(n * 1.5), 4, 20);
        }

        function currentWidth() {
            if (tool === 'erase') return eraserWidthFor(penWidth);
            if (tool === 'highlight') return clamp(penWidth * 3, 8, 48);
            return clamp(penWidth, 1, 48);
        }

        function currentDrawProps() {
            return {
                color: penColor,
                width: currentWidth(),
                opacity: tool === 'highlight' ? highlightOpacity : undefined,
                size: clamp(Math.round(12 + penWidth * 1.5), 12, 72),
                font: textFont,
                style: textStyle
            };
        }

        function setTool(next) {
            tool = next || 'pen';
            document.querySelectorAll('#ckBoardTools [data-tool]').forEach(function (b) {
                b.classList.toggle('is-on', b.getAttribute('data-tool') === tool);
            });
            var opWrap = $('ckBoardOpacityWrap');
            var fontWrap = $('ckBoardFontWrap');
            var styleWrap = $('ckBoardStyleWrap');
            var fontSec = $('ckTppFontSection');
            var strokeSec = $('ckTppStrokeSection');
            var colorSec = $('ckTppColorSection');
            var advSec = $('ckTppAdvancedSection');
            var toolIcon = $('ckTppToolIcon');
            var toolName = $('ckTppToolName');

            var toolMeta = {
                select: { name: 'Select', icon: 'bi-cursor-fill' },
                pen: { name: 'Pen', icon: 'bi-pen-fill' },
                highlight: { name: 'Highlighter', icon: 'bi-highlighter' },
                erase: { name: 'Eraser', icon: 'bi-eraser-fill' },
                text: { name: 'Text', icon: 'bi-type' },
                sticky: { name: 'Sticky Note', icon: 'bi-sticky-fill' },
                rect: { name: 'Rectangle', icon: 'bi-square' },
                ellipse: { name: 'Circle', icon: 'bi-circle' },
                line: { name: 'Line', icon: 'bi-slash-lg' },
                arrow: { name: 'Arrow', icon: 'bi-arrow-up-right' },
                laser: { name: 'Laser', icon: 'bi-record-circle-fill' },
                pan: { name: 'Hand', icon: 'bi-hand-index-thumb' },
                h2t: { name: 'Handwriting to Text', icon: 'bi-textarea-t' }
            };

            var meta = toolMeta[tool] || { name: 'Pen', icon: 'bi-pen-fill' };
            if (toolName) toolName.textContent = meta.name;
            if (toolIcon) toolIcon.className = 'bi ' + meta.icon;

            var isText = (tool === 'text' || tool === 'sticky' || !!(selectedId && isTextLike(selectedId)));
            var isErase = tool === 'erase';
            var isHighlighter = tool === 'highlight';

            if (opWrap) opWrap.hidden = !isHighlighter;
            if (fontWrap) fontWrap.hidden = !isText;
            if (styleWrap) styleWrap.hidden = !isText;
            if (fontSec) fontSec.hidden = !isText;
            if (strokeSec) strokeSec.hidden = isText;
            if (colorSec) colorSec.hidden = isErase;
            if (advSec) advSec.hidden = isText || isErase || isHighlighter;

            var c = canvas();
            if (c) {
                if (tool === 'pan') c.style.cursor = 'grab';
                else if (tool === 'laser') c.style.cursor = 'none';
                else if (tool === 'select') c.style.cursor = 'default';
                else if (tool === 'h2t') c.style.cursor = boardCanDraw() ? 'crosshair' : 'default';
                else if (tool === 'text' || tool === 'sticky') c.style.cursor = boardCanDraw() ? 'text' : 'default';
                else c.style.cursor = boardCanDraw() ? 'crosshair' : 'default';
            }
            if (tool !== 'h2t' && h2tUi && h2tUi.clearSelection) h2tUi.clearSelection();
            if (tool === 'h2t') {
                selectedId = null;
                paintSelection();
            }
            if (h2tUi && h2tUi.onToolChange) h2tUi.onToolChange(tool);
            syncEditButtons();
        }

        function isTextLike(id) {
            var scene = sceneFromLog();
            var o = scene.objects[id];
            return !!(o && (o.tool === 'text' || o.tool === 'sticky'));
        }

        function isShapeTool(t) {
            return t === 'rect' || t === 'line' || t === 'ellipse' || t === 'arrow';
        }

        function isFreehandTool(t) {
            return t === 'pen' || t === 'highlight' || t === 'erase';
        }

        function samplePoints(pts, max) {
            pts = pts || [];
            if (pts.length <= max) return pts.slice();
            var out = [];
            var last = pts.length - 1;
            for (var i = 0; i < max; i++) {
                out.push(pts[Math.round(i * last / (max - 1))]);
            }
            return out;
        }

        function roundPt(p) {
            return {
                x: Math.round(Number(p.x) * 10) / 10,
                y: Math.round(Number(p.y) * 10) / 10
            };
        }

        function sanitizeInkPts(list) {
            var out = [];
            var arr = list || [];
            var n = Math.min(arr.length, 400);
            for (var i = 0; i < n; i++) {
                var p = arr[i];
                if (!p || !isFinite(Number(p.x)) || !isFinite(Number(p.y))) continue;
                out.push(roundPt(p));
            }
            return out;
        }

        function logHasStrokeId(id) {
            if (!id) return false;
            for (var i = 0; i < boardLog.length; i++) {
                var s = boardLog[i];
                if (s && s.tool !== 'op' && s.id === id) return true;
            }
            return false;
        }

        function pruneFinishedInk() {
            var now = Date.now();
            Object.keys(finishedInkAt).forEach(function (k) {
                if (now - finishedInkAt[k] > 120000) delete finishedInkAt[k];
            });
        }

        function finishRemoteInk(id) {
            if (!id) return;
            if (remoteInk[id]) delete remoteInk[id];
            finishedInkAt[id] = Date.now();
            pruneFinishedInk();
        }

        function pushLoggedStroke(stroke) {
            if (!stroke) return false;
            if (stroke.id && stroke.tool !== 'op') {
                finishRemoteInk(stroke.id);
                if (logHasStrokeId(stroke.id)) return false;
            }
            boardLog.push(stroke);
            return true;
        }

        function paintRemoteInks(ctx) {
            if (!ctx) return;
            Object.keys(remoteInk).forEach(function (id) {
                var ink = remoteInk[id];
                if (!ink || !ink.points || ink.points.length < 2) return;
                drawObject(ctx, ink);
            });
        }

        function paintLocalGesture(ctx) {
            if (!ctx || !drawing || dragKind !== 'draw' || points.length < 2) return;
            if (tool === 'text' || tool === 'sticky') return;
            var props = currentDrawProps();
            drawObject(ctx, {
                tool: tool,
                color: props.color,
                width: props.width,
                opacity: props.opacity,
                page: boardPage,
                points: isShapeTool(tool) ? [points[0], points[points.length - 1]] : points.slice()
            });
        }

        function paintInkSegment(ink, fromIndex) {
            var c = canvas();
            if (!c || !ink) return;
            var from = fromIndex < 0 ? 0 : fromIndex;
            var seg = ink.points.slice(from);
            if (seg.length < 2) return;
            drawObject(c.getContext('2d'), {
                id: ink.id,
                tool: ink.tool,
                color: ink.color,
                width: ink.width,
                opacity: ink.opacity,
                page: ink.page,
                points: seg
            });
        }

        function publishInk(body) {
            var msg = { t: 'wb-ink' };
            Object.keys(body).forEach(function (k) {
                if (body[k] != null) msg[k] = body[k];
            });
            publishData(msg);
        }

        function scheduleLiveInk(delay) {
            if (!liveInk || liveInk.timer) return;
            liveInk.timer = setTimeout(function () {
                if (liveInk) liveInk.timer = null;
                flushLiveInk(false);
            }, delay == null ? INK_MIN_GAP_MS : delay);
        }

        function flushLiveInk(ending) {
            if (!liveInk) return;
            if (liveInk.timer) {
                clearTimeout(liveInk.timer);
                liveInk.timer = null;
            }
            if (points.length <= liveInk.sent) return;
            var now = Date.now();
            if (!ending && liveInk.lastSend && (now - liveInk.lastSend) < INK_MIN_GAP_MS) {
                scheduleLiveInk(INK_MIN_GAP_MS - (now - liveInk.lastSend));
                return;
            }
            var sendFull = points.length >= 2 && points.length <= 320 &&
                (liveInk.lastFull === 0 || (points.length - liveInk.lastFull) >= 40);
            var payloadPts;
            var index;
            if (sendFull) {
                payloadPts = points.map(roundPt);
                index = 0;
                liveInk.lastFull = points.length;
                liveInk.sent = points.length;
            } else {
                var take = Math.min(80, points.length - liveInk.sent);
                payloadPts = points.slice(liveInk.sent, liveInk.sent + take).map(roundPt);
                index = liveInk.sent;
                liveInk.sent += payloadPts.length;
            }
            if (!payloadPts.length) return;
            var body = {
                id: liveInk.id,
                tool: liveInk.tool,
                color: liveInk.color,
                width: liveInk.width,
                page: liveInk.page,
                i: index,
                pts: payloadPts
            };
            if (liveInk.opacity != null) body.opacity = liveInk.opacity;
            if (sendFull) body.full = 1;
            publishInk(body);
            liveInk.lastSend = Date.now();
            if (points.length > liveInk.sent) scheduleLiveInk(INK_MIN_GAP_MS);
        }

        function beginLiveInk() {
            if (liveInk) {
                var oldId = liveInk.id;
                var oldSent = liveInk.sent;
                if (liveInk.timer) clearTimeout(liveInk.timer);
                liveInk = null;
                if (oldSent > 0) publishInk({ id: oldId, phase: 'cancel' });
            }
            if (!isFreehandTool(tool)) return;
            var props = currentDrawProps();
            liveInk = {
                id: uid(),
                tool: tool,
                color: props.color,
                width: props.width,
                opacity: props.opacity,
                page: boardPage,
                sent: 0,
                lastSend: 0,
                lastFull: 0,
                timer: null
            };
            scheduleLiveInk(0);
        }

        function endLiveInk() {
            if (!liveInk) return null;
            flushLiveInk(true);
            var meta = { id: liveInk.id, sent: liveInk.sent };
            if (liveInk.timer) clearTimeout(liveInk.timer);
            liveInk = null;
            return meta;
        }

        function abandonLiveInk() {
            if (!liveInk) {
                points = [];
                return;
            }
            var id = liveInk.id;
            var sent = liveInk.sent;
            if (liveInk.timer) clearTimeout(liveInk.timer);
            liveInk = null;
            points = [];
            if (sent > 0) publishInk({ id: id, phase: 'cancel' });
        }

        function noteLiveInkProgress() {
            if (!liveInk || !isFreehandTool(tool)) return;
            if (points.length > liveInk.sent) scheduleLiveInk(INK_MIN_GAP_MS);
        }

        function receiveInk(msg) {
            if (!msg || !msg.id) return;
            var id = String(msg.id).replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 40);
            if (!id) return;
            if (msg.phase === 'cancel') {
                if (remoteInk[id]) delete remoteInk[id];
                finishedInkAt[id] = Date.now();
                replay();
                return;
            }
            if (liveInk && liveInk.id === id) return;
            if (finishedInkAt[id] || logHasStrokeId(id)) return;
            var toolName = msg.tool || 'pen';
            if (!isFreehandTool(toolName)) toolName = 'pen';
            var incoming = sanitizeInkPts(msg.pts);
            if (!incoming.length && !msg.full) return;
            var ink = remoteInk[id];
            if (!ink) {
                ink = remoteInk[id] = {
                    id: id,
                    tool: toolName,
                    color: /^#[0-9a-fA-F]{3,8}$/.test(String(msg.color || '')) ? String(msg.color) : '#111827',
                    width: Math.max(1, Math.min(64, parseInt(msg.width, 10) || 3)),
                    opacity: msg.opacity != null ? msg.opacity : undefined,
                    page: Math.max(0, Math.min(40, parseInt(msg.page, 10) || 0)),
                    points: []
                };
            }
            if (msg.full) {
                ink.points = incoming.slice();
                ink.tool = toolName;
                replay();
                onStudentShow(false);
                return;
            }
            var start = typeof msg.i === 'number' && msg.i >= 0 ? msg.i : ink.points.length;
            if (start > ink.points.length) start = ink.points.length;
            if (start < ink.points.length) {
                incoming = incoming.slice(ink.points.length - start);
                start = ink.points.length;
            }
            if (!incoming.length) return;
            var anchor = ink.points.length - 1;
            ink.points = ink.points.concat(incoming);
            if (ink.points.length > 4000) {
                ink.points = samplePoints(ink.points, 4000);
                replay();
            } else {
                paintInkSegment(ink, anchor);
            }
            onStudentShow(false);
        }

        function pointerPos(ev, el) {
            var c = el || canvas();
            if (!c) return { x: 0, y: 0 };
            var r = c.getBoundingClientRect();
            if (!r.width || !r.height) return { x: 0, y: 0 };
            var clientX = ('touches' in ev && ev.touches[0]) ? ev.touches[0].clientX : ev.clientX;
            var clientY = ('touches' in ev && ev.touches[0]) ? ev.touches[0].clientY : ev.clientY;
            return {
                x: (clientX - r.left) * (c.width / r.width),
                y: (clientY - r.top) * (c.height / r.height)
            };
        }

        function clientPos(ev) {
            if (ev.touches && ev.touches[0]) return { x: ev.touches[0].clientX, y: ev.touches[0].clientY };
            return { x: ev.clientX, y: ev.clientY };
        }

        function publishLaser(on, p) {
            var x = p ? p.x : 0;
            var y = p ? p.y : 0;
            showLaser(localIdentity(), on, x, y, penColor);
            if (!on) {
                laserNetPending = null;
                if (laserNetTimer) {
                    clearTimeout(laserNetTimer);
                    laserNetTimer = null;
                }
                publishData({ t: 'wb-laser', on: false, x: x, y: y, color: penColor });
                return;
            }
            var now = Date.now();
            if ((now - laserNetAt) >= 32) {
                laserNetAt = now;
                laserNetPending = null;
                if (laserNetTimer) {
                    clearTimeout(laserNetTimer);
                    laserNetTimer = null;
                }
                publishData({ t: 'wb-laser', on: true, x: x, y: y, color: penColor });
                return;
            }
            laserNetPending = { x: x, y: y, color: penColor };
            if (!laserNetTimer) {
                laserNetTimer = setTimeout(function () {
                    laserNetTimer = null;
                    var pending = laserNetPending;
                    laserNetPending = null;
                    if (!pending) return;
                    laserNetAt = Date.now();
                    publishData({
                        t: 'wb-laser',
                        on: true,
                        x: pending.x,
                        y: pending.y,
                        color: pending.color
                    });
                }, Math.max(8, 32 - (now - laserNetAt)));
            }
        }

        function showLaser(id, on, x, y, color) {
            var layer = $('ckBoardLasers');
            if (!layer) return;
            var key = String(id || 'x');
            if (!on) {
                if (lasers[key] && lasers[key].el) lasers[key].el.remove();
                delete lasers[key];
                return;
            }
            if (!lasers[key]) {
                var el = document.createElement('div');
                el.className = 'ck-laser-dot';
                layer.appendChild(el);
                lasers[key] = { el: el };
            }
            lasers[key].el.style.left = (x / CANVAS_W * 100) + '%';
            lasers[key].el.style.top = (y / CANVAS_H * 100) + '%';
            lasers[key].el.style.background = color || '#ef4444';
            lasers[key].ts = Date.now();
        }

        function pruneLasers() {
            var now = Date.now();
            Object.keys(lasers).forEach(function (k) {
                if (now - (lasers[k].ts || 0) > 1200) showLaser(k, false);
            });
        }

        function selectObject(id) {
            selectedId = id;
            paintSelection();
            syncEditButtons();
            if (id) {
                var scene = sceneFromLog();
                var o = scene.objects[id];
                if (o) {
                    if (o.color && $('ckBoardColor')) $('ckBoardColor').value = o.color;
                    if (o.width && $('ckBoardSize')) {
                        penWidth = o.tool === 'erase' ? clamp(Math.round(o.width / 1.5), 1, 48) : (o.tool === 'highlight' ? Math.round(o.width / 3) : o.width);
                        $('ckBoardSize').value = String(penWidth);
                    }
                    if (o.opacity != null && $('ckBoardOpacity')) {
                        highlightOpacity = o.opacity;
                        $('ckBoardOpacity').value = String(Math.round(o.opacity * 100));
                    }
                    if (o.font && $('ckBoardFont')) $('ckBoardFont').value = o.font;
                    if (o.style && $('ckBoardStyle')) $('ckBoardStyle').value = o.style;
                    var fontWrap = $('ckBoardFontWrap');
                    var styleWrap = $('ckBoardStyleWrap');
                    var showFont = o.tool === 'text' || o.tool === 'sticky';
                    if (fontWrap) fontWrap.hidden = !showFont;
                    if (styleWrap) styleWrap.hidden = !showFont;
                }
            }
        }

        function patchSelected(patch) {
            if (!selectedId || !boardCanDraw()) return;
            var scene = sceneFromLog();
            var o = scene.objects[selectedId];
            if (!o) return;
            var before = {};
            Object.keys(patch).forEach(function (k) { before[k] = o[k]; });
            commitStroke({ tool: 'op', op: 'patch', id: selectedId, patch: patch }, {
                kind: 'patch',
                id: selectedId,
                before: before,
                after: deepClone(patch)
            });
        }

        function deleteSelected() {
            if (!selectedId || !boardCanDraw()) return;
            var scene = sceneFromLog();
            var o = scene.objects[selectedId];
            if (!o) return;
            var copy = deepClone(o);
            var id = selectedId;
            selectedId = null;
            commitStroke({ tool: 'op', op: 'delete', id: id }, { kind: 'create', stroke: copy });
        }

        function duplicateSelected() {
            if (!selectedId || !boardCanDraw()) return;
            var scene = sceneFromLog();
            var o = scene.objects[selectedId];
            if (!o) return;
            var copy = deepClone(o);
            copy.id = uid();
            var dx = 18; var dy = 18;
            if (copy.points) {
                copy.points = copy.points.map(function (p) { return { x: p.x + dx, y: p.y + dy }; });
            }
            if (typeof copy.x === 'number') copy.x += dx;
            if (typeof copy.y === 'number') copy.y += dy;
            selectedId = copy.id;
            commitStroke(copy);
            clipboard = deepClone(copy);
        }

        function copySelected() {
            if (!selectedId) return;
            var scene = sceneFromLog();
            var o = scene.objects[selectedId];
            if (o) clipboard = deepClone(o);
        }

        function pasteClipboard() {
            if (!clipboard || !boardCanDraw()) return;
            var copy = deepClone(clipboard);
            copy.id = uid();
            var dx = 24; var dy = 24;
            if (copy.points) copy.points = copy.points.map(function (p) { return { x: p.x + dx, y: p.y + dy }; });
            if (typeof copy.x === 'number') copy.x += dx;
            if (typeof copy.y === 'number') copy.y += dy;
            selectedId = copy.id;
            commitStroke(copy);
        }

        function undo() {
            if (!boardCanDraw() || !undoStack.length) return;
            var entry = undoStack.pop();
            redoStack.push(entry);
            applyUndoEntry(entry, false);
            syncEditButtons();
        }

        function redo() {
            if (!boardCanDraw() || !redoStack.length) return;
            var entry = redoStack.pop();
            undoStack.push(entry);
            applyUndoEntry(entry, true);
            syncEditButtons();
        }

        function applyUndoEntry(entry, forward) {
            if (!entry) return;
            var kind = entry.kind;
            var opts = { skipUndo: true };
            if (kind === 'delete') {
                if (forward) {
                    if (entry.stroke) {
                        var restored = deepClone(entry.stroke);
                        commitStroke(restored, null, opts);
                        selectedId = restored.id || selectedId;
                    }
                    return;
                }
                commitStroke({ tool: 'op', op: 'delete', id: entry.id }, null, opts);
                if (selectedId === entry.id) selectedId = null;
                return;
            }
            if (kind === 'create') {
                // Undo-entry kind is the undo action: restore the stroke; redo deletes again.
                if (forward) {
                    commitStroke({ tool: 'op', op: 'delete', id: entry.stroke.id }, null, opts);
                    if (selectedId === entry.stroke.id) selectedId = null;
                } else {
                    var s = deepClone(entry.stroke);
                    if (!s.id) s.id = uid();
                    commitStroke(s, null, opts);
                    selectedId = s.id;
                }
                return;
            }
            if (kind === 'patch') {
                var p = forward ? entry.after : entry.before;
                if (!p && entry.patch) p = entry.patch;
                if (p) commitStroke({ tool: 'op', op: 'patch', id: entry.id, patch: p }, null, opts);
                return;
            }
            if (kind === 'transform') {
                var inv = {
                    tool: 'op',
                    op: 'transform',
                    id: entry.id,
                    dx: forward ? entry.dx : -entry.dx,
                    dy: forward ? entry.dy : -entry.dy
                };
                if (forward && entry.after) {
                    ['x', 'y', 'w', 'h', 'points'].forEach(function (k) {
                        if (entry.after[k] != null) inv[k] = entry.after[k];
                    });
                } else if (!forward && entry.before) {
                    ['x', 'y', 'w', 'h', 'points'].forEach(function (k) {
                        if (entry.before[k] != null) inv[k] = entry.before[k];
                    });
                }
                commitStroke(inv, null, opts);
                return;
            }
            if (kind === 'bg') {
                commitStroke({
                    tool: 'op',
                    op: 'bg',
                    grid: forward ? entry.after.grid : entry.before.grid,
                    bg: forward ? entry.after.bg : entry.before.bg
                }, null, opts);
                return;
            }
            if (kind === 'h2t') {
                if (forward) {
                    if (entry.textStroke) {
                        var restoredText = deepClone(entry.textStroke);
                        commitStroke(restoredText, null, opts);
                        selectedId = restoredText.id || selectedId;
                    }
                    if (entry.inkMode === 'replace' && entry.inkIds) {
                        entry.inkIds.forEach(function (id) {
                            commitStroke({ tool: 'op', op: 'delete', id: id }, null, opts);
                        });
                    } else if (entry.inkMode === 'hide' && entry.inkIds) {
                        entry.inkIds.forEach(function (id) {
                            commitStroke({ tool: 'op', op: 'patch', id: id, patch: { hidden: true } }, null, opts);
                        });
                    }
                    return;
                }
                if (entry.textId) {
                    commitStroke({ tool: 'op', op: 'delete', id: entry.textId }, null, opts);
                    if (selectedId === entry.textId) selectedId = null;
                }
                if (entry.inkMode === 'replace' && entry.inkStrokes) {
                    entry.inkStrokes.forEach(function (s) {
                        if (!s) return;
                        commitStroke(deepClone(s), null, opts);
                    });
                } else if (entry.inkMode === 'hide' && entry.inkIds) {
                    entry.inkIds.forEach(function (id) {
                        commitStroke({ tool: 'op', op: 'patch', id: id, patch: { hidden: false } }, null, opts);
                    });
                }
            }
        }

        function mountTextUi() {
            if (textUi || !global.CKBoardText || typeof global.CKBoardText.mount !== 'function') return textUi;
            textUi = global.CKBoardText.mount({
                $: $,
                toast: toast,
                uid: uid,
                canDraw: boardCanDraw,
                getColor: function () { return penColor; },
                getPage: function () { return boardPage; },
                setTool: setTool,
                selectId: function (id) { selectObject(id || null); },
                replay: replay,
                setEditingTextId: function (id) {
                    editingTextId = id || null;
                    replay();
                },
                commitText: function (payload) {
                    if (!payload) return;
                    selectedId = payload.id;
                    commitStroke(payload);
                },
                patchObject: function (id, patch) {
                    if (!id || !patch) return;
                    selectedId = id;
                    commitStroke({ tool: 'op', op: 'patch', id: id, patch: patch }, {
                        kind: 'patch',
                        id: id,
                        before: {},
                        after: deepClone(patch)
                    });
                },
                commitOp: function (stroke) { commitStroke(stroke); }
            });
            return textUi;
        }

        function openTextAt(x, y, sticky) {
            mountTextUi();
            if (textUi) textUi.openAt(x, y, !!sticky);
        }

        function editTextObject(obj) {
            mountTextUi();
            if (textUi) textUi.editObject(obj);
        }

        function promptText(defaultValue) {
            // Fallback only if text UI unavailable
            var v = window.prompt(tool === 'sticky' ? 'Sticky note' : 'Board text', defaultValue || '');
            return v && String(v).trim() ? String(v).trim() : '';
        }

        function beginPinch(x0, y0, x1, y1) {
            if (isPinching) return;
            abandonLiveInk();
            drawing = false;
            dragKind = '';
            isPinching = true;
            pinchStartDist = Math.hypot(x0 - x1, y0 - y1) || 1;
            pinchStartZoom = boardZoom;
            pinchOrigin = { x: boardPanX, y: boardPanY };
            pinchStartMid = { x: (x0 + x1) / 2, y: (y0 + y1) / 2 };
            replay();
        }

        function applyPinch(x0, y0, x1, y1) {
            if (!isPinching) beginPinch(x0, y0, x1, y1);
            var curDist = Math.hypot(x0 - x1, y0 - y1) || 1;
            var curMid = { x: (x0 + x1) / 2, y: (y0 + y1) / 2 };
            var factor = curDist / pinchStartDist;
            var newZoom = clamp(pinchStartZoom * factor, 0.4, 2.5);
            boardPanX = pinchOrigin.x + (curMid.x - pinchStartMid.x);
            boardPanY = pinchOrigin.y + (curMid.y - pinchStartMid.y);
            boardZoom = newZoom;
            applyViewTransform();
        }

        function pointsFromEvent(ev) {
            var raw = [];
            if (ev && typeof ev.getCoalescedEvents === 'function') {
                try {
                    var extra = ev.getCoalescedEvents();
                    if (extra && extra.length) raw = extra;
                } catch (e) {}
            }
            if (!raw.length) raw = [ev];
            var out = [];
            for (var i = 0; i < raw.length; i++) out.push(pointerPos(raw[i]));
            return out;
        }

        function pushDrawPoint(p) {
            if (!p || !isFinite(p.x) || !isFinite(p.y)) return false;
            var last = points[points.length - 1];
            if (last && Math.abs(last.x - p.x) < 0.15 && Math.abs(last.y - p.y) < 0.15) return false;
            points.push(p);
            return true;
        }

        function startDraw(ev) {
            if (ev.touches && ev.touches.length >= 2) {
                var t0 = ev.touches[0];
                var t1 = ev.touches[1];
                beginPinch(t0.clientX, t0.clientY, t1.clientX, t1.clientY);
                ev.preventDefault();
                return;
            }
            if (isPinching) return;
            var cfgNow = cfg();
            var p = pointerPos(ev);
            var cp = clientPos(ev);

            if (tool === 'laser') {
                if (!boardCanDraw()) return;
                drawing = true;
                dragKind = 'laser';
                publishLaser(true, p);
                ev.preventDefault();
                return;
            }

            if (tool === 'pan' || spacePan) {
                drawing = true;
                dragKind = 'pan';
                dragStart = cp;
                dragOrigin = { x: boardPanX, y: boardPanY };
                var c = canvas();
                if (c) c.style.cursor = 'grabbing';
                ev.preventDefault();
                return;
            }

            if (!boardCanDraw() && tool !== 'select') return;

            if (tool === 'h2t') {
                if (!boardCanDraw()) return;
                drawing = true;
                dragKind = 'h2t';
                if (h2tUi && h2tUi.start) h2tUi.start(ev, p);
                ev.preventDefault();
                return;
            }

            if (tool === 'select') {
                if (!boardCanDraw()) {
                    selectObject(hitTest(p.x, p.y));
                    return;
                }
                if (selectedId) {
                    var scene = sceneFromLog();
                    var b = boundsOf(scene.objects[selectedId]);
                    var h = handleAt(p.x, p.y, b);
                    if (h) {
                        drawing = true;
                        dragKind = 'resize';
                        resizeCorner = h;
                        dragStart = p;
                        dragOrigin = deepClone(scene.objects[selectedId]);
                        ev.preventDefault();
                        return;
                    }
                }
                var hit = hitTest(p.x, p.y);
                selectObject(hit);
                if (hit) {
                    var hitObj = sceneFromLog().objects[hit];
                    if (hitObj && hitObj.locked && !cfg().isHost) {
                        toast('That object is locked by the teacher.');
                        return;
                    }
                    // Double-click (or quick re-click) text/sticky to edit
                    if (hitObj && (hitObj.tool === 'text' || hitObj.tool === 'sticky') && boardCanDraw()) {
                        var now = Date.now();
                        if (hit === selectedId && canvas()._ckLastTextHit === hit && (now - (canvas()._ckLastTextAt || 0)) < 450) {
                            editTextObject(hitObj);
                            canvas()._ckLastTextHit = null;
                            ev.preventDefault();
                            return;
                        }
                        canvas()._ckLastTextHit = hit;
                        canvas()._ckLastTextAt = now;
                    }
                    drawing = true;
                    dragKind = 'move';
                    dragStart = p;
                    dragOrigin = deepClone(hitObj);
                }
                ev.preventDefault();
                return;
            }

            if (!boardCanDraw()) return;
            drawing = true;
            dragKind = 'draw';
            points = [p];
            if (isFreehandTool(tool)) beginLiveInk();
            if (tool === 'pen' || tool === 'highlight' || tool === 'erase') {
                if (selectedId) {
                    selectedId = null;
                    paintSelection();
                }
            }
            if (cfgNow.isHost) onHostFocus(true);
            if (isShapeTool(tool) || tool === 'text' || tool === 'sticky') snapshotBoard();
            ev.preventDefault();
        }

        function moveDraw(ev) {
            if (ev.touches && ev.touches.length >= 2) {
                var t0 = ev.touches[0];
                var t1 = ev.touches[1];
                applyPinch(t0.clientX, t0.clientY, t1.clientX, t1.clientY);
                ev.preventDefault();
                return;
            }
            if (isPinching) return;

            if (!drawing) {
                if (tool === 'laser' && boardCanDraw()) {
                    publishLaser(true, pointerPos(ev));
                }
                return;
            }
            var p = pointerPos(ev);
            var cp = clientPos(ev);

            if (dragKind === 'laser') {
                publishLaser(true, p);
                ev.preventDefault();
                return;
            }
            if (dragKind === 'pan') {
                boardPanX = dragOrigin.x + (cp.x - dragStart.x);
                boardPanY = dragOrigin.y + (cp.y - dragStart.y);
                applyViewTransform();
                ev.preventDefault();
                return;
            }
            if (dragKind === 'move' && selectedId && dragOrigin) {
                var dx = p.x - dragStart.x;
                var dy = p.y - dragStart.y;
                previewTransform(selectedId, dragOrigin, dx, dy, null);
                ev.preventDefault();
                return;
            }
            if (dragKind === 'resize' && selectedId && dragOrigin) {
                previewResize(selectedId, dragOrigin, resizeCorner, p);
                ev.preventDefault();
                return;
            }
            if (dragKind === 'h2t') {
                if (h2tUi && h2tUi.move) h2tUi.move(ev, p);
                ev.preventDefault();
                return;
            }
            if (dragKind !== 'draw' || tool === 'text' || tool === 'sticky') return;
            var before = points.length;
            var batch = pointsFromEvent(ev);
            var added = false;
            for (var bi = 0; bi < batch.length; bi++) {
                if (pushDrawPoint(batch[bi])) added = true;
            }
            if (!added) {
                ev.preventDefault();
                return;
            }
            var props = currentDrawProps();
            if (isShapeTool(tool)) {
                restoreBoard();
                drawObject(canvas().getContext('2d'), {
                    points: [points[0], points[points.length - 1]],
                    tool: tool,
                    color: props.color,
                    width: props.width
                });
            } else {
                var from = Math.max(0, before - 1);
                drawObject(canvas().getContext('2d'), {
                    points: points.slice(from),
                    tool: tool,
                    color: props.color,
                    width: props.width,
                    opacity: props.opacity
                });
                noteLiveInkProgress();
            }
            ev.preventDefault();
        }

        function previewTransform(id, origin, dx, dy) {
            replay();
            var tmp = deepClone(origin);
            if (tmp.points) tmp.points = tmp.points.map(function (pt) { return { x: pt.x + dx, y: pt.y + dy }; });
            if (typeof tmp.x === 'number') tmp.x += dx;
            if (typeof tmp.y === 'number') tmp.y += dy;
            drawObject(canvas().getContext('2d'), tmp);
            selectedId = id;
            paintSelectionFromObj(tmp);
        }

        function paintSelectionFromObj(obj) {
            var overlay = $('ckBoardOverlay');
            if (!overlay) return;
            overlay.innerHTML = '';
            var b = boundsOf(obj);
            if (!b) return;
            var box = document.createElement('div');
            box.className = 'ck-sel-box';
            box.style.left = (b.x / CANVAS_W * 100) + '%';
            box.style.top = (b.y / CANVAS_H * 100) + '%';
            box.style.width = (b.w / CANVAS_W * 100) + '%';
            box.style.height = (b.h / CANVAS_H * 100) + '%';
            overlay.appendChild(box);
        }

        function previewResize(id, origin, corner, p) {
            var tmp = deepClone(origin);
            var b = boundsOf(origin);
            if (!b) return;
            var x1 = b.x; var y1 = b.y; var x2 = b.x + b.w; var y2 = b.y + b.h;
            if (corner.indexOf('n') >= 0) y1 = p.y;
            if (corner.indexOf('s') >= 0) y2 = p.y;
            if (corner.indexOf('w') >= 0) x1 = p.x;
            if (corner.indexOf('e') >= 0) x2 = p.x;
            if (tmp.tool === 'sticky' || tmp.tool === 'text') {
                tmp.x = Math.min(x1, x2);
                tmp.y = Math.min(y1, y2) + (tmp.tool === 'text' ? (tmp.size || 18) : 0);
                if (tmp.tool === 'sticky') {
                    tmp.w = Math.max(80, Math.abs(x2 - x1));
                    tmp.h = Math.max(60, Math.abs(y2 - y1));
                }
            } else if (tmp.points && tmp.points.length >= 2 && isShapeTool(tmp.tool)) {
                tmp.points = [{ x: x1, y: y1 }, { x: x2, y: y2 }];
            } else if (tmp.points) {
                var ob = boundsOf(origin);
                var sx = ob.w ? Math.abs(x2 - x1) / ob.w : 1;
                var sy = ob.h ? Math.abs(y2 - y1) / ob.h : 1;
                var ox = Math.min(x1, x2);
                var oy = Math.min(y1, y2);
                tmp.points = origin.points.map(function (pt) {
                    return {
                        x: ox + (pt.x - ob.x) * sx,
                        y: oy + (pt.y - ob.y) * sy
                    };
                });
            }
            replay();
            drawObject(canvas().getContext('2d'), tmp);
            paintSelectionFromObj(tmp);
            dragOrigin._preview = tmp;
        }

        function endDraw(ev) {
            if (isPinching) {
                if (!ev || !ev.touches || ev.touches.length < 2) {
                    isPinching = false;
                }
                drawing = false;
                dragKind = '';
                return;
            }
            if (!drawing) return;
            drawing = false;
            var p = points[0];

            if (dragKind === 'laser') {
                publishLaser(false);
                dragKind = '';
                return;
            }
            if (dragKind === 'pan') {
                dragKind = '';
                var c = canvas();
                if (c) c.style.cursor = tool === 'pan' ? 'grab' : (boardCanDraw() ? 'crosshair' : 'default');
                return;
            }
            if (dragKind === 'h2t') {
                dragKind = '';
                if (h2tUi && h2tUi.end) h2tUi.end(ev, pointerPos(ev || { clientX: 0, clientY: 0 }));
                return;
            }
            if (dragKind === 'move' && selectedId && dragOrigin) {
                var endP = pointerPos(ev || { clientX: 0, clientY: 0 });
                var dx = endP.x - dragStart.x;
                var dy = endP.y - dragStart.y;
                dragKind = '';
                if (Math.abs(dx) < 0.5 && Math.abs(dy) < 0.5) {
                    replay();
                    return;
                }
                var before = {
                    x: dragOrigin.x,
                    y: dragOrigin.y,
                    w: dragOrigin.w,
                    h: dragOrigin.h,
                    points: dragOrigin.points ? deepClone(dragOrigin.points) : null
                };
                var afterObj = deepClone(dragOrigin);
                if (afterObj.points) afterObj.points = afterObj.points.map(function (pt) { return { x: pt.x + dx, y: pt.y + dy }; });
                if (typeof afterObj.x === 'number') afterObj.x += dx;
                if (typeof afterObj.y === 'number') afterObj.y += dy;
                commitStroke({
                    tool: 'op',
                    op: 'transform',
                    id: selectedId,
                    dx: dx,
                    dy: dy,
                    x: afterObj.x,
                    y: afterObj.y,
                    points: afterObj.points
                }, {
                    kind: 'transform',
                    id: selectedId,
                    dx: dx,
                    dy: dy,
                    before: before,
                    after: { x: afterObj.x, y: afterObj.y, points: afterObj.points }
                });
                return;
            }
            if (dragKind === 'resize' && selectedId && dragOrigin) {
                var resized = dragOrigin._preview || dragOrigin;
                dragKind = '';
                commitStroke({
                    tool: 'op',
                    op: 'transform',
                    id: selectedId,
                    dx: 0,
                    dy: 0,
                    x: resized.x,
                    y: resized.y,
                    w: resized.w,
                    h: resized.h,
                    points: resized.points
                }, {
                    kind: 'transform',
                    id: selectedId,
                    dx: 0,
                    dy: 0,
                    before: {
                        x: dragOrigin.x,
                        y: dragOrigin.y,
                        w: dragOrigin.w,
                        h: dragOrigin.h,
                        points: dragOrigin.points
                    },
                    after: {
                        x: resized.x,
                        y: resized.y,
                        w: resized.w,
                        h: resized.h,
                        points: resized.points
                    }
                });
                return;
            }

            dragKind = '';
            if (tool === 'text' || tool === 'sticky') {
                restoreBoard();
                boardSnap = null;
                replay();
                openTextAt(p.x, p.y, tool === 'sticky');
                return;
            }
            if (isShapeTool(tool)) {
                restoreBoard();
                var props2 = currentDrawProps();
                var sstroke = {
                    id: uid(),
                    points: [points[0], points[points.length - 1]],
                    tool: tool,
                    color: props2.color,
                    width: props2.width
                };
                selectedId = sstroke.id;
                commitStroke(sstroke);
                boardSnap = null;
                return;
            }
            var inkMeta = isFreehandTool(tool) ? endLiveInk() : null;
            if (points.length >= 2) {
                var props3 = currentDrawProps();
                var free = {
                    id: (inkMeta && inkMeta.id) || uid(),
                    points: points.slice(),
                    tool: tool,
                    color: props3.color,
                    width: props3.width
                };
                if (tool === 'highlight') free.opacity = props3.opacity;
                // Pen / highlighter / eraser: keep drawing mode — do not auto-select the new stroke.
                selectedId = null;
                commitStroke(free);
            } else {
                if (inkMeta && inkMeta.sent > 0) publishInk({ id: inkMeta.id, phase: 'cancel' });
                replay();
            }
        }

        function receiveStroke(stroke, id) {
            if (!stroke) return;
            if (id) lastStrokeId = Math.max(lastStrokeId, id);
            if (boardFetchingFull) {
                boardLiveWhileLoad.push(stroke);
                if (stroke.id && stroke.tool !== 'op') finishRemoteInk(stroke.id);
                return;
            }
            applyIncoming(stroke);
            onStudentShow(false);
        }

        function receiveClear() {
            if (liveInk && liveInk.timer) clearTimeout(liveInk.timer);
            liveInk = null;
            drawing = false;
            dragKind = '';
            points = [];
            boardLoadSeq += 1;
            boardFetchingFull = false;
            boardLiveWhileLoad = [];
            remoteInk = {};
            finishedInkAt = {};
            boardLog = [];
            lastStrokeId = 0;
            selectedId = null;
            undoStack = [];
            redoStack = [];
            boardGrid = 'none';
            boardBg = '#ffffff';
            boardPage = 0;
            pageNames = { 0: 'Page 1' };
            syncBgUi();
            syncPageUi();
            replay();
            syncEditButtons();
            if (eduUi && eduUi.refresh) eduUi.refresh();
        }

        function applyMeta(data) {
            if (!data) return;
            if (typeof data.students_can_draw !== 'undefined') {
                hooks.applyCanDraw && hooks.applyCanDraw(!!data.students_can_draw);
            } else if (typeof data.can_draw !== 'undefined' && !cfg().isHost) {
                hooks.applyCanDraw && hooks.applyCanDraw(!!data.can_draw);
            }
        }

        function fetchBoard(full) {
            if (cfg().whiteboardEnabled === false) return;
            var wantFull = !!full || lastStrokeId < 1;
            if (boardBusy && !wantFull) return;
            if (drawing && wantFull) return;
            boardBusy = true;
            var seq = wantFull ? (boardLoadSeq += 1) : boardLoadSeq;
            if (wantFull) {
                boardFetchingFull = true;
                boardLiveWhileLoad = [];
            }
            var after = wantFull ? 0 : lastStrokeId;
            api('whiteboard.php?after=' + after).then(function (data) {
                if (wantFull && seq !== boardLoadSeq) return;
                if (!data || !data.ok) return;
                applyMeta(data);
                if (data.pdf_state !== undefined && global.CKPdf && typeof global.CKPdf.syncState === 'function') {
                    global.CKPdf.syncState(data.pdf_state);
                }
                var rows = data.strokes || [];
                if (wantFull) {
                    boardLog = [];
                    lastStrokeId = 0;
                }
                rows.forEach(function (row) {
                    if (row.id) lastStrokeId = Math.max(lastStrokeId, row.id);
                    if (row.stroke) pushLoggedStroke(row.stroke);
                });
                if (wantFull) {
                    boardLiveWhileLoad.forEach(function (s) { pushLoggedStroke(s); });
                    boardLiveWhileLoad = [];
                    boardFetchingFull = false;
                    replay();
                    if (!cfg().isHost && boardLog.length) onStudentShow(false);
                } else if (rows.length) {
                    replay();
                }
            }).catch(function () {
                if (wantFull && seq === boardLoadSeq) boardFetchingFull = false;
            }).then(function () {
                boardBusy = false;
                if (wantFull && seq === boardLoadSeq) boardFetchingFull = false;
            });
            startBoardPoll();
        }

        function startBoardPoll() {
            if (boardPollTimer) return;
            boardPollTimer = setInterval(function () {
                if (!isConnected()) return;
                fetchBoard(false);
            }, 4000);
        }

        function updateCanDrawUi() {
            var can = boardCanDraw();
            var locked = $('ckBoardLocked');
            if (locked) locked.hidden = cfg().isHost || can;
            var c = canvas();
            if (c && tool !== 'pan' && tool !== 'laser' && tool !== 'select') {
                c.style.cursor = can ? 'crosshair' : 'default';
            }
            var box = $('ckAllowDraw');
            if (box) box.checked = !!cfg().studentsCanDraw;
            document.querySelectorAll('#ckBoardTools [data-tool]').forEach(function (b) {
                b.disabled = !can && b.getAttribute('data-tool') !== 'pan' && b.getAttribute('data-tool') !== 'select';
            });
            ['ckBoardColor', 'ckBoardSize', 'ckBoardOpacity', 'ckBoardFont', 'ckBoardStyle', 'ckBoardGrid', 'ckBoardBg'].forEach(function (id) {
                var el = $(id);
                if (el) el.disabled = !can && id !== 'ckBoardGrid';
            });
            syncEditButtons();
        }

        function setStudentsCanDraw(allow) {
            allow = !!allow;
            var box = $('ckAllowDraw');
            if (box) box.checked = allow;
            api('whiteboard.php', { action: 'allow_draw', allow: allow }).then(function (data) {
                if (!data.ok) {
                    toast(data.error || 'Could not update the board.');
                    updateCanDrawUi();
                    return;
                }
                cfg().studentsCanDraw = !!data.students_can_draw;
                hooks.applyCanDraw && hooks.applyCanDraw(!!data.students_can_draw);
                publishData({ t: 'wb-allow', allow: !!data.students_can_draw });
                toast(data.students_can_draw ? 'Students may draw on the board.' : 'Only you can draw on the board.');
            });
        }

        function wireInput() {
            var c = canvas();
            if (!c || c._ckBoard) return;
            c._ckBoard = true;
            c.style.touchAction = 'none';
            var activePointers = {};

            function pointerMapCount() {
                var n = 0;
                for (var k in activePointers) {
                    if (Object.prototype.hasOwnProperty.call(activePointers, k)) n++;
                }
                return n;
            }

            function pointerPair() {
                var list = [];
                for (var k in activePointers) {
                    if (Object.prototype.hasOwnProperty.call(activePointers, k)) list.push(activePointers[k]);
                }
                return list;
            }

            function start(ev) { releaseStaleFocus(); startDraw(ev); }
            function move(ev) { moveDraw(ev); }
            function end(ev) { endDraw(ev); }

            if (window.PointerEvent) {
                c.addEventListener('pointerdown', function (ev) {
                    if (ev.pointerType === 'mouse' && ev.button !== 0) return;
                    var penDown = false;
                    for (var k in activePointers) {
                        if (activePointers[k] && activePointers[k].type === 'pen') penDown = true;
                    }
                    if (penDown && ev.pointerType !== 'pen') return;
                    activePointers[ev.pointerId] = { x: ev.clientX, y: ev.clientY, type: ev.pointerType || 'mouse' };
                    if (pointerMapCount() >= 2) {
                        var pair = pointerPair();
                        beginPinch(pair[0].x, pair[0].y, pair[1].x, pair[1].y);
                        ev.preventDefault();
                        return;
                    }
                    try { c.setPointerCapture(ev.pointerId); } catch (e) {}
                    start(ev);
                });
                window.addEventListener('pointermove', function (ev) {
                    if (activePointers[ev.pointerId]) {
                        activePointers[ev.pointerId].x = ev.clientX;
                        activePointers[ev.pointerId].y = ev.clientY;
                    } else if (ev.target !== c && !(c.contains && c.contains(ev.target))) {
                        return;
                    }
                    if (pointerMapCount() >= 2 && activePointers[ev.pointerId]) {
                        var pair = pointerPair();
                        if (pair.length >= 2) applyPinch(pair[0].x, pair[0].y, pair[1].x, pair[1].y);
                        ev.preventDefault();
                        return;
                    }
                    move(ev);
                });
                function onPointerEnd(ev) {
                    var tracked = !!activePointers[ev.pointerId];
                    delete activePointers[ev.pointerId];
                    if (!tracked) return;
                    if (isPinching) {
                        if (pointerMapCount() < 2) {
                            isPinching = false;
                            drawing = false;
                            dragKind = '';
                        }
                        return;
                    }
                    end(ev);
                }
                window.addEventListener('pointerup', onPointerEnd);
                window.addEventListener('pointercancel', onPointerEnd);
            } else {
                c.addEventListener('mousedown', start);
                c.addEventListener('mousemove', move);
                window.addEventListener('mouseup', end);
                c.addEventListener('touchstart', start, { passive: false });
                c.addEventListener('touchmove', move, { passive: false });
                c.addEventListener('touchend', end);
                c.addEventListener('touchcancel', end);
            }
            c.addEventListener('dblclick', function (ev) {
                if (!boardCanDraw()) return;
                var p = pointerPos(ev);
                var hit = hitTest(p.x, p.y);
                if (!hit) return;
                var obj = sceneFromLog().objects[hit];
                if (obj && (obj.tool === 'text' || obj.tool === 'sticky')) {
                    if (obj.locked && !cfg().isHost) return;
                    selectObject(hit);
                    editTextObject(obj);
                    ev.preventDefault();
                }
            });
            c.addEventListener('wheel', function (ev) {
                if (global.CKPdf && global.CKPdf.active) return;
                if (!ev.ctrlKey && !ev.metaKey) return;
                ev.preventDefault();
                var factor = ev.deltaY < 0 ? 1.08 : 0.92;
                setZoom(boardZoom * factor, ev.clientX, ev.clientY);
            }, { passive: false });

            window.addEventListener('resize', function () {
                applyViewTransform();
                paintSelection();
            });
            window.addEventListener('orientationchange', function () {
                setTimeout(function () {
                    applyViewTransform();
                    paintSelection();
                }, 150);
            });
        }

        function pageTotal() {
            var maxPage = boardPage;
            (boardLog || []).forEach(function (s) {
                if (s && typeof s.page === 'number') maxPage = Math.max(maxPage, s.page);
            });
            return Math.max(1, maxPage + 1);
        }

        function switchBoardPage(n, opts) {
            opts = opts || {};
            n = Math.max(0, Math.min(40, parseInt(n, 10) || 0));
            if (n === boardPage && !opts.create) {
                syncPageUi();
                replay();
                return;
            }
            boardPage = n;
            selectedId = null;
            if (!pageNames[n]) pageNames[n] = 'Page ' + (n + 1);
            syncPageUi();
            replay();
            if (opts.sync !== false && boardCanDraw()) {
                commitStroke({
                    tool: 'op',
                    op: 'page',
                    page: n,
                    name: pageNames[n],
                    follow: true
                }, null, { skipUndo: true });
            }
        }

        function paintBoardThumbnails() {
            if (global.CKPdf && global.CKPdf.active && boardPage === 0 && pageTotal() < 2) return;
            var scene = sceneFromLog();
            var total = pageTotal();
            for (var p = 1; p <= total; p++) {
                var thumb = $('ckBoardThumbCanvas_' + p);
                if (!thumb) continue;
                var ctx = thumb.getContext('2d');
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.clearRect(0, 0, thumb.width, thumb.height);
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, thumb.width, thumb.height);
                ctx.save();
                ctx.scale(thumb.width / CANVAS_W, thumb.height / CANVAS_H);
                drawPageFilter = p - 1;
                try {
                    scene.order.forEach(function (id) {
                        drawObject(ctx, scene.objects[id]);
                    });
                } finally {
                    drawPageFilter = null;
                    ctx.restore();
                }
            }
        }

        function scheduleThumbPaint() {
            if (thumbPaintTimer) clearTimeout(thumbPaintTimer);
            thumbPaintTimer = setTimeout(function () {
                thumbPaintTimer = null;
                paintBoardThumbnails();
            }, 40);
        }

        function renderBoardThumbnails(total, currentPage) {
            var list = $('ckPageThumbnails');
            if (!list) return;
            var html = '';
            for (var p = 1; p <= total; p++) {
                var active = (p === currentPage) ? ' is-active' : '';
                html += '<div class="ck-pns-item' + active + '" data-page="' + p + '">' +
                    '<div class="ck-pns-thumb">' +
                    '<canvas class="ck-thumb-canvas" id="ckBoardThumbCanvas_' + p + '" width="72" height="45"></canvas>' +
                    '</div>' +
                    '<span class="ck-pns-num">' + p + '</span>' +
                    '</div>';
            }
            list.innerHTML = html;

            var badge = $('ckPnsCurBadge');
            if (badge) badge.textContent = currentPage + '/' + total;
            var prevBtn = $('ckPnsPrevBtn');
            if (prevBtn) prevBtn.disabled = currentPage <= 1;
            var nextBtn = $('ckPnsNextBtn');
            if (nextBtn) nextBtn.disabled = currentPage >= total;

            list.querySelectorAll('.ck-pns-item').forEach(function (item) {
                item.addEventListener('click', function () {
                    var pg = parseInt(item.getAttribute('data-page'), 10) || 1;
                    if (global.CKPdf && global.CKPdf.active) {
                        global.CKPdf.goToPage(pg, true);
                    } else {
                        switchBoardPage(pg - 1);
                    }
                });
            });
        }

        function syncPageUi() {
            var el = $('ckBoardPageName');
            if (el) el.textContent = pageNames[boardPage] || ('Page ' + (boardPage + 1));
            var num = $('ckBoardPageNum');
            if (num) num.textContent = String(boardPage + 1);

            var pdfOwnsIndicator = !!(global.CKPdf && global.CKPdf.active) && boardPage === 0 && pageTotal() < 2;
            if (!pdfOwnsIndicator) {
                var total = pageTotal();
                var pill = $('ckBoardPageIndicator');
                if (pill) pill.textContent = (boardPage + 1) + ' / ' + total;
                renderBoardThumbnails(total, boardPage + 1);
                scheduleThumbPaint();
            }
        }

        function placeEdu(obj, opts) {
            if (!boardCanDraw()) return null;
            obj = obj || {};
            opts = opts || {};
            var stroke = {
                tool: 'edu',
                kind: obj.kind,
                id: obj.id || uid(),
                x: obj.x != null ? obj.x : 60 + (boardPage % 3) * 12,
                y: obj.y != null ? obj.y : 50 + (boardPage % 3) * 12,
                w: obj.w || 180,
                h: obj.h || 120,
                color: obj.color || penColor,
                data: obj.data || {},
                page: typeof obj.page === 'number' ? obj.page : boardPage,
                locked: !!obj.locked,
                hidden: !!obj.hidden,
                points: obj.points
            };
            selectedId = stroke.id;
            commitStroke(stroke, opts.undoEntry || null, { skipUndo: !!opts.skipUndo });
            return stroke;
        }

        function commitTextObject(payload, opts) {
            if (!boardCanDraw()) return null;
            payload = payload || {};
            opts = opts || {};
            var stroke = {
                tool: payload.tool === 'sticky' ? 'sticky' : 'text',
                id: payload.id || uid(),
                x: payload.x || 40,
                y: payload.y || 40,
                text: payload.text || '',
                color: payload.color || penColor,
                size: payload.size || 18,
                font: payload.font || textFont,
                style: payload.style || textStyle,
                align: payload.align || 'left',
                lineHeight: payload.lineHeight || 1.35,
                page: typeof payload.page === 'number' ? payload.page : boardPage
            };
            if (payload.w) stroke.w = payload.w;
            if (payload.h) stroke.h = payload.h;
            if (payload.underline) stroke.underline = true;
            if (payload.bg) stroke.bg = payload.bg;
            if (stroke.tool === 'sticky') {
                stroke.fill = payload.fill || '#fef08a';
                stroke.w = payload.w || 160;
                stroke.h = payload.h || 120;
            }
            selectedId = stroke.id;
            commitStroke(stroke, opts.undoEntry || null, { skipUndo: !!opts.skipUndo });
            return stroke;
        }

        function exportPng() {
            var c = canvas();
            if (!c) return null;
            try { return c.toDataURL('image/png'); } catch (e) { return null; }
        }

        function mountH2tUi() {
            if (h2tUi || !global.CKBoardH2T || typeof global.CKBoardH2T.mount !== 'function') return;
            h2tUi = global.CKBoardH2T.mount({
                $: $,
                toast: toast,
                uid: uid,
                deepClone: deepClone,
                canDraw: boardCanDraw,
                apiCall: api,
                getTool: function () { return tool; },
                setTool: setTool,
                getPage: function () { return boardPage; },
                getScene: function () { return sceneFromLog(); },
                boundsOf: boundsOf,
                drawObject: drawObject,
                hitTestInk: hitTestInk,
                selectId: function (id) { selectObject(id || null); },
                placeEdu: placeEdu,
                commitTextObject: commitTextObject,
                commitOp: function (stroke, undoEntry, opts) { commitStroke(stroke, undoEntry, opts || {}); },
                pushUndo: pushUndo,
                replay: replay
            });
        }

        function mountEduUi() {
            if (eduUi || !global.CKBoardEdu || typeof global.CKBoardEdu.mount !== 'function') return;
            eduUi = global.CKBoardEdu.mount({
                placeEdu: placeEdu,
                setTool: setTool,
                getTool: function () { return tool; },
                getSelected: function () {
                    if (!selectedId) return null;
                    return sceneFromLog().objects[selectedId] || null;
                },
                selectId: function (id) { selectObject(id || null); },
                patchSelected: patchSelected,
                editTextObject: function (obj) { editTextObject(obj); },
                commitOp: function (stroke) { commitStroke(stroke); },
                getPage: function () { return boardPage; },
                setPage: function (n, opts) {
                    switchBoardPage(n, opts);
                },
                renamePage: function (name) {
                    pageNames[boardPage] = String(name || '').slice(0, 40) || ('Page ' + (boardPage + 1));
                    syncPageUi();
                    if (boardCanDraw()) commitStroke({ tool: 'op', op: 'page', page: boardPage, name: pageNames[boardPage] }, null, { skipUndo: true });
                },
                getScene: function () { return sceneFromLog(); },
                getLog: function () { return boardLog.slice(); },
                restoreLog: function (log) {
                    boardLog = Array.isArray(log) ? log.slice() : [];
                    replay();
                },
                replay: replay,
                exportPng: exportPng,
                canDraw: boardCanDraw,
                isHost: function () { return !!(cfg().isHost); },
                toast: toast,
                getColor: function () { return penColor; },
                setColor: function (c) { penColor = c || penColor; var el = $('ckBoardColor'); if (el) el.value = penColor; },
                getSize: function () { return penWidth; },
                setSize: function (n) { penWidth = Math.max(1, Math.min(48, n || 3)); var el = $('ckBoardSize'); if (el) el.value = String(penWidth); },
                uid: uid,
                undo: undo,
                redo: redo,
                duplicateSelected: duplicateSelected,
                deleteSelected: deleteSelected,
                $: $,
                publishData: publishData,
                clearBoard: function () {
                    var btn = $('ckBoardClear');
                    if (btn) btn.click();
                }
            }, {
                onPresent: function () {},
                onFollow: function () {}
            });
            syncPageUi();
        }

        var TYPING_INPUT_TYPES = {
            text: 1, search: 1, email: 1, url: 1, tel: 1, password: 1, number: 1,
            date: 1, 'datetime-local': 1, month: 1, time: 1, week: 1
        };
        var TOOL_KEYS = {
            v: 'select', p: 'pen', h: 'highlight', e: 'erase', l: 'laser', z: 'laser',
            a: 'arrow', r: 'rect', o: 'ellipse', t: 'text', n: 'sticky', m: 'pan', w: 'h2t'
        };
        var HOST_ALT_KEYS = { s: 'ckShare', c: 'ckCam', p: 'ckSharePause' };

        // Range sliders, colour pickers, checkboxes and buttons keep focus after use but
        // accept no typed letters, so they must not disable board shortcuts.
        function isTypingTarget(el) {
            if (!el || el === document.body || el === document.documentElement) return false;
            if (el.isContentEditable) return true;
            var tag = (el.tagName || '').toUpperCase();
            if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
            if (tag !== 'INPUT') return false;
            return !!TYPING_INPUT_TYPES[String(el.type || 'text').toLowerCase()];
        }

        // Latin ev.key respects AZERTY/Dvorak; ev.code covers Sinhala/Tamil layouts, IME
        // modes and macOS Alt+letter, where ev.key is not a Latin letter.
        function shortcutLetter(ev) {
            var k = ev.key || '';
            if (k.length === 1 && /[a-z]/i.test(k)) return k.toLowerCase();
            var code = ev.code || '';
            if (/^Key[A-Z]$/.test(code)) return code.charAt(3).toLowerCase();
            return '';
        }

        function boardShortcutsActive() {
            if (!document.body.classList.contains('ck-in-room')) return false;
            var c = canvas();
            var stageEl = $('ckBoardStage') || c;
            if (!c || !stageEl) return false;
            var fs = document.fullscreenElement || document.webkitFullscreenElement;
            if (fs) return fs === stageEl || !!(fs.contains && fs.contains(stageEl));
            if (!c.getClientRects().length) return false;
            var r = c.getBoundingClientRect();
            return r.width > 0 && r.height > 0 && r.right > 0 && r.bottom > 0 &&
                r.left < (window.innerWidth || 0) && r.top < (window.innerHeight || 0);
        }

        function resetSpacePan() {
            if (!spacePan) return;
            spacePan = false;
            var c = canvas();
            if (c && tool !== 'pan') c.style.cursor = tool === 'select' ? 'default' : (boardCanDraw() ? 'crosshair' : 'default');
        }

        function onBoardKeyDown(ev) {
            if (ev.isComposing || ev.keyCode === 229) return;
            var cfgNow = cfg();
            var letter = shortcutLetter(ev);
            var target = (ev.composedPath && ev.composedPath()[0]) || ev.target;

            if (ev.altKey && !ev.ctrlKey && !ev.metaKey) {
                var altId = HOST_ALT_KEYS[letter];
                if (!altId || !cfgNow.isHost || !document.body.classList.contains('ck-in-room')) return;
                ev.preventDefault();
                if (ev.repeat) return;
                var altBtn = $(altId);
                if (altBtn && !altBtn.disabled) altBtn.click();
                return;
            }

            if (isTypingTarget(target) || isTypingTarget(document.activeElement)) return;
            if (!boardShortcutsActive()) return;

            if (ev.code === 'Space') {
                ev.preventDefault();
                if (ev.repeat || spacePan) return;
                spacePan = true;
                var c = canvas();
                if (c) c.style.cursor = 'grab';
                return;
            }

            if (ev.ctrlKey || ev.metaKey) {
                if (ev.altKey) return;
                if (letter === 'z') {
                    ev.preventDefault();
                    if (ev.shiftKey) redo();
                    else undo();
                } else if (letter === 'y') {
                    ev.preventDefault();
                    redo();
                } else if (letter === 'c') {
                    copySelected();
                } else if (letter === 'v') {
                    ev.preventDefault();
                    pasteClipboard();
                } else if (letter === 'd') {
                    ev.preventDefault();
                    duplicateSelected();
                }
                return;
            }
            if (ev.altKey) return;

            if (ev.key === 'Delete' || ev.key === 'Del' || ev.key === 'Backspace') {
                ev.preventDefault();
                if (selectedId) {
                    deleteSelected();
                    return;
                }
                if (ev.key !== 'Backspace' && !ev.repeat && cfgNow.isHost) {
                    var clearBtn = $('ckBoardClear');
                    if (clearBtn && !clearBtn.disabled) clearBtn.click();
                }
                return;
            }

            if (letter === 'f') {
                ev.preventDefault();
                if (!ev.repeat && typeof global.toggleBoardFullscreen === 'function') global.toggleBoardFullscreen();
                return;
            }

            var next = TOOL_KEYS[letter];
            if (!next) return;
            ev.preventDefault();
            if (ev.repeat) return;
            if (!boardCanDraw() && next !== 'select' && next !== 'pan') return;
            setTool(next);
        }

        function onBoardKeyUp(ev) {
            if (ev.code === 'Space') resetSpacePan();
        }

        function onBoardWindowBlur() {
            resetSpacePan();
        }

        function unbindBoardShortcuts() {
            window.removeEventListener('keydown', onBoardKeyDown, true);
            window.removeEventListener('keyup', onBoardKeyUp, true);
            window.removeEventListener('blur', onBoardWindowBlur);
            window.removeEventListener('pagehide', onBoardPageHide);
            global.__ckBoardShortcutsBound = null;
        }

        function onBoardPageHide(ev) {
            if (ev && ev.persisted) return;
            unbindBoardShortcuts();
        }

        // Capture phase so no inner stopPropagation (PDF viewer, panels) can swallow keys.
        function bindBoardShortcuts() {
            var prev = global.__ckBoardShortcutsBound;
            if (prev === unbindBoardShortcuts) return;
            if (typeof prev === 'function') prev();
            window.addEventListener('keydown', onBoardKeyDown, true);
            window.addEventListener('keyup', onBoardKeyUp, true);
            window.addEventListener('blur', onBoardWindowBlur);
            window.addEventListener('pagehide', onBoardPageHide);
            global.__ckBoardShortcutsBound = unbindBoardShortcuts;
        }

        // Pointer handlers call preventDefault, so a focused slider, select or chat box
        // would otherwise keep focus and silently block every shortcut.
        function releaseStaleFocus() {
            var a = document.activeElement;
            if (!a || a === document.body || typeof a.blur !== 'function') return;
            var tag = (a.tagName || '').toUpperCase();
            var editable = tag === 'TEXTAREA' || a.isContentEditable;
            var stage = $('ckBoardStage');
            if (editable && stage && stage.contains(a)) return;
            if (editable || tag === 'INPUT' || tag === 'SELECT' || tag === 'IFRAME') {
                try { a.blur(); } catch (e) {}
            }
        }

        function wireTools() {
            if (toolsWired) return;
            toolsWired = true;
            mountEduUi();
            mountH2tUi();

            document.querySelectorAll('#ckBoardTools [data-tool]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var t = b.getAttribute('data-tool');
                    if (!boardCanDraw() && t !== 'pan' && t !== 'select') return;
                    setTool(t);
                });
            });

            var colorEl = $('ckBoardColor');
            if (colorEl) {
                colorEl.addEventListener('input', function () {
                    penColor = colorEl.value || '#111827';
                    document.querySelectorAll('#ckTppSwatches .ck-color-swatch').forEach(function (s) {
                        s.classList.toggle('is-active', (s.getAttribute('data-color') || '').toLowerCase() === penColor.toLowerCase());
                    });
                    if (selectedId && tool === 'select') patchSelected({ color: penColor });
                });
            }

            // Quick color swatches in properties panel
            document.querySelectorAll('#ckTppSwatches .ck-color-swatch').forEach(function (swatch) {
                swatch.addEventListener('click', function () {
                    if (!boardCanDraw()) return;
                    var col = swatch.getAttribute('data-color');
                    if (!col) return;
                    penColor = col;
                    if (colorEl) colorEl.value = col;
                    document.querySelectorAll('#ckTppSwatches .ck-color-swatch').forEach(function (s) {
                        s.classList.toggle('is-active', s === swatch);
                    });
                    if (selectedId && tool === 'select') patchSelected({ color: penColor });
                });
            });

            var sizeEl = $('ckBoardSize');
            if (sizeEl) {
                sizeEl.addEventListener('input', function () {
                    penWidth = clamp(parseInt(sizeEl.value, 10) || 3, 1, 48);
                    var szVal = $('ckBoardSizeVal');
                    if (szVal) szVal.textContent = penWidth + 'px';
                    if (selectedId && tool === 'select') {
                        var scene = sceneFromLog();
                        var o = scene.objects[selectedId];
                        if (!o) return;
                        var w = penWidth;
                        if (o.tool === 'highlight') w = clamp(penWidth * 3, 8, 48);
                        if (o.tool === 'erase') w = eraserWidthFor(penWidth);
                        if (o.tool === 'text' || o.tool === 'sticky') {
                            patchSelected({ size: clamp(Math.round(12 + penWidth * 1.5), 12, 72) });
                        } else {
                            patchSelected({ width: w });
                        }
                    }
                });
            }
            var opEl = $('ckBoardOpacity');
            if (opEl) {
                opEl.addEventListener('input', function () {
                    highlightOpacity = clamp((parseInt(opEl.value, 10) || 35) / 100, 0.1, 0.9);
                    var opVal = $('ckBoardOpacityVal');
                    if (opVal) opVal.textContent = Math.round(highlightOpacity * 100) + '%';
                    if (selectedId && tool === 'select') patchSelected({ opacity: highlightOpacity });
                });
            }
            var fontEl = $('ckBoardFont');
            if (fontEl) {
                fontEl.addEventListener('change', function () {
                    textFont = fontEl.value || 'sans';
                    if (selectedId) patchSelected({ font: textFont });
                });
            }
            var fontSizeEl = $('ckBoardFontSize');
            if (fontSizeEl) {
                fontSizeEl.addEventListener('change', function () {
                    var sz = parseInt(fontSizeEl.value, 10) || 24;
                    if (selectedId) patchSelected({ size: sz });
                });
            }
            var styleEl = $('ckBoardStyle');
            if (styleEl) {
                styleEl.addEventListener('change', function () {
                    textStyle = styleEl.value || 'normal';
                    if (selectedId) patchSelected({ style: textStyle });
                });
            }

            // Formatting toggles: B, I, U, Alignments
            $('ckFmtBold') && $('ckFmtBold').addEventListener('click', function () {
                var has = textStyle.indexOf('bold') >= 0;
                var italic = textStyle.indexOf('italic') >= 0;
                textStyle = (has ? (italic ? 'italic' : 'normal') : (italic ? 'bolditalic' : 'bold'));
                this.classList.toggle('is-active', !has);
                if (styleEl) styleEl.value = textStyle;
                if (selectedId) patchSelected({ style: textStyle });
            });
            $('ckFmtItalic') && $('ckFmtItalic').addEventListener('click', function () {
                var has = textStyle.indexOf('italic') >= 0;
                var bold = textStyle.indexOf('bold') >= 0;
                textStyle = (has ? (bold ? 'bold' : 'normal') : (bold ? 'bolditalic' : 'italic'));
                this.classList.toggle('is-active', !has);
                if (styleEl) styleEl.value = textStyle;
                if (selectedId) patchSelected({ style: textStyle });
            });
            $('ckFmtUnderline') && $('ckFmtUnderline').addEventListener('click', function () {
                var active = this.classList.toggle('is-active');
                if (selectedId) patchSelected({ underline: active });
            });
            document.querySelectorAll('.ck-align-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var align = btn.getAttribute('data-align') || 'left';
                    document.querySelectorAll('.ck-align-btn').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
                    if (selectedId) patchSelected({ align: align });
                });
            });

            $('ckBoardUndo') && $('ckBoardUndo').addEventListener('click', undo);
            $('ckBoardRedo') && $('ckBoardRedo').addEventListener('click', redo);
            $('ckBoardDup') && $('ckBoardDup').addEventListener('click', duplicateSelected);
            $('ckBoardDelete') && $('ckBoardDelete').addEventListener('click', deleteSelected);
            $('ckBoardZoomIn') && $('ckBoardZoomIn').addEventListener('click', function () { setZoom(boardZoom * 1.15); });
            $('ckBoardZoomOut') && $('ckBoardZoomOut').addEventListener('click', function () { setZoom(boardZoom / 1.15); });
            $('ckBoardZoomReset') && $('ckBoardZoomReset').addEventListener('click', function () {
                boardZoom = 1;
                boardPanX = 0;
                boardPanY = 0;
                applyViewTransform();
            });

            // Bottom pill page navigation & fit select
            $('ckBoardPrevPage') && $('ckBoardPrevPage').addEventListener('click', function () {
                if (global.CKPdf && global.CKPdf.active) {
                    if (global.CKPdf.currentPage > 1) global.CKPdf.goToPage(global.CKPdf.currentPage - 1, true);
                } else if (boardPage > 0) {
                    switchBoardPage(boardPage - 1);
                }
            });
            $('ckBoardNextPage') && $('ckBoardNextPage').addEventListener('click', function () {
                if (global.CKPdf && global.CKPdf.active) {
                    if (global.CKPdf.currentPage < global.CKPdf.totalPages) global.CKPdf.goToPage(global.CKPdf.currentPage + 1, true);
                } else {
                    switchBoardPage(boardPage + 1);
                }
            });
            var fitSelect = $('ckBoardFitSelect');
            if (fitSelect) {
                fitSelect.addEventListener('change', function () {
                    var val = fitSelect.value;
                    if (val === 'fit' || val === 'width') {
                        if (global.CKPdf && global.CKPdf.active) {
                            global.CKPdf.zoom = 1.0;
                            global.CKPdf.renderPage(global.CKPdf.currentPage);
                        } else {
                            boardZoom = 1;
                            boardPanX = 0;
                            boardPanY = 0;
                            applyViewTransform();
                        }
                    } else {
                        var num = (parseInt(val, 10) || 100) / 100;
                        setZoom(num);
                    }
                });
            }

            // More Options Dropdown & Export PNG
            var moreOptionsBtn = $('ckBoardMoreOptionsBtn');
            var moreMenu = $('ckBoardMoreMenu');
            var moreWrap = $('ckBoardMoreWrap');

            if (moreOptionsBtn && moreMenu) {
                moreOptionsBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    moreMenu.hidden = !moreMenu.hidden;
                });
                document.addEventListener('click', function (e) {
                    if (moreWrap && !moreWrap.contains(e.target)) {
                        moreMenu.hidden = true;
                    }
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && !moreMenu.hidden) {
                        moreMenu.hidden = true;
                    }
                });
            }
            $('ckBoardExportPngBtn') && $('ckBoardExportPngBtn').addEventListener('click', function () {
                if (moreMenu) moreMenu.hidden = true;
                exportPng();
            });

            // Document download button
            $('ckBoardDownloadBtn') && $('ckBoardDownloadBtn').addEventListener('click', function (e) {
                if (global.CKPdf && global.CKPdf.active) {
                    global.CKPdf.triggerDownload();
                } else {
                    exportPng();
                }
            });

            // Left Page Navigation Sidebar Quick Actions
            $('ckPageAddBtn') && $('ckPageAddBtn').addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                switchBoardPage(boardPage + 1, { create: true });
            });
            $('ckPageRotateBtn') && $('ckPageRotateBtn').addEventListener('click', function () {
                if (global.CKPdf && global.CKPdf.active) {
                    global.CKPdf.rotation = (global.CKPdf.rotation + 90) % 360;
                    global.CKPdf.renderPage(global.CKPdf.currentPage);
                }
            });
            $('ckPageFitBtn') && $('ckPageFitBtn').addEventListener('click', function () {
                if (global.CKPdf && global.CKPdf.active) {
                    global.CKPdf.zoom = 1.0;
                    global.CKPdf.renderPage(global.CKPdf.currentPage);
                } else {
                    boardZoom = 1;
                    boardPanX = 0;
                    boardPanY = 0;
                    applyViewTransform();
                }
            });
            $('ckPageZoomInBtn') && $('ckPageZoomInBtn').addEventListener('click', function () {
                if (global.CKPdf && global.CKPdf.active) {
                    global.CKPdf.zoom = Math.min(3.0, (global.CKPdf.zoom || 1.0) + 0.2);
                    global.CKPdf.renderPage(global.CKPdf.currentPage);
                } else {
                    setZoom(boardZoom * 1.15);
                }
            });
            $('ckPageZoomOutBtn') && $('ckPageZoomOutBtn').addEventListener('click', function () {
                if (global.CKPdf && global.CKPdf.active) {
                    global.CKPdf.zoom = Math.max(0.4, (global.CKPdf.zoom || 1.0) - 0.2);
                    global.CKPdf.renderPage(global.CKPdf.currentPage);
                } else {
                    setZoom(boardZoom / 1.15);
                }
            });
            var gridEl = $('ckBoardGrid');
            if (gridEl) {
                gridEl.addEventListener('change', function () {
                    if (!boardCanDraw()) return;
                    var before = { grid: boardGrid, bg: boardBg };
                    boardGrid = gridEl.value || 'none';
                    commitStroke({ tool: 'op', op: 'bg', grid: boardGrid, bg: boardBg }, {
                        kind: 'bg',
                        before: before,
                        after: { grid: boardGrid, bg: boardBg }
                    });
                });
            }
            var bgEl = $('ckBoardBg');
            if (bgEl) {
                bgEl.addEventListener('input', function () {
                    if (!boardCanDraw()) return;
                    var before = { grid: boardGrid, bg: boardBg };
                    boardBg = bgEl.value || '#ffffff';
                    commitStroke({ tool: 'op', op: 'bg', grid: boardGrid, bg: boardBg }, {
                        kind: 'bg',
                        before: before,
                        after: { grid: boardGrid, bg: boardBg }
                    });
                });
            }
            $('ckBoardClear') && $('ckBoardClear').addEventListener('click', function () {
                if (!window.confirm('Clear the board for everyone? This cannot be undone.')) return;
                api('whiteboard.php', { action: 'clear' }).then(function () {
                    receiveClear();
                    publishData({ t: 'wb-clear' });
                });
            });
            function onAllowDrawChange(ev) {
                setStudentsCanDraw(!!ev.target.checked);
            }
            $('ckAllowDraw') && $('ckAllowDraw').addEventListener('change', onAllowDrawChange);

            // Sleek Page Navigation Controls
            var pnsToggle = $('ckPnsToggleBtn');
            var pnsAside = $('ckPageNavSidebar');
            var pnsDrawer = $('ckPnsDrawer');
            if (pnsToggle && pnsAside) {
                pnsToggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var coll = pnsAside.classList.toggle('is-collapsed');
                    pnsToggle.setAttribute('aria-expanded', coll ? 'false' : 'true');
                    if (pnsDrawer) pnsDrawer.hidden = coll;
                });
            }
            var pnsPrev = $('ckPnsPrevBtn');
            if (pnsPrev) {
                pnsPrev.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (global.CKPdf && global.CKPdf.active) {
                        global.CKPdf.prevPage();
                    } else if (boardPage > 0) {
                        switchBoardPage(boardPage - 1);
                    }
                });
            }
            var pnsNext = $('ckPnsNextBtn');
            if (pnsNext) {
                pnsNext.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (global.CKPdf && global.CKPdf.active) {
                        global.CKPdf.nextPage();
                    } else {
                        switchBoardPage(boardPage + 1);
                    }
                });
            }
            var pnsQuickAdd = $('ckPnsQuickAddBtn');
            if (pnsQuickAdd) {
                pnsQuickAdd.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var add = $('ckPageAddBtn');
                    if (add) add.click();
                });
            }

            bindBoardShortcuts();

            if (!laserTimer) laserTimer = setInterval(pruneLasers, 800);

            // Quick color chips via long-press not needed; expose swatches in CSS if present
            var props = document.querySelector('#ckBoardTools .ck-bt-props');
            if (props && !props._swatches) {
                props._swatches = true;
                var row = document.createElement('div');
                row.className = 'ck-bt-swatches';
                COLORS.forEach(function (col) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'ck-bt-swatch';
                    b.style.background = col;
                    b.title = col;
                    b.addEventListener('click', function () {
                        if (!boardCanDraw()) return;
                        penColor = col;
                        if (colorEl) colorEl.value = col;
                        if (selectedId && tool === 'select') patchSelected({ color: penColor });
                    });
                    row.appendChild(b);
                });
                props.insertBefore(row, props.firstChild);
            }

            // Shape Quick Button
            $('ckShapeQuickBtn') && $('ckShapeQuickBtn').addEventListener('click', function (e) {
                e.stopPropagation();
                setTool('rect');
            });

            // Overflow Button
            $('ckBoardOverflowBtn') && $('ckBoardOverflowBtn').addEventListener('click', function (e) {
                e.stopPropagation();
                var extras = document.querySelectorAll('#ckBoardTools .ck-wb-extra');
                extras.forEach(function (el) {
                    el.classList.toggle('is-visible');
                });
            });

            // Bottom Action Dock Bar
            $('ckPdfUploadBtn') && $('ckPdfUploadBtn').addEventListener('click', function () {
                if (global.CKPdf) {
                    global.CKPdf.triggerUpload();
                } else {
                    var addBtn = $('ckPageAddBtn');
                    if (addBtn) addBtn.click();
                }
            });

            $('ckBoardExportPdfBtn') && $('ckBoardExportPdfBtn').addEventListener('click', function () {
                var menu = $('ckBoardMoreMenu');
                if (menu) menu.hidden = true;
                if (global.CKPdf && global.CKPdf.active) {
                    global.CKPdf.triggerDownload();
                } else {
                    exportPng();
                }
            });

            $('ckBoardClearAllBtn') && $('ckBoardClearAllBtn').addEventListener('click', function () {
                var menu = $('ckBoardMoreMenu');
                if (menu) menu.hidden = true;
                var clearBtn = $('ckBoardClear');
                if (clearBtn) clearBtn.click();
            });

            $('ckDockWhiteboard') && $('ckDockWhiteboard').addEventListener('click', function () {
                var mob = $('ckMobileBoardToggle');
                if (mob) mob.click();
                this.classList.add('is-on');
            });

            $('ckDockShare') && $('ckDockShare').addEventListener('click', function () {
                var shareBtn = $('ckShare');
                if (shareBtn) shareBtn.click();
            });

            $('ckDockRecord') && $('ckDockRecord').addEventListener('click', function () {
                var recStart = $('ckRecStart');
                var recStop = $('ckRecStop');
                if (recStart && !recStart.hidden) recStart.click();
                else if (recStop && !recStop.hidden) recStop.click();
            });

            $('ckDockSettings') && $('ckDockSettings').addEventListener('click', function () {
                var settingsBtn = $('ckSettings');
                if (settingsBtn) settingsBtn.click();
            });

            initPropsSlidePanel();
        }

        function initPropsSlidePanel() {
            var panel = $('ckToolPropsPanel');
            var trigger = $('ckPropsTriggerZone');
            var handle = $('ckPropsOpenHandle');
            var viewport = $('ckBoardViewport');
            var closeBtn = $('ckTppCloseBtn');
            var advToggle = $('ckTppAdvToggle');
            var advBody = $('ckTppAdvBody');
            if (!panel) return;

            var hideTimer = null;
            var autoHideTimer = null;

            function showPanel() {
                if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
                if (autoHideTimer) { clearTimeout(autoHideTimer); autoHideTimer = null; }
                panel.classList.remove('ck-props-hidden');
                panel.classList.add('ck-props-visible');
                if (handle) handle.classList.add('is-open');
            }

            function hidePanel(immediate) {
                if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
                if (autoHideTimer) { clearTimeout(autoHideTimer); autoHideTimer = null; }
                if (immediate) {
                    panel.classList.add('ck-props-hidden');
                    panel.classList.remove('ck-props-visible');
                    if (handle) handle.classList.remove('is-open');
                } else {
                    hideTimer = setTimeout(function () {
                        panel.classList.add('ck-props-hidden');
                        panel.classList.remove('ck-props-visible');
                        if (handle) handle.classList.remove('is-open');
                    }, 350);
                }
            }

            function autoHideAfterSelect(delay) {
                if (autoHideTimer) clearTimeout(autoHideTimer);
                autoHideTimer = setTimeout(function () {
                    hidePanel(true);
                }, delay || 450);
            }

            // Right-edge hover zone activation
            if (trigger) {
                trigger.addEventListener('mouseenter', function () {
                    showPanel();
                });
            }

            // Viewport edge detection: when pointer moves within 28px of right edge
            if (viewport) {
                viewport.addEventListener('mousemove', function (e) {
                    var rect = viewport.getBoundingClientRect();
                    if (e.clientX >= rect.right - 28 && e.clientY >= rect.top && e.clientY <= rect.bottom) {
                        showPanel();
                    }
                });
            }

            // Mobile / trackpad touch handle
            if (handle) {
                handle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (panel.classList.contains('ck-props-visible')) {
                        hidePanel(true);
                    } else {
                        showPanel();
                    }
                });
            }

            var mobPropsBtn = $('ckMobilePropsBtn');
            if (mobPropsBtn) {
                mobPropsBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (panel.classList.contains('ck-props-visible')) {
                        hidePanel(true);
                    } else {
                        showPanel();
                    }
                });
            }

            // Panel hover persistence: stay open while pointer is inside
            panel.addEventListener('mouseenter', function () {
                if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
                if (autoHideTimer) { clearTimeout(autoHideTimer); autoHideTimer = null; }
            });
            panel.addEventListener('mouseleave', function () {
                hidePanel(false);
            });

            // Auto-hide when selecting color swatches
            panel.querySelectorAll('.ck-color-swatch').forEach(function (sw) {
                sw.addEventListener('click', function () {
                    autoHideAfterSelect(450);
                });
            });

            // Sliders: auto-hide on release
            ['ckBoardSize', 'ckBoardOpacity'].forEach(function (id) {
                var el = $(id);
                if (el) {
                    el.addEventListener('change', function () {
                        autoHideAfterSelect(550);
                    });
                    el.addEventListener('pointerup', function () {
                        autoHideAfterSelect(550);
                    });
                }
            });

            // Switches: auto-hide on toggle
            panel.querySelectorAll('.ck-switch-input').forEach(function (inp) {
                inp.addEventListener('change', function () {
                    autoHideAfterSelect(450);
                });
            });

            // Font & alignment changes: auto-hide
            ['ckBoardFont', 'ckBoardFontSize', 'ckBoardStyle'].forEach(function (id) {
                var el = $(id);
                if (el) {
                    el.addEventListener('change', function () {
                        autoHideAfterSelect(450);
                    });
                }
            });
            panel.querySelectorAll('.ck-align-btn, .ck-fmt-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    autoHideAfterSelect(450);
                });
            });

            // Advanced toggle inside panel
            if (advToggle && advBody) {
                advToggle.addEventListener('click', function () {
                    var isHidden = advBody.hidden;
                    advBody.hidden = !isHidden;
                    advToggle.classList.toggle('is-open', isHidden);
                });
            }

            // Manual close button
            if (closeBtn) {
                closeBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    hidePanel(true);
                });
            }

            // Click outside to close
            document.addEventListener('pointerdown', function (e) {
                if (!panel.classList.contains('ck-props-visible')) return;
                if (!panel.contains(e.target) && (!handle || !handle.contains(e.target)) && (!trigger || !trigger.contains(e.target))) {
                    hidePanel(true);
                }
            });

            // Escape key to close
            window.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && panel.classList.contains('ck-props-visible')) {
                    hidePanel(true);
                }
            });

            panel._show = showPanel;
            panel._hide = hidePanel;
        }

        function load() {
            wireInput();
            wireTools();
            mountEduUi();
            mountTextUi();
            mountH2tUi();
            updateCanDrawUi();
            setTool(tool);
            syncPageUi();
            fetchBoard(true);
        }

        return {
            load: load,
            fetch: fetchBoard,
            receiveStroke: receiveStroke,
            receiveInk: receiveInk,
            receiveClear: receiveClear,
            applyMeta: applyMeta,
            replay: replay,
            updateCanDrawUi: updateCanDrawUi,
            setStudentsCanDraw: setStudentsCanDraw,
            wireTools: wireTools,
            placeEdu: placeEdu,
            exportPng: exportPng,
            getPage: function () { return boardPage; },
            receiveLaser: function (msg, fromId) {
                if (!msg) return;
                showLaser(fromId || 'peer', !!msg.on, msg.x || 0, msg.y || 0, msg.color || '#ef4444');
            },
            receiveSpotlight: function (msg) {
                var layer = $('ckBoardLasers');
                if (!layer || !msg) return;
                var id = 'spotlight';
                if (!msg.on) {
                    var old = layer.querySelector('.ck-spotlight');
                    if (old) old.remove();
                    return;
                }
                var el = layer.querySelector('.ck-spotlight');
                if (!el) {
                    el = document.createElement('div');
                    el.className = 'ck-spotlight';
                    layer.appendChild(el);
                }
                el.style.left = ((msg.x || 0) / CANVAS_W * 100) + '%';
                el.style.top = ((msg.y || 0) / CANVAS_H * 100) + '%';
            },
            isDrawing: function () { return drawing; },
            getLastStrokeId: function () { return lastStrokeId; },
            setLastStrokeId: function (id) { lastStrokeId = id; },
            recalculateViewport: function () { applyViewTransform(); paintSelection(); }
        };
    }

    global.CKCreateBoard = createBoard;
})(window);
