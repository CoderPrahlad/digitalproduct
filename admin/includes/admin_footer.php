</main>
</div><!-- /admin-layout -->

<div id="toast-container" class="toast-container"></div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>

<script>
function toggleAdminSidebar() {
  var s = document.getElementById('adminSidebar');
  var o = document.getElementById('adminOverlay');
  s.classList.toggle('open');
  o.classList.toggle('open');
  document.body.style.overflow = s.classList.contains('open') ? 'hidden' : '';
}
document.querySelectorAll('.sidebar-nav a').forEach(function(a) {
  a.addEventListener('click', function() {
    if (window.innerWidth <= 900) toggleAdminSidebar();
  });
});
</script>
</body>
</html>