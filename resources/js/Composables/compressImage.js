/**
 * Resize and convert an image to WebP in the browser before upload.
 * Keeps uploads under the server limit and makes pages load faster (LCP).
 * Falls back to the original file when the browser cannot decode it (e.g. HEIC) or for GIF/SVG.
 */
export async function compressImage(file, { maxSide = 2000, quality = 0.82, maxBytes = 1.8 * 1024 * 1024 } = {}) {
  if (!file || !file.type?.startsWith('image/') || ['image/gif', 'image/svg+xml'].includes(file.type)) return file;

  let bitmap;
  try {
    bitmap = await createImageBitmap(file);
  } catch (e) {
    return file;
  }

  const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
  if (scale === 1 && file.type === 'image/webp' && file.size <= 400 * 1024) return file;

  const canvas = document.createElement('canvas');
  canvas.width = Math.round(bitmap.width * scale);
  canvas.height = Math.round(bitmap.height * scale);
  canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
  bitmap.close?.();

  let q = quality;
  let blob = await toBlob(canvas, q);
  while (blob && blob.size > maxBytes && q > 0.45) {
    q -= 0.1;
    blob = await toBlob(canvas, q);
  }
  if (!blob || blob.size >= file.size) return file;

  const name = file.name.replace(/\.[^.]+$/, '') + '.webp';
  return new File([blob], name, { type: 'image/webp', lastModified: Date.now() });
}

function toBlob(canvas, quality) {
  return new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', quality));
}
