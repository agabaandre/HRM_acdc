/**
 * Staff Signature Manager — generate typed signatures (profile style) and bulk upload.
 */
(function () {
  'use strict';

  var cfg = window.STAFF_SIGNATURE_MANAGER || {};
  var FONT_URL = 'https://cdn.jsdelivr.net/npm/@fontsource/dancing-script/files/dancing-script-latin-400-normal.woff';
  var FONT_SPEC = 'italic 58px SignatureFont';
  var DEFAULT_COLOR = cfg.signatureColor || '#3B82F6';
  var CANVAS_WIDTH = 620;
  var CANVAS_HEIGHT = 110;

  /** staff_id -> { dataUrl, blob } */
  var generated = Object.create(null);

  function waitForSignatureFont() {
    if (!document.fonts) {
      return Promise.resolve();
    }
    return document.fonts.load(FONT_SPEC).then(function () {
      return document.fonts.check(FONT_SPEC);
    }).catch(function () {
      if (typeof FontFace === 'undefined') {
        return document.fonts.ready;
      }
      return new FontFace('SignatureFont', 'url(' + FONT_URL + ')', { style: 'italic', weight: '400' })
        .load()
        .then(function (face) {
          document.fonts.add(face);
        })
        .catch(function () {
          return document.fonts.ready;
        });
    });
  }

  /** Match DocuSeal signature-maker drawText(): italic 58px, centered, y offset +11 */
  function generateTypedSignaturePng(text, color) {
    var name = (text || '').trim();
    if (!name) {
      return Promise.reject(new Error('Empty signature text'));
    }
    return waitForSignatureFont().then(function () {
      var canvas = document.createElement('canvas');
      var scale = 2;
      canvas.width = CANVAS_WIDTH * scale;
      canvas.height = CANVAS_HEIGHT * scale;
      var ctx = canvas.getContext('2d');
      ctx.scale(scale, scale);
      ctx.clearRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
      ctx.font = FONT_SPEC;
      ctx.fillStyle = color || DEFAULT_COLOR;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'alphabetic';
      ctx.fillText(name, CANVAS_WIDTH / 2, CANVAS_HEIGHT / 2 + 11);
      return new Promise(function (resolve, reject) {
        canvas.toBlob(function (blob) {
          if (!blob) {
            reject(new Error('PNG conversion failed'));
            return;
          }
          var reader = new FileReader();
          reader.onload = function () {
            resolve({
              blob: blob,
              dataUrl: typeof reader.result === 'string' ? reader.result : '',
            });
          };
          reader.onerror = reject;
          reader.readAsDataURL(blob);
        }, 'image/png');
      });
    });
  }

  function setPreview(staffId, dataUrl) {
    var box = document.querySelector('[data-preview-for="' + staffId + '"]');
    var placeholder = document.querySelector('[data-placeholder-for="' + staffId + '"]');
    var img = box ? box.querySelector('.sig-manager-generated-img') : null;
    if (box && img) {
      img.src = dataUrl;
      box.classList.remove('d-none');
    }
    if (placeholder) {
      placeholder.classList.add('d-none');
    }
  }

  function updateUploadButtonState() {
    var keys = Object.keys(generated);
    var btn = document.getElementById('sigManagerUploadSelected');
    if (btn) {
      btn.disabled = keys.length === 0;
    }
  }

  function setBulkStatus(msg) {
    var el = document.getElementById('sigManagerBulkStatus');
    if (el) {
      el.textContent = msg || '';
    }
  }

  function generateForRow(row) {
    var staffId = row.getAttribute('data-staff-id');
    var text = row.getAttribute('data-signature-text') || '';
    if (!staffId || row.getAttribute('data-signature-status') === 'valid') {
      return Promise.resolve(false);
    }
    return generateTypedSignaturePng(text, DEFAULT_COLOR).then(function (result) {
      generated[staffId] = result;
      setPreview(staffId, result.dataUrl);
      updateUploadButtonState();
      return true;
    });
  }

  function getSelectedRows() {
    return Array.prototype.slice.call(
      document.querySelectorAll('.sig-manager-row .sig-manager-select:checked')
    ).map(function (cb) {
      return cb.closest('.sig-manager-row');
    }).filter(Boolean);
  }

  var currentPage = 0;
  var currentPerPage = 20;
  var debounceTimer = null;

  function collectFilters() {
    var staffName = ($('#staff_name').val() || '').trim();
    var status = $('#signature_status').val() || 'all';
    var scope = $('#signature_scope').val() || 'approvers';
    var filters = {
      signature_status: status,
      scope: scope,
    };
    if (staffName.length >= 3) {
      filters.staff_name = staffName;
    }
    return filters;
  }

  function updateStats(stats) {
    if (!stats) {
      return;
    }
    $('#sigStatTotal').text(stats.total != null ? stats.total : '—');
    $('#sigStatValid').text(stats.valid != null ? stats.valid : '—');
    $('#sigStatMissing').text(stats.missing != null ? stats.missing : '—');
    $('#sigStatBroken').text(stats.broken != null ? stats.broken : '—');
  }

  function generatePagination(total, page, perPage, records) {
    var totalPages = Math.ceil(total / perPage) || 1;
    var html = '<nav><ul class="pagination pagination-sm mb-0">';
    var prevDisabled = page <= 0 ? ' disabled' : '';
    html += '<li class="page-item' + prevDisabled + '"><a class="page-link" href="#" data-page-sig="' + (page - 1) + '">Prev</a></li>';
    var start = Math.max(0, page - 2);
    var end = Math.min(totalPages - 1, page + 2);
    for (var i = start; i <= end; i++) {
      html += '<li class="page-item' + (i === page ? ' active' : '') + '"><a class="page-link" href="#" data-page-sig="' + i + '">' + (i + 1) + '</a></li>';
    }
    var nextDisabled = page >= totalPages - 1 ? ' disabled' : '';
    html += '<li class="page-item' + nextDisabled + '"><a class="page-link" href="#" data-page-sig="' + (page + 1) + '">Next</a></li></ul></nav>';
    var recordsText = '<span class="text-muted ms-3"><strong>' + (records || 0) + '</strong> matching staff</span>';
    $('#paginationLinksTopSig').html(html + recordsText);
    $('#paginationLinksSig').html(html);
  }

  function loadData() {
    $('#signatureManagerBody').html(
      '<tr><td colspan="7" class="text-center">' +
      '<div class="spinner-border text-primary" role="status"></div>' +
      '<p class="mt-2 mb-0">Loading staff signatures...</p></td></tr>'
    );

    var postData = collectFilters();
    postData.page = currentPage;
    postData.per_page = currentPerPage;
    postData[cfg.csrfTokenName] = cfg.csrfHash;

    $.ajax({
      url: cfg.ajaxUrl,
      method: 'POST',
      data: postData,
      dataType: 'json',
      success: function (response) {
        if (response && response.error) {
          setBulkStatus(response.message || 'Could not load data.');
        }
        if (response && response.html !== undefined) {
          $('#signatureManagerBody').html(response.html);
        }
        if (response && response.csrf_hash) {
          cfg.csrfHash = response.csrf_hash;
        }
        if (response && response.stats) {
          updateStats(response.stats);
        }
        generatePagination(response.total || 0, response.page || 0, response.per_page || currentPerPage, response.records || 0);
        generated = Object.create(null);
        updateUploadButtonState();
        $('#sigManagerSelectAll').prop('checked', false);
        if (response && response.approver_count != null && $('#signature_scope').val() === 'approvers') {
          setBulkStatus(response.approver_count + ' APM approver(s) in scope');
        } else if ($('#signature_scope').val() === 'current' && response && response.stats) {
          setBulkStatus(response.stats.total + ' active staff in scope');
        }
      },
      error: function (xhr) {
        var msg = 'Error loading data. Please try again.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        } else if (xhr.responseText && xhr.responseText.indexOf('not allowed') !== -1) {
          msg = 'Session expired or CSRF rejected — refresh the page and try again.';
        } else if (xhr.status === 403) {
          msg = 'You do not have permission to view Signature Manager.';
        }
        $('#signatureManagerBody').html('<tr><td colspan="7" class="text-center text-danger">' + msg + '</td></tr>');
      }
    });
  }

  function uploadGenerated() {
    var entries = Object.keys(generated).map(function (staffId) {
      return {
        staff_id: parseInt(staffId, 10),
        signature_data_url: generated[staffId].dataUrl
      };
    });
    if (!entries.length) {
      setBulkStatus('Generate previews first.');
      return;
    }

    setBulkStatus('Uploading ' + entries.length + ' signature(s)...');
    $('#sigManagerUploadSelected').prop('disabled', true);

    var postData = {};
    postData.signatures_json = JSON.stringify(entries);
    postData[cfg.csrfTokenName] = cfg.csrfHash;

    $.ajax({
      url: cfg.uploadUrl,
      method: 'POST',
      data: postData,
      dataType: 'json',
      success: function (response) {
        if (response.csrf_hash) {
          cfg.csrfHash = response.csrf_hash;
        }
        var msg = 'Saved: ' + (response.saved || 0) +
          ', skipped: ' + (response.skipped || 0) +
          ', failed: ' + (response.failed || 0);
        setBulkStatus(msg);
        loadData();
      },
      error: function (xhr) {
        var msg = 'Upload failed.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        setBulkStatus(msg);
        updateUploadButtonState();
      }
    });
  }

  function uploadManualFile(staffId, file) {
    if (!file || !cfg.manualUploadUrl) {
      return;
    }
    setBulkStatus('Uploading file for staff #' + staffId + '…');
    var fd = new FormData();
    fd.append('staff_id', staffId);
    fd.append('signature', file);
    fd.append(cfg.csrfTokenName, cfg.csrfHash);

    $.ajax({
      url: cfg.manualUploadUrl,
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function (response) {
        if (response.csrf_hash) {
          cfg.csrfHash = response.csrf_hash;
        }
        if (response.error) {
          setBulkStatus(response.message || 'Upload failed.');
          return;
        }
        setBulkStatus('Signature uploaded for staff #' + staffId);
        loadData();
      },
      error: function (xhr) {
        var msg = 'Upload failed.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        setBulkStatus(msg);
      }
    });
  }

  function bindExportLinks() {
    function refresh() {
      var filters = collectFilters();
      var qs = $.param(filters);
      $('#sigExportExcel').attr('href', cfg.exportExcelUrl + (qs ? ('?' + qs) : ''));
      $('#sigExportPdf').attr('href', cfg.exportPdfUrl + (qs ? ('?' + qs) : ''));
    }
    refresh();
    $('#staff_name, #signature_status, #signature_scope').on('change input', refresh);
  }

  $(document).ready(function () {
    waitForSignatureFont();
    bindExportLinks();
    loadData();

    $('#recordsPerPageSig').on('change', function () {
      currentPerPage = parseInt($(this).val(), 10) || 20;
      currentPage = 0;
      loadData();
    });

    $('#signature_status, #signature_scope').on('change', function () {
      currentPage = 0;
      loadData();
    });

    $('#staff_name').on('input', function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        currentPage = 0;
        loadData();
      }, 350);
    });

    $(document).on('click', '[data-page-sig]', function (e) {
      e.preventDefault();
      var p = $(this).data('page-sig');
      if (p !== undefined && !$(this).parent().hasClass('disabled')) {
        currentPage = parseInt(p, 10);
        loadData();
      }
    });

    $('#sigManagerSelectAll').on('change', function () {
      var checked = $(this).is(':checked');
      $('.sig-manager-select').prop('checked', checked);
    });

    $('#sigManagerGenerateSelected').on('click', function () {
      var rows = getSelectedRows();
      if (!rows.length) {
        rows = Array.prototype.slice.call(document.querySelectorAll('.sig-manager-row[data-signature-status="missing"], .sig-manager-row[data-signature-status="broken"]'));
      }
      if (!rows.length) {
        setBulkStatus('No staff available to generate.');
        return;
      }
      setBulkStatus('Generating previews...');
      var chain = Promise.resolve();
      var count = 0;
      rows.forEach(function (row) {
        chain = chain.then(function () {
          return generateForRow(row).then(function (ok) {
            if (ok) {
              count++;
            }
          });
        });
      });
      chain.then(function () {
        setBulkStatus('Generated ' + count + ' preview(s). Review then upload.');
      }).catch(function () {
        setBulkStatus('Some previews could not be generated.');
      });
    });

    $(document).on('click', '.sig-manager-generate-one', function () {
      var row = $(this).closest('.sig-manager-row')[0];
      if (!row) {
        return;
      }
      setBulkStatus('Generating preview...');
      generateForRow(row).then(function (ok) {
        setBulkStatus(ok ? 'Preview generated.' : 'Could not generate preview.');
      }).catch(function () {
        setBulkStatus('Could not generate preview.');
      });
    });

    $(document).on('change', '.sig-manager-upload-input', function () {
      var input = this;
      var staffId = input.getAttribute('data-staff-id');
      var file = input.files && input.files[0];
      if (!staffId || !file) {
        return;
      }
      uploadManualFile(staffId, file);
      input.value = '';
    });

    $('#sigManagerUploadSelected').on('click', uploadGenerated);
  });
})();
