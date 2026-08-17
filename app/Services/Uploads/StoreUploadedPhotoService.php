<?php

namespace App\Services\Uploads;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The one place a new photo/logo file actually gets written to (or
 * removed from) the `public` disk — shared by RestaurantController
 * (logo) and MenuItemController (photo), so both follow the exact same
 * replace/remove/keep rules instead of two slightly-different copies.
 */
class StoreUploadedPhotoService
{
    /**
     * Resolves the new stored path for a photo/logo field from one call:
     *   - a new file was uploaded: delete the old one (if any), store the
     *     new one, return its path.
     *   - no new file, but $shouldRemove is true: delete the old one (if
     *     any), return null.
     *   - no new file, not removing: leave $currentPath untouched.
     */
    public function replace(?string $currentPath, ?UploadedFile $newFile, bool $shouldRemove, string $directory): ?string
    {
        if ($newFile !== null) {
            if ($currentPath !== null) {
                Storage::disk('public')->delete($currentPath);
            }

            return $newFile->store($directory, 'public');
        }

        if ($shouldRemove && $currentPath !== null) {
            Storage::disk('public')->delete($currentPath);

            return null;
        }

        return $currentPath;
    }

    /**
     * Deletes a stored photo/logo outright — used by destroy() actions
     * (restaurant/category/item delete) where there's no replacement, just
     * cleanup, so replace()'s three-way branching isn't the right shape.
     */
    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
