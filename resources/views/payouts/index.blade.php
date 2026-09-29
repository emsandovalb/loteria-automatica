<x-app-layout>
    <div class="space-y-6">
        <div>
            <div class="brand-badge bg-brand-primary/10 text-brand-primary">{{ __('Prize payments') }}</div>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">{{ __('Prizes') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Winners of the day. Each branch pays the bets it sold and marks them as paid.') }}</p>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-brand-success/20 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">{{ session('status') }}</div>
        @endif

        <form method="GET" action="{{ route('payouts.index') }}" class="grid gap-3 rounded-3xl border border-slate-200/80 bg-white p-4 shadow-sm md:grid-cols-4">
            <div>
                <label for="draw_date" class="block text-sm font-medium text-slate-700">{{ __('Draw date') }}</label>
                <input id="draw_date" name="draw_date" type="date" value="{{ $drawDate }}" class="brand-input mt-1 block w-full rounded-xl text-sm">
            </div>
            <div>
                <label for="branch_id" class="block text-sm font-medium text-slate-700">{{ __('Branch') }}</label>
                <select id="branch_id" name="branch_id" class="brand-input mt-1 block w-full rounded-xl text-sm">
                    <option value="">{{ __('All branches') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) ($filters['branch_id'] ?? 0) === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-slate-700">{{ __('Status') }}</label>
                <select id="status" name="status" class="brand-input mt-1 block w-full rounded-xl text-sm">
                    <option value="">{{ __('All') }}</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>{{ __('Unpaid') }}</option>
                    <option value="paid" @selected(($filters['status'] ?? '') === 'paid')>{{ __('Paid') }}</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="brand-btn-primary w-full">{{ __('Apply') }}</button>
            </div>
        </form>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-4">
                <div class="text-sm text-amber-800">{{ __('To pay') }}</div>
                <div class="text-2xl font-semibold text-amber-900">₡{{ number_format($totals['pending'], 2, '.', ',') }}</div>
            </div>
            <div class="rounded-3xl border border-green-200 bg-green-50 p-4">
                <div class="text-sm text-green-800">{{ __('Paid') }}</div>
                <div class="text-2xl font-semibold text-green-900">₡{{ number_format($totals['paid'], 2, '.', ',') }}</div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach (['Draw', 'Number', 'Customer', 'Branch', 'Bet', 'Prize', 'Status', 'Action'] as $heading)
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __($heading) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($payouts as $payout)
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $payout->drawResult?->draw?->name }}</td>
                            <td class="px-4 py-3 text-lg font-semibold text-brand-navy">{{ $payout->number }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">
                                {{ $payout->customer?->name ?? '-' }}
                                <div class="text-xs text-slate-500">{{ $payout->customer?->phone }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $payout->branch?->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">
                                ₡{{ number_format((float) $payout->bet_amount, 2, '.', ',') }}
                                @if ((float) $payout->reventado_amount > 0)
                                    <div class="text-xs text-slate-500">{{ __('Reventado') }} ₡{{ number_format((float) $payout->reventado_amount, 2, '.', ',') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold text-brand-navy">
                                ₡{{ number_format((float) $payout->total_prize, 2, '.', ',') }}
                                @if ((float) $payout->reventado_prize > 0)
                                    <div class="text-xs font-normal text-slate-500">{{ __('includes reventado ₡:amount', ['amount' => number_format((float) $payout->reventado_prize, 2, '.', ',')]) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if ($payout->isPaid())
                                    <span class="brand-badge bg-green-100 text-green-800">{{ __('Paid') }}</span>
                                    <div class="mt-1 text-xs text-slate-500">{{ $payout->paidByUser?->name }} · {{ $payout->paid_at?->format('Y-m-d H:i') }}</div>
                                @else
                                    <span class="brand-badge bg-amber-100 text-amber-800">{{ __('Unpaid') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @can('pay', $payout)
                                    <form method="POST" action="{{ route('payouts.pay', $payout) }}" onsubmit="return confirm(@js(__('Confirm that the prize was paid to the customer?')))">
                                        @csrf
                                        <button type="submit" class="brand-btn-primary px-3 py-1.5 text-xs">{{ __('Mark as paid') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">{{ __('No winners for this date.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
