<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMenuCategoryRequest;
use App\Http\Requests\Admin\UpdateMenuCategoryRequest;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use App\Services\Uploads\StoreUploadedPhotoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MenuCategoryController extends Controller
{
    public function create(Restaurant $restaurant): View
    {
        Gate::authorize('create', [MenuCategory::class, $restaurant]);

        return view('admin.menu-categories.create', [
            'restaurant' => $restaurant,
        ]);
    }

    public function store(StoreMenuCategoryRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $restaurant->menuCategories()->create($request->validated());

        return redirect()->route('admin.restaurants.show', $restaurant)
            ->with('status', 'Category created.');
    }

    public function edit(MenuCategory $menuCategory): View
    {
        Gate::authorize('update', $menuCategory);

        return view('admin.menu-categories.edit', [
            'menuCategory' => $menuCategory,
            'restaurant' => $menuCategory->restaurant,
        ]);
    }

    public function update(UpdateMenuCategoryRequest $request, MenuCategory $menuCategory): RedirectResponse
    {
        $menuCategory->update($request->validated());

        return redirect()->route('admin.restaurants.show', $menuCategory->restaurant)
            ->with('status', 'Category updated.');
    }

    /**
     * Deleting a category cascades to its items at the DB level (the
     * migration's FK) — their photo files on disk don't clean up on
     * their own, so those are deleted here first, before the cascade
     * fires, while the item rows (and their photo_path values) still
     * exist to read.
     */
    public function destroy(MenuCategory $menuCategory, StoreUploadedPhotoService $photos): RedirectResponse
    {
        Gate::authorize('delete', $menuCategory);

        $restaurant = $menuCategory->restaurant;

        foreach ($menuCategory->menuItems as $item) {
            $photos->delete($item->photo_path);
        }

        $menuCategory->delete();

        return redirect()->route('admin.restaurants.show', $restaurant)
            ->with('status', 'Category deleted.');
    }
}
