<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

class FileEncryptionService
{
    /**
     * Encrypt and store a file. Returns the stored file path.
     */
    public function encryptAndStore(UploadedFile $file, string $directory, string $disk = 'local'): string
    {
        $contents = file_get_contents($file->getRealPath());
        $encrypted = Crypt::encryptString($contents);

        $filename = bin2hex(random_bytes(16)) . '.' . $file->getClientOriginalExtension();
        $path = public_path('uploads/' . $directory);

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        file_put_contents($path . '/' . $filename, $encrypted);

        return '/uploads/' . $directory . '/' . $filename;
    }

    /**
     * Decrypt a file and return its contents.
     */
    public function decrypt(string $filePath): ?string
    {
        $fullPath = public_path(ltrim($filePath, '/'));

        if (!File::exists($fullPath)) {
            return null;
        }

        $encrypted = file_get_contents($fullPath);

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Delete an encrypted file from disk.
     */
    public function delete(string $filePath): bool
    {
        $fullPath = public_path(ltrim($filePath, '/'));

        if (File::exists($fullPath)) {
            return File::delete($fullPath);
        }

        return false;
    }
}
