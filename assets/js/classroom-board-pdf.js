/**
 * Interactive PDF Whiteboard module for LiveKit live classroom.
 * Enables teachers to upload PDFs, navigate pages, zoom, and annotate on top.
 * Synchronizes document state, page turns, and annotations to all students in real time.
 * Provides a dedicated secure Student PDF Download button in the top-right corner.
 */
(function (global) {
    'use strict';

    var CANVAS_W = 900;
    var CANVAS_H = 560;

    function $(id) {
        return document.getElementById(id);
    }

    var CKPdf = {
        docId: null,
        filename: '',
        totalPages: 0,
        currentPage: 1,
        zoom: 1.0,
        rotation: 0,
        pdfDoc: null,
        rendering: false,
        pendingPage: null,
        downloadToken: '',
        downloadUrl: '',
        active: false,
        hooks: {},

        init: function (hooks) {
            this.hooks = hooks || {};
            this.setupDom();
            this.bindEvents();

            // Set up PDF.js worker if available
            if (global.pdfjsLib) {
                global.pdfjsLib.GlobalWorkerOptions.workerSrc =
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            }

            // Check if initial config contains active PDF
            var cfg = (this.hooks.getCfg && this.hooks.getCfg()) || global.CK_CONFIG || {};
            if (cfg.activePdf && cfg.activePdf.doc_id) {
                this.syncState(cfg.activePdf);
            }

            this.watchContainer();

            // Retry attaching to top toolbar once all board modules finish mounting
            var self = this;
            setTimeout(function () { self.setupDom(); self.bindEvents(); }, 100);
            setTimeout(function () { self.setupDom(); self.bindEvents(); }, 500);
            setTimeout(function () { self.setupDom(); self.bindEvents(); }, 1200);
        },

        setupDom: function () {
            var cfg = (this.hooks.getCfg && this.hooks.getCfg()) || global.CK_CONFIG || {};
            var isHost = !!cfg.isHost;

            // Background canvas for PDF rendering inside #ckBoardWorld
            var world = $('ckBoardWorld');
            if (world && !$('ckPdfCanvas')) {
                var pdfCanvas = document.createElement('canvas');
                pdfCanvas.id = 'ckPdfCanvas';
                pdfCanvas.width = CANVAS_W;
                pdfCanvas.height = CANVAS_H;
                pdfCanvas.className = 'ck-pdf-canvas';
                var boardCanvas = $('ckBoard');
                if (boardCanvas) {
                    world.insertBefore(pdfCanvas, boardCanvas);
                } else {
                    world.appendChild(pdfCanvas);
                }
            }

            // Student Download PDF button (top-right corner of stage)
            var stage = $('ckBoardStage') || $('ckBoardViewport');
            if (stage && !$('ckPdfDownloadBtn')) {
                var dlBtn = document.createElement('a');
                dlBtn.id = 'ckPdfDownloadBtn';
                dlBtn.className = 'ck-pdf-download-btn';
                dlBtn.setAttribute('role', 'button');
                dlBtn.setAttribute('title', 'Download original PDF uploaded by teacher');
                dlBtn.setAttribute('aria-label', 'Download PDF');
                dlBtn.hidden = true;
                dlBtn.innerHTML = '<i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> <span>Download PDF</span>';
                stage.appendChild(dlBtn);
            }

            // PDF Toolbar Group
            var group = $('ckPdfToolsGroup');
            if (!group) {
                group = document.createElement('div');
                group.id = 'ckPdfToolsGroup';
                group.className = 'ck-bt-group ck-pdf-toolbar-group';
                group.setAttribute('data-group', 'pdf');

                var html = '';

                html += '<div class="ck-pdf-nav" id="ckPdfNav" style="display:none;">' +
                    '<button type="button" id="ckPdfPrev" class="ck-icon-btn ck-top-btn" title="Previous page" aria-label="Previous page" disabled><i class="bi bi-chevron-left" aria-hidden="true"></i></button>' +
                    '<span id="ckPdfPageIndicator" class="ck-pdf-page-indicator" title="Current PDF Page">0 / 0</span>' +
                    '<button type="button" id="ckPdfNext" class="ck-icon-btn ck-top-btn" title="Next page" aria-label="Next page" disabled><i class="bi bi-chevron-right" aria-hidden="true"></i></button>' +
                    '<button type="button" id="ckPdfZoomOut" class="ck-icon-btn ck-top-btn" title="Zoom out" aria-label="Zoom out"><i class="bi bi-zoom-out" aria-hidden="true"></i></button>' +
                    '<button type="button" id="ckPdfZoomIn" class="ck-icon-btn ck-top-btn" title="Zoom in" aria-label="Zoom in"><i class="bi bi-zoom-in" aria-hidden="true"></i></button>' +
                    '<button type="button" id="ckPdfFit" class="ck-icon-btn ck-top-btn" title="Fit PDF to screen" aria-label="Fit to screen"><i class="bi bi-aspect-ratio" aria-hidden="true"></i></button>' +
                    '<button type="button" id="ckPdfRotate" class="ck-icon-btn ck-top-btn" title="Rotate page" aria-label="Rotate"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>';

                if (isHost) {
                    html += '<button type="button" id="ckPdfClose" class="ck-icon-btn ck-top-btn ck-danger" title="Close and remove PDF" aria-label="Close PDF"><i class="bi bi-x-circle" aria-hidden="true"></i></button>';
                }

                html += '</div>';
                group.innerHTML = html;
            }

            // Attach group to #ckBoardTop (preferred) or #ckBoardTools
            var topBar = $('ckBoardTop');
            var tools = $('ckBoardTools');

            if (topBar && group.parentNode !== topBar) {
                var clearBtn = $('ckBoardClear');
                if (clearBtn && clearBtn.parentNode && clearBtn.parentNode.parentNode === topBar) {
                    topBar.insertBefore(group, clearBtn.parentNode);
                } else if (clearBtn && clearBtn.parentNode === topBar) {
                    topBar.insertBefore(group, clearBtn);
                } else {
                    topBar.appendChild(group);
                }
            } else if (!topBar && tools && group.parentNode !== tools) {
                var viewGroup = tools.querySelector('[data-group="view"]');
                if (viewGroup) {
                    tools.insertBefore(group, viewGroup);
                } else {
                    tools.appendChild(group);
                }
            }

            // Hidden file input for PDF upload
            if (isHost && !$('ckPdfFileInput')) {
                var fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.id = 'ckPdfFileInput';
                fileInput.accept = '.pdf,application/pdf';
                fileInput.hidden = true;
                document.body.appendChild(fileInput);
            }
        },

        triggerUpload: function () {
            var fileInput = $('ckPdfFileInput');
            if (!fileInput) {
                this.setupDom();
                fileInput = $('ckPdfFileInput');
            }
            if (fileInput) {
                fileInput.value = '';
                fileInput.click();
            }
        },

        bindEvents: function () {
            var self = this;

            // Upload button
            var uploadBtn = $('ckPdfUploadBtn');
            var fileInput = $('ckPdfFileInput');
            if (uploadBtn && !uploadBtn._ckBound && fileInput) {
                uploadBtn._ckBound = true;
                uploadBtn.addEventListener('click', function () {
                    self.triggerUpload();
                });

                fileInput.addEventListener('change', function (e) {
                    var file = e.target.files && e.target.files[0];
                    if (file) {
                        self.uploadPdf(file);
                    }
                });
            }

            // Navigation buttons
            var prevBtn = $('ckPdfPrev');
            if (prevBtn && !prevBtn._ckBound) {
                prevBtn._ckBound = true;
                prevBtn.addEventListener('click', function () {
                    if (self.currentPage > 1) {
                        self.goToPage(self.currentPage - 1, true);
                    }
                });
            }

            var nextBtn = $('ckPdfNext');
            if (nextBtn && !nextBtn._ckBound) {
                nextBtn._ckBound = true;
                nextBtn.addEventListener('click', function () {
                    if (self.currentPage < self.totalPages) {
                        self.goToPage(self.currentPage + 1, true);
                    }
                });
            }

            // Zoom Out button
            var zoomOutBtn = $('ckPdfZoomOut');
            if (zoomOutBtn && !zoomOutBtn._ckBound) {
                zoomOutBtn._ckBound = true;
                zoomOutBtn.addEventListener('click', function () {
                    self.zoom = Math.max(0.5, (self.zoom || 1.0) - 0.25);
                    self.applyScale();
                });
            }

            // Zoom In button
            var zoomInBtn = $('ckPdfZoomIn');
            if (zoomInBtn && !zoomInBtn._ckBound) {
                zoomInBtn._ckBound = true;
                zoomInBtn.addEventListener('click', function () {
                    self.zoom = Math.min(2, (self.zoom || 1.0) + 0.25);
                    self.applyScale();
                });
            }

            // Fit button
            var fitBtn = $('ckPdfFit');
            if (fitBtn && !fitBtn._ckBound) {
                fitBtn._ckBound = true;
                fitBtn.addEventListener('click', function () {
                    self.zoom = 1.0;
                    self.applyScale();
                });
            }

            // Rotate button
            var rotateBtn = $('ckPdfRotate');
            if (rotateBtn && !rotateBtn._ckBound) {
                rotateBtn._ckBound = true;
                rotateBtn.addEventListener('click', function () {
                    self.rotation = (self.rotation + 90) % 360;
                    self.applyScale();
                });
            }

            // Close button
            var closeBtn = $('ckPdfClose');
            if (closeBtn && !closeBtn._ckBound) {
                closeBtn._ckBound = true;
                closeBtn.addEventListener('click', function () {
                    if (confirm('Close and remove this PDF from the whiteboard?')) {
                        self.closePdf(true);
                    }
                });
            }

            // Student Download button
            var dlBtn = $('ckPdfDownloadBtn');
            if (dlBtn && !dlBtn._ckBound) {
                dlBtn._ckBound = true;
                dlBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.triggerDownload();
                });
            }

            // Toolbar Download button
            var tbDlBtn = $('ckBoardDownloadBtn');
            if (tbDlBtn && !tbDlBtn._ckPdfBound) {
                tbDlBtn._ckPdfBound = true;
                tbDlBtn.addEventListener('click', function (e) {
                    if (self.active) {
                        e.preventDefault();
                        self.triggerDownload();
                    }
                });
            }

            // Mobile Sheet Download button
            var mobDlBtn = $('ckMobileDownloadPdfBtn');
            if (mobDlBtn && !mobDlBtn._ckPdfBound) {
                mobDlBtn._ckPdfBound = true;
                mobDlBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (self.active) {
                        self.triggerDownload();
                    } else {
                        self.toast('No active PDF to download.');
                    }
                });
            }
        },

        uploadPdf: function (file) {
            var self = this;
            var cfg = (this.hooks.getCfg && this.hooks.getCfg()) || global.CK_CONFIG || {};

            if (!file.name.toLowerCase().endsWith('.pdf') && file.type !== 'application/pdf') {
                self.toast('Please select a valid PDF file.');
                return;
            }

            var maxMb = parseInt(cfg.pdfMaxMb, 10) || 30;
            if (file.size > maxMb * 1024 * 1024) {
                self.toast('File is too large. Maximum allowed size is ' + maxMb + 'MB.');
                return;
            }

            self.toast('Uploading PDF: ' + file.name + '...');
            var uploadBtn = $('ckPdfUploadBtn');
            if (uploadBtn) uploadBtn.disabled = true;

            var formData = new FormData();
            formData.append('pdf_file', file);
            formData.append('action', 'upload');
            formData.append('lesson', cfg.lessonId);
            formData.append('csrf_token', cfg.csrf || '');

            var apiBase = cfg.apiBase || '/api/classroom/';
            var uploadUrl = apiBase.replace(/\/?$/, '/') + 'pdf.php';

            fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': cfg.csrf || ''
                },
                body: formData
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (uploadBtn) uploadBtn.disabled = false;
                    if (!data || !data.ok) {
                        self.toast(data.error || 'Upload failed. Please try again.');
                        return;
                    }

                    self.docId = data.doc_id;
                    self.filename = data.filename || file.name;
                    self.downloadToken = data.download_token || '';
                    self.downloadUrl = data.download_url || '';
                    self.active = true;

                    self.toast('Loading ' + self.filename + '...');

                    // Read local binary file for instant display
                    if (window.FileReader) {
                        var reader = new FileReader();
                        reader.onload = function (ev) {
                            try {
                                var typedarray = new Uint8Array(ev.target.result);
                                self.loadPdfData(typedarray, 1, true);
                            } catch (e) {
                                self.loadPdfFromUrl(self.downloadUrl, 1);
                            }
                        };
                        reader.onerror = function () {
                            self.loadPdfFromUrl(self.downloadUrl, 1);
                        };
                        reader.readAsArrayBuffer(file);
                    } else {
                        self.loadPdfFromUrl(self.downloadUrl, 1);
                    }
                })
                .catch(function (err) {
                    if (uploadBtn) uploadBtn.disabled = false;
                    self.toast('Upload error. Please check your connection.');
                });
        },

        loadPdfData: function (source, targetPage, broadcast) {
            var self = this;
            if (!global.pdfjsLib) {
                self.toast('PDF viewer library is loading. Please wait a moment.');
                return;
            }

            var docParam;
            if (typeof source === 'string') {
                docParam = { url: source, withCredentials: true };
            } else if (source instanceof Uint8Array || (typeof ArrayBuffer !== 'undefined' && source instanceof ArrayBuffer)) {
                docParam = { data: source };
            } else if (source && typeof source === 'object' && (source.data || source.url)) {
                docParam = source;
            } else {
                docParam = { data: source };
            }

            try {
                var loadingTask = global.pdfjsLib.getDocument(docParam);
                loadingTask.promise.then(function (pdf) {
                    self.pdfDoc = pdf;
                    self.totalPages = pdf.numPages;
                    self.active = true;
                    self.currentPage = targetPage || 1;

                    self.setupDom();
                    self.updateUi();
                    self.openDocument();
                    self.renderThumbnails();

                    var boardApi = self.getBoardApi();
                    if (boardApi) {
                        if (typeof boardApi.setPage === 'function') {
                            boardApi.setPage(self.currentPage - 1, { sync: false });
                        }
                        if (typeof boardApi.replay === 'function') {
                            boardApi.replay();
                        }
                    }

                    var cfg = (self.hooks.getCfg && self.hooks.getCfg()) || global.CK_CONFIG || {};
                    if (!cfg.isHost && self.hooks.onStudentShow) {
                        self.hooks.onStudentShow(true);
                    }

                    if (broadcast && self.hooks.publishData) {
                        self.hooks.publishData({
                            t: 'pdf_open',
                            doc_id: self.docId,
                            filename: self.filename,
                            total_pages: self.totalPages,
                            page: self.currentPage,
                            zoom: self.zoom,
                            rotation: self.rotation,
                            download_token: self.downloadToken
                        });

                        self.notifyServerPage(self.currentPage, self.totalPages);
                    }
                }).catch(function (err) {
                    console.error('PDF.js loadingTask error:', err);
                    self.toast('Could not open PDF: ' + (err.message || 'Error parsing document'));
                });
            } catch (e) {
                console.error('PDF.js getDocument exception:', e);
                self.toast('Error starting PDF viewer: ' + (e.message || 'Unknown error'));
            }
        },

        loadPdfFromUrl: function (url, targetPage) {
            var self = this;
            if (!global.pdfjsLib) return;

            self.loadPdfData({ url: url, withCredentials: true }, targetPage, false);
        },

        pdfBox: function () {
            var world = $('ckBoardWorld');
            var cssW = world ? world.clientWidth : 0;
            var cssH = world ? world.clientHeight : 0;
            if (cssW < 8 || cssH < 8) {
                var board = $('ckBoard');
                if (board) {
                    var rect = board.getBoundingClientRect();
                    cssW = rect.width;
                    cssH = rect.height;
                }
            }
            return {
                cssW: Math.max(0, Math.floor(cssW)),
                cssH: Math.max(0, Math.floor(cssH))
            };
        },

        renderMetrics: function (pageWidth, pageHeight) {
            var box = this.pdfBox();
            var cssW = box.cssW;
            var cssH = box.cssH;
            var dpr = Math.min(window.devicePixelRatio || 1, 2);
            if (!(dpr > 0)) dpr = 1;
            var fit = 1;
            if (pageWidth > 0 && pageHeight > 0 && cssW > 0 && cssH > 0) {
                fit = Math.min(cssW / pageWidth, cssH / pageHeight);
            }
            var cssScale = fit * (this.zoom || 1);
            var pixelScale = cssScale * dpr;
            var maxEdge = 3200;
            if (pageWidth * pixelScale > maxEdge || pageHeight * pixelScale > maxEdge) {
                var limit = Math.min(maxEdge / (pageWidth * cssScale), maxEdge / (pageHeight * cssScale));
                if (limit > 0 && limit < dpr) dpr = limit;
                pixelScale = cssScale * dpr;
            }
            var bw = Math.max(1, Math.ceil(cssW * dpr));
            var bh = Math.max(1, Math.ceil(cssH * dpr));
            if (bw > maxEdge || bh > maxEdge) {
                var edgeCap = Math.min(maxEdge / bw, maxEdge / bh);
                bw = Math.max(1, Math.ceil(bw * edgeCap));
                bh = Math.max(1, Math.ceil(bh * edgeCap));
                if (cssW > 0) pixelScale = cssScale * (bw / cssW);
            }
            return {
                cssW: cssW,
                cssH: cssH,
                dpr: dpr,
                bw: bw,
                bh: bh,
                cssScale: cssScale,
                pixelScale: pixelScale
            };
        },

        watchContainer: function () {
            var self = this;
            if (self._watchBound) return;
            self._watchBound = true;
            var timer = null;
            function schedule() {
                if (timer) clearTimeout(timer);
                timer = setTimeout(function () {
                    timer = null;
                    if (!self.active || !self.pdfDoc) return;
                    self.layoutSheets(false);
                }, 120);
            }
            window.addEventListener('resize', schedule);
            document.addEventListener('fullscreenchange', schedule);
            document.addEventListener('webkitfullscreenchange', schedule);
        },

        scrollerEl: function () {
            return $('ckPdfScroller');
        },

        ensureViewer: function () {
            var viewport = $('ckBoardViewport');
            if (!viewport) return null;
            var scroller = $('ckPdfScroller');
            if (!scroller) {
                scroller = document.createElement('div');
                scroller.id = 'ckPdfScroller';
                scroller.className = 'ck-pdf-scroller';
                scroller.innerHTML = '<div class="ck-pdf-stack" id="ckPdfStack"></div>';
                viewport.appendChild(scroller);
            }
            if (!scroller._ckScroll) {
                scroller._ckScroll = true;
                var self = this;
                scroller.addEventListener('scroll', function () {
                    self.onScrollerScroll();
                }, { passive: true });
                scroller.addEventListener('pointerdown', function (ev) {
                    self.onSheetPointerDown(ev);
                }, true);
            }
            return scroller;
        },

        fitWidth: function () {
            var scroller = this.scrollerEl();
            var w = scroller ? scroller.clientWidth : 0;
            if (w < 8) {
                var viewport = $('ckBoardViewport');
                w = viewport ? viewport.clientWidth : 800;
            }
            var gutter = w < 700 ? 16 : 36;
            return Math.max(220, Math.min(980, w - gutter));
        },

        openDocument: function () {
            var self = this;
            if (!self.pdfDoc) return;
            var scroller = self.ensureViewer();
            if (!scroller) return;
            var stack = $('ckPdfStack');
            if (!stack) return;
            stack.innerHTML = '';
            self._sheets = [];
            self._pageMeta = [];
            var pending = [];
            var total = self.pdfDoc.numPages;
            for (var i = 1; i <= total; i++) pending.push(self.pdfDoc.getPage(i));
            Promise.all(pending).then(function (pages) {
                if (!self.pdfDoc || self.pdfDoc.numPages !== total) return;
                pages.forEach(function (page, idx) {
                    var pageNo = idx + 1;
                    self._pageMeta[pageNo] = { page: page };
                    var sheet = document.createElement('article');
                    sheet.className = 'ck-pdf-sheet';
                    sheet.setAttribute('data-pdf-page', String(pageNo));
                    var canvas = document.createElement('canvas');
                    canvas.className = 'ck-pdf-sheet-canvas';
                    canvas.setAttribute('data-pdf-page', String(pageNo));
                    var ink = document.createElement('canvas');
                    ink.className = 'ck-pdf-sheet-ink';
                    ink.setAttribute('data-page', String(pageNo - 1));
                    sheet.appendChild(canvas);
                    sheet.appendChild(ink);
                    stack.appendChild(sheet);
                    self._sheets[pageNo] = sheet;
                    if (self._io) self._io.observe(sheet);
                });
                self.bindPageObserver();
                self.layoutSheets(true);
                self.scrollToPage(self.currentPage || 1);
                self.updateUi();
                var boardApi = self.getBoardApi();
                if (boardApi && typeof boardApi.replay === 'function') boardApi.replay();
                if (self._pendingRatio != null) {
                    self.applyRemoteScroll(self._pendingRatio, self.currentPage);
                    self._pendingRatio = null;
                }
            }).catch(function (err) {
                console.error('PDF document build error:', err);
            });
        },

        bindPageObserver: function () {
            var self = this;
            var scroller = self.scrollerEl();
            if (!scroller || typeof IntersectionObserver === 'undefined') {
                self.renderVisibleSheets();
                return;
            }
            if (self._io) {
                self._io.disconnect();
            }
            self._io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) self.renderSheet(entry.target);
                });
            }, { root: scroller, rootMargin: '800px 0px', threshold: 0.01 });
            (self._sheets || []).forEach(function (sheet) {
                if (sheet) self._io.observe(sheet);
            });
        },

        layoutSheets: function (force) {
            var self = this;
            var scroller = self.scrollerEl();
            if (!scroller || !self._sheets) return;
            var ratio = 0;
            if (!force && scroller.scrollHeight > 0) {
                ratio = scroller.scrollTop / scroller.scrollHeight;
            } else if (scroller.scrollHeight > 0) {
                ratio = scroller.scrollTop / scroller.scrollHeight;
            }
            var fit = self.fitWidth();
            var zoom = self.zoom || 1;
            var key = fit + '|' + zoom + '|' + (self.rotation || 0);
            var sizeChanged = key !== self._layoutKey;
            self._layoutKey = key;
            var cssW = Math.max(180, Math.round(fit * zoom));
            for (var n = 1; n < self._sheets.length; n++) {
                var sheet = self._sheets[n];
                var meta = self._pageMeta[n];
                if (!sheet || !meta || !meta.page) continue;
                var baseVp = meta.page.getViewport({ scale: 1, rotation: self.rotation || 0 });
                var cssH = Math.max(1, Math.round(cssW * (baseVp.height / baseVp.width)));
                var inkH = Math.max(1, Math.round(900 * (baseVp.height / baseVp.width)));
                sheet.style.width = cssW + 'px';
                sheet.style.height = cssH + 'px';
                sheet.setAttribute('data-ink-h', String(inkH));
                var ink = sheet.querySelector('.ck-pdf-sheet-ink');
                if (ink && (ink.width !== 900 || ink.height !== inkH)) {
                    ink.width = 900;
                    ink.height = inkH;
                }
                if (sizeChanged) {
                    var canvas = sheet.querySelector('.ck-pdf-sheet-canvas');
                    if (canvas) delete canvas.dataset.renderKey;
                }
            }
            if (scroller.scrollHeight > 0 && ratio > 0) {
                scroller.scrollTop = ratio * scroller.scrollHeight;
            }
            self.renderVisibleSheets();
            var live = document.querySelector('.ck-pdf-sheet.is-live');
            if (live) self.placeLiveBoard(live, (parseInt(live.getAttribute('data-pdf-page'), 10) || 1) - 1);
        },

        applyScale: function () {
            this.layoutSheets(false);
            this.updateUi();
            this.publishScroll();
        },

        renderPage: function (pageNumber) {
            if (!this.pdfDoc) return;
            if (pageNumber) {
                this.currentPage = Math.max(1, Math.min(this.totalPages || 1, pageNumber));
            }
            this.layoutSheets(false);
            this.updateUi();
            this.publishScroll();
        },

        renderVisibleSheets: function () {
            var self = this;
            var scroller = self.scrollerEl();
            if (!scroller || !self._sheets) return;
            var top = scroller.scrollTop - 900;
            var bot = scroller.scrollTop + scroller.clientHeight + 900;
            for (var n = 1; n < self._sheets.length; n++) {
                var sheet = self._sheets[n];
                if (!sheet) continue;
                var y = sheet.offsetTop;
                if (y + sheet.offsetHeight >= top && y <= bot) self.renderSheet(sheet);
            }
        },

        renderSheet: function (sheet) {
            var self = this;
            if (!sheet || !self.pdfDoc) return;
            var pageNo = parseInt(sheet.getAttribute('data-pdf-page'), 10) || 1;
            var meta = self._pageMeta && self._pageMeta[pageNo];
            if (!meta || !meta.page) return;
            var canvas = sheet.querySelector('.ck-pdf-sheet-canvas');
            if (!canvas) return;
            var cssW = sheet.clientWidth || parseInt(sheet.style.width, 10) || 0;
            var cssH = sheet.clientHeight || parseInt(sheet.style.height, 10) || 0;
            if (cssW < 8 || cssH < 8) return;
            var dpr = Math.min(window.devicePixelRatio || 1, 2);
            if (!(dpr > 0)) dpr = 1;
            var bw = Math.max(1, Math.round(cssW * dpr));
            var bh = Math.max(1, Math.round(cssH * dpr));
            var maxEdge = 2800;
            if (bw > maxEdge || bh > maxEdge) {
                var cap = Math.min(maxEdge / bw, maxEdge / bh);
                bw = Math.max(1, Math.round(bw * cap));
                bh = Math.max(1, Math.round(bh * cap));
            }
            var renderKey = bw + 'x' + bh + '@' + (self.rotation || 0);
            if (canvas.dataset.renderKey === renderKey) return;
            if (sheet._renderTask && typeof sheet._renderTask.cancel === 'function') {
                try { sheet._renderTask.cancel(); } catch (e) {}
                sheet._renderTask = null;
            }
            var gen = (sheet._renderGen || 0) + 1;
            sheet._renderGen = gen;
            var baseVp = meta.page.getViewport({ scale: 1, rotation: self.rotation || 0 });
            var scale = bw / baseVp.width;
            var viewport = meta.page.getViewport({ scale: scale, rotation: self.rotation || 0 });
            var off = document.createElement('canvas');
            off.width = bw;
            off.height = bh;
            var task = meta.page.render({ canvasContext: off.getContext('2d'), viewport: viewport });
            sheet._renderTask = task;
            task.promise.then(function () {
                if (sheet._renderGen !== gen || !canvas.isConnected) return;
                canvas.width = bw;
                canvas.height = bh;
                canvas.getContext('2d').drawImage(off, 0, 0);
                canvas.dataset.renderKey = renderKey;
                sheet._renderTask = null;
            }).catch(function (err) {
                sheet._renderTask = null;
                if (err && err.name === 'RenderingCancelledException') return;
            });
        },

        scrollToPage: function (pageNumber) {
            var sheet = this._sheets && this._sheets[pageNumber];
            var scroller = this.scrollerEl();
            if (!sheet || !scroller) return;
            var top = sheet.offsetTop - 12;
            if (top < 0) top = 0;
            this._applyingScroll = true;
            scroller.scrollTop = top;
            var self = this;
            setTimeout(function () { self._applyingScroll = false; }, 60);
        },

        syncBoardPage: function (pageIndex) {
            var boardApi = this.getBoardApi();
            if (boardApi && typeof boardApi.setPage === 'function') {
                boardApi.setPage(pageIndex, { sync: false });
            }
        },

        placeLiveBoard: function (sheet, pageIndex) {
            if (!sheet) return;
            var board = $('ckBoard');
            if (!board) return;
            var overlay = $('ckBoardOverlay');
            var lasers = $('ckBoardLasers');
            var inkH = parseInt(sheet.getAttribute('data-ink-h'), 10) || 560;
            document.querySelectorAll('.ck-pdf-sheet.is-live').forEach(function (el) {
                if (el !== sheet) el.classList.remove('is-live');
            });
            sheet.classList.add('is-live');
            if (board.parentNode !== sheet) sheet.appendChild(board);
            if (overlay && overlay.parentNode !== sheet) sheet.appendChild(overlay);
            if (lasers && lasers.parentNode !== sheet) sheet.appendChild(lasers);
            if (board.width !== 900 || board.height !== inkH) {
                board.width = 900;
                board.height = inkH;
            }
            this.syncBoardPage(pageIndex);
        },

        canDrawOnPdf: function () {
            if (document.body.classList.contains('ck-is-host')) return true;
            return document.body.classList.contains('ck-students-can-draw');
        },

        activeToolName: function () {
            var btn = document.querySelector('.ck-board-rail [data-tool].is-on, #ckBoardTools [data-tool].is-on');
            return btn ? (btn.getAttribute('data-tool') || 'pen') : 'pen';
        },

        onSheetPointerDown: function (ev) {
            if (!this.active) return;
            if (ev.target && (ev.target.id === 'ckBoard' || (ev.target.closest && ev.target.closest('#ckBoard')))) return;
            var sheet = ev.target && ev.target.closest ? ev.target.closest('.ck-pdf-sheet') : null;
            if (!sheet) return;
            if (!this.canDrawOnPdf()) return;
            if (this.activeToolName() === 'pan') return;
            var pageNo = parseInt(sheet.getAttribute('data-pdf-page'), 10) || 1;
            this.currentPage = pageNo;
            this.placeLiveBoard(sheet, pageNo - 1);
            this.updateUi();
            var board = $('ckBoard');
            if (!board) return;
            board.dispatchEvent(new PointerEvent('pointerdown', {
                bubbles: true,
                cancelable: true,
                clientX: ev.clientX,
                clientY: ev.clientY,
                pointerId: ev.pointerId || 1,
                pointerType: ev.pointerType || 'mouse',
                button: ev.button || 0,
                buttons: ev.buttons || 1
            }));
        },

        onScrollerScroll: function () {
            var self = this;
            if (!self.active) return;
            if (!self._scrollRaf) {
                self._scrollRaf = requestAnimationFrame(function () {
                    self._scrollRaf = 0;
                    self.noteVisiblePage();
                    if (!self._io) self.renderVisibleSheets();
                });
            }
            if (self._inkScrollTimer) clearTimeout(self._inkScrollTimer);
            self._inkScrollTimer = setTimeout(function () {
                self._inkScrollTimer = null;
                var boardApi = self.getBoardApi();
                if (boardApi && typeof boardApi.replay === 'function') boardApi.replay();
            }, 80);
            if (self._applyingScroll) return;
            var cfg = (self.hooks.getCfg && self.hooks.getCfg()) || global.CK_CONFIG || {};
            if (!cfg.isHost) return;
            if (self._scrollTimer) return;
            self._scrollTimer = setTimeout(function () {
                self._scrollTimer = null;
                self.publishScroll();
            }, 280);
        },

        noteVisiblePage: function () {
            var scroller = this.scrollerEl();
            if (!scroller || !this._sheets) return;
            var mark = scroller.scrollTop + Math.min(120, scroller.clientHeight * 0.25);
            var best = this.currentPage || 1;
            var bestDist = Infinity;
            for (var n = 1; n < this._sheets.length; n++) {
                var sheet = this._sheets[n];
                if (!sheet) continue;
                var dist = Math.abs(sheet.offsetTop - mark);
                if (dist < bestDist) {
                    bestDist = dist;
                    best = n;
                }
            }
            if (best !== this.currentPage) {
                this.currentPage = best;
                this.updateUi();
            }
        },

        publishScroll: function () {
            var self = this;
            var cfg = (self.hooks.getCfg && self.hooks.getCfg()) || global.CK_CONFIG || {};
            if (!cfg.isHost || !self.hooks.publishData || !self.active) return;
            var scroller = self.scrollerEl();
            if (!scroller || !scroller.scrollHeight) return;
            var ratio = scroller.scrollTop / scroller.scrollHeight;
            if (self._lastSentRatio != null && Math.abs(ratio - self._lastSentRatio) < 0.003 && self._lastSentPage === self.currentPage && self._lastSentZoom === self.zoom) return;
            self._lastSentRatio = ratio;
            self._lastSentPage = self.currentPage;
            self._lastSentZoom = self.zoom;
            self.hooks.publishData({
                t: 'pdf_scroll',
                doc_id: self.docId,
                ratio: Math.round(ratio * 1000) / 1000,
                page: self.currentPage,
                zoom: self.zoom
            });
            if (self._lastServerPage !== self.currentPage) {
                self._lastServerPage = self.currentPage;
                self.notifyServerPage(self.currentPage, self.totalPages);
            }
        },

        applyRemoteScroll: function (ratio, page) {
            var scroller = this.scrollerEl();
            if (!scroller) {
                this._pendingRatio = ratio;
                return;
            }
            this._applyingScroll = true;
            if (page) this.currentPage = page;
            var top = (parseFloat(ratio) || 0) * scroller.scrollHeight;
            scroller.scrollTop = top;
            this.renderVisibleSheets();
            this.updateUi();
            var self = this;
            setTimeout(function () { self._applyingScroll = false; }, 80);
        },

        goToPage: function (pageNumber, broadcast) {
            var self = this;
            pageNumber = Math.max(1, Math.min(self.totalPages || 1, pageNumber));
            self.currentPage = pageNumber;
            self.scrollToPage(pageNumber);
            self.renderVisibleSheets();
            self.updateUi();
            self.syncBoardPage(pageNumber - 1);

            var cfg = (self.hooks.getCfg && self.hooks.getCfg()) || global.CK_CONFIG || {};
            if (broadcast && cfg.isHost) {
                if (self.hooks.publishData) {
                    self.hooks.publishData({
                        t: 'pdf_page',
                        doc_id: self.docId,
                        page: pageNumber,
                        zoom: self.zoom
                    });
                }
                self.publishScroll();
                self.notifyServerPage(pageNumber, self.totalPages);
            }
        },

        notifyServerPage: function (page, totalPages) {
            var self = this;
            var cfg = (self.hooks.getCfg && self.hooks.getCfg()) || global.CK_CONFIG || {};
            if (!cfg.isHost || !self.docId) return;

            var apiBase = cfg.apiBase || '/api/classroom/';
            var pageUrl = apiBase.replace(/\/?$/, '/') + 'pdf.php?action=page';

            fetch(pageUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': cfg.csrf || ''
                },
                body: JSON.stringify({
                    lesson: cfg.lessonId,
                    csrf_token: cfg.csrf || '',
                    doc_id: self.docId,
                    page: page,
                    total_pages: totalPages || self.totalPages || 0,
                    zoom: self.zoom
                })
            }).catch(function () {});
        },

        closePdf: function (broadcast) {
            var self = this;
            self.active = false;
            self.pdfDoc = null;
            self.docId = null;
            self.filename = '';
            self.totalPages = 0;
            self.currentPage = 1;
            self._layoutKey = '';
            self._pageMeta = [];
            self._sheets = [];
            self.restoreBoardHome();
            var stack = $('ckPdfStack');
            if (stack) stack.innerHTML = '';

            var canvas = $('ckPdfCanvas');
            if (canvas) {
                var ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }

            self.updateUi();

            var container = $('ckPageThumbnails');
            if (container) {
                container.innerHTML = '<div class="ck-pns-item is-active" data-page="1">' +
                    '<div class="ck-pns-thumb"><canvas class="ck-thumb-canvas" width="60" height="42"></canvas></div>' +
                    '<span class="ck-pns-num">1</span>' +
                    '</div>';
            }
            var bPill = $('ckBoardPageIndicator');
            if (bPill) bPill.textContent = '1 / 1';

            var boardApi = self.getBoardApi();
            if (boardApi && typeof boardApi.replay === 'function') {
                boardApi.replay();
            }

            var cfg = (self.hooks.getCfg && self.hooks.getCfg()) || global.CK_CONFIG || {};
            if (broadcast && cfg.isHost) {
                if (self.hooks.publishData) {
                    self.hooks.publishData({ t: 'pdf_close' });
                }

                var apiBase = cfg.apiBase || '/api/classroom/';
                fetch(apiBase.replace(/\/?$/, '/') + 'pdf.php?action=close', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': cfg.csrf || ''
                    },
                    body: JSON.stringify({
                        lesson: cfg.lessonId,
                        csrf_token: cfg.csrf || ''
                    })
                }).catch(function () {});
            }
        },

        restoreBoardHome: function () {
            var world = $('ckBoardWorld');
            var board = $('ckBoard');
            var overlay = $('ckBoardOverlay');
            var lasers = $('ckBoardLasers');
            if (!world || !board) return;
            document.querySelectorAll('.ck-pdf-sheet.is-live').forEach(function (el) {
                el.classList.remove('is-live');
            });
            if (board.width !== 900 || board.height !== 560) {
                board.width = 900;
                board.height = 560;
            }
            var pdfCanvas = $('ckPdfCanvas');
            if (pdfCanvas && pdfCanvas.parentNode === world) {
                world.insertBefore(board, pdfCanvas.nextSibling);
            } else if (board.parentNode !== world) {
                world.appendChild(board);
            }
            if (overlay && overlay.parentNode !== world) world.appendChild(overlay);
            if (lasers && lasers.parentNode !== world) world.appendChild(lasers);
        },

        triggerDownload: function () {
            var self = this;
            var cfg = (self.hooks.getCfg && self.hooks.getCfg()) || global.CK_CONFIG || {};

            if (!self.docId && !self.active) {
                self.toast('No PDF is currently available to download.');
                return;
            }

            var apiBase = cfg.apiBase || '/api/classroom/';
            var signUrl = apiBase.replace(/\/?$/, '/') + 'pdf.php?action=sign&lesson=' + cfg.lessonId + '&doc_id=' + (self.docId || '');

            fetch(signUrl, {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.ok && data.download_url) {
                        var a = document.createElement('a');
                        a.href = data.download_url + '&download=1';
                        a.download = data.filename || self.filename || 'lesson.pdf';
                        a.target = '_blank';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        self.toast('Downloading PDF...');
                    } else {
                        self.toast(data.error || 'Could not download PDF document.');
                    }
                })
                .catch(function () {
                    self.toast('Download request failed. Please try again.');
                });
        },

        renderThumbnails: function () {
            var self = this;
            var container = $('ckPageThumbnails');
            if (!container || !self.pdfDoc || !self.totalPages) return;

            var html = '';
            for (var i = 1; i <= self.totalPages; i++) {
                var active = (i === self.currentPage) ? ' is-active' : '';
                html += '<div class="ck-pns-item' + active + '" data-page="' + i + '">' +
                    '<div class="ck-pns-thumb">' +
                    '<canvas class="ck-thumb-canvas" id="ckPdfThumb_' + i + '" width="72" height="45"></canvas>' +
                    '</div>' +
                    '<span class="ck-pns-num">' + i + '</span>' +
                    '</div>';
            }
            container.innerHTML = html;

            var badge = $('ckPnsCurBadge');
            if (badge) badge.textContent = self.currentPage + '/' + self.totalPages;
            var pBtn = $('ckPnsPrevBtn');
            if (pBtn) pBtn.disabled = self.currentPage <= 1;
            var nBtn = $('ckPnsNextBtn');
            if (nBtn) nBtn.disabled = self.currentPage >= self.totalPages;

            container.querySelectorAll('.ck-pns-item').forEach(function (item) {
                item.addEventListener('click', function () {
                    var pg = parseInt(item.getAttribute('data-page'), 10) || 1;
                    self.goToPage(pg, true);
                });
            });

            for (var p = 1; p <= self.totalPages; p++) {
                (function (pageNum) {
                    self.pdfDoc.getPage(pageNum).then(function (page) {
                        var c = $('ckPdfThumb_' + pageNum);
                        if (!c) return;
                        var ctx = c.getContext('2d');
                        var vp = page.getViewport({ scale: 1 });
                        var scale = Math.min(c.width / vp.width, c.height / vp.height);
                        var scaledVp = page.getViewport({ scale: scale });
                        page.render({
                            canvasContext: ctx,
                            viewport: scaledVp
                        });
                    }).catch(function () {});
                })(p);
            }
        },

        updateUi: function () {
            var self = this;
            document.body.classList.toggle('ck-pdf-open', !!self.active);
            var nav = $('ckPdfNav');
            var dlBtn = $('ckPdfDownloadBtn');
            var prevBtn = $('ckPdfPrev');
            var nextBtn = $('ckPdfNext');
            var indicator = $('ckPdfPageIndicator');
            var cfg = (self.hooks.getCfg && self.hooks.getCfg()) || global.CK_CONFIG || {};

            if (nav) {
                nav.style.display = self.active ? 'flex' : 'none';
            }

            var pageStr = self.active ? (self.currentPage + ' / ' + (self.totalPages || '?')) : '0 / 0';
            if (indicator) {
                indicator.textContent = pageStr;
            }

            // Sync with bottom floating pill controls
            var bPill = $('ckBoardPageIndicator');
            if (bPill && self.active) {
                bPill.textContent = self.currentPage + ' / ' + (self.totalPages || '?');
            }
            var bPrev = $('ckBoardPrevPage');
            if (bPrev && self.active) {
                bPrev.disabled = self.currentPage <= 1;
            }
            var bNext = $('ckBoardNextPage');
            if (bNext && self.active) {
                bNext.disabled = (self.totalPages > 0 && self.currentPage >= self.totalPages);
            }

            // Update active state in thumbnail sidebar
            var container = $('ckPageThumbnails');
            if (container && self.active) {
                container.querySelectorAll('.ck-pns-item').forEach(function (item) {
                    var pg = parseInt(item.getAttribute('data-page'), 10) || 1;
                    item.classList.toggle('is-active', pg === self.currentPage);
                });
            }

            if (prevBtn) {
                prevBtn.disabled = !self.active || self.currentPage <= 1;
            }

            if (nextBtn) {
                nextBtn.disabled = !self.active || (self.totalPages > 0 && self.currentPage >= self.totalPages);
            }

            // Student download button
            if (dlBtn) {
                var canDl = self.active && (cfg.isHost || cfg.studentPdfDownload !== false);
                dlBtn.hidden = !canDl;
            }
        },

        syncState: function (state) {
            var self = this;
            if (!state || !state.doc_id) {
                if (self.active) {
                    self.closePdf(false);
                }
                return;
            }

            var cfg = (self.hooks.getCfg && self.hooks.getCfg()) || global.CK_CONFIG || {};
            var docId = parseInt(state.doc_id, 10);
            var targetPage = parseInt(state.page, 10) || 1;

            if (self.docId === docId && self.active && self.pdfDoc && cfg.isHost) return;

            var prevZoom = self.zoom || 1;
            var prevRot = self.rotation || 0;
            if (state.zoom !== undefined) {
                self.zoom = parseFloat(state.zoom) || 1.0;
            }
            if (state.rotation !== undefined) {
                self.rotation = parseInt(state.rotation, 10) || 0;
            }

            if (self.docId === docId && self.active && self.pdfDoc) {
                if (Math.abs((self.zoom || 1) - prevZoom) > 0.01 || (self.rotation || 0) !== prevRot) self.applyScale();
                return;
            }

            self.docId = docId;
            self.filename = state.filename || 'lesson.pdf';
            self.downloadToken = state.download_token || '';
            self.totalPages = parseInt(state.total_pages, 10) || 0;

            var apiBase = cfg.apiBase || '/api/classroom/';
            var fetchUrl = apiBase.replace(/\/?$/, '/') + 'pdf.php?action=download&lesson=' + cfg.lessonId + '&doc_id=' + docId + '&token=' + encodeURIComponent(self.downloadToken);

            self.loadPdfFromUrl(fetchUrl, targetPage);
        },

        handleDataMessage: function (msg) {
            var self = this;
            if (!msg || !msg.t) return;

            if (msg.t === 'pdf_open') {
                var incomingDoc = parseInt(msg.doc_id, 10);
                if (self.active && self.pdfDoc && self.docId === incomingDoc) return;
                self.docId = incomingDoc;
                self.filename = msg.filename || 'lesson.pdf';
                self.downloadToken = msg.download_token || '';
                self.totalPages = parseInt(msg.total_pages, 10) || 0;
                if (msg.zoom !== undefined) self.zoom = parseFloat(msg.zoom) || 1.0;
                if (msg.rotation !== undefined) self.rotation = parseInt(msg.rotation, 10) || 0;

                var cfg = (self.hooks.getCfg && self.hooks.getCfg()) || global.CK_CONFIG || {};
                var apiBase = cfg.apiBase || '/api/classroom/';
                var fetchUrl = apiBase.replace(/\/?$/, '/') + 'pdf.php?action=download&lesson=' + cfg.lessonId + '&doc_id=' + self.docId + '&token=' + encodeURIComponent(self.downloadToken);

                self.loadPdfFromUrl(fetchUrl, parseInt(msg.page, 10) || 1);
                self.toast('Teacher opened PDF: ' + self.filename);
            }

            if (msg.t === 'pdf_page') {
                if (msg.zoom !== undefined) {
                    self.zoom = parseFloat(msg.zoom) || 1.0;
                }
                if (msg.rotation !== undefined) {
                    self.rotation = parseInt(msg.rotation, 10) || 0;
                }
                if (msg.doc_id && self.docId !== parseInt(msg.doc_id, 10)) {
                    self.syncState({
                        doc_id: msg.doc_id,
                        page: msg.page,
                        zoom: msg.zoom,
                        rotation: msg.rotation,
                        download_token: msg.download_token
                    });
                } else {
                    self.goToPage(parseInt(msg.page, 10) || 1, false);
                }
            }

            if (msg.t === 'pdf_scroll') {
                var cfgScroll = (self.hooks.getCfg && self.hooks.getCfg()) || global.CK_CONFIG || {};
                if (cfgScroll.isHost) return;
                if (msg.doc_id && self.docId && parseInt(msg.doc_id, 10) !== self.docId) return;
                if (msg.zoom !== undefined) {
                    var z = parseFloat(msg.zoom) || 1;
                    if (Math.abs(z - (self.zoom || 1)) > 0.01) {
                        self.zoom = z;
                        self.applyScale();
                    }
                }
                self.applyRemoteScroll(msg.ratio, parseInt(msg.page, 10) || self.currentPage);
            }

            if (msg.t === 'pdf_close') {
                self.closePdf(false);
                self.toast('Teacher closed the PDF.');
            }
        },

        republishState: function (destinationIdentities) {
            var self = this;
            if (!self.active || !self.docId) return;
            var cfg = (self.hooks.getCfg && self.hooks.getCfg()) || global.CK_CONFIG || {};
            if (!cfg.isHost) return;

            var packet = {
                t: 'pdf_open',
                doc_id: self.docId,
                filename: self.filename,
                total_pages: self.totalPages,
                page: self.currentPage,
                zoom: self.zoom,
                rotation: self.rotation,
                download_token: self.downloadToken
            };

            if (self.hooks.publishData) {
                self.hooks.publishData(packet);
            }
        },

        getBoardApi: function () {
            if (this.hooks.getBoardApi) return this.hooks.getBoardApi();
            return global.boardApi || null;
        },

        toast: function (msg) {
            if (this.hooks.toast) {
                this.hooks.toast(msg);
            } else if (global.toast) {
                global.toast(msg);
            }
        }
    };

    global.CKPdf = CKPdf;
})(window);
