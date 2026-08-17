<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMenuItemRequest;
use App\Http\Requests\Admin\UpdateMenuItemRequest;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\Uploads\StoreUploadedPhotoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MenuItemController extends Controller
{
    public function create(Request $request, Restaurant $restaurant): View
    {
        Gate::authorize('create', MenuItem::class);

        return view('admin.menu-items.create', [
            'restaurant' => $restaurant,
            'categories' => $restaurant->menuCategories,
            'selectedCategoryId' => $request->integer('menu_category_id') ?: null,
        ]);
    }

    public function store(StoreMenuItemRequest $request, Restaurant $restaurant, StoreUploadedPhotoService $photos): RedirectResponse
    {
        // photo_path is deliberately not #[Fillable] on MenuItem (see its
        // docblock) — create() would silently drop it if passed in the
        // mass-assigned array, so it's set as a direct attribute after
        // create() instead (same as RestaurantController::store()).
        $item = $restaurant->menuItems()->create($request->validated());

        $item->photo_path = $request->hasFile('photo')
            ? $photos->replace(null, $request->file('photo'), false, 'menu-items')
            : null;
        $item->save();

        return redirect()->route('admin.restaurants.show', $restaurant)
            ->with('status', 'Item created.');
    }

    public function edit(MenuItem $menuItem): View
    {
        Gate::authorize('update', $menuItem);

        return view('admin.menu-items.edit', [
            'menuItem' => $menuItem,
            'restaurant' => $menuItem->restaurant,
            'categories' => $menuItem->restaurant->menuCategories,
        ]);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem, StoreUploadedPhotoService $photos): RedirectResponse
    {
        $validated = $request->validated();

        $menuItem->photo_path = $photos->replace(
            $menuItem->photo_path,
            $request->file('photo'),
            $validated['remove_photo'],
            'menu-items',
        );

        unset($validated['remove_photo']);

        $menuItem->update($validated);

        return redirect()->route('admin.restaurants.show', $menuItem->restaurant)
            ->with('status', 'Item updated.');
    }

    public function destroy(MenuItem $menuItem, StoreUploadedPhotoService $photos): RedirectResponse
    {
        Gate::authorize('delete', $menuItem);

        $restaurant = $menuItem->restaurant;

        $photos->delete($menuItem->photo_path);
        $menuItem->delete();

        return redirect()->route('admin.restaurants.show', $restaurant)
            ->with('status', 'Item deleted.');
    }
}
