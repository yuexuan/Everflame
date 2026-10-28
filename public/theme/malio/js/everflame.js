const sidebarToggle = document.getElementById('malio-sidebar-toggle');
function closeSidebar() {
 document.body.classList.remove('malio-sidebar-open');
 sidebarToggle?.setAttribute('aria-expanded', 'false');
}
sidebarToggle?.addEventListener('click', function () {
 this.setAttribute('aria-expanded', String(document.body.classList.toggle('malio-sidebar-open')));
});
document.querySelectorAll('.sidebar-menu a').forEach(a => {
 if (a.pathname === location.pathname) {
  a.classList.add('active');
  a.setAttribute('aria-current', 'page');
 }
});
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeSidebar(); });
document.querySelector('.page-wrapper')?.addEventListener('click', closeSidebar);
