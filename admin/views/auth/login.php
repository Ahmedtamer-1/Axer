<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Axer CMS — Admin Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .login-card { background: #1e293b; padding: 2.5rem; border-radius: 0.75rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); width: 100%; max-width: 400px; border: 1px solid #334155; }
        .logo-title { text-align: center; margin-bottom: 2rem; }
        .logo-title h1 { margin: 0; font-size: 2rem; font-weight: 700; color: #6366f1; letter-spacing: -0.025em; }
        .logo-title p { margin: 0.25rem 0 0; color: #94a3b8; font-size: 0.875rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: #cbd5e1; }
        input[type="email"], input[type="password"] { width: 100%; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; font-size: 0.95rem; transition: border-color 0.2s; }
        input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.25); }
        .btn { display: inline-block; width: 100%; padding: 0.875rem; background: #6366f1; color: white; border: none; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; box-sizing: border-box; margin-top: 0.5rem; transition: background 0.2s; }
        .btn:hover { background: #4f46e5; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 0.875rem 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.875rem; text-align: center; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-title">
            <h1>Axer CMS</h1>
            <p>Admin Control Panel</p>
        </div>
        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="/admin/login">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Axer\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required autofocus placeholder="admin@example.com">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn">Sign In</button>
        </form>
    </div>
</body>
</html>
