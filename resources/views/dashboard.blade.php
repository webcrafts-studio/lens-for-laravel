<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lens For Laravel - Technical Auditor</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap"
        rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Instrument Sans', 'sans-serif'],
                    },
                },
            },
        }
    </script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] {
            display: none !important;
        }

        /* Custom Scrollbar for Brutalist look */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .dark ::-webkit-scrollbar-track {
            background: #111;
        }

        ::-webkit-scrollbar-thumb {
            background: #333;
            border: 2px solid #f1f1f1;
        }

        .dark ::-webkit-scrollbar-thumb {
            background: #eee;
            border: 2px solid #111;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #E11D48;
        }
    </style>
</head>

<body
    class="bg-white text-black dark:bg-black dark:text-neutral-200 font-sans antialiased min-h-screen flex flex-col border-t-[4px] border-t-[#E11D48]"
    x-data="scanner()">

    <div
        class="flex-1 flex flex-col selection:bg-[#E11D48] selection:text-white dark:selection:bg-[#E11D48] dark:selection:text-white relative">
        <!-- Header -->
        <header class="border-b border-black dark:border-neutral-700 bg-white dark:bg-black sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <!-- Lens For Laravel logomark -->

                    <h1 class="font-sans font-bold text-xl tracking-tight whitespace-nowrap">
                        <span class="text-black dark:text-white">Lens for</span><span class="text-[#E11D48]">
                            Laravel</span>
                    </h1>
                </div>
                <div class="flex items-center gap-6 font-mono text-sm">
                    <a href="https://github.com/webcrafts-studio/lens-for-laravel" target="_blank"
                        class="hover:underline hidden sm:block uppercase tracking-wider">REPOSITORY</a>
                    <!-- Theme Toggle -->
                    <button @click="toggleTheme" aria-label="Toggle Color Theme"
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
        <main class="flex-1 py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-5xl mx-auto space-y-12">

                <!-- Hero Section & Controls -->
                <div class="relative mt-4">
                    <div
                        class="bg-white dark:bg-black border border-black dark:border-neutral-700 p-8 sm:p-10 relative z-10">
                        <div class="max-w-2xl relative z-10">
                            <h2
                                class="text-2xl font-mono font-bold uppercase tracking-widest border-b border-black dark:border-neutral-700 pb-4 mb-4">
                                Target Designation</h2>
                            <p class="mt-2 text-base font-sans text-neutral-700 dark:text-neutral-300 leading-relaxed">
                                Enter target URL for comprehensive accessibility analysis. This auditor utilizes <a
                                    href="https://github.com/dequelabs/axe-core" target="_blank"
                                    class="underline decoration-black/20 hover:decoration-black dark:decoration-white/20 dark:hover:decoration-white transition-all">Axe-core</a>
                                via <a href="https://spatie.be/docs/browsershot" target="_blank"
                                    class="underline decoration-black/20 hover:decoration-black dark:decoration-white/20 dark:hover:decoration-white transition-all">Spatie
                                    Browsershot</a> to identify WCAG violations.
                            </p>
                            <p
                                class="mt-4 text-sm font-sans text-neutral-600 dark:text-neutral-400 leading-relaxed italic">
                                System evaluates Level A, AA, and AAA compliance, identifies best practice improvements,
                                and provides experimental remediation proposals powered by AI.
                            </p>
                        </div>

                        <form @submit.prevent="performScan" class="mt-8 space-y-4 relative z-10">
                            <!-- Mode Toggle -->
                            <div class="flex items-center gap-4 font-mono text-xs mb-4">
                                <button type="button" @click="scanMode = 'single'"
                                    class="px-3 py-1 border transition-colors"
                                    :class="scanMode === 'single' ?
                                        'bg-black text-white dark:bg-white dark:text-black border-black dark:border-white' :
                                        'border-neutral-300 dark:border-neutral-600 text-neutral-500 hover:border-neutral-500 dark:hover:border-neutral-400 hover:text-black dark:hover:text-neutral-200'">
                                    SINGLE_URL
                                </button>
                                <button type="button" @click="scanMode = 'website'"
                                    class="px-3 py-1 border transition-colors"
                                    :class="scanMode === 'website' ?
                                        'bg-black text-white dark:bg-white dark:text-black border-black dark:border-white' :
                                        'border-neutral-300 dark:border-neutral-600 text-neutral-500 hover:border-neutral-500 dark:hover:border-neutral-400 hover:text-black dark:hover:text-neutral-200'">
                                    WHOLE_WEBSITE
                                </button>
                                <button type="button" @click="scanMode = 'multiple'"
                                    class="px-3 py-1 border transition-colors"
                                    :class="scanMode === 'multiple' ?
                                        'bg-black text-white dark:bg-white dark:text-black border-black dark:border-white' :
                                        'border-neutral-300 dark:border-neutral-600 text-neutral-500 hover:border-neutral-500 dark:hover:border-neutral-400 hover:text-black dark:hover:text-neutral-200'">
                                    MULTIPLE_URLS
                                </button>
                            </div>

                            <div
                                class="flex flex-col sm:flex-row gap-0 border border-black dark:border-neutral-700 p-1 bg-neutral-50 dark:bg-neutral-900">
                                <label for="target-url" class="sr-only">Target URL to scan</label>
                                <div class="relative flex-grow">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex"
                                        :class="scanMode === 'multiple' ? 'items-start pt-3 pl-3' : 'items-center pl-3'">
                                        <span class="font-mono text-[#E11D48] font-bold" aria-hidden="true">></span>
                                    </div>
                                    <input type="url" id="target-url" x-model="url"
                                        x-show="scanMode !== 'multiple'" :required="scanMode !== 'multiple'"
                                        class="block w-full rounded-none border-0 py-3 pl-8 pr-4 text-black dark:text-white dark:bg-black ring-1 ring-inset ring-black dark:ring-neutral-700 placeholder:text-neutral-600 dark:placeholder:text-neutral-400 focus:ring-2 focus:ring-inset focus:ring-[#E11D48] dark:focus:ring-[#E11D48] sm:text-sm sm:leading-6 font-mono bg-white outline-none"
                                        placeholder="http://localhost">
                                    <textarea id="target-urls" x-model="urlsText" x-show="scanMode === 'multiple'" :required="scanMode === 'multiple'"
                                        rows="4"
                                        class="block w-full rounded-none border-0 py-3 pl-8 pr-4 text-black dark:text-white dark:bg-black ring-1 ring-inset ring-black dark:ring-neutral-700 placeholder:text-neutral-600 dark:placeholder:text-neutral-400 focus:ring-2 focus:ring-inset focus:ring-[#E11D48] sm:text-sm font-mono bg-white outline-none resize-none"
                                        placeholder="https://example.com/page-1&#10;https://example.com/page-2&#10;https://example.com/about" x-cloak></textarea>
                                </div>
                                <button type="submit" :disabled="isLoading"
                                    class="inline-flex items-center justify-center rounded-none bg-[#E11D48] text-white px-8 py-3 text-sm font-mono font-bold uppercase tracking-widest hover:bg-black hover:text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap border-l sm:border-t-0 border-t border-[#E11D48] hover:border-black sm:ml-1 mt-1 sm:mt-0">
                                    <span x-show="!isLoading">EXECUTE</span>
                                    <span x-show="isLoading" class="flex items-center gap-2" x-cloak>
                                        PROCESSING...
                                    </span>
                                </button>
                            </div>
                        </form>

                        <!-- Progress Bar -->
                        <div x-show="isLoading && (scanMode === 'website' || scanMode === 'multiple')" x-cloak
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
                                        Exception Caught</h3>
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
                        class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-black dark:border-neutral-700 pb-4">
                        <h3 class="text-xl font-mono font-bold uppercase tracking-widest">Diagnostic Report</h3>
                        <div class="flex items-center gap-6">
                            <div class="text-sm font-mono">
                                <span class="text-neutral-600 dark:text-neutral-300 uppercase">TOTAL_VIOLATIONS:</span>
                                <span class="text-[#E11D48] font-bold" x-text="totalIssues"></span>
                            </div>
                            <button @click="generatePdf()" :disabled="isGeneratingPdf"
                                class="flex items-center gap-2 px-4 py-2 border-2 border-black dark:border-white font-mono text-xs font-bold uppercase tracking-widest transition-colors hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black disabled:opacity-40 disabled:cursor-not-allowed">
                                <span x-show="!isGeneratingPdf">⬇ Export PDF</span>
                                <span x-show="isGeneratingPdf" x-cloak>Generating...</span>
                            </button>
                        </div>
                    </div>

                    <!-- Summary Cards (Filters) -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-8">
                        <!-- Level A: Solid Background -->
                        <button @click="activeFilter = (activeFilter === 'wcag2a' ? null : 'wcag2a')"
                            class="relative group text-left transition-colors">
                            <div class="bg-[#E11D48] text-white border-2 px-6 py-5 flex flex-col justify-between h-full relative z-10 transition-colors"
                                :class="activeFilter === 'wcag2a' ?
                                    'border-black dark:border-white ring-2 ring-inset ring-white/20' :
                                    'border-[#E11D48] opacity-90 group-hover:opacity-100'">
                                <dt
                                    class="truncate text-xs font-mono font-bold uppercase tracking-widest border-b border-white/30 pb-2 mb-2 relative z-10">
                                    A Level
                                </dt>
                                <dd class="mt-2 text-4xl font-mono font-bold tracking-tight relative z-10"
                                    x-text="levelAIssues"></dd>
                            </div>
                        </button>

                        <!-- Level AA: Solid Border -->
                        <button @click="activeFilter = (activeFilter === 'wcag2aa' ? null : 'wcag2aa')"
                            class="relative group text-left transition-colors">
                            <div class="bg-white dark:bg-black border-2 px-6 py-5 flex flex-col justify-between h-full relative z-10 transition-colors text-black dark:text-white"
                                :class="activeFilter === 'wcag2aa' ?
                                    'border-black dark:border-white bg-neutral-100 dark:bg-neutral-800' :
                                    'border-neutral-300 dark:border-neutral-700 border-solid group-hover:border-neutral-500 dark:group-hover:border-neutral-400 group-hover:bg-neutral-50 dark:group-hover:bg-neutral-900'">
                                <dt
                                    class="truncate text-xs font-mono font-bold uppercase tracking-widest border-b border-black/10 dark:border-white/10 pb-2 mb-2 relative z-10">
                                    AA Level
                                </dt>
                                <dd class="mt-2 text-4xl font-mono font-bold tracking-tight relative z-10"
                                    x-text="levelAAIssues"></dd>
                            </div>
                        </button>

                        <!-- Level AAA: Dashed Border -->
                        <button @click="activeFilter = (activeFilter === 'wcag2aaa' ? null : 'wcag2aaa')"
                            class="relative group text-left transition-colors">
                            <div class="bg-white dark:bg-black border-2 px-6 py-5 flex flex-col justify-between h-full relative z-10 transition-colors text-black dark:text-white"
                                :class="activeFilter === 'wcag2aaa' ?
                                    'border-black dark:border-white border-solid bg-neutral-100 dark:bg-neutral-800' :
                                    'border-neutral-300 dark:border-neutral-700 border-dashed group-hover:border-neutral-500 dark:group-hover:border-neutral-400 group-hover:bg-neutral-50 dark:group-hover:bg-neutral-900'">
                                <dt
                                    class="truncate text-xs font-mono font-bold uppercase tracking-widest border-b border-black/10 dark:border-white/10 pb-2 mb-2 relative z-10">
                                    AAA Level
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
                                    Other
                                </dt>
                                <dd class="mt-2 text-4xl font-mono font-bold tracking-tight relative z-10"
                                    x-text="otherIssuesCount"></dd>
                            </div>
                        </button>
                    </div>
                    </button>
                </div>

                <!-- Level Description Area -->
                <div x-show="activeFilter" x-cloak x-transition
                    class="bg-neutral-100 dark:bg-neutral-900 border-l-4 border-black dark:border-white p-4 font-mono text-sm relative">
                    <span class="text-[#FF2D20] font-bold">INFO:</span> <span x-text="levelDescription"></span>
                </div>

                <!-- Issue List -->

                <!-- Issue List -->
                <div class="relative mt-8">
                    <div
                        class="bg-white dark:bg-black border border-black dark:border-neutral-700 overflow-hidden relative z-10">
                        <div
                            class="border-b border-black dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-900 px-6 py-4 flex items-center justify-between relative z-10">
                            <h3 class="text-sm font-mono font-bold uppercase tracking-widest"
                                x-text="activeFilter ? `Filtered Logs: ${activeFilter}` : 'Diagnostic Logs'"></h3>
                            <div class="flex items-center gap-4">
                                <template x-if="activeFilter">
                                    <button @click="activeFilter = null"
                                        class="text-xs font-mono uppercase tracking-widest text-neutral-500 dark:text-neutral-400 hover:text-[#E11D48] dark:hover:text-[#E11D48] transition-colors">[
                                        CLEAR_FILTER ]</button>
                                </template>
                                <template x-if="hasResults">
                                    <span class="text-xs font-mono dark:text-white">SHOWING: <span
                                            class="text-[#E11D48] font-bold"
                                            x-text="filteredIssues.length"></span></span>
                                </template>
                            </div>
                        </div>

                        <!-- Initial State -->
                        <template x-if="!hasResults && !isLoading">
                            <div class="text-center py-16 px-6 font-mono relative z-10">
                                <div class="text-2xl mb-2 font-bold dark:text-white">[ READY ]</div>
                                <p class="text-sm text-neutral-600 dark:text-neutral-300 uppercase tracking-widest">
                                    System idle. Execute target scan to begin analysis.</p>
                            </div>
                        </template>

                        <!-- Results Empty -->
                        <template x-if="hasResults && filteredIssues.length === 0">
                            <div class="text-center py-16 px-6 font-mono relative z-10">
                                <div class="text-2xl mb-2 font-bold dark:text-white">[ OK ]</div>
                                <p class="text-sm text-neutral-600 dark:text-neutral-300 uppercase tracking-widest">No
                                    violations found for this criteria.</p>
                            </div>
                        </template>

                        <ul x-show="hasResults && filteredIssues.length > 0" role="list"
                            class="divide-y divide-black dark:divide-neutral-700 relative z-10">
                            <template x-for="(issue, index) in filteredIssues" :key="index">
                                <li class="p-6 sm:p-8">
                                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                        <div class="flex-1 space-y-3">
                                            <div class="flex flex-wrap items-center gap-3">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 text-xs font-mono font-bold uppercase tracking-wider"
                                                    :class="getBadgeColor(issue.impact, issue.tags)"
                                                    x-text="issue.tags && issue.tags.includes('wcag2a') ? '[WCAG A]' : (issue.tags && issue.tags.includes('wcag2aa') ? '[WCAG AA]' : (issue.tags && issue.tags.includes('wcag2aaa') ? '[WCAG AAA]' : '[OTHER]'))"></span>
                                                <span
                                                    class="text-sm font-mono font-bold tracking-widest text-neutral-700 dark:text-neutral-300"
                                                    x-text="issue.id"></span>
                                                <!-- Page URL Badge -->
                                                <template x-if="scanMode === 'website' && issue.url">
                                                    <span
                                                        class="text-[10px] font-mono border border-black/10 dark:border-white/10 px-1.5 py-0.5 bg-neutral-50 dark:bg-neutral-900 text-neutral-500"
                                                        x-text="new URL(issue.url).pathname"></span>
                                                </template>
                                                <!-- Preview Button -->
                                                <button @click="loadPreview(issue)"
                                                    class="inline-flex items-center justify-center px-2.5 py-1.5 border border-black/30 dark:border-white/30 text-xs font-mono font-bold uppercase tracking-widest hover:border-black dark:hover:border-white hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors"
                                                    title="Preview element on page"><svg
                                                        xmlns="http://www.w3.org/2000/svg" width="14"
                                                        height="14" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg></button>
                                                <!-- AI Fix Button -->
                                                <template x-if="issue.fileName">
                                                    <button @click="requestAiFix(issue)"
                                                        class="inline-flex items-center justify-center px-2.5 py-1.5 border border-black/30 dark:border-white/30 text-xs font-mono font-bold uppercase tracking-widest hover:border-black dark:hover:border-white hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors"
                                                        title="Fix with AI">AI FIX</button>
                                                </template>

                                            </div>
                                            <h4 class="text-base font-sans font-medium text-black dark:text-white"
                                                x-text="issue.description"></h4>
                                        </div>
                                        <a :href="issue.helpUrl" target="_blank"
                                            class="flex-shrink-0 inline-flex items-center gap-1.5 text-xs font-mono font-bold border border-black dark:border-white hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors uppercase px-2.5 py-1.5 text-black dark:text-white">
                                            VIEW_DOCS ->
                                        </a>
                                    </div>

                                    <div class="mt-6">
                                        <p
                                            class="text-xs font-mono font-bold text-neutral-600 dark:text-neutral-300 mb-2 uppercase tracking-widest">
                                            <span class="text-black dark:text-white">>>></span> FAILING_NODE
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
                                                <span class="text-black dark:text-white">>>></span> SRC_LOC
                                                <span x-show="editorEnabled"
                                                    class="normal-case tracking-normal font-normal text-neutral-400 dark:text-neutral-500 ml-1"
                                                    x-cloak>— click to open</span>
                                            </p>
                                            <template x-if="issue.fileName">
                                                <div class="flex items-center gap-2 text-sm font-mono bg-white dark:bg-black border border-black dark:border-neutral-700 px-3 py-2 w-max text-black dark:text-white transition-colors"
                                                    :class="editorEnabled ?
                                                        'cursor-pointer hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black group' :
                                                        ''"
                                                    :title="editorEnabled ? ('Open in ' + editorLabel) : ''"
                                                    @click="openInEditor(issue.fileName, issue.lineNumber)">
                                                    <span x-text="issue.fileName + ':' + issue.lineNumber"></span>
                                                    <span x-show="editorEnabled"
                                                        class="text-base leading-none opacity-60 group-hover:opacity-100 transition-opacity"
                                                        aria-hidden="true">↗</span>
                                                </div>
                                            </template>
                                            <template x-if="!issue.fileName">
                                                <div
                                                    class="flex items-center gap-2 text-sm font-mono text-[#D01D10] dark:text-[#FF4D40] border border-[#FF2D20] border-dashed px-3 py-2 w-max uppercase bg-[#FF2D20]/10">
                                                    [ PENDING_LOCATOR ]
                                                </div>
                                            </template>
                                        </div>
                                        <div class="sm:text-right" x-data="{ copied: false }">
                                            <p
                                                class="text-xs font-mono font-bold text-neutral-600 dark:text-neutral-300 uppercase tracking-widest mb-2 block sm:inline-block">
                                                <span class="text-black dark:text-white sm:hidden">>>></span>
                                                CSS_SELECTOR
                                                <span
                                                    class="normal-case tracking-normal font-normal text-neutral-400 dark:text-neutral-500 ml-1">—
                                                    click to copy</span>
                                            </p>
                                            <div class="group cursor-pointer flex items-center gap-2 text-sm font-mono bg-white dark:bg-black border border-black dark:border-neutral-700 px-3 py-2 overflow-x-auto break-all sm:ml-auto w-fit max-w-full text-black dark:text-white transition-colors hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black"
                                                @click="navigator.clipboard.writeText(issue.selector).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                                title="Copy selector">
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
                <span class="text-neutral-500">WCAG Accessibility Auditor &middot; Powered by axe-core &amp; AI</span>
                <span class="text-neutral-400 dark:text-neutral-600">A / AA / AAA &nbsp;&bull;&nbsp; Laravel 10 / 11 /
                    12</span>
            </div>
        </div>
    </footer>

    <!-- AI Fix Modal -->
    <div x-show="showFixModal" @keydown.escape.window="closeFixModal()"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-cloak>
        <div
            class="bg-white dark:bg-black border-2 border-black dark:border-white w-full max-w-4xl relative shadow-[8px_8px_0px_rgba(0,0,0,1)] dark:shadow-[8px_8px_0px_rgba(255,255,255,0.2)] flex flex-col max-h-[90vh]">

            <!-- Header -->
            <div
                class="border-b border-black dark:border-white px-6 py-4 flex items-center justify-between bg-neutral-100 dark:bg-neutral-900 shrink-0">
                <div>
                    <h3 class="text-lg font-mono font-bold uppercase tracking-widest">[ AI_FIX ]</h3>
                    <p class="text-xs font-mono text-neutral-500 uppercase tracking-widest mt-0.5"
                        x-text="fixIssue?.id ?? ''"></p>
                </div>
                <button @click="closeFixModal()"
                    class="w-8 h-8 inline-flex items-center justify-center border border-transparent hover:border-black dark:hover:border-white hover:text-[#E11D48] font-mono font-bold text-xl leading-none transition-colors text-black dark:text-white">&times;</button>
            </div>

            <!-- Body -->
            <div class="overflow-y-auto flex-1 p-6 space-y-6">

                <!-- Loading -->
                <div x-show="isLoadingFix" class="flex flex-col items-center justify-center py-16 gap-4">
                    <div
                        class="w-6 h-6 rounded-full border-2 border-black dark:border-white border-t-transparent animate-spin">
                    </div>
                    <div class="font-mono text-center">
                        <p class="text-sm font-bold uppercase tracking-widest">Consulting AI...</p>
                        <p class="text-xs text-neutral-500 mt-1 uppercase tracking-widest">AI is analyzing the
                            accessibility issue</p>
                    </div>
                </div>

                <!-- Error -->
                <div x-show="!isLoadingFix && fixError" x-cloak class="border-2 border-dashed border-[#E11D48] p-4">
                    <p class="font-mono text-xs font-bold uppercase tracking-widest text-[#E11D48] mb-2">[ERR] Fix
                        generation failed</p>
                    <p class="font-mono text-sm" x-text="fixError"></p>
                </div>

                <!-- Applied success -->
                <div x-show="fixApplied" x-cloak class="border-2 border-green-500 p-6 text-center space-y-2">
                    <p class="font-mono text-sm font-bold uppercase tracking-widest text-green-500">✓ Fix Applied
                        Successfully</p>
                    <p class="font-mono text-xs text-neutral-500 uppercase tracking-widest">The file has been updated.
                        Re-scan to verify the fix.</p>
                </div>

                <!-- Fix Data -->
                <template x-if="!isLoadingFix && fixData && !fixApplied">
                    <div class="space-y-6">

                        <!-- AI Explanation -->
                        <div class="bg-neutral-100 dark:bg-neutral-900 border-l-4 border-black dark:border-white p-4">
                            <p class="text-xs font-mono font-bold uppercase tracking-widest text-neutral-500 mb-2">>>
                                AI Explanation</p>
                            <p class="font-mono text-sm leading-relaxed" x-text="fixData.explanation"></p>
                        </div>

                        <!-- File info -->
                        <div class="font-mono text-xs text-neutral-500 uppercase tracking-widest">
                            <span class="font-bold text-black dark:text-white">File:</span>
                            <span x-text="fixData.fileName + '  (context from line ' + fixData.startLine + ')'"></span>
                        </div>

                        <!-- Diff view -->
                        <div>
                            <p class="text-xs font-mono font-bold uppercase tracking-widest text-neutral-500 mb-2">>>
                                Diff</p>
                            <div class="border border-black dark:border-neutral-700 overflow-hidden bg-[#0d1117]">
                                <!-- Diff legend -->
                                <div
                                    class="flex items-center gap-4 px-4 py-2 bg-neutral-800 border-b border-neutral-700 text-xs font-mono">
                                    <span class="text-red-400">— original</span>
                                    <span class="text-neutral-600">|</span>
                                    <span class="text-green-400">+ fixed</span>
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
            <div x-show="!isLoadingFix && fixData && !fixApplied" x-cloak
                class="border-t border-black dark:border-white px-6 py-4 flex justify-end gap-3 bg-neutral-100 dark:bg-neutral-900 shrink-0">
                <button @click="closeFixModal()"
                    class="px-6 py-2 border-2 border-black dark:border-white font-mono text-sm font-bold uppercase tracking-widest hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors">REJECT</button>
                <button @click="applyFix()" :disabled="isApplyingFix"
                    class="px-6 py-2 bg-[#E11D48] border-2 border-[#E11D48] text-white font-mono text-sm font-bold uppercase tracking-widest hover:bg-black hover:border-black transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!isApplyingFix">ACCEPT &amp; APPLY</span>
                    <span x-show="isApplyingFix" x-cloak>APPLYING...</span>
                </button>
            </div>

            <!-- Footer — after applied -->
            <div x-show="fixApplied" x-cloak
                class="border-t border-black dark:border-white px-6 py-4 flex justify-end bg-neutral-100 dark:bg-neutral-900 shrink-0">
                <button @click="closeFixModal()"
                    class="px-6 py-2 border-2 border-black dark:border-white font-mono text-sm font-bold uppercase tracking-widest hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors">CLOSE</button>
            </div>

        </div>
    </div>

    <!-- Preview Modal -->
    <div x-show="showPreviewModal" @keydown.escape.window="closePreview()"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-cloak>
        <div
            class="bg-white dark:bg-black border-2 border-black dark:border-white w-full max-w-5xl relative shadow-[8px_8px_0px_rgba(0,0,0,1)] dark:shadow-[8px_8px_0px_rgba(255,255,255,0.2)]">
            <div
                class="border-b border-black dark:border-white px-6 py-4 flex items-center justify-between bg-neutral-100 dark:bg-neutral-900">
                <h3 class="text-lg font-mono font-bold uppercase tracking-widest">[ ELEMENT_PREVIEW ]</h3>
                <button @click="closePreview()"
                    class="w-8 h-8 inline-flex items-center justify-center border border-transparent hover:border-black dark:hover:border-white hover:text-[#E11D48] font-mono font-bold text-xl leading-none transition-colors text-black dark:text-white">&times;</button>
            </div>
            <div class="p-6">
                <!-- Loading -->
                <div x-show="isLoadingPreview" class="flex flex-col items-center justify-center py-20 gap-3">
                    <div
                        class="w-5 h-5 rounded-full border-2 border-black dark:border-white border-t-transparent animate-spin">
                    </div>
                    <span class="font-mono text-xs uppercase tracking-widest text-neutral-500">Rendering
                        screenshot...</span>
                </div>
                <!-- Screenshot -->
                <div x-show="!isLoadingPreview && previewScreenshot" x-cloak>
                    <img :src="previewScreenshot" class="w-full border border-black dark:border-neutral-700"
                        alt="Element preview screenshot" />
                    <div class="mt-3 flex items-center justify-between gap-4">
                        <p
                            class="text-xs font-mono text-neutral-400 dark:text-neutral-500 uppercase tracking-widest truncate">
                            Selector: <span class="text-black dark:text-white" x-text="previewIssue?.selector"></span>
                        </p>
                        <a :href="previewScreenshot" :download="'preview-' + (previewIssue?.id ?? 'element') + '.png'"
                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-white text-xs font-mono font-bold uppercase tracking-widest hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-colors">⬇
                            SAVE</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>

    <script>
        const LENS_VIEWS_PATH = @json(resource_path('views'));
        const LENS_EDITOR = @json(config('lens-for-laravel.editor', 'vscode'));

        document.addEventListener('alpine:init', () => {
            Alpine.data('scanner', () => ({
                url: '{{ url('/') }}',
                isLoading: false,
                hasResults: false,
                error: null,
                issues: [],
                theme: localStorage.getItem('theme') || 'light',
                activeFilter: null,

                // Scan Mode & Progress
                scanMode: 'single', // 'single' | 'website' | 'multiple'
                urlsText: '', // textarea content for multiple mode, one URL per line
                progressStatus: 'Initializing...',
                progressPercent: 0,

                // PDF Export State
                isGeneratingPdf: false,

                // Preview State
                showPreviewModal: false,
                isLoadingPreview: false,
                previewScreenshot: null,
                previewIssue: null,

                // AI Fix State
                showFixModal: false,
                isLoadingFix: false,
                fixIssue: null,
                fixData: null,
                isApplyingFix: false,
                fixApplied: false,
                fixError: null,


                init() {
                    document.documentElement.classList.toggle('dark', this.theme === 'dark');
                    this.$watch('theme', val => {
                        document.documentElement.classList.toggle('dark', val === 'dark');
                        localStorage.setItem('theme', val);
                    });
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
                    return this.issues.filter(i => i.tags && i.tags.includes('wcag2a')).length;
                },
                get levelAAIssues() {
                    return this.issues.filter(i => i.tags && i.tags.includes('wcag2aa')).length;
                },
                get levelAAAIssues() {
                    return this.issues.filter(i => i.tags && i.tags.includes('wcag2aaa')).length;
                },
                get otherIssuesCount() {
                    return this.issues.filter(i => !i.tags || (!i.tags.includes('wcag2a') && !i.tags
                        .includes('wcag2aa') && !i.tags.includes('wcag2aaa'))).length;
                },

                get levelDescription() {
                    switch (this.activeFilter) {
                        case 'wcag2a':
                            return 'Level A is the minimum level of accessibility. These issues are critical blockers for users with disabilities.';
                        case 'wcag2aa':
                            return 'Level AA is the standard for accessibility. It removes most common barriers for people with a wide range of disabilities.';
                        case 'wcag2aaa':
                            return 'Level AAA is the highest level of accessibility. It provides an enhanced experience, though it can be difficult to achieve for all content.';
                        case 'other':
                            return 'These are best practice recommendations and general improvements that don\'t strictly fall into a WCAG level but improve UX.';
                        default:
                            return null;
                    }
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

                openInEditor(fileName, lineNumber) {
                    if (!fileName || !this.editorEnabled) return;
                    const path = LENS_VIEWS_PATH + '/' + fileName;
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
                        if (this.activeFilter === 'other') {
                            return this.issues.filter(i => !i.tags || (!i.tags.includes('wcag2a') &&
                                !i.tags.includes('wcag2aa') && !i.tags.includes('wcag2aaa')
                            ));
                        }
                        return this.issues.filter(i => i.tags && i.tags.includes(this
                            .activeFilter));
                    }

                    // Sort issues by WCAG level when no filter is active
                    return [...this.issues].sort((a, b) => {
                        const getWeight = (issue) => {
                            if (issue.tags && issue.tags.includes('wcag2a')) return 1;
                            if (issue.tags && issue.tags.includes('wcag2aa')) return 2;
                            if (issue.tags && issue.tags.includes('wcag2aaa')) return 3;
                            return 4;
                        };
                        return getWeight(a) - getWeight(b);
                    });
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
                        if (!response.ok) throw new Error('Screenshot failed.');
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
                                url: this.url
                            })
                        });

                        if (!response.ok) {
                            const data = await response.json().catch(() => ({}));
                            throw new Error(data.message || 'PDF generation failed.');
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
                            this.progressStatus = 'Scanning page...';
                            this.progressPercent = 50;
                            await this.scanSingleUrl(this.url, token);
                            this.progressPercent = 100;
                        } else if (this.scanMode === 'website') {
                            this.progressStatus = 'Crawling website...';
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
                                'Crawling failed.');

                            const urls = crawlData.urls || [];
                            if (urls.length === 0) throw new Error('No internal links discovered.');

                            // 2. Scan each URL
                            for (let i = 0; i < urls.length; i++) {
                                const currentUrl = urls[i];
                                this.progressPercent = 10 + Math.round(((i) / urls.length) * 90);
                                this.progressStatus =
                                    `Scanning [${i + 1}/${urls.length}]: ${currentUrl}`;

                                try {
                                    await this.scanSingleUrl(currentUrl, token, true);
                                } catch (e) {
                                    console.error(`Failed to scan ${currentUrl}:`, e);
                                }
                            }
                            this.progressPercent = 100;
                            this.progressStatus = 'Scan complete.';
                        } else if (this.scanMode === 'multiple') {
                            const urlList = this.urlsText
                                .split('\n')
                                .map(u => u.trim())
                                .filter(u => u.length > 0);

                            if (urlList.length === 0) throw new Error('No URLs provided.');

                            for (let i = 0; i < urlList.length; i++) {
                                const currentUrl = urlList[i];
                                this.progressPercent = Math.round((i / urlList.length) * 100);
                                this.progressStatus =
                                    `Scanning [${i + 1}/${urlList.length}]: ${currentUrl}`;
                                try {
                                    await this.scanSingleUrl(currentUrl, token, i > 0);
                                } catch (e) {
                                    console.error(`Failed to scan ${currentUrl}:`, e);
                                }
                            }
                            this.progressPercent = 100;
                            this.progressStatus = 'Scan complete.';
                        }

                        this.hasResults = true;
                    } catch (err) {
                        this.error = err.message;
                    } finally {
                        this.isLoading = false;
                    }
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
                            url: targetUrl
                        })
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'An error occurred during scanning.');
                    }

                    if (append) {
                        this.issues = [...this.issues, ...(data.issues || [])];
                    } else {
                        this.issues = data.issues || [];
                    }
                },

                async requestAiFix(issue) {
                    this.fixIssue = issue;
                    this.fixData = null;
                    this.fixError = null;
                    this.fixApplied = false;
                    this.isLoadingFix = true;
                    this.showFixModal = true;
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
                                body: JSON.stringify({
                                    htmlSnippet: issue.htmlSnippet,
                                    description: issue.description,
                                    fileName: issue.fileName,
                                    lineNumber: issue.lineNumber,
                                    tags: issue.tags ?? [],
                                })
                            });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message ||
                            'AI fix generation failed.');
                        this.fixData = data;
                    } catch (err) {
                        this.fixError = err.message;
                    } finally {
                        this.isLoadingFix = false;
                    }
                },

                async applyFix() {
                    if (!this.fixData) return;
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
                                fixedCode: this.fixData.fixedCode,
                            })
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || 'Failed to apply fix.');
                        this.fixApplied = true;
                    } catch (err) {
                        this.fixError = err.message;
                    } finally {
                        this.isApplyingFix = false;
                    }
                },

                closeFixModal() {
                    this.showFixModal = false;
                    this.fixIssue = null;
                    this.fixData = null;
                    this.fixError = null;
                    this.fixApplied = false;
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
                        this.fixData.fixedCode.split('\n')
                    );
                },

                getBadgeColor(impact, tags) {
                    if (tags && tags.includes('wcag2a'))
                        return 'bg-[#E11D48] text-white border border-[#E11D48]';
                    if (tags && tags.includes('wcag2aa'))
                        return 'bg-white text-black dark:bg-black dark:text-white border border-black dark:border-white';
                    if (tags && tags.includes('wcag2aaa'))
                        return 'bg-white text-neutral-600 dark:bg-black dark:text-neutral-400 border border-dashed border-neutral-600 dark:border-neutral-400';

                    // Fallback to OTHER style (subtle but readable)
                    return 'bg-white text-neutral-700 dark:bg-black dark:text-neutral-300 border border-dotted border-neutral-700 dark:border-neutral-300';
                }
            }));
        });
    </script>
</body>

</html>
