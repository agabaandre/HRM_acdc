/**
 * Layout-wide jQuery plugins (flatpickr, select2, DataTables, Summernote).
 * Re-inits after Livewire wire:navigate; core libraries use data-navigate-once.
 */
(function () {
    'use strict';

    if (typeof window.apmOnJQuery !== 'function') {
        return;
    }

    function uploadImage(file, editor, $) {
        var url = document.body.getAttribute('data-image-upload-url');
        if (!url || typeof $ === 'undefined') {
            return;
        }
        var tokenEl = document.querySelector('meta[name="csrf-token"]');
        var data = new FormData();
        data.append('file', file);
        if (tokenEl) {
            data.append('_token', tokenEl.getAttribute('content'));
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            cache: false,
            contentType: false,
            processData: false,
            success: function (response) {
                var imageUrl = response.url || response;
                $(editor).summernote('insertImage', imageUrl);
            },
            error: function (jqXHR, textStatus, errorThrown) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Image upload failed: ' + textStatus + ' ' + errorThrown);
                }
            },
        });
    }

    window.apmSummernoteOptions = function (overrides) {
        var $ = window.jQuery;
        if (!$) {
            return {};
        }

        var base = {
            placeholder: 'Type here…',
            height: 300,
            minHeight: 200,
            maxHeight: null,
            focus: false,
            dialogsInBody: true,
            styleWithSpan: true,
            disableDragAndDrop: false,
            fontNames: ['Arial', 'Arial Black', 'Calibri', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
            fontNamesIgnoreCheck: ['Arial', 'Arial Black', 'Calibri', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
            fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '28', '32', '36', '48', '64'],
            fontSizeUnits: ['px', 'pt'],
            lineHeights: ['0.9', '1.0', '1.15', '1.3', '1.5', '1.75', '2.0', '2.5', '3.0'],
            tableClassName: 'table table-bordered table-sm',
            toolbar: [
                ['misc', ['undo', 'redo']],
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'clear']],
                ['fontname', ['fontname']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['height', ['lineheight']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video', 'hr']],
                ['view', ['fullscreen', 'codeview', 'help']],
            ],
            popover: {
                table: [
                    ['add', ['addRowDown', 'addRowUp', 'addColLeft', 'addColRight']],
                    ['delete', ['deleteRow', 'deleteCol', 'deleteTable']],
                ],
                image: [
                    ['image', ['resizeFull', 'resizeHalf', 'resizeQuarter', 'resizeNone']],
                    ['float', ['floatLeft', 'floatRight', 'floatNone']],
                    ['remove', ['removeMedia']],
                ],
            },
            callbacks: {
                onInit: function () {
                    $(this).next('.note-editor').find('.note-editable').css({
                        'font-family': 'Arial, Calibri, Tahoma, Verdana, "Times New Roman", "Courier New", sans-serif',
                        'font-size': '12pt',
                        'line-height': '1.3',
                    });
                },
                onCreateLink: function (link) {
                    return link;
                },
                onImageUpload: function (files) {
                    for (var i = 0; i < files.length; i++) {
                        uploadImage(files[i], this, $);
                    }
                },
                onPaste: function (e) {
                    var clipboardData = (e.originalEvent || e).clipboardData;
                    if (clipboardData && clipboardData.getData) {
                        var html = clipboardData.getData('text/html');
                        if (html) {
                            html = html
                                .replace(/border="[^"]*"/gi, '')
                                .replace(/style="[^"]*border[^"]*"/gi, '');
                            document.execCommand('insertHTML', false, html);
                            e.preventDefault();
                        }
                    }
                },
            },
        };

        return $.extend(true, {}, base, overrides || {});
    };

    window.apmInitLayoutPlugins = function ($) {
        if (typeof flatpickr !== 'undefined') {
            $('.datepicker').each(function () {
                if (this._flatpickr) {
                    return;
                }
                flatpickr(this, {
                    theme: 'confetti',
                    altInput: true,
                    altFormat: 'F j, Y',
                    dateFormat: 'Y-m-d',
                    allowInput: true,
                });
            });

            var currentYear = new Date().getFullYear();
            document.querySelectorAll('.current_datepicker').forEach(function (el) {
                if (el._flatpickr) {
                    return;
                }
                flatpickr(el, {
                    dateFormat: 'Y-m-d',
                    minDate: currentYear + '-01-01',
                    maxDate: currentYear + '-12-31',
                    disableMobile: true,
                });
            });
        }

        if ($.fn.select2) {
            $('.select2').each(function () {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    return;
                }
                $(this).select2({
                    theme: 'bootstrap4',
                    width: $(this).data('width') || ($(this).hasClass('w-100') ? '100%' : 'style'),
                    placeholder: $(this).data('placeholder'),
                    allowClear: Boolean($(this).data('allow-clear')),
                });
            });
            $('.multiple-select').each(function () {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    return;
                }
                $(this).select2({
                    theme: 'bootstrap4',
                    multiple: true,
                    width: $(this).data('width') || ($(this).hasClass('w-100') ? '100%' : 'style'),
                    placeholder: $(this).data('placeholder'),
                    allowClear: Boolean($(this).data('allow-clear')),
                });
            });
        }

        if ($.fn.autocomplete) {
            var priorities = ['Low', 'Medium', 'High'];
            var $prio = $('#edit_priority');
            if ($prio.length) {
                $prio.autocomplete({ source: priorities });
            }
        }

        if ($.fn.DataTable) {
            $('.mydata').each(function () {
                if ($.fn.DataTable.isDataTable(this)) {
                    return;
                }
                $(this).DataTable({
                    dom: 'Bfrtip',
                    paging: true,
                    lengthChange: true,
                    searching: true,
                    ordering: true,
                    info: true,
                    autoWidth: true,
                    lengthMenu: [
                        [25, 50, 100, 150, -1],
                        ['25', '50', '100', '150', '200', 'Show all'],
                    ],
                    buttons: ['csvHtml5', 'pdfHtml5', 'pageLength'],
                });
            });
        }

        if ($.fn.summernote && typeof window.apmSummernoteOptions === 'function') {
            $('.summernote').each(function () {
                var $el = $(this);
                if ($el.next('.note-editor').length) {
                    return;
                }
                $el.summernote(window.apmSummernoteOptions());
            });
        }
    };

    window.apmOnJQuery(window.apmInitLayoutPlugins);
})();
