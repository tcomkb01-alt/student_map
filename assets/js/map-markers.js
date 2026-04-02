/**
 * map-markers.js
 * Custom Leaflet marker factory – cute pin markers (like the reference image)
 * Student Home Visit Map System
 */

'use strict';

/* ── Palette cycling ───────────────────────────────────────── */
const MARKER_COLORS = [
  { bg: '#4f46e5', light: '#ede9fe' },  // indigo
  { bg: '#06b6d4', light: '#cffafe' },  // cyan
  { bg: '#f59e0b', light: '#fef3c7' },  // amber
  { bg: '#10b981', light: '#d1fae5' },  // emerald
  { bg: '#ef4444', light: '#fee2e2' },  // red
  { bg: '#8b5cf6', light: '#ede9fe' },  // violet
  { bg: '#ec4899', light: '#fce7f3' },  // pink
  { bg: '#14b8a6', light: '#ccfbf1' },  // teal
];

let colorIndex = 0;
const classColorMap = {};

function getClassColor(className) {
  if (!classColorMap[className]) {
    classColorMap[className] = MARKER_COLORS[colorIndex % MARKER_COLORS.length];
    colorIndex++;
  }
  return classColorMap[className];
}

/**
 * Build a cute teardrop-pin Divicon.
 * @param {Object} student  – student row object
 * @param {boolean} highlight – enlarge + ring when highlighted
 */
function buildMarkerIcon(student, highlight = false) {
  const color  = getClassColor(student.class || '');
  const size   = highlight ? 54 : 42;
  const imgUrl = student.profile_image
    ? BASE_URL + '/uploads/students/' + student.profile_image
    : null;

  // Inner content: photo or initial letter
  let inner = '';
  if (imgUrl) {
    inner = `<img src="${imgUrl}" style="width:100%;height:100%;object-fit:cover;border-radius:50% 50% 0 50%;" onerror="this.style.display='none';this.nextSibling.style.display='flex'">
             <div style="display:none;width:100%;height:100%;align-items:center;justify-content:center;font-weight:800;font-size:${size * 0.35}px;color:#fff;border-radius:50% 50% 0 50%;">${(student.first_name||'?')[0]}</div>`;
  } else {
    const letter = (student.first_name || '?')[0];
    inner = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:${size * 0.35}px;color:#fff;border-radius:50% 50% 0 50%;">${letter}</div>`;
  }

  const ring = highlight ? `box-shadow:0 0 0 5px ${color.bg}55, 0 6px 20px rgba(0,0,0,.35);` : 'box-shadow:0 4px 14px rgba(0,0,0,.3);';

  const html = `
    <div style="
      width:${size}px;height:${size}px;
      background:${color.bg};
      border-radius:50% 50% 50% 0;
      transform:rotate(-45deg);
      border:3px solid #fff;
      ${ring}
      display:flex;align-items:center;justify-content:center;
      transition:transform .2s;
    ">
      <div style="transform:rotate(45deg);width:82%;height:82%;overflow:hidden;border-radius:50% 50% 0 50%;display:flex;">
        ${inner}
      </div>
    </div>`;

  return L.divIcon({
    html,
    className: '',
    iconSize:    [size, size],
    iconAnchor:  [size / 2, size],
    popupAnchor: [0, -(size + 6)],
  });
}

/**
 * Build popup HTML for a student.
 */
function buildPopupHTML(student) {
  const imgUrl = student.profile_image
    ? BASE_URL + '/uploads/students/' + student.profile_image
    : BASE_URL + '/assets/images/default-avatar.png';

  const fullName = `${student.prefix || ''} ${student.first_name || ''} ${student.last_name || ''}`.trim();
  const address  = student.address || '–';
  const phone    = student.parent_phone || '–';

  const mapsUrl = student.latitude && student.longitude
    ? `https://www.google.com/maps/dir/?api=1&destination=${student.latitude},${student.longitude}`
    : '#';

  return `
    <div class="popup-card">
      <div class="popup-header">
        <img class="popup-avatar" src="${imgUrl}" onerror="this.src='${BASE_URL}/assets/images/default-avatar.png'" alt="">
        <div>
          <div style="font-weight:700;font-size:.95rem;line-height:1.3;">${fullName}</div>
          <div style="font-size:.78rem;opacity:.85;">ชั้น ${student.class || '–'} · เลขที่ ${student.student_number || '–'}</div>
        </div>
      </div>
      <div class="popup-body">
        <div class="popup-row">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.568 17.568 0 0 0 4.168 6.608 17.569 17.569 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.678.678 0 0 0-.58-.122l-2.19.547a1.745 1.745 0 0 1-1.657-.459L5.482 8.062a1.745 1.745 0 0 1-.46-1.657l.548-2.19a.678.678 0 0 0-.122-.58L3.654 1.328z"/></svg>
          <span>${phone}</span>
        </div>
        <div class="popup-row">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A31.493 31.493 0 0 1 8 14.58a31.481 31.481 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94z"/><path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4z"/></svg>
          <span style="word-break:break-word;">${address}</span>
        </div>
        <div class="popup-row">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2zm13 2.383-4.708 2.825L15 11.105V5.383zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741z"/></svg>
          <span>เลขประจำตัว: ${student.student_id || '–'}</span>
        </div>
      </div>
      <div class="popup-footer">
        <a href="${mapsUrl}" target="_blank" rel="noopener"
           style="display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.55rem;background:#4f46e5;color:#fff;border-radius:.75rem;font-size:.84rem;font-weight:700;text-decoration:none;transition:filter .2s;"
           onmouseover="this.style.filter='brightness(1.1)'" onmouseout="this.style.filter=''">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/></svg>
          นำทาง (Google Maps)
        </a>
      </div>
    </div>`;
}
