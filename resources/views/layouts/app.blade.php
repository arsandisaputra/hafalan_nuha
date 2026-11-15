<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- MANDATORY: Declare support for both light and dark themes to prevent FOUC -->
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#059669">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/icons/icon-192.svg">

    <title>@yield('title', 'HafalanNuha — Tahfidz Mushaf Madinah 15 Baris')</title>

    <!-- Synchronous inline script (not defer, not module) to restore pinned color-scheme before first paint -->
    <script>
    {
        const savedScheme = localStorage.getItem("color-scheme");
        const metaScheme = document.querySelector('meta[name="color-scheme"]');
        const rootEl = document.documentElement;
        const systemDarkQuery = window.matchMedia("(prefers-color-scheme: dark)");

        const applyResolvedTheme = (scheme) => {
            if (scheme === "dark" || scheme === "light") {
                if (metaScheme) metaScheme.content = scheme;
                rootEl.classList.toggle("dark", scheme === "dark");
                rootEl.classList.toggle("light", scheme === "light");
            } else {
                if (metaScheme) metaScheme.content = "light dark";
                rootEl.classList.toggle("dark", systemDarkQuery.matches);
                rootEl.classList.toggle("light", !systemDarkQuery.matches);
            }
        };

        applyResolvedTheme(savedScheme);

        systemDarkQuery.addEventListener("change", () => {
            const currentPinned = localStorage.getItem("color-scheme");
            if (!currentPinned || currentPinned === "light dark") {
                applyResolvedTheme(null);
            }
        });

        window.toggleColorScheme = () => {
            const pinned = localStorage.getItem("color-scheme");
            const isCurrentlyDark = rootEl.classList.contains("dark");
            const targetScheme = isCurrentlyDark ? "light" : "dark";
            const systemCurrentlyDark = systemDarkQuery.matches;

            // Two-state comfort control: either pin opposite of system or return to system setting
            if (pinned && ((targetScheme === "dark" && systemCurrentlyDark) || (targetScheme === "light" && !systemCurrentlyDark))) {
                localStorage.removeItem("color-scheme");
                applyResolvedTheme(null);
            } else {
                localStorage.setItem("color-scheme", targetScheme);
                applyResolvedTheme(targetScheme);
            }

            window.dispatchEvent(new CustomEvent("theme-changed", {
                detail: { dark: rootEl.classList.contains("dark") }
            }));
        };
    }
    </script>

    <!-- Google Fonts: Scheherazade New, Amiri (Uthmani Arabic), Plus Jakarta Sans (UI) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Scheherazade+New:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS & Alpine.js (works with compiled Vite assets or instant CDN fallback) -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            paper: {
                                DEFAULT: '#FAF8F5',
                                warm: '#F3EFE6',
                                border: '#E5DEC9',
                            },
                            night: {
                                DEFAULT: '#0F172A',
                                surface: '#1E293B',
                                border: '#334155',
                            },
                        },
                        fontFamily: {
                            sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                            arabic: ['"Scheherazade New"', '"Amiri"', 'serif'],
                        },
                    }
                }
            };
        </script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    @endif

    <style>
        @layer base, theme, components, utilities;

        @layer theme {
            :root {
                --bg-paper-light: #FAF8F5;
                --bg-paper-dark: #0F172A;
                --surface-light: #FFFFFF;
                --surface-dark: #1E293B;
                --text-primary-light: #1E293B;
                --text-primary-dark: #F1F5F9;
                --text-muted-light: #64748B;
                --text-muted-dark: #94A3B8;
                --border-subtle-light: #E5DEC9;
                --border-subtle-dark: #334155;
                --brand-accent-light: #059669;
                --brand-accent-dark: #34D399;

                --canvas-bg: var(--bg-paper-light);
                --surface-bg: var(--surface-light);
                --text-primary: var(--text-primary-light);
                --text-muted: var(--text-muted-light);
                --border-subtle: var(--border-subtle-light);
                --accent-color: var(--brand-accent-light);
                --scrollbar-track: #EFECE6;
                --scrollbar-thumb: #C5BCA8;

                @media (prefers-color-scheme: dark) {
                    --canvas-bg: var(--bg-paper-dark);
                    --surface-bg: var(--surface-dark);
                    --text-primary: var(--text-primary-dark);
                    --text-muted: var(--text-muted-dark);
                    --border-subtle: var(--border-subtle-dark);
                    --accent-color: var(--brand-accent-dark);
                    --scrollbar-track: #0F172A;
                    --scrollbar-thumb: #475569;
                }

                @supports (color: light-dark(white, black)) {
                    --canvas-bg: light-dark(var(--bg-paper-light), var(--bg-paper-dark));
                    --surface-bg: light-dark(var(--surface-light), var(--surface-dark));
                    --text-primary: light-dark(var(--text-primary-light), var(--text-primary-dark));
                    --text-muted: light-dark(var(--text-muted-light), var(--text-muted-dark));
                    --border-subtle: light-dark(var(--border-subtle-light), var(--border-subtle-dark));
                    --accent-color: light-dark(var(--brand-accent-light), var(--brand-accent-dark));
                    --scrollbar-track: light-dark(#EFECE6, #0F172A);
                    --scrollbar-thumb: light-dark(#C5BCA8, #475569);
                }

                color-scheme: light dark;
                accent-color: var(--accent-color);
                scrollbar-width: thin;
                scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
                --arabic-font-size: 2rem;
            }

            html.dark {
                color-scheme: dark;
            }

            html.light {
                color-scheme: light;
            }
        }

        @layer base {
            [x-cloak] {
                display: none !important;
            }

            body {
                background-color: var(--canvas-bg);
                color: var(--text-primary);
                font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif;
            }

            :focus-visible {
                outline: 2px solid var(--accent-color);
                outline-offset: 2px;
            }

            button,
            select,
            a.interactive-pill,
            input[type="range"] {
                min-block-size: 24px;
                min-inline-size: 24px;
            }

            @media (pointer: coarse) {
                button,
                select,
                a.interactive-pill {
                    min-block-size: 36px;
                    min-inline-size: 36px;
                }
            }

            [inert],
            [inert] * {
                opacity: 0.5;
                cursor: default;
                user-select: none;
            }
        }

        @layer components {
            .arabic-uthmani {
                font-family: "Scheherazade New", "Amiri", serif;
                font-size: var(--arabic-font-size, 2rem);
                line-height: 2.38;
                direction: rtl;
                text-align: right;
                word-spacing: 0.06em;
            }

            .recall-blur {
                filter: blur(8px);
                opacity: 0.35;
                transition: filter 0.22s ease, opacity 0.22s ease;
            }

            .recall-unblurred {
                filter: blur(0);
                opacity: 1;
                transition: filter 0.2s ease, opacity 0.2s ease;
            }

            dialog::backdrop {
                background: rgba(15, 23, 42, 0.55);
                backdrop-filter: blur(3px);
            }
        }
    </style>
</head>
<body class="min-h-dvh bg-[#FAF8F5] dark:bg-[#0F172A] text-[#1E293B] dark:text-slate-100 antialiased selection:bg-emerald-500/20 selection:text-emerald-900 dark:selection:text-emerald-200 transition-colors duration-200">
    @yield('content')

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(() => {});
            });
        }
    </script>
</body>
</html>
