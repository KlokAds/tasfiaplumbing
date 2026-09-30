/**
 * Display title for an article card: drops the brand suffix that old posts carry for SEO
 * ("… - Tasfia Plumbing Team", "… | Plumbing Team"). The stored title is left as it is.
 */
export function cleanTitle(title = '') {
  return String(title)
    .replace(/\s*[-–|]\s*(Tasfia\s+)?Plumb(ing|er)(\s+Service)?(\s+Singapore)?(\s+Team)?\s*$/i, '')
    .replace(/\s*[-–|]\s*Tasfia(\s+\w+)?\s*$/i, '')
    .trim();
}
