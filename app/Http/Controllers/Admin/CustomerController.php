<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    /**
     * Minimum characters before searching at all — matches the frontend
     * widget's own gate (resources/js/runix/admin-customer-search.js), so
     * a request that somehow arrives under that length (a stale/manual
     * request) gets the same empty result rather than a full-table scan
     * on a one- or two-character wildcard.
     */
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Result cap for the same reason index()'s own listing is paginated —
     * this is an autocomplete dropdown, not a browsable list; if a query
     * matches more than this many customers the dispatcher should narrow
     * it (e.g. add more of the phone number), not scroll a long menu.
     */
    private const RESULT_LIMIT = 8;

    /**
     * Backs the customer search/autocomplete on Admin\OrderController's
     * order-create form (spec: a dispatcher entering a WhatsApp/phone
     * order needs to find an existing customer by name or phone in one
     * request, not scan a plain <select> of every active customer).
     * Same authorization as index() — dispatcher/super_admin only — and
     * the same minimal-fields discipline as the public tracking
     * endpoints: never email/notes/is_active/timestamps, only what's
     * needed to display a match and tell duplicates apart.
     */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return response()->json(['customers' => []]);
        }

        $customers = Customer::query()
            ->where(function ($builder) use ($query) {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->orderByDesc('id')
            ->limit(self::RESULT_LIMIT)
            ->get(['id', 'name', 'phone', 'address']);

        return response()->json([
            // address rides along purely so two customers who share a
            // phone (customers.phone has no unique constraint — see
            // docs) render as visibly distinct rows instead of the
            // dispatcher having to guess which one they meant.
            'customers' => $customers->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
            ])->all(),
        ]);
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'search' => $request->string('search')->value(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Customer::class);

        return view('admin.customers.create');
    }

    /**
     * The exact same validation/creation this always ran — the only
     * addition is the JSON branch, taken when the order-create page's
     * "no customer found" link opens this form in a way that asks for
     * JSON back (see resources/js/runix/admin-customer-search.js) instead
     * of the normal full-page redirect. Nothing about who may create a
     * customer, or how, changes based on which response shape is asked
     * for — StoreCustomerRequest::authorize() runs either way.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse|JsonResponse
    {
        $customer = Customer::create($request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
            ], 201);
        }

        return redirect()->route('admin.customers.show', $customer)
            ->with('status', 'Customer created.');
    }

    public function show(Customer $customer): View
    {
        Gate::authorize('view', $customer);

        return view('admin.customers.show', [
            'customer' => $customer,
        ]);
    }

    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('admin.customers.edit', [
            'customer' => $customer,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('admin.customers.show', $customer)
            ->with('status', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('status', 'Customer deleted.');
    }
}
