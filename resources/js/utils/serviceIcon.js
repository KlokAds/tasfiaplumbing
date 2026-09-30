/** One line icon (24px SVG path) per kind of job, picked from the service name. */
const ICONS = [
  [/heater/i, 'M8 3h8a2 2 0 012 2v13a2 2 0 01-2 2H8a2 2 0 01-2-2V5a2 2 0 012-2zM10 21v1M14 21v1M12 9.5s-2 2.2-2 3.6a2 2 0 004 0c0-1.4-2-3.6-2-3.6z'],
  [/toilet|flush/i, 'M7 3h5v7H7zM4 10h16c0 3.3-2.7 6-6 6h-4c-3.3 0-6-2.7-6-6zM9 16l-1 5h8l-1-5'],
  [/shower/i, 'M5 21V8a4 4 0 018 0M9 8h8M11 12v.01M14 12v.01M17 12v.01M12 15v.01M15 15v.01M18 15v.01'],
  [/leak|pipe|silicone/i, 'M4 9h7a3 3 0 013 3v1M4 6v6M18 13.5s-2 2.3-2 3.8a2 2 0 004 0c0-1.5-2-3.8-2-3.8zM11 9V6'],
  [/tap|mixer|faucet/i, 'M5 11h9a4 4 0 014 4v1M9 11V7m-3 0h6M18 19.5s-1.5 1.5-1.5 2.3a1.5 1.5 0 003 0c0-.8-1.5-2.3-1.5-2.3z'],
  [/sink|basin/i, 'M3 11h18c0 3.3-2.7 6-6 6H9c-3.3 0-6-2.7-6-6zM12 11V6a2 2 0 014 0M10 17l-.5 4h5l-.5-4'],
  [/door|hinge|roller|lock|frame|wardrobe/i, 'M6 3h12v18H6zM14 12h.01M3 21h18'],
  [/electric|wiring|light/i, 'M13 3L4 14h7l-1 7 9-11h-7l1-7z'],
  [/aircon/i, 'M3 5h18v7H3zM6 9h12M8 16c0 1.5-1 2-1 3M12 16c0 1.5-1 2-1 3M16 16c0 1.5-1 2-1 3'],
  [/paint|plaster|polish|varnish/i, 'M4 4h13v5H4zM17 6h3v5h-8v3M12 14v7'],
  [/tile/i, 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
  [/cabinet|drawer|furniture/i, 'M4 4h16v16H4zM4 12h16M10 8h4M10 16h4'],
];
const FALLBACK = 'M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.8-3.8a6 6 0 01-7.9 7.9l-6.9 6.9a2.1 2.1 0 01-3-3l6.9-6.9a6 6 0 017.9-7.9l-3.8 3.8z';

export function serviceIcon(name = '') {
  const hit = ICONS.find(([re]) => re.test(name));
  return hit ? hit[1] : FALLBACK;
}
