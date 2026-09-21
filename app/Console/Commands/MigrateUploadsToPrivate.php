<?php

namespace App\Console\Commands;

use App\Models\DocumentFile;
use App\Models\Renewal;
use App\Services\FileEncryptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateUploadsToPrivate extends Command
{
    protected $signature = 'storage:migrate-uploads {--dry-run : List legacy files without moving them}';
    protected $description = 'Move legacy public/uploads document & renewal files into private storage';

    public function handle(FileEncryptionService $files): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $missing = 0;

        $move = function (string $legacyPath, callable $persist) use ($files, $dryRun, &$moved, &$missing): void {
            $fullPath = public_path(ltrim($legacyPath, '/'));

            if (!is_file($fullPath)) {
                $missing++;
                $this->warn("Missing file on disk: {$legacyPath}");
                return;
            }

            if ($dryRun) {
                $moved++;
                $this->line("Would move: {$legacyPath}");
                return;
            }

            $contents = @file_get_contents($fullPath);

            if ($contents === false) {
                $missing++;
                $this->warn("Unreadable file: {$legacyPath}");
                return;
            }

            $directory = str_contains($legacyPath, 'renewals') ? 'renewals' : 'documents';
            $filename = bin2hex(random_bytes(16)) . '.' . pathinfo($fullPath, PATHINFO_EXTENSION);
            $newPath = trim($directory, '/') . '/' . $filename;

            Storage::disk(FileEncryptionService::DISK)->put($newPath, $contents);
            $persist($newPath);

            if (Storage::disk(FileEncryptionService::DISK)->exists($newPath)) {
                @unlink($fullPath);
                $moved++;
            } else {
                $this->error("Failed to store: {$legacyPath}");
            }
        };

        foreach (DocumentFile::where('file_path', 'like', '/uploads/%')->cursor() as $docFile) {
            $move($docFile->file_path, fn(string $new) => $docFile->update(['file_path' => $new]));
        }

        foreach (Renewal::where('document_file_path', 'like', '/uploads/%')->cursor() as $renewal) {
            $move($renewal->document_file_path, fn(string $new) => $renewal->update(['document_file_path' => $new]));
        }

        $this->info($dryRun
            ? "Dry run: {$moved} file(s) would move, {$missing} missing."
            : "Done: {$moved} file(s) moved to private storage, {$missing} missing.");

        return Command::SUCCESS;
    }
}
