/**
 * The customer's shopping cart — client-side only (localStorage), no
 * server-side cart table. A cart holds items from exactly ONE restaurant
 * at a time (an order can only ever have one pickup location); adding an
 * item from a different restaurant asks to clear the current cart first.
 *
 * Read by: the restaurant menu page's "Add to cart" controls, the site
 * header's cart badge, and the checkout page (resources/views/cart/show.blade.php)
 * — all just talk to Alpine.store('cart'), never localStorage directly,
 * so the persistence detail stays in one place.
 */
document.addEventListener('alpine:init', () => {
    const STORAGE_KEY = 'runix-cart';

    const load = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            const parsed = raw ? JSON.parse(raw) : null;

            return {
                restaurantId: parsed?.restaurantId ?? null,
                restaurantName: parsed?.restaurantName ?? null,
                items: parsed?.items ?? {},
            };
        } catch (e) {
            return { restaurantId: null, restaurantName: null, items: {} };
        }
    };

    Alpine.store('cart', {
        restaurantId: null,
        restaurantName: null,
        items: {}, // { [menuItemId]: { name, price, quantity } }

        init() {
            const stored = load();
            this.restaurantId = stored.restaurantId;
            this.restaurantName = stored.restaurantName;
            this.items = stored.items;
        },

        persist() {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                restaurantId: this.restaurantId,
                restaurantName: this.restaurantName,
                items: this.items,
            }));
        },

        get itemCount() {
            return Object.values(this.items).reduce((sum, item) => sum + item.quantity, 0);
        },

        get total() {
            return Object.values(this.items).reduce((sum, item) => sum + item.price * item.quantity, 0);
        },

        get itemsArray() {
            return Object.entries(this.items).map(([id, item]) => ({ id: Number(id), ...item }));
        },

        /**
         * @returns {boolean} false only when adding was declined because
         *   it would have silently wiped a cart from a different
         *   restaurant — the caller can use this to avoid resetting its
         *   own local UI state (e.g. a quantity stepper) on a no-op.
         */
        addItem(restaurantId, restaurantName, menuItemId, name, price) {
            if (this.restaurantId !== null && this.restaurantId !== restaurantId) {
                if (!confirm('Starting an order from a new restaurant will clear your current cart. Continue?')) {
                    return false;
                }
                this.items = {};
            }

            this.restaurantId = restaurantId;
            this.restaurantName = restaurantName;

            const existing = this.items[menuItemId];
            this.items[menuItemId] = {
                name,
                price,
                quantity: (existing?.quantity ?? 0) + 1,
            };

            this.persist();
            return true;
        },

        updateQuantity(menuItemId, quantity) {
            if (quantity <= 0) {
                this.removeItem(menuItemId);
                return;
            }

            if (this.items[menuItemId]) {
                this.items[menuItemId].quantity = quantity;
                this.persist();
            }
        },

        removeItem(menuItemId) {
            delete this.items[menuItemId];

            if (Object.keys(this.items).length === 0) {
                this.restaurantId = null;
                this.restaurantName = null;
            }

            this.persist();
        },

        clear() {
            this.restaurantId = null;
            this.restaurantName = null;
            this.items = {};
            this.persist();
        },
    });
});
