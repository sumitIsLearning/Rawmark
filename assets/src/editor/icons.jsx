import React from 'react';

const base = {
  width: 16,
  height: 16,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 1.8,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  'aria-hidden': true,
};

const PATHS = {
  mobile: (
    <>
      <rect x="7" y="3" width="10" height="18" rx="2" />
      <line x1="11" y1="18" x2="13" y2="18" />
    </>
  ),
  tablet: (
    <>
      <rect x="5" y="3" width="14" height="18" rx="2" />
      <line x1="10.5" y1="18" x2="13.5" y2="18" />
    </>
  ),
  desktop: (
    <>
      <rect x="3" y="4" width="18" height="12" rx="1.5" />
      <line x1="8" y1="20" x2="16" y2="20" />
      <line x1="12" y1="16" x2="12" y2="20" />
    </>
  ),
  split: (
    <>
      <rect x="3" y="4" width="18" height="16" rx="2" />
      <line x1="12" y1="4" x2="12" y2="20" />
    </>
  ),
  codeonly: (
    <>
      <polyline points="9 8 5 12 9 16" />
      <polyline points="15 8 19 12 15 16" />
    </>
  ),
  previewonly: (
    <>
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <circle cx="12" cy="12" r="2.4" />
    </>
  ),
  refresh: (
    <>
      <polyline points="21 3 21 9 15 9" />
      <path d="M20 13a8 8 0 1 1-2.3-5.7L21 9" />
    </>
  ),
  warn: (
    <>
      <path d="M12 3 2 20h20L12 3z" />
      <line x1="12" y1="10" x2="12" y2="14" />
      <line x1="12" y1="17" x2="12" y2="17" />
    </>
  ),
  check: <polyline points="20 6 9 17 4 12" />,
};

export function Icon({ name }) {
  return <svg {...base}>{PATHS[name]}</svg>;
}
