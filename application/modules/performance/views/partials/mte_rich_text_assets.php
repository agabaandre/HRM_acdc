<?php
/** Midterm / endterm: Summernote styling + init (same behaviour as annual PPA plan). */
$wrap_midterm_training = !empty($wrap_midterm_training);
?>
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
  .objective-table td.ppa-deliverables-cell .ppa-summernote + .note-editor {
    max-width: 100% !important;
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box;
  }
  .objective-table td.ppa-deliverables-cell .note-toolbar {
    flex-wrap: wrap;
  }
  .objective-table td .ppa-summernote + .note-editor {
    max-width: 100%;
    box-sizing: border-box;
  }
  .objective-table td .ppa-summernote + .note-editor .note-editable {
    min-height: 120px;
  }
  .ppa-summernote + .note-editor .note-editable,
  .ppa-summernote + .note-editor .note-editable * {
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 14px !important;
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
<script>
(function () {
  window.syncMteSummernoteToTextareas = function () {
    if (typeof jQuery === 'undefined') return;
    jQuery('.ppa-summernote').each(function () {
      var $ta = jQuery(this);
      if ($ta.next('.note-editor').length) {
        $ta.val($ta.summernote('code'));
      }
    });
  };

  /** True if textarea value is empty or only Summernote placeholder HTML. */
  window.mteRichTextAreaEmpty = function (textarea) {
    var v = (textarea && textarea.value ? String(textarea.value) : '').trim();
    if (!v) return true;
    var div = document.createElement('div');
    div.innerHTML = v;
    return (div.textContent || '').replace(/\u00a0/g, ' ').trim() === '';
  };

  window.initMteSummernote = function () {
    if (typeof jQuery === 'undefined') return;
    jQuery('.ppa-summernote').each(function () {
      var $ta = jQuery(this);
      if ($ta.next('.note-editor').length) {
        return;
      }
      var readOnly = $ta.prop('readonly') || $ta.prop('disabled');
      $ta.removeAttr('readonly').removeAttr('disabled');
      $ta.summernote({
        placeholder: 'Type here…',
        tabsize: 2,
        height: 170,
        dialogsInBody: true,
        fontNames: ['Arial', 'Arial Black', 'Helvetica', 'sans-serif'],
        fontNamesIgnoreCheck: ['Arial', 'Arial Black', 'Helvetica', 'sans-serif'],
        toolbar: [
          ['style', ['bold', 'italic', 'underline', 'clear']],
          ['para', ['ul', 'ol', 'paragraph']],
          ['insert', ['link']],
          ['view', ['fullscreen', 'codeview']]
        ],
        callbacks: {
          onInit: function () {
            jQuery(this).next('.note-editor').find('.note-editable').css({
              fontFamily: 'Arial, Helvetica, sans-serif',
              fontSize: '14px'
            });
          },
          onChange: function () {
            var $ta = jQuery(this);
            try {
              $ta.val($ta.summernote('code'));
            } catch (e) { /* ignore */ }
            $ta.trigger('change');
          }
        }
      });
      if (readOnly) {
        $ta.summernote('disable');
      }
    });
  };

  function bindWhenReady() {
    if (typeof jQuery === 'undefined') {
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
      if (_toggle._mteSummernoteWrapped) return;
      window.toggleMidtermTraining = function (show) {
        _toggle(show);
        if (show && typeof window.initMteSummernote === 'function') {
          setTimeout(function () {
            jQuery('#midterm-training-section .ppa-summernote').each(function () {
              var $t = jQuery(this);
              if ($t.next('.note-editor').length) {
                try {
                  $t.summernote('destroy');
                } catch (e) { /* ignore */ }
              }
            });
            window.initMteSummernote();
          }, 80);
        }
      };
      window.toggleMidtermTraining._mteSummernoteWrapped = true;
    }
    setTimeout(wrapToggle, 0);
  })();
  <?php endif; ?>
})();
</script>
