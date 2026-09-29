<div class="login">
  <a class="login-portal-link" href="<?= h($portalUrl) ?>">&larr; Kembali ke Portal</a>

  <div class="login-brand">
    <img src="/assets/cosmax-idn-logo.jpg" alt="COSMAX Indonesia">
    <h1>QAC Trial Validation</h1>
    <p>Trial Validation System</p>
  </div>

  <form method="post">
    <?php csrf_field(); ?>

    <label>Email
      <input name="email" type="email" required placeholder="name@company.com">
    </label>

    <label>Password
      <input name="password" type="password" required placeholder="Password">
    </label>

    <button>Login</button>
  </form>

  <p class="muted">Gunakan akun yang diberikan Admin.</p>
</div>

<style>
.login-portal-link {
  display: inline-block;
  margin-bottom: 12px;
  font-size: 13px;
  color: #6b7280;
  text-decoration: none;
}
.login-portal-link:hover {
  color: #111827;
  text-decoration: underline;
}
.login-brand img {
  width: 110px;      /* ukuran logo */
  height: auto;      /* menjaga proporsi */
  display: block;
  margin: 0 auto 15px;
}
</style>