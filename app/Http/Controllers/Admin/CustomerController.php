<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
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

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

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
