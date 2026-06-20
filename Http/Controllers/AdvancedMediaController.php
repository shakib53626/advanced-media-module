<?php

namespace App\Modules\AdvancedMedia\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdvancedMedia\Repositories\AdvancedMediaRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvancedMediaController extends Controller
{
    public function __construct(private AdvancedMediaRepository $media) {}

    // GET /admin/advanced-media?folder=path
    public function index(Request $request): JsonResponse
    {
        $folder = $request->query('folder');
        $items  = $this->media->list($folder);
        $crumbs = $this->media->breadcrumb($folder);

        return response()->json(['items' => $items, 'breadcrumb' => $crumbs]);
    }

    // POST /admin/advanced-media/folder
    public function createFolder(Request $request): JsonResponse
    {
        $request->validate([
            'name'   => ['required', 'string', 'max:100'],
            'parent' => ['nullable', 'string'],
        ]);

        try {
            $folder = $this->media->createFolder($request->name, $request->parent);
            return response()->json(['item' => $folder], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /admin/advanced-media/upload
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'   => ['required', 'file', 'max:51200'],
            'folder' => ['nullable', 'string'],
        ]);

        $item = $this->media->upload($request->file('file'), $request->folder);

        return response()->json(['item' => $item], 201);
    }

    // PATCH /admin/advanced-media/rename
    public function rename(Request $request): JsonResponse
    {
        $request->validate([
            'path'      => ['required', 'string'],
            'name'      => ['required', 'string', 'max:200'],
            'is_folder' => ['required', 'boolean'],
        ]);

        $item = $this->media->rename($request->path, $request->name, $request->boolean('is_folder'));

        return response()->json(['item' => $item]);
    }

    // DELETE /admin/advanced-media
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'path'      => ['required', 'string'],
            'is_folder' => ['required', 'boolean'],
        ]);

        $this->media->delete($request->path, $request->boolean('is_folder'));

        return response()->json(['message' => 'Deleted successfully.']);
    }

    // PATCH /admin/advanced-media/move
    public function move(Request $request): JsonResponse
    {
        $request->validate([
            'path'          => ['required', 'string'],
            'target_folder' => ['nullable', 'string'],
            'is_folder'     => ['required', 'boolean'],
        ]);

        $item = $this->media->move($request->path, $request->target_folder, $request->boolean('is_folder'));

        return response()->json(['item' => $item]);
    }
}
