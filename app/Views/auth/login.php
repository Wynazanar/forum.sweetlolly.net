<?php
$error = $error ?? null;
$login = $login ?? '';
?>
<style>
.auth-page { max-width: 420px; margin: 48px auto; padding: 0 16px 48px; }
.auth-card {
    background: var(--card-bg, #1a1a1f);
    border: 1px solid var(--border, #2a2a32);
    border-radius: 14px;
    padding: 28px 24px;
}
.auth-card h1 { margin: 0 0 8px; font-size: 1.4rem; }
.auth-card .lead { color: var(--muted, #999); margin-bottom: 20px; font-size: 14px; }
.auth-card label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; }
.auth-card input[type=text],
.auth-card input[type=password] {
    width: 100%; padding: 10px 12px; border-radius: 8px;
    border: 1px solid var(--border, #2a2a32);
    background: var(--input-bg, #121216); color: inherit; font: inherit;
    margin-bottom: 14px;
}
.auth-card .btn-primary {
    width: 100%; background: #7c5cfc; color: #fff; border: none;
    padding: 11px 16px; border-radius: 8px; font-weight: 600; cursor: pointer;
}
.auth-error {
    background: rgba(248,81,73,.12); border: 1px solid rgba(248,81,73,.35);
    color: #f85149; padding: 10px 12px; border-radius: 8px; margin-bottom: 14px; font-size: 14px;
}
.auth-hint { margin-top: 16px; font-size: 12px; color: var(--muted, #888); line-height: 1.45; }
</style>
<main class="auth-page">
    <div class="auth-card">
        <h1>Вход</h1>
        <p class="lead">Войдите, чтобы подавать заявки и пользоваться форумом.</p>

        <?php if ($error): ?>
            <div class="auth-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('/login/submit') ?>">
            <?php if (!empty($_GET['redirect'])): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect']) ?>">
            <?php endif; ?>
            <label for="login">Логин или email</label>
            <input type="text" id="login" name="login" required autocomplete="username"
                   value="<?= htmlspecialchars($login) ?>" placeholder="Admin">

            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" required autocomplete="current-password"
                   placeholder="••••••••">

            <button type="submit" class="btn-primary">Войти</button>
        </form>

        <p class="auth-hint">
            Тестовые аккаунты (если не меняли сиды):<br>
            <code>Admin</code> / <code>password</code> — админ<br>
            <code>Moderator</code> / <code>password</code> — модер<br>
            Обычный пользователь — смотри таблицу <code>users</code>
        </p>
    </div>
</main>
