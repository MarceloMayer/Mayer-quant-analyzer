@once
    <style>
        .mqa-wopt-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .mqa-wopt-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .mqa-wopt-btn { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.4rem 0.9rem; font-size: 0.8125rem; font-weight: 600; border-radius: 0.5rem; border: none; cursor: pointer; transition: background .15s, opacity .15s; }
        .mqa-wopt-btn:disabled { opacity: .5; cursor: not-allowed; }
        .mqa-wopt-btn-primary { background: rgb(59 130 246); color: #fff; }
        .mqa-wopt-btn-primary:hover:not(:disabled) { background: rgb(37 99 235); }
        .mqa-wopt-btn-ghost { background: transparent; color: rgb(107 114 128); border: 1px solid rgb(229 231 235); }
        .mqa-wopt-btn-ghost:hover { background: rgb(243 244 246); }
        .dark .mqa-wopt-btn-ghost { border-color: rgb(55 65 81); color: rgb(156 163 175); }
        .dark .mqa-wopt-btn-ghost:hover { background: rgb(31 41 55); }
        .mqa-wopt-body { padding: 1rem; }
        .mqa-wopt-note { margin: 0 0 0.75rem; font-size: 0.8125rem; color: rgb(133 77 14); background: rgb(254 249 195); border: 1px solid rgb(253 224 71); border-radius: 0.5rem; padding: 0.5rem 0.75rem; }
        .dark .mqa-wopt-note { color: rgb(253 224 71); background: rgb(113 63 18 / .3); border-color: rgb(113 63 18); }
        .mqa-wopt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 820px) { .mqa-wopt-grid { grid-template-columns: 1fr; } }
        .mqa-wopt-panel { border: 1px solid rgb(229 231 235); border-radius: 0.5rem; overflow: hidden; }
        .dark .mqa-wopt-panel { border-color: rgb(55 65 81); }
        .mqa-wopt-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; font-variant-numeric: tabular-nums; }
        .mqa-wopt-table th { background: rgb(249 250 251); color: rgb(107 114 128); font-weight: 600; padding: 0.5rem 0.75rem; }
        .dark .mqa-wopt-table th { background: rgb(31 41 55); color: rgb(156 163 175); }
        .mqa-wopt-table td { padding: 0.5rem 0.75rem; border-top: 1px solid rgb(243 244 246); color: rgb(55 65 81); }
        .dark .mqa-wopt-table td { border-color: rgb(31 41 55); color: rgb(209 213 219); }
        .mqa-wopt-table .mqa-left { text-align: left; }
        .mqa-wopt-table .mqa-right { text-align: right; }
        .mqa-wopt-changed { color: rgb(37 99 235); font-weight: 700; }
        .dark .mqa-wopt-changed { color: rgb(96 165 250); }
        .mqa-wopt-up { color: rgb(22 163 74); font-weight: 700; }
        .mqa-wopt-down { color: rgb(220 38 38); font-weight: 700; }
        .dark .mqa-wopt-up { color: rgb(74 222 128); }
        .dark .mqa-wopt-down { color: rgb(248 113 113); }
        .mqa-wopt-chart { margin-top: 1rem; }
        .mqa-wopt-chart-title { padding: 0.6rem 0.75rem 0; font-size: 0.8125rem; font-weight: 600; color: rgb(55 65 81); }
        .dark .mqa-wopt-chart-title { color: rgb(209 213 219); }
        .mqa-wopt-chart-plot { padding: 0.5rem 0.75rem 0; }
        .mqa-wopt-legend { display: flex; flex-wrap: wrap; gap: 0.5rem 1.25rem; padding: 0.5rem 0.75rem 0.75rem; font-size: 0.8125rem; color: rgb(75 85 99); }
        .dark .mqa-wopt-legend { color: rgb(156 163 175); }
        .mqa-wopt-legend-item { display: inline-flex; align-items: center; gap: 0.4rem; }
        .mqa-wopt-dot { display: inline-block; width: 0.65rem; height: 0.65rem; border-radius: 9999px; }

        .mqa-wopt-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; font-size: 0.8125rem; color: rgb(75 85 99); }
        .dark .mqa-wopt-toolbar { color: rgb(156 163 175); }
        .mqa-wopt-check { display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        .mqa-wopt-card { background: #fff; border: 1px solid rgb(229 231 235); border-radius: 0.75rem; box-shadow: 0 1px 2px rgb(0 0 0 / .04); overflow: hidden; }
        .dark .mqa-wopt-card { background: rgb(17 24 39); border-color: rgb(55 65 81); }
        .mqa-wopt-card-head { display: flex; align-items: flex-start; gap: 0.75rem; flex-wrap: wrap; padding: 0.9rem 1rem; }
        .mqa-wopt-card-head .mqa-wopt-actions { margin-left: auto; }
        .mqa-wopt-star { border: none; background: transparent; cursor: pointer; font-size: 1.35rem; line-height: 1; color: rgb(156 163 175); padding: 0.1rem 0.25rem; }
        .mqa-wopt-star:hover { color: rgb(245 158 11); }
        .mqa-wopt-star-on { color: rgb(245 158 11); }
        .mqa-wopt-card-main { flex: 1 1 22rem; min-width: 0; }
        .mqa-wopt-card-title { font-size: 0.9375rem; font-weight: 700; color: rgb(17 24 39); }
        .dark .mqa-wopt-card-title { color: rgb(243 244 246); }
        .mqa-wopt-card-date { font-weight: 500; color: rgb(107 114 128); }
        .mqa-wopt-card-meta { margin-top: 0.15rem; font-size: 0.8125rem; color: rgb(107 114 128); }
        .mqa-wopt-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.5rem; }
        .mqa-wopt-chip { font-size: 0.75rem; padding: 0.15rem 0.55rem; border-radius: 9999px; background: rgb(243 244 246); color: rgb(55 65 81); }
        .dark .mqa-wopt-chip { background: rgb(31 41 55); color: rgb(209 213 219); }
        .mqa-wopt-chip-changed { background: rgb(219 234 254); color: rgb(30 64 175); font-weight: 600; }
        .dark .mqa-wopt-chip-changed { background: rgb(30 58 138 / .5); color: rgb(191 219 254); }
        .mqa-wopt-badge { display: inline-block; margin-top: 0.5rem; font-size: 0.75rem; font-weight: 600; padding: 0.15rem 0.55rem; border-radius: 9999px; background: rgb(220 252 231); color: rgb(22 101 52); }
        .dark .mqa-wopt-badge { background: rgb(20 83 45 / .5); color: rgb(134 239 172); }
        .mqa-wopt-card .mqa-wopt-body { border-top: 1px solid rgb(229 231 235); }
        .dark .mqa-wopt-card .mqa-wopt-body { border-color: rgb(55 65 81); }
        .mqa-wopt-empty { padding: 1.25rem; font-size: 0.875rem; color: rgb(107 114 128); border: 1px dashed rgb(209 213 219); border-radius: 0.75rem; text-align: center; }
        .dark .mqa-wopt-empty { border-color: rgb(55 65 81); color: rgb(156 163 175); }
    </style>
@endonce
