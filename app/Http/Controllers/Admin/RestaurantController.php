<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRestaurantRequest;
use App\Http\Requests\Admin\UpdateRestaurantRequest;
use App\Models\Restaurant;
use App\Services\Uploads\StoreUploadedPhotoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RestaurantController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Restaurant::class);

        $restaurants = Restaurant::query()
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

        return view('admin.restaurants.index', [
            'restaurants' => $restaurants,
            'search' => $request->string('search')->value(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Restaurant::class);

        return view('admin.restaurants.create');
    }

    public function store(StoreRestaurantRequest $request, StoreUploadedPhotoService $photos): RedirectResponse
    {
        // logo_path is deliberately not #[Fillable] on Restaurant (see its
        // docblock) — Restaurant::create() would silently drop it if
        // passed in the mass-assigned array, so it's set as a direct
        // attribute after create() instead.
        $restaurant = Restaurant::create($request->validated());

        $restaurant->logo_path = $request->hasFile('logo')
            ? $photos->replace(null, $request->file('logo'), false, 'restaurants')
            : null;
        $restaurant->save();

        return redirect()->route('admin.restaurants.show', $restaurant)
            ->with('status', 'Restaurant created.');
    }

    public function show(Restaurant $restaurant): View
    {
        Gate::authorize('view', $restaurant);

        return view('admin.restaurants.show', [
            'restaurant' => $restaurant->load('menuCategories.menuItems'),
            // Only rendered for a Dispatcher/Super Admin viewer (see the
            // view itself) — computed unconditionally here anyway since
            // it's cheap and statusPreviewWeekday() already defaults
            // sanely for every role.
            'previewWeekday' => auth()->user()->statusPreviewWeekday(),
        ]);
    }

    public function edit(Restaurant $restaurant): View
    {
        Gate::authorize('update', $restaurant);

        return view('admin.restaurants.edit', [
            'restaurant' => $restaurant,
        ]);
    }

    public function update(UpdateRestaurantRequest $request, Restaurant $restaurant, StoreUploadedPhotoService $photos): RedirectResponse
    {
        $validated = $request->validated();

        $restaurant->logo_path = $photos->replace(
            $restaurant->logo_path,
            $request->file('logo'),
            $validated['remove_logo'],
            'restaurants',
        );

        unset($validated['remove_logo']);

        $restaurant->update($validated);

        return redirect()->route('admin.restaurants.show', $restaurant)
            ->with('status', 'Restaurant updated.');
    }

    public function destroy(Restaurant $restaurant, StoreUploadedPhotoService $photos): RedirectResponse
    {
        Gate::authorize('delete', $restaurant);

        $photos->delete($restaurant->logo_path);
        // Menu items' own photos are cleaned up by MenuCategoryController/
        // MenuItemController's own destroy() actions in normal use; a
        // whole-restaurant delete relies on the FK cascade for the DB
        // rows, which is fine — orphaned files left behind here are a
        // pre-existing, accepted tradeoff of not walking every item's
        // photo_path before a cascading delete (same simplicity level as
        // the rest of this app's V1 scope).
        $restaurant->delete();

        return redirect()->route('admin.restaurants.index')
            ->with('status', 'Restaurant deleted.');
    }
}
