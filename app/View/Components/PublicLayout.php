<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Phase 7 — maps <x-public-layout> to layouts/public.blade.php, same
 * pattern as AppLayout/GuestLayout above.
 */
class PublicLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.public');
    }
}
