<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Safe uploads: private disk, random storage name, allow-listed types (validated by callers with fileItemRules). */
class AttachmentStore
{
    public const MIMES = ['jpg', 'jpeg', 'png', 'pdf', 'txt', 'zip', 'docx', 'xlsx'];

    public const MAX_KB = 10240;

    public static function rules(): array
    {
        return ['file', 'max:'.self::MAX_KB, 'mimes:'.implode(',', self::MIMES)];
    }

    /** @param  array<int, UploadedFile>  $files */
    public static function store(Model $model, array $files, ?User $user): void
    {
        foreach ($files as $file) {
            $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
            $path = $file->storeAs('attachments/'.now()->format('Y/m'), Str::random(40).'.'.$ext, 'local');

            $model->attachments()->create([
                'user_id' => $user?->id,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 120, ''),
                'path' => $path,
                'mime' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }
    }
}
