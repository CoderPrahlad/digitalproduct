</main>

<!-- ===== Newsletter Subscribe Box ===== -->
<section class="newsletter-section">
  <div class="container" style="max-width:760px">
    <div class="newsletter-box">
      <div style="font-size:32px;margin-bottom:10px">📬</div>
      <h3 style="font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:800;margin-bottom:5px">Stay in the loop</h3>
      <p style="color:var(--muted);font-size:13px;margin-bottom:18px">Get notified when new products drop — no spam, unsubscribe anytime.</p>
      <div class="nl-form-wrap">
        <input type="email" id="nlEmail" placeholder="your@email.com" class="nl-input">
        <button type="button" id="nlBtn" class="btn btn-primary nl-btn">Subscribe</button>
      </div>
      <div id="nlMsg" style="margin-top:10px;font-size:13px;min-height:18px"></div>
    </div>
  </div>
</section>
<style>
.newsletter-section{padding:48px 16px 20px;text-align:center}
.newsletter-box{background:linear-gradient(135deg,rgba(124,58,237,.12),rgba(139,92,246,.06));border:1px solid rgba(124,58,237,.25);border-radius:18px;padding:36px 28px}
.nl-form-wrap{display:flex;gap:8px;max-width:400px;margin:0 auto}
.nl-input{flex:1;background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 14px;color:var(--text);font-size:14px;outline:none;transition:border .2s}
.nl-input:focus{border-color:var(--primary)}
.nl-btn{white-space:nowrap;padding:10px 20px}
@media(max-width:480px){.nl-form-wrap{flex-direction:column}}
</style>
<script>
(function(){
  var btn = document.getElementById('nlBtn');
  var inp = document.getElementById('nlEmail');
  var msg = document.getElementById('nlMsg');
  if (!btn) return;
  btn.addEventListener('click', function(){
    var email = inp ? inp.value.trim() : '';
    if (!email) { if(msg) msg.innerHTML = '<span style="color:var(--danger)">Please enter your email.</span>'; return; }
    btn.disabled = true; btn.textContent = 'Subscribing...';
    var fd = new FormData();
    fd.append('email', email);
    fetch(window.SITE_URL + '/api/newsletter_subscribe.php', { method:'POST', body:fd })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (msg) msg.innerHTML = d.ok
          ? '<span style="color:#34d399">' + d.msg + '</span>'
          : '<span style="color:var(--danger)">' + d.msg + '</span>';
        if (d.ok && inp) inp.value = '';
        btn.disabled = false; btn.textContent = 'Subscribe';
      })
      .catch(function(){ if(msg) msg.innerHTML = '<span style="color:var(--danger)">Error. Try again.</span>'; btn.disabled=false; btn.textContent='Subscribe'; });
  });
})();
</script>

<footer class="site-footer">
  <div class="container">
    <p style="margin-bottom:8px">
      <a href="<?= SITE_URL ?>">Home</a> &nbsp;·&nbsp;
      <a href="<?= SITE_URL ?>/#products">Products</a> &nbsp;·&nbsp;
      <a href="<?= SITE_URL ?>/contact.php">Contact</a> &nbsp;·&nbsp;
      <a href="https://wa.me/<?= WA_NUMBER ?>" target="_blank">WhatsApp Support</a> &nbsp;·&nbsp;
      <a href="<?= TG_CHANNEL_URL ?>" target="_blank">Telegram Channel</a> &nbsp;·&nbsp;
      <?php if (defined('YT_CHANNEL_URL') && YT_CHANNEL_URL): ?>
      <a href="<?= YT_CHANNEL_URL ?>" target="_blank">YouTube</a>
      <?php endif; ?>
    </p>
    <p>© <?= date('Y') ?> <?= SITE_NAME ?> — All Rights Reserved</p>
    <p style="margin-top:6px;font-size:13px">
      Owned &amp; developed by
      <a href="<?= OWNER_TG_URL ?>" target="_blank"><?= clean(OWNER_TG_USERNAME) ?></a>
    </p>
  </div>
</footer>
<div id="toast-container" class="toast-container"></div>
<script>window.SITE_URL = <?= json_encode(SITE_URL) ?>;</script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>