(() => {
  const dialog = document.getElementById('traffic-range-dialog');
  if (!dialog) return;
  const config = JSON.parse(document.getElementById('traffic-calendar-data').textContent);
  const form = document.getElementById('traffic-range-form');
  const start = document.getElementById('traffic-range-start');
  const end = document.getElementById('traffic-range-end');
  const kind = document.getElementById('traffic-range-kind');
  const hint = document.getElementById('traffic-range-hint');
  const preview = document.getElementById('traffic-range-preview');
  const calendarEl = document.getElementById('traffic-range-calendar');
  const submit = form.querySelector('[type="submit"]');
  const presets = [...form.querySelectorAll('[data-preset]')];
  const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const parse = value => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
    const [y, m, d] = value.split('-').map(Number);
    const date = new Date(y, m - 1, d, 12);
    return iso(date) === value ? date : null;
  };
  const day = (date, amount) => { const d = new Date(date); d.setDate(d.getDate() + amount); return d; };
  const months = (date, amount) => {
    const d = new Date(date); const original = d.getDate();
    d.setDate(1); d.setMonth(d.getMonth() + amount);
    d.setDate(Math.min(original, new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate()));
    return d;
  };
  const today = parse(config.today);
  let picker;
  function validate() {
    const a = parse(start.value); const b = parse(end.value);
    let error = '';
    if (!a || !b) error = '请填写有效日期，格式为 YYYY-MM-DD。';
    else if (b < a) error = '结束日期不能早于开始日期。';
    else if (b > today) error = '不能选择未来日期。';
    else if (day(b, 1) > months(a, 6)) error = '单次最多6个自然月，请缩短日期范围。';
    submit.disabled = Boolean(error);
    hint.textContent = error || '单次最多6个自然月，不含未来日期。';
    hint.classList.toggle('is-error', Boolean(error));
    preview.textContent = error ? '' : kind.value === 'today' ? '今天 00:00 至现在 · 按小时'
      : kind.value === 'yesterday' ? '昨天 00:00 至24:00 · 按小时'
        : `${start.value} 至 ${end.value}${start.value === end.value ? ' · 按小时' : ''}`;
    presets.forEach(button => {
      const active = button.dataset.preset === kind.value;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', String(active));
    });
    return !error;
  }
  function syncCalendar() {
    if (!picker) return;
    picker.calendar.set({displayDateMin: null, displayDateMax: null});
    if (parse(start.value) && parse(end.value) && validate()) {
      picker.setSelectedDates([start.value, end.value]);
      let visible = parse(start.value);
      // 所选范围位于当前月时，让显示的两个月份都保持有效。
      if (visible.getFullYear() === today.getFullYear() && visible.getMonth() === today.getMonth()) {
        visible = months(visible, -1);
      }
      picker.calendar.set({selectedYear: visible.getFullYear(), selectedMonth: visible.getMonth()}, {dates: false, month: true, year: true});
    }
  }
  function initCalendar() {
    if (picker) return;
    if (!window.tabler?.Datepicker || !window.VanillaCalendarPro) {
      calendarEl.textContent = '日历暂时无法加载，仍可在上方输入日期查询。';
      return;
    }
    picker = new tabler.Datepicker(calendarEl, {
      inline: true, selectionMode: 'multiple-ranged', displayMonthsCount: 2,
      locale: 'zh-CN', dateMax: config.today, datepickerTheme: 'light',
      selectedDates: [start.value, end.value],
      vcpOptions: {dateToday: config.today, enableEdgeDatesOnly: true, animation: false,
        labels: {application: '日期范围日历', navigation: '切换月份', month: '选择月份，当前月份：',
          year: '选择年份，当前年份：', months: '月份列表', years: '年份列表', week: '星期',
          dates: '当月日期', arrowNext: {month: '下个月', year: '后一组年份', week: '下一周'},
          arrowPrev: {month: '上个月', year: '前一组年份', week: '上一周'}}}
    });
    calendarEl.addEventListener('change.bs.datepicker', event => {
      const dates = [...event.dates].sort();
      if (!dates.length) return;
      kind.value = 'custom'; start.value = dates[0]; end.value = dates[dates.length - 1];
      if (dates.length === 1) {
        const anchor = parse(dates[0]);
        const max = iso(day(months(anchor, 6), -1));
        // 允许反向选择，最终校验会处理月底日期的边界调整。
        picker.calendar.set({displayDateMin: iso(day(months(anchor, -6), 1)),
          displayDateMax: max < config.today ? max : null}, {dates: false});
      } else picker.calendar.set({displayDateMin: null, displayDateMax: null}, {dates: false});
      validate();
    });
  }
  document.getElementById('traffic-custom-toggle').addEventListener('click', () => {
    start.value = config.start; end.value = config.end;
    kind.value = presets.some(button => button.dataset.preset === config.range) ? config.range : 'custom';
    dialog.showModal(); initCalendar(); syncCalendar(); validate();
  });
  form.querySelectorAll('[data-range-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const box = dialog.getBoundingClientRect();
    if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
  });
  presets.forEach(button => button.addEventListener('click', () => {
    kind.value = button.dataset.preset;
    const last = kind.value === 'yesterday' ? day(today, -1) : today;
    const first = kind.value === '3m' || kind.value === '6m'
      ? months(day(today, 1), kind.value === '3m' ? -3 : -6) : last;
    start.value = iso(first); end.value = iso(last); syncCalendar(); validate();
  }));
  [start, end].forEach(input => input.addEventListener('input', () => {
    kind.value = 'custom'; validate();
  }));
  [start, end].forEach(input => input.addEventListener('change', () => { syncCalendar(); validate(); }));
  form.addEventListener('submit', event => { if (!validate()) event.preventDefault(); });
})();
