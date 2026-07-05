/**
 * Profile signature field — Vue 3 + Vuetify island with DocuSeal signature-maker.
 * @see https://github.com/docusealco/signature-maker-js
 */
(function () {
  'use strict';

  var SIGNATURE_FONT_URL = 'https://cdn.jsdelivr.net/npm/@fontsource/dancing-script/files/dancing-script-latin-400-normal.woff';
  var SIGNATURE_FONT_SPEC = 'italic 58px SignatureFont';
  var DEFAULT_SIGNATURE_COLOR = '#3B82F6';

  /** Ensure Dancing Script is loaded before typed signature is drawn. */
  function waitForSignatureFont() {
    if (typeof document === 'undefined' || !document.fonts) {
      return Promise.resolve();
    }
    return document.fonts.load(SIGNATURE_FONT_SPEC).catch(function () {
      if (typeof FontFace === 'undefined') {
        return document.fonts.ready;
      }
      return new FontFace('SignatureFont', 'url(' + SIGNATURE_FONT_URL + ')')
        .load()
        .then(function (face) {
          document.fonts.add(face);
        })
        .catch(function () {
          return document.fonts.ready;
        });
    });
  }

  function applyTypeSignature(maker, text, color) {
    if (!maker) {
      return Promise.resolve();
    }
    return waitForSignatureFont().then(function () {
      var textInput = maker.querySelector('[data-target="textInput"]');
      if (textInput && text) {
        textInput.value = text;
        textInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
      var pickColor = color || DEFAULT_SIGNATURE_COLOR;
      var colorRadio = maker.querySelector(
        'input[data-target="colorInput"][value="' + pickColor + '"]'
      );
      if (colorRadio) {
        colorRadio.checked = true;
        colorRadio.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }

  /** DocuSeal returns raw base64; normalize to a data URL for preview and PHP fallback. */
  function normalizeSignatureDataUrl(value) {
    if (!value || typeof value !== 'string') {
      return '';
    }
    var trimmed = value.trim();
    if (trimmed.indexOf('data:image/') === 0) {
      return trimmed;
    }
    return 'data:image/png;base64,' + trimmed.replace(/\s+/g, '');
  }

  function dataUrlToBlob(dataUrl) {
    var normalized = normalizeSignatureDataUrl(dataUrl);
    var parts = normalized.split(',');
    if (parts.length < 2) {
      return null;
    }
    var mimeMatch = parts[0].match(/data:([^;]+);/);
    var mime = mimeMatch ? mimeMatch[1] : 'image/png';
    var bin = atob(parts[1]);
    var len = bin.length;
    var arr = new Uint8Array(len);
    for (var i = 0; i < len; i++) {
      arr[i] = bin.charCodeAt(i);
    }
    return new Blob([arr], { type: mime });
  }

  function attachBlobToFileInput(blob, filename) {
    var input = document.getElementById('profile-signature-file');
    if (!input || !blob) {
      return false;
    }
    try {
      var pngName = filename || 'signature.png';
      if (pngName.slice(-4).toLowerCase() !== '.png') {
        pngName = 'signature.png';
      }
      var file = blob instanceof File
        ? new File([blob], pngName, { type: 'image/png' })
        : new File([blob], pngName, { type: 'image/png' });
      var dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      return true;
    } catch (e) {
      return false;
    }
  }

  /** Re-encode any signature blob as PNG (transparent canvas) for storage. */
  function convertBlobToPng(blob) {
    return new Promise(function (resolve, reject) {
      if (!blob) {
        reject(new Error('No signature blob'));
        return;
      }
      var url = URL.createObjectURL(blob);
      var img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        var canvas = document.createElement('canvas');
        canvas.width = img.naturalWidth || img.width;
        canvas.height = img.naturalHeight || img.height;
        if (!canvas.width || !canvas.height) {
          reject(new Error('Invalid signature dimensions'));
          return;
        }
        var ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0);
        canvas.toBlob(function (pngBlob) {
          if (!pngBlob) {
            reject(new Error('PNG conversion failed'));
            return;
          }
          var reader = new FileReader();
          reader.onload = function () {
            resolve({
              blob: pngBlob,
              dataUrl: typeof reader.result === 'string' ? reader.result : '',
            });
          };
          reader.onerror = reject;
          reader.readAsDataURL(pngBlob);
        }, 'image/png');
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
        reject(new Error('Failed to load signature image'));
      };
      img.src = url;
    });
  }

  async function ensurePngSignature(dataUrl, blob) {
    var source = blob || dataUrlToBlob(dataUrl);
    if (!source) {
      return null;
    }
    if (source.type === 'image/png' && dataUrl && dataUrl.indexOf('data:image/png') === 0) {
      return { blob: source, dataUrl: normalizeSignatureDataUrl(dataUrl) };
    }
    return convertBlobToPng(source);
  }

  function initProfileSignatureApp() {
    waitForSignatureFont();

    var mountEl = document.getElementById('profile-signature-app');
    if (!mountEl || !window.Vue || !window.Vuetify) {
      return;
    }

    var Vue = window.Vue;
    var createApp = Vue.createApp;
    var ref = Vue.ref;
    var computed = Vue.computed;
    var watch = Vue.watch;
    var nextTick = Vue.nextTick;
    var onMounted = Vue.onMounted;
    var createVuetify = window.Vuetify.createVuetify;

    var currentUrl = mountEl.getAttribute('data-current-url') || '';
    var defaultSignatureText = mountEl.getAttribute('data-default-signature-text') || '';
    var makerSessionState = {
      text: defaultSignatureText,
      color: DEFAULT_SIGNATURE_COLOR,
    };

    var vuetify = createVuetify({
      theme: {
        defaultTheme: 'light',
        themes: {
          light: {
            colors: {
              primary: '#119a48',
              secondary: '#0d7a3a',
            },
          },
        },
      },
    });

    createApp({
      compilerOptions: {
        isCustomElement: function (tag) {
          return tag === 'signature-maker';
        },
      },
      setup: function () {
        var dialogOpen = ref(false);
        var makerDraft = ref('');
        var makerDraftBlob = ref(null);
        var pendingDataUrl = ref('');
        var previewDataUrl = ref('');
        var nativeFileName = ref('');
        var applyError = ref('');

        var displayUrl = computed(function () {
          if (previewDataUrl.value) {
            return previewDataUrl.value;
          }
          if (pendingDataUrl.value) {
            return pendingDataUrl.value;
          }
          return currentUrl || '';
        });

        var hasDisplay = computed(function () {
          return displayUrl.value !== '';
        });

        var previewLabel = computed(function () {
          if (previewDataUrl.value || pendingDataUrl.value) {
            return 'New signature (will replace current on save)';
          }
          return 'Current signature';
        });

        function getMakerEl() {
          return document.getElementById('profile-signature-maker');
        }

        function onMakerChange(event) {
          var detail = event && event.detail;
          if (!detail || !detail.base64) {
            makerDraft.value = '';
            makerDraftBlob.value = null;
            return;
          }
          makerDraft.value = normalizeSignatureDataUrl(detail.base64);
          makerDraftBlob.value = detail.blob || null;
        }

        function captureMakerSessionState(maker) {
          if (!maker) {
            return;
          }
          var textInput = maker.querySelector('[data-target="textInput"]');
          if (textInput && textInput.value) {
            makerSessionState.text = textInput.value;
          }
          var checkedColor = maker.querySelector('input[data-target="colorInput"]:checked');
          if (checkedColor && checkedColor.value) {
            makerSessionState.color = checkedColor.value;
          }
        }

        function bindMakerTabPreservation(maker) {
          if (!maker || maker.dataset.tabPreserve === '1') {
            return;
          }
          maker.dataset.tabPreserve = '1';

          var textInput = maker.querySelector('[data-target="textInput"]');
          if (textInput) {
            textInput.addEventListener('input', function () {
              if (textInput.value) {
                makerSessionState.text = textInput.value;
              }
            });
          }

          maker.querySelectorAll('input[data-target="colorInput"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
              if (radio.checked) {
                makerSessionState.color = radio.value;
              }
            });
          });

          [
            { target: 'drawTypeButton', restore: false },
            { target: 'uploadTypeButton', restore: false },
            { target: 'textTypeButton', restore: true },
          ].forEach(function (tab) {
            var btn = maker.querySelector('[data-target="' + tab.target + '"]');
            if (!btn) {
              return;
            }
            btn.addEventListener('click', function () {
              captureMakerSessionState(maker);
            }, true);
            if (tab.restore) {
              btn.addEventListener('click', function () {
                window.setTimeout(function () {
                  applyTypeSignature(
                    maker,
                    makerSessionState.text,
                    makerSessionState.color
                  );
                }, 0);
              });
            }
          });
        }

        function bindMakerListener() {
          nextTick(function () {
            var maker = getMakerEl();
            if (!maker || maker.dataset.bound === '1') {
              return;
            }
            maker.dataset.bound = '1';
            maker.addEventListener('change', onMakerChange);
            maker.addEventListener('save', onMakerChange);
          });
        }

        function setupMakerDefaults() {
          nextTick(function () {
            var attempts = 0;

            function tryApply() {
              var maker = getMakerEl();
              if (!maker) {
                if (attempts++ < 25) {
                  window.setTimeout(tryApply, 80);
                }
                return;
              }

              var textBtn = maker.querySelector('[data-target="textTypeButton"]');
              if (textBtn) {
                textBtn.click();
              }

              bindMakerTabPreservation(maker);

              makerSessionState.text = defaultSignatureText || makerSessionState.text;
              makerSessionState.color = DEFAULT_SIGNATURE_COLOR;

              applyTypeSignature(maker, makerSessionState.text, makerSessionState.color);
            }

            window.setTimeout(tryApply, 120);
          });
        }

        function pullLatestFromMaker() {
          return new Promise(function (resolve) {
            var maker = getMakerEl();
            if (!maker) {
              resolve(null);
              return;
            }

            var settled = false;
            function finish(detail) {
              if (settled) {
                return;
              }
              settled = true;
              maker.removeEventListener('change', onOnce);
              resolve(detail || null);
            }

            function onOnce(e) {
              if (e.detail && e.detail.base64) {
                finish(e.detail);
              }
            }

            maker.addEventListener('change', onOnce);

            var textInput = maker.querySelector('[data-target="textInput"]');
            if (textInput && textInput.value) {
              textInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            window.setTimeout(function () {
              if (makerDraft.value) {
                finish({ base64: makerDraft.value, blob: makerDraftBlob.value });
                return;
              }
              finish(null);
            }, 600);
          });
        }

        watch(dialogOpen, function (open) {
          if (open) {
            applyError.value = '';
            makerDraft.value = '';
            makerDraftBlob.value = null;
            makerSessionState.text = defaultSignatureText;
            makerSessionState.color = DEFAULT_SIGNATURE_COLOR;
            bindMakerListener();
            setupMakerDefaults();
          }
        });

        function triggerFilePicker() {
          var nativeFile = document.getElementById('profile-signature-file');
          if (nativeFile) {
            nativeFile.click();
          }
        }

        function onNativeFileChange(event) {
          var input = event.target;
          var file = input && input.files && input.files[0];
          if (file) {
            pendingDataUrl.value = '';
            previewDataUrl.value = '';
            makerDraft.value = '';
            makerDraftBlob.value = null;
            nativeFileName.value = file.name;
            applyError.value = '';

            var reader = new FileReader();
            reader.onload = function () {
              if (typeof reader.result === 'string') {
                previewDataUrl.value = reader.result;
              }
            };
            reader.readAsDataURL(file);
          } else {
            nativeFileName.value = '';
            if (!pendingDataUrl.value) {
              previewDataUrl.value = '';
            }
          }
        }

        async function applyDrawnSignature() {
          applyError.value = '';
          var dataUrl = makerDraft.value;
          var blob = makerDraftBlob.value;

          if (!dataUrl) {
            var pulled = await pullLatestFromMaker();
            if (pulled && pulled.base64) {
              dataUrl = normalizeSignatureDataUrl(pulled.base64);
              blob = pulled.blob || null;
            }
          }

          if (!dataUrl && !blob) {
            applyError.value = 'Please draw, type, or upload a signature first.';
            return;
          }

          try {
            var png = await ensurePngSignature(dataUrl, blob);
            if (!png || !png.dataUrl || !png.blob) {
              applyError.value = 'Could not convert signature to PNG. Please try again.';
              return;
            }
            pendingDataUrl.value = png.dataUrl;
            previewDataUrl.value = png.dataUrl;
            attachBlobToFileInput(png.blob, 'signature.png');
            nativeFileName.value = 'signature.png';
            dialogOpen.value = false;
          } catch (e) {
            applyError.value = 'Could not convert signature to PNG. Please try again.';
          }
        }

        function clearPendingDrawn() {
          pendingDataUrl.value = '';
          previewDataUrl.value = '';
          makerDraft.value = '';
          makerDraftBlob.value = null;
          nativeFileName.value = '';
          applyError.value = '';
          var nativeFile = document.getElementById('profile-signature-file');
          if (nativeFile) {
            nativeFile.value = '';
          }
        }

        onMounted(bindMakerListener);

        return {
          dialogOpen: dialogOpen,
          pendingDataUrl: pendingDataUrl,
          previewDataUrl: previewDataUrl,
          nativeFileName: nativeFileName,
          displayUrl: displayUrl,
          hasDisplay: hasDisplay,
          previewLabel: previewLabel,
          applyError: applyError,
          onNativeFileChange: onNativeFileChange,
          applyDrawnSignature: applyDrawnSignature,
          clearPendingDrawn: clearPendingDrawn,
          triggerFilePicker: triggerFilePicker,
        };
      },
      template:
        '<v-app class="profile-signature-v-app">' +
          '<v-card variant="outlined" rounded="lg" class="pa-3 bg-white">' +
            '<div class="text-subtitle-2 font-weight-bold mb-2 d-flex align-center">' +
              '<v-icon icon="mdi-draw-pen" size="small" color="primary" class="me-2"></v-icon>' +
              'Signature' +
            '</div>' +

            '<v-sheet' +
              ' v-if="hasDisplay"' +
              ' class="profile-signature-preview mb-3 pa-2 rounded-lg"' +
              ' border' +
            '>' +
              '<div class="text-caption text-medium-emphasis mb-1">{{ previewLabel }}</div>' +
              '<v-img :src="displayUrl" max-height="72" contain alt="Signature preview"></v-img>' +
            '</v-sheet>' +

            '<v-alert v-if="!hasDisplay" type="info" variant="tonal" density="compact" class="mb-3 text-caption">' +
              'No signature on file yet. Upload an image or create one below.' +
            '</v-alert>' +

            '<v-btn' +
              ' variant="outlined"' +
              ' color="primary"' +
              ' block' +
              ' size="small"' +
              ' prepend-icon="mdi-file-image-outline"' +
              ' class="mb-2 profile-signature-action-btn"' +
              ' @click="triggerFilePicker"' +
            '>' +
              'Upload image' +
            '</v-btn>' +

            '<v-chip' +
              ' v-if="nativeFileName"' +
              ' class="mb-2"' +
              ' size="small"' +
              ' closable' +
              ' @click:close="clearPendingDrawn"' +
            '>' +
              ' {{ nativeFileName }}' +
            '</v-chip>' +

            '<input' +
              ' type="file"' +
              ' name="signature"' +
              ' id="profile-signature-file"' +
              ' accept="image/*"' +
              ' class="d-none"' +
              ' @change="onNativeFileChange"' +
            '>' +

            '<input type="hidden" name="signature_data_url" :value="pendingDataUrl">' +

            '<v-btn' +
              ' color="primary"' +
              ' variant="tonal"' +
              ' block' +
              ' size="small"' +
              ' prepend-icon="mdi-signature-freehand"' +
              ' class="mb-2 profile-signature-action-btn"' +
              ' @click="dialogOpen = true"' +
            '>' +
              'Draw or type' +
            '</v-btn>' +

            '<v-btn' +
              ' v-if="pendingDataUrl || previewDataUrl"' +
              ' variant="text"' +
              ' size="small"' +
              ' color="secondary"' +
              ' block' +
              ' @click="clearPendingDrawn"' +
            '>' +
              ' Clear new signature' +
            '</v-btn>' +

            '<p class="text-caption text-medium-emphasis mb-0 mt-2">' +
              'Max 1MB. PNG with transparent background recommended.' +
            '</p>' +
          '</v-card>' +

          '<v-dialog v-model="dialogOpen" max-width="620" scrollable persistent>' +
            '<v-card rounded="lg">' +
              '<v-card-title class="d-flex align-center py-3">' +
                '<v-icon icon="mdi-signature-freehand" color="primary" class="me-2"></v-icon>' +
                'Create your signature' +
                '<v-spacer></v-spacer>' +
                '<v-btn icon="mdi-close" variant="text" @click="dialogOpen = false" aria-label="Close"></v-btn>' +
              '</v-card-title>' +
              '<v-divider></v-divider>' +
              '<v-card-text class="pa-4 pt-3">' +
                '<p class="text-body-2 text-medium-emphasis mb-3">' +
                  'Draw, type, or upload a signature. Click <strong>Use this signature</strong> when ready.' +
                '</p>' +
                '<signature-maker' +
                  ' id="profile-signature-maker"' +
                  ' data-with-submit="false"' +
                  ' data-with-drawn="true"' +
                  ' data-with-typed="true"' +
                  ' data-with-upload="true"' +
                  ' data-with-color-select="true"' +
                  ' data-font-url="' + SIGNATURE_FONT_URL + '"' +
                  ' data-draw-type-button-text="Draw"' +
                  ' data-text-type-button-text="Type"' +
                  ' data-upload-type-button-text="Upload"' +
                  ' data-text-input-placeholder="Type your signature"' +
                  ' data-type-buttons-container-style="display:flex;flex-wrap:wrap;gap:0.5rem;width:100%;margin-bottom:1rem;"' +
                  ' data-draw-type-button-style="font-size:0.8125rem;padding:0.45rem 0.85rem;white-space:nowrap;"' +
                  ' data-text-type-button-style="font-size:0.8125rem;padding:0.45rem 0.85rem;white-space:nowrap;"' +
                  ' data-upload-type-button-style="font-size:0.8125rem;padding:0.45rem 0.85rem;white-space:nowrap;"' +
                  ' data-canvas-class="profile-signature-canvas bg-white border rounded-lg w-100"' +
                  ' data-canvas-style="display:block;width:100%;height:110px;max-height:110px;border-radius:8px;background-color:#ffffff;border:1px solid #D3D3D3;"' +
                '></signature-maker>' +
                '<v-alert v-if="applyError" type="warning" variant="tonal" density="compact" class="mt-3 mb-0">' +
                  ' {{ applyError }}' +
                '</v-alert>' +
              '</v-card-text>' +
              '<v-divider></v-divider>' +
              '<v-card-actions class="pa-4">' +
                '<v-spacer></v-spacer>' +
                '<v-btn variant="text" @click="dialogOpen = false">Cancel</v-btn>' +
                '<v-btn color="primary" variant="flat" prepend-icon="mdi-check" @click="applyDrawnSignature">' +
                  'Use this signature' +
                '</v-btn>' +
              '</v-card-actions>' +
            '</v-card>' +
          '</v-dialog>' +
        '</v-app>',
    })
      .use(vuetify)
      .mount(mountEl);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProfileSignatureApp);
  } else {
    initProfileSignatureApp();
  }
})();
