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

    function registerFonts() {
        if (fontsRegistered || typeof Quill === 'undefined') {
            return;
        }
        try {
            var Font = Quill.import('formats/font');
            Font.whitelist = [
                'arial',
                'calibri',
                'courier-new',
                'georgia',
                'tahoma',
                'times-new-roman',
                'verdana',
            ];
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
            [{ header: [1, 2, 3, 4, 5, 6, false] }],
            [{ font: ['arial', 'calibri', 'courier-new', 'georgia', 'tahoma', 'times-new-roman', 'verdana'] }],
            [{ size: ['small', false, 'large', 'huge'] }],
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

        registerFonts();

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
        }

        var html = sourceHtml(hidden);
        if (html) {
            quill.root.innerHTML = html;
            hidden.value = html;
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
                entry.hidden.value = entry.quill.root.innerHTML;
            }
        });
    }

    function initAll(root) {
        if (typeof Quill === 'undefined') {
            return;
        }
        registerFonts();
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
