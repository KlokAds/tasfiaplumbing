// Small number and date formatters for the public pages, without Intl: the first Intl call
// (toLocaleString / toLocaleDateString) loads locale data and blocks the page for ~40 ms.
// Output matches the en-SG format: "2,000", "12 Sep 2026", "Sep 2026".
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** 1200 → "1,200", 1200.5 → "1,200.5" */
export function groupDigits(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) return String(value ?? '');
  const [int, frac] = String(Math.abs(n)).split('.');
  return (n < 0 ? '-' : '') + int.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (frac ? `.${frac}` : '');
}

const asDate = (d) => {
  const x = d instanceof Date ? d : new Date(d);
  return Number.isNaN(x.getTime()) ? null : x;
};

/** "12 Sep 2026" */
export function shortDate(d) {
  const x = asDate(d);
  return x ? `${x.getDate()} ${MONTHS[x.getMonth()]} ${x.getFullYear()}` : '';
}

/** "Sep 2026" */
export function monthYear(d) {
  const x = asDate(d);
  return x ? `${MONTHS[x.getMonth()]} ${x.getFullYear()}` : '';
}
