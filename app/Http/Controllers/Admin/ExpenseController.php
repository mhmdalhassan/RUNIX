<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExpenseRequest;
use App\Models\Expense;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Create + list only, deliberately — expense records are append-only,
 * same reasoning OrderStatusHistory uses for financial/audit records:
 * once entered, a mistake is corrected with a new entry (or a database
 * fix), not a silent edit. No edit/destroy actions exist.
 */
class ExpenseController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Expense::class);

        return view('admin.expenses.index', [
            'expenses' => Expense::with('recordedBy')->latest('date')->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Expense::class);

        return view('admin.expenses.create');
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        Expense::create([
            ...$request->validated(),
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.expenses.index')->with('status', 'Expense recorded.');
    }
}
