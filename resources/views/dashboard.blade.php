<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('lens-for-laravel::messages.scanner.page_title') }}</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script>
        (function() {
            var saved = localStorage.getItem('lens-theme') || localStorage.getItem('theme');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            if (saved === 'dark' || (!saved && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Instrument Sans', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                },
            },
        }
    </script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            color-scheme: light;
            --lens-page: #ffffff;
            --lens-panel: #f4f4f5;
            --lens-panel-strong: #e4e4e7;
            --lens-content: #09090b;
            --lens-body: #27272a;
            --lens-muted: #52525b;
            --lens-subtle: #71717a;
            --lens-divider: #a1a1aa;
            --lens-control: #71717a;
            --lens-accent: #c52b21;
            --lens-accent-soft: #ffebe8;
            --lens-accent-solid: #c52b21;
            --lens-on-accent: #ffffff;
            --lens-focus: #1d4ed8;
            --lens-grid: rgb(9 9 11 / 0.05);
        }

        .dark {
            color-scheme: dark;
            --lens-page: #09090b;
            --lens-panel: #18181b;
            --lens-panel-strong: #27272a;
            --lens-content: #fafafa;
            --lens-body: #e4e4e7;
            --lens-muted: #c4c4cc;
            --lens-subtle: #a1a1aa;
            --lens-divider: #52525b;
            --lens-control: #a1a1aa;
            --lens-accent: #ff8a8a;
            --lens-accent-soft: #3f1118;
            --lens-accent-solid: #b91c1c;
            --lens-on-accent: #ffffff;
            --lens-focus: #fde047;
            --lens-grid: rgb(250 250 250 / 0.04);
        }

        [x-cloak] {
            display: none !important;
        }

        body {
            background-color: var(--lens-page) !important;
            background-image:
                linear-gradient(var(--lens-grid) 1px, transparent 1px),
                linear-gradient(90deg, var(--lens-grid) 1px, transparent 1px);
            background-size: 48px 48px;
            color: var(--lens-body) !important;
        }

        body::selection,
        body ::selection {
            background: var(--lens-accent-solid) !important;
            color: var(--lens-on-accent) !important;
        }

        body a:focus-visible,
        body button:focus-visible,
        body input:focus-visible,
        body textarea:focus-visible,
        body select:focus-visible,
        body [tabindex]:not([tabindex="-1"]):focus-visible {
            outline: 3px solid var(--lens-focus) !important;
            outline-offset: 3px !important;
        }

        .skip-link {
            position: fixed;
            top: 0.75rem;
            left: 0.75rem;
            z-index: 100;
            transform: translateY(-200%);
            border: 2px solid var(--lens-content);
            background: var(--lens-page);
            color: var(--lens-content);
            padding: 0.75rem 1rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .skip-link:focus {
            transform: translateY(0);
        }

        [class~="text-[#E11D48]"],
        [class~="text-[#FF2D20]"],
        [class~="text-[#D01D10]"],
        .dark [class~="dark:text-[#FF4D40]"] {
            color: var(--lens-accent) !important;
        }

        [class~="bg-[#E11D48]"],
        [class~="bg-[#FF2D20]"] {
            background-color: var(--lens-accent-solid) !important;
        }

        [class~="bg-[#E11D48]/10"],
        [class~="bg-[#FF2D20]/10"] {
            background-color: var(--lens-accent-soft) !important;
        }

        [class~="border-[#E11D48]"],
        [class~="border-[#FF2D20]"],
        [class~="border-t-[#E11D48]"] {
            border-color: var(--lens-accent-solid) !important;
        }

        [class~="border-[#E11D48]/30"],
        [class~="border-[#E11D48]/40"] {
            border-color: var(--lens-accent) !important;
        }

        [class~="hover:text-[#E11D48]"]:hover,
        .dark [class~="dark:hover:text-[#E11D48]"]:hover {
            color: var(--lens-accent) !important;
        }

        [class~="hover:bg-[#E11D48]"]:hover {
            background-color: var(--lens-accent-solid) !important;
        }

        [class~="text-neutral-300"],
        [class~="text-neutral-400"],
        [class~="text-neutral-500"],
        [class~="text-neutral-600"] {
            color: var(--lens-muted) !important;
        }

        [class~="text-neutral-700"],
        [class~="text-neutral-800"] {
            color: var(--lens-body) !important;
        }

        .dark [class~="dark:text-neutral-200"] {
            color: var(--lens-content) !important;
        }

        .dark [class~="dark:text-neutral-300"],
        .dark [class~="dark:text-neutral-400"] {
            color: var(--lens-muted) !important;
        }

        [class~="border-neutral-300"],
        [class~="border-neutral-400"],
        [class~="border-neutral-500"],
        [class~="border-neutral-600"],
        [class~="border-neutral-700"],
        .dark [class~="dark:border-neutral-400"],
        .dark [class~="dark:border-neutral-500"],
        .dark [class~="dark:border-neutral-600"],
        .dark [class~="dark:border-neutral-700"] {
            border-color: var(--lens-control) !important;
        }

        [class~="border-black/10"],
        [class~="border-white/10"] {
            border-color: var(--lens-divider) !important;
        }

        [class~="border-black/20"],
        [class~="border-black/30"],
        [class~="border-white/20"],
        [class~="border-white/30"] {
            border-color: var(--lens-control) !important;
        }

        input::placeholder,
        textarea::placeholder {
            color: var(--lens-subtle) !important;
            opacity: 1;
        }

        header [class~="text-[10px]"],
        main [class~="text-[10px]"],
        .lens-modal [class~="text-[10px]"],
        footer [class~="text-[10px]"] {
            font-size: 0.6875rem !important;
            line-height: 0.9375rem !important;
        }

        /* Custom Scrollbar for Brutalist look */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--lens-panel);
        }

        .dark ::-webkit-scrollbar-track {
            background: var(--lens-panel);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--lens-content);
            border: 2px solid var(--lens-panel);
        }

        .dark ::-webkit-scrollbar-thumb {
            background: var(--lens-content);
            border: 2px solid var(--lens-panel);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--lens-accent);
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body
    class="bg-white text-black dark:bg-black dark:text-neutral-200 font-sans antialiased min-h-screen flex flex-col border-t-[4px] border-t-[#E11D48] overflow-x-hidden"
    x-data="scanner()">

    <a href="#scanner-content" class="skip-link">{{ __('lens-for-laravel::messages.nav.scanner') }}</a>

    <div
        class="flex-1 flex flex-col selection:bg-[#E11D48] selection:text-white dark:selection:bg-[#E11D48] dark:selection:text-white relative min-w-0">
        <!-- Header -->
        <header class="border-b border-black dark:border-neutral-700 bg-white dark:bg-black sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between gap-3 min-w-0">
                <div class="flex items-center gap-3">
                    <!-- Lens For Laravel logomark -->

                    <h1 class="font-mono font-black text-sm sm:text-base tracking-[0.12em] uppercase whitespace-nowrap">
                        <span class="text-black dark:text-white">Lens for</span><span class="text-[#E11D48]">
                            Laravel</span>
                    </h1>
                </div>
                <div class="flex items-center gap-2 sm:gap-6 font-mono text-xs sm:text-sm shrink-0">
                    <button @click="activeTab = 'scanner'"
                        class="uppercase tracking-widest px-2 py-1 transition-colors"
                        :class="activeTab === 'scanner' ? 'text-[#E11D48] font-bold border-b-2 border-[#E11D48]' : 'text-neutral-500 hover:text-black dark:hover:text-white'">{{ __('lens-for-laravel::messages.nav.scanner') }}</button>
                    <button @click="activeTab = 'history'; if (!historyScans.length) loadHistory()"
                        class="uppercase tracking-widest px-2 py-1 transition-colors"
                        :class="activeTab === 'history' ? 'text-[#E11D48] font-bold border-b-2 border-[#E11D48]' : 'text-neutral-500 hover:text-black dark:hover:text-white'">{{ __('lens-for-laravel::messages.nav.history') }}</button>
                    <a href="https://github.com/webcrafts-studio/lens-for-laravel" target="_blank"
                        class="hover:underline hidden sm:block uppercase tracking-wider">{{ __('lens-for-laravel::messages.nav.repository') }}</a>
                    <div class="hidden md:flex items-center gap-1" aria-label="{{ __('lens-for-laravel::messages.language') }}">
                        @foreach (config('lens-for-laravel.supported_locales', []) as $localeCode => $localeLabel)
                            <a href="{{ request()->fullUrlWithQuery(['lens_locale' => $localeCode]) }}"
                                class="px-1.5 py-1 border text-[10px] uppercase tracking-widest {{ app()->getLocale() === $localeCode ? 'border-[#E11D48] text-[#E11D48]' : 'border-transparent text-neutral-500 hover:text-black dark:hover:text-white' }}">
                                {{ $localeCode }}
                            </a>
                        @endforeach
                    </div>
                    <!-- Theme Toggle -->
                    <button @click="toggleTheme" aria-label="{{ __('lens-for-laravel::messages.nav.theme_toggle') }}"
                        :aria-pressed="theme === 'dark'"
                        class="p-1.5 border border-black dark:border-neutral-500 hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors flex items-center justify-center">
                        <svg x-show="theme === 'dark'" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                        </svg>
                        <svg x-show="theme === 'light'" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main id="scanner-content" tabindex="-1" class="flex-1 py-12 px-4 sm:px-6 lg:px-8">
            <div class="w-full max-w-5xl mx-auto space-y-12 min-w-0">

                <!-- ═══ SCANNER TAB ═══ -->
                <div x-show="activeTab === 'scanner'" class="space-y-12">

                    @if (! $aiFixStatus['available'])
                        <div class="border-2 border-dashed border-amber-500 bg-amber-50 px-5 py-4 font-mono text-xs text-amber-950 dark:bg-amber-950/30 dark:text-amber-200"
                            role="status">
                            <strong class="block uppercase tracking-widest">{{ __('lens-for-laravel::messages.ai_fix.unavailable_title') }}</strong>
                            <span class="mt-1 block">{{ $aiFixStatus['message'] }}</span>
                        </div>
                    @endif

                <!-- Hero Section & Controls -->
                <div class="relative">
                    <div
                        class="w-full min-w-0 bg-white dark:bg-black border border-black dark:border-neutral-700 p-8 sm:p-10 relative z-10">
                        <div class="max-w-2xl min-w-0 relative z-10">
                            <h2
                                class="text-2xl font-mono font-bold uppercase tracking-widest border-b border-black dark:border-neutral-700 pb-4 mb-4">
                                {{ __('lens-for-laravel::messages.scanner.title') }}</h2>
                            <p class="mt-2 text-base font-sans text-neutral-700 dark:text-neutral-300 leading-relaxed">
                                {!! __('lens-for-laravel::messages.scanner.intro', [
                                    'axe' => '<a href="https://github.com/dequelabs/axe-core" target="_blank" class="underline decoration-black/20 hover:decoration-black dark:decoration-white/20 dark:hover:decoration-white transition-all">Axe-core</a>',
                                    'browsershot' => '<a href="https://spatie.be/docs/browsershot" target="_blank" class="underline decoration-black/20 hover:decoration-black dark:decoration-white/20 dark:hover:decoration-white transition-all">Spatie Browsershot</a>',
                                ]) !!}
                            </p>
                            <p
                                class="mt-4 text-sm font-sans text-neutral-600 dark:text-neutral-400 leading-relaxed italic">
                                {{ __('lens-for-laravel::messages.scanner.intro_2') }}
                            </p>
                        </div>

                        <form @submit.prevent="performScan" class="mt-8 space-y-4 relative z-10">
                            <!-- Mode Toggle -->
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 font-mono text-xs mb-4">
                                <button type="button" @click="scanMode = 'single'"
                                    class="min-w-0 px-3 py-2 border transition-colors text-center"
                                    :class="scanMode === 'single' ?
                                        'bg-black text-white dark:bg-white dark:text-black border-black dark:border-white' :
                                        'border-neutral-300 dark:border-neutral-600 text-neutral-500 hover:border-neutral-500 dark:hover:border-neutral-400 hover:text-black dark:hover:text-neutral-200'">
                                    {{ __('lens-for-laravel::messages.scanner.single_url') }}
                                </button>
                                <button type="button" @click="scanMode = 'website'"
                                    class="min-w-0 px-3 py-2 border transition-colors text-center"
                                    :class="scanMode === 'website' ?
                                        'bg-black text-white dark:bg-white dark:text-black border-black dark:border-white' :
                                        'border-neutral-300 dark:border-neutral-600 text-neutral-500 hover:border-neutral-500 dark:hover:border-neutral-400 hover:text-black dark:hover:text-neutral-200'">
                                    {{ __('lens-for-laravel::messages.scanner.whole_website') }}
                                </button>
                                <button type="button" @click="scanMode = 'multiple'"
                                    class="min-w-0 px-3 py-2 border transition-colors text-center"
                                    :class="scanMode === 'multiple' ?
                                        'bg-black text-white dark:bg-white dark:text-black border-black dark:border-white' :
                                        'border-neutral-300 dark:border-neutral-600 text-neutral-500 hover:border-neutral-500 dark:hover:border-neutral-400 hover:text-black dark:hover:text-neutral-200'">
                                    {{ __('lens-for-laravel::messages.scanner.multiple_urls') }}
                                </button>
                                <button type="button" @click="scanMode = 'states'"
                                    class="min-w-0 px-3 py-2 border transition-colors text-center"
                                    :class="scanMode === 'states' ?
                                        'bg-black text-white dark:bg-white dark:text-black border-black dark:border-white' :
                                        'border-neutral-300 dark:border-neutral-600 text-neutral-500 hover:border-neutral-500 dark:hover:border-neutral-400 hover:text-black dark:hover:text-neutral-200'">
                                    {{ __('lens-for-laravel::messages.scanner.states') }}
                                </button>
                            </div>

                            <fieldset class="border border-black dark:border-neutral-700 p-4">
                                <legend class="px-1 text-[10px] font-mono font-bold uppercase tracking-widest text-neutral-600 dark:text-neutral-300">
                                    {{ __('lens-for-laravel::messages.scanner.wcag_version') }}
                                </legend>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ __('lens-for-laravel::messages.scanner.wcag_version_help') }}
                                    </p>
                                    <div class="grid grid-cols-3 gap-1 font-mono text-xs" role="radiogroup" aria-label="{{ __('lens-for-laravel::messages.scanner.wcag_version') }}">
                                        <template x-for="version in ['2.0', '2.1', '2.2']" :key="version">
                                            <button type="button" @click="wcagVersion = version" role="radio"
                                                :aria-checked="wcagVersion === version"
                                                class="border px-4 py-2 transition-colors"
                                                :class="wcagVersion === version
                                                    ? 'border-black bg-black text-white dark:border-white dark:bg-white dark:text-black'
                                                    : 'border-neutral-300 text-neutral-500 hover:border-neutral-500 dark:border-neutral-700 dark:hover:border-neutral-400'"
                                                x-text="`WCAG ${version}`"></button>
                                        </template>
                                    </div>
                                </div>
                            </fieldset>

                            <div
                                class="flex flex-col sm:flex-row gap-0 border border-black dark:border-neutral-700 p-1 bg-neutral-50 dark:bg-neutral-900 min-w-0">
                                <label for="target-url" class="sr-only">{{ __('lens-for-laravel::messages.scanner.target_url_label') }}</label>
                                <div class="relative flex-grow min-w-0">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex"
                                        :class="scanMode === 'multiple' ? 'items-start pt-3 pl-3' : 'items-center pl-3'">
                                        <span class="font-mono text-[#E11D48] font-bold" aria-hidden="true">></span>
                                    </div>
                                    <input type="url" id="target-url" x-model="url"
                                        x-show="scanMode !== 'multiple'" :required="scanMode !== 'multiple'"
                                        class="block w-full min-w-0 rounded-none border-0 py-3 pl-8 pr-4 text-black dark:text-white dark:bg-black ring-1 ring-inset ring-black dark:ring-neutral-700 placeholder:text-neutral-600 dark:placeholder:text-neutral-400 focus:ring-2 focus:ring-inset focus:ring-[#E11D48] dark:focus:ring-[#E11D48] sm:text-sm sm:leading-6 font-mono bg-white outline-none"
                                        placeholder="http://localhost">
                                    <textarea id="target-urls" x-model="urlsText" x-show="scanMode === 'multiple'" :required="scanMode === 'multiple'"
                                        rows="4"
                                        class="block w-full min-w-0 rounded-none border-0 py-3 pl-8 pr-4 text-black dark:text-white dark:bg-black ring-1 ring-inset ring-black dark:ring-neutral-700 placeholder:text-neutral-600 dark:placeholder:text-neutral-400 focus:ring-2 focus:ring-inset focus:ring-[#E11D48] sm:text-sm font-mono bg-white outline-none resize-none"
                                        placeholder="https://example.com/page-1&#10;https://example.com/page-2&#10;https://example.com/about" x-cloak></textarea>
                                </div>
                                <button type="submit" :disabled="isLoading"
                                    class="inline-flex items-center justify-center rounded-none bg-[#E11D48] text-white px-8 py-3 text-sm font-mono font-bold uppercase tracking-widest hover:bg-black hover:text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap border-l sm:border-t-0 border-t border-[#E11D48] hover:border-black sm:ml-1 mt-1 sm:mt-0">
                                    <span x-show="!isLoading">{{ __('lens-for-laravel::messages.scanner.execute') }}</span>
                                    <span x-show="isLoading" class="flex items-center gap-2" x-cloak>
                                        {{ __('lens-for-laravel::messages.scanner.processing') }}
                                    </span>
                                </button>
                            </div>
                            <div x-show="scanMode === 'states'" x-cloak
                                class="border border-black dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 p-1 min-w-0">
                                <div class="flex flex-col gap-3 border border-black dark:border-neutral-700 bg-white dark:bg-black p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-[10px] font-mono font-bold uppercase tracking-widest text-neutral-600 dark:text-neutral-300">
                                                {{ __('lens-for-laravel::messages.states.title') }}
                                            </p>
                                            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                                {{ __('lens-for-laravel::messages.states.description') }}
                                            </p>
                                        </div>
                                        <div class="grid grid-cols-2 gap-1 font-mono text-[10px] uppercase tracking-widest">
                                            <button type="button" @click="openStateRecorder()"
                                                class="border border-[#E11D48] bg-[#E11D48] px-3 py-2 text-white">
                                                {{ __('lens-for-laravel::messages.states.record') }}
                                            </button>
                                            <button type="button"
                                                class="border border-black dark:border-white bg-black px-3 py-2 text-white dark:bg-white dark:text-black">
                                                {{ __('lens-for-laravel::messages.states.raw') }}
                                            </button>
                                        </div>
                                    </div>

                                    <label for="interaction-script"
                                        class="block text-[10px] font-mono font-bold uppercase tracking-widest text-neutral-600 dark:text-neutral-300">
                                        {{ __('lens-for-laravel::messages.states.script') }}
                                    </label>
                                    <textarea id="interaction-script" x-model="statesScript" :required="scanMode === 'states'"
                                        rows="8"
                                        class="block w-full min-w-0 rounded-none border-0 py-3 px-4 text-black dark:text-white dark:bg-black ring-1 ring-inset ring-black dark:ring-neutral-700 placeholder:text-neutral-500 dark:placeholder:text-neutral-500 focus:ring-2 focus:ring-inset focus:ring-[#E11D48] sm:text-sm font-mono bg-white outline-none resize-y min-h-52"
                                        placeholder="{{ __('lens-for-laravel::messages.states.script_placeholder') }}"></textarea>
                                </div>
                            </div>
                        </form>

                        <!-- Progress Bar -->
                        <div x-show="isLoading && (scanMode === 'website' || scanMode === 'multiple' || scanMode === 'states')" x-cloak
                            class="mt-6 space-y-2">
                            <div
                                class="flex justify-between text-[10px] font-mono uppercase tracking-widest text-neutral-500">
                                <span x-text="progressStatus"></span>
                                <span x-text="`${progressPercent}%`" class="text-neutral-500"></span>
                            </div>
                            <div
                                class="w-full h-1 bg-neutral-100 dark:bg-neutral-900 border border-black/5 dark:border-white/5">
                                <div class="h-full bg-[#E11D48] transition-all duration-300"
                                    :style="`width: ${progressPercent}%`"></div>
                            </div>
                        </div>

                        <!-- Error Alert -->
                        <div x-show="error" x-cloak
                            class="bg-white dark:bg-black p-4 border-2 border-[#E11D48] text-[#E11D48] mt-6 border-dashed relative z-10">
                            <div class="flex">
                                <div class="flex-shrink-0 font-mono font-bold mr-3">
                                    [ERR]
                                </div>
                                <div>
                                    <h3 class="text-sm font-mono font-bold uppercase tracking-wider text-[#E11D48]">
                                        {{ __('lens-for-laravel::messages.scanner.exception_caught') }}</h3>
                                    <div class="mt-1 text-sm font-mono">
                                        <p x-text="error"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Area -->
                <div x-show="hasResults" x-cloak class="space-y-12">

                    <div
                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-black dark:border-neutral-700 pb-5 pt-1">
                        <h3 class="text-xl font-mono font-bold uppercase tracking-widest">{{ __('lens-for-laravel::messages.scanner.diagnostic_report') }}</h3>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6">
                            <div class="text-sm font-mono">
                                <span class="text-neutral-600 dark:text-neutral-300 uppercase">{{ __('lens-for-laravel::messages.scanner.total_violations') }}:</span>
                                <span class="text-[#E11D48] font-bold" x-text="totalIssues"></span>
                            </div>
                            <button @click="generatePdf()" :disabled="isGeneratingPdf"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 border-2 border-black dark:border-white font-mono text-xs font-bold uppercase tracking-widest transition-colors hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black disabled:opacity-40 disabled:cursor-not-allowed">
                                <span x-show="!isGeneratingPdf">⬇ {{ __('lens-for-laravel::messages.scanner.export_pdf') }}</span>
                                <span x-show="isGeneratingPdf" x-cloak>{{ __('lens-for-laravel::messages.scanner.generating') }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Summary Cards (Filters) -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-8">
                        <!-- Level A: Solid Background -->
                        <button @click="activeFilter = (activeFilter === 'a' ? null : 'a')"
                            class="relative group text-left transition-colors">
                            <div class="bg-[#E11D48] text-white border-2 px-6 py-5 flex flex-col justify-between h-full relative z-10 transition-colors"
                                :class="activeFilter === 'a' ?
                                    'border-black dark:border-white ring-2 ring-inset ring-white/20' :
                                    'border-[#E11D48] opacity-90 group-hover:opacity-100'">
                                <dt
                                    class="truncate text-xs font-mono font-bold uppercase tracking-widest border-b border-white/30 pb-2 mb-2 relative z-10">
                                    {{ __('lens-for-laravel::messages.common.level_a') }}
                                </dt>
                                <dd class="mt-2 text-4xl font-mono font-bold tracking-tight relative z-10"
                                    x-text="levelAIssues"></dd>
                            </div>
                        </button>

                        <!-- Level AA: Solid Border -->
                        <button @click="activeFilter = (activeFilter === 'aa' ? null : 'aa')"
                            class="relative group text-left transition-colors">
                            <div class="bg-white dark:bg-black border-2 px-6 py-5 flex flex-col justify-between h-full relative z-10 transition-colors text-black dark:text-white"
                                :class="activeFilter === 'aa' ?
                                    'border-black dark:border-white bg-neutral-100 dark:bg-neutral-800' :
                                    'border-neutral-300 dark:border-neutral-700 border-solid group-hover:border-neutral-500 dark:group-hover:border-neutral-400 group-hover:bg-neutral-50 dark:group-hover:bg-neutral-900'">
                                <dt
                                    class="truncate text-xs font-mono font-bold uppercase tracking-widest border-b border-black/10 dark:border-white/10 pb-2 mb-2 relative z-10">
                                    {{ __('lens-for-laravel::messages.common.level_aa') }}
                                </dt>
                                <dd class="mt-2 text-4xl font-mono font-bold tracking-tight relative z-10"
                                    x-text="levelAAIssues"></dd>
                            </div>
                        </button>

                        <!-- Level AAA: Dashed Border -->
                        <button @click="activeFilter = (activeFilter === 'aaa' ? null : 'aaa')"
                            class="relative group text-left transition-colors">
                            <div class="bg-white dark:bg-black border-2 px-6 py-5 flex flex-col justify-between h-full relative z-10 transition-colors text-black dark:text-white"
                                :class="activeFilter === 'aaa' ?
                                    'border-black dark:border-white border-solid bg-neutral-100 dark:bg-neutral-800' :
                                    'border-neutral-300 dark:border-neutral-700 border-dashed group-hover:border-neutral-500 dark:group-hover:border-neutral-400 group-hover:bg-neutral-50 dark:group-hover:bg-neutral-900'">
                                <dt
                                    class="truncate text-xs font-mono font-bold uppercase tracking-widest border-b border-black/10 dark:border-white/10 pb-2 mb-2 relative z-10">
                                    {{ __('lens-for-laravel::messages.common.level_aaa') }}
                                </dt>
                                <dd class="mt-2 text-4xl font-mono font-bold tracking-tight relative z-10"
                                    x-text="levelAAAIssues"></dd>
                            </div>
                        </button>

                        <!-- Other: Dotted Border -->
                        <button @click="activeFilter = (activeFilter === 'other' ? null : 'other')"
                            class="relative group text-left transition-colors">
                            <div class="bg-white dark:bg-black border-2 px-6 py-5 flex flex-col justify-between h-full relative z-10 transition-colors text-black dark:text-white"
                                :class="activeFilter === 'other' ?
                                    'border-black dark:border-white border-solid bg-neutral-100 dark:bg-neutral-800' :
                                    'border-neutral-300 dark:border-neutral-700 border-dotted group-hover:border-neutral-500 dark:group-hover:border-neutral-400 group-hover:bg-neutral-50 dark:group-hover:bg-neutral-900'">
                                <dt
                                    class="truncate text-xs font-mono font-bold uppercase tracking-widest border-b border-black/10 dark:border-white/10 pb-2 mb-2 relative z-10">
                                    {{ __('lens-for-laravel::messages.common.other') }}
                                </dt>
                                <dd class="mt-2 text-4xl font-mono font-bold tracking-tight relative z-10"
                                    x-text="otherIssuesCount"></dd>
                            </div>
                        </button>
                    </div>

                <!-- Level Description Area -->
                <div x-show="activeFilter" x-cloak x-transition
                    class="bg-neutral-100 dark:bg-neutral-900 border-l-4 border-black dark:border-white p-4 font-mono text-sm relative">
                    <span class="text-[#FF2D20] font-bold uppercase">{{ __('lens-for-laravel::messages.scanner.info') }}:</span> <span x-text="levelDescription"></span>
                </div>

                <!-- Issue List -->

                <!-- Issue List -->
                <div class="relative mt-8">
                    <div
                        class="bg-white dark:bg-black border border-black dark:border-neutral-700 overflow-hidden relative z-10">
                        <div
                            class="border-b border-black dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-900 px-6 py-4 flex items-center justify-between relative z-10">
                            <h3 class="text-sm font-mono font-bold uppercase tracking-widest"
                                x-text="issueListTitle"></h3>
                            <div class="flex flex-wrap items-center justify-end gap-3">
                                @if ($aiFixStatus['available'])
                                    <button x-show="eligibleFixCount('a') > 0" x-cloak
                                        @click="requestAllAiFixes('a')"
                                        class="inline-flex items-center gap-2 border-2 border-[#E11D48] bg-[#E11D48] px-3 py-1.5 font-mono text-xs font-bold uppercase tracking-widest text-white transition-colors hover:border-black hover:bg-black dark:hover:border-white dark:hover:bg-white dark:hover:text-black">
                                        <span>{{ __('lens-for-laravel::messages.ai_fix.fix_all_a') }}</span>
                                        <span class="border-l border-white/40 pl-2"
                                            x-text="eligibleFixCount('a')"></span>
                                    </button>
                                    <button x-show="eligibleFixCount('aa') > 0" x-cloak
                                        @click="requestAllAiFixes('aa')"
                                        class="inline-flex items-center gap-2 border-2 border-black bg-white px-3 py-1.5 font-mono text-xs font-bold uppercase tracking-widest text-black transition-colors hover:bg-black hover:text-white dark:border-white dark:bg-black dark:text-white dark:hover:bg-white dark:hover:text-black">
                                        <span>{{ __('lens-for-laravel::messages.ai_fix.fix_all_aa') }}</span>
                                        <span class="border-l border-black/30 pl-2 dark:border-white/40"
                                            x-text="eligibleFixCount('aa')"></span>
                                    </button>
                                @endif
                                <template x-if="activeFilter">
                                    <button @click="activeFilter = null"
                                        class="text-xs font-mono uppercase tracking-widest text-neutral-500 dark:text-neutral-400 hover:text-[#E11D48] dark:hover:text-[#E11D48] transition-colors">[
                                        {{ __('lens-for-laravel::messages.scanner.clear_filter') }} ]</button>
                                </template>
                                <template x-if="hasResults">
                                    <span class="text-xs font-mono uppercase dark:text-white">{{ __('lens-for-laravel::messages.scanner.showing') }}: <span
                                            class="text-[#E11D48] font-bold"
                                            x-text="filteredIssues.length"></span></span>
                                </template>
                            </div>
                        </div>

                        <!-- Initial State -->
                        <template x-if="!hasResults && !isLoading">
                            <div class="text-center py-16 px-6 font-mono relative z-10">
                                <div class="text-2xl mb-2 font-bold uppercase dark:text-white">[ {{ __('lens-for-laravel::messages.scanner.ready') }} ]</div>
                                <p class="text-sm text-neutral-600 dark:text-neutral-300 uppercase tracking-widest">
                                    {{ __('lens-for-laravel::messages.scanner.idle') }}</p>
                            </div>
                        </template>

                        <!-- Results Empty -->
                        <template x-if="hasResults && filteredIssues.length === 0">
                            <div class="text-center py-16 px-6 font-mono relative z-10">
                                <div class="text-2xl mb-2 font-bold uppercase dark:text-white">[ {{ __('lens-for-laravel::messages.scanner.ok') }} ]</div>
                                <p class="text-sm text-neutral-600 dark:text-neutral-300 uppercase tracking-widest">
                                    {{ __('lens-for-laravel::messages.scanner.no_violations') }}</p>
                            </div>
                        </template>

                        <ul x-show="hasResults && filteredIssues.length > 0" role="list"
                            class="divide-y divide-black dark:divide-neutral-700 relative z-10">
                            <template x-for="issue in filteredIssues" :key="issue._lensDomKey">
                                <li class="p-6 sm:p-8 transition-colors"
                                    :class="issue.aiFixStatus === 'pending_verification' ?
                                        'border-l-4 border-l-emerald-500 bg-emerald-50/70 dark:bg-emerald-950/20' : ''">
                                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                        <div class="flex-1 space-y-3">
                                            <div class="flex flex-wrap items-center gap-3">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 text-xs font-mono font-bold uppercase tracking-wider"
                                                    :class="getBadgeColor(issue.impact, issue.tags)"
                                                    x-text="getLevelBadge(issue.tags)"></span>
                                                <span
                                                    class="text-sm font-mono font-bold tracking-widest text-neutral-700 dark:text-neutral-300"
                                                    x-text="issue.id"></span>
                                                <template x-if="issue.aiFixStatus === 'pending_verification'">
                                                    <span
                                                        class="inline-flex items-center gap-1.5 px-2 py-1 text-[10px] font-mono font-bold uppercase tracking-wider border border-emerald-600 bg-emerald-100 text-emerald-800 dark:border-emerald-400 dark:bg-emerald-950 dark:text-emerald-300"
                                                        title="{{ __('lens-for-laravel::messages.ai_fix.applied_description') }}">
                                                        <span aria-hidden="true">✓</span>
                                                        <span>{{ __('lens-for-laravel::messages.ai_fix.applied_badge') }}</span>
                                                    </span>
                                                </template>
                                                <!-- Page URL Badge -->
                                                <template x-if="scanMode === 'website' && issue.url">
                                                    <span
                                                        class="text-[10px] font-mono border border-black/10 dark:border-white/10 px-1.5 py-0.5 bg-neutral-50 dark:bg-neutral-900 text-neutral-500"
                                                        x-text="new URL(issue.url).pathname"></span>
                                                </template>
                                                <template x-if="issue.stateLabel">
                                                    <span
                                                        class="text-[10px] font-mono border border-[#E11D48]/40 px-1.5 py-0.5 bg-[#E11D48]/10 text-[#E11D48] uppercase tracking-widest"
                                                        x-text="issue.stateLabel"></span>
                                                </template>
                                                <!-- Preview Button -->
                                                <button @click="loadPreview(issue)"
                                                    class="inline-flex items-center justify-center px-2.5 py-1.5 border border-black/30 dark:border-white/30 text-xs font-mono font-bold uppercase tracking-widest hover:border-black dark:hover:border-white hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors"
                                                    title="{{ __('lens-for-laravel::messages.scanner.preview_element') }}"><svg
                                                        xmlns="http://www.w3.org/2000/svg" width="14"
                                                        height="14" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg></button>
                                                <!-- AI Fix Button -->
                                                @if ($aiFixStatus['available'])
                                                    <template
                                                        x-if="issue.fileName && issue.aiFixStatus !== 'pending_verification'">
                                                        <button @click="requestAiFix(issue)"
                                                            class="inline-flex items-center justify-center px-2.5 py-1.5 border border-black/30 dark:border-white/30 text-xs font-mono font-bold uppercase tracking-widest hover:border-black dark:hover:border-white hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors"
                                                            title="{{ __('lens-for-laravel::messages.scanner.fix_with_ai') }}">{{ __('lens-for-laravel::messages.ai_fix.button') }}</button>
                                                    </template>
                                                @endif

                                            </div>
                                            <h4 class="text-base font-sans font-medium text-black dark:text-white"
                                                x-text="issue.description"></h4>
                                        </div>
                                        <a :href="issue.helpUrl" target="_blank"
                                            class="flex-shrink-0 inline-flex items-center gap-1.5 text-xs font-mono font-bold border border-black dark:border-white hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors uppercase px-2.5 py-1.5 text-black dark:text-white">
                                            {{ __('lens-for-laravel::messages.scanner.view_docs') }} ->
                                        </a>
                                    </div>

                                    <div class="mt-6">
                                        <p
                                            class="text-xs font-mono font-bold text-neutral-600 dark:text-neutral-300 mb-2 uppercase tracking-widest">
                                            <span class="text-black dark:text-white">>>></span> {{ __('lens-for-laravel::messages.scanner.failing_node') }}
                                        </p>
                                        <div
                                            class="bg-neutral-100 dark:bg-neutral-900 border-l-4 border-black dark:border-neutral-500 p-4 overflow-x-auto">
                                            <pre><code class="text-sm font-mono whitespace-pre-wrap text-neutral-800 dark:text-neutral-200" x-text="issue.htmlSnippet"></code></pre>
                                        </div>
                                    </div>

                                    <div
                                        class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-black dark:border-neutral-700 pt-6">
                                        <div>
                                            <p
                                                class="text-xs font-mono font-bold text-neutral-600 dark:text-neutral-300 uppercase tracking-widest mb-2">
                                                <span class="text-black dark:text-white">>>></span> {{ __('lens-for-laravel::messages.scanner.source_location') }}
                                                <span x-show="editorEnabled"
                                                    class="normal-case tracking-normal font-normal text-neutral-400 dark:text-neutral-500 ml-1"
                                                    x-cloak>— {{ __('lens-for-laravel::messages.scanner.click_to_open') }}</span>
                                            </p>
                                            <template x-if="issue.fileName">
                                                <div class="flex items-center gap-2 text-sm font-mono bg-white dark:bg-black border border-black dark:border-neutral-700 px-3 py-2 w-max text-black dark:text-white transition-colors"
                                                    :class="editorEnabled ?
                                                        'cursor-pointer hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black group' :
                                                        ''"
                                                    :title="editorEnabled ? LENS_I18N.openInEditor.replace(':editor', editorLabel) : ''"
                                                    @click="openInEditor(issue.fileName, issue.lineNumber)">
                                                    <span x-show="issue.sourceType"
                                                        class="text-[10px] uppercase tracking-widest border border-black/20 dark:border-white/20 px-1"
                                                        x-text="issue.sourceType"></span>
                                                    <span x-text="issue.fileName + ':' + issue.lineNumber"></span>
                                                    <span x-show="editorEnabled"
                                                        class="text-base leading-none opacity-60 group-hover:opacity-100 transition-opacity"
                                                        aria-hidden="true">↗</span>
                                                </div>
                                            </template>
                                            <template x-if="!issue.fileName">
                                                <div
                                                    class="flex items-center gap-2 text-sm font-mono text-[#D01D10] dark:text-[#FF4D40] border border-[#FF2D20] border-dashed px-3 py-2 w-max uppercase bg-[#FF2D20]/10">
                                                    [ {{ __('lens-for-laravel::messages.scanner.pending_locator') }} ]
                                                </div>
                                            </template>
                                        </div>
                                        <div class="sm:text-right" x-data="{ copied: false }">
                                            <p
                                                class="text-xs font-mono font-bold text-neutral-600 dark:text-neutral-300 uppercase tracking-widest mb-2 block sm:inline-block">
                                                <span class="text-black dark:text-white sm:hidden">>>></span>
                                                {{ __('lens-for-laravel::messages.scanner.css_selector') }}
                                                <span
                                                    class="normal-case tracking-normal font-normal text-neutral-400 dark:text-neutral-500 ml-1">—
                                                    {{ __('lens-for-laravel::messages.scanner.click_to_copy') }}</span>
                                            </p>
                                            <div class="group cursor-pointer flex items-center gap-2 text-sm font-mono bg-white dark:bg-black border border-black dark:border-neutral-700 px-3 py-2 overflow-x-auto break-all sm:ml-auto w-fit max-w-full text-black dark:text-white transition-colors hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black"
                                                @click="navigator.clipboard.writeText(issue.selector).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                                title="{{ __('lens-for-laravel::messages.scanner.copy_selector') }}">
                                                <span x-text="issue.selector"></span>
                                                <span
                                                    class="shrink-0 text-base leading-none opacity-60 group-hover:opacity-100 transition-opacity"
                                                    aria-hidden="true" x-text="copied ? '✓' : '⎘'"></span>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

                </div><!-- /scanner tab -->

                <!-- ═══ HISTORY TAB ═══ -->
                <div x-show="activeTab === 'history'" x-cloak>

                    <!-- Trend Chart -->
                    <div class="bg-white dark:bg-black border border-black dark:border-neutral-700 p-6 sm:p-8">
                        <h3 class="text-sm font-mono font-bold uppercase tracking-widest mb-6 border-b border-black dark:border-neutral-700 pb-3">{{ __('lens-for-laravel::messages.history.trend_title') }}</h3>
                        <div class="relative" style="height: 260px;">
                            <canvas id="trendChart"></canvas>
                        </div>
                        <p x-show="trendData.length === 0" class="text-center font-mono text-sm text-neutral-500 py-8 uppercase tracking-widest">{{ __('lens-for-laravel::messages.history.no_history_short') }}</p>
                    </div>

                    <!-- History Table -->
                    <div class="mt-8 bg-white dark:bg-black border border-black dark:border-neutral-700 overflow-hidden">
                        <div class="border-b border-black dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-900 px-6 py-4 flex items-center justify-between">
                            <h3 class="text-sm font-mono font-bold uppercase tracking-widest">{{ __('lens-for-laravel::messages.history.title') }}</h3>
                            <div class="flex items-center gap-3">
                                <button @click="loadHistory()" class="text-xs font-mono uppercase tracking-widest text-neutral-500 hover:text-[#E11D48] transition-colors">[ {{ __('lens-for-laravel::messages.common.refresh') }} ]</button>
                            </div>
                        </div>

                        <!-- Loading -->
                        <div x-show="historyLoading" class="py-12 flex justify-center">
                            <div class="w-5 h-5 rounded-full border-2 border-black dark:border-white border-t-transparent animate-spin"></div>
                        </div>

                        <!-- Empty State -->
                        <div x-show="!historyLoading && historyScans.length === 0" class="text-center py-16 px-6 font-mono">
                            <div class="text-2xl mb-2 font-bold uppercase dark:text-white">[ {{ __('lens-for-laravel::messages.history.empty_code') }} ]</div>
                            <p class="text-sm text-neutral-600 dark:text-neutral-300 uppercase tracking-widest">{{ __('lens-for-laravel::messages.history.empty') }}</p>
                        </div>

                        <!-- Scans List -->
                        <ul x-show="!historyLoading && historyScans.length > 0" class="divide-y divide-black dark:divide-neutral-700">
                            <template x-for="scan in historyScans" :key="scan.id">
                                <li class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-3 mb-1">
                                            <span class="text-xs font-mono text-neutral-500 uppercase tracking-widest" x-text="new Date(scan.created_at).toLocaleDateString(LENS_LOCALE, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })"></span>
                                            <span class="text-[10px] font-mono border border-black/10 dark:border-white/10 px-1.5 py-0.5 bg-neutral-50 dark:bg-neutral-900 text-neutral-500 uppercase" x-text="scan.scan_mode"></span>
                                            <span class="text-[10px] font-mono border border-black/10 dark:border-white/10 px-1.5 py-0.5 bg-neutral-50 dark:bg-neutral-900 text-neutral-500 uppercase" x-text="`WCAG ${scan.wcag_version || '2.0'}`"></span>
                                        </div>
                                        <p class="text-sm font-mono truncate text-black dark:text-white" x-text="scan.url"></p>
                                        <div class="flex gap-4 mt-1 text-xs font-mono text-neutral-500">
                                            <span>{{ __('lens-for-laravel::messages.common.total') }}: <span class="text-[#E11D48] font-bold" x-text="scan.total_issues"></span></span>
                                            <span>A: <span x-text="scan.level_a_count"></span></span>
                                            <span>AA: <span x-text="scan.level_aa_count"></span></span>
                                            <span>AAA: <span x-text="scan.level_aaa_count"></span></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button @click="viewHistoryScan(scan.id)"
                                            class="px-3 py-1.5 border border-black dark:border-white text-xs font-mono font-bold uppercase tracking-widest hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors">{{ __('lens-for-laravel::messages.common.view') }}</button>
                                        <button @click="startCompare(scan)"
                                            class="px-3 py-1.5 border border-neutral-300 dark:border-neutral-600 text-xs font-mono uppercase tracking-widest hover:border-black dark:hover:border-white transition-colors"
                                            :class="compareBaseScan?.id === scan.id ? 'bg-[#E11D48] text-white border-[#E11D48]' : ''">{{ __('lens-for-laravel::messages.common.compare') }}</button>
                                        <button @click="deleteHistoryScan(scan.id)"
                                            class="px-3 py-1.5 border border-neutral-300 dark:border-neutral-600 text-xs font-mono uppercase tracking-widest text-neutral-500 hover:border-[#E11D48] hover:text-[#E11D48] transition-colors">{{ __('lens-for-laravel::messages.common.delete') }}</button>
                                    </div>
                                </li>
                            </template>
                        </ul>

                        <!-- Pagination -->
                        <div x-show="historyPagination.lastPage > 1" class="border-t border-black dark:border-neutral-700 px-6 py-3 flex items-center justify-between">
                            <button @click="loadHistory(historyPagination.currentPage - 1)" :disabled="historyPagination.currentPage <= 1"
                                class="text-xs font-mono uppercase tracking-widest disabled:opacity-30 hover:text-[#E11D48] transition-colors">[ {{ __('lens-for-laravel::messages.common.previous') }} ]</button>
                            <span class="text-xs font-mono text-neutral-500" x-text="historyPageLabel"></span>
                            <button @click="loadHistory(historyPagination.currentPage + 1)" :disabled="historyPagination.currentPage >= historyPagination.lastPage"
                                class="text-xs font-mono uppercase tracking-widest disabled:opacity-30 hover:text-[#E11D48] transition-colors">[ {{ __('lens-for-laravel::messages.common.next') }} ]</button>
                        </div>
                    </div>

                    <!-- Scan Detail Modal (inline) -->
                    <div x-show="selectedHistoryScan" x-cloak class="mt-8 bg-white dark:bg-black border-2 border-black dark:border-white">
                        <div class="border-b border-black dark:border-white bg-neutral-100 dark:bg-neutral-900 px-6 py-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-mono font-bold uppercase tracking-widest">[ {{ __('lens-for-laravel::messages.history.scan_detail') }} ]</h3>
                                <p class="text-xs font-mono text-neutral-500 mt-0.5" x-text="selectedHistoryScan?.url"></p>
                                <p class="text-[10px] font-mono text-neutral-500 mt-0.5" x-text="`WCAG ${selectedHistoryScan?.wcag_version || '2.0'}`"></p>
                            </div>
                            <button @click="selectedHistoryScan = null" class="text-xs font-mono uppercase tracking-widest text-neutral-500 hover:text-[#E11D48] transition-colors">[ {{ __('lens-for-laravel::messages.common.close') }} ]</button>
                        </div>
                        <div class="divide-y divide-black dark:divide-neutral-700 max-h-[60vh] overflow-y-auto">
                            <template x-for="(issue, idx) in (selectedHistoryScan?.issues || [])" :key="idx">
                                <div class="px-6 py-4">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-mono font-bold uppercase tracking-wider"
                                            :class="getBadgeColor(issue.impact, issue.tags)"
                                            x-text="getLevelBadge(issue.tags)"></span>
                                        <span class="text-sm font-mono font-bold tracking-widest text-neutral-700 dark:text-neutral-300" x-text="issue.rule_id"></span>
                                        <template x-if="issue.state_label">
                                            <span
                                                class="text-[10px] font-mono border border-[#E11D48]/40 px-1.5 py-0.5 bg-[#E11D48]/10 text-[#E11D48] uppercase tracking-widest"
                                                x-text="issue.state_label"></span>
                                        </template>
                                    </div>
                                    <p class="text-sm text-black dark:text-white" x-text="issue.description"></p>
                                    <div x-show="issue.html_snippet" class="mt-2 bg-neutral-100 dark:bg-neutral-900 border-l-4 border-black dark:border-neutral-500 p-3 overflow-x-auto">
                                        <pre><code class="text-xs font-mono whitespace-pre-wrap text-neutral-800 dark:text-neutral-200" x-text="issue.html_snippet"></code></pre>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Compare View -->
                    <div x-show="compareData" x-cloak class="mt-8 bg-white dark:bg-black border-2 border-black dark:border-white">
                        <div class="border-b border-black dark:border-white bg-neutral-100 dark:bg-neutral-900 px-6 py-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-mono font-bold uppercase tracking-widest">[ {{ __('lens-for-laravel::messages.comparison.title') }} ]</h3>
                                <p class="text-xs font-mono text-neutral-500 mt-0.5">
                                    <span x-text="comparisonTitle"></span>
                                </p>
                            </div>
                            <button @click="compareData = null; compareBaseScan = null" class="text-xs font-mono uppercase tracking-widest text-neutral-500 hover:text-[#E11D48] transition-colors">[ {{ __('lens-for-laravel::messages.common.close') }} ]</button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-0 border-b border-black dark:border-neutral-700">
                            <div class="px-6 py-5 border-r border-black dark:border-neutral-700 text-center">
                                <dt class="text-xs font-mono font-bold uppercase tracking-widest text-green-600 dark:text-green-400 mb-1">{{ __('lens-for-laravel::messages.comparison.fixed') }}</dt>
                                <dd class="text-3xl font-mono font-bold" x-text="compareData?.fixed?.length || 0"></dd>
                            </div>
                            <div class="px-6 py-5 border-r border-black dark:border-neutral-700 text-center">
                                <dt class="text-xs font-mono font-bold uppercase tracking-widest text-[#E11D48] mb-1">{{ __('lens-for-laravel::messages.comparison.new') }}</dt>
                                <dd class="text-3xl font-mono font-bold" x-text="compareData?.new?.length || 0"></dd>
                            </div>
                            <div class="px-6 py-5 text-center">
                                <dt class="text-xs font-mono font-bold uppercase tracking-widest text-neutral-500 mb-1">{{ __('lens-for-laravel::messages.comparison.remaining') }}</dt>
                                <dd class="text-3xl font-mono font-bold" x-text="compareData?.remaining?.length || 0"></dd>
                            </div>
                        </div>

                        <div class="max-h-[50vh] overflow-y-auto divide-y divide-black dark:divide-neutral-700">
                            <!-- Fixed issues -->
                            <template x-for="(issue, idx) in (compareData?.fixed || [])" :key="'f'+idx">
                                <div class="px-6 py-3 flex items-center gap-3 bg-green-50 dark:bg-green-900/10">
                                    <span class="text-xs font-mono font-bold uppercase text-green-600 dark:text-green-400 shrink-0">{{ __('lens-for-laravel::messages.comparison.fixed') }}</span>
                                    <span class="text-sm font-mono text-black dark:text-white" x-text="issue.rule_id"></span>
                                    <span x-show="issue.state_label" x-cloak class="text-[10px] font-mono text-[#E11D48] uppercase tracking-widest" x-text="issue.state_label"></span>
                                    <span x-show="issue.url" x-cloak class="text-[10px] font-mono border border-black/10 dark:border-white/10 px-1.5 py-0.5 text-neutral-500" x-text="comparisonIssueUrl(issue.url)"></span>
                                    <span class="text-xs font-mono text-neutral-500 truncate" x-text="issue.selector"></span>
                                </div>
                            </template>
                            <!-- New issues -->
                            <template x-for="(issue, idx) in (compareData?.new || [])" :key="'n'+idx">
                                <div class="px-6 py-3 flex items-center gap-3 bg-red-50 dark:bg-red-900/10">
                                    <span class="text-xs font-mono font-bold uppercase text-[#E11D48] shrink-0">{{ __('lens-for-laravel::messages.comparison.new') }}</span>
                                    <span class="text-sm font-mono text-black dark:text-white" x-text="issue.rule_id"></span>
                                    <span x-show="issue.state_label" x-cloak class="text-[10px] font-mono text-[#E11D48] uppercase tracking-widest" x-text="issue.state_label"></span>
                                    <span x-show="issue.url" x-cloak class="text-[10px] font-mono border border-black/10 dark:border-white/10 px-1.5 py-0.5 text-neutral-500" x-text="comparisonIssueUrl(issue.url)"></span>
                                    <span class="text-xs font-mono text-neutral-500 truncate" x-text="issue.selector"></span>
                                </div>
                            </template>
                            <!-- Remaining issues -->
                            <template x-for="(issue, idx) in (compareData?.remaining || [])" :key="'r'+idx">
                                <div class="px-6 py-3 flex items-center gap-3">
                                    <span class="text-xs font-mono font-bold uppercase text-neutral-400 shrink-0">{{ __('lens-for-laravel::messages.comparison.same') }}</span>
                                    <span class="text-sm font-mono text-black dark:text-white" x-text="issue.rule_id"></span>
                                    <span x-show="issue.state_label" x-cloak class="text-[10px] font-mono text-[#E11D48] uppercase tracking-widest" x-text="issue.state_label"></span>
                                    <span x-show="issue.url" x-cloak class="text-[10px] font-mono border border-black/10 dark:border-white/10 px-1.5 py-0.5 text-neutral-500" x-text="comparisonIssueUrl(issue.url)"></span>
                                    <span class="text-xs font-mono text-neutral-500 truncate" x-text="issue.selector"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                </div><!-- /history tab -->

    </div>
    </main>

    <!-- Footer -->
    <footer class="border-t-2 border-black dark:border-neutral-700 bg-white dark:bg-black overflow-hidden">
        <div class="px-6 py-12 sm:py-16 flex flex-col items-center gap-8 w-full">
            <div class="flex flex-col lg:flex-row items-center justify-center w-full gap-6 lg:gap-8">
                <pre aria-label="Lens"
                    class="font-mono leading-none select-none text-black dark:text-white w-fit text-[8px] sm:text-[10px] md:text-[12px] lg:text-[15px]"
                    style="line-height:1.2">██╗     ███████╗███╗   ██╗███████╗
██║     ██╔════╝████╗  ██║██╔════╝
██║     █████╗  ██╔██╗ ██║███████╗
██║     ██╔══╝  ██║╚██╗██║╚════██║
███████╗███████╗██║ ╚████║███████║
╚══════╝╚══════╝╚═╝  ╚═══╝╚══════╝</pre>
                <pre aria-label="for"
                    class="font-mono leading-none select-none text-neutral-500 w-fit text-[8px] sm:text-[10px] md:text-[12px] lg:text-[15px]"
                    style="line-height:1.2">███████╗ ██████╗ ██████╗ 
██╔════╝██╔═══██╗██╔══██╗
█████╗  ██║   ██║██████╔╝
██╔══╝  ██║   ██║██╔══██╗
██║     ╚██████╔╝██║  ██║
╚═╝      ╚═════╝ ╚═╝  ╚═╝</pre>
                <pre aria-label="Laravel"
                    class="font-mono leading-none select-none text-[#E11D48] w-fit text-[6px] sm:text-[8px] md:text-[10px] lg:text-[15px]"
                    style="line-height:1.2">██╗      █████╗ ██████╗  █████╗ ██╗   ██╗███████╗██╗
██║     ██╔══██╗██╔══██╗██╔══██╗██║   ██║██╔════╝██║
██║     ███████║██████╔╝███████║╚██╗ ██╔╝█████╗  ██║
██║     ██╔══██║██╔══██╗██╔══██║ ╚████╔╝ ██╔══╝  ██║
███████╗██║  ██║██║  ██║██║  ██║  ╚██╔╝  ███████╗███████╗
╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝╚═╝  ╚═╝   ╚═╝   ╚══════╝╚══════╝</pre>
            </div>
            <div class="flex flex-col items-center gap-2 text-center font-mono text-xs uppercase tracking-widest">
                <span class="text-neutral-500">{{ __('lens-for-laravel::messages.scanner.footer') }}</span>
                <span class="text-neutral-400 dark:text-neutral-600">A / AA / AAA &nbsp;&bull;&nbsp; Laravel 10 / 11 /
                    12 / 13</span>
                <a href="https://buycoffee.to/jakub-lipinski" target="_blank" rel="noopener noreferrer"
                    class="mt-2 inline-flex items-center gap-2 border border-[#E11D48]/30 px-3 py-1.5 text-[10px] text-neutral-500 dark:text-neutral-400 hover:border-[#E11D48] hover:text-[#E11D48] transition-colors">
                    <span aria-hidden="true">☕</span>
                    <span>{{ __('lens-for-laravel::messages.scanner.support') }}</span>
                    <span aria-hidden="true">↗</span>
                </a>
            </div>
        </div>
    </footer>

    <!-- AI Fix Modal -->
    <div x-show="showFixModal" @keydown.escape.window="handleFixEscape()" role="dialog" aria-modal="true"
        aria-labelledby="ai-fix-modal-title"
        class="lens-modal fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-cloak>
        <div
            class="bg-white dark:bg-black border-2 border-black dark:border-white w-full max-w-4xl relative shadow-[8px_8px_0px_rgba(0,0,0,1)] dark:shadow-[8px_8px_0px_rgba(255,255,255,0.2)] flex flex-col max-h-[90vh]">

            <!-- Header -->
            <div
                class="border-b border-black dark:border-white px-6 py-4 flex items-center justify-between bg-neutral-100 dark:bg-neutral-900 shrink-0">
                <div class="min-w-0">
                    <h3 id="ai-fix-modal-title" class="text-lg font-mono font-bold uppercase tracking-widest"
                        x-text="`[ ${fixModalTitle} ]`"></h3>
                    <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-mono text-neutral-500 uppercase tracking-widest">
                        <span x-text="fixIssue?.id ?? ''"></span>
                        <span x-show="isBulkFix" x-cloak x-text="fixQueuePosition"></span>
                    </div>
                </div>
                <button @click="closeFixModal()" aria-label="{{ __('lens-for-laravel::messages.common.close') }}" title="{{ __('lens-for-laravel::messages.common.close') }}"
                    class="w-8 h-8 inline-flex items-center justify-center border border-transparent hover:border-black dark:hover:border-white hover:text-[#E11D48] font-mono font-bold text-xl leading-none transition-colors text-black dark:text-white">&times;</button>
            </div>

            <!-- Queue navigation -->
            <div x-show="isBulkFix" x-cloak
                class="border-b border-black dark:border-white bg-white px-6 py-3 dark:bg-black shrink-0">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-mono text-xs uppercase tracking-widest text-neutral-600 dark:text-neutral-300"
                        x-text="fixQueueProgress"></p>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="previousFix()" :disabled="!hasPreviousFix || isApplyingFix"
                            class="border border-black px-3 py-1.5 font-mono text-xs font-bold uppercase tracking-widest transition-colors hover:bg-black hover:text-white disabled:cursor-not-allowed disabled:opacity-30 dark:border-white dark:hover:bg-white dark:hover:text-black">
                            ← {{ __('lens-for-laravel::messages.ai_fix.previous') }}
                        </button>
                        <button type="button" @click="nextFix()" :disabled="!hasNextFix || isApplyingFix"
                            class="border border-black px-3 py-1.5 font-mono text-xs font-bold uppercase tracking-widest transition-colors hover:bg-black hover:text-white disabled:cursor-not-allowed disabled:opacity-30 dark:border-white dark:hover:bg-white dark:hover:text-black">
                            {{ __('lens-for-laravel::messages.ai_fix.next') }} →
                        </button>
                    </div>
                </div>
                <div class="mt-3 flex gap-2 overflow-x-auto pb-1" role="tablist"
                    aria-label="{{ __('lens-for-laravel::messages.ai_fix.queue_navigation') }}">
                    <template x-for="(item, index) in fixQueue" :key="item.key">
                        <button type="button" role="tab" @click="goToFix(index)"
                            :aria-selected="index === activeFixIndex"
                            :aria-label="fixQueueItemAriaLabel(item, index)"
                            class="relative flex h-9 min-w-9 shrink-0 items-center justify-center border-2 px-2 font-mono text-xs font-bold transition-colors"
                            :class="fixQueueStatusClass(item, index)">
                            <span x-text="index + 1"></span>
                            <span x-show="item.status === 'loading' || item.status === 'queued'"
                                class="absolute -right-1 -top-1 h-3 w-3 rounded-full border-2 border-black border-t-transparent bg-white animate-spin dark:border-white dark:border-t-transparent dark:bg-black"></span>
                            <span x-show="item.status === 'ready'"
                                class="absolute -right-1 -top-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-emerald-500 text-[8px] text-white">✓</span>
                            <span x-show="item.status === 'error'"
                                class="absolute -right-1 -top-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-[#E11D48] text-[8px] text-white">!</span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Body -->
            <div class="overflow-y-auto flex-1 p-6 space-y-6">

                <!-- Loading -->
                <div x-show="isLoadingFix" class="flex flex-col items-center justify-center py-16 gap-4">
                    <div
                        class="w-6 h-6 rounded-full border-2 border-black dark:border-white border-t-transparent animate-spin">
                    </div>
                    <div class="font-mono text-center">
                        <p class="text-sm font-bold uppercase tracking-widest">{{ __('lens-for-laravel::messages.ai_fix.waiting_title') }}</p>
                        <p class="text-xs text-neutral-500 mt-1 uppercase tracking-widest"
                            x-text="isBulkFix ? LENS_I18N.queueWaiting : LENS_I18N.analyzing"></p>
                    </div>
                </div>

                <!-- Error -->
                <div x-show="!isLoadingFix && fixError" x-cloak class="border-2 border-dashed border-[#E11D48] p-4">
                    <p class="font-mono text-xs font-bold uppercase tracking-widest text-[#E11D48] mb-2">[ERR] {{ __('lens-for-laravel::messages.ai_fix.error_title') }}</p>
                    <p class="font-mono text-sm" x-text="fixError"></p>
                    <button type="button" @click="retryCurrentFix()"
                        class="mt-4 border border-[#E11D48] px-3 py-1.5 font-mono text-xs font-bold uppercase tracking-widest text-[#E11D48] transition-colors hover:bg-[#E11D48] hover:text-white">
                        {{ __('lens-for-laravel::messages.ai_fix.retry') }}
                    </button>
                </div>

                <!-- Applied success -->
                <div x-show="fixApplied" x-cloak class="border-2 border-green-500 p-6 text-center space-y-2">
                    <p class="font-mono text-sm font-bold uppercase tracking-widest text-green-500">✓
                        {{ __('lens-for-laravel::messages.ai_fix.applied_title') }}</p>
                    <p class="font-mono text-xs text-neutral-500 uppercase tracking-widest">
                        {{ __('lens-for-laravel::messages.ai_fix.applied_description') }}</p>
                </div>

                <!-- Rejected -->
                <div x-show="fixRejected" x-cloak class="border-2 border-neutral-400 p-6 text-center space-y-2">
                    <p class="font-mono text-sm font-bold uppercase tracking-widest text-neutral-600 dark:text-neutral-300">
                        {{ __('lens-for-laravel::messages.ai_fix.rejected_title') }}</p>
                    <p class="font-mono text-xs text-neutral-500 uppercase tracking-widest">
                        {{ __('lens-for-laravel::messages.ai_fix.rejected_description') }}</p>
                </div>

                <!-- Fix Data -->
                <template x-if="!isLoadingFix && fixData && !fixApplied && !fixRejected">
                    <div class="space-y-6">

                        <!-- AI Explanation -->
                        <div class="bg-neutral-100 dark:bg-neutral-900 border-l-4 border-black dark:border-white p-4">
                            <p class="text-xs font-mono font-bold uppercase tracking-widest text-neutral-500 mb-2">>>
                                {{ __('lens-for-laravel::messages.ai_fix.explanation') }}</p>
                            <p class="font-mono text-sm leading-relaxed" x-text="fixData.explanation"></p>
                        </div>

                        <!-- File info -->
                        <div class="font-mono text-xs text-neutral-500 uppercase tracking-widest">
                            <span class="font-bold text-black dark:text-white">{{ __('lens-for-laravel::messages.common.file') }}:</span>
                            <span x-text="fixFileContext"></span>
                        </div>

                        <!-- Diff view -->
                        <div>
                            <div x-show="isEditingFix" x-cloak class="mb-6">
                                <div class="mb-2 flex flex-wrap items-end justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-mono font-bold uppercase tracking-widest text-neutral-500">>>
                                            {{ __('lens-for-laravel::messages.ai_fix.edit_title') }}</p>
                                        <p id="ai-fix-editor-help"
                                            class="mt-1 text-[10px] font-mono text-neutral-500">
                                            {{ __('lens-for-laravel::messages.ai_fix.edit_help') }}</p>
                                    </div>
                                    <button type="button" @click="resetEditedFix()"
                                        :disabled="!hasEditedFix"
                                        class="border border-black dark:border-white px-3 py-1.5 font-mono text-[10px] font-bold uppercase tracking-widest hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors disabled:cursor-not-allowed disabled:opacity-40">
                                        {{ __('lens-for-laravel::messages.ai_fix.reset') }}
                                    </button>
                                </div>

                                <div
                                    class="border-2 border-black dark:border-white bg-[#0d1117] focus-within:ring-2 focus-within:ring-[#E11D48] focus-within:ring-offset-2 dark:focus-within:ring-offset-black">
                                    <div class="flex min-h-[18rem] max-h-[50vh] overflow-hidden">
                                        <div x-ref="fixEditorGutter" aria-hidden="true"
                                            class="w-12 shrink-0 overflow-hidden border-r border-neutral-700 bg-neutral-900 py-3 text-right font-mono text-xs leading-6 text-neutral-500 select-none">
                                            <template x-for="line in fixEditorLineCount" :key="line">
                                                <div class="h-6 pr-3" x-text="line"></div>
                                            </template>
                                        </div>
                                        <textarea x-ref="fixEditor" x-model="editedFixCode"
                                            @input="persistActiveFixEdits()"
                                            @scroll="syncFixEditorScroll($event)"
                                            @keydown.tab.prevent="handleFixEditorTab($event)"
                                            @keydown.ctrl.enter.prevent="applyFix()"
                                            @keydown.meta.enter.prevent="applyFix()"
                                            maxlength="12000" spellcheck="false" wrap="off"
                                            aria-describedby="ai-fix-editor-help ai-fix-editor-status"
                                            class="min-h-[18rem] max-h-[50vh] flex-1 resize-y overflow-auto border-0 bg-[#0d1117] px-4 py-3 font-mono text-xs leading-6 text-neutral-100 outline-none"
                                            aria-label="{{ __('lens-for-laravel::messages.ai_fix.edit_title') }}"></textarea>
                                    </div>
                                    <div id="ai-fix-editor-status"
                                        class="flex flex-wrap items-center justify-between gap-2 border-t border-neutral-700 bg-neutral-900 px-3 py-2 font-mono text-[10px] uppercase tracking-widest text-neutral-400"
                                        aria-live="polite">
                                        <span x-text="fixEditorStatus"></span>
                                        <span x-show="hasEditedFix" x-cloak
                                            class="font-bold text-amber-300">{{ __('lens-for-laravel::messages.ai_fix.edited_badge') }}</span>
                                    </div>
                                </div>
                            </div>

                            <p class="text-xs font-mono font-bold uppercase tracking-widest text-neutral-500 mb-2">>>
                                {{ __('lens-for-laravel::messages.ai_fix.diff') }}</p>
                            <div class="border border-black dark:border-neutral-700 overflow-hidden bg-[#0d1117]">
                                <!-- Diff legend -->
                                <div
                                    class="flex items-center gap-4 px-4 py-2 bg-neutral-800 border-b border-neutral-700 text-xs font-mono">
                                    <span class="text-red-400">— {{ __('lens-for-laravel::messages.ai_fix.original') }}</span>
                                    <span class="text-neutral-600">|</span>
                                    <span class="text-green-400">+ {{ __('lens-for-laravel::messages.ai_fix.fixed') }}</span>
                                </div>
                                <!-- Diff lines -->
                                <div class="overflow-x-auto">
                                    <template x-for="(line, idx) in fixDiff" :key="idx">
                                        <div class="flex items-start px-2 font-mono text-xs leading-5 whitespace-pre"
                                            :class="{
                                                'bg-red-950/60': line.type === 'removed',
                                                'bg-green-950/60': line.type === 'added',
                                            }">
                                            <span class="select-none shrink-0 w-5 mr-2 text-center"
                                                :class="{
                                                    'text-red-400': line.type === 'removed',
                                                    'text-green-400': line.type === 'added',
                                                    'text-neutral-600': line.type === 'context',
                                                }"
                                                x-text="line.type === 'removed' ? '-' : (line.type === 'added' ? '+' : ' ')"></span>
                                            <span
                                                :class="{
                                                    'text-red-300': line.type === 'removed',
                                                    'text-green-300': line.type === 'added',
                                                    'text-neutral-400': line.type === 'context',
                                                }"
                                                x-text="line.text"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                    </div>
                </template>
            </div>

            <!-- Footer — actions -->
            <div x-show="!isLoadingFix && fixData && !fixApplied && !fixRejected" x-cloak
                class="border-t border-black dark:border-white px-6 py-4 flex flex-wrap justify-end gap-3 bg-neutral-100 dark:bg-neutral-900 shrink-0">
                <button @click="rejectFix()"
                    class="px-6 py-2 border-2 border-black dark:border-white font-mono text-sm font-bold uppercase tracking-widest hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors">{{ __('lens-for-laravel::messages.ai_fix.reject') }}</button>
                <button type="button" @click="isEditingFix ? finishEditingFix() : startEditingFix()"
                    :aria-pressed="isEditingFix"
                    class="px-6 py-2 border-2 border-black dark:border-white font-mono text-sm font-bold uppercase tracking-widest hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors">
                    <span x-show="!isEditingFix">{{ __('lens-for-laravel::messages.ai_fix.edit') }}</span>
                    <span x-show="isEditingFix" x-cloak>{{ __('lens-for-laravel::messages.ai_fix.preview_changes') }}</span>
                </button>
                <button @click="applyFix()" :disabled="isApplyingFix"
                    class="px-6 py-2 bg-[#E11D48] border-2 border-[#E11D48] text-white font-mono text-sm font-bold uppercase tracking-widest hover:bg-black hover:border-black transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!isApplyingFix">{{ __('lens-for-laravel::messages.ai_fix.accept_apply') }}</span>
                    <span x-show="isApplyingFix" x-cloak>{{ __('lens-for-laravel::messages.ai_fix.applying') }}</span>
                </button>
            </div>

            <!-- Footer — after applied -->
            <div x-show="fixApplied || fixRejected" x-cloak
                class="border-t border-black dark:border-white px-6 py-4 flex flex-wrap justify-end gap-3 bg-neutral-100 dark:bg-neutral-900 shrink-0">
                <button x-show="isBulkFix && hasNextFix" type="button" @click="nextFix()"
                    class="px-6 py-2 bg-black border-2 border-black text-white dark:bg-white dark:border-white dark:text-black font-mono text-sm font-bold uppercase tracking-widest transition-colors">
                    {{ __('lens-for-laravel::messages.ai_fix.next') }} →
                </button>
                <button @click="closeFixModal()"
                    class="px-6 py-2 border-2 border-black dark:border-white font-mono text-sm font-bold uppercase tracking-widest hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors">{{ __('lens-for-laravel::messages.ai_fix.close') }}</button>
            </div>

        </div>
    </div>

    <!-- Preview Modal -->
    <div x-show="showPreviewModal" @keydown.escape.window="closePreview()" role="dialog" aria-modal="true"
        aria-labelledby="preview-modal-title"
        class="lens-modal fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-cloak>
        <div
            class="bg-white dark:bg-black border-2 border-black dark:border-white w-full max-w-5xl relative shadow-[8px_8px_0px_rgba(0,0,0,1)] dark:shadow-[8px_8px_0px_rgba(255,255,255,0.2)]">
            <div
                class="border-b border-black dark:border-white px-6 py-4 flex items-center justify-between bg-neutral-100 dark:bg-neutral-900">
                <h3 id="preview-modal-title" class="text-lg font-mono font-bold uppercase tracking-widest">[
                    {{ __('lens-for-laravel::messages.preview.title') }} ]</h3>
                <button @click="closePreview()" aria-label="{{ __('lens-for-laravel::messages.common.close') }}" title="{{ __('lens-for-laravel::messages.common.close') }}"
                    class="w-8 h-8 inline-flex items-center justify-center border border-transparent hover:border-black dark:hover:border-white hover:text-[#E11D48] font-mono font-bold text-xl leading-none transition-colors text-black dark:text-white">&times;</button>
            </div>
            <div class="p-6">
                <!-- Loading -->
                <div x-show="isLoadingPreview" class="flex flex-col items-center justify-center py-20 gap-3">
                    <div
                        class="w-5 h-5 rounded-full border-2 border-black dark:border-white border-t-transparent animate-spin">
                    </div>
                    <span class="font-mono text-xs uppercase tracking-widest text-neutral-500">{{ __('lens-for-laravel::messages.preview.rendering') }}</span>
                </div>
                <!-- Screenshot -->
                <div x-show="!isLoadingPreview && previewScreenshot" x-cloak>
                    <img :src="previewScreenshot" class="w-full border border-black dark:border-neutral-700"
                        alt="{{ __('lens-for-laravel::messages.preview.alt') }}" />
                    <div class="mt-3 flex items-center justify-between gap-4">
                        <p
                            class="text-xs font-mono text-neutral-400 dark:text-neutral-500 uppercase tracking-widest truncate">
                            {{ __('lens-for-laravel::messages.common.selector') }}: <span class="text-black dark:text-white" x-text="previewIssue?.selector"></span>
                        </p>
                        <a :href="previewScreenshot" :download="'preview-' + (previewIssue?.id ?? 'element') + '.png'"
                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-white text-xs font-mono font-bold uppercase tracking-widest hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors">⬇
                            {{ __('lens-for-laravel::messages.preview.save') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>

    <script>
        const LENS_RESOURCES_PATH = @json(resource_path());
        const LENS_VIEWS_PATH = @json(resource_path('views'));
        const LENS_EDITOR = @json(config('lens-for-laravel.editor', 'vscode'));
        const LENS_LOCALE = @json(app()->getLocale());
        const LENS_DEFAULT_WCAG_VERSION = @json(\LensForLaravel\LensForLaravel\Support\Wcag::configuredVersion());
        const LENS_I18N = {{ Illuminate\Support\Js::from([
            'initializing' => __('lens-for-laravel::messages.progress.initializing'),
            'scanningPage' => __('lens-for-laravel::messages.progress.scanning_page'),
            'crawling' => __('lens-for-laravel::messages.progress.crawling'),
            'scanComplete' => __('lens-for-laravel::messages.progress.scan_complete'),
            'noScript' => __('lens-for-laravel::messages.states.no_script'),
            'executingStates' => __('lens-for-laravel::messages.states.executing'),
            'stateScanComplete' => __('lens-for-laravel::messages.states.complete'),
            'filteredLogs' => __('lens-for-laravel::messages.scanner.filtered_logs'),
            'diagnosticLogs' => __('lens-for-laravel::messages.scanner.diagnostic_logs'),
            'openInEditor' => __('lens-for-laravel::messages.scanner.open_in_editor'),
            'levelADescription' => __('lens-for-laravel::messages.scanner.level_a_description'),
            'levelAADescription' => __('lens-for-laravel::messages.scanner.level_aa_description'),
            'levelAAADescription' => __('lens-for-laravel::messages.scanner.level_aaa_description'),
            'otherDescription' => __('lens-for-laravel::messages.scanner.other_description'),
            'scanningProgress' => __('lens-for-laravel::messages.scanner.scanning_progress'),
            'pageOf' => __('lens-for-laravel::messages.common.page_of'),
            'baseVsCompare' => __('lens-for-laravel::messages.comparison.base_vs_compare'),
            'fixFileContext' => __('lens-for-laravel::messages.ai_fix.file_context'),
            'screenshotFailed' => __('lens-for-laravel::messages.errors.screenshot_failed'),
            'pdfFailed' => __('lens-for-laravel::messages.errors.pdf_failed'),
            'crawlFailed' => __('lens-for-laravel::messages.errors.crawl_failed'),
            'noLinks' => __('lens-for-laravel::messages.errors.no_links'),
            'noUrls' => __('lens-for-laravel::messages.errors.no_urls'),
            'stateScanFailed' => __('lens-for-laravel::messages.errors.state_scan_failed'),
            'scanFailed' => __('lens-for-laravel::messages.errors.scan_failed'),
            'aiGenerationFailed' => __('lens-for-laravel::messages.errors.ai_generation_failed'),
            'applyFailed' => __('lens-for-laravel::messages.errors.apply_failed'),
            'emptyFixCode' => __('lens-for-laravel::messages.ai_fix.empty_code'),
            'fixEditorStatus' => __('lens-for-laravel::messages.ai_fix.editor_status'),
            'aiFixTitle' => __('lens-for-laravel::messages.ai_fix.title'),
            'fixAllTitle' => __('lens-for-laravel::messages.ai_fix.fix_all_title'),
            'fixQueuePosition' => __('lens-for-laravel::messages.ai_fix.queue_position'),
            'fixQueueProgress' => __('lens-for-laravel::messages.ai_fix.queue_progress'),
            'queueWaiting' => __('lens-for-laravel::messages.ai_fix.queue_waiting'),
            'analyzing' => __('lens-for-laravel::messages.ai_fix.analyzing'),
            'queueItemLabel' => __('lens-for-laravel::messages.ai_fix.queue_item_label'),
            'statusQueued' => __('lens-for-laravel::messages.ai_fix.status_queued'),
            'statusLoading' => __('lens-for-laravel::messages.ai_fix.status_loading'),
            'statusReady' => __('lens-for-laravel::messages.ai_fix.status_ready'),
            'statusApplied' => __('lens-for-laravel::messages.ai_fix.status_applied'),
            'statusRejected' => __('lens-for-laravel::messages.ai_fix.status_rejected'),
            'statusError' => __('lens-for-laravel::messages.ai_fix.status_error'),
            'deleteConfirm' => __('lens-for-laravel::messages.history.delete_confirm'),
            'chartTotal' => __('lens-for-laravel::messages.history.chart_total'),
            'levelA' => __('lens-for-laravel::messages.common.level_a'),
            'levelAA' => __('lens-for-laravel::messages.common.level_aa'),
            'levelAAA' => __('lens-for-laravel::messages.common.level_aaa'),
        ], JSON_UNESCAPED_UNICODE) }};

        document.addEventListener('alpine:init', () => {
            Alpine.data('scanner', () => ({
                url: '{{ url('/') }}',
                isLoading: false,
                hasResults: false,
                error: null,
                issues: [],
                theme: localStorage.getItem('lens-theme') ||
                    localStorage.getItem('theme') ||
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'),
                activeFilter: null,

                // Scan Mode & Progress
                scanMode: 'single', // 'single' | 'website' | 'multiple' | 'states'
                wcagVersion: LENS_DEFAULT_WCAG_VERSION,
                urlsText: '', // textarea content for multiple mode, one URL per line
                statesScript: '',
                recorderChannel: null,
                progressStatus: LENS_I18N.initializing,
                progressPercent: 0,

                // PDF Export State
                isGeneratingPdf: false,

                // Preview State
                showPreviewModal: false,
                isLoadingPreview: false,
                previewScreenshot: null,
                previewIssue: null,

                // Tab State
                activeTab: 'scanner',

                // History State
                historyScans: [],
                historyLoading: false,
                historyPagination: { currentPage: 1, lastPage: 1 },
                selectedHistoryScan: null,
                compareBaseScan: null,
                compareData: null,
                trendData: [],
                trendChart: null,

                // AI Fix State
                showFixModal: false,
                isLoadingFix: false,
                fixIssue: null,
                fixData: null,
                isApplyingFix: false,
                fixApplied: false,
                fixRejected: false,
                fixError: null,
                isEditingFix: false,
                editedFixCode: '',
                isBulkFix: false,
                fixQueue: [],
                activeFixIndex: 0,
                issueDomSequence: 0,
                fixRequestSequence: 0,


                init() {
                    document.documentElement.classList.toggle('dark', this.theme === 'dark');
                    this.$watch('theme', val => {
                        document.documentElement.classList.toggle('dark', val === 'dark');
                        localStorage.setItem('lens-theme', val);
                        localStorage.setItem('theme', val);
                        this.renderTrendChart();
                    });
                    this.initStateRecorderListener();
                },

                toggleTheme() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                },

                get totalIssues() {
                    return this.issues.length;
                },
                get criticalIssues() {
                    return this.issues.filter(i => i.impact === 'critical').length;
                },
                get seriousIssues() {
                    return this.issues.filter(i => i.impact === 'serious').length;
                },
                get moderateIssues() {
                    return this.issues.filter(i => i.impact === 'moderate').length;
                },
                get minorIssues() {
                    return this.issues.filter(i => i.impact === 'minor').length;
                },
                get unknownIssues() {
                    return this.issues.filter(i => !['critical', 'serious', 'moderate', 'minor']
                        .includes(i.impact)).length;
                },

                // WCAG Level Counts
                get levelAIssues() {
                    return this.issues.filter(i => this.getIssueLevel(i.tags) === 'a').length;
                },
                get levelAAIssues() {
                    return this.issues.filter(i => this.getIssueLevel(i.tags) === 'aa').length;
                },
                get levelAAAIssues() {
                    return this.issues.filter(i => this.getIssueLevel(i.tags) === 'aaa').length;
                },
                get otherIssuesCount() {
                    return this.issues.filter(i => this.getIssueLevel(i.tags) === 'other').length;
                },

                get levelDescription() {
                    switch (this.activeFilter) {
                        case 'a':
                            return LENS_I18N.levelADescription;
                        case 'aa':
                            return LENS_I18N.levelAADescription;
                        case 'aaa':
                            return LENS_I18N.levelAAADescription;
                        case 'other':
                            return LENS_I18N.otherDescription;
                        default:
                            return null;
                    }
                },

                get issueListTitle() {
                    return this.activeFilter
                        ? LENS_I18N.filteredLogs.replace(':level', this.activeFilter.toUpperCase())
                        : LENS_I18N.diagnosticLogs;
                },

                get historyPageLabel() {
                    return LENS_I18N.pageOf
                        .replace(':current', this.historyPagination.currentPage)
                        .replace(':last', this.historyPagination.lastPage);
                },

                get comparisonTitle() {
                    return LENS_I18N.baseVsCompare
                        .replace(':base', this.compareData?.base?.id ?? '')
                        .replace(':compare', this.compareData?.compare?.id ?? '');
                },

                get fixFileContext() {
                    if (!this.fixData) return '';

                    return LENS_I18N.fixFileContext
                        .replace(':file', this.fixData.fileName)
                        .replace(':line', this.fixData.startLine);
                },

                get fixModalTitle() {
                    if (!this.isBulkFix) return LENS_I18N.aiFixTitle;

                    const level = this.getIssueLevel(this.fixIssue?.tags ?? []).toUpperCase();

                    return LENS_I18N.fixAllTitle.replace(':level', level);
                },

                get fixQueuePosition() {
                    return LENS_I18N.fixQueuePosition
                        .replace(':current', this.fixQueue.length ? this.activeFixIndex + 1 : 0)
                        .replace(':total', this.fixQueue.length);
                },

                get fixQueueProgress() {
                    const completed = this.fixQueue.filter(item =>
                        ['ready', 'applied', 'rejected', 'error'].includes(item.status)
                    ).length;
                    const ready = this.fixQueue.filter(item => item.status === 'ready').length;

                    return LENS_I18N.fixQueueProgress
                        .replace(':completed', completed)
                        .replace(':total', this.fixQueue.length)
                        .replace(':ready', ready);
                },

                get hasPreviousFix() {
                    return this.activeFixIndex > 0;
                },

                get hasNextFix() {
                    return this.activeFixIndex < this.fixQueue.length - 1;
                },

                get editorEnabled() {
                    return LENS_EDITOR && LENS_EDITOR !== 'none';
                },

                get editorLabel() {
                    const labels = {
                        vscode: 'VS Code',
                        cursor: 'Cursor',
                        phpstorm: 'PhpStorm',
                        sublime: 'Sublime Text'
                    };
                    return labels[LENS_EDITOR] || LENS_EDITOR;
                },

                openStateRecorder() {
                    const recorderUrl = new URL(@json(route('lens-for-laravel.states.recorder')), window.location.origin);
                    recorderUrl.searchParams.set('target', this.url);
                    window.open(recorderUrl.toString(), 'lens-state-recorder', 'width=1440,height=1000');
                },

                initStateRecorderListener() {
                    const applyPayload = (payload) => {
                        if (!payload || payload.type !== 'lens-state-script' || !payload.script) return;

                        this.scanMode = 'states';
                        this.statesScript = payload.script;
                        if (payload.targetUrl) this.url = payload.targetUrl;
                    };

                    if ('BroadcastChannel' in window) {
                        this.recorderChannel = new BroadcastChannel('lens-state-recorder');
                        this.recorderChannel.onmessage = (event) => applyPayload(event.data);
                    }

                    window.addEventListener('message', (event) => {
                        if (event.origin !== window.location.origin) return;
                        applyPayload(event.data);
                    });

                    const stored = localStorage.getItem('lens-state-recorder-script');
                    if (stored) {
                        try {
                            applyPayload(JSON.parse(stored));
                            localStorage.removeItem('lens-state-recorder-script');
                        } catch (e) {
                            localStorage.removeItem('lens-state-recorder-script');
                        }
                    }
                },

                openInEditor(fileName, lineNumber) {
                    if (!fileName || !this.editorEnabled) return;
                    const path = fileName.startsWith('js/')
                        ? LENS_RESOURCES_PATH + '/' + fileName
                        : LENS_VIEWS_PATH + '/' + fileName;
                    const line = lineNumber || 1;
                    let url;
                    switch (LENS_EDITOR) {
                        case 'phpstorm':
                            url = `phpstorm://open?file=${encodeURIComponent(path)}&line=${line}`;
                            break;
                        case 'sublime':
                            url =
                                `subl://open?url=${encodeURIComponent('file://' + path)}&line=${line}`;
                            break;
                        case 'cursor':
                            url = `cursor://file/${path}:${line}`;
                            break;
                        default: // vscode
                            url = `vscode://file/${path}:${line}`;
                    }
                    window.location.href = url;
                },

                get filteredIssues() {
                    if (this.activeFilter) {
                        return this.issues.filter(i => this.getIssueLevel(i.tags) === this.activeFilter);
                    }

                    // Sort issues by WCAG level when no filter is active
                    return [...this.issues].sort((a, b) => {
                        const getWeight = (issue) => {
                            return { a: 1, aa: 2, aaa: 3, other: 4 }[this.getIssueLevel(issue.tags)];
                        };
                        return getWeight(a) - getWeight(b);
                    });
                },

                eligibleFixIssues(level) {
                    return this.issues.filter(issue =>
                        this.getIssueLevel(issue.tags) === level &&
                        issue.fileName &&
                        issue.aiFixStatus !== 'pending_verification'
                    );
                },

                eligibleFixCount(level) {
                    return this.eligibleFixIssues(level).length;
                },

                closePreview() {
                    this.showPreviewModal = false;
                    if (this.previewScreenshot) {
                        URL.revokeObjectURL(this.previewScreenshot);
                        this.previewScreenshot = null;
                    }
                    this.previewIssue = null;
                },

                async loadPreview(issue) {
                    this.previewIssue = issue;
                    this.previewScreenshot = null;
                    this.showPreviewModal = true;
                    this.isLoadingPreview = true;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')
                            .getAttribute('content');
                        const response = await fetch('{{ route('lens-for-laravel.preview') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify({
                                url: issue.url || this.url,
                                selector: issue.selector
                            })
                        });
                        if (!response.ok) throw new Error(LENS_I18N.screenshotFailed);
                        const blob = await response.blob();
                        this.previewScreenshot = URL.createObjectURL(blob);
                    } catch (err) {
                        this.closePreview();
                        this.error = err.message;
                    } finally {
                        this.isLoadingPreview = false;
                    }
                },

                async generatePdf() {
                    this.isGeneratingPdf = true;
                    this.error = null;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')
                            .getAttribute('content');
                        const response = await fetch('{{ route('lens-for-laravel.report.pdf') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify({
                                issues: this.issues,
                                url: this.url,
                                wcagVersion: this.wcagVersion
                            })
                        });

                        if (!response.ok) {
                            const data = await response.json().catch(() => ({}));
                            throw new Error(data.message || LENS_I18N.pdfFailed);
                        }

                        const blob = await response.blob();
                        const objectUrl = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = objectUrl;
                        a.download = 'accessibility-report-{{ now()->format('Y-m-d') }}.pdf';
                        a.click();
                        URL.revokeObjectURL(objectUrl);
                    } catch (err) {
                        this.error = err.message;
                    } finally {
                        this.isGeneratingPdf = false;
                    }
                },

                async performScan() {
                    this.isLoading = true;
                    this.hasResults = false;
                    this.error = null;
                    this.issues = [];
                    this.activeFilter = null;
                    this.progressPercent = 0;

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')
                            .getAttribute('content');

                        if (this.scanMode === 'single') {
                            this.progressStatus = LENS_I18N.scanningPage;
                            this.progressPercent = 50;
                            await this.scanSingleUrl(this.url, token);
                            this.progressPercent = 100;
                        } else if (this.scanMode === 'website') {
                            this.progressStatus = LENS_I18N.crawling;
                            this.progressPercent = 10;

                            // 1. Crawl
                            const crawlResponse = await fetch(
                                '{{ route('lens-for-laravel.crawl') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': token
                                    },
                                    body: JSON.stringify({
                                        url: this.url
                                    })
                                });

                            const crawlData = await crawlResponse.json();
                            if (!crawlResponse.ok) throw new Error(crawlData.message ||
                                LENS_I18N.crawlFailed);

                            const urls = crawlData.urls || [];
                            if (urls.length === 0) throw new Error(LENS_I18N.noLinks);

                            // 2. Scan each URL
                            for (let i = 0; i < urls.length; i++) {
                                const currentUrl = urls[i];
                                this.progressPercent = 10 + Math.round(((i) / urls.length) * 90);
                                this.progressStatus = LENS_I18N.scanningProgress
                                    .replace(':current', i + 1)
                                    .replace(':total', urls.length)
                                    .replace(':url', currentUrl);

                                try {
                                    await this.scanSingleUrl(currentUrl, token, true);
                                } catch (e) {
                                    console.error(`Failed to scan ${currentUrl}:`, e);
                                }
                            }
                            this.progressPercent = 100;
                            this.progressStatus = LENS_I18N.scanComplete;
                        } else if (this.scanMode === 'multiple') {
                            const urlList = this.urlsText
                                .split('\n')
                                .map(u => u.trim())
                                .filter(u => u.length > 0);

                            if (urlList.length === 0) throw new Error(LENS_I18N.noUrls);

                            for (let i = 0; i < urlList.length; i++) {
                                const currentUrl = urlList[i];
                                this.progressPercent = Math.round((i / urlList.length) * 100);
                                this.progressStatus = LENS_I18N.scanningProgress
                                    .replace(':current', i + 1)
                                    .replace(':total', urlList.length)
                                    .replace(':url', currentUrl);
                                try {
                                    await this.scanSingleUrl(currentUrl, token, i > 0);
                                } catch (e) {
                                    console.error(`Failed to scan ${currentUrl}:`, e);
                                }
                            }
                            this.progressPercent = 100;
                            this.progressStatus = LENS_I18N.scanComplete;
                        } else if (this.scanMode === 'states') {
                            if (!this.statesScript.trim()) throw new Error(LENS_I18N.noScript);

                            this.progressStatus = LENS_I18N.executingStates;
                            this.progressPercent = 25;
                            await this.scanInteractiveStates(this.url, token);
                            this.progressPercent = 100;
                            this.progressStatus = LENS_I18N.stateScanComplete;
                        }

                        this.hasResults = true;
                        this.saveToHistory();
                    } catch (err) {
                        this.error = err.message;
                    } finally {
                        this.isLoading = false;
                    }
                },

                async scanInteractiveStates(targetUrl, token) {
                    const response = await fetch('{{ route('lens-for-laravel.scan.states') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token
                        },
                        body: JSON.stringify({
                            url: targetUrl,
                            script: this.statesScript,
                            wcagVersion: this.wcagVersion
                        })
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || LENS_I18N.stateScanFailed);
                    }

                    this.issues = this.prepareIssues(data.issues || []);
                },

                async scanSingleUrl(targetUrl, token, append = false) {
                    const response = await fetch('{{ route('lens-for-laravel.scan') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token
                        },
                        body: JSON.stringify({
                            url: targetUrl,
                            wcagVersion: this.wcagVersion
                        })
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || LENS_I18N.scanFailed);
                    }

                    const scannedIssues = this.prepareIssues(data.issues || []);

                    if (append) {
                        this.issues = [...this.issues, ...scannedIssues];
                    } else {
                        this.issues = scannedIssues;
                    }
                },

                async requestAiFix(issue) {
                    this.openFixQueue([issue], false);
                },

                requestAllAiFixes(level) {
                    const issues = this.eligibleFixIssues(level);
                    if (!issues.length) return;

                    this.openFixQueue(issues, true);
                },

                openFixQueue(issues, isBulk) {
                    this.cancelAiFixRequest();
                    const requestId = this.fixRequestSequence;

                    this.isBulkFix = isBulk;
                    this.fixQueue = issues.map((issue, index) => ({
                        key: `${issue._lensDomKey}-fix-${requestId}-${index}`,
                        issue,
                        status: 'queued',
                        data: null,
                        error: null,
                        editedCode: '',
                        isEditing: false,
                        controller: null,
                    }));
                    this.activeFixIndex = 0;
                    this.showFixModal = true;
                    this.loadActiveFix();
                    this.runFixQueue(requestId);
                },

                runFixQueue(requestId) {
                    let nextIndex = 0;
                    const workerCount = Math.min(3, this.fixQueue.length);
                    const worker = async (workerIndex) => {
                        if (workerIndex > 0) {
                            await new Promise(resolve => window.setTimeout(resolve, workerIndex * 250));
                        }

                        while (requestId === this.fixRequestSequence) {
                            const index = nextIndex++;
                            if (index >= this.fixQueue.length) return;

                            await this.generateFixForQueueItem(index, requestId);
                        }
                    };

                    Promise.all(Array.from({ length: workerCount }, (_, index) => worker(index)));
                },

                async generateFixForQueueItem(index, requestId) {
                    const item = this.fixQueue[index];
                    if (!item || requestId !== this.fixRequestSequence) return;

                    const controller = new AbortController();
                    item.status = 'loading';
                    item.error = null;
                    item.controller = controller;
                    if (index === this.activeFixIndex) this.loadActiveFix();

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')
                            .getAttribute('content');
                        const response = await fetch(
                            '{{ route('lens-for-laravel.fix.suggest') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token
                                },
                                signal: controller.signal,
                                body: JSON.stringify({
                                    htmlSnippet: item.issue.htmlSnippet,
                                    description: item.issue.description,
                                    fileName: item.issue.fileName,
                                    lineNumber: item.issue.lineNumber,
                                    tags: item.issue.tags ?? [],
                                })
                            });
                        const data = await response.json();
                        if (requestId !== this.fixRequestSequence) return;
                        if (!response.ok) throw new Error(data.message ||
                            LENS_I18N.aiGenerationFailed);

                        item.data = data;
                        item.editedCode = data.fixedCode;
                        item.status = 'ready';
                    } catch (err) {
                        if (err.name === 'AbortError' || requestId !== this.fixRequestSequence) return;

                        item.error = err.message;
                        item.status = 'error';
                    } finally {
                        item.controller = null;
                        if (requestId === this.fixRequestSequence && index === this.activeFixIndex) {
                            this.loadActiveFix();
                        }
                    }
                },

                async applyFix() {
                    if (!this.fixData) return;
                    if (!this.editedFixCode.trim()) {
                        this.fixError = LENS_I18N.emptyFixCode;
                        this.startEditingFix();
                        return;
                    }
                    this.isApplyingFix = true;
                    this.fixError = null;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')
                            .getAttribute('content');
                        const response = await fetch('{{ route('lens-for-laravel.fix.apply') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify({
                                fileName: this.fixData.fileName,
                                originalCode: this.fixData.originalCode,
                                fixedCode: this.editedFixCode,
                            })
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || LENS_I18N.applyFailed);
                        this.fixIssue.aiFixStatus = 'pending_verification';
                        this.fixApplied = true;
                        const item = this.fixQueue[this.activeFixIndex];
                        if (item) {
                            item.status = 'applied';
                            item.editedCode = this.editedFixCode;
                            item.isEditing = false;
                        }
                    } catch (err) {
                        this.fixError = err.message;
                    } finally {
                        this.isApplyingFix = false;
                    }
                },

                closeFixModal() {
                    this.cancelAiFixRequest();
                    this.showFixModal = false;
                    this.isLoadingFix = false;
                    this.fixIssue = null;
                    this.fixData = null;
                    this.fixError = null;
                    this.fixApplied = false;
                    this.fixRejected = false;
                    this.isEditingFix = false;
                    this.editedFixCode = '';
                    this.isBulkFix = false;
                    this.fixQueue = [];
                    this.activeFixIndex = 0;
                },

                cancelAiFixRequest() {
                    this.fixQueue.forEach(item => item.controller?.abort());
                    this.fixRequestSequence++;
                },

                loadActiveFix() {
                    const item = this.fixQueue[this.activeFixIndex];
                    if (!item) return;

                    this.fixIssue = item.issue;
                    this.fixData = item.data;
                    this.fixError = item.error;
                    this.fixApplied = item.status === 'applied';
                    this.fixRejected = item.status === 'rejected';
                    this.isLoadingFix = ['queued', 'loading'].includes(item.status);
                    this.editedFixCode = item.editedCode || item.data?.fixedCode || '';
                    this.isEditingFix = item.isEditing && item.status === 'ready';
                },

                persistActiveFixEdits() {
                    const item = this.fixQueue[this.activeFixIndex];
                    if (!item) return;

                    item.editedCode = this.editedFixCode;
                    item.isEditing = this.isEditingFix;
                },

                goToFix(index) {
                    if (index < 0 || index >= this.fixQueue.length || this.isApplyingFix) return;

                    this.persistActiveFixEdits();
                    this.activeFixIndex = index;
                    this.loadActiveFix();
                },

                previousFix() {
                    if (this.hasPreviousFix) this.goToFix(this.activeFixIndex - 1);
                },

                nextFix() {
                    if (this.hasNextFix) this.goToFix(this.activeFixIndex + 1);
                },

                rejectFix() {
                    const item = this.fixQueue[this.activeFixIndex];
                    if (!item) {
                        this.closeFixModal();
                        return;
                    }

                    item.status = 'rejected';
                    item.isEditing = false;
                    this.fixRejected = true;
                    this.isEditingFix = false;

                    if (this.isBulkFix && this.hasNextFix) this.nextFix();
                    else if (!this.isBulkFix) this.closeFixModal();
                },

                retryCurrentFix() {
                    const item = this.fixQueue[this.activeFixIndex];
                    if (!item || item.status !== 'error') return;

                    item.status = 'queued';
                    item.data = null;
                    item.error = null;
                    item.editedCode = '';
                    item.isEditing = false;
                    this.loadActiveFix();
                    this.generateFixForQueueItem(this.activeFixIndex, this.fixRequestSequence);
                },

                fixQueueStatusClass(item, index) {
                    if (index === this.activeFixIndex) {
                        return 'border-black bg-black text-white dark:border-white dark:bg-white dark:text-black';
                    }

                    return {
                        ready: 'border-emerald-500 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
                        applied: 'border-emerald-600 bg-emerald-600 text-white',
                        rejected: 'border-neutral-400 bg-neutral-100 text-neutral-500 dark:bg-neutral-900',
                        error: 'border-[#E11D48] bg-red-50 text-[#E11D48] dark:bg-red-950',
                        loading: 'border-neutral-400 bg-white text-neutral-500 dark:bg-black',
                        queued: 'border-neutral-300 bg-white text-neutral-400 dark:bg-black',
                    }[item.status];
                },

                fixQueueItemAriaLabel(item, index) {
                    const status = {
                        queued: LENS_I18N.statusQueued,
                        loading: LENS_I18N.statusLoading,
                        ready: LENS_I18N.statusReady,
                        applied: LENS_I18N.statusApplied,
                        rejected: LENS_I18N.statusRejected,
                        error: LENS_I18N.statusError,
                    }[item.status];

                    return LENS_I18N.queueItemLabel
                        .replace(':current', index + 1)
                        .replace(':total', this.fixQueue.length)
                        .replace(':rule', item.issue.id)
                        .replace(':status', status);
                },

                prepareIssues(issues) {
                    return issues.map(issue => ({
                        ...issue,
                        _lensDomKey: `lens-issue-${++this.issueDomSequence}`,
                    }));
                },

                handleFixEscape() {
                    if (this.isEditingFix) {
                        this.finishEditingFix();
                        return;
                    }

                    this.closeFixModal();
                },

                startEditingFix() {
                    if (!this.fixData) return;

                    this.isEditingFix = true;
                    this.fixError = null;
                    this.persistActiveFixEdits();
                    this.$nextTick(() => {
                        this.$refs.fixEditor?.focus();
                    });
                },

                finishEditingFix() {
                    if (!this.editedFixCode.trim()) {
                        this.fixError = LENS_I18N.emptyFixCode;
                        this.$nextTick(() => this.$refs.fixEditor?.focus());
                        return;
                    }

                    this.isEditingFix = false;
                    this.fixError = null;
                    this.persistActiveFixEdits();
                },

                resetEditedFix() {
                    if (!this.fixData) return;

                    this.editedFixCode = this.fixData.fixedCode;
                    this.fixError = null;
                    this.persistActiveFixEdits();
                    this.$nextTick(() => {
                        this.$refs.fixEditor?.focus();
                        this.syncFixEditorScroll({ target: this.$refs.fixEditor });
                    });
                },

                syncFixEditorScroll(event) {
                    if (this.$refs.fixEditorGutter) {
                        this.$refs.fixEditorGutter.scrollTop = event.target.scrollTop;
                    }
                },

                handleFixEditorTab(event) {
                    const editor = event.target;
                    const start = editor.selectionStart;
                    const end = editor.selectionEnd;
                    const indent = '    ';

                    if (start === end && !event.shiftKey) {
                        editor.setRangeText(indent, start, end, 'end');
                        this.editedFixCode = editor.value;
                        this.persistActiveFixEdits();

                        return;
                    }

                    const blockStart = editor.value.lastIndexOf('\n', Math.max(0, start - 1)) + 1;

                    if (start === end && event.shiftKey) {
                        const line = editor.value.slice(blockStart);
                        const match = line.match(/^(\t| {1,4})/);
                        if (!match) return;

                        editor.setRangeText('', blockStart, blockStart + match[0].length, 'preserve');
                        this.editedFixCode = editor.value;
                        this.persistActiveFixEdits();
                        this.$nextTick(() => {
                            const caret = Math.max(blockStart, start - match[0].length);
                            editor.setSelectionRange(caret, caret);
                        });

                        return;
                    }

                    const selectedBlock = editor.value.slice(blockStart, end);
                    const replacement = selectedBlock
                        .split('\n')
                        .map(line => event.shiftKey ? line.replace(/^(\t| {1,4})/, '') : indent + line)
                        .join('\n');

                    editor.setRangeText(replacement, blockStart, end, 'select');
                    this.editedFixCode = editor.value;
                    this.persistActiveFixEdits();
                },

                // LCS-based line diff (Myers-style, simplified)
                _lcs(a, b) {
                    const m = a.length,
                        n = b.length;
                    const dp = Array.from({
                        length: m + 1
                    }, () => new Array(n + 1).fill(0));
                    for (let i = 1; i <= m; i++)
                        for (let j = 1; j <= n; j++)
                            dp[i][j] = a[i - 1] === b[j - 1] ? dp[i - 1][j - 1] + 1 : Math.max(dp[i - 1]
                                [j], dp[i][j - 1]);
                    const diff = [];
                    let i = m,
                        j = n;
                    while (i > 0 || j > 0) {
                        if (i > 0 && j > 0 && a[i - 1] === b[j - 1]) {
                            diff.unshift({
                                type: 'context',
                                text: a[i - 1]
                            });
                            i--;
                            j--;
                        } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
                            diff.unshift({
                                type: 'added',
                                text: b[j - 1]
                            });
                            j--;
                        } else {
                            diff.unshift({
                                type: 'removed',
                                text: a[i - 1]
                            });
                            i--;
                        }
                    }
                    return diff;
                },

                get fixDiff() {
                    if (!this.fixData) return [];
                    return this._lcs(
                        this.fixData.originalCode.split('\n'),
                        this.editedFixCode.split('\n')
                    );
                },

                get hasEditedFix() {
                    return Boolean(this.fixData) && this.editedFixCode !== this.fixData.fixedCode;
                },

                get fixEditorLineCount() {
                    return Math.max(1, this.editedFixCode.split('\n').length);
                },

                get fixEditorStatus() {
                    return LENS_I18N.fixEditorStatus
                        .replace(':lines', this.fixEditorLineCount)
                        .replace(':characters', this.editedFixCode.length);
                },

                // ─── History Methods ─────────────────────────────────

                async saveToHistory() {
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const urlsScanned = this.scanMode === 'multiple'
                            ? this.urlsText.split('\n').map(u => u.trim()).filter(Boolean)
                            : [this.url];
                        await fetch('{{ route("lens-for-laravel.history.store") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify({
                                url: this.url,
                                scanMode: this.scanMode,
                                wcagVersion: this.wcagVersion,
                                urlsScanned,
                                issues: this.issues.map(i => ({
                                    id: i.id,
                                    impact: i.impact,
                                    description: i.description,
                                    helpUrl: i.helpUrl,
                                    htmlSnippet: i.htmlSnippet,
                                    selector: i.selector,
                                    tags: i.tags,
                                    url: i.url,
                                    stateLabel: i.stateLabel,
                                    fileName: i.fileName,
                                    lineNumber: i.lineNumber,
                                    sourceType: i.sourceType,
                                }))
                            })
                        });
                    } catch (e) {
                        console.error('Failed to save scan to history:', e);
                    }
                },

                async loadHistory(page = 1) {
                    this.historyLoading = true;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const res = await fetch(`{{ route("lens-for-laravel.history.index") }}?page=${page}`, {
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
                        });
                        const data = await res.json();
                        this.historyScans = data.scans?.data || [];
                        this.historyPagination = {
                            currentPage: data.scans?.current_page || 1,
                            lastPage: data.scans?.last_page || 1,
                        };
                    } catch (e) {
                        console.error('Failed to load history:', e);
                    } finally {
                        this.historyLoading = false;
                    }
                    this.loadTrends();
                },

                async loadTrends() {
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const res = await fetch('{{ route("lens-for-laravel.history.trends") }}', {
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
                        });
                        const data = await res.json();
                        this.trendData = data.trends || [];
                        this.renderTrendChart();
                    } catch (e) {
                        console.error('Failed to load trends:', e);
                    }
                },

                renderTrendChart() {
                    const canvas = document.getElementById('trendChart');
                    if (!canvas) return;
                    if (this.trendChart) { this.trendChart.destroy(); }

                    const isDark = document.documentElement.classList.contains('dark');
                    const gridColor = isDark ? 'rgba(161,161,170,0.45)' : 'rgba(82,82,91,0.35)';
                    const textColor = isDark ? '#c4c4cc' : '#52525b';
                    const accentColor = isDark ? '#ff8a8a' : '#991b1b';

                    const labels = this.trendData.map(t => new Date(t.created_at).toLocaleDateString(LENS_LOCALE, { month: 'short', day: 'numeric' }));
                    this.trendChart = new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [
                                {
                                    label: LENS_I18N.chartTotal,
                                    data: this.trendData.map(t => t.total_issues),
                                    borderColor: accentColor,
                                    backgroundColor: isDark ? 'rgba(255,138,138,0.12)' : 'rgba(153,27,27,0.10)',
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: 4,
                                    pointHoverRadius: 6,
                                },
                                {
                                    label: LENS_I18N.levelA,
                                    data: this.trendData.map(t => t.level_a_count),
                                    borderColor: isDark ? '#fca5a5' : '#b91c1c',
                                    borderDash: [5, 5],
                                    tension: 0.3,
                                    pointRadius: 2,
                                },
                                {
                                    label: LENS_I18N.levelAA,
                                    data: this.trendData.map(t => t.level_aa_count),
                                    borderColor: isDark ? '#e5e5e5' : '#171717',
                                    borderDash: [3, 3],
                                    tension: 0.3,
                                    pointRadius: 2,
                                },
                                {
                                    label: LENS_I18N.levelAAA,
                                    data: this.trendData.map(t => t.level_aaa_count),
                                    borderColor: isDark ? '#a1a1aa' : '#52525b',
                                    borderDash: [2, 4],
                                    tension: 0.3,
                                    pointRadius: 2,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    labels: { color: textColor, font: { family: 'monospace', size: 11 } }
                                },
                            },
                            scales: {
                                x: { grid: { color: gridColor }, ticks: { color: textColor, font: { family: 'monospace', size: 10 } } },
                                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, font: { family: 'monospace', size: 10 } } },
                            },
                        }
                    });
                },

                async viewHistoryScan(id) {
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const res = await fetch(`{{ url(config('lens-for-laravel.route_prefix', 'lens-for-laravel')) }}/history/${id}`, {
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
                        });
                        const data = await res.json();
                        if (data.scan) {
                            this.selectedHistoryScan = data.scan;
                            this.compareData = null;
                        }
                    } catch (e) {
                        console.error('Failed to load scan detail:', e);
                    }
                },

                async deleteHistoryScan(id) {
                    if (!confirm(LENS_I18N.deleteConfirm)) return;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        await fetch(`{{ url(config('lens-for-laravel.route_prefix', 'lens-for-laravel')) }}/history/${id}`, {
                            method: 'DELETE',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
                        });
                        this.historyScans = this.historyScans.filter(s => s.id !== id);
                        if (this.selectedHistoryScan?.id === id) this.selectedHistoryScan = null;
                        this.loadTrends();
                    } catch (e) {
                        console.error('Failed to delete scan:', e);
                    }
                },

                startCompare(scan) {
                    if (this.compareBaseScan?.id === scan.id) {
                        this.compareBaseScan = null;
                        this.compareData = null;
                        return;
                    }
                    if (!this.compareBaseScan) {
                        this.compareBaseScan = scan;
                        return;
                    }
                    this.runCompare(this.compareBaseScan.id, scan.id);
                },

                async runCompare(baseId, compareId) {
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const res = await fetch(`{{ url(config('lens-for-laravel.route_prefix', 'lens-for-laravel')) }}/history/${baseId}/compare/${compareId}`, {
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            this.compareData = data;
                            this.selectedHistoryScan = null;
                        }
                    } catch (e) {
                        console.error('Failed to compare scans:', e);
                    } finally {
                        this.compareBaseScan = null;
                    }
                },

                comparisonIssueUrl(url) {
                    try {
                        const parsed = new URL(url, window.location.origin);
                        return parsed.pathname + parsed.search;
                    } catch (e) {
                        return url;
                    }
                },

                getBadgeColor(impact, tags) {
                    if (this.getIssueLevel(tags) === 'a')
                        return 'bg-[#E11D48] text-white border border-[#E11D48]';
                    if (this.getIssueLevel(tags) === 'aa')
                        return 'bg-white text-black dark:bg-black dark:text-white border border-black dark:border-white';
                    if (this.getIssueLevel(tags) === 'aaa')
                        return 'bg-white text-neutral-600 dark:bg-black dark:text-neutral-400 border border-dashed border-neutral-600 dark:border-neutral-400';

                    // Fallback to OTHER style (subtle but readable)
                    return 'bg-white text-neutral-700 dark:bg-black dark:text-neutral-300 border border-dotted border-neutral-700 dark:border-neutral-300';
                },

                getIssueLevel(tags) {
                    const issueTags = Array.isArray(tags) ? tags : [];
                    if (['wcag2a', 'wcag21a'].some(tag => issueTags.includes(tag))) return 'a';
                    if (['wcag2aa', 'wcag21aa', 'wcag22aa'].some(tag => issueTags.includes(tag))) return 'aa';
                    if (issueTags.includes('wcag2aaa')) return 'aaa';
                    return 'other';
                },

                getLevelBadge(tags) {
                    return {
                        a: '[WCAG A]',
                        aa: '[WCAG AA]',
                        aaa: '[WCAG AAA]',
                        other: '[OTHER]'
                    }[this.getIssueLevel(tags)];
                }
            }));
        });
    </script>
</body>

</html>
