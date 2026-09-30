/**
 * Responsive image helpers for uploaded images (stored like "Admin/Service/abc.jpg").
 * Resized WebP copies are served from /cache/img/{width}/{path}.webp (see ImageController).
 * External URLs and SVGs are returned unchanged.
 */
const WIDTHS = [96, 160, 320, 480, 640, 800, 1024, 1280, 1600, 1920];

const local = (path) => {
  if (!path) return null;
  const p = String(path).replace(/^\/+/, '');
  if (/^https?:/i.test(p) || !/\.(jpe?g|png|webp|gif)$/i.test(p)) return null;
  return /^(Admin|uploads|images)\//.test(p) ? p : null;
};

/** Full-size URL of the original file. */
export const src = (path) => (!path ? '' : /^https?:/i.test(path) ? path : '/' + String(path).replace(/^\/+/, ''));

/** One resized WebP (falls back to the original). */
export function img(path, width = 800) {
  const p = local(path);
  if (!p) return src(path);
  const w = WIDTHS.find((x) => x >= width) || WIDTHS[WIDTHS.length - 1];
  return `/cache/img/${w}/${p}.webp`;
}

/** srcset up to maxWidth, for use with a matching `sizes` attribute. */
export function srcset(path, maxWidth = 1600) {
  const p = local(path);
  if (!p) return undefined;
  return WIDTHS.filter((w) => w >= 320 && w <= maxWidth).map((w) => `/cache/img/${w}/${p}.webp ${w}w`).join(', ');
}
