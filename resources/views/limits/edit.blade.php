<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Edit limit</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Update the branch, draw, number, or max amount.
                </p>
            </div>
            <a href="{{ route('limits.index') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                Back to limits
            </a>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('limits.update', $limit) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="branch_id" class="block text-sm font-medium text-slate-700">Branch</label>
                        <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id', $limit->branch_id) == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="draw_id" class="block text-sm font-medium text-slate-700">Draw</label>
                        <select id="draw_id" name="draw_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($draws as $draw)
                                <option value="{{ $draw->id }}" @selected(old('draw_id', $limit->draw_id) == $draw->id)>{{ $draw->name }}</option>
                            @endforeach
                        </select>
                        @error('draw_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="number" class="block text-sm font-medium text-slate-700">Number</label>
                        <select id="number" name="number" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($numbers as $number)
                                <option value="{{ $number }}" @selected(old('number', $limit->number) === $number)>{{ $number }}</option>
                            @endforeach
                        </select>
                        @error('number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="max_amount" class="block text-sm font-medium text-slate-700">Max amount</label>
                        <input id="max_amount" name="max_amount" type="number" step="0.01" min="0.01" value="{{ old('max_amount', $limit->max_amount) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('max_amount')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-4">
                        <div class="text-sm font-semibold text-slate-900">Restriction settings</div>

                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="hidden" name="is_restricted" value="0">
                            <input type="checkbox" name="is_restricted" value="1" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500" @checked(old('is_restricted', $limit->is_restricted))>
                            <span>
                                Mark as restricted
                                <span class="block text-xs text-slate-500">Shows a visual warning on the board and limits pages.</span>
                            </span>
                        </label>

                        <div>
                            <label for="restriction_type" class="block text-sm font-medium text-slate-700">Restriction type</label>
                            <select id="restriction_type" name="restriction_type" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                <option value="">Normal</option>
                                <option value="normal" @selected(old('restriction_type', $limit->restriction_type) === 'normal')>Normal</option>
                                <option value="restricted" @selected(old('restriction_type', $limit->restriction_type) === 'restricted')>Restricted</option>
                                <option value="hot" @selected(old('restriction_type', $limit->restriction_type) === 'hot')>Hot</option>
                                <option value="blocked" @selected(old('restriction_type', $limit->restriction_type) === 'blocked')>Blocked</option>
                            </select>
                            @error('restriction_type')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="restriction_reason" class="block text-sm font-medium text-slate-700">Restriction reason</label>
                            <textarea id="restriction_reason" name="restriction_reason" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Optional">{{ old('restriction_reason', $limit->restriction_reason) }}</textarea>
                            @error('restriction_reason')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-start gap-3 text-sm text-slate-700">
                                <input type="hidden" name="requires_manual_review" value="0">
                                <input type="checkbox" name="requires_manual_review" value="1" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500" @checked(old('requires_manual_review', $limit->requires_manual_review))>
                                <span>Requires manual review</span>
                            </label>

                            <label class="flex items-start gap-3 text-sm text-slate-700">
                                <input type="hidden" name="is_blocked" value="0">
                                <input type="checkbox" name="is_blocked" value="1" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500" @checked(old('is_blocked', $limit->is_blocked))>
                                <span>Blocked</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Update limit
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-rose-900">Delete limit</h2>
                    <p class="mt-1 text-sm text-rose-800">This removes the limit for this branch, draw, and number.</p>
                </div>
                <form method="POST" action="{{ route('limits.delete', $limit) }}" onsubmit="return confirm('Delete this limit?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
