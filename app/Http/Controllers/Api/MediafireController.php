<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MediafireController extends Controller
{
    public function fetchMetadata(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $url = $request->input('url');

        try {
            // Step 1: Get direct download URL from MediaFire page
            $directUrl = $this->getDirectDownloadUrl($url);
            if (!$directUrl) {
                return response()->json([
                    'message' => 'Could not extract direct download URL from MediaFire page',
                ], 422);
            }

            // Step 2: Get video metadata using ffprobe
            $metadata = $this->getVideoMetadata($directUrl);

            // Step 3: Extract thumbnail
            $thumbnailPath = null;
            if ($metadata['duration'] > 0) {
                $thumbnailPath = $this->extractThumbnail($directUrl, $metadata['duration']);
            }

            return response()->json([
                'duration_seconds' => $metadata['duration'],
                'thumbnail_path' => $thumbnailPath,
                'direct_url' => $directUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch metadata: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getDirectDownloadUrl(string $pageUrl): ?string
    {
        // Fetch the MediaFire page - don't follow redirects to avoid downloading the video
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ])
        ->timeout(30)
        ->withoutRedirecting()
        ->get($pageUrl);

        // If redirected, follow manually but only for page URLs
        if ($response->status() >= 300 && $response->status() < 400) {
            $redirectUrl = $response->header('Location');
            if ($redirectUrl && !str_contains($redirectUrl, 'download')) {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                ])->timeout(30)->withoutRedirecting()->get($redirectUrl);
            }
        }

        if (!$response->successful() && $response->status() < 300) {
            return null;
        }

        $html = $response->body();

        // Look for the direct download link in the page
        // MediaFire uses various patterns, try multiple
        $patterns = [
            '/href="(https:\/\/download\d*\.mediafire\.com\/[^"]+)"/',
            '/aria-label="Download file"\s+href="([^"]+)"/',
            '/"downloadUrl":"([^"]+)"/',
            '/window\.location\.href\s*=\s*[\'"]([^\'"]+)[\'"]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $downloadUrl = $matches[1];
                // Unescape if needed
                $downloadUrl = str_replace('\/', '/', $downloadUrl);
                return $downloadUrl;
            }
        }

        return null;
    }

    private function getVideoMetadata(string $videoUrl): array
    {
        $duration = 0;

        // Check if ffprobe is available
        $ffprobePath = $this->findExecutable('ffprobe');
        if (!$ffprobePath) {
            // Fallback: try to get duration from HTTP headers or partial download
            return ['duration' => 0];
        }

        // Use ffprobe to get duration with minimal data transfer
        // -analyzeduration and -probesize limit how much data is read
        $escapedUrl = escapeshellarg($videoUrl);
        $command = "{$ffprobePath} -v quiet -analyzeduration 10000000 -probesize 10000000 -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 {$escapedUrl} 2>&1";

        $output = shell_exec($command);
        if ($output && is_numeric(trim($output))) {
            $duration = (int) round((float) trim($output));
        }

        return ['duration' => $duration];
    }

    private function extractThumbnail(string $videoUrl, int $duration): ?string
    {
        $ffmpegPath = $this->findExecutable('ffmpeg');
        if (!$ffmpegPath) {
            return null;
        }

        // Generate thumbnail filename
        $filename = 'thumbnails/' . Str::uuid() . '.jpg';
        $outputPath = storage_path('app/public/videos/' . $filename);

        // Ensure directory exists
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Capture frame at 2 seconds (to avoid black frames at start)
        // Using -ss before -i for fast seeking without downloading entire video
        $seekTime = min(2, max(0, $duration - 1));
        $escapedUrl = escapeshellarg($videoUrl);
        $escapedOutput = escapeshellarg($outputPath);

        // Use -ss before -i for input seeking (fast, no full download needed)
        // Add timeout to prevent hanging on slow connections
        $command = "{$ffmpegPath} -ss {$seekTime} -i {$escapedUrl} -vframes 1 -q:v 2 -t 1 {$escapedOutput} -y 2>&1";

        shell_exec($command);

        if (file_exists($outputPath)) {
            return 'videos/' . $filename;
        }

        return null;
    }

    private function findExecutable(string $name): ?string
    {
        // Common paths for ffmpeg/ffprobe
        $paths = [
            $name, // System PATH
            "/usr/bin/{$name}",
            "/usr/local/bin/{$name}",
            "C:\\ffmpeg\\bin\\{$name}.exe",
            "C:\\Program Files\\ffmpeg\\bin\\{$name}.exe",
        ];

        foreach ($paths as $path) {
            // Check if executable exists and is runnable
            $checkCommand = PHP_OS_FAMILY === 'Windows'
                ? "where " . escapeshellarg($path) . " 2>nul"
                : "which " . escapeshellarg($path) . " 2>/dev/null";

            $result = shell_exec($checkCommand);
            if ($result && trim($result)) {
                return trim($result);
            }

            // Direct check
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Try running directly (might be in PATH)
        $testCommand = PHP_OS_FAMILY === 'Windows'
            ? "{$name} -version 2>nul"
            : "{$name} -version 2>/dev/null";

        $result = shell_exec($testCommand);
        if ($result && strpos($result, 'version') !== false) {
            return $name;
        }

        return null;
    }
}
