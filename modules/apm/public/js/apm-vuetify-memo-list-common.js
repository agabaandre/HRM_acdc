/**
 * Shared helpers for APM memo/document Vuetify list pages.
 */
(function () {
    'use strict';

    window.ApmVuetifyMemoList = {
        createVuetify() {
            const { createVuetify } = Vuetify;
            return createVuetify({
                theme: {
                    defaultTheme: 'apmLight',
                    themes: {
                        apmLight: {
                            dark: false,
                            colors: {
                                primary: '#119a48',
                                secondary: '#64748b',
                                success: '#119a48',
                                error: '#dc3545',
                                info: '#0ea5e9',
                                warning: '#f59e0b',
                                surface: '#ffffff',
                                background: '#f8fafc',
                            },
                        },
                    },
                },
                defaults: {
                    VCard: { rounded: 'lg', elevation: 2 },
                    VBtn: { rounded: 'lg' },
                    VTextField: { variant: 'outlined', density: 'comfortable', hideDetails: true },
                    VSelect: { variant: 'outlined', density: 'comfortable', hideDetails: true },
                    VAutocomplete: { variant: 'outlined', density: 'comfortable', hideDetails: true },
                },
            });
        },

        statusColor(status) {
            const s = String(status || '').toLowerCase();
            if (s === 'approved') return 'success';
            if (s === 'pending' || s === 'submitted') return 'warning';
            if (s === 'cancelled' || s === 'rejected') return 'error';
            if (s === 'returned') return 'info';
            if (s === 'archived') return 'secondary';
            return 'secondary';
        },

        submitHiddenForm(action, method, csrf) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = action;
            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = csrf;
            form.appendChild(token);
            if (method && method !== 'POST') {
                const m = document.createElement('input');
                m.type = 'hidden';
                m.name = '_method';
                m.value = method;
                form.appendChild(m);
            }
            document.body.appendChild(form);
            form.submit();
        },

        buildExportUrl(baseUrl, filters, extraKeys = []) {
            const url = new URL(baseUrl, window.location.origin);
            const keys = ['year', 'division_id', 'staff_id', 'status', 'document_number', 'search', 'fund_type_id', ...extraKeys];
            keys.forEach((k) => {
                if (filters[k]) url.searchParams.set(k, filters[k]);
            });
            return url.toString();
        },

        actionsTemplate: `
          <template #item.actions="{ item }">
            <div class="d-flex flex-wrap gap-1 justify-end">
              <v-btn size="small" variant="outlined" color="info" prepend-icon="mdi-eye" :href="item.show_url">Open</v-btn>
              <v-btn v-if="item.copy_url" size="small" variant="outlined" color="secondary" prepend-icon="mdi-content-copy" :href="item.copy_url">Copy</v-btn>
              <v-btn v-if="item.edit_url" size="small" variant="outlined" color="warning" prepend-icon="mdi-pencil" :href="item.edit_url">Edit</v-btn>
              <v-btn v-if="item.delete_url" size="small" variant="outlined" color="error" prepend-icon="mdi-delete" @click="confirmDelete(item)">Delete</v-btn>
              <v-btn v-if="item.print_url" size="small" variant="outlined" color="success" prepend-icon="mdi-printer" :href="item.print_url" target="_blank">Print</v-btn>
            </div>
          </template>
        `,
    };
})();
