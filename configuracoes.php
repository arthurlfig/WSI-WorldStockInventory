<?php
require_once __DIR__ . '/auth.php';
exigirLogin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configurações — WSI</title>
<style>
    :root {
        --bg: #14181f;
        --panel: #1c222c;
        --panel-border: #2b3340;
        --ink: #e8ebf0;
        --ink-dim: #8b96a8;
        --accent: #4fb0a5;
        --accent-dim: #35766f;
        --danger: #e2665a;
        --field-bg: #10141a;
        --radius: 6px;
        --mono: 'JetBrains Mono', 'Courier New', monospace;
        --sans: 'Inter', 'Segoe UI', system-ui, sans-serif;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        min-height: 100vh;
        background:
            linear-gradient(180deg, rgba(79,176,165,0.06), transparent 320px),
            var(--bg);
        color: var(--ink);
        font-family: var(--sans);
    }

    header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 32px;
        border-bottom: 1px solid var(--panel-border);
    }

    header .eyebrow {
        font-family: var(--mono);
        font-size: 11px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--accent);
        margin: 0;
    }

    header nav a {
        color: var(--ink-dim);
        text-decoration: none;
        font-size: 13px;
        margin-left: 18px;
    }

    header nav a:hover,
    header nav a.active { color: var(--accent); }

    main {
        max-width: 640px;
        margin: 0 auto;
        padding: 40px 20px 60px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    main h1 { font-size: 24px; margin: 0 0 4px; }
    main p.sub { color: var(--ink-dim); margin: 0 0 8px; font-size: 13px; }

    .card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius);
        overflow: hidden;
    }

    .card__header {
        padding: 16px 24px;
        border-bottom: 1px solid var(--panel-border);
    }

    .card__eyebrow {
        font-family: var(--mono);
        font-size: 11px;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--accent);
        margin: 0;
    }

    .card__body {
        padding: 6px 24px;
    }

    /* ---------- Linha de opção ---------- */
    .opcao {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 16px 0;
    }

    .opcao:not(:last-child) {
        border-bottom: 1px solid var(--panel-border);
    }

    .opcao .opcao-texto p.titulo {
        margin: 0 0 3px;
        font-size: 14px;
        font-weight: 600;
    }

    .opcao .opcao-texto p.desc {
        margin: 0;
        font-size: 12px;
        color: var(--ink-dim);
    }

    /* ---------- Toggle (switch) ---------- */
    .switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 22px;
        flex-shrink: 0;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .switch .slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background: var(--field-bg);
        border: 1px solid var(--panel-border);
        border-radius: 999px;
        transition: background 0.15s ease;
    }

    .switch .slider::before {
        content: "";
        position: absolute;
        height: 14px;
        width: 14px;
        left: 3px;
        top: 3px;
        background: var(--ink-dim);
        border-radius: 50%;
        transition: transform 0.15s ease, background 0.15s ease;
    }

    .switch input:checked + .slider {
        background: rgba(79,176,165,0.15);
        border-color: var(--accent-dim);
    }

    .switch input:checked + .slider::before {
        transform: translateX(18px);
        background: var(--accent);
    }

    /* ---------- Select simples ---------- */
    select.opcao-select {
        background: var(--field-bg);
        border: 1px solid var(--panel-border);
        color: var(--ink);
        padding: 7px 10px;
        border-radius: var(--radius);
        font-size: 13px;
        font-family: var(--sans);
    }

    select.opcao-select:focus {
        outline: none;
        border-color: var(--accent);
    }

    /* ---------- Botão perigoso ---------- */
    .btn-perigo {
        background: transparent;
        border: 1px solid var(--danger);
        color: var(--danger);
        padding: 8px 14px;
        border-radius: var(--radius);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-perigo:hover {
        background: rgba(226,102,90,0.1);
    }

    .aviso {
        font-size: 12px;
        color: var(--ink-dim);
        font-family: var(--mono);
        text-align: center;
        margin-top: 4px;
    }
</style>
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<main>
    <div>
        <h1>Configurações</h1>
        <p class="sub">Ajustes gerais do sistema.</p>
    </div>

    <!-- Aparência -->
    <div class="card">
        <div class="card__header">
            <p class="card__eyebrow">Aparência</p>
        </div>
        <div class="card__body">
            <div class="opcao">
                <div class="opcao-texto">
                    <p class="titulo">Tema escuro</p>
                    <p class="desc">Usar o tema escuro em todas as telas.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" checked>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="opcao">
                <div class="opcao-texto">
                    <p class="titulo">Densidade compacta</p>
                    <p class="desc">Reduzir espaçamento nas listas e tabelas.</p>
                </div>
                <label class="switch">
                    <input type="checkbox">
                    <span class="slider"></span>
                </label>
            </div>

            <div class="opcao">
                <div class="opcao-texto">
                    <p class="titulo">Idioma</p>
                    <p class="desc">Idioma usado na interface.</p>
                </div>
                <select class="opcao-select">
                    <option>Português (Brasil)</option>
                    <option>English</option>
                    <option>Español</option>
                </select>
            </div>
        </div>
    </div>
    <!-- Preferências regionais -->
    <div class="card">
        <div class="card__header">
            <p class="card__eyebrow">Regional</p>
        </div>
        <div class="card__body">
            <div class="opcao">
                <div class="opcao-texto">
                    <p class="titulo">Formato de data</p>
                    <p class="desc">Como as datas aparecem nas telas.</p>
                </div>
                <select class="opcao-select">
                    <option>DD/MM/AAAA</option>
                    <option>MM/DD/AAAA</option>
                    <option>AAAA-MM-DD</option>
                </select>
            </div>

            <div class="opcao">
                <div class="opcao-texto">
                    <p class="titulo">Moeda</p>
                    <p class="desc">Moeda usada nos preços do estoque.</p>
                </div>
                <select class="opcao-select">
                    <option>Real (R$)</option>
                    <option>Dólar (US$)</option>
                    <option>Euro (€)</option>
                </select>
            </div>
        </div>
    </div>

</main>

</body>
</html>