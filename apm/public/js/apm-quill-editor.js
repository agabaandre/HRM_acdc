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
            ['link', 'image', 'video', 'table'],
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

    function uploadImageFile(file, quill) {
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
                var range = quill.getSelection(true);
                var index = range ? range.index : quill.getLength();
                quill.insertEmbed(index, 'image', url, 'user');
                quill.setSelection(index + 1);
                window.setTimeout(function () {
                    prepareInsertedImage(quill, index);
                }, 0);
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

    function insertBasicTable(quill) {
        var range = quill.getSelection(true);
        var index = range ? range.index : quill.getLength();
        var html =
            '<table class="table table-bordered table-sm"><tbody>'
            + '<tr><td><br></td><td><br></td><td><br></td></tr>'
            + '<tr><td><br></td><td><br></td><td><br></td></tr>'
            + '<tr><td><br></td><td><br></td><td><br></td></tr>'
            + '</tbody></table><p><br></p>';
        quill.clipboard.dangerouslyPasteHTML(index, html);
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

        var toolbarMode = wrap.dataset.apmQuillToolbar || (options && options.toolbar) || 'full';
        var disabled = wrap.dataset.apmQuillDisabled !== undefined || (options && options.disabled === true);
        var minHeight = wrap.dataset.apmQuillMinHeight || (options && options.minHeight) || '200px';
        editorEl.style.minHeight = minHeight;

        var quill;
        if (disabled) {
            quill = new Quill(editorEl, { theme: 'snow', modules: { toolbar: false } });
            quill.enable(false);
        } else {
            quill = new Quill(editorEl, {
                theme: 'snow',
                modules: {
                    toolbar: {
                        container: toolbarItems(toolbarMode),
                        handlers: {
                            image: function () {
                                pickAndUploadImage(quill);
                            },
                            table: function () {
                                insertBasicTable(quill);
                            },
                        },
                    },
                },
            });
            bindImageDrop(editorEl, quill, false);
            bindImageResize(quill, wrap);
            configureArialFontPicker(wrap, quill);
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

        var editor = document.createElement('div');
        editor.className = 'apm-quill-editor border rounded bg-white';
        editor.id = (textarea.id || 'apm-quill') + '-editor';

        textarea.classList.add('apm-quill-source', 'd-none');
        textarea.classList.remove('summernote');
        textarea.dataset.apmQuillUpgraded = '1';

        var parent = textarea.parentNode;
        parent.insertBefore(wrap, textarea);
        wrap.appendChild(editor);
        wrap.appendChild(textarea);

        return bindQuill(wrap, options || {});
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
                    form.querySelector(
                        '.apm-quill-wrap, textarea.apm-rich-editor, textarea.apm-quill-source, textarea[data-apm-quill-upgraded]'
                    )
                ) {
                    syncAll(form);
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
        destroyAll: destroyAll,
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
