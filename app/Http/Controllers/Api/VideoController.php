<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        $query = Video::with('category')
            ->orderBy('created_at', 'desc');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 24), 48);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:videos,slug',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'duration_seconds' => 'nullable|integer|min:0',
            'video' => 'nullable|file|mimes:mp4,mov,avi,wmv,webm|max:512000', // max 512MB
            'thumbnail' => 'nullable|image|max:10240', // 10MB
            'thumbnail_url' => 'nullable|url|max:500',
            'thumbnail_path' => 'nullable|string|max:500', // Server-extracted thumbnail
            'mediafire_url' => 'nullable|url|max:500',
        ]);

        // Either video file or mediafire_url is required
        if (!$request->hasFile('video') && empty($validated['mediafire_url'])) {
            return response()->json([
                'message' => 'Either a video file or MediaFire URL is required.',
                'errors' => ['video' => ['Either a video file or MediaFire URL is required.']]
            ], 422);
        }

        if ($request->hasFile('video')) {
            $file = $request->file('video');
            $path = $file->store('videos', 'public');
            $validated['file_path'] = $path;
        }

        // Thumbnail priority: uploaded file > server-extracted path > URL
        if ($request->hasFile('thumbnail')) {
            $thumbPath = $request->file('thumbnail')->store('videos/thumbnails', 'public');
            $validated['thumbnail_path'] = $thumbPath;
        }
        // If thumbnail_path is passed directly (server-extracted), it's already in $validated

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }
        unset($validated['video'], $validated['thumbnail']);
        $video = Video::create($validated);
        $video->load('category');
        return response()->json($video, 201);
    }

    public function show(Video $video)
    {
        return $video->load('category');
    }

    public function showBySlug(string $slug)
    {
        $video = Video::where('slug', $slug)->with('category')->first();
        if (!$video) {
            return response()->json(['message' => 'Video not found'], 404);
        }
        return $video;
    }

    public function update(Request $request, Video $video)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|unique:videos,slug,' . $video->id,
            'description' => 'nullable|string',
            'category_id' => 'sometimes|exists:categories,id',
            'duration_seconds' => 'nullable|integer|min:0',
            'video' => 'nullable|file|mimes:mp4,mov,avi,wmv,webm|max:512000',
            'thumbnail' => 'nullable|image|max:10240',
            'thumbnail_url' => 'nullable|url|max:500',
            'thumbnail_path' => 'nullable|string|max:500', // Server-extracted thumbnail
            'mediafire_url' => 'nullable|url|max:500',
        ]);

        if ($request->hasFile('video')) {
            if ($video->file_path) {
                Storage::disk('public')->delete($video->file_path);
            }
            $validated['file_path'] = $request->file('video')->store('videos', 'public');
        }
        // Thumbnail priority: uploaded file > server-extracted path
        if ($request->hasFile('thumbnail')) {
            if ($video->thumbnail_path) {
                Storage::disk('public')->delete($video->thumbnail_path);
            }
            $validated['thumbnail_path'] = $request->file('thumbnail')->store('videos/thumbnails', 'public');
        }
        // If thumbnail_path is passed directly (server-extracted), it's already in $validated
        if (isset($validated['title']) && !isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }
        unset($validated['video'], $validated['thumbnail']);
        $video->update($validated);
        $video->load('category');
        return response()->json($video);
    }

    public function destroy(Video $video)
    {
        if ($video->file_path) {
            Storage::disk('public')->delete($video->file_path);
        }
        if ($video->thumbnail_path) {
            Storage::disk('public')->delete($video->thumbnail_path);
        }
        $video->delete();
        return response()->json(null, 204);
    }
}
