<x-app-layout>
    @php($money = fn (float $amount) => '₡' . number_format($amount, 2, '.', ','))
    <div class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">{{ __('Daily settlement') }}</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">{{ __('Settlement') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('Confirmed sales minus prizes, per branch and draw.') }}</p>
            </div>
            <form method="GET" action="{{ route('settlement.index') }}" class="flex items-end gap-2">
                <div>
                    <label for="draw_date" class="block text-sm font-medium text-slate-700">{{ __('Draw date') }}</label>
                    <input id="draw_date" name="draw_date" type="date" value="{{ $drawDate }}" class="brand-input mt-1 block rounded-xl text-sm">
                </div>
                <button type="submit" class="brand-btn-secondary">{{ __('Apply') }}</button>
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-3xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <div class="text-sm text-slate-500">{{ __('Sales') }}</div>
                <div class="text-2xl font-semibold text-brand-navy">{{ $money($totals['sales']) }}</div>
            </div>
            <div class="rounded-3xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <div class="text-sm text-slate-500">{{ __('Prizes') }}</div>
                <div class="text-2xl font-semibold text-brand-navy">{{ $money($totals['prizes']) }}</div>
            </div>
            <div class="rounded-3xl border p-4 shadow-sm {{ $totals['net'] >= 0 ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }}">
                <div class="text-sm {{ $totals['net'] >= 0 ? 'text-green-800' : 'text-red-800' }}">{{ __('Net') }}</div>
                <div class="text-2xl font-semibold {{ $totals['net'] >= 0 ? 'text-green-900' : 'text-red-900' }}">{{ $money($totals['net']) }}</div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach (['Branch', 'Draw', 'Winning number', 'Sales', 'Prizes', 'Net'] as $heading)
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __($heading) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach ($rows as $row)
                        @foreach ($row['draws'] as $line)
                            <tr>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $row['branch']->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $line['draw']->name }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-brand-navy">
                                    {{ $line['result']?->winning_number ?? __('Pending') }}
                                    @if ($line['result']?->reventado_hit)
                                        <span class="brand-badge bg-red-100 text-red-800">{{ __('Reventado') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $money($line['sales']) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $money($line['prizes']) }}</td>
                                <td class="px-4 py-3 text-sm font-semibold {{ $line['net'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ $money($line['net']) }}</td>
                            </tr>
                        @endforeach
                        @if ($row['draws'] !== [])
                            <tr class="bg-slate-50">
                                <td class="px-4 py-2 text-sm font-semibold text-slate-900" colspan="3">{{ __('Total :branch', ['branch' => $row['branch']->name]) }}</td>
                                <td class="px-4 py-2 text-sm font-semibold text-slate-900">{{ $money($row['totals']['sales']) }}</td>
                                <td class="px-4 py-2 text-sm font-semibold text-slate-900">{{ $money($row['totals']['prizes']) }}</td>
                                <td class="px-4 py-2 text-sm font-semibold {{ $row['totals']['net'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ $money($row['totals']['net']) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    @if (collect($rows)->every(fn ($row) => $row['draws'] === []))
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">{{ __('No confirmed sales for this date.') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
