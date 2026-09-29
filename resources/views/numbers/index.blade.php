<x-app-layout>
    @php
        $numberGroups = collect($numbers)->chunk(10)->values();
        $allNumbers = collect($numbers)->pluck('number')->values();
        $numberLookup = collect($numbers)->keyBy('number')->all();
        $initialSelectedNumber = old('number', '');
        $initialSelectedRow = $initialSelectedNumber !== '' ? ($numberLookup[$initialSelectedNumber] ?? null) : null;
    @endphp

    <div
        class="space-y-6"
        x-data="{
            view: 'grid',
            searchTerm: '',
            modalOpen: {{ $canCreateManualRequests && $selectedBranch && $selectedDraw && $errors->any() ? 'true' : 'false' }},
            selectedNumber: @js($initialSelectedNumber),
            selectedLabel: @js($initialSelectedNumber ? __('Manual request for number :number', ['number' => $initialSelectedNumber]) : __('Select a number')),
            selectedRow: @js($initialSelectedRow),
            numberLookup: @js($numberLookup),
            allNumbers: @js($allNumbers),
            openNumber(number) {
                this.selectedNumber = number;
                this.selectedLabel = @js(__('Manual request for number :number')).replace(':number', number);
                this.selectedRow = this.numberLookup[number] ?? null;
                this.modalOpen = true;
            },
            currencySymbol: '\u20A1',
            formatAmount(value) {
                return `${this.currencySymbol}${Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            },
            statusClassesFor(row) {
                if (!row) {
                    return 'border-slate-200 bg-slate-100 text-slate-700';
                }

                if (row.status === 'blocked' || row.status === 'over_limit' || row.status === 'full') {
                    return 'border-red-200 bg-red-100 text-red-800';
                }

                if (row.status === 'warning' || row.status === 'hot') {
                    return 'border-amber-200 bg-amber-100 text-amber-800';
                }

                if (row.status === 'restricted' || row.status === 'manual_review') {
                    return 'border-purple-200 bg-purple-100 text-purple-800';
                }

                return row.max_amount !== null
                    ? 'border-emerald-200 bg-emerald-100 text-emerald-800'
                    : 'border-slate-200 bg-slate-100 text-slate-700';
            },
            statusLabelFor(row) {
                if (!row) {
                    return '';
                }

                if (row.status === 'blocked' || row.status === 'over_limit') {
                    return 'BLOCKED';
                }

                if (row.status === 'full') {
                    return 'FULL';
                }

                if (row.status === 'warning') {
                    return 'WARN';
                }

                if (row.status === 'hot') {
                    return 'HOT';
                }

                if (row.status === 'restricted') {
                    return 'RESTRICT';
                }

                if (row.status === 'manual_review') {
                    return 'REVIEW';
                }

                return row.max_amount !== null ? 'OK' : 'NO LIMIT';
            },
            matchesNumber(number) {
                const term = this.searchTerm.trim();

                if (term === '') {
                    return true;
                }

                return number.includes(term);
            },
            groupHasMatch(numbers) {
                const term = this.searchTerm.trim();

                if (term === '') {
                    return true;
                }

                return numbers.some((number) => number.includes(term));
            },
            hasAnyMatch() {
                return this.allNumbers.some((number) => this.matchesNumber(number));
            },
            clearSearch() {
                this.searchTerm = '';
            }
        }"
    >
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div class="max-w-3xl">
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">{{ __('Live board') }}</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">{{ __('Numbers') }}</h1>
                <p class="mt-1 text-sm text-slate-600">
                    {{ __('Scan 00-99 in grouped tiles, open a centered manual request modal from any card, and keep the board wide.') }}
                </p>
            </div>
            <a href="{{ route('intake-requests.index') }}" class="brand-btn-secondary">{{ __('Back to requests') }}</a>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-brand-gold/20 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="brand-card p-4 sm:p-5">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div>
                    <div class="text-sm font-medium text-slate-700">{{ __('Branch') }}</div>
                    <form method="GET" action="{{ route('numbers.index') }}" class="mt-1 grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                        <div>
                            @if ($branches->count() > 1)
                                <select id="branch_id" name="branch_id" class="brand-input block w-full rounded-xl text-sm">
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected($selectedBranch?->id === $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="hidden" name="branch_id" value="{{ $selectedBranch?->id }}">
                                <div class="rounded-xl border border-slate-200/80 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                                    {{ $selectedBranch?->name ?? __('No branch available') }}
                                </div>
                            @endif
                        </div>

                        <div>
                            <label for="draw_id" class="block text-sm font-medium text-slate-700">{{ __('Draw') }}</label>
                            <select id="draw_id" name="draw_id" class="brand-input mt-1 block w-full rounded-xl text-sm" @disabled($draws->isEmpty())>
                                @foreach ($draws as $draw)
                                    <option value="{{ $draw->id }}" @selected($selectedDraw?->id === $draw->id)>{{ $draw->name }}</option>
                                @endforeach
                            </select>
                            @if ($selectedDraw)
                                <div class="mt-2">
                                    <span class="brand-badge {{ $selectedDraw->isOpenForIntake() ? 'bg-green-100 text-green-800' : ($selectedDraw->closingReason() === 'manually_closed' ? 'bg-amber-100 text-amber-800' : ($selectedDraw->closingReason() === 'inactive' ? 'bg-slate-100 text-slate-700' : 'bg-red-100 text-red-800')) }}">
                                        {{ $selectedDraw->intakeStatusLabel() }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div>
                            <label for="draw_date" class="block text-sm font-medium text-slate-700">{{ __('Draw date') }}</label>
                            <input id="draw_date" name="draw_date" type="date" value="{{ $selectedDate }}" class="brand-input mt-1 block w-full rounded-xl text-sm">
                            @if ($selectedDayClosed)
                                <div class="mt-2">
                                    <span class="brand-badge bg-slate-100 text-slate-700">{{ __('Day closed') }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="brand-btn-primary w-full">
                                {{ __('Refresh board') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-sm text-slate-500">{{ __('Selected branch') }}</div>
                        <div class="mt-1 text-base font-semibold text-brand-navy">{{ $selectedBranch?->name ?? __('No branch available') }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-sm text-slate-500">{{ __('Selected draw') }}</div>
                        <div class="mt-1 text-base font-semibold text-brand-navy">{{ $selectedDraw?->name ?? __('No draw available') }}</div>
                        @if ($selectedDraw)
                            <div class="mt-2">
                                <span class="brand-badge {{ $selectedDraw->isOpenForIntake() ? 'bg-green-100 text-green-800' : ($selectedDraw->closingReason() === 'manually_closed' ? 'bg-amber-100 text-amber-800' : ($selectedDraw->closingReason() === 'inactive' ? 'bg-slate-100 text-slate-700' : 'bg-red-100 text-red-800')) }}">
                                    {{ $selectedDraw->intakeStatusLabel() }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <div class="brand-card border-brand-success/20 p-5">
                <div class="text-sm font-medium text-brand-success">{{ __('Total confirmed') }}</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">&#8353;{{ number_format($summary['confirmed_amount'], 2, '.', ',') }}</div>
            </div>
            <div class="brand-card border-brand-info/20 p-5">
                <div class="text-sm font-medium text-brand-info">{{ __('Total pending') }}</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">&#8353;{{ number_format($summary['pending_amount'], 2, '.', ',') }}</div>
            </div>
            <div class="brand-card border-brand-primary/20 p-5">
                <div class="text-sm font-medium text-brand-primary">{{ __('Total needs review') }}</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">&#8353;{{ number_format($summary['needs_review_amount'], 2, '.', ',') }}</div>
            </div>
            <div class="brand-card border-brand-gold/20 p-5">
                <div class="text-sm font-medium text-brand-gold">{{ __('Total active') }}</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">&#8353;{{ number_format($summary['active_amount'], 2, '.', ',') }}</div>
            </div>
            <div class="brand-card border-brand-warning/20 p-5">
                <div class="text-sm font-medium text-brand-warning">{{ __('Numbers near limit') }}</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $summary['near_limit_count'] }}</div>
            </div>
            <div class="brand-card border-brand-danger/20 p-5">
                <div class="text-sm font-medium text-brand-danger">{{ __('Numbers over limit') }}</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $summary['over_limit_count'] }}</div>
            </div>
        </div>

        <div class="brand-card p-3 sm:p-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-slate-700">{{ __('Status legend') }}</span>
                    <span class="brand-badge bg-green-50 text-green-700">{{ __('available') }} <span class="font-normal text-slate-500">{{ __('below 80% or no limit') }}</span></span>
                    <span class="brand-badge bg-amber-50 text-amber-700">{{ __('warning') }} <span class="font-normal text-slate-500">80% to 99%</span></span>
                    <span class="brand-badge bg-blue-50 text-blue-700">{{ __('full') }} <span class="font-normal text-slate-500">100%</span></span>
                    <span class="brand-badge bg-red-50 text-red-700">{{ __('over_limit') }} <span class="font-normal text-slate-500">&gt; 100%</span></span>
                    <span class="brand-badge bg-red-50 text-red-700">{{ __('blocked') }} <span class="font-normal text-slate-500">{{ __('manual review') }}</span></span>
                    <span class="brand-badge bg-amber-50 text-amber-700">{{ __('restricted') }} <span class="font-normal text-slate-500">{{ __('visual warning') }}</span></span>
                    <span class="brand-badge bg-yellow-50 text-yellow-700">{{ __('hot') }} <span class="font-normal text-slate-500">{{ __('special flag') }}</span></span>
                    <span class="brand-badge bg-purple-50 text-purple-700">{{ __('manual_review') }} <span class="font-normal text-slate-500">{{ __('manual check') }}</span></span>
                    <span class="brand-badge bg-slate-100 text-slate-700">{{ __('no_limit') }} <span class="font-normal text-slate-500">{{ __('no configured limit') }}</span></span>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="inline-flex rounded-2xl border border-slate-200 bg-slate-100 p-1">
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm font-medium transition"
                            :class="view === 'grid' ? 'bg-brand-primary text-white shadow-sm' : 'text-slate-600 hover:text-brand-navy'"
                            @click="view = 'grid'"
                        >
                            Grid view (00-99)
                        </button>
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm font-medium transition"
                            :class="view === 'table' ? 'bg-brand-primary text-white shadow-sm' : 'text-slate-600 hover:text-brand-navy'"
                            @click="view = 'table'"
                        >
                            {{ __('Detailed table') }}
                        </button>
                    </div>

                    <div class="relative w-full sm:w-80">
                        <label for="number-search" class="sr-only">{{ __('Search number') }}</label>
                        <input
                            id="number-search"
                            type="search"
                            x-model="searchTerm"
                            placeholder="{{ __('Search number...') }}"
                            class="brand-input w-full rounded-2xl py-2.5 pl-4 pr-10 text-sm"
                        >
                        <button
                            type="button"
                            class="absolute inset-y-0 right-2 inline-flex items-center justify-center rounded-xl px-2 text-slate-400 transition hover:text-slate-700"
                            @click="clearSearch()"
                            aria-label="Clear search"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <section class="space-y-3">
            <div x-show="view === 'grid'" x-cloak class="grid items-start gap-3 overflow-visible md:grid-cols-4 xl:grid-cols-5">
                @foreach ($numberGroups as $groupIndex => $group)
                    @php
                        $start = $groupIndex * 10;
                        $end = $start + 9;
                        $groupStatuses = collect($group)->pluck('status');
                        $groupTone = match (true) {
                            $groupStatuses->contains('blocked') => 'blocked',
                            $groupStatuses->contains('over_limit') => 'over_limit',
                            $groupStatuses->contains('full') => 'full',
                            $groupStatuses->contains('warning') => 'warning',
                            $groupStatuses->contains('restricted') => 'restricted',
                            $groupStatuses->contains('hot') => 'hot',
                            $groupStatuses->contains('manual_review') => 'manual_review',
                            default => 'available',
                        };
                    @endphp

                    <div
                        x-data="{ collapsed: false }"
                        x-show="groupHasMatch(@js($group->pluck('number')->values()))"
                        class="relative z-0 self-start overflow-visible rounded-3xl border border-slate-200/80 bg-white shadow-sm transition hover:z-50 focus-within:z-50"
                    >
                        <button
                            type="button"
                            class="group relative flex w-full items-center justify-between gap-4 px-3 py-3 text-left transition hover:bg-slate-50 sm:px-4"
                            @click="collapsed = !collapsed"
                            :aria-expanded="(!collapsed).toString()"
                        >
                            <div class="flex items-center gap-3">
                                <div class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-100 text-slate-900">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $groupTone === 'available' ? 'bg-brand-success' : ($groupTone === 'warning' ? 'bg-brand-warning' : ($groupTone === 'full' ? 'bg-brand-info' : 'bg-brand-danger')) }}"></span>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-brand-navy">{{ sprintf('%02d-%02d', $start, $end) }}</div>
                                    <div class="text-xs text-slate-500">{{ __('Group :number', ['number' => $groupIndex + 1]) }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="hidden rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-500 sm:inline-flex">
                                    10 cards
                                </span>
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    class="h-5 w-5 text-slate-500 transition-transform duration-200"
                                    :class="{ 'rotate-180': collapsed }"
                                    aria-hidden="true"
                                >
                                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </button>

                        <div x-show="!collapsed" x-transition.opacity.duration.150ms class="px-3 pb-3 sm:px-4 sm:pb-4">
                            <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">
                                @foreach ($group as $row)
                                    @php
                                        $hasLimit = $row['max_amount'] !== null;
                                        $statusMeta = match ($row['status']) {
                                            'blocked' => ['label' => __('BLOCKED'), 'classes' => 'border-red-200 bg-red-100 text-red-800', 'dot' => 'bg-red-500', 'tone' => 'blocked'],
                                            'over_limit' => ['label' => __('BLOCKED'), 'classes' => 'border-red-200 bg-red-100 text-red-800', 'dot' => 'bg-red-500', 'tone' => 'blocked'],
                                            'full' => ['label' => __('FULL'), 'classes' => 'border-red-200 bg-red-100 text-red-800', 'dot' => 'bg-red-500', 'tone' => 'blocked'],
                                            'warning' => ['label' => __('WARN'), 'classes' => 'border-amber-200 bg-amber-100 text-amber-800', 'dot' => 'bg-amber-500', 'tone' => 'warning'],
                                            'hot' => ['label' => __('HOT'), 'classes' => 'border-orange-200 bg-orange-100 text-orange-800', 'dot' => 'bg-orange-500', 'tone' => 'warning'],
                                            'restricted' => ['label' => __('RESTRICT'), 'classes' => 'border-purple-200 bg-purple-100 text-purple-800', 'dot' => 'bg-purple-500', 'tone' => 'restricted'],
                                            'manual_review' => ['label' => __('REVIEW'), 'classes' => 'border-purple-200 bg-purple-100 text-purple-800', 'dot' => 'bg-purple-500', 'tone' => 'restricted'],
                                            default => $hasLimit ? ['label' => __('OK'), 'classes' => 'border-emerald-200 bg-emerald-100 text-emerald-800', 'dot' => 'bg-emerald-500', 'tone' => 'available'] : ['label' => __('NO LIMIT'), 'classes' => 'border-slate-200 bg-slate-100 text-slate-700', 'dot' => 'bg-slate-400', 'tone' => 'no_limit'],
                                        };
                                        $tooltipStatus = __(match ($row['status']) {
                                            'blocked', 'over_limit' => 'Blocked or over the limit',
                                            'full' => 'At the limit',
                                            'warning' => '80% to 99% used',
                                            'hot' => 'Hot number',
                                            'restricted' => 'Restricted',
                                            'manual_review' => 'Manual review required',
                                            default => $hasLimit ? 'Within limit' : 'No configured limit',
                                        });
                                        $tooltipRows = [
                                            ['label' => __('Confirmed'), 'value' => '&#8353;' . number_format($row['confirmed_amount'], 2, '.', ',')],
                                            ['label' => __('Pending'), 'value' => '&#8353;' . number_format($row['pending_amount'], 2, '.', ',')],
                                            ['label' => __('Needs review'), 'value' => '&#8353;' . number_format($row['needs_review_amount'], 2, '.', ',')],
                                            ['label' => __('Rejected'), 'value' => '&#8353;' . number_format($row['rejected_amount'], 2, '.', ',')],
                                        ];
                                        if ($hasLimit) {
                                            $tooltipRows[] = ['label' => __('Max limit'), 'value' => '&#8353;' . number_format((float) $row['max_amount'], 2, '.', ',')];
                                            $tooltipRows[] = ['label' => __('Available'), 'value' => '&#8353;' . number_format(max((float) $row['available_amount'], 0), 2, '.', ',')];
                                        } else {
                                            $tooltipRows[] = ['label' => __('Max limit'), 'value' => __('No limit')];
                                        }
                                        $tooltipRows[] = ['label' => __('% used'), 'value' => $row['percentage_used'] !== null ? number_format($row['percentage_used'], 1) . '%' : '-'];
                                    @endphp

                                    <button
                                        type="button"
                                        class="group/number-card relative cursor-pointer rounded-2xl border px-2.5 py-2 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-brand-primary/10"
                                        :class="selectedNumber === @js($row['number']) ? '{{ $statusMeta['classes'] }} ring-2 ring-brand-primary/10' : '{{ $statusMeta['classes'] }}'"
                                        @click="openNumber(@js($row['number']))"
                                        x-show="matchesNumber(@js($row['number']))"
                                    >
                                        <div class="flex min-h-[4.8rem] flex-col justify-between gap-1.5 text-center">
                                            <div>
                                                <div class="text-[1.55rem] font-semibold leading-none tracking-tight text-brand-navy">{{ $row['number'] }}</div>
                                                <div class="mt-1 text-[0.8125rem] font-medium leading-tight text-slate-700">&#8353;{{ number_format($row['active_amount'], 0, '.', ',') }}</div>
                                            </div>

                                            <span class="inline-flex w-full items-center justify-center rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $statusMeta['classes'] }}">
                                                {{ $statusMeta['label'] }}
                                            </span>
                                        </div>

                                        <div class="pointer-events-none absolute bottom-full left-1/2 z-[999] hidden w-72 -translate-x-1/2 -translate-y-2 rounded-2xl border border-slate-200 bg-white p-3 text-left shadow-[0_18px_40px_-24px_rgba(8,31,77,0.55)] opacity-0 transition duration-150 md:block md:group-hover/number-card:opacity-100 md:group-hover/number-card:translate-y-0 md:group-focus-visible/number-card:opacity-100 md:group-focus-visible/number-card:translate-y-0">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <div class="text-sm font-semibold text-brand-navy">{{ $row['number'] }}</div>
                                                    <div class="text-xs text-slate-500">{{ $tooltipStatus }}</div>
                                                </div>
                                                <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $statusMeta['classes'] }}">
                                                    {{ $statusMeta['label'] }}
                                                </span>
                                            </div>

                                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                                @foreach ($tooltipRows as $tooltipRow)
                                                    <div class="rounded-xl bg-slate-50 px-2.5 py-2">
                                                        <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">{{ $tooltipRow['label'] }}</div>
                                                        <div class="mt-1 font-semibold text-slate-800">{!! $tooltipRow['value'] !!}</div>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div class="mt-3 flex flex-wrap gap-1.5">
                                                @if ($row['is_blocked'])
                                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-800">{{ __('Blocked') }}</span>
                                                @endif
                                                @if ($row['is_restricted'])
                                                    <span class="rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-purple-800">{{ __('Restricted') }}</span>
                                                @endif
                                                @if ($row['restriction_type'] === \App\Models\NumberLimit::RESTRICTION_TYPE_HOT)
                                                    <span class="rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-orange-800">{{ __('Hot') }}</span>
                                                @endif
                                                @if ($row['requires_manual_review'])
                                                    <span class="rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-purple-800">{{ __('Review') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                <div
                    x-show="searchTerm.trim() !== '' && !hasAnyMatch()"
                    x-cloak
                    class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 shadow-sm md:col-span-4 xl:col-span-5"
                >
                    {{ __('No numbers match the current search.') }}
                </div>
            </div>

            <div x-show="view === 'table'" x-cloak class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                    <h3 class="text-base font-semibold text-brand-navy">{{ __('Detailed table view') }}</h3>
                    <p class="text-sm text-slate-600">{{ __('The same board data, shown in a compact operational table.') }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[1220px] divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Number') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Confirmed') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Pending') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Needs review') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Rejected') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Active total') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Max limit') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Available') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">% used</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($numbers as $row)
                                @php
                                    $hasLimit = $row['max_amount'] !== null;
                                    $tableRowClasses = match ($row['status']) {
                                        'blocked' => 'bg-red-50/60',
                                        'over_limit' => 'bg-red-50/60',
                                        'full' => 'bg-blue-50/60',
                                        'warning' => 'bg-amber-50/60',
                                        'restricted' => 'bg-amber-50/60',
                                        'hot' => 'bg-yellow-50/60',
                                        'manual_review' => 'bg-purple-50/60',
                                        default => '',
                                    };
                                    $statusClasses = match ($row['status']) {
                                        'blocked' => 'border-red-200 bg-red-100 text-red-700',
                                        'over_limit' => 'border-red-200 bg-red-100 text-red-700',
                                        'full' => 'border-blue-200 bg-blue-100 text-blue-700',
                                        'warning' => 'border-amber-200 bg-amber-100 text-amber-700',
                                        'restricted' => 'border-amber-200 bg-amber-100 text-amber-800',
                                        'hot' => 'border-yellow-200 bg-yellow-100 text-yellow-800',
                                        'manual_review' => 'border-purple-200 bg-purple-100 text-purple-800',
                                        default => $hasLimit ? 'border-brand-success/20 bg-green-100 text-green-700' : 'border-slate-200 bg-slate-200 text-slate-600',
                                    };
                                    $statusLabel = $hasLimit ? __(str_replace('_', ' ', $row['status'])) : __('no limit');
                                @endphp
                                <tr class="{{ $tableRowClasses }}" x-show="matchesNumber(@js($row['number']))">
                                    <td class="px-4 py-3 text-sm font-semibold text-brand-navy">{{ $row['number'] }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">&#8353;{{ number_format($row['confirmed_amount'], 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">&#8353;{{ number_format($row['pending_amount'], 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">&#8353;{{ number_format($row['needs_review_amount'], 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">&#8353;{{ number_format($row['rejected_amount'], 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-slate-800">&#8353;{{ number_format($row['active_amount'], 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $row['max_amount'] !== null ? '₡' . number_format((float) $row['max_amount'], 2, '.', ',') : __('No limit') }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $row['available_amount'] !== null ? '₡' . number_format((float) $row['available_amount'], 2, '.', ',') : '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $row['percentage_used'] !== null ? number_format($row['percentage_used'], 1) . '%' : '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($canCreateManualRequests && $selectedBranch && $selectedDraw)
                                            <button
                                                type="button"
                                                class="brand-btn-secondary px-3 py-1.5 text-xs"
                                                @click="openNumber(@js($row['number']))"
                                            >
                                                {{ __('Manual request') }}
                                            </button>
                                        @else
                                            <span class="text-sm text-slate-400">{{ __('View only') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                x-show="view === 'table' && searchTerm.trim() !== '' && !hasAnyMatch()"
                x-cloak
                class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 shadow-sm"
            >
                {{ __('No numbers match the current search.') }}
            </div>
        </section>

        @if ($canCreateManualRequests && $selectedBranch && $selectedDraw)
            <div
                x-show="modalOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-8 backdrop-blur-sm"
                @keydown.escape.window="modalOpen = false"
            >
                <div class="w-full max-w-2xl rounded-3xl border border-slate-200/80 bg-white shadow-[0_30px_80px_-36px_rgba(8,31,77,0.6)]">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-6">
                        <div>
                            <div class="brand-badge bg-brand-primary/10 text-brand-primary">{{ __('Manual request') }}</div>
                            <h2 class="mt-2 text-lg font-semibold text-brand-navy">{{ __('Create manual request') }}</h2>
                            <p class="mt-1 text-sm text-slate-600" x-text="selectedLabel"></p>
                        </div>
                        <button type="button" class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-900" @click="modalOpen = false" aria-label="Close modal">
                            <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('numbers.store') }}" class="space-y-5 px-5 py-5 sm:px-6">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $selectedBranch?->id }}">
                        <input type="hidden" name="draw_id" value="{{ $selectedDraw?->id }}">
                        <input type="hidden" name="number" x-bind:value="selectedNumber">

                        <div x-show="selectedRow" x-cloak class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-medium text-slate-500">{{ __('Selected number') }}</div>
                                    <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy" x-text="selectedNumber"></div>
                                    <div class="mt-1 text-sm text-slate-600" x-text="selectedRow ? (selectedRow.max_amount !== null ? @js(__('Limited number')) : @js(__('No configured limit'))) : ''"></div>
                                </div>
                                <span
                                    class="rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
                                    :class="statusClassesFor(selectedRow)"
                                    x-text="statusLabelFor(selectedRow)"
                                ></span>
                            </div>

                            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="rounded-xl bg-white px-3 py-2">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">{{ __('Confirmed') }}</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-800" x-text="selectedRow ? formatAmount(selectedRow.confirmed_amount) : '-'"></div>
                                </div>
                                <div class="rounded-xl bg-white px-3 py-2">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">{{ __('Pending') }}</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-800" x-text="selectedRow ? formatAmount(selectedRow.pending_amount) : '-'"></div>
                                </div>
                                <div class="rounded-xl bg-white px-3 py-2">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">{{ __('Needs review') }}</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-800" x-text="selectedRow ? formatAmount(selectedRow.needs_review_amount) : '-'"></div>
                                </div>
                                <div class="rounded-xl bg-white px-3 py-2">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">{{ __('Rejected') }}</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-800" x-text="selectedRow ? formatAmount(selectedRow.rejected_amount) : '-'"></div>
                                </div>
                                <div class="rounded-xl bg-white px-3 py-2">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">{{ __('Max limit') }}</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-800" x-text="selectedRow ? (selectedRow.max_amount !== null ? formatAmount(selectedRow.max_amount) : @js(__('No limit'))) : '-'"></div>
                                </div>
                                <div class="rounded-xl bg-white px-3 py-2">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-slate-500">{{ __('Available') }}</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-800" x-text="selectedRow ? (selectedRow.available_amount !== null ? formatAmount(Math.max(selectedRow.available_amount, 0)) : '-') : '-'"></div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">{{ __('Selected branch') }}</label>
                                <input type="text" readonly value="{{ $selectedBranch?->name ?? '-' }}" class="brand-input mt-1 block w-full rounded-xl bg-slate-50 text-sm text-slate-700">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">{{ __('Selected draw') }}</label>
                                <input type="text" readonly value="{{ $selectedDraw?->name ?? '-' }}" class="brand-input mt-1 block w-full rounded-xl bg-slate-50 text-sm text-slate-700">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">{{ __('Selected number') }}</label>
                                <input type="text" readonly x-bind:value="selectedNumber" class="brand-input mt-1 block w-full rounded-xl bg-slate-50 text-sm text-slate-700">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="amount">{{ __('Amount') }}</label>
                            <input id="amount" name="amount" type="number" step="0.01" min="0" required value="{{ old('amount') }}" class="brand-input mt-1 block w-full rounded-xl" placeholder="1000">
                            @error('amount')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700" for="customer_name">{{ __('Customer name') }}</label>
                                <input id="customer_name" name="customer_name" type="text" value="{{ old('customer_name') }}" class="brand-input mt-1 block w-full rounded-xl" placeholder="{{ __('Optional') }}">
                                @error('customer_name')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700" for="customer_phone">{{ __('Customer phone') }}</label>
                                <input id="customer_phone" name="customer_phone" type="text" value="{{ old('customer_phone') }}" class="brand-input mt-1 block w-full rounded-xl" placeholder="+50255510001">
                                @error('customer_phone')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700" for="notes">{{ __('Notes') }}</label>
                                <textarea id="notes" name="notes" rows="4" class="brand-input mt-1 block w-full rounded-xl" placeholder="{{ __('Optional') }}">{{ old('notes') }}</textarea>
                                @error('notes')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                            <button type="button" class="brand-btn-secondary" @click="modalOpen = false">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="brand-btn-primary">
                                {{ __('Save request') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
