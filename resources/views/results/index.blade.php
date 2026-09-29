<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">{{ __('Draw results') }}</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">{{ __('Results') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('Enter the winning number of each draw. Winners are calculated from confirmed requests only.') }}</p>
            </div>
            <form method="GET" action="{{ route('results.index') }}" class="flex items-end gap-2">
                <div>
                    <label for="draw_date" class="block text-sm font-medium text-slate-700">{{ __('Draw date') }}</label>
                    <input id="draw_date" name="draw_date" type="date" value="{{ $drawDate }}" class="brand-input mt-1 block rounded-xl text-sm">
                </div>
                <button type="submit" class="brand-btn-secondary">{{ __('Apply') }}</button>
            </form>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-brand-success/20 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            @forelse ($draws as $draw)
                @php($result = $results->get($draw->id))
                <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-lg font-semibold text-brand-navy">{{ $draw->name }}</div>
                            <div class="text-xs text-slate-500">
                                {{ __('Prize ×:multiplier', ['multiplier' => rtrim(rtrim((string) $draw->prize_multiplier, '0'), '.')]) }}
                                @if ($draw->offersReventado())
                                    · {{ __('Reventado ×:multiplier', ['multiplier' => rtrim(rtrim((string) $draw->reventado_multiplier, '0'), '.')]) }}
                                @endif
                            </div>
                        </div>
                        @if ($result)
                            <div class="text-right">
                                <div class="text-3xl font-bold tracking-tight text-brand-navy">{{ $result->winning_number }}</div>
                                @if ($result->reventado_hit)
                                    <span class="brand-badge bg-red-100 text-red-800">{{ __('Reventado') }}</span>
                                @endif
                            </div>
                        @else
                            <span class="brand-badge bg-slate-100 text-slate-700">{{ __('No result yet') }}</span>
                        @endif
                    </div>

                    @if ($result)
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-2xl bg-slate-50 p-3">
                                <div class="text-slate-500">{{ __('Winners') }}</div>
                                <div class="text-lg font-semibold text-brand-navy">{{ $result->payouts_count }}</div>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-3">
                                <div class="text-slate-500">{{ __('Total prizes') }}</div>
                                <div class="text-lg font-semibold text-brand-navy">₡{{ number_format((float) $result->payouts_sum_total_prize, 2, '.', ',') }}</div>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-slate-500">
                            {{ __('Entered by :name', ['name' => $result->enteredByUser?->name ?? __('System')]) }} · {{ $result->updated_at?->format('Y-m-d H:i') }}
                            @if ($result->winners_notified_at)
                                · {{ __('Winners notified :date', ['date' => $result->winners_notified_at->format('Y-m-d H:i')]) }}
                            @endif
                        </div>
                    @endif

                    @can('create', \App\Models\DrawResult::class)
                        @if (! $result || ! $result->isLocked())
                            <form method="POST" action="{{ route('results.store') }}" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                                @csrf
                                <input type="hidden" name="draw_id" value="{{ $draw->id }}">
                                <input type="hidden" name="draw_date" value="{{ $drawDate }}">
                                <div class="flex flex-wrap items-end gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700" for="winning_number_{{ $draw->id }}">{{ __('Winning number') }}</label>
                                        <input id="winning_number_{{ $draw->id }}" name="winning_number" inputmode="numeric" maxlength="2" pattern="[0-9]{2}" required placeholder="00" value="{{ $result?->winning_number }}" class="brand-input mt-1 block w-24 rounded-xl text-center text-lg font-semibold">
                                    </div>
                                    @if ($draw->offersReventado())
                                        <label class="flex items-center gap-2 pb-2 text-sm text-slate-700">
                                            <input type="hidden" name="reventado_hit" value="0">
                                            <input type="checkbox" name="reventado_hit" value="1" @checked($result?->reventado_hit) class="rounded border-slate-300">
                                            {{ __('Reventado ball came out') }}
                                        </label>
                                    @endif
                                    <button type="submit" class="brand-btn-primary">{{ $result ? __('Correct result') : __('Save result') }}</button>
                                </div>
                            </form>
                        @endif

                        @if ($result && ! $result->winners_notified_at && $result->payouts_count > 0)
                            <form method="POST" action="{{ route('results.notify', $result) }}" class="mt-3" onsubmit="return confirm(@js(__('After notifying, the result can no longer be corrected. Continue?')))">
                                @csrf
                                <button type="submit" class="brand-btn-secondary w-full">{{ __('Notify winners') }}</button>
                            </form>
                        @endif
                    @endcan

                    @if ($result && $result->payouts_count > 0)
                        <a href="{{ route('payouts.index', ['draw_date' => $drawDate]) }}" class="mt-3 block text-sm font-medium text-brand-primary hover:underline">{{ __('See winners') }} →</a>
                    @endif
                </div>
            @empty
                <div class="rounded-3xl border border-slate-200/80 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">{{ __('No draws available for this account.') }}</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
