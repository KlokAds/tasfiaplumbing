import { groupDigits } from './fmt';
// Icons (24px stroke paths) and helpers for the counter strips and value cards.
export const icons = {
  project: 'M9 12l2 2 4-4M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6z',
  year: 'M12 8v4l3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  team: 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8zM21 20v-2a4 4 0 00-3-3.87M16 2.13a4 4 0 010 7.75',
  happy: 'M14.8 14.5a4 4 0 01-5.6 0M9 9.5h.01M15 9.5h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  star: 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3z',
  scale: 'M12 3v18M5 7h14M5 7l-3 7a3.5 3.5 0 006 0L5 7zm14 0l-3 7a3.5 3.5 0 006 0l-3-7zM8 21h8',
  cap: 'M12 4L2 9l10 5 10-5-10-5zM6 11v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5',
  shield: 'M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6l8-3zM9 12l2 2 4-4',
  tools: 'M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.5 2.5-2.4-.6-.6-2.4 2.5-2.5z',
};

function pick(text) {
  const t = String(text || '').toLowerCase();
  if (/smile|happy|satisf|client|customer/.test(t)) return icons.happy;
  if (/project|job|work|complet|tasks/.test(t)) return icons.project;
  if (/year|experience|clock|24|hour|time|hourglass/.test(t)) return icons.year;
  if (/expert|team|staff|worker|engineer|users/.test(t)) return icons.team;
  if (/honest|balance|scale|fair|price/.test(t)) return icons.scale;
  if (/train|graduation|skill|learn|updated/.test(t)) return icons.cap;
  if (/safe|licen|insur|warrant|shield/.test(t)) return icons.shield;
  return icons.star;
}

/** Counters from admin → [{ id, value: "2,000+", label, icon }] */
export function statsFrom(counters = [], max = 4) {
  return counters.slice(0, max).map((c) => {
    const raw = String(c.c_count ?? '').trim();
    const n = Number(raw.replace(/[,+\s]/g, ''));
    const value = raw && Number.isFinite(n) && /^[\d,\s]+\+?$/.test(raw) ? groupDigits(n) + '+' : raw;
    const label = String(c.c_title ?? '').replace(/[!.]+$/, '').trim();
    return { id: c.id, value, label, icon: pick(label) };
  }).filter((c) => c.value && c.label);
}

/** Icon for a value/skill card: the old Font Awesome name first, then the title. */
export function iconFor(faName, title) {
  return pick(`${faName || ''} ${title || ''}`);
}
