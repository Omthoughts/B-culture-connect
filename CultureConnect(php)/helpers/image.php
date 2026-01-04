<?php
declare(strict_types=1);
/**
 * ImageHelper - minimal image processing helper
 * Provides optimizeAndSave(tempPath, destPath): bool
 */
class ImageHelper {
    public static function optimizeAndSave(string $tmpPath, string $destPath): bool
    {
        // Ensure destination directory exists
        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                return false;
            }
        }

        // Use GD to re-encode and save image to reduce size
        $info = @getimagesize($tmpPath);
        if ($info === false) return false;

        $mime = $info['mime'] ?? '';
        try {
            switch ($mime) {
                case 'image/jpeg':
                    $img = imagecreatefromjpeg($tmpPath);
                    if ($img === false) return false;
                    $result = imagejpeg($img, $destPath, 85);
                    imagedestroy($img);
                    return (bool)$result;
                case 'image/png':
                    $img = imagecreatefrompng($tmpPath);
                    if ($img === false) return false;
                    // convert PNG to PNG with compression level 6
                    $result = imagepng($img, $destPath, 6);
                    imagedestroy($img);
                    return (bool)$result;
                case 'image/gif':
                    $img = imagecreatefromgif($tmpPath);
                    if ($img === false) return false;
                    $result = imagegif($img, $destPath);
                    imagedestroy($img);
                    return (bool)$result;
                case 'image/webp':
                    if (!function_exists('imagecreatefromwebp')) {
                        // Fallback: move file directly
                        return @move_uploaded_file($tmpPath, $destPath) || @copy($tmpPath, $destPath);
                    }
                    $img = imagecreatefromwebp($tmpPath);
                    if ($img === false) return false;
                    $result = imagewebp($img, $destPath, 80);
                    imagedestroy($img);
                    return (bool)$result;
                default:
                    // Unknown type, just move the uploaded file
                    return @move_uploaded_file($tmpPath, $destPath) || @copy($tmpPath, $destPath);
            }
        } catch (Throwable $e) {
            error_log('ImageHelper error: ' . $e->getMessage());
            return false;
        }
    }
}
