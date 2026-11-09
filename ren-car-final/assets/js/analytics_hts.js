(() => {
  const form = document.getElementById('analyticsFilterForm');
  if (!form) return;
  const topics = [...document.querySelectorAll('.hts-topic-button')];
  const groups = [...document.querySelectorAll('.hts-filter-group')];
  const searchButton = document.getElementById('analyticsSearchButton');
  const resetButton = document.getElementById('analyticsResetButton');
  const error = document.getElementById('analyticsFilterError');
  const resultState = document.getElementById('analyticsResultState');
  const results = document.getElementById('analyticsResults');
  const toast = document.getElementById('analyticsToast');
  let activeTopic = document.querySelector('.hts-topic-button.is-active')?.dataset.topic || 'customer_id';

  function activate(topic, moveFocus = false) {
    activeTopic = topic;
    topics.forEach(button => {
      const active = button.dataset.topic === topic;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', String(active));
      button.tabIndex = active ? 0 : -1;
    });
    groups.forEach(group => {
      const active = group.dataset.filter === topic;
      group.hidden = !active;
      group.querySelectorAll('select, input').forEach(control => { control.disabled = !active; });
      if (active && moveFocus) group.querySelector('select, input')?.focus();
    });
    error.hidden = true;
  }

  topics.forEach(button => button.addEventListener('click', () => activate(button.dataset.topic, true)));
  form.addEventListener('submit', event => {
    const activeControl = form.querySelector(`[name="${activeTopic}"]`);
    if (!activeControl || activeControl.value === 'all' || activeControl.value === '') {
      event.preventDefault();
      error.textContent = `กรุณาเลือก${activeControl?.dataset.label || 'เงื่อนไข'}ก่อนค้นหา`;
      error.hidden = false;
      activeControl?.focus();
      return;
    }
    searchButton.disabled = true;
    searchButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2" aria-hidden="true"></i>กำลังค้นหา';
  });
  resetButton.addEventListener('click', () => {
    form.querySelectorAll('select').forEach(control => { control.value = 'all'; });
    form.querySelectorAll('input').forEach(control => { control.value = ''; });
    activate('customer_id', true);
    results.hidden = true;
    resultState.innerHTML = '<strong>ล้างตัวกรองแล้ว</strong> กรุณาเลือกหัวข้อและเงื่อนไขเพื่อเริ่มค้นหาใหม่';
    toast.textContent = 'ล้างค่าตัวกรองเรียบร้อยแล้ว';
    toast.hidden = false;
    window.setTimeout(() => { toast.hidden = true; }, 3000);
  });
  activate(activeTopic);
})();
