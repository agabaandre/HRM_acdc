<?php
/** Annual PPA (plan / view_ppa): Quill 2 for `.ppa-summernote` fields (aligned with weekly brief + midterm/endterm). */
$wrap_ppa_training_toggle = !empty($wrap_ppa_training_toggle);
?>
<?php /* Quill snow CSS: templates/partials/css_files.php */ ?>
<style>
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
  #ppaApproverPreviewModal .preview-readonly-text.ppa-html-preview {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 14px;
    white-space: normal;
    word-break: break-word;
  }
  #ppaApproverPreviewModal .preview-readonly-text.ppa-html-preview p {
    margin: 0 0 0.5em 0;
  }
</style>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
(function () {
  var toolbarOptions = [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']];

  function ppaPlanQuillOptions(readOnly) {
    if (readOnly) {
      return { theme: 'snow', readOnly: true, modules: {} };
    }
    return { theme: 'snow', readOnly: false, modules: { toolbar: toolbarOptions } };
  }

  window.syncPpaPlanRichTextToTextareas = function () {
    if (typeof jQuery === 'undefined') return;
    jQuery('.ppa-quill-wrap').each(function () {
      var q = jQuery(this).data('ppaQuill');
      var ta = jQuery(this).find('textarea.ppa-summernote')[0];
      if (q && ta) ta.value = q.root.innerHTML;
    });
  };

  function destroyQuillWrap($w) {
    var $ta = $w.find('textarea.ppa-summernote').first();
    if (!$ta.length) return;
    $ta.detach();
    $w.after($ta);
    $w.remove();
  }

  function initOneTa(ta) {
    if (typeof jQuery === 'undefined' || typeof Quill === 'undefined') return;
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

    if ($editor[0].__quill) return;

    var quill = new Quill($editor[0], ppaPlanQuillOptions(readOnly));
    if (initial) {
      quill.root.innerHTML = initial;
    }
    ta.value = quill.root.innerHTML;

    quill.on('text-change', function () {
      ta.value = quill.root.innerHTML;
      jQuery(ta).trigger('change');
    });

    $wrap.data('ppaQuill', quill);
  }

  window.initPpaPlanQuill = function () {
    if (typeof jQuery === 'undefined' || typeof Quill === 'undefined') return;
    jQuery('.ppa-summernote').each(function () {
      initOneTa(this);
    });
  };

  window.initPpaSummernote = window.initPpaPlanQuill;

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
      setTimeout(window.initPpaPlanQuill, 0);
      jQuery('#staff_ppa').on('submit', function () {
        window.syncPpaPlanRichTextToTextareas();
      });
    });
  }
  bindWhenReady();

  <?php if ($wrap_ppa_training_toggle): ?>
  (function () {
    function wrapToggle() {
      if (typeof jQuery === 'undefined' || typeof window.toggleTrainingSection !== 'function') {
        setTimeout(wrapToggle, 50);
        return;
      }
      var _toggle = window.toggleTrainingSection;
      if (_toggle._ppaPlanQuillWrapped) return;
      window.toggleTrainingSection = function (show) {
        _toggle(show);
        if (show && typeof window.initPpaPlanQuill === 'function') {
          setTimeout(function () {
            jQuery('#training-section .ppa-quill-wrap').each(function () {
              destroyQuillWrap(jQuery(this));
            });
            window.initPpaPlanQuill();
          }, 80);
        }
      };
      window.toggleTrainingSection._ppaPlanQuillWrapped = true;
    }
    setTimeout(wrapToggle, 0);
  })();
  <?php endif; ?>
})();
</script>
