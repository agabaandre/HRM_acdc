<?php
/** Midterm / endterm: Quill 2 rich text (aligned with APM weekly-briefing/edit). */
$wrap_midterm_training = !empty($wrap_midterm_training);
?>
<?php /* Quill snow CSS is already linked globally in templates/partials/css_files.php */ ?>
<style>
  .objective-table th, .objective-table td { text-align: left; padding: 0; border: 1px solid #ccc; }
  .objective-table {
    table-layout: fixed;
    width: 100%;
  }
  .objective-table td {
    vertical-align: top;
    overflow: hidden;
  }
  .objective-table td.ppa-deliverables-cell {
    max-width: 0;
  }
  .objective-table td .ppa-quill-wrap {
    max-width: 100%;
    box-sizing: border-box;
  }
  .objective-table td .ppa-quill-wrap .ql-toolbar {
    flex-wrap: wrap;
  }
  .ppa-quill-wrap .ppa-quill-editor .ql-editor {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 14px;
    min-height: 120px;
  }
  .ppa-quill-wrap:has(textarea.is-invalid) .ppa-quill-editor {
    outline: 2px solid #dc3545;
    outline-offset: 1px;
  }
  #midtermApproverPreviewModal .preview-readonly-text.ppa-html-preview,
  #endtermApproverPreviewModal .preview-readonly-text.ppa-html-preview {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 14px;
    white-space: normal;
    word-break: break-word;
  }
  #midtermApproverPreviewModal .preview-readonly-text.ppa-html-preview p,
  #endtermApproverPreviewModal .preview-readonly-text.ppa-html-preview p {
    margin: 0 0 0.5em 0;
  }
  .ppa-html-readonly {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    word-break: break-word;
  }
  .ppa-html-readonly p:last-child { margin-bottom: 0; }
</style>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
(function () {
  var toolbarOptions = [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']];

  function ppaMteQuillOptions(readOnly) {
    if (readOnly) {
      return { theme: 'snow', readOnly: true, modules: {} };
    }
    return { theme: 'snow', readOnly: false, modules: { toolbar: toolbarOptions } };
  }

  window.syncMteSummernoteToTextareas = function () {
    if (typeof jQuery === 'undefined') return;
    jQuery('.ppa-quill-wrap').each(function () {
      var q = jQuery(this).data('ppaQuill');
      var ta = jQuery(this).find('textarea.ppa-summernote')[0];
      if (q && ta) ta.value = q.root.innerHTML;
    });
  };

  /** True if textarea value is empty or only placeholder HTML (Summernote/Quill). */
  window.mteRichTextAreaEmpty = function (textarea) {
    var v = (textarea && textarea.value ? String(textarea.value) : '').trim();
    if (!v) return true;
    var div = document.createElement('div');
    div.innerHTML = v;
    return (div.textContent || '').replace(/\u00a0/g, ' ').trim() === '';
  };

  function destroyQuillWrap($w) {
    var $ta = $w.find('textarea.ppa-summernote').first();
    if (!$ta.length) return;
    $ta.detach();
    $w.after($ta);
    $w.remove();
  }

  window.initMteSummernote = function () {
    if (typeof jQuery === 'undefined' || typeof Quill === 'undefined') return;
    jQuery('.ppa-summernote').each(function () {
      var ta = this;
      var $ta = jQuery(ta);
      if ($ta.closest('.ppa-quill-wrap').length) return;

      var readOnly = !!(ta.readOnly || ta.disabled);
      var initial = $ta.val() || '';

      var $wrap = jQuery('<div class="ppa-quill-wrap"></div>');
      var $editor = jQuery('<div class="ppa-quill-editor border rounded bg-white" style="min-height:140px;"></div>');

      $ta.before($wrap);
      $wrap.append($editor);
      $wrap.append($ta);
      $ta.css({
        position: 'absolute',
        width: '1px',
        height: '1px',
        padding: 0,
        margin: '-1px',
        overflow: 'hidden',
        clip: 'rect(0,0,0,0)',
        whiteSpace: 'nowrap',
        border: 0
      });

      var quill = new Quill($editor[0], ppaMteQuillOptions(readOnly));
      if (initial) {
        quill.root.innerHTML = initial;
      }
      ta.value = quill.root.innerHTML;

      quill.on('text-change', function () {
        ta.value = quill.root.innerHTML;
        jQuery(ta).trigger('change');
      });

      $wrap.data('ppaQuill', quill);
    });
  };

  function bindWhenReady() {
    if (typeof jQuery === 'undefined') {
      setTimeout(bindWhenReady, 50);
      return;
    }
    if (typeof Quill === 'undefined') {
      setTimeout(bindWhenReady, 50);
      return;
    }
    jQuery(function () {
      setTimeout(window.initMteSummernote, 0);
    });
  }
  bindWhenReady();

  <?php if ($wrap_midterm_training): ?>
  (function () {
    function wrapToggle() {
      if (typeof jQuery === 'undefined' || typeof window.toggleMidtermTraining !== 'function') {
        setTimeout(wrapToggle, 50);
        return;
      }
      var _toggle = window.toggleMidtermTraining;
      if (_toggle._mteQuillWrapped) return;
      window.toggleMidtermTraining = function (show) {
        _toggle(show);
        if (show && typeof window.initMteSummernote === 'function') {
          setTimeout(function () {
            jQuery('#midterm-training-section .ppa-quill-wrap').each(function () {
              destroyQuillWrap(jQuery(this));
            });
            window.initMteSummernote();
          }, 80);
        }
      };
      window.toggleMidtermTraining._mteQuillWrapped = true;
    }
    setTimeout(wrapToggle, 0);
  })();
  <?php endif; ?>
})();
</script>
