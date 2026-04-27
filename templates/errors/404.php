<?php
/** @var \DigiSecure\Kernel\View\ViewRenderer|null $view */
$e = \DigiSecure\Kernel\View\ViewRenderer::e(...);
$dashboardUrl = \DigiSecure\Kernel\Http\BasePath::prepend('/dashboard');
$loginUrl = \DigiSecure\Kernel\Http\BasePath::prepend('/login');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>404 | Pagina no encontrada — DigiSecure</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YcnS/1p8Rbdc5E3QYkQ5jU3TmgE7pC7dMf1" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --ds-tone: #2563eb; --ds-tone-bg: rgba(37,99,235,.12); }
        * { box-sizing: border-box; }
        body {
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex; align-items: center; justify-content: center;
            padding: 2rem;
            color: #16324f;
            background:
                radial-gradient(circle at top left, rgba(47,128,237,.15), transparent 35%),
                radial-gradient(circle at bottom right, rgba(14,165,164,.12), transparent 32%),
                #f4f7fb;
        }
        .ds-error-card {
            width: 100%; max-width: 560px;
            background: #fff;
            border-radius: 20px;
            padding: 2.5rem 2.25rem 2rem;
            text-align: center;
            box-shadow: 0 24px 60px rgba(9,29,53,.12);
            border: 1px solid rgba(21,48,79,.08);
        }
        .ds-error-brand {
            display: inline-flex; align-items: center; gap: .5rem;
            font-weight: 700; font-size: .85rem; letter-spacing: .04em;
            color: #5f7288;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }
        .ds-error-brand .mark {
            width: 28px; height: 28px; display: inline-flex;
            align-items: center; justify-content: center;
            border-radius: 8px; color: #fff;
            background: linear-gradient(135deg, #2f80ed, #0ea5a4);
        }
        .ds-error-illustration {
            width: 112px; height: 112px;
            margin: 0 auto 1rem;
            display: flex; align-items: center; justify-content: center;
            border-radius: 24px;
            background: var(--ds-tone-bg);
            color: var(--ds-tone);
            font-size: 3rem;
        }
        .ds-error-code {
            font-family: ui-monospace, "SF Mono", Consolas, monospace;
            font-weight: 700; font-size: .78rem;
            letter-spacing: .15em;
            color: var(--ds-tone);
            background: var(--ds-tone-bg);
            padding: .25rem .6rem;
            border-radius: 999px;
            display: inline-block;
            margin-bottom: .75rem;
        }
        .ds-error-title {
            font-size: 1.65rem; font-weight: 700;
            margin: 0 0 .5rem;
        }
        .ds-error-lead {
            color: #5f7288;
            margin: 0 auto 1.5rem;
            max-width: 420px;
            line-height: 1.55;
        }
        .ds-error-actions {
            display: flex; gap: .6rem; justify-content: center; flex-wrap: wrap;
        }
        .ds-btn {
            display: inline-flex; align-items: center; gap: .45rem;
            padding: .6rem 1.1rem;
            border-radius: 10px;
            font-weight: 600; font-size: .88rem;
            text-decoration: none;
            transition: transform .12s, box-shadow .12s;
            border: 1px solid transparent;
        }
        .ds-btn:hover { transform: translateY(-1px); }
        .ds-btn-primary {
            background: linear-gradient(135deg, #2f80ed, #1e63c5);
            color: #fff;
            box-shadow: 0 6px 18px rgba(47,128,237,.35);
        }
        .ds-btn-primary:hover { color: #fff; box-shadow: 0 10px 24px rgba(47,128,237,.45); }
        .ds-btn-ghost {
            background: #f4f7fb;
            color: #16324f;
            border-color: rgba(21,48,79,.08);
        }
        .ds-btn-ghost:hover { background: #eaeff7; color: #16324f; }
        .ds-error-footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eef1f5;
            font-size: .78rem;
            color: #8293a7;
        }
    </style>
</head>
<body>
    <div class="ds-error-card">
        <div class="ds-error-brand">
            <span class="mark"><i class="bi bi-shield-lock-fill"></i></span>
            DigiSecure
        </div>
        <div class="ds-error-illustration">
            <i class="bi bi-compass"></i>
        </div>
        <span class="ds-error-code">ERROR 404</span>
        <h1 class="ds-error-title">Esta pagina se extravio</h1>
        <p class="ds-error-lead">
            La ruta que intentas visitar no existe o fue movida.
            Revisa el enlace o regresa al panel principal para continuar.
        </p>
        <div class="ds-error-actions">
            <a href="<?= $e($dashboardUrl) ?>" class="ds-btn ds-btn-primary">
                <i class="bi bi-arrow-left"></i>Volver al panel
            </a>
            <a href="<?= $e($loginUrl) ?>" class="ds-btn ds-btn-ghost">
                <i class="bi bi-box-arrow-in-right"></i>Ir al login
            </a>
        </div>
        <div class="ds-error-footer">
            Si crees que esto es un error, reporta el enlace al administrador del sistema.
        </div>
    </div>
</body>
</html>
