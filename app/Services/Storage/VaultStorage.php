<?php

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private Document Vault abstraction over Laravel Storage.
 *
 * Local development uses private disk storage (`storage/app/private`) with zero
 * public URL exposure. Future cloud object storage (e.g. S3/R2/GCS) integrates
 * strictly by configuring the `vault` disk in `config/filesystems.php` and
 * setting `CRM_VAULT_DISK=vault`, while historical records continue to resolve
 * via their persisted `storage_disk` column.
 */
class VaultStorage
{
    public static function activeDiskName(): string
    {
        $configured = (string) config('filesystems.vault_disk', 'local');
        $disks = (array) config('filesystems.disks', []);

        return array_key_exists($configured, $disks) ? $configured : 'local';
    }

    public static function resolveDiskName(?string $recordDisk = null): string
    {
        $disks = (array) config('filesystems.disks', []);
        if ($recordDisk && array_key_exists($recordDisk, $disks)) {
            return $recordDisk;
        }

        return self::activeDiskName();
    }

    /**
     * @return FilesystemAdapter|Filesystem
     */
    public static function disk(?string $recordDisk = null): Filesystem
    {
        return Storage::disk(self::resolveDiskName($recordDisk));
    }

    /**
     * Store an uploaded file privately on the active vault disk.
     *
     * @return array{disk: string, path: string}
     */
    public static function storeUploaded(UploadedFile $file, string $directory, ?string $filename = null): array
    {
        $diskName = self::activeDiskName();
        $cleanDir = trim(str_replace('\\', '/', $directory), '/');
        if (str_contains($cleanDir, '..') || str_contains($cleanDir, "\0") || ($filename !== null && (str_contains($filename, '..') || str_contains($filename, "\0") || str_contains($filename, '/') || str_contains($filename, '\\')))) {
            throw new RuntimeException('Invalid vault storage target path.');
        }

        $storedPath = $filename !== null
            ? $file->storeAs($cleanDir, $filename, $diskName)
            : $file->store($cleanDir, $diskName);

        if ($storedPath === false || $storedPath === '') {
            throw new RuntimeException('Failed to write uploaded file to private vault storage.');
        }

        return [
            'disk' => $diskName,
            'path' => (string) $storedPath,
        ];
    }

    /**
     * Write raw binary content privately on the active vault disk.
     *
     * @return array{disk: string, path: string}
     */
    public static function put(string $relativePath, string $contents): array
    {
        $diskName = self::activeDiskName();
        $cleanPath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($cleanPath === '' || str_contains($cleanPath, '..') || str_contains($cleanPath, "\0")) {
            throw new RuntimeException('Invalid vault storage file path.');
        }

        $written = Storage::disk($diskName)->put($cleanPath, $contents);
        if ($written === false) {
            throw new RuntimeException('Failed to write file contents to private vault storage.');
        }

        return [
            'disk' => $diskName,
            'path' => $cleanPath,
        ];
    }

    public static function exists(?string $path, ?string $recordDisk = null): bool
    {
        if (! $path || str_contains(str_replace('\\', '/', $path), '..') || str_contains($path, "\0")) {
            return false;
        }

        $primary = self::resolveDiskName($recordDisk);
        if (Storage::disk($primary)->exists($path)) {
            return true;
        }

        if ($primary !== 'local' && Storage::disk('local')->exists($path)) {
            return true;
        }

        return false;
    }

    public static function delete(?string $path, ?string $recordDisk = null): void
    {
        if (! $path || str_contains(str_replace('\\', '/', $path), '..') || str_contains($path, "\0")) {
            return;
        }

        $primary = self::resolveDiskName($recordDisk);
        if (Storage::disk($primary)->exists($path)) {
            Storage::disk($primary)->delete($path);
        } elseif ($primary !== 'local' && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public static function download(string $path, string $downloadName, array $headers = [], ?string $recordDisk = null): StreamedResponse
    {
        if ($path === '' || str_contains(str_replace('\\', '/', $path), '..') || str_contains($path, "\0")) {
            abort(403, 'Invalid private vault path.');
        }

        $primary = self::resolveDiskName($recordDisk);
        $targetDisk = Storage::disk($primary)->exists($path) ? $primary : 'local';

        return Storage::disk($targetDisk)->download(
            $path,
            $downloadName,
            array_merge(['X-Content-Type-Options' => 'nosniff'], $headers)
        );
    }

    public static function response(string $path, string $fileName, array $headers = [], ?string $recordDisk = null): StreamedResponse
    {
        if ($path === '' || str_contains(str_replace('\\', '/', $path), '..') || str_contains($path, "\0")) {
            abort(403, 'Invalid private vault path.');
        }

        $primary = self::resolveDiskName($recordDisk);
        $targetDisk = Storage::disk($primary)->exists($path) ? $primary : 'local';

        return Storage::disk($targetDisk)->response(
            $path,
            $fileName,
            array_merge(['X-Content-Type-Options' => 'nosniff'], $headers)
        );
    }
}
