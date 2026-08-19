import Alpine from 'alpinejs';

/**
 * Search-and-select widget for Admin\OrderController::create()'s Customer
 * field — replaces what used to be a single <select> listing every
 * active customer (unusable past a couple hundred rows, no way to jump
 * straight to a phone number). Talks to GET /admin/customers/search
 * (Admin\CustomerController::search — dispatcher/super_admin only,
 * id/name/phone/address only) and, when nothing matches, can create a
 * customer inline through the exact same POST /admin/customers
 * (Admin\CustomerController::store) the full Customers page uses — same
 * validation, same authorization, just asked to answer in JSON instead
 * of redirecting.
 *
 * Usage:
 *   <div x-data="customerSearch(@js($selectedCustomer), @js(['genericError' => __('...')]))">
 *
 * $selectedCustomer is either null or {id, name, phone} — set when the
 * form re-renders after a validation failure elsewhere on the page, so
 * an already-made customer choice isn't lost. The second argument carries
 * the one JS-generated (not Blade-rendered) user-facing string, translated
 * server-side and handed in — same reason available-order-card.blade.php
 * passes its "Taken by :name" phrase via a data attribute instead of
 * hardcoding English in the JS module.
 */
Alpine.data('customerSearch', (selected = null, i18n = {}) => ({
    query: selected ? `${selected.name} (${selected.phone})` : '',
    results: [],
    loading: false,
    error: false,
    selectedId: selected?.id ?? null,
    open: false,
    searchToken: 0,

    showCreateForm: false,
    newCustomerName: '',
    newCustomerPhone: '',
    creating: false,
    createErrors: {},

    csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content;
    },

    search() {
        this.selectedId = null;
        this.open = true;
        this.showCreateForm = false;

        const trimmed = this.query.trim();

        if (trimmed.length < 2) {
            this.results = [];
            this.loading = false;
            this.error = false;
            return;
        }

        // Each call gets its own token so a slow earlier response can
        // never overwrite a newer one that already landed (typing fast
        // fires several overlapping requests; only the latest matters).
        const token = ++this.searchToken;
        this.loading = true;
        this.error = false;

        fetch(`/admin/customers/search?q=${encodeURIComponent(trimmed)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        })
            .then((response) => (response.ok ? response.json() : Promise.reject()))
            .then((data) => {
                if (token !== this.searchToken) {
                    return;
                }

                this.results = data.customers ?? [];
                this.loading = false;
            })
            .catch(() => {
                if (token !== this.searchToken) {
                    return;
                }

                this.error = true;
                this.loading = false;
                this.results = [];
            });
    },

    select(customer) {
        this.selectedId = customer.id;
        this.query = `${customer.name} (${customer.phone})`;
        this.results = [];
        this.open = false;
        this.showCreateForm = false;
    },

    clear() {
        this.selectedId = null;
        this.query = '';
        this.results = [];
        this.open = true;
        this.showCreateForm = false;
        this.$refs.input?.focus();
    },

    startCreating() {
        // A light guess so the dispatcher doesn't retype what they just
        // searched: an all-digits/+/-/space query is almost certainly the
        // phone they were given over WhatsApp, anything else a name.
        const trimmed = this.query.trim();
        const looksLikePhone = /^[0-9+\-\s]+$/.test(trimmed);

        this.newCustomerName = looksLikePhone ? '' : trimmed;
        this.newCustomerPhone = looksLikePhone ? trimmed : '';
        this.createErrors = {};
        this.showCreateForm = true;
    },

    createCustomer() {
        if (this.creating) {
            return;
        }

        this.creating = true;
        this.createErrors = {};

        fetch('/admin/customers', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken(),
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                name: this.newCustomerName,
                phone: this.newCustomerPhone,
            }),
        })
            .then(async (response) => {
                if (response.status === 422) {
                    const body = await response.json();
                    this.createErrors = body.errors ?? {};
                    return null;
                }

                if (!response.ok) {
                    throw new Error('create failed');
                }

                return response.json();
            })
            .then((customer) => {
                if (customer) {
                    this.select(customer);
                }
            })
            .catch(() => {
                this.createErrors = { name: [i18n.genericError ?? 'Something went wrong — try again.'] };
            })
            .finally(() => {
                this.creating = false;
            });
    },
}));
