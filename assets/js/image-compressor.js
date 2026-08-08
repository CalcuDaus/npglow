/**
 * NPGLOW Client-Side Image Compressor & WebP Converter
 * Compresses camera/gallery photos before upload to ensure ultra-fast uploads and minimal bandwidth usage.
 */
window.NPGLOWCompressor = {
    /**
     * Compress an image file to WebP (or fallback JPEG)
     * @param {File} file The original File object
     * @param {Object} options Compression options
     * @returns {Promise<{file: File, blob: Blob, originalSize: number, compressedSize: number, savings: number, previewUrl: string}>}
     */
    compress: function(file, options = {}) {
        return new Promise((resolve, reject) => {
            if (!file || !file.type.startsWith('image/')) {
                return reject(new Error('File yang dipilih bukan gambar valid.'));
            }

            const maxWidth = options.maxWidth || 1600;
            const maxHeight = options.maxHeight || 1600;
            const quality = options.quality || 0.82;
            const originalSize = file.size;

            const reader = new FileReader();
            reader.readAsDataURL(file);

            reader.onload = (e) => {
                const img = new Image();
                img.src = e.target.result;

                img.onload = () => {
                    let width = img.width;
                    let height = img.height;

                    // Calculate proportional downscaling
                    if (width > maxWidth || height > maxHeight) {
                        const ratio = Math.min(maxWidth / width, maxHeight / height);
                        width = Math.round(width * ratio);
                        height = Math.round(height * ratio);
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    // Try WebP first, fallback to jpeg
                    let mimeType = 'image/webp';
                    
                    // Check if canvas supports webp export
                    if (canvas.toDataURL('image/webp').indexOf('data:image/webp') !== 0) {
                        mimeType = 'image/jpeg';
                    }

                    canvas.toBlob((blob) => {
                        if (!blob) {
                            return reject(new Error('Gagal mengompres gambar di browser.'));
                        }

                        // Generate clean webp filename
                        const baseName = file.name.replace(/\.[^/.]+$/, "");
                        const newExt = mimeType === 'image/webp' ? '.webp' : '.jpg';
                        const compressedFile = new File([blob], baseName + newExt, {
                            type: mimeType,
                            lastModified: Date.now()
                        });

                        const compressedSize = blob.size;
                        const savings = originalSize > 0 
                            ? Math.max(0, Math.round((1 - (compressedSize / originalSize)) * 100))
                            : 0;
                        const previewUrl = URL.createObjectURL(blob);

                        resolve({
                            file: compressedFile,
                            blob: blob,
                            originalSize: originalSize,
                            compressedSize: compressedSize,
                            savings: savings,
                            previewUrl: previewUrl,
                            width: width,
                            height: height
                        });
                    }, mimeType, quality);
                };

                img.onerror = () => reject(new Error('Gagal memuat format gambar.'));
            };

            reader.onerror = () => reject(new Error('Gagal membaca file gambar.'));
        });
    },

    /**
     * Format bytes into readable KB/MB
     */
    formatBytes: function(bytes, decimals = 1) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
};
