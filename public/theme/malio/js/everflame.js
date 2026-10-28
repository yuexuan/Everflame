document.getElementById('malio-sidebar-toggle')?.addEventListener('click', function () {
 this.setAttribute('aria-expanded', String(document.body.classList.toggle('malio-sidebar-open')));
});
document.querySelectorAll('.sidebar-menu a').forEach(a => { if(a.pathname === location.pathname) a.classList.add('active'); });
