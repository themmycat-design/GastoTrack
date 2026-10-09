<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transactions | GastoTrack</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="gt-owner-page min-h-screen bg-[#F4F8F7] font-sans text-[#0A2E2A] antialiased" style="font-family: Inter, ui-sans-serif, system-ui, sans-serif">
    <div class="min-h-screen lg:flex">
        @include('layouts.owner-navigation')

        <main class="min-w-0 flex-1">
            @include('layouts.owner-topbar')

            <div class="mx-auto max-w-[1600px] space-y-6 px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
                <section class="gt-enter flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                    <div>
                        <p class="text-sm font-semibold text-[#00A87E]">Money in and out</p>
                        <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-[#0A2E2A] sm:text-3xl">Transactions</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Review income and expenses recorded by your team, or narrow the list by source, category, and date.</p>
                    </div>
                </section>

                <section class="gt-card gt-enter gt-enter-1 p-5 sm:p-6" aria-label="Filter transactions">
                    <form id="transaction-filter-form" method="GET" action="{{ route('transactions.index') }}" data-category-url="{{ route('transactions.categories') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                        <div class="md:col-span-2 lg:col-span-5">
                            <label for="transaction-search" class="mb-2 block text-sm font-semibold text-slate-700">Search transactions</label>
                            <input id="transaction-search" type="text" name="search" value="{{ request('search') }}" placeholder="Category, source, product..." class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-4 py-3 text-sm text-[#0A2E2A] placeholder:text-slate-400 focus:border-[#00A87E] focus:ring-2 focus:ring-[#00C897]/20">
                        </div>

                        <div>
                            <label for="transaction-source" class="mb-2 block text-sm font-semibold text-slate-700">Source</label>
                            <select id="transaction-source" name="source" class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-4 py-3 text-sm text-[#0A2E2A] focus:border-[#00A87E] focus:ring-2 focus:ring-[#00C897]/20">
                                <option value="">All Sources</option>
                                @foreach($sourceOptions as $source)
                                    <option value="{{ $source }}" {{ request('source') === $source ? 'selected' : '' }}>{{ $source }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="transaction-category" class="mb-2 block text-sm font-semibold text-slate-700">Category</label>
                            <select id="transaction-category" name="category" {{ request()->filled('source') ? '' : 'disabled' }} class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-4 py-3 text-sm text-[#0A2E2A] focus:border-[#00A87E] focus:ring-2 focus:ring-[#00C897]/20 disabled:cursor-not-allowed disabled:bg-[#F4F8F7] disabled:text-slate-400">
                                <option value="">{{ request()->filled('source') ? 'All Categories' : 'Select a source first' }}</option>
                                @foreach($categoryOptions as $category)
                                    <option value="{{ $category }}" {{ request('category') === $category ? 'selected' : '' }}>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="transaction-date-from" class="mb-2 block text-sm font-semibold text-slate-700">From date</label>
                            <input id="transaction-date-from" type="date" name="date_from" value="{{ request('date_from') }}" class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-4 py-3 text-sm text-[#0A2E2A] focus:border-[#00A87E] focus:ring-2 focus:ring-[#00C897]/20">
                        </div>

                        <div>
                            <label for="transaction-date-to" class="mb-2 block text-sm font-semibold text-slate-700">To date</label>
                            <input id="transaction-date-to" type="date" name="date_to" value="{{ request('date_to') }}" class="gt-focus w-full rounded-xl border border-[#DDE9E5] bg-white px-4 py-3 text-sm text-[#0A2E2A] focus:border-[#00A87E] focus:ring-2 focus:ring-[#00C897]/20">
                        </div>

                        <div class="flex flex-wrap items-end gap-2 md:col-span-2 lg:col-span-5">
                            <button type="submit" class="gt-focus rounded-xl bg-[#00A87E] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#08745D]">Apply filters</button>
                            <a id="clear-transaction-filters" href="{{ route('transactions.index') }}" class="gt-focus rounded-xl border border-[#C8DED7] bg-white px-5 py-3 text-sm font-bold text-[#08745D] transition hover:bg-[#EAF8F2]">Clear filters</a>
                        </div>
                    </form>
                </section>

                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Transaction totals">
                    @foreach([
                        ['label' => 'Total income', 'value' => $totalIncome, 'color' => 'text-[#08745D]', 'iconBg' => 'bg-[#EAF8F2]', 'symbol' => '↑'],
                        ['label' => 'Total expenses', 'value' => $totalExpense, 'color' => 'text-rose-600', 'iconBg' => 'bg-rose-50', 'symbol' => '↓'],
                        ['label' => 'Net total', 'value' => $totalIncome - $totalExpense, 'color' => ($totalIncome - $totalExpense) >= 0 ? 'text-[#08745D]' : 'text-rose-600', 'iconBg' => 'bg-[#EAF8F2]', 'symbol' => '↗'],
                    ] as $index => $summary)
                        <article class="gt-card gt-lift gt-enter gt-enter-{{ $index + 1 }} p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-500">{{ $summary['label'] }}</p>
                                    <p class="mt-3 text-2xl font-extrabold tracking-tight {{ $summary['color'] }} sm:text-3xl">&#8369;{{ number_format($summary['value'], 2) }}</p>
                                    <p class="mt-2 text-xs text-slate-400">Across all business transactions</p>
                                </div>
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl {{ $summary['iconBg'] }} text-xl font-bold {{ $summary['color'] }}" aria-hidden="true">{{ $summary['symbol'] }}</span>
                            </div>
                        </article>
                    @endforeach
                </section>

                @include('transactions.partials.activity')
            </div>
        </main>
    </div>

    <script>
        (() => {
            const filterForm = document.getElementById('transaction-filter-form');
            const sourceSelect = document.getElementById('transaction-source');
            const categorySelect = document.getElementById('transaction-category');
            const filterButton = filterForm.querySelector('button[type="submit"]');
            const clearFiltersLink = document.getElementById('clear-transaction-filters');
            let activityController = null;
            let categoryController = null;

            const cleanUrl = (value) => {
                const url = new URL(value, window.location.origin);
                url.searchParams.delete('fragment');
                return url;
            };

            const activityUrl = (value) => {
                const url = cleanUrl(value);
                url.searchParams.set('fragment', 'activity');
                return url;
            };

            async function loadActivity(value, updateHistory = false) {
                const displayUrl = cleanUrl(value);
                const requestUrl = activityUrl(value);
                const activity = document.getElementById('owner-activity');
                const loadingMessage = activity.querySelector('[data-activity-loading]');

                activityController?.abort();
                const requestController = new AbortController();
                activityController = requestController;
                activity.setAttribute('aria-busy', 'true');
                activity.classList.add('opacity-60');
                loadingMessage.textContent = 'Updating activity…';
                loadingMessage.classList.remove('hidden');
                filterButton.disabled = true;
                filterButton.textContent = 'Loading...';

                try {
                    const response = await fetch(requestUrl, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                        signal: requestController.signal,
                    });
                    if (!response.ok) throw new Error('Activity could not be loaded.');

                    const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const updatedActivity = responseDocument.getElementById('owner-activity');
                    if (!updatedActivity) throw new Error('Activity response was incomplete.');

                    document.getElementById('owner-activity').replaceWith(updatedActivity);
                    if (updateHistory) {
                        window.history.pushState({}, '', displayUrl.pathname + displayUrl.search);
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        activity.setAttribute('aria-busy', 'false');
                        activity.classList.remove('opacity-60');
                        loadingMessage.textContent = 'Could not refresh activity. Please try again.';
                        loadingMessage.classList.remove('hidden');
                    }
                } finally {
                    if (activityController === requestController) {
                        activityController = null;
                        filterButton.disabled = false;
                        filterButton.textContent = 'Apply filters';
                    }
                }
            }

            async function loadCategories(source, selectedCategory = '') {
                categoryController?.abort();
                categorySelect.replaceChildren(new Option(source ? 'Loading categories...' : 'Select a source first', ''));
                categorySelect.disabled = true;

                if (!source) return;

                const requestController = new AbortController();
                categoryController = requestController;
                const url = new URL(filterForm.dataset.categoryUrl, window.location.origin);
                url.searchParams.set('source', source);

                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        signal: requestController.signal,
                    });
                    if (!response.ok) throw new Error('Categories could not be loaded.');

                    const data = await response.json();
                    if (categoryController !== requestController) return;
                    categorySelect.replaceChildren(new Option(data.categories.length ? 'All Categories' : 'No categories available', ''));
                    data.categories.forEach((category) => categorySelect.add(new Option(category, category)));
                    categorySelect.value = selectedCategory;
                    categorySelect.disabled = false;
                } catch (error) {
                    if (error.name !== 'AbortError' && categoryController === requestController) {
                        categorySelect.replaceChildren(new Option('Categories unavailable', ''));
                    }
                }
            }

            sourceSelect.addEventListener('change', () => loadCategories(sourceSelect.value));

            filterForm.addEventListener('submit', (event) => {
                event.preventDefault();
                const url = new URL(filterForm.action, window.location.origin);
                new FormData(filterForm).forEach((value, key) => {
                    if (String(value).trim()) url.searchParams.set(key, value);
                });
                loadActivity(url, true);
            });

            clearFiltersLink.addEventListener('click', (event) => {
                event.preventDefault();
                filterForm.querySelector('[name="search"]').value = '';
                sourceSelect.value = '';
                loadCategories('');
                filterForm.querySelector('[name="date_from"]').value = '';
                filterForm.querySelector('[name="date_to"]').value = '';
                loadActivity(clearFiltersLink.href, true);
            });

            document.addEventListener('click', (event) => {
                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                const pageLink = event.target.closest('#owner-activity nav[role="navigation"] a[href], #owner-activity .pagination a[href]');
                if (!pageLink) return;
                event.preventDefault();
                loadActivity(pageLink.href, true);
            });

            window.addEventListener('popstate', () => {
                const url = new URL(window.location.href);
                filterForm.querySelector('[name="search"]').value = url.searchParams.get('search') || '';
                sourceSelect.value = url.searchParams.get('source') || '';
                filterForm.querySelector('[name="date_from"]').value = url.searchParams.get('date_from') || '';
                filterForm.querySelector('[name="date_to"]').value = url.searchParams.get('date_to') || '';
                loadCategories(sourceSelect.value, url.searchParams.get('category') || '');
                loadActivity(url);
            });
        })();
    </script>
</body>
</html>
