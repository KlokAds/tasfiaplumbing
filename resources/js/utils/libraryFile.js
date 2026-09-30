/**
 * A tiny placeholder file that stands for an existing Media library image.
 * Sent in place of an upload; the server swaps it for the real file (UseLibraryFiles),
 * so a library path can be kept in a draft and turned back into a form file later.
 */
export function libraryFile(path) {
  const ext = (path.split('.').pop() || 'jpg').toLowerCase();
  const code = btoa(unescape(encodeURIComponent(path))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  const mime = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp', gif: 'image/gif', avif: 'image/avif', svg: 'image/svg+xml' }[ext] || 'application/octet-stream';
  return new File([new Uint8Array([0])], `library--${code}.${ext}`, { type: mime });
}

/** Public URL of a library path (each folder and file name encoded). */
export function libraryUrl(path) {
  return '/' + path.split('/').map(encodeURIComponent).join('/');
}
