/**
 * Other memo create/edit — AJAX save so validation errors do not reload the page.
 */
(function () {
    'use strict';

    function escHtml(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function syncSummernoteBeforeSubmit(form) {
        if (typeof jQuery === 'undefined') return;
        var root = form.querySelector('#memo-dynamic-fields') || form;
        jQuery(root).find('textarea.summernote').each(function () {
            var $t = jQuery(this);
            if ($t.summernote && $t.next('.note-editor').length) {
                $t.val($t.summernote('code'));
            }
        });
    }

    function errorBoxForForm(form) {
        return document.getElementById(form.id === 'other-memo-edit-form'
            ? 'other-memo-edit-form-errors'
            : 'other-memo-create-form-errors');
    }

    function flattenErrors(data) {
        var list = [];
        if (data && data.errors && typeof data.errors === 'object') {
            Object.keys(data.errors).forEach(function (key) {
                var msgs = data.errors[key];
                if (Array.isArray(msgs)) {
                    msgs.forEach(function (m) {
                        if (m) list.push(String(m));
                    });
                } else if (typeof msgs === 'string' && msgs) {
                    list.push(msgs);
                }
            });
        }
        if (!list.length && data && data.message) {
            list.push(String(data.message));
        }
        return list;
    }

    function showFormErrors(form, list, fieldErrors) {
        var box = errorBoxForForm(form);
        if (!box) return;
        if (!list.length) {
            box.classList.add('d-none');
            box.innerHTML = '';
            return;
        }
        box.classList.remove('d-none');
        box.innerHTML = '<ul class="mb-0">' + list.map(function (m) {
            return '<li>' + escHtml(m) + '</li>';
        }).join('') + '</ul>';
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        var refInvalid = !!(fieldErrors && fieldErrors.referenced_memo_links);
        document.querySelectorAll('.referenced-memo-link-input').forEach(function (inp) {
            inp.classList.toggle('is-invalid', refInvalid);
        });
    }

    function clearFormErrors(form) {
        showFormErrors(form, [], null);
        document.querySelectorAll('.referenced-memo-link-input.is-invalid').forEach(function (inp) {
            inp.classList.remove('is-invalid');
        });
    }

    function notify(msg, type) {
        if (typeof show_notification === 'function') {
            show_notification(msg, type || 'info');
        }
    }

    function submitOtherMemoForm(form) {
        syncSummernoteBeforeSubmit(form);
        clearFormErrors(form);

        var submitBtn = form.querySelector('[type="submit"]:not([formaction])')
            || form.querySelector('button[type="submit"]');
        var originalHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving…';
        }

        var fd = new FormData(form);
        var action = form.getAttribute('action');
        var method = (fd.get('_method') || form.getAttribute('method') || 'POST').toString().toUpperCase();

        fetch(action, {
            method: method === 'GET' ? 'POST' : 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-APM-Ajax-Form': '1'
            }
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, status: response.status, data: data };
                }).catch(function () {
                    return { ok: false, status: response.status, data: { message: 'Unexpected server response.' } };
                });
            })
            .then(function (result) {
                if (result.ok && result.data && result.data.success && result.data.redirect_url) {
                    notify(result.data.message || 'Saved.', 'success');
                    if (typeof Livewire !== 'undefined' && Livewire.navigate) {
                        Livewire.navigate(result.data.redirect_url);
                    } else {
                        window.location.href = result.data.redirect_url;
                    }
                    return;
                }

                var list = flattenErrors(result.data || {});
                if (!list.length) {
                    list.push('Could not save. Please try again.');
                }
                showFormErrors(form, list, result.data && result.data.errors);
                notify(list[0], 'error');
            })
            .catch(function () {
                var msg = 'Network error while saving. Check your connection and try again.';
                showFormErrors(form, [msg], null);
                notify(msg, 'error');
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }
            });
    }

    function bindAjaxOtherMemoFormsOnce() {
        if (window._apmOtherMemoAjaxFormBound) return;
        window._apmOtherMemoAjaxFormBound = true;

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || form.tagName !== 'FORM') return;
            if (form.id !== 'other-memo-create-form' && form.id !== 'other-memo-edit-form') return;
            if (e.submitter && e.submitter.getAttribute('formaction')) return;

            e.preventDefault();
            e.stopPropagation();
            submitOtherMemoForm(form);
        }, true);
    }

    document.addEventListener('DOMContentLoaded', bindAjaxOtherMemoFormsOnce);
    document.addEventListener('livewire:navigated', bindAjaxOtherMemoFormsOnce);
})();
