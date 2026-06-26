/**
 * Shared APM rich-text editor (Quill) for memos, activities, and other memo types.
 * Replaces Summernote on memo forms while keeping the same image upload endpoint.
 */
(function (global) {
    'use strict';

    /** @type {WeakMap<HTMLElement, { quill: any, hidden: HTMLTextAreaElement }>} */
    var registry = new WeakMap();
    var fontsRegistered = false;

    function uploadUrl() {
        var meta = document.querySelector('meta[name="apm-image-upload-url"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content');
        }
        return '/image/upload';
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    var DATA_URI_IMAGE_RE = /^data:image\/(png|jpe?g|gif|webp);base64,/i;
    var pendingImageUploads = 0;
    var dataUriReplaceTimers = new WeakMap();

    function htmlContainsDataUriImages(html) {
        if (!html) {
            return false;
        }
        if (DATA_URI_IMAGE_RE.test(html)) {
            return true;
        }
        return collectDataUriImagesFromHtml(html).length > 0;
    }

    function collectDataUriImagesFromHtml(html) {
        if (!html || html.indexOf('<') === -1) {
            return [];
        }
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var out = [];
        doc.querySelectorAll('img[src]').forEach(function (img) {
            var src = img.getAttribute('src') || '';
            if (DATA_URI_IMAGE_RE.test(src)) {
                out.push(src);
            }
        });
        return out;
    }

    function dataUriToFile(dataUri, index) {
        var match = /^data:(image\/[a-z0-9.+-]+);base64,(.+)$/i.exec(dataUri || '');
        if (!match) {
            return null;
        }
        try {
            var binary = atob(match[2]);
            var bytes = new Uint8Array(binary.length);
            for (var i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            var mime = match[1].toLowerCase();
            var ext = mime.indexOf('jpeg') !== -1 || mime.indexOf('jpg') !== -1
                ? 'jpg'
                : mime.indexOf('gif') !== -1
                    ? 'gif'
                    : mime.indexOf('webp') !== -1
                        ? 'webp'
                        : 'png';
            return new File([bytes], 'pasted-image-' + (index || 0) + '.' + ext, { type: mime });
        } catch (e) {
            return null;
        }
    }

    function syncEntryHtml(entry) {
        if (!entry || !entry.quill || !entry.hidden) {
            return;
        }
        normalizeArialContent(entry.quill.root);
        entry.hidden.value = entry.quill.root.innerHTML;
    }

    function replaceDataUriImagesInEditor(quill, entry) {
        if (!quill || !quill.root) {
            return Promise.resolve();
        }
        var imgs = Array.prototype.slice.call(
            quill.root.querySelectorAll('img[src^="data:image"]')
        );
        if (!imgs.length) {
            return Promise.resolve();
        }
        var chain = Promise.resolve();
        imgs.forEach(function (img, index) {
            chain = chain.then(function () {
                if (!img.isConnected) {
                    return;
                }
                var dataUri = img.getAttribute('src') || '';
                var file = dataUriToFile(dataUri, index);
                if (!file) {
                    img.remove();
                    return;
                }
                return uploadImageFile(file, quill, {
                    trackPending: false,
                    replaceImg: img,
                    entry: entry,
                }).catch(function () {
                    if (img.isConnected) {
                        img.remove();
                    }
                });
            });
        });
        return chain.then(function () {
            syncEntryHtml(entry);
        });
    }

    function scheduleDataUriReplacement(quill, entry) {
        if (!quill || !entry) {
            return;
        }
        var existing = dataUriReplaceTimers.get(quill);
        if (existing) {
            clearTimeout(existing);
        }
        dataUriReplaceTimers.set(
            quill,
            window.setTimeout(function () {
                dataUriReplaceTimers.delete(quill);
                replaceDataUriImagesInEditor(quill, entry).catch(function (err) {
                    console.error('APM Quill data URI replacement failed:', err);
                });
            }, 200)
        );
    }

    function ensureAllImagesUploaded(root) {
        var scope = root || document;
        var promises = [];
        scope.querySelectorAll('.apm-quill-wrap[data-apm-quill-bound="1"]').forEach(function (wrap) {
            var entry = registry.get(wrap);
            if (entry && entry.quill) {
                promises.push(replaceDataUriImagesInEditor(entry.quill, entry));
            }
        });
        return Promise.all(promises);
    }

    function formHasPendingImages(form) {
        if (pendingImageUploads > 0) {
            return true;
        }
        var pending = false;
        form.querySelectorAll('textarea.apm-quill-source').forEach(function (ta) {
            if (htmlContainsDataUriImages(ta.value)) {
                pending = true;
            }
        });
        return pending;
    }

    function registerFormats() {
        if (fontsRegistered || typeof Quill === 'undefined') {
            return;
        }
        try {
            var Font = Quill.import('formats/font');
            Font.whitelist = ['arial'];
            Quill.register(Font, true);

            var icons = Quill.import('ui/icons');
            icons.table =
                '<svg viewbox="0 0 18 18"><rect class="ql-stroke" height="12" width="12" x="3" y="3"></rect>'
                + '<line class="ql-stroke" x1="3" x2="15" y1="9" y2="9"></line>'
                + '<line class="ql-stroke" x1="9" x2="9" y1="3" y2="15"></line></svg>';
            icons['apm-table-insert'] = icons.table;
            icons['apm-table-row-below'] =
                '<svg viewbox="0 0 18 18"><rect class="ql-stroke" height="10" width="12" x="3" y="3"></rect>'
                + '<line class="ql-stroke" x1="8" x2="10" y1="15" y2="15"></line>'
                + '<line class="ql-stroke" x1="9" x2="9" y1="13" y2="15"></line></svg>';
            icons['apm-table-row-above'] =
                '<svg viewbox="0 0 18 18"><line class="ql-stroke" x1="8" x2="10" y1="3" y2="3"></line>'
                + '<line class="ql-stroke" x1="9" x2="9" y1="3" y2="5"></line>'
                + '<rect class="ql-stroke" height="10" width="12" x="3" y="5"></rect></svg>';
            icons['apm-table-col-after'] =
                '<svg viewbox="0 0 18 18"><rect class="ql-stroke" height="12" width="10" x="3" y="3"></rect>'
                + '<line class="ql-stroke" x1="15" x2="15" y1="8" y2="10"></line>'
                + '<line class="ql-stroke" x1="13" x2="15" y1="9" y2="9"></line></svg>';
            icons['apm-table-col-before'] =
                '<svg viewbox="0 0 18 18"><rect class="ql-stroke" height="12" width="10" x="5" y="3"></rect>'
                + '<line class="ql-stroke" x1="3" x2="3" y1="8" y2="10"></line>'
                + '<line class="ql-stroke" x1="3" x2="5" y1="9" y2="9"></line></svg>';
            icons['apm-table-del-row'] =
                '<svg viewbox="0 0 18 18"><rect class="ql-stroke" height="12" width="12" x="3" y="3"></rect>'
                + '<line class="ql-stroke" x1="5" x2="13" y1="9" y2="9"></line></svg>';
            icons['apm-table-del-table'] =
                '<svg viewbox="0 0 18 18"><rect class="ql-stroke" height="12" width="12" x="3" y="3"></rect>'
                + '<line class="ql-stroke" x1="5" x2="13" y1="5" y2="13"></line>'
                + '<line class="ql-stroke" x1="13" x2="5" y1="5" y2="13"></line></svg>';
        } catch (e) {
            /* optional formats */
        }
        fontsRegistered = true;
    }

    function fullToolbar() {
        return [
            [{ font: ['arial'] }],
            [{ size: ['small', false, 'large', 'huge'] }],
            [{ header: [1, 2, 3, 4, 5, 6, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ script: 'sub' }, { script: 'super' }],
            [{ color: [] }, { background: [] }],
            [{ list: 'ordered' }, { list: 'bullet' }],
            [{ indent: '-1' }, { indent: '+1' }],
            [{ align: [] }],
            ['blockquote', 'code-block'],
            ['link', 'image', 'video'],
            [
                'apm-table-insert',
                'apm-table-row-below',
                'apm-table-row-above',
                'apm-table-col-before',
                'apm-table-col-after',
                'apm-table-del-row',
                'apm-table-del-table',
            ],
            ['clean'],
        ];
    }

    function simpleToolbar() {
        return [
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link', 'image'],
            ['clean'],
        ];
    }

    function toolbarItems(mode) {
        return mode === 'simple' ? simpleToolbar() : fullToolbar();
    }

    function uploadImageFile(file, quill, options) {
        options = options || {};
        var trackPending = options.trackPending !== false;
        if (trackPending) {
            pendingImageUploads += 1;
        }
        var fd = new FormData();
        fd.append('file', file);
        var token = csrfToken();
        if (token) {
            fd.append('_token', token);
        }

        return fetch(uploadUrl(), {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: token ? { 'X-CSRF-TOKEN': token, Accept: 'application/json' } : { Accept: 'application/json' },
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error((data && data.error) || 'Upload failed');
                    }
                    return data;
                });
            })
            .then(function (data) {
                var url = data.url || data;
                if (!url) {
                    throw new Error('Upload response missing URL');
                }
                if (options.replaceImg && options.replaceImg.isConnected) {
                    options.replaceImg.setAttribute('src', url);
                    prepareInsertedImage(quill);
                    syncEntryHtml(options.entry);
                    return;
                }
                var range = quill.getSelection(true);
                var index = range ? range.index : quill.getLength();
                quill.insertEmbed(index, 'image', url, 'user');
                quill.setSelection(index + 1);
                window.setTimeout(function () {
                    prepareInsertedImage(quill, index);
                }, 0);
            })
            .finally(function () {
                if (trackPending) {
                    pendingImageUploads = Math.max(0, pendingImageUploads - 1);
                }
            });
    }

    function prepareInsertedImage(quill, index) {
        var img = findImageAtIndex(quill, index);
        if (!img) {
            return;
        }
        img.style.maxWidth = '100%';
        img.style.height = 'auto';
        if (!img.style.width) {
            img.style.width = '50%';
        }
        img.classList.add('apm-quill-image');
    }

    function findImageAtIndex(quill, index) {
        var node = quill.getLeaf(index)[0];
        if (node && node.domNode && node.domNode.tagName === 'IMG') {
            return node.domNode;
        }
        var imgs = quill.root.querySelectorAll('img');
        return imgs.length ? imgs[imgs.length - 1] : null;
    }

    function normalizeArialContent(root) {
        if (!root) {
            return;
        }
        root.style.fontFamily = 'Arial, Helvetica, sans-serif';
        root.querySelectorAll('*').forEach(function (el) {
            if (el.tagName === 'IMG') {
                return;
            }
            if (el.classList) {
                el.classList.forEach(function (cls) {
                    if (cls.indexOf('ql-font-') === 0) {
                        el.classList.remove(cls);
                    }
                });
            }
            if (el.style && el.style.fontFamily) {
                el.style.fontFamily = 'Arial, Helvetica, sans-serif';
            }
        });
        root.querySelectorAll('img').forEach(function (img) {
            img.classList.add('apm-quill-image');
            if (!img.style.maxWidth) {
                img.style.maxWidth = '100%';
            }
            if (!img.style.height) {
                img.style.height = 'auto';
            }
        });
        styleAllTables(root);
    }

    function bindImageResize(quill, wrap) {
        if (!quill || !wrap || wrap.dataset.apmImageResizeBound === '1') {
            return;
        }
        wrap.dataset.apmImageResizeBound = '1';

        var root = quill.root;
        var container = wrap.querySelector('.apm-quill-editor');
        if (!container) {
            return;
        }

        var overlay = document.createElement('div');
        overlay.className = 'apm-quill-image-overlay d-none';
        overlay.innerHTML =
            '<div class="apm-quill-image-toolbar" role="toolbar" aria-label="Image size">'
            + '<button type="button" class="btn btn-sm btn-light" data-apm-img-size="25">25%</button>'
            + '<button type="button" class="btn btn-sm btn-light" data-apm-img-size="50">50%</button>'
            + '<button type="button" class="btn btn-sm btn-light" data-apm-img-size="75">75%</button>'
            + '<button type="button" class="btn btn-sm btn-light" data-apm-img-size="100">100%</button>'
            + '</div>'
            + '<div class="apm-quill-image-frame"></div>'
            + '<span class="apm-quill-image-handle" title="Drag to resize"></span>';
        container.style.position = 'relative';
        container.appendChild(overlay);

        var frame = overlay.querySelector('.apm-quill-image-frame');
        var handle = overlay.querySelector('.apm-quill-image-handle');
        var toolbar = overlay.querySelector('.apm-quill-image-toolbar');
        var activeImg = null;
        var drag = null;

        function clearSelection() {
            activeImg = null;
            overlay.classList.add('d-none');
            drag = null;
        }

        function editorWidth() {
            return root.clientWidth || container.clientWidth || 1;
        }

        function setImageWidthPercent(img, percent) {
            var pct = Math.max(10, Math.min(100, percent));
            img.style.width = pct + '%';
            img.style.maxWidth = '100%';
            img.style.height = 'auto';
            positionOverlay(img);
        }

        function positionOverlay(img) {
            if (!img || !container || !frame) {
                return;
            }
            var imgRect = img.getBoundingClientRect();
            var boxRect = container.getBoundingClientRect();
            var top = imgRect.top - boxRect.top + container.scrollTop;
            var left = imgRect.left - boxRect.left + container.scrollLeft;
            overlay.classList.remove('d-none');
            overlay.style.top = top + 'px';
            overlay.style.left = left + 'px';
            overlay.style.width = imgRect.width + 'px';
            overlay.style.height = imgRect.height + 'px';
            frame.style.width = '100%';
            frame.style.height = '100%';
        }

        function selectImage(img) {
            activeImg = img;
            img.classList.add('apm-quill-image');
            positionOverlay(img);
        }

        toolbar.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
        });

        toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-apm-img-size]');
            if (!btn || !activeImg) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            setImageWidthPercent(activeImg, parseInt(btn.getAttribute('data-apm-img-size'), 10));
        });

        handle.addEventListener('mousedown', function (e) {
            if (!activeImg) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            drag = {
                startX: e.clientX,
                startWidth: activeImg.getBoundingClientRect().width,
            };
        });

        document.addEventListener('mousemove', function (e) {
            if (!drag || !activeImg) {
                return;
            }
            var delta = e.clientX - drag.startX;
            var nextWidth = Math.max(40, drag.startWidth + delta);
            var percent = Math.round((nextWidth / editorWidth()) * 100);
            setImageWidthPercent(activeImg, percent);
        });

        document.addEventListener('mouseup', function () {
            drag = null;
        });

        root.addEventListener('click', function (e) {
            var target = e.target;
            if (target && target.tagName === 'IMG' && root.contains(target)) {
                e.preventDefault();
                selectImage(target);
                return;
            }
            if (!overlay.contains(target)) {
                clearSelection();
            }
        });

        quill.on('text-change', function () {
            if (activeImg && !root.contains(activeImg)) {
                clearSelection();
            } else if (activeImg) {
                positionOverlay(activeImg);
            }
        });

        window.addEventListener('resize', function () {
            if (activeImg) {
                positionOverlay(activeImg);
            }
        });
    }

    function pickAndUploadImage(quill) {
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = function () {
            var file = input.files && input.files[0];
            if (!file) {
                return;
            }
            uploadImageFile(file, quill).catch(function (err) {
                console.error('APM Quill image upload failed:', err);
            });
        };
        input.click();
    }

    var TOOLBAR_TITLES = {
        'ql-font': 'Font (Arial)',
        'ql-size': 'Font size',
        'ql-header': 'Paragraph style',
        'ql-bold': 'Bold',
        'ql-italic': 'Italic',
        'ql-underline': 'Underline',
        'ql-strike': 'Strikethrough',
        'ql-script': 'Subscript / superscript',
        'ql-color': 'Text color',
        'ql-background': 'Highlight color',
        'ql-list': 'Lists',
        'ql-indent': 'Indent',
        'ql-align': 'Alignment',
        'ql-blockquote': 'Block quote',
        'ql-code-block': 'Code block',
        'ql-link': 'Insert link',
        'ql-image': 'Insert image',
        'ql-video': 'Insert video',
        'ql-clean': 'Clear formatting',
        'ql-apm-table-insert': 'Insert table (3×3)',
        'ql-apm-table-row-below': 'Add row below',
        'ql-apm-table-row-above': 'Add row above',
        'ql-apm-table-col-before': 'Add column left',
        'ql-apm-table-col-after': 'Add column right',
        'ql-apm-table-del-row': 'Delete current row',
        'ql-apm-table-del-table': 'Delete table',
    };

    function styleTableElement(table) {
        if (!table || table.tagName !== 'TABLE') {
            return;
        }
        table.classList.add('apm-quill-table');
        table.style.borderCollapse = 'collapse';
        table.style.width = '100%';
        table.style.margin = '8px 0';
        Array.from(table.querySelectorAll('td, th')).forEach(function (cell) {
            cell.style.border = '1px solid #4a5568';
            cell.style.padding = '6px 8px';
            cell.style.verticalAlign = 'top';
            cell.style.minWidth = '48px';
        });
    }

    function styleAllTables(root) {
        if (!root) {
            return;
        }
        root.querySelectorAll('table').forEach(styleTableElement);
    }

    function getActiveTableCell(quill) {
        var sel = window.getSelection();
        if (!sel || !sel.anchorNode || !quill || !quill.root) {
            return null;
        }
        var node = sel.anchorNode;
        if (node.nodeType === 3) {
            node = node.parentNode;
        }
        if (!node || !node.closest) {
            return null;
        }
        var cell = node.closest('td, th');
        if (!cell || !quill.root.contains(cell)) {
            return null;
        }
        return cell;
    }

    function requireTableCell(quill) {
        var cell = getActiveTableCell(quill);
        if (!cell) {
            window.alert('Click inside a table cell first, then use the table buttons.');
        }
        return cell;
    }

    function addTableRow(cell, position) {
        var row = cell.parentNode;
        var table = row.closest('table');
        if (!table) {
            return;
        }
        var colCount = row.cells.length;
        var newRow = document.createElement('tr');
        for (var i = 0; i < colCount; i++) {
            var td = document.createElement('td');
            td.innerHTML = '<br>';
            newRow.appendChild(td);
        }
        if (position === 'above') {
            row.parentNode.insertBefore(newRow, row);
        } else {
            row.parentNode.insertBefore(newRow, row.nextSibling);
        }
        styleTableElement(table);
    }

    function addTableColumn(cell, position) {
        var table = cell.closest('table');
        if (!table) {
            return;
        }
        var insertAt = position === 'before' ? cell.cellIndex : cell.cellIndex + 1;
        Array.from(table.rows).forEach(function (row) {
            var td = document.createElement(row.parentNode && row.parentNode.tagName === 'THEAD' ? 'th' : 'td');
            td.innerHTML = '<br>';
            if (insertAt >= row.cells.length) {
                row.appendChild(td);
            } else {
                row.insertBefore(td, row.cells[insertAt]);
            }
        });
        styleTableElement(table);
    }

    function deleteTableRow(cell) {
        var row = cell.parentNode;
        var table = row.closest('table');
        if (!table) {
            return;
        }
        if (table.rows.length <= 1) {
            table.remove();
            return;
        }
        row.remove();
        styleTableElement(table);
    }

    function deleteTableElement(cell) {
        var table = cell.closest('table');
        if (table) {
            table.remove();
        }
    }

    function insertTable(quill, rows, cols) {
        rows = rows || 3;
        cols = cols || 3;
        var body = '';
        for (var r = 0; r < rows; r++) {
            body += '<tr>';
            for (var c = 0; c < cols; c++) {
                body += '<td><br></td>';
            }
            body += '</tr>';
        }
        var html = '<table class="apm-quill-table"><tbody>' + body + '</tbody></table><p><br></p>';
        var range = quill.getSelection(true);
        var index = range ? range.index : quill.getLength();
        quill.clipboard.dangerouslyPasteHTML(index, html);
        window.setTimeout(function () {
            styleAllTables(quill.root);
        }, 0);
    }

    function tableToolbarHandlers(getQuill) {
        return {
            'apm-table-insert': function () {
                insertTable(getQuill(), 3, 3);
            },
            'apm-table-row-below': function () {
                var cell = requireTableCell(getQuill());
                if (cell) {
                    addTableRow(cell, 'below');
                }
            },
            'apm-table-row-above': function () {
                var cell = requireTableCell(getQuill());
                if (cell) {
                    addTableRow(cell, 'above');
                }
            },
            'apm-table-col-before': function () {
                var cell = requireTableCell(getQuill());
                if (cell) {
                    addTableColumn(cell, 'before');
                }
            },
            'apm-table-col-after': function () {
                var cell = requireTableCell(getQuill());
                if (cell) {
                    addTableColumn(cell, 'after');
                }
            },
            'apm-table-del-row': function () {
                var cell = requireTableCell(getQuill());
                if (cell) {
                    deleteTableRow(cell);
                }
            },
            'apm-table-del-table': function () {
                var cell = requireTableCell(getQuill());
                if (cell) {
                    deleteTableElement(cell);
                }
            },
        };
    }

    function applyToolbarTooltips(wrap) {
        if (!wrap) {
            return;
        }
        wrap.querySelectorAll('.ql-toolbar .ql-picker').forEach(function (picker) {
            var key = Array.from(picker.classList).find(function (cls) {
                return TOOLBAR_TITLES[cls];
            });
            if (!key) {
                return;
            }
            var title = TOOLBAR_TITLES[key];
            picker.setAttribute('title', title);
            var label = picker.querySelector('.ql-picker-label');
            if (label) {
                label.setAttribute('title', title);
                label.setAttribute('aria-label', title);
            }
        });
        wrap.querySelectorAll('.ql-toolbar button').forEach(function (btn) {
            Array.from(btn.classList).forEach(function (cls) {
                if (!TOOLBAR_TITLES[cls]) {
                    return;
                }
                btn.setAttribute('title', TOOLBAR_TITLES[cls]);
                btn.setAttribute('aria-label', TOOLBAR_TITLES[cls]);
            });
        });
    }

    function bindTableEditor(quill, wrap) {
        if (!quill || !wrap || wrap.dataset.apmTableEditorBound === '1') {
            return;
        }
        wrap.dataset.apmTableEditorBound = '1';
        var root = quill.root;
        var activeCell = null;

        function clearActiveCell() {
            if (activeCell) {
                activeCell.classList.remove('apm-quill-table-active');
                activeCell = null;
            }
        }

        root.addEventListener('click', function (e) {
            var target = e.target;
            if (!target || !target.closest) {
                return;
            }
            var cell = target.closest('td, th');
            if (cell && root.contains(cell)) {
                clearActiveCell();
                activeCell = cell;
                cell.classList.add('apm-quill-table-active');
                return;
            }
            clearActiveCell();
        });

        quill.on('text-change', function () {
            styleAllTables(root);
        });
    }

    function sourceHtml(el) {
        if (!el) {
            return '';
        }
        return el.value !== undefined && el.value !== null ? String(el.value) : String(el.textContent || '');
    }

    function shouldKeepSummernote(textarea) {
        if (!textarea) {
            return true;
        }
        if (textarea.dataset.keepSummernote !== undefined || textarea.classList.contains('keep-summernote')) {
            return true;
        }
        var legacyRoot = textarea.closest('[data-apm-use-summernote]');
        return !!legacyRoot;
    }

    function configureArialFontPicker(wrap, quill) {
        var fontPicker = wrap.querySelector('.ql-font');
        if (fontPicker) {
            var label = fontPicker.querySelector('.ql-picker-label');
            if (label) {
                label.setAttribute('data-value', 'arial');
            }
        }
        try {
            quill.format('font', 'arial', Quill.sources.SILENT);
        } catch (e) {
            /* ignore */
        }
    }

    function bindImageDrop(editorEl, quill, disabled) {
        if (disabled) {
            return;
        }
        editorEl.addEventListener('drop', function (e) {
            var files = e.dataTransfer && e.dataTransfer.files;
            if (!files || !files.length) {
                return;
            }
            for (var i = 0; i < files.length; i++) {
                if (files[i].type && files[i].type.indexOf('image/') === 0) {
                    e.preventDefault();
                    uploadImageFile(files[i], quill).catch(function (err) {
                        console.error('APM Quill image drop failed:', err);
                    });
                }
            }
        });
    }

    function bindImagePaste(editorEl, quill, disabled) {
        if (disabled) {
            return;
        }
        editorEl.addEventListener(
            'paste',
            function (ev) {
                var clipboard = ev.clipboardData;
                if (!clipboard) {
                    return;
                }
                var items = clipboard.items ? Array.prototype.slice.call(clipboard.items) : [];
                for (var i = 0; i < items.length; i++) {
                    if (items[i].kind === 'file' && items[i].type.indexOf('image/') === 0) {
                        ev.preventDefault();
                        ev.stopImmediatePropagation();
                        var file = items[i].getAsFile();
                        if (file) {
                            uploadImageFile(file, quill).catch(function (err) {
                                console.error('APM Quill image paste failed:', err);
                            });
                        }
                        return;
                    }
                }
                var html = clipboard.getData('text/html');
                if (html && /data:image\//i.test(html)) {
                    ev.preventDefault();
                    ev.stopImmediatePropagation();
                    collectDataUriImagesFromHtml(html).forEach(function (dataUri, index) {
                        var file = dataUriToFile(dataUri, index);
                        if (file) {
                            uploadImageFile(file, quill).catch(function (err) {
                                console.error('APM Quill HTML image paste failed:', err);
                            });
                        }
                    });
                }
            },
            true
        );
    }

    function bindQuill(wrap, options) {
        if (!wrap || wrap.dataset.apmQuillBound === '1' || typeof Quill === 'undefined') {
            return registry.get(wrap) || null;
        }

        registerFormats();

        var editorEl = wrap.querySelector('.apm-quill-editor');
        var hidden = wrap.querySelector('textarea.apm-quill-source');
        if (!editorEl || !hidden) {
            return null;
        }

        adoptRequiredFromHidden(wrap, hidden);

        var toolbarMode = wrap.dataset.apmQuillToolbar || (options && options.toolbar) || 'full';
        var disabled = wrap.dataset.apmQuillDisabled !== undefined || (options && options.disabled === true);
        var minHeight = wrap.dataset.apmQuillMinHeight || (options && options.minHeight) || '200px';
        editorEl.style.minHeight = minHeight;

        var quill;
        if (disabled) {
            quill = new Quill(editorEl, { theme: 'snow', modules: { toolbar: false } });
            quill.enable(false);
        } else {
            var tableHandlers = tableToolbarHandlers(function () {
                return quill;
            });
            quill = new Quill(editorEl, {
                theme: 'snow',
                modules: {
                    clipboard: {
                        matchVisual: false,
                    },
                    toolbar: {
                        container: toolbarItems(toolbarMode),
                        handlers: Object.assign(
                            {
                                image: function () {
                                    pickAndUploadImage(quill);
                                },
                            },
                            tableHandlers
                        ),
                    },
                },
            });
            bindImageDrop(editorEl, quill, false);
            bindImagePaste(editorEl, quill, false);
            bindImageResize(quill, wrap);
            bindTableEditor(quill, wrap);
            configureArialFontPicker(wrap, quill);
            applyToolbarTooltips(wrap);
        }

        var html = sourceHtml(hidden);
        if (html) {
            quill.root.innerHTML = html;
            normalizeArialContent(quill.root);
            hidden.value = quill.root.innerHTML;
        } else {
            normalizeArialContent(quill.root);
        }

        wrap.dataset.apmQuillBound = '1';
        var entry = { quill: quill, hidden: hidden, wrap: wrap };
        registry.set(wrap, entry);

        if (!disabled && quill) {
            quill.on('text-change', function () {
                normalizeArialContent(quill.root);
                hidden.value = quill.root.innerHTML;
                markQuillInvalid(entry, false);
                scheduleDataUriReplacement(quill, entry);
            });
            if (htmlContainsDataUriImages(quill.root.innerHTML)) {
                scheduleDataUriReplacement(quill, entry);
            }
        }

        return entry;
    }

    function upgradeTextarea(textarea, options) {
        if (!textarea || textarea.dataset.apmQuillUpgraded === '1' || shouldKeepSummernote(textarea)) {
            return null;
        }

        var wrap = document.createElement('div');
        wrap.className = 'apm-quill-wrap';
        if (textarea.dataset.apmQuillToolbar) {
            wrap.dataset.apmQuillToolbar = textarea.dataset.apmQuillToolbar;
        } else if (options && options.toolbar) {
            wrap.dataset.apmQuillToolbar = options.toolbar;
        }
        if (textarea.dataset.apmQuillMinHeight) {
            wrap.dataset.apmQuillMinHeight = textarea.dataset.apmQuillMinHeight;
        } else if (options && options.minHeight) {
            wrap.dataset.apmQuillMinHeight = options.minHeight;
        }
        if (textarea.required || textarea.hasAttribute('required') || textarea.dataset.apmQuillRequired === '1') {
            wrap.dataset.apmQuillRequired = '1';
        }

        var editor = document.createElement('div');
        editor.className = 'apm-quill-editor border rounded bg-white';
        editor.id = (textarea.id || 'apm-quill') + '-editor';

        textarea.classList.add('apm-quill-source', 'd-none');
        textarea.classList.remove('summernote');
        textarea.dataset.apmQuillUpgraded = '1';
        textarea.removeAttribute('required');

        var parent = textarea.parentNode;
        parent.insertBefore(wrap, textarea);
        wrap.appendChild(editor);
        wrap.appendChild(textarea);

        return bindQuill(wrap, options || {});
    }

    function quillContentEmpty(quill) {
        if (!quill || !quill.root) {
            return true;
        }
        var text = (quill.getText() || '').replace(/\u00a0/g, ' ').trim();
        if (text) {
            return false;
        }
        var html = (quill.root.innerHTML || '').trim();
        return !html || html === '<p><br></p>' || html === '<p></p>';
    }

    function markQuillInvalid(entry, invalid) {
        if (!entry || !entry.wrap) {
            return;
        }
        entry.wrap.classList.toggle('is-invalid', !!invalid);
    }

    function clearQuillInvalidStates(root) {
        var scope = root || document;
        scope.querySelectorAll('.apm-quill-wrap.is-invalid').forEach(function (wrap) {
            wrap.classList.remove('is-invalid');
        });
    }

    function validateRequired(root) {
        var scope = root || document;
        var firstInvalid = null;
        scope.querySelectorAll('.apm-quill-wrap[data-apm-quill-required]').forEach(function (wrap) {
            var entry = registry.get(wrap);
            if (!entry || !entry.quill) {
                return;
            }
            normalizeArialContent(entry.quill.root);
            if (entry.hidden) {
                entry.hidden.value = entry.quill.root.innerHTML;
            }
            var empty = quillContentEmpty(entry.quill);
            markQuillInvalid(entry, empty);
            if (empty && !firstInvalid) {
                firstInvalid = entry;
            }
        });
        return firstInvalid;
    }

    function focusQuillEntry(entry) {
        if (!entry || !entry.quill) {
            return;
        }
        try {
            entry.quill.focus();
        } catch (e) {
            if (entry.quill.root && entry.quill.root.focus) {
                entry.quill.root.focus();
            }
        }
        if (entry.wrap && entry.wrap.scrollIntoView) {
            entry.wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function adoptRequiredFromHidden(wrap, hidden) {
        if (!wrap || !hidden) {
            return;
        }
        if (hidden.required || hidden.hasAttribute('required')) {
            wrap.dataset.apmQuillRequired = '1';
            hidden.removeAttribute('required');
        }
    }

    function syncAll(root) {
        var scope = root || document;
        scope.querySelectorAll('.apm-quill-wrap[data-apm-quill-bound="1"]').forEach(function (wrap) {
            var entry = registry.get(wrap);
            if (entry && entry.quill && entry.hidden) {
                normalizeArialContent(entry.quill.root);
                entry.hidden.value = entry.quill.root.innerHTML;
            }
        });
    }

    function initAll(root) {
        if (typeof Quill === 'undefined') {
            return;
        }
        registerFormats();
        var scope = root || document;

        scope.querySelectorAll('.apm-quill-wrap:not([data-apm-quill-bound])').forEach(function (wrap) {
            bindQuill(wrap, {});
        });

        scope.querySelectorAll('textarea.apm-rich-editor:not([data-apm-quill-upgraded])').forEach(function (ta) {
            upgradeTextarea(ta, {});
        });
    }

    function destroyAll(root) {
        var scope = root || document;
        scope.querySelectorAll('.apm-quill-wrap[data-apm-quill-bound="1"]').forEach(function (wrap) {
            delete wrap.dataset.apmQuillBound;
            registry.delete(wrap);
        });
        scope.querySelectorAll('textarea[data-apm-quill-upgraded="1"]').forEach(function (ta) {
            delete ta.dataset.apmQuillUpgraded;
        });
    }

    var formSyncBound = false;
    function bindFormSync() {
        if (formSyncBound) {
            return;
        }
        formSyncBound = true;
        document.addEventListener(
            'submit',
            function (e) {
                var form = e.target;
                if (!form || form.tagName !== 'FORM') {
                    return;
                }
                if (
                    !form.querySelector(
                        '.apm-quill-wrap, textarea.apm-rich-editor, textarea.apm-quill-source, textarea[data-apm-quill-upgraded]'
                    )
                ) {
                    return;
                }
                if (form.dataset.apmQuillResubmit === '1') {
                    delete form.dataset.apmQuillResubmit;
                    syncAll(form);
                    var invalid = validateRequired(form);
                    if (invalid) {
                        e.preventDefault();
                        e.stopPropagation();
                        focusQuillEntry(invalid);
                        if (typeof window.show_notification === 'function') {
                            window.show_notification('Please complete all required rich-text fields.', 'warning');
                        }
                    }
                    return;
                }
                if (formHasPendingImages(form)) {
                    e.preventDefault();
                    e.stopPropagation();
                    ensureAllImagesUploaded(form).then(function () {
                        syncAll(form);
                        if (formHasPendingImages(form)) {
                            if (typeof window.show_notification === 'function') {
                                window.show_notification('An image is still uploading. Wait a moment and try again.', 'warning');
                            }
                            return;
                        }
                        form.dataset.apmQuillResubmit = '1';
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    }).catch(function (err) {
                        console.error('APM Quill image upload before submit failed:', err);
                        if (typeof window.show_notification === 'function') {
                            window.show_notification('Image upload failed. Try again or remove the image.', 'warning');
                        }
                    });
                    return;
                }
                syncAll(form);
                var invalid = validateRequired(form);
                if (invalid) {
                    e.preventDefault();
                    e.stopPropagation();
                    focusQuillEntry(invalid);
                    if (typeof window.show_notification === 'function') {
                        window.show_notification('Please complete all required rich-text fields.', 'warning');
                    }
                }
            },
            true
        );
    }

    function boot() {
        if (typeof Quill === 'undefined') {
            return;
        }
        initAll(document);
        bindFormSync();
    }

    global.ApmQuillEditor = {
        fullToolbar: fullToolbar,
        simpleToolbar: simpleToolbar,
        bindQuill: bindQuill,
        upgradeTextarea: upgradeTextarea,
        initAll: initAll,
        syncAll: syncAll,
        validateRequired: validateRequired,
        clearInvalidStates: clearQuillInvalidStates,
        destroyAll: destroyAll,
        ensureAllImagesUploaded: ensureAllImagesUploaded,
        boot: boot,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', function () {
        boot();
    });
})(window);
