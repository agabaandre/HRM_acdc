/**
 * Staff Signature Manager — generate typed signatures (profile style) and bulk upload.
 */
(function () {
  'use strict';

  var cfg = window.STAFF_SIGNATURE_MANAGER || {};
  var FONT_URL = 'https://cdn.jsdelivr.net/npm/@fontsource/dancing-script/files/dancing-script-latin-400-normal.woff';
  var DEFAULT_COLOR = cfg.signatureColor || '#3B82F6';
  /** Standard export size (matches saved signature PNGs, ~kaseya reference). */
  var CANVAS_WIDTH = cfg.signatureWidth || 380;
  var CANVAS_WIDTH_LARGE = cfg.signatureWidthLarge || Math.round(CANVAS_WIDTH * 1.2);
  var CANVAS_HEIGHT = cfg.signatureHeight || 169;
  var CANVAS_PADDING = cfg.signaturePadding || 2;
  var FONT_FAMILY = 'SignatureFont';

  /** staff_id -> { dataUrl, blob, allowOverride } */
  var generated = Object.create(null);

  function fontSpec(sizePx) {
    return 'italic ' + sizePx + 'px ' + FONT_FAMILY;
  }

  function waitForSignatureFont(sizePx) {
    var spec = fontSpec(sizePx || 88);
    if (!document.fonts) {
      return Promise.resolve();
    }
    return document.fonts.load(spec).then(function () {
      return document.fonts.check(spec);
    }).catch(function () {
      if (typeof FontFace === 'undefined') {
        return document.fonts.ready;
      }
      return new FontFace(FONT_FAMILY, 'url(' + FONT_URL + ')', { style: 'italic', weight: '400' })
        .load()
        .then(function (face) {
          document.fonts.add(face);
        })
        .catch(function () {
          return document.fonts.ready;
        });
    });
  }

  /** Use +20% width when name does not fit at full height on the standard canvas. */
  function resolveTypedExportWidth(name, startSize) {
    var probe = document.createElement('canvas').getContext('2d');
    probe.font = fontSpec(startSize);
    var innerBase = CANVAS_WIDTH - (CANVAS_PADDING * 2);
    if (probe.measureText(name).width > innerBase) {
      return CANVAS_WIDTH_LARGE;
    }
    return CANVAS_WIDTH;
  }

  /** Fit typed signature to export canvas with ~2px padding (fills height like reference PNG). */
  function generateTypedSignaturePng(text, color) {
    var name = (text || '').trim();
    if (!name) {
      return Promise.reject(new Error('Empty signature text'));
    }
    var innerH = CANVAS_HEIGHT - (CANVAS_PADDING * 2);
    var startSize = Math.floor(innerH * 0.92);
    return waitForSignatureFont(startSize).then(function () {
      var exportWidth = resolveTypedExportWidth(name, startSize);
      var innerW = exportWidth - (CANVAS_PADDING * 2);
      var canvas = document.createElement('canvas');
      var scale = 2;
      canvas.width = exportWidth * scale;
      canvas.height = CANVAS_HEIGHT * scale;
      var ctx = canvas.getContext('2d');
      ctx.scale(scale, scale);
      ctx.clearRect(0, 0, exportWidth, CANVAS_HEIGHT);
      ctx.fillStyle = color || DEFAULT_COLOR;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      var fontSize = startSize;
      var minSize = 16;
      while (fontSize >= minSize) {
        ctx.font = fontSpec(fontSize);
        if (ctx.measureText(name).width <= innerW) {
          break;
        }
        fontSize -= 2;
      }
      ctx.font = fontSpec(fontSize);
      ctx.fillText(name, exportWidth / 2, CANVAS_HEIGHT / 2);
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
    var savedBox = document.querySelector('[data-saved-preview-for="' + staffId + '"]');
    var placeholder = document.querySelector('[data-placeholder-for="' + staffId + '"]');
    var img = box ? box.querySelector('.sig-manager-generated-img') : null;
    if (box && img) {
      img.src = dataUrl;
      box.classList.remove('d-none');
    }
    if (savedBox) {
      savedBox.classList.add('d-none');
    }
    if (placeholder) {
      placeholder.classList.add('d-none');
    }
  }

  function rowAllowsReplace(row) {
    if (!row) {
      return false;
    }
    var status = row.getAttribute('data-signature-status');
    if (status !== 'valid') {
      return true;
    }
    var override = row.querySelector('.sig-manager-override');
    return override && override.checked;
  }

  function rowAllowOverrideFlag(row) {
    return rowAllowsReplace(row) && row.getAttribute('data-signature-status') === 'valid';
  }

  function syncRowOverrideUi(row) {
    if (!row) {
      return;
    }
    var allowed = rowAllowsReplace(row);
    var isValid = row.getAttribute('data-signature-status') === 'valid';
    var selectCb = row.querySelector('.sig-manager-select');
    var genBtn = row.querySelector('.sig-manager-generate-one');
    var uploadInput = row.querySelector('.sig-manager-upload-input');
    var uploadBtn = row.querySelector('.sig-manager-upload-btn');
    if (genBtn) {
      genBtn.disabled = !allowed;
    }
    if (uploadInput) {
      uploadInput.disabled = !allowed;
    }
    if (uploadBtn) {
      uploadBtn.disabled = !allowed;
    }
    if (selectCb && selectCb.classList.contains('sig-manager-select-override')) {
      if (allowed) {
        selectCb.classList.remove('d-none');
        selectCb.disabled = false;
      } else {
        selectCb.checked = false;
        selectCb.classList.add('d-none');
        selectCb.disabled = true;
      }
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

  function updateApproverCacheMeta(cache) {
    var el = document.getElementById('sigManagerApproverCacheMeta');
    var btn = document.getElementById('sigManagerRefreshApprovers');
    if (!el) {
      return;
    }
    if ($('#signature_scope').val() !== 'approvers') {
      el.textContent = 'Approver cache applies to APM approvers scope only.';
      if (btn) {
        btn.disabled = true;
      }
      return;
    }
    if (btn) {
      btn.disabled = false;
    }
    if (!cache) {
      el.textContent = 'Approver cache: not loaded';
      return;
    }
    var count = cache.count != null ? cache.count : '—';
    var label = cache.updated_at_label || 'Never';
    var staleNote = cache.stale ? ' (refreshing in background…)' : '';
    var source = cache.source ? (' · source: ' + cache.source) : '';
    el.textContent = 'Approver cache: ' + count + ' staff · updated ' + label + source + staleNote;
  }

  function refreshApproverCache(reloadTable) {
    if (!cfg.refreshApproversUrl) {
      return;
    }
    var btn = $('#sigManagerRefreshApprovers');
    btn.prop('disabled', true).find('i').addClass('fa-spin');
    setBulkStatus('Refreshing approver list from APM…');

    var postData = {};
    postData[cfg.csrfTokenName] = cfg.csrfHash;

    $.ajax({
      url: cfg.refreshApproversUrl,
      method: 'POST',
      data: postData,
      dataType: 'json',
      success: function (response) {
        if (response && response.csrf_hash) {
          cfg.csrfHash = response.csrf_hash;
        }
        if (response && response.approver_cache) {
          cfg.approverCache = response.approver_cache;
          updateApproverCacheMeta(response.approver_cache);
        }
        setBulkStatus(response && response.message ? response.message : 'Approver cache updated.');
        if (reloadTable && $('#signature_scope').val() === 'approvers') {
          loadData(true);
        }
      },
      error: function (xhr) {
        var msg = 'Could not refresh approver cache.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        setBulkStatus(msg);
      },
      complete: function () {
        btn.prop('disabled', false).find('i').removeClass('fa-spin');
      }
    });
  }

  function generateForRow(row) {
    var staffId = row.getAttribute('data-staff-id');
    var text = row.getAttribute('data-signature-text') || '';
    if (!staffId || !rowAllowsReplace(row)) {
      return Promise.resolve(false);
    }
    return generateTypedSignaturePng(text, DEFAULT_COLOR).then(function (result) {
      generated[staffId] = {
        dataUrl: result.dataUrl,
        blob: result.blob,
        allowOverride: rowAllowOverrideFlag(row),
      };
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

  function loadData(clearGenerated) {
    if (clearGenerated !== false) {
      generated = Object.create(null);
    }

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
        updateUploadButtonState();
        $('#sigManagerSelectAll').prop('checked', false);
        document.querySelectorAll('.sig-manager-row').forEach(function (row) {
          syncRowOverrideUi(row);
          var staffId = row.getAttribute('data-staff-id');
          if (staffId && generated[staffId]) {
            setPreview(staffId, generated[staffId].dataUrl);
          }
        });
        if (response && response.approver_cache) {
          cfg.approverCache = response.approver_cache;
          updateApproverCacheMeta(response.approver_cache);
        }
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
        signature_data_url: generated[staffId].dataUrl,
        allow_override: generated[staffId].allowOverride ? 1 : 0,
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
        loadData(true);
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

  function uploadManualFile(staffId, file, allowOverride) {
    if (!file || !cfg.manualUploadUrl) {
      return;
    }
    setBulkStatus('Uploading file for staff #' + staffId + '…');
    var fd = new FormData();
    fd.append('staff_id', staffId);
    fd.append('signature', file);
    if (allowOverride) {
      fd.append('allow_override', '1');
    }
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
        loadData(true);
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
    waitForSignatureFont(Math.floor((CANVAS_HEIGHT - CANVAS_PADDING * 2) * 0.92));
    bindExportLinks();
    updateApproverCacheMeta(cfg.approverCache || null);
    loadData(true);

    $('#sigManagerRefreshApprovers').on('click', function () {
      refreshApproverCache(true);
    });

    $('#recordsPerPageSig').on('change', function () {
      currentPerPage = parseInt($(this).val(), 10) || 20;
      currentPage = 0;
      loadData(true);
    });

    $('#signature_status, #signature_scope').on('change', function () {
      currentPage = 0;
      updateApproverCacheMeta(cfg.approverCache || null);
      loadData(true);
    });

    $('#staff_name').on('input', function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        currentPage = 0;
        loadData(true);
      }, 350);
    });

    $(document).on('click', '[data-page-sig]', function (e) {
      e.preventDefault();
      var p = parseInt($(this).attr('data-page-sig'), 10);
      if (!isNaN(p) && !$(this).parent().hasClass('disabled')) {
        currentPage = p;
        loadData(false);
      }
    });

    $('#sigManagerSelectAll').on('change', function () {
      var checked = $(this).is(':checked');
      $('#signatureManagerBody .sig-manager-select:not(:disabled)').prop('checked', checked);
    });

    $('#sigManagerGenerateSelected').on('click', function () {
      var rows = getSelectedRows();
      if (!rows.length) {
        rows = Array.prototype.slice.call(document.querySelectorAll('.sig-manager-row')).filter(function (row) {
          var status = row.getAttribute('data-signature-status');
          return status === 'missing' || status === 'broken' || rowAllowsReplace(row);
        });
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

    $(document).on('click', '.sig-manager-upload-btn', function () {
      var btn = this;
      if (btn.disabled) {
        return;
      }
      var row = btn.closest('.sig-manager-row');
      if (!row || !rowAllowsReplace(row)) {
        return;
      }
      var input = row.querySelector('.sig-manager-upload-input');
      if (input && !input.disabled) {
        input.value = '';
        input.click();
      }
    });

    $(document).on('change', '.sig-manager-upload-input', function () {
      var input = this;
      var staffId = input.getAttribute('data-staff-id');
      var file = input.files && input.files[0];
      var row = input.closest('.sig-manager-row');
      if (!staffId || !file || !rowAllowsReplace(row)) {
        return;
      }
      uploadManualFile(staffId, file, rowAllowOverrideFlag(row));
      input.value = '';
    });

    $(document).on('change', '.sig-manager-override', function () {
      var row = this.closest('.sig-manager-row');
      syncRowOverrideUi(row);
      if (!this.checked) {
        var staffId = row.getAttribute('data-staff-id');
        if (staffId && generated[staffId]) {
          delete generated[staffId];
          updateUploadButtonState();
        }
      }
    });

    $('#sigManagerUploadSelected').on('click', uploadGenerated);
  });
})();
