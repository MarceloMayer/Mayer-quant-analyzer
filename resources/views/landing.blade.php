@php($registerUrl = url('/admin/register'))
@php($loginUrl = url('/admin/login'))
@php($appUrl = url('/admin'))
<!DOCTYPE html>
<html lang="pt-BR" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mayer Quant Analyzer — do backtest do MetaTrader 5 ao portfólio robusto</title>
    <meta name="description" content="Importe os resultados dos seus robôs do MetaTrader 5 e transforme milhares de trades em métricas de risco, curadoria de execuções e portfólios diversificados.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @verbatim
    <style>
        :root {
            color-scheme: dark;
            --bg: #070b16;
            --bg-soft: #0b1120;
            --panel: rgba(20, 28, 46, 0.66);
            --panel-solid: #111a2e;
            --border: rgba(148, 163, 184, 0.14);
            --border-strong: rgba(148, 163, 184, 0.28);
            --text: #e8edf6;
            --muted: #93a1b8;
            --muted-dim: #6b7890;
            --brand: #3b82f6;
            --brand-2: #22d3ee;
            --brand-3: #6366f1;
            --pos: #34d399;
            --neg: #fb7185;
            --radius: 16px;
            --radius-lg: 22px;
            --maxw: 1180px;
            --font: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        * , *::before, *::after { box-sizing: border-box; }

        html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: var(--font);
            color: var(--text);
            background: var(--bg);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -2;
            background:
                radial-gradient(900px 600px at 78% -8%, rgba(59, 130, 246, 0.22), transparent 60%),
                radial-gradient(760px 520px at 8% 4%, rgba(99, 102, 241, 0.16), transparent 58%),
                radial-gradient(1100px 720px at 50% 110%, rgba(34, 211, 238, 0.10), transparent 60%),
                var(--bg);
        }

        body::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            background-image:
                linear-gradient(rgba(148, 163, 184, 0.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.045) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: radial-gradient(ellipse 100% 70% at 50% 0%, #000 55%, transparent 100%);
        }

        img { max-width: 100%; display: block; }
        a { color: inherit; text-decoration: none; }

        .container {
            width: 100%;
            max-width: var(--maxw);
            margin: 0 auto;
            padding-inline: clamp(1.15rem, 4vw, 2.5rem);
        }

        /* ---------- Nav ---------- */
        .nav {
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            background: rgba(7, 11, 22, 0.72);
            border-bottom: 1px solid var(--border);
        }
        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            height: 68px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            font-size: 1.02rem;
        }
        .brand-mark {
            width: 30px; height: 30px;
            border-radius: 9px;
            display: grid; place-items: center;
            background: linear-gradient(140deg, var(--brand), var(--brand-3));
            box-shadow: 0 6px 20px -6px rgba(59, 130, 246, 0.7);
            flex: none;
        }
        .brand-mark svg { width: 17px; height: 17px; }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 2rem;
            font-size: 0.92rem;
            color: var(--muted);
        }
        .nav-links a:hover { color: var(--text); }
        .nav-cta { display: flex; align-items: center; gap: 0.6rem; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font: inherit;
            font-weight: 600;
            font-size: 0.94rem;
            padding: 0.7rem 1.15rem;
            border-radius: 11px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.2s ease;
            white-space: nowrap;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--brand), var(--brand-3));
            color: #fff;
            box-shadow: 0 10px 30px -10px rgba(59, 130, 246, 0.75);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 16px 40px -12px rgba(59, 130, 246, 0.85); }
        .btn-ghost {
            background: rgba(148, 163, 184, 0.06);
            border-color: var(--border-strong);
            color: var(--text);
        }
        .btn-ghost:hover { background: rgba(148, 163, 184, 0.13); transform: translateY(-2px); }
        .btn-lg { padding: 0.9rem 1.5rem; font-size: 1rem; }

        /* ---------- Hero ---------- */
        .hero { padding-block: clamp(3.5rem, 8vw, 6.5rem) clamp(3rem, 7vw, 5rem); }
        .hero-grid {
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: clamp(2rem, 5vw, 4rem);
            align-items: center;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.76rem;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--brand-2);
            padding: 0.4rem 0.8rem;
            border: 1px solid var(--border-strong);
            border-radius: 999px;
            background: rgba(34, 211, 238, 0.06);
        }
        .eyebrow .dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--brand-2);
            box-shadow: 0 0 10px var(--brand-2);
        }
        h1 {
            margin: 1.3rem 0 0;
            font-size: clamp(2.3rem, 5.4vw, 3.75rem);
            line-height: 1.04;
            letter-spacing: -0.028em;
            font-weight: 700;
        }
        .gradient-text {
            background: linear-gradient(110deg, var(--brand-2), var(--brand) 45%, var(--brand-3));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .hero p.lead {
            margin: 1.35rem 0 0;
            font-size: clamp(1.02rem, 1.5vw, 1.18rem);
            color: var(--muted);
            max-width: 33em;
        }
        .hero-actions {
            margin-top: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
        }
        .hero-note {
            margin-top: 1.3rem;
            font-size: 0.88rem;
            color: var(--muted-dim);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .hero-note svg { width: 15px; height: 15px; color: var(--pos); flex: none; }

        /* ---------- Hero card ---------- */
        .hero-card {
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-lg);
            background: linear-gradient(180deg, rgba(23, 32, 52, 0.9), rgba(11, 17, 32, 0.9));
            box-shadow: 0 40px 90px -40px rgba(0, 0, 0, 0.8), inset 0 1px 0 rgba(255, 255, 255, 0.04);
            padding: 1.25rem;
            position: relative;
        }
        .hero-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.25rem 0.4rem 0.9rem;
            font-size: 0.8rem;
            color: var(--muted-dim);
        }
        .hero-card-head .dots { display: flex; gap: 0.35rem; }
        .hero-card-head .dots i { width: 9px; height: 9px; border-radius: 50%; background: rgba(148,163,184,0.25); }
        .chart-wrap {
            position: relative;
            border-radius: 14px;
            background: rgba(7, 11, 22, 0.55);
            border: 1px solid var(--border);
            padding: 0.75rem;
            overflow: hidden;
        }
        .chart-wrap svg { width: 100%; height: auto; display: block; }
        .equity-line {
            fill: none;
            stroke: url(#eqGrad);
            stroke-width: 2.4;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 1600;
            stroke-dashoffset: 1600;
            animation: draw 2.6s ease-out 0.2s forwards;
        }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        .equity-dot { animation: pulse 2.4s ease-in-out 2.6s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.35; } }

        .tiles {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.7rem;
            margin-top: 0.85rem;
        }
        .tile {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.75rem 0.85rem;
            background: rgba(148, 163, 184, 0.035);
        }
        .tile .t-label { font-size: 0.72rem; color: var(--muted-dim); letter-spacing: 0.03em; }
        .tile .t-value { font-size: 1.32rem; font-weight: 700; margin-top: 0.2rem; letter-spacing: -0.02em; }
        .tile .t-value.pos { color: var(--pos); }
        .tile .t-value.brand { color: var(--brand-2); }

        /* ---------- Stats strip ---------- */
        .strip {
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            background: rgba(11, 17, 32, 0.4);
        }
        .strip-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1px;
            background: var(--border);
        }
        .strip-item {
            background: var(--bg);
            padding: 1.6rem 1.2rem;
            text-align: center;
        }
        .strip-item .s-value {
            font-size: clamp(1.3rem, 2.4vw, 1.7rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #fff;
        }
        .strip-item .s-label { font-size: 0.82rem; color: var(--muted); margin-top: 0.3rem; }

        /* ---------- Sections ---------- */
        section { padding-block: clamp(4rem, 9vw, 7rem); }
        .section-head { max-width: 660px; margin: 0 auto clamp(2.5rem, 5vw, 3.75rem); text-align: center; }
        .section-head .eyebrow { color: var(--brand); background: rgba(59, 130, 246, 0.07); }
        .section-head .eyebrow .dot { background: var(--brand); box-shadow: 0 0 10px var(--brand); }
        h2 {
            margin: 1rem 0 0;
            font-size: clamp(1.8rem, 3.6vw, 2.6rem);
            line-height: 1.12;
            letter-spacing: -0.025em;
            font-weight: 700;
        }
        .section-head p { margin: 0.9rem 0 0; color: var(--muted); font-size: 1.03rem; }

        /* ---------- Feature grid ---------- */
        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.1rem;
        }
        .feature {
            position: relative;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem 1.4rem;
            background: var(--panel);
            transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease;
            overflow: hidden;
        }
        .feature:hover {
            transform: translateY(-4px);
            border-color: var(--border-strong);
            background: rgba(30, 41, 66, 0.72);
        }
        .feature.wide { grid-column: span 3; }
        .f-icon {
            width: 42px; height: 42px;
            border-radius: 11px;
            display: grid; place-items: center;
            background: linear-gradient(140deg, rgba(59,130,246,0.22), rgba(99,102,241,0.14));
            border: 1px solid rgba(99, 102, 241, 0.3);
            margin-bottom: 1rem;
        }
        .f-icon svg { width: 21px; height: 21px; color: var(--brand-2); }
        .feature h3 { margin: 0 0 0.45rem; font-size: 1.08rem; font-weight: 650; letter-spacing: -0.01em; }
        .feature p { margin: 0; color: var(--muted); font-size: 0.93rem; }
        .badge {
            position: absolute;
            top: 1.15rem; right: 1.15rem;
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--brand-2);
            border: 1px solid rgba(34, 211, 238, 0.35);
            background: rgba(34, 211, 238, 0.08);
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
        }
        .feature.wide .wide-inner {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 2rem;
            align-items: center;
        }

        /* ---------- Spotlight ---------- */
        .spotlight-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: clamp(2rem, 5vw, 3.5rem);
            align-items: center;
        }
        .spotlight h2 { margin-top: 1rem; }
        .spotlight p.desc { color: var(--muted); margin: 1rem 0 1.75rem; }
        .checklist { list-style: none; padding: 0; margin: 0; display: grid; gap: 0.7rem; }
        .checklist li { display: flex; gap: 0.65rem; align-items: flex-start; font-size: 0.95rem; color: var(--text); }
        .checklist svg { width: 18px; height: 18px; color: var(--pos); flex: none; margin-top: 0.15rem; }

        .panel {
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-lg);
            background: linear-gradient(180deg, rgba(23, 32, 52, 0.8), rgba(11, 17, 32, 0.85));
            padding: 1.5rem;
            box-shadow: 0 40px 80px -44px rgba(0,0,0,0.75);
        }
        .panel-title { font-size: 0.8rem; color: var(--muted-dim); letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 1rem; }
        .formula-row { display: grid; gap: 0.85rem; }
        .fbar { }
        .fbar-head { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem; }
        .fbar-head b { color: var(--brand-2); font-variant-numeric: tabular-nums; }
        .fbar-track { height: 8px; border-radius: 999px; background: rgba(148, 163, 184, 0.12); overflow: hidden; }
        .fbar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--brand), var(--brand-2)); }

        .mock-table { margin-top: 1.5rem; border-top: 1px solid var(--border); }
        .mock-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 0.75rem;
            align-items: center;
            padding: 0.7rem 0.15rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.86rem;
        }
        .mock-row .m-name { color: var(--muted); }
        .mock-row .m-score { font-weight: 700; color: var(--brand-2); font-variant-numeric: tabular-nums; }
        .mock-row .m-dd { color: var(--neg); font-variant-numeric: tabular-nums; font-size: 0.8rem; }
        .mock-row.head { color: var(--muted-dim); font-size: 0.72rem; letter-spacing: 0.06em; text-transform: uppercase; }
        .mock-row.head .m-score, .mock-row.head .m-dd { color: var(--muted-dim); font-weight: 600; }

        /* ---------- Metrics grid ---------- */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        .metric-card {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.3rem;
            background: var(--panel);
        }
        .metric-card h4 { margin: 0 0 0.4rem; font-size: 1rem; font-weight: 650; }
        .metric-card p { margin: 0; font-size: 0.89rem; color: var(--muted); }
        .metric-card .k {
            font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--brand); display: block; margin-bottom: 0.55rem;
        }

        /* ---------- Steps ---------- */
        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.4rem; counter-reset: step; }
        .step {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.6rem 1.4rem;
            background: var(--panel);
            position: relative;
        }
        .step .n {
            width: 34px; height: 34px;
            border-radius: 10px;
            display: grid; place-items: center;
            font-weight: 700;
            background: rgba(59, 130, 246, 0.12);
            border: 1px solid rgba(59, 130, 246, 0.35);
            color: var(--brand-2);
            margin-bottom: 0.9rem;
        }
        .step h3 { margin: 0 0 0.4rem; font-size: 1.1rem; font-weight: 650; }
        .step p { margin: 0; color: var(--muted); font-size: 0.92rem; }
        .step:not(:last-child)::after {
            content: "";
            position: absolute;
            top: 2.5rem; right: -0.95rem;
            width: 1.9rem; height: 1px;
            background: linear-gradient(90deg, var(--border-strong), transparent);
        }

        /* ---------- Diffs ---------- */
        .diffs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem 2rem;
        }
        .diff { display: flex; gap: 0.7rem; align-items: flex-start; padding: 0.85rem 0; border-bottom: 1px solid var(--border); }
        .diff svg { width: 19px; height: 19px; color: var(--brand); flex: none; margin-top: 0.15rem; }
        .diff span { font-size: 0.95rem; color: var(--text); }

        /* ---------- CTA band ---------- */
        .cta-band {
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-lg);
            padding: clamp(2.5rem, 6vw, 4rem);
            text-align: center;
            background:
                radial-gradient(600px 300px at 50% 0%, rgba(59, 130, 246, 0.22), transparent 70%),
                linear-gradient(180deg, rgba(23, 32, 52, 0.85), rgba(11, 17, 32, 0.9));
            box-shadow: 0 40px 90px -44px rgba(0,0,0,0.8);
        }
        .cta-band h2 { margin: 0; }
        .cta-band p { color: var(--muted); margin: 0.9rem auto 0; max-width: 34em; }
        .cta-band .hero-actions { justify-content: center; margin-top: 2rem; }

        /* ---------- Footer ---------- */
        footer {
            border-top: 1px solid var(--border);
            padding-block: 2.5rem;
            color: var(--muted-dim);
            font-size: 0.86rem;
        }
        .footer-inner { display: flex; flex-wrap: wrap; gap: 1rem 2rem; align-items: center; justify-content: space-between; }
        .footer-links { display: flex; gap: 1.5rem; }
        .footer-links a:hover { color: var(--text); }
        .disclaimer { margin-top: 1.25rem; font-size: 0.78rem; color: var(--muted-dim); opacity: 0.75; }

        /* ---------- Reveal animation ---------- */
        .js .reveal { opacity: 0; transform: translateY(18px); transition: opacity 0.7s ease, transform 0.7s ease; }
        .js .reveal.in { opacity: 1; transform: none; }

        @media (prefers-reduced-motion: reduce) {
            .js .reveal { opacity: 1 !important; transform: none !important; }
            .equity-line { animation: none; stroke-dashoffset: 0; }
            .equity-dot { animation: none; }
            html { scroll-behavior: auto; }
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 960px) {
            .hero-grid { grid-template-columns: 1fr; }
            .hero p.lead { max-width: none; }
            .features { grid-template-columns: repeat(2, 1fr); }
            .feature.wide { grid-column: span 2; }
            .feature.wide .wide-inner { grid-template-columns: 1fr; gap: 1.4rem; }
            .spotlight-grid { grid-template-columns: 1fr; }
            .metrics-grid { grid-template-columns: repeat(2, 1fr); }
            .steps { grid-template-columns: 1fr; }
            .step:not(:last-child)::after { display: none; }
            .nav-links { display: none; }
        }
        @media (max-width: 620px) {
            .strip-grid { grid-template-columns: 1fr 1fr; }
            .features { grid-template-columns: 1fr; }
            .feature.wide { grid-column: span 1; }
            .metrics-grid { grid-template-columns: 1fr; }
            .diffs-grid { grid-template-columns: 1fr; }
            .tiles { grid-template-columns: 1fr 1fr; }
            .nav-cta .btn-ghost { display: none; }
            .hero-actions .btn { flex: 1 1 auto; }
        }
    </style>
    @endverbatim
</head>
<body>
    <header class="nav">
        <div class="container nav-inner">
            <a href="/" class="brand">
                <span class="brand-mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
                </span>
                Mayer Quant Analyzer
            </a>
            <nav class="nav-links">
                <a href="#recursos">Recursos</a>
                <a href="#combinacoes">Combinações</a>
                <a href="#metricas">Métricas</a>
                <a href="#como-funciona">Como funciona</a>
            </nav>
            <div class="nav-cta">
                <a href="{{ $loginUrl }}" class="btn btn-ghost">Entrar</a>
                <a href="{{ $registerUrl }}" class="btn btn-primary">Criar conta</a>
            </div>
        </div>
    </header>

    <main>
        <!-- HERO -->
        <section class="hero">
            <div class="container hero-grid">
                <div>
                    <span class="eyebrow"><span class="dot"></span>Análise quantitativa · MetaTrader 5</span>
                    <h1>Do relatório de backtest ao <span class="gradient-text">portfólio robusto</span>.</h1>
                    <p class="lead">
                        Importe os resultados dos seus robôs do MetaTrader 5 e transforme milhares de
                        trades em métricas de risco, curadoria de execuções e portfólios diversificados —
                        tudo em uma única bancada.
                    </p>
                    <div class="hero-actions">
                        <a href="{{ $registerUrl }}" class="btn btn-primary btn-lg">Criar conta grátis</a>
                        <a href="{{ $loginUrl }}" class="btn btn-ghost btn-lg">Entrar no sistema</a>
                    </div>
                    <p class="hero-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        Feito para quem opera mini-índice e mini-dólar de forma sistemática.
                    </p>
                </div>

                <div class="hero-card reveal">
                    <div class="hero-card-head">
                        <span>Curva consolidada · portfólio 3 estratégias</span>
                        <span class="dots"><i></i><i></i><i></i></span>
                    </div>
                    <div class="chart-wrap">
                        <svg viewBox="0 0 520 240" preserveAspectRatio="none" role="img" aria-label="Curva de capital consolidada em tendência de alta">
                            <defs>
                                <linearGradient id="eqGrad" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0" stop-color="#22d3ee"/>
                                    <stop offset="0.55" stop-color="#3b82f6"/>
                                    <stop offset="1" stop-color="#6366f1"/>
                                </linearGradient>
                                <linearGradient id="eqFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stop-color="#3b82f6" stop-opacity="0.28"/>
                                    <stop offset="1" stop-color="#3b82f6" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <line x1="0" y1="60" x2="520" y2="60" stroke="rgba(148,163,184,0.10)"/>
                            <line x1="0" y1="120" x2="520" y2="120" stroke="rgba(148,163,184,0.10)"/>
                            <line x1="0" y1="180" x2="520" y2="180" stroke="rgba(148,163,184,0.10)"/>
                            <path d="M12,214 L52,206 L92,210 L132,182 L172,176 L212,190 L252,150 L292,160 L332,116 L372,132 L412,86 L452,74 L500,44 L500,240 L12,240 Z" fill="url(#eqFill)"/>
                            <path class="equity-line" d="M12,214 L52,206 L92,210 L132,182 L172,176 L212,190 L252,150 L292,160 L332,116 L372,132 L412,86 L452,74 L500,44"/>
                            <circle class="equity-dot" cx="500" cy="44" r="4.5" fill="#22d3ee"/>
                        </svg>
                    </div>
                    <div class="tiles">
                        <div class="tile">
                            <div class="t-label">Consistency Score</div>
                            <div class="t-value brand">87,4<span style="font-size:0.8rem;color:var(--muted-dim)"> / 100</span></div>
                        </div>
                        <div class="tile">
                            <div class="t-label">Lucro / Drawdown</div>
                            <div class="t-value pos">4,7×</div>
                        </div>
                        <div class="tile">
                            <div class="t-label">Correlação média</div>
                            <div class="t-value">0,18</div>
                        </div>
                        <div class="tile">
                            <div class="t-label">Ulcer Index</div>
                            <div class="t-value">3,1</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- STATS STRIP -->
        <div class="strip">
            <div class="container" style="padding-inline:0">
                <div class="strip-grid">
                    <div class="strip-item">
                        <div class="s-value">20+</div>
                        <div class="s-label">métricas calculadas por estratégia</div>
                    </div>
                    <div class="strip-item">
                        <div class="s-value">20.000</div>
                        <div class="s-label">combinações avaliadas por análise</div>
                    </div>
                    <div class="strip-item">
                        <div class="s-value">XLSX + CSV</div>
                        <div class="s-label">relatórios nativos do MetaTrader 5</div>
                    </div>
                    <div class="strip-item">
                        <div class="s-value">SHA-256</div>
                        <div class="s-label">deduplicação de trades por fingerprint</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FEATURES -->
        <section id="recursos">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow"><span class="dot"></span>Recursos</span>
                    <h2>Uma bancada completa para estratégias automatizadas</h2>
                    <p>Cada etapa do fluxo — da importação do relatório à decisão de portfólio — em telas pensadas para quem desenvolve robôs.</p>
                </div>

                <div class="features">
                    <div class="feature reveal">
                        <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></div>
                        <h3>Importação inteligente do MT5</h3>
                        <p>Leia relatórios XLSX e CSV nativos, junte vários períodos do mesmo backtest sob um identificador e deixe a deduplicação por fingerprint evitar trades contados em dobro. O resultado importado é conferido contra a soma dos trades.</p>
                    </div>
                    <div class="feature reveal">
                        <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="8"/><rect x="12" y="6" width="3" height="12"/><rect x="17" y="13" width="3" height="5"/></svg></div>
                        <h3>Análise individual profunda</h3>
                        <p>Resultado líquido, profit factor, payoff, taxa de acerto, sequências de ganhos e perdas, drawdown máximo em valor e percentual, curva de capital, dias sem novo topo e desempenho mês a mês.</p>
                    </div>
                    <div class="feature reveal">
                        <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                        <h3>Curadoria de execuções</h3>
                        <p>Organize backtest principal, in-sample, out-of-sample, custos maiores, testes de estresse e parâmetros vizinhos. A classificação automática sugere aprovar, observar ou reprovar segundo critérios que você define.</p>
                    </div>
                    <div class="feature reveal">
                        <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></div>
                        <h3>Comparação de estratégias</h3>
                        <p>Duelo métrica a métrica em retorno, risco e consistência, com veredito, curvas sobrepostas, resultado ano a ano, placar período a período e o efeito de rodar as duas estratégias juntas.</p>
                    </div>
                    <div class="feature reveal">
                        <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M2 12h20"/><circle cx="12" cy="12" r="10"/></svg></div>
                        <h3>Portfólios ponderados</h3>
                        <p>Combine estratégias com pesos, ative e desative componentes e acompanhe métricas consolidadas, curva consolidada, desempenho diário e mensal e a contribuição individual de cada estratégia.</p>
                    </div>
                    <div class="feature reveal">
                        <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></div>
                        <h3>Matriz de correlação</h3>
                        <p>Correlação de Pearson entre todas as estratégias do portfólio, em base diária, semanal ou mensal, com faixas coloridas e destaque para os pares mais redundantes.</p>
                    </div>

                    <div class="feature wide reveal">
                        <span class="badge">Destaque</span>
                        <div class="wide-inner">
                            <div>
                                <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 3h15M6 3v6.5L3 21h18l-3-11.5V3"/><path d="M4 15h16"/></svg></div>
                                <h3>Analisador de Combinações</h3>
                                <p>Escolha um conjunto de estratégias candidatas e o tamanho do portfólio. O sistema monta automaticamente todas as combinações possíveis, reconstrói a curva de cada uma e atribui um <b style="color:var(--text)">Consistency Score</b> de 0 a 100. As melhores viram portfólios salvos com um clique.</p>
                            </div>
                            <ul class="checklist">
                                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Até 20.000 portfólios pontuados por rodada</li>
                                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Ordene por score, drawdown, profit factor ou Ulcer</li>
                                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Salve os melhores em lote e abra o resultado completo</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SPOTLIGHT: COMBINATION ANALYZER -->
        <section id="combinacoes" class="spotlight">
            <div class="container spotlight-grid">
                <div class="reveal">
                    <span class="eyebrow" style="color:var(--brand);background:rgba(59,130,246,0.07)"><span class="dot" style="background:var(--brand);box-shadow:0 0 10px var(--brand)"></span>Motor de combinações</span>
                    <h2>O sistema testa os portfólios por você</h2>
                    <p class="desc">
                        Em vez de montar portfólios no olho, selecione as estratégias aprovadas e deixe o
                        analisador gerar cada combinação C(n, k), reconstruir a curva consolidada e medir a
                        consistência com um placar transparente e reproduzível.
                    </p>
                    <ul class="checklist">
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Filtre candidatas por ativo, nome, período e capital inicial</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Resultados paginados e ordenáveis por qualquer coluna</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Um clique salva a combinação como portfólio real</li>
                    </ul>
                </div>

                <div class="panel reveal">
                    <div class="panel-title">Composição do Consistency Score</div>
                    <div class="formula-row">
                        <div class="fbar">
                            <div class="fbar-head"><span>R² da curva de capital</span><b>35%</b></div>
                            <div class="fbar-track"><div class="fbar-fill" style="width:35%"></div></div>
                        </div>
                        <div class="fbar">
                            <div class="fbar-head"><span>Ulcer Index (invertido)</span><b>25%</b></div>
                            <div class="fbar-track"><div class="fbar-fill" style="width:25%"></div></div>
                        </div>
                        <div class="fbar">
                            <div class="fbar-head"><span>Meses positivos</span><b>20%</b></div>
                            <div class="fbar-track"><div class="fbar-fill" style="width:20%"></div></div>
                        </div>
                        <div class="fbar">
                            <div class="fbar-head"><span>Lucro / Drawdown</span><b>20%</b></div>
                            <div class="fbar-track"><div class="fbar-fill" style="width:20%"></div></div>
                        </div>
                    </div>

                    <div class="mock-table">
                        <div class="mock-row head"><span>Combinação</span><span class="m-score">Score</span><span class="m-dd">Drawdown</span></div>
                        <div class="mock-row"><span class="m-name">Tendência IND + Reversão IND + Range DOL</span><span class="m-score">91,2</span><span class="m-dd">-R$ 1.840</span></div>
                        <div class="mock-row"><span class="m-name">Breakout DOL + Reversão IND + Scalp IND</span><span class="m-score">88,7</span><span class="m-dd">-R$ 2.110</span></div>
                        <div class="mock-row"><span class="m-name">Tendência IND + Breakout DOL + Swing DOL</span><span class="m-score">84,3</span><span class="m-dd">-R$ 2.560</span></div>
                        <div class="mock-row"><span class="m-name">Scalp IND + Range DOL + Swing DOL</span><span class="m-score">79,5</span><span class="m-dd">-R$ 3.020</span></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- METRICS BEYOND MT5 -->
        <section id="metricas">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow"><span class="dot"></span>Métricas</span>
                    <h2>Indicadores que vão além do relatório do MT5</h2>
                    <p>O MetaTrader entrega o resultado. O Mayer Quant Analyzer entrega a leitura de risco e de consistência que sustenta a decisão.</p>
                </div>
                <div class="metrics-grid">
                    <div class="metric-card reveal">
                        <span class="k">Risco</span>
                        <h4>Ulcer Index</h4>
                        <p>Mede profundidade e duração dos drawdowns abaixo do topo da curva, não só a queda máxima pontual.</p>
                    </div>
                    <div class="metric-card reveal">
                        <span class="k">Consistência</span>
                        <h4>R² da curva de capital</h4>
                        <p>Aderência da curva a uma reta por regressão linear: quanto mais perto de 1, mais suave e previsível o crescimento.</p>
                    </div>
                    <div class="metric-card reveal">
                        <span class="k">Eficiência</span>
                        <h4>Lucro / Drawdown</h4>
                        <p>Quantas vezes o resultado líquido cobre o pior rebaixamento — o fator de recuperação do portfólio.</p>
                    </div>
                    <div class="metric-card reveal">
                        <span class="k">Robustez</span>
                        <h4>Concentração de lucro</h4>
                        <p>Quanto do resultado veio do melhor mês, do melhor ano ou de poucos trades. Alerta quando o desempenho depende de outliers.</p>
                    </div>
                    <div class="metric-card reveal">
                        <span class="k">Diversificação</span>
                        <h4>Correlação entre estratégias</h4>
                        <p>Pearson em base diária, semanal ou mensal, com interpretação em texto e destaque dos pares que agregam pouca diversificação.</p>
                    </div>
                    <div class="metric-card reveal">
                        <span class="k">Decisão</span>
                        <h4>Consistency Score</h4>
                        <p>Placar único de 0 a 100 que combina suavidade, drawdown, meses positivos e eficiência para ranquear portfólios.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- HOW IT WORKS -->
        <section id="como-funciona">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow"><span class="dot"></span>Como funciona</span>
                    <h2>Três passos, do arquivo à decisão</h2>
                </div>
                <div class="steps">
                    <div class="step reveal">
                        <div class="n">1</div>
                        <h3>Importe</h3>
                        <p>Cadastre a estratégia e carregue os relatórios do MetaTrader 5. Junte quantos períodos quiser sob o mesmo identificador de backtest, sem risco de duplicar trades.</p>
                    </div>
                    <div class="step reveal">
                        <div class="n">2</div>
                        <h3>Analise e cure</h3>
                        <p>Leia as métricas, compare execuções in-sample e out-of-sample, veja os alertas automáticos e classifique o que avança para a próxima etapa.</p>
                    </div>
                    <div class="step reveal">
                        <div class="n">3</div>
                        <h3>Combine e monitore</h3>
                        <p>Monte portfólios ponderados, confira a matriz de correlação e use o analisador de combinações para encontrar os conjuntos mais consistentes.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- DIFFERENTIATORS -->
        <section>
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow"><span class="dot"></span>Por que</span>
                    <h2>Por que o Mayer Quant Analyzer</h2>
                </div>
                <div class="diffs-grid">
                    <div class="diff reveal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span>Foco em quem opera mini-índice e mini-dólar de forma sistemática.</span></div>
                    <div class="diff reveal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span>Cada usuário enxerga apenas as próprias estratégias e portfólios.</span></div>
                    <div class="diff reveal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span>Métricas de risco calibradas pelo capital inicial de cada execução.</span></div>
                    <div class="diff reveal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span>Resultados pesados ficam em cache e recalculam quando os pesos mudam.</span></div>
                    <div class="diff reveal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span>Critérios de curadoria totalmente configuráveis por variável de ambiente.</span></div>
                    <div class="diff reveal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span>Arquitetura de serviços dedicada, com dezenas de cálculos isolados.</span></div>
                </div>
            </div>
        </section>

        <!-- FINAL CTA -->
        <section>
            <div class="container">
                <div class="cta-band reveal">
                    <h2>Pare de decidir portfólio no olho</h2>
                    <p>Importe seus backtests do MetaTrader 5 e deixe as métricas de risco, a curadoria de execuções e o analisador de combinações fazerem o trabalho pesado.</p>
                    <div class="hero-actions">
                        <a href="{{ $registerUrl }}" class="btn btn-primary btn-lg">Criar conta grátis</a>
                        <a href="{{ $loginUrl }}" class="btn btn-ghost btn-lg">Já tenho conta</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <div class="footer-inner">
                <div class="brand" style="font-size:0.95rem">
                    <span class="brand-mark" style="width:26px;height:26px">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
                    </span>
                    Mayer Quant Analyzer
                </div>
                <div class="footer-links">
                    <a href="{{ $loginUrl }}">Entrar</a>
                    <a href="{{ $registerUrl }}">Criar conta</a>
                    <a href="#recursos">Recursos</a>
                </div>
            </div>
            <p class="disclaimer">
                Ferramenta de análise de resultados históricos de backtests. Desempenho passado não é
                garantia de resultado futuro e nada aqui constitui recomendação de investimento.
            </p>
        </div>
    </footer>

    @verbatim
    <script>
        document.documentElement.classList.remove('no-js');
        document.documentElement.classList.add('js');
        (function () {
            var els = document.querySelectorAll('.reveal');
            if (!('IntersectionObserver' in window)) {
                els.forEach(function (el) { el.classList.add('in'); });
                return;
            }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in');
                        io.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
            els.forEach(function (el) { io.observe(el); });
        })();
    </script>
    @endverbatim
</body>
</html>
