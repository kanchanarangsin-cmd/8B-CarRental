(() => {
  const menus = [...document.querySelectorAll('.rental-action-menu')];
  const moreButtons = [...document.querySelectorAll('.rental-more-button')];
  const backdrop = document.getElementById('rentalDeleteBackdrop');
  const modal = backdrop?.querySelector('.rental-delete-modal');
  const idTarget = document.getElementById('deleteRentalId');
  const confirmButton = document.getElementById('confirmRentalDelete');
  const cancelButton = document.getElementById('cancelRentalDelete');
  let deleteUrl = '';
  let returnFocusTo = null;
  function closeMenus(exceptId = '') { menus.forEach(menu => { if (menu.id !== exceptId) menu.hidden = true; }); moreButtons.forEach(button => { if (button.getAttribute('aria-controls') !== exceptId) button.setAttribute('aria-expanded', 'false'); }); }
  moreButtons.forEach(button => button.addEventListener('click', () => { const menuId = button.getAttribute('aria-controls'); const menu = document.getElementById(menuId); const nextHidden = !menu.hidden; closeMenus(menuId); menu.hidden = nextHidden; button.setAttribute('aria-expanded', String(!nextHidden)); if (!nextHidden) menu.querySelector('.rental-action-item:not([aria-disabled="true"])')?.focus(); }));
  document.addEventListener('click', event => { if (!event.target.closest('.rental-action-cell')) closeMenus(); });
  document.querySelectorAll('[data-return-disabled="true"]').forEach(item => item.addEventListener('click', event => event.preventDefault()));
  document.querySelectorAll('.rental-action-delete').forEach(button => button.addEventListener('click', () => { deleteUrl = button.dataset.deleteUrl; returnFocusTo = button; idTarget.textContent = button.dataset.rentalId; backdrop.hidden = false; document.body.style.overflow = 'hidden'; cancelButton.focus(); closeMenus(); }));
  function closeModal() { backdrop.hidden = true; document.body.style.overflow = ''; deleteUrl = ''; returnFocusTo?.focus(); }
  cancelButton?.addEventListener('click', closeModal);
  backdrop?.addEventListener('click', event => { if (event.target === backdrop) closeModal(); });
  confirmButton?.addEventListener('click', () => { if (!deleteUrl) return; confirmButton.disabled = true; confirmButton.textContent = 'กำลังลบ'; window.location.assign(deleteUrl); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') { if (backdrop && !backdrop.hidden) closeModal(); else closeMenus(); } if (event.key === 'Tab' && backdrop && !backdrop.hidden && modal) { const focusable = [...modal.querySelectorAll('button:not([disabled]), a[href]')]; if (!focusable.length) return; const first = focusable[0], last = focusable[focusable.length - 1]; if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); } } });
})();
