<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class FileEncryptionService
{
    /**
     * The private disk. Never store these files under public_path().
     */
    public const DISK = 'local';

    /**
     * Encrypt and store a file on private storage.
     * Returns the disk-relative path (e.g. "documents/ab12....pdf").
     */
    public function encryptAndStore(UploadedFile $file, string $directory, string $disk = self::DISK): string
    {
        $contents = file_get_contents($file->getRealPath());
        $encrypted = Crypt::encryptString($contents);

        $filename = bin2hex(random_bytes(16)) . '.' . $file->getClientOriginalExtension();
        $path = trim($directory, '/') . '/' . $filename;

        Storage::disk($disk)->put($path, $encrypted);

        return $path;
    }

    /**
     * Decrypt a file and return its contents.
     * Supports legacy "/uploads/..." paths (public disk era) so existing
     * rows keep working until "php artisan storage:migrate-uploads" is run.
     */
    public function decrypt(string $filePath): ?string
    {
        $encrypted = $this->readRaw($filePath);

        if ($encrypted === null) {
            return null;
        }

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
        if ($this->isLegacyPath($filePath)) {
            $fullPath = public_path(ltrim($filePath, '/'));

            if (is_file($fullPath)) {
                return @unlink($fullPath);
            }

            return false;
        }

        $disk = Storage::disk(self::DISK);

        if ($disk->exists($filePath)) {
            return $disk->delete($filePath);
        }

        return false;
    }

    /**
     * Check whether the stored file exists (new or legacy location).
     */
    public function exists(string $filePath): bool
    {
        return $this->readRaw($filePath) !== null;
    }

    /**
     * Read the raw (still encrypted) bytes, or null when missing.
     */
    private function readRaw(string $filePath): ?string
    {
        if ($this->isLegacyPath($filePath)) {
            $fullPath = public_path(ltrim($filePath, '/'));

            if (!is_file($fullPath)) {
                return null;
            }

            $contents = @file_get_contents($fullPath);

            return $contents === false ? null : $contents;
        }

        $disk = Storage::disk(self::DISK);

        if (!$disk->exists($filePath)) {
            return null;
        }

        $contents = $disk->get($filePath);

        return $contents === null ? null : (string) $contents;
    }

    private function isLegacyPath(string $filePath): bool
    {
        return str_starts_with(ltrim($filePath, '/'), 'uploads/');
    }
}
