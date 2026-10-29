const sidebarToggle = document.getElementById('malio-sidebar-toggle');
const desktopSidebar = window.matchMedia('(min-width:1025px)');
function syncSidebarState() {
 const expanded = desktopSidebar.matches ? !document.body.classList.contains('sidebar-mini') : document.body.classList.contains('malio-sidebar-open');
 sidebarToggle?.setAttribute('aria-expanded', String(expanded));
}
function closeSidebar() {
 document.body.classList.remove('malio-sidebar-open');
 syncSidebarState();
}
sidebarToggle?.addEventListener('click', function () {
 document.body.classList.toggle(desktopSidebar.matches ? 'sidebar-mini' : 'malio-sidebar-open');
 syncSidebarState();
});
syncSidebarState();
desktopSidebar.addEventListener('change', () => {
 document.body.classList.remove('sidebar-mini');
 closeSidebar();
});
document.querySelectorAll('.sidebar-menu a').forEach(a => {
 a.setAttribute('aria-label', a.textContent.trim());
 a.setAttribute('title', a.textContent.trim());
 if (a.pathname === location.pathname || (a.pathname === '/user/tutorial' && location.pathname.startsWith('/user/docs')) || (a.pathname === '/user/code' && location.pathname === '/user/money')) {
  a.classList.add('active');
  a.setAttribute('aria-current', 'page');
 }
});
const accountMenu = document.querySelector('.ef-account-menu');
document.addEventListener('click', event => { if (accountMenu && !accountMenu.contains(event.target)) accountMenu.open = false; });
document.addEventListener('keydown', event => {
 if (event.key === 'Escape') {
  closeSidebar();
  if (accountMenu?.open) { accountMenu.open = false; accountMenu.querySelector('summary')?.focus(); }
 }
});
document.querySelector('.page-wrapper')?.addEventListener('click', closeSidebar);
