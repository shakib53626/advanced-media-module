<?php

namespace App\Modules\AdvancedMedia\Repositories;

use App\Traits\FileHelpers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdvancedMediaRepository
{
    use FileHelpers;

    private string $disk = 'public';

    // ── List folder contents (folders + files) ───────────────────────────────

    public function list(?string $folder = null): array
    {
        $path        = $folder ?? '';
        $directories = Storage::disk($this->disk)->directories($path);
        $files       = Storage::disk($this->disk)->files($path);
        $items       = [];

        foreach ($directories as $dir) {
            $name     = basename($dir);
            $children = count(Storage::disk($this->disk)->allFiles($dir));
            $items[]  = [
                'id'       => base64_encode($dir),
                'type'     => 'folder',
                'name'     => $name,
                'path'     => $dir,
                'parentId' => $folder ? base64_encode($folder) : null,
                'size'     => null,
                'children' => $children,
                'date'     => date('Y-m-d', Storage::disk($this->disk)->lastModified($dir)),
                'url'      => null,
            ];
        }

        foreach ($files as $file) {
            $name = basename($file);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $size = Storage::disk($this->disk)->size($file);
            $type = $this->getFileType($ext);
            $url  = Storage::disk($this->disk)->url($file);

            $items[] = [
                'id'       => base64_encode($file),
                'type'     => $type,
                'name'     => $name,
                'path'     => $file,
                'parentId' => $folder ? base64_encode($folder) : null,
                'size'     => $size,
                'ext'      => $ext,
                'children' => 0,
                'date'     => date('Y-m-d', Storage::disk($this->disk)->lastModified($file)),
                'url'      => $url,
            ];
        }

        return $items;
    }

    // ── Create folder ────────────────────────────────────────────────────────

    public function createFolder(string $name, ?string $parent = null): array
    {
        $name = Str::slug($name, '-');
        $path = $parent ? "{$parent}/{$name}" : $name;

        if (Storage::disk($this->disk)->exists($path)) {
            throw new \RuntimeException("Folder already exists.");
        }

        Storage::disk($this->disk)->makeDirectory($path);

        return [
            'id'       => base64_encode($path),
            'type'     => 'folder',
            'name'     => $name,
            'path'     => $path,
            'parentId' => $parent ? base64_encode($parent) : null,
            'size'     => null,
            'children' => 0,
            'date'     => now()->format('Y-m-d'),
            'url'      => null,
        ];
    }

    // ── Upload file ──────────────────────────────────────────────────────────

    public function upload(UploadedFile $file, ?string $folder = null): array
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext      = strtolower($file->getClientOriginalExtension());
        $name     = Str::slug($original) . '.' . $ext;
        $path     = $folder ? "{$folder}/{$name}" : $name;

        $counter = 1;
        while (Storage::disk($this->disk)->exists($path)) {
            $name    = Str::slug($original) . "-{$counter}.{$ext}";
            $path    = $folder ? "{$folder}/{$name}" : $name;
            $counter++;
        }

        Storage::disk($this->disk)->putFileAs($folder ?? '', $file, $name);

        $url  = Storage::disk($this->disk)->url($path);
        $size = Storage::disk($this->disk)->size($path);
        $type = $this->getFileType($ext);

        return [
            'id'       => base64_encode($path),
            'type'     => $type,
            'name'     => $name,
            'path'     => $path,
            'parentId' => $folder ? base64_encode($folder) : null,
            'size'     => $size,
            'ext'      => $ext,
            'date'     => now()->format('Y-m-d'),
            'url'      => $url,
        ];
    }

    // ── Rename ───────────────────────────────────────────────────────────────

    public function rename(string $path, string $newName, bool $isFolder): array
    {
        $dir     = dirname($path);
        $dir     = $dir === '.' ? '' : $dir;
        $newName = $isFolder
            ? Str::slug($newName, '-')
            : Str::slug(pathinfo($newName, PATHINFO_FILENAME)) . '.' . pathinfo($path, PATHINFO_EXTENSION);
        $newPath = $dir ? "{$dir}/{$newName}" : $newName;

        Storage::disk($this->disk)->move($path, $newPath);

        if ($isFolder) {
            return ['id' => base64_encode($newPath), 'name' => $newName, 'path' => $newPath, 'type' => 'folder'];
        }

        $ext = strtolower(pathinfo($newPath, PATHINFO_EXTENSION));
        $url = Storage::disk($this->disk)->url($newPath);

        return [
            'id'   => base64_encode($newPath),
            'name' => $newName,
            'path' => $newPath,
            'url'  => $url,
            'ext'  => $ext,
            'type' => $this->getFileType($ext),
        ];
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function delete(string $path, bool $isFolder): bool
    {
        if ($isFolder) {
            return Storage::disk($this->disk)->deleteDirectory($path);
        }
        return Storage::disk($this->disk)->delete($path);
    }

    // ── Move ─────────────────────────────────────────────────────────────────

    public function move(string $path, ?string $targetFolder, bool $isFolder): array
    {
        $name    = basename($path);
        $newPath = $targetFolder ? "{$targetFolder}/{$name}" : $name;

        Storage::disk($this->disk)->move($path, $newPath);

        $id = base64_encode($newPath);

        if ($isFolder) {
            return ['id' => $id, 'path' => $newPath, 'type' => 'folder'];
        }

        return ['id' => $id, 'path' => $newPath, 'url' => Storage::disk($this->disk)->url($newPath)];
    }

    // ── Breadcrumb ───────────────────────────────────────────────────────────

    public function breadcrumb(?string $path): array
    {
        if (!$path) return [];

        $parts  = explode('/', $path);
        $crumbs = [];
        $built  = '';

        foreach ($parts as $part) {
            $built    = $built ? "{$built}/{$part}" : $part;
            $crumbs[] = ['name' => $part, 'id' => base64_encode($built), 'path' => $built];
        }

        return $crumbs;
    }
}
