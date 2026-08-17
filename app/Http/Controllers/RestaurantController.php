<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The public, unauthenticated restaurant listing/menu pages — every
 * Restaurant an admin/dispatcher creates via Admin\RestaurantController
 * shows up here automatically once is_active (their default). No
 * Gate::authorize() calls, same reasoning as OrderTrackingController's own
 * docblock: RestaurantPolicy is for staff CRUD access, this is
 * intentionally reachable by anyone, no login required.
 */
class RestaurantController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim();

        $restaurants = Restaurant::query()
            ->active()
            ->when($search->isNotEmpty(), function ($query) use ($search) {
                $term = $search->value();

                // Matches restaurant name/address, the name of any
                // available item on its menu, or a menu category name —
                // a customer searching "burger" or "drinks" should find
                // every restaurant with one, not just a restaurant
                // literally named that.
                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('address', 'like', "%{$term}%")
                        ->orWhereHas('menuItems', function ($query) use ($term) {
                            $query->available()->where('name', 'like', "%{$term}%");
                        })
                        ->orWhereHas('menuCategories', function ($query) use ($term) {
                            $query->where('name', 'like', "%{$term}%");
                        });
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('restaurants.index', [
            'restaurants' => $restaurants,
            'search' => $search->value(),
        ]);
    }

    public function show(Restaurant $restaurant): View
    {
        // Route model binding resolves by ID regardless of is_active —
        // an inactive restaurant must 404 like it never existed, not
        // silently render (it's still fully visible/editable in the
        // admin, just not on the public site).
        abort_unless($restaurant->is_active, 404);

        return view('restaurants.show', [
            'restaurant' => $restaurant->load('menuCategories.menuItems'),
        ]);
    }
}
