<?php
/**
 * Image Helper for NPGLOW
 * Handles automatic conversion, auto-orientation (EXIF), resizing, and compression to WebP.
 */

if (!function_exists('convert_image_to_webp')) {
    /**
     * Convert and compress an uploaded image to WebP format.
     *
     * @param string $sourcePath Path to the source image (e.g. $_FILES['photo']['tmp_name'])
     * @param string $destPath Absolute destination path where the .webp file should be saved
     * @param int $quality Compression quality (1-100, default 82 for great balance of quality & file size)
     * @param int $maxWidth Max width to downscale if larger (default 1600px)
     * @param int $maxHeight Max height to downscale if larger (default 1600px)
     * @return array Result array with status, file path, original size, compressed size, and dimensions.
     */
    function convert_image_to_webp($sourcePath, $destPath, $quality = 82, $maxWidth = 1600, $maxHeight = 1600) {
        if (!file_exists($sourcePath)) {
            return ['success' => false, 'error' => 'Source file not found'];
        }

        // Ensure destination directory exists
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $originalSize = filesize($sourcePath);

        // Check if GD extension is available
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            // Fallback: copy original if WebP is not supported by PHP GD
            if (move_uploaded_file($sourcePath, $destPath) || copy($sourcePath, $destPath)) {
                return [
                    'success' => true,
                    'fallback' => true,
                    'file_path' => $destPath,
                    'original_size' => $originalSize,
                    'compressed_size' => filesize($destPath),
                    'savings_percent' => 0
                ];
            }
            return ['success' => false, 'error' => 'GD WebP extension not available and fallback failed'];
        }

        // Read image data from file
        $imageData = file_get_contents($sourcePath);
        if ($imageData === false) {
            return ['success' => false, 'error' => 'Could not read image content'];
        }

        $srcImage = @imagecreatefromstring($imageData);
        if (!$srcImage) {
            return ['success' => false, 'error' => 'Unsupported image format or corrupt image file'];
        }

        // Handle EXIF orientation (fixes smartphone camera rotation issues)
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $srcImage = imagerotate($srcImage, 180, 0);
                        break;
                    case 6:
                        $srcImage = imagerotate($srcImage, -90, 0);
                        break;
                    case 8:
                        $srcImage = imagerotate($srcImage, 90, 0);
                        break;
                }
            }
        }

        // Original dimensions
        $origWidth = imagesx($srcImage);
        $origHeight = imagesy($srcImage);

        // Calculate proportional downscaled dimensions if needed
        $newWidth = $origWidth;
        $newHeight = $origHeight;

        if ($origWidth > $maxWidth || $origHeight > $maxHeight) {
            $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
            $newWidth = (int)round($origWidth * $ratio);
            $newHeight = (int)round($origHeight * $ratio);
        }

        // Create new truecolor image canvas
        $targetImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG / WebP with alpha
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);

        // High quality bicubic resampling
        imagecopyresampled(
            $targetImage,
            $srcImage,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $origWidth,
            $origHeight
        );

        // Save as WebP
        $saved = imagewebp($targetImage, $destPath, $quality);

        // Free memory
        imagedestroy($srcImage);
        imagedestroy($targetImage);

        if (!$saved || !file_exists($destPath)) {
            return ['success' => false, 'error' => 'Failed to save WebP file'];
        }

        $compressedSize = filesize($destPath);
        $savings = $originalSize > 0 ? round((1 - ($compressedSize / $originalSize)) * 100, 1) : 0;

        return [
            'success' => true,
            'file_path' => $destPath,
            'original_size' => $originalSize,
            'compressed_size' => $compressedSize,
            'savings_percent' => max(0, $savings),
            'width' => $newWidth,
            'height' => $newHeight
        ];
    }
}

if (!function_exists('generate_unique_webp_filename')) {
    /**
     * Generate a unique clean filename with .webp extension.
     *
     * @param string $prefix Prefix e.g. 'initial', 'progress', 'product'
     * @return string e.g. 'progress_20260805_1722849201_a8f3.webp'
     */
    function generate_unique_webp_filename($prefix = 'img') {
        return $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.webp';
    }
}
