<?php

namespace App\Platform\Auth;

use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores avatars as square WebP images re-encoded server-side.
 *
 * Re-encoding (rather than storing the upload) strips metadata such as EXIF location,
 * normalizes size and neutralizes files that are only pretending to be images.
 */
class Avatars
{
    public function replace(UserProfile $profile, UploadedFile $file): void
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['avatar' => __('validation.image', ['attribute' => 'avatar'])]);
        }

        $size = (int) config('platform.avatars.size');
        $side = min(imagesx($source), imagesy($source));
        $target = imagecreatetruecolor($size, $size);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled(
            $target, $source, 0, 0,
            intdiv(imagesx($source) - $side, 2), intdiv(imagesy($source) - $side, 2),
            $size, $size, $side, $side,
        );

        ob_start();
        imagewebp($target, null, 85);
        $contents = (string) ob_get_clean();

        $path = 'avatars/'.$profile->user_id.'-'.Str::random(16).'.webp';
        Storage::disk('public')->put($path, $contents);

        $this->deleteFile($profile);
        $profile->forceFill(['avatar_path' => $path])->save();
    }

    public function remove(UserProfile $profile): void
    {
        $this->deleteFile($profile);
        $profile->forceFill(['avatar_path' => null])->save();
    }

    private function deleteFile(UserProfile $profile): void
    {
        if ($profile->avatar_path !== null) {
            Storage::disk('public')->delete($profile->avatar_path);
        }
    }
}
