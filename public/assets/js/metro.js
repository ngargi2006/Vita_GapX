/**
 * Vita-Gap — Signature Metro-Map Pathway Visualizer
 * Renders SVG branch lanes, stations, and interactive inspection drawers
 */

export class MetroMapVisualizer {
  constructor(containerElement, onStationClick) {
    this.container = containerElement;
    this.onStationClick = onStationClick;
  }

  render(pathwayData) {
    this.container.innerHTML = '';
    const steps = pathwayData.steps || [];
    if (steps.length === 0) {
      this.container.innerHTML = '<p class="text-muted">No pathway steps available.</p>';
      return;
    }

    // Group steps by branch
    const branchNames = [...new Set(steps.map(s => s.branch || 'main'))];
    const isMultiBranch = branchNames.length > 1;

    // Calculate layout coordinates
    const stationSpacing = 130;
    const paddingX = 70;
    const paddingY = 60;
    const laneHeight = 80;

    // Map branches to distinct vertical lanes
    const laneYMap = {};
    if (!isMultiBranch) {
      laneYMap[branchNames[0]] = paddingY + 30;
    } else {
      branchNames.forEach((branch, idx) => {
        // If branch is 'merge' or 'main', put it in the center
        if (branch === 'merge' || branch === 'main') {
          laneYMap[branch] = paddingY + (laneHeight * 0.7);
        } else {
          laneYMap[branch] = paddingY + (idx * laneHeight);
        }
      });
    }

    // Assign X and Y coordinates to each step
    const branchCurrentX = {};
    branchNames.forEach(b => { branchCurrentX[b] = paddingX; });

    let maxX = paddingX;
    const stepCoords = [];

    steps.forEach((step, idx) => {
      const branch = step.branch || 'main';
      const isMerge = (branch === 'merge');

      if (isMerge) {
        // Align merge steps after all previous branches have completed
        const prevMax = Math.max(...Object.values(branchCurrentX));
        branchCurrentX[branch] = Math.max(branchCurrentX[branch], prevMax);
      }

      const x = branchCurrentX[branch];
      const y = laneYMap[branch] || paddingY;

      stepCoords.push({ step, x, y, idx });

      branchCurrentX[branch] += stationSpacing;
      if (branchCurrentX[branch] > maxX) {
        maxX = branchCurrentX[branch];
      }
    });

    const svgWidth = Math.max(760, maxX + paddingX);
    const svgHeight = Math.max(220, (branchNames.length * laneHeight) + (paddingY * 2));

    // Create SVG container
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'metro-svg');
    svg.setAttribute('width', svgWidth);
    svg.setAttribute('height', svgHeight);
    svg.setAttribute('viewBox', `0 0 ${svgWidth} ${svgHeight}`);

    // Draw tracks (connecting lines)
    const trackGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    trackGroup.setAttribute('class', 'metro-tracks');

    for (let i = 0; i < stepCoords.length - 1; i++) {
      const curr = stepCoords[i];
      const next = stepCoords[i + 1];

      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('class', 'metro-track');

      const isCurrentFound = curr.step.status === 'present';
      const isNextFound = next.step.status === 'present';
      const strokeColor = (isCurrentFound && isNextFound) ? '#009E73' : '#94a3b8';
      path.setAttribute('stroke', strokeColor);
      path.setAttribute('stroke-width', '4');

      if (curr.y === next.y) {
        // Direct horizontal track
        path.setAttribute('d', `M ${curr.x} ${curr.y} L ${next.x} ${next.y}`);
      } else {
        // Smooth S-curve merging branch into trunk
        const midX = (curr.x + next.x) / 2;
        path.setAttribute('d', `M ${curr.x} ${curr.y} C ${midX} ${curr.y}, ${midX} ${next.y}, ${next.x} ${next.y}`);
      }
      trackGroup.appendChild(path);
    }
    svg.appendChild(trackGroup);

    // Draw branch labels
    branchNames.forEach(b => {
      const y = laneYMap[b];
      const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      label.setAttribute('x', '15');
      label.setAttribute('y', y + 4);
      label.setAttribute('font-size', '10');
      label.setAttribute('font-weight', '700');
      label.setAttribute('fill', 'var(--text-muted)');
      label.setAttribute('text-anchor', 'start');
      label.textContent = b.toUpperCase();
      svg.appendChild(label);
    });

    // Draw stations
    const stationGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    stationGroup.setAttribute('class', 'metro-stations');

    stepCoords.forEach(({ step, x, y }) => {
      const station = document.createElementNS('http://www.w3.org/2000/svg', 'g');
      station.setAttribute('class', 'metro-station');
      station.style.cursor = 'pointer';

      const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      circle.setAttribute('cx', x);
      circle.setAttribute('cy', y);
      circle.setAttribute('r', '7');
      circle.setAttribute('class', 'metro-station-circle');

      const isPresent = (step.status === 'present');
      const isPartial = (step.status === 'partial');
      const isGap = (step.kind === 'gap');

      if (isGap) {
        circle.setAttribute('fill', 'var(--bg-surface)');
        circle.setAttribute('stroke', '#8A8A8A');
        circle.setAttribute('stroke-width', '2.5');
        circle.setAttribute('stroke-dasharray', '3 2');
      } else if (isPresent) {
        circle.setAttribute('fill', '#009E73');
        circle.setAttribute('stroke', '#ffffff');
        circle.setAttribute('stroke-width', '2.5');
      } else if (isPartial) {
        circle.setAttribute('fill', '#E69F00');
        circle.setAttribute('stroke', '#ffffff');
        circle.setAttribute('stroke-width', '2.5');
      } else {
        circle.setAttribute('fill', 'var(--bg-surface)');
        circle.setAttribute('stroke', '#D55E00');
        circle.setAttribute('stroke-width', '3');
      }

      // Station code / title
      const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      label.setAttribute('x', x);
      label.setAttribute('y', y - 14);
      label.setAttribute('text-anchor', 'middle');
      label.setAttribute('class', 'metro-station-text');
      label.textContent = step.code.split('.').pop() || step.code;

      // Subtext: Gene symbol or EC
      const sub = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      sub.setAttribute('x', x);
      sub.setAttribute('y', y + 20);
      sub.setAttribute('text-anchor', 'middle');
      sub.setAttribute('class', 'metro-station-subtext');
      sub.textContent = step.ec ? `EC ${step.ec}` : (step.options?.[0] || '');

      station.appendChild(circle);
      station.appendChild(label);
      station.appendChild(sub);

      station.addEventListener('click', () => {
        if (typeof this.onStationClick === 'function') {
          this.onStationClick(step);
        }
      });

      stationGroup.appendChild(station);
    });

    svg.appendChild(stationGroup);
    this.container.appendChild(svg);
  }
}
