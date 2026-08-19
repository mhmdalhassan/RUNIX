import Alpine from 'alpinejs';

/**
 * UX-only guard against duplicate submissions (a double-tap on mobile, a
 * slow connection tempting a second click) on the app's important
 * once-only actions — driver accept/reject/claim/release, admin order
 * creation, customer checkout. Disables every submit control on the form
 * the instant it's submitted, so a second click/Enter can't fire a
 * second request while the first is still in flight or the page is
 * navigating away.
 *
 * This is NOT what actually prevents a duplicate order/claim/accept from
 * landing twice — the backend's own atomic guarantees (
 * ClaimOrderForDriverService's conditional UPDATEs, OrderTransitionService,
 * server-side validation) remain authoritative regardless of what the
 * browser does. This only stops the browser from bothering to try twice.
 *
 * Opt-in per form, not global: a form is only ever affected if it's
 * explicitly given `x-data="preventDoubleSubmit" @submit="onSubmit"` —
 * search/filter forms, GET forms, and anything else that legitimately
 * submits more than once are simply never touched.
 *
 * Usage:
 *   <form x-data="preventDoubleSubmit" @submit="onSubmit" method="POST" ...>
 *       @csrf
 *       <x-button type="submit">...</x-button>
 *   </form>
 */
Alpine.data('preventDoubleSubmit', () => ({
    submitting: false,

    onSubmit() {
        // A second `submit` event on an already-submitting form (e.g. a
        // stray Enter keypress landing right after a click) is a no-op —
        // the first submission already owns whatever happens next.
        if (this.submitting) {
            return;
        }

        this.submitting = true;

        this.$el
            .querySelectorAll('button[type="submit"], input[type="submit"]')
            .forEach((control) => {
                control.disabled = true;
            });
    },
}));
