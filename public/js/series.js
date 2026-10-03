// Crosshair and tooltip for the weekly series charts: the pointer snaps to the nearest week.
for (const chart of document.querySelectorAll('.chart[data-points]')) {
  const points = JSON.parse(chart.dataset.points);
  const svg = chart.querySelector('svg');
  const crosshair = svg.querySelector('.crosshair');
  const tooltip = document.createElement('div');
  tooltip.className = 'tooltip';
  tooltip.hidden = true;
  const value = tooltip.appendChild(document.createElement('strong'));
  const week = tooltip.appendChild(document.createElement('span'));
  chart.appendChild(tooltip);

  const show = (clientX) => {
    const box = svg.getBoundingClientRect();
    const frame = chart.getBoundingClientRect();
    const x = ((clientX - box.left) / box.width) * svg.viewBox.baseVal.width;
    const nearest = points.reduce((best, point) => (Math.abs(point.x - x) < Math.abs(best.x - x) ? point : best));
    crosshair.setAttribute('x1', nearest.x);
    crosshair.setAttribute('x2', nearest.x);
    crosshair.style.visibility = 'visible';
    value.textContent = nearest.volume === null ? 'not measurable' : nearest.volume.toLocaleString();
    week.textContent = `week of ${nearest.week}`;
    tooltip.style.left = `${box.left - frame.left + (nearest.x / svg.viewBox.baseVal.width) * box.width}px`;
    tooltip.style.top = `${box.top - frame.top + (nearest.y / svg.viewBox.baseVal.height) * box.height}px`;
    tooltip.hidden = false;
  };

  svg.addEventListener('pointermove', (event) => show(event.clientX));
  svg.addEventListener('pointerleave', () => { tooltip.hidden = true; crosshair.style.visibility = 'hidden'; });
}
