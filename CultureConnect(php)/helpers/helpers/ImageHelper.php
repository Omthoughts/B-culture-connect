<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * CULTURECONNECT IMAGE HELPER
 * Optimized, safe, and robust image processing utility.
 * ═══════════════════════════════════════════════════════════════════
 */

class ImageHelper
{
    /**
     * Optimizes a temporary file (e.g., from an upload) and saves it to a final destination.
     * Performs compression and preserves transparency for better web performance.
     *
     * @param string $sourceFile Path to the temporary/source image file.
     * @param string $destinationPath Full path to save the final image.
     * @return bool True on successful save and optimization, false otherwise.
     */
    public static function optimizeAndSave(string $sourceFile, string $destinationPath): bool
    {
        // 1. Basic checks
        if (!file_exists($sourceFile)) {
            error_log("ImageHelper Error: Source file not found.");
            return false;
        }
        
        // Ensure GD library is available
        if (!extension_loaded('gd')) {
            error_log("ImageHelper Error: GD extension is not loaded. Cannot process image.");
            return false;
        }

        // 2. Get image information and create resource
        $imageInfo = getimagesize($sourceFile);
        if ($imageInfo === false) {
            error_log("ImageHelper Error: File is not a valid image or could not be read.");
            return false;
        }
        
        $mime = $imageInfo['mime'];

        // Use a match expression (PHP 8.0+) for clean resource creation
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($sourceFile),
            'image/png' => imagecreatefrompng($sourceFile),
            'image/gif'  => imagecreatefromgif($sourceFile),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($sourceFile) : null,
            default      => null,
        };

        if ($image === null) {
            error_log("ImageHelper Error: Unsupported image format or failed to create image resource: " . $mime);
            return false;
        }
        
        // 3. Optimization and Transparency Handling
        
        $success = false;

        switch ($mime) {
            case 'image/jpeg':
            case 'image/webp':
                // For JPEG and WebP, use a moderate quality setting (80) for good compression/quality trade-off
                $quality = 80;
                if ($mime === 'image/jpeg') {
                    $success = imagejpeg($image, $destinationPath, $quality);
                } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
                    $success = imagewebp($image, $destinationPath, $quality);
                }
                break;

            case 'image/png':
                // Preserve full transparency for PNG files
                imagealphablending($image, false);
                imagesavealpha($image, true);
                // Compression level 6 is a good balance (0=no compression, 9=max compression)
                $compression_level = 6;
                $success = imagepng($image, $destinationPath, $compression_level);
                break;

            case 'image/gif':
                // Simple GIF saving (preserves animation frames if present, but we only process first frame)
                $success = imagegif($image, $destinationPath);
                break;
                
            default:
                // Should not happen if the 'match' was successful, but as a fallback
                $success = false;
        }

        // 4. Cleanup and return
        imagedestroy($image);
        return $success;
    }
}