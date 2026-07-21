<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('lens-for-laravel::messages.recorder.title') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&display=swap"
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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                },
            },
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            color-scheme: light;
            --lens-page: #ffffff;
            --lens-panel: #f4f4f5;
            --lens-content: #09090b;
            --lens-body: #27272a;
            --lens-muted: #52525b;
            --lens-subtle: #71717a;
            --lens-divider: #a1a1aa;
            --lens-control: #71717a;
            --lens-accent: #c52b21;
            --lens-accent-solid: #c52b21;
            --lens-focus: #1d4ed8;
        }

        .dark {
            color-scheme: dark;
            --lens-page: #09090b;
            --lens-panel: #18181b;
            --lens-content: #fafafa;
            --lens-body: #e4e4e7;
            --lens-muted: #c4c4cc;
            --lens-subtle: #a1a1aa;
            --lens-divider: #52525b;
            --lens-control: #a1a1aa;
            --lens-accent: #ff8a8a;
            --lens-accent-solid: #b91c1c;
            --lens-focus: #fde047;
        }

        [x-cloak] {
            display: none !important;
        }

        body {
            background: var(--lens-page) !important;
            color: var(--lens-body) !important;
        }

        body::selection,
        body ::selection {
            background: var(--lens-accent-solid) !important;
            color: #ffffff !important;
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

        [class~="text-neutral-400"],
        [class~="text-neutral-500"] {
            color: var(--lens-muted) !important;
        }

        [class~="text-neutral-200"] {
            color: var(--lens-body) !important;
        }

        [class~="border-white/10"] {
            border-color: var(--lens-divider) !important;
        }

        [class~="border-black/10"] {
            border-color: var(--lens-divider) !important;
        }

        [class~="border-black/20"],
        [class~="border-black/30"],
        [class~="border-white/15"],
        [class~="border-white/20"],
        [class~="border-white/30"] {
            border-color: var(--lens-control) !important;
        }

        [class~="bg-[#E11D48]"] {
            background-color: var(--lens-accent-solid) !important;
        }

        [class~="border-[#E11D48]"] {
            border-color: var(--lens-accent-solid) !important;
        }

        [class~="text-[#E11D48]"],
        [class~="hover:text-[#E11D48]"]:hover {
            color: var(--lens-accent) !important;
        }

        [class~="hover:bg-[#E11D48]"]:hover {
            background-color: var(--lens-accent-solid) !important;
        }

        input::placeholder {
            color: var(--lens-subtle) !important;
            opacity: 1;
        }

        header [class~="text-[10px]"],
        header [class~="text-[11px]"] {
            font-size: 0.6875rem !important;
            line-height: 0.9375rem !important;
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

<body class="h-screen overflow-hidden bg-white text-black dark:bg-black dark:text-white" x-data="stateRecorder()" x-init="init()">
    <a href="#recorder-preview" class="skip-link">{{ __('lens-for-laravel::messages.preview.frame_title') }}</a>

    <div class="grid h-screen grid-rows-[auto_1fr]">
        <header class="border-b border-black/20 bg-white dark:border-white/20 dark:bg-black">
            <div class="flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <span class="h-2.5 w-2.5 rounded-full" :class="recording ? 'bg-[#E11D48]' : 'bg-neutral-500'"></span>
                        <h1 class="font-mono text-sm font-bold uppercase tracking-widest">{{ __('lens-for-laravel::messages.recorder.title') }}</h1>
                    </div>
                    <p class="mt-1 truncate font-mono text-xs text-neutral-500 dark:text-neutral-400" x-text="targetUrl"></p>
                </div>

                <div class="flex flex-wrap items-center gap-2 font-mono text-xs font-bold uppercase tracking-widest">
                    <button type="button" @click="recording = !recording"
                        class="border px-3 py-2"
                        :class="recording ? 'border-[#E11D48] bg-[#E11D48] text-white' : 'border-black text-black hover:bg-black hover:text-white dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-black'">
                        <span x-text="recording ? t.recording : t.record"></span>
                    </button>
                    <button type="button" @click="reloadPreview()"
                        class="border border-black/30 px-3 py-2 text-black hover:border-black dark:border-white/30 dark:text-white dark:hover:border-white">{{ __('lens-for-laravel::messages.recorder.reload') }}</button>
                    <button type="button" @click="addState()"
                        class="border border-black/30 px-3 py-2 text-black hover:border-black dark:border-white/30 dark:text-white dark:hover:border-white">{{ __('lens-for-laravel::messages.recorder.new_state') }}</button>
                    <button type="button" @click="sendToDashboard()"
                        class="border border-[#E11D48] px-3 py-2 text-[#E11D48] hover:bg-[#E11D48] hover:text-white">{{ __('lens-for-laravel::messages.recorder.send_script') }}</button>
                </div>
            </div>

            <div class="grid gap-3 border-t border-black/10 px-4 py-3 dark:border-white/10 lg:grid-cols-[0.8fr_1.2fr]">
                <div class="min-w-0">
                    <label class="block font-mono text-[10px] font-bold uppercase tracking-widest text-neutral-500 dark:text-neutral-400">
                        {{ __('lens-for-laravel::messages.recorder.current_state') }}
                    </label>
                    <div class="mt-2 flex gap-2">
                        <select x-model.number="activeStateIndex"
                            class="min-w-0 flex-1 rounded-none border border-black/30 bg-white px-3 py-2 font-mono text-xs text-black outline-none focus:border-[#E11D48] dark:border-white/20 dark:bg-black dark:text-white">
                            <template x-for="(state, index) in states" :key="state.id">
                                <option :value="index" x-text="`${index + 1}. ${state.label || t.unnamedState} (${state.actions.length})`"></option>
                            </template>
                        </select>
                        <input type="text" x-model="activeState.label" maxlength="80"
                            class="min-w-0 flex-1 rounded-none border border-black/30 bg-white px-3 py-2 font-mono text-xs text-black outline-none focus:border-[#E11D48] dark:border-white/20 dark:bg-black dark:text-white"
                            placeholder="{{ __('lens-for-laravel::messages.recorder.state_label') }}">
                    </div>
                    <p class="mt-2 font-mono text-[11px] text-neutral-500 dark:text-neutral-400">
                        {{ __('lens-for-laravel::messages.recorder.hint') }}
                    </p>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-mono text-[10px] font-bold uppercase tracking-widest text-neutral-500 dark:text-neutral-400">{{ __('lens-for-laravel::messages.recorder.recorded_script') }}</span>
                        <button type="button" @click="clearActions()"
                            class="font-mono text-[10px] font-bold uppercase tracking-widest text-neutral-500 hover:text-[#E11D48] dark:text-neutral-400">{{ __('lens-for-laravel::messages.recorder.clear') }}</button>
                    </div>
                    <textarea readonly x-text="script"
                        class="mt-2 h-24 w-full resize-none rounded-none border border-black/30 bg-neutral-100 px-3 py-2 font-mono text-xs text-neutral-800 outline-none dark:border-white/20 dark:bg-neutral-950 dark:text-neutral-200"></textarea>
                </div>
            </div>

            <div x-show="message" x-cloak role="status" aria-live="polite"
                class="border-t border-black/10 px-4 py-2 font-mono text-xs dark:border-white/10"
                :class="messageType === 'error' ? 'bg-[#E11D48] text-white' : 'bg-white text-black'"
                x-text="message"></div>
        </header>

        <main id="recorder-preview" tabindex="-1" class="relative min-h-0">
            <iframe x-ref="preview" :src="previewUrl" @load="attachFrameListeners()"
                class="h-full w-full bg-white" title="{{ __('lens-for-laravel::messages.preview.frame_title') }}"></iframe>
        </main>
    </div>

    <script>
        function stateRecorder() {
            return {
                targetUrl: @json($targetUrl),
                previewUrl: @json($targetUrl),
                t: {{ Illuminate\Support\Js::from([
                    'record' => __('lens-for-laravel::messages.recorder.record'),
                    'recording' => __('lens-for-laravel::messages.recorder.recording'),
                    'unnamedState' => __('lens-for-laravel::messages.recorder.unnamed_state'),
                    'initialPage' => __('lens-for-laravel::messages.recorder.initial_page'),
                    'state' => __('lens-for-laravel::messages.recorder.state'),
                    'newStateReady' => __('lens-for-laravel::messages.recorder.new_state_ready'),
                    'cleared' => __('lens-for-laravel::messages.recorder.cleared'),
                    'cannotRecord' => __('lens-for-laravel::messages.recorder.cannot_record'),
                    'attached' => __('lens-for-laravel::messages.recorder.attached'),
                    'sent' => __('lens-for-laravel::messages.recorder.sent'),
                ]) }},
                recording: true,
                activeStateIndex: 0,
                message: '',
                messageType: 'info',
                cleanup: null,
                states: [
                    { id: 1, label: @json(__('lens-for-laravel::messages.recorder.initial_page')), actions: [] },
                ],

                init() {
                    this.channel = 'BroadcastChannel' in window ? new BroadcastChannel('lens-state-recorder') : null;
                },

                get activeState() {
                    return this.states[this.activeStateIndex] || this.states[0];
                },

                get script() {
                    return this.states
                        .filter(state => (state.label || '').trim())
                        .map(state => {
                            const lines = [`state: ${this.normalize(state.label)}`];
                            state.actions.forEach(action => lines.push(this.formatAction(action)));
                            return lines.join('\n');
                        })
                        .join('\n\n');
                },

                normalize(value) {
                    return String(value || '').replace(/\s+/g, ' ').trim();
                },

                addState() {
                    this.states.push({
                        id: Date.now() + Math.random(),
                        label: this.t.state.replace(':number', this.states.length + 1),
                        actions: [],
                    });
                    this.activeStateIndex = this.states.length - 1;
                    this.flash(this.t.newStateReady);
                },

                clearActions() {
                    this.states = [{ id: 1, label: this.t.initialPage, actions: [] }];
                    this.activeStateIndex = 0;
                    this.flash(this.t.cleared);
                },

                reloadPreview() {
                    this.detachFrameListeners();
                    this.previewUrl = 'about:blank';
                    this.$nextTick(() => {
                        this.previewUrl = this.targetUrl;
                    });
                },

                attachFrameListeners() {
                    this.detachFrameListeners();

                    let doc;
                    try {
                        doc = this.$refs.preview.contentDocument || this.$refs.preview.contentWindow.document;
                    } catch (e) {
                        this.flash(this.t.cannotRecord, 'error');
                        return;
                    }

                    if (!doc || !doc.body || this.$refs.preview.src === 'about:blank') return;

                    const onClick = (event) => {
                        if (!this.recording) return;
                        const target = event.target.closest('button, a, [role="button"], input, select, textarea, label, summary, [tabindex]');
                        if (!target) return;

                        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) && target.type !== 'checkbox' && target.type !== 'radio') {
                            return;
                        }

                        this.recordAction({ type: 'click', selector: this.selectorForElement(target) });
                    };

                    const onChange = (event) => {
                        if (!this.recording) return;
                        const target = event.target;
                        if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) return;

                        if (target.tagName === 'SELECT') {
                            this.recordAction({ type: 'select', selector: this.selectorForElement(target), value: target.value });
                            return;
                        }

                        if (target.type === 'checkbox' || target.type === 'radio') {
                            this.recordAction({ type: target.checked ? 'check' : 'uncheck', selector: this.selectorForElement(target) });
                            return;
                        }

                        this.recordAction({ type: 'type', selector: this.selectorForElement(target), value: target.value });
                    };

                    doc.addEventListener('click', onClick, true);
                    doc.addEventListener('change', onChange, true);
                    this.cleanup = () => {
                        doc.removeEventListener('click', onClick, true);
                        doc.removeEventListener('change', onChange, true);
                    };
                    this.flash(this.t.attached);
                },

                detachFrameListeners() {
                    if (this.cleanup) {
                        this.cleanup();
                        this.cleanup = null;
                    }
                },

                recordAction(action) {
                    if (!action.selector && action.type !== 'wait') return;

                    const previous = this.activeState.actions[this.activeState.actions.length - 1];
                    if (previous && JSON.stringify(previous) === JSON.stringify(action)) return;

                    this.activeState.actions.push({
                        id: Date.now() + Math.random(),
                        ...action,
                    });
                    this.flash(`Recorded ${this.formatAction(action)}`);
                },

                formatAction(action) {
                    if (action.type === 'wait') return `wait: ${action.ms ?? 300}`;
                    if (action.type === 'type' || action.type === 'select') {
                        return `${action.type}: ${action.selector} => ${action.value ?? ''}`;
                    }
                    return `${action.type}: ${action.selector}`;
                },

                sendToDashboard() {
                    const payload = {
                        type: 'lens-state-script',
                        script: this.script,
                        targetUrl: this.targetUrl,
                    };

                    localStorage.setItem('lens-state-recorder-script', JSON.stringify(payload));
                    this.channel?.postMessage(payload);
                    window.opener?.postMessage(payload, window.location.origin);
                    this.flash(this.t.sent);
                },

                flash(message, type = 'info') {
                    this.message = message;
                    this.messageType = type;
                    clearTimeout(this.messageTimer);
                    this.messageTimer = setTimeout(() => {
                        this.message = '';
                    }, 3000);
                },

                selectorForElement(element) {
                    const escape = (value) => {
                        if (window.CSS && typeof window.CSS.escape === 'function') {
                            return window.CSS.escape(value);
                        }

                        return String(value).replace(/["\\]/g, '\\$&');
                    };

                    if (element.id) return `#${escape(element.id)}`;

                    for (const attr of ['data-testid', 'data-test', 'data-cy', 'data-action']) {
                        if (element.hasAttribute(attr)) {
                            return `[${attr}="${escape(element.getAttribute(attr))}"]`;
                        }
                    }

                    for (const attr of element.getAttributeNames()) {
                        if (attr.startsWith('data-') && element.getAttribute(attr) === '') {
                            return `[${attr}]`;
                        }
                    }

                    if (element.getAttribute('name')) {
                        return `${element.tagName.toLowerCase()}[name="${escape(element.getAttribute('name'))}"]`;
                    }

                    if (element.getAttribute('aria-label')) {
                        return `${element.tagName.toLowerCase()}[aria-label="${escape(element.getAttribute('aria-label'))}"]`;
                    }

                    const parts = [];
                    let current = element;
                    while (current && current.nodeType === 1 && current.tagName.toLowerCase() !== 'html') {
                        let part = current.tagName.toLowerCase();
                        const classes = Array.from(current.classList || [])
                            .filter(className => !className.includes(':'))
                            .slice(0, 2);
                        if (classes.length) part += `.${classes.map(escape).join('.')}`;

                        const parent = current.parentElement;
                        if (parent) {
                            const siblings = Array.from(parent.children).filter(child => child.tagName === current.tagName);
                            if (siblings.length > 1) part += `:nth-of-type(${siblings.indexOf(current) + 1})`;
                        }

                        parts.unshift(part);
                        if (parts.length >= 4) break;
                        current = parent;
                    }

                    return parts.join(' > ');
                },
            };
        }
    </script>
</body>

</html>
