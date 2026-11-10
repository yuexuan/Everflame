(() => {
  const canvas = document.getElementById('user-traffic-chart');
  if (!canvas) return;
  const showError = () => { document.getElementById('traffic-chart-error').hidden = false; };
  if (!window.Chart) { showError(); return; }
  try {
    const points = JSON.parse(document.getElementById('user-traffic-data').textContent);
    const maxBytes = Math.max(0, ...points.map(point => point.bytes || 0));
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const power = maxBytes > 0 ? Math.max(0, Math.min(4, Math.floor(Math.log(maxBytes) / Math.log(1024)))) : 3;
    const divisor = 1024 ** power;
    const tokens = getComputedStyle(document.body);
    const primary = tokens.getPropertyValue('--tblr-primary').trim() || '#5664d9';
    const muted = tokens.getPropertyValue('--ef-muted').trim() || '#68758b';
    const border = tokens.getPropertyValue('--ef-border').trim() || '#e3e7ef';
    const surface = tokens.getPropertyValue('--ef-surface').trim() || '#fff';
    Chart.defaults.font.family = tokens.fontFamily;
    Chart.defaults.font.size = 14;
    const format = value => `${new Intl.NumberFormat('zh-CN', {maximumFractionDigits: 2}).format(value)} ${units[power]}`;
    new Chart(canvas, {
      type: 'line',
      data: {
        labels: points.map(point => point.label),
        datasets: [{label: '总传输流量', data: points.map(point => point.bytes === null ? null : point.bytes / divisor),
          borderColor: primary, backgroundColor: 'rgba(103,119,239,0.12)', fill: true,
          borderWidth: 2, tension: 0.25, spanGaps: false, pointRadius: points.length > 10 ? 2 : 4,
          pointBackgroundColor: surface, pointBorderColor: primary, pointHoverRadius: 6, pointHitRadius: 16}]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        animation: matchMedia('(prefers-reduced-motion: reduce)').matches ? false : {duration: 250},
        interaction: {mode: 'index', intersect: false},
        font: {family: tokens.fontFamily, size: 13},
        plugins: {
          legend: {position: 'bottom', labels: {color: muted, usePointStyle: true, boxWidth: 8, boxHeight: 8, padding: 24}},
          tooltip: {backgroundColor: surface, titleColor: muted, bodyColor: primary, borderColor: border, borderWidth: 1, padding: 12,
            callbacks: {label: context => `总传输流量：${format(context.parsed.y)}`}}
        },
        scales: {
          x: {grid: {display: false}, border: {color: border}, ticks: {color: muted, maxRotation: 0, maxTicksLimit: innerWidth < 576 ? 4 : 8}},
          y: {beginAtZero: true, suggestedMax: maxBytes === 0 ? 1 : undefined, border: {display: false},
            grid: {color: border}, ticks: {color: muted, maxTicksLimit: 6, callback: value => format(value)}}
        }
      }
    });
  } catch (error) { showError(); console.error('Traffic chart could not be initialized.', error); }
})();
