<?php

namespace App\Http\Requests\Concerns;

use App\Models\GoogleDriveConnection;
use Illuminate\Validation\Validator;

/**
 * Images are saved to the user's own Google Drive, so a form that carries images needs a connected Drive. Checked
 * during validation so nothing is uploaded when the user still has to connect.
 */
trait RequiresGoogleDriveForImages
{
    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $hasImages = [
                    'photo' => $this->hasFile('photo'),
                    'pedigree_images' => $this->hasFile('pedigree_images'),
                ];

                if (! in_array(true, $hasImages, true)
                    || GoogleDriveConnection::where('user_id', $this->user()?->id ?? 0)->exists()) {
                    return;
                }

                foreach (array_keys(array_filter($hasImages)) as $field) {
                    $validator->errors()->add($field, 'Connect your Google Drive in Settings → Storage to add photos.');
                }
            },
        ];
    }
}
