<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Create limit</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Set a single number limit or apply the same value across all numbers.
                </p>
            </div>
            <a href="{{ route('limits.index') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                Back to limits
            </a>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Single limit</h2>
                <p class="mt-1 text-sm text-slate-600">Create one limit for a specific branch, draw, and number.</p>

                <form method="POST" action="{{ route('limits.store') }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="mode" value="single">

                    <div>
                        <label for="branch_id" class="block text-sm font-medium text-slate-700">Branch</label>
                        <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $selectedBranchId) === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="draw_id" class="block text-sm font-medium text-slate-700">Draw</label>
                        <select id="draw_id" name="draw_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($draws as $draw)
                                <option value="{{ $draw->id }}" @selected((string) old('draw_id', $selectedDrawId) === (string) $draw->id)>{{ $draw->name }}</option>
                            @endforeach
                        </select>
                        @error('draw_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="number" class="block text-sm font-medium text-slate-700">Number</label>
                        <select id="number" name="number" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($numbers as $number)
                                <option value="{{ $number }}" @selected(old('number') === $number)>{{ $number }}</option>
                            @endforeach
                        </select>
                        @error('number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="max_amount" class="block text-sm font-medium text-slate-700">Max amount</label>
                        <input id="max_amount" name="max_amount" type="number" step="0.01" min="0.01" value="{{ old('max_amount') }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="1000">
                        @error('max_amount')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-4">
                        <div class="text-sm font-semibold text-slate-900">Restriction settings</div>

                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="hidden" name="is_restricted" value="0">
                            <input type="checkbox" name="is_restricted" value="1" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500" @checked(old('is_restricted'))>
                            <span>
                                Mark as restricted
                                <span class="block text-xs text-slate-500">Shows a visual warning on the board and limits pages.</span>
                            </span>
                        </label>

                        <div>
                            <label for="restriction_type" class="block text-sm font-medium text-slate-700">Restriction type</label>
                            <select id="restriction_type" name="restriction_type" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                <option value="">Normal</option>
                                <option value="normal" @selected(old('restriction_type') === 'normal')>Normal</option>
                                <option value="restricted" @selected(old('restriction_type') === 'restricted')>Restricted</option>
                                <option value="hot" @selected(old('restriction_type') === 'hot')>Hot</option>
                                <option value="blocked" @selected(old('restriction_type') === 'blocked')>Blocked</option>
                            </select>
                            @error('restriction_type')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="restriction_reason" class="block text-sm font-medium text-slate-700">Restriction reason</label>
                            <textarea id="restriction_reason" name="restriction_reason" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Optional">{{ old('restriction_reason') }}</textarea>
                            @error('restriction_reason')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-start gap-3 text-sm text-slate-700">
                                <input type="hidden" name="requires_manual_review" value="0">
                                <input type="checkbox" name="requires_manual_review" value="1" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500" @checked(old('requires_manual_review'))>
                                <span>Requires manual review</span>
                            </label>

                            <label class="flex items-start gap-3 text-sm text-slate-700">
                                <input type="hidden" name="is_blocked" value="0">
                                <input type="checkbox" name="is_blocked" value="1" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500" @checked(old('is_blocked'))>
                                <span>Blocked</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Create limit
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Bulk editor</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Apply the same max amount to 00-99 or only fill in missing limits.
                </p>

                <form method="POST" action="{{ route('limits.store') }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="mode" value="bulk">

                    <div>
                        <label for="bulk_branch_id" class="block text-sm font-medium text-slate-700">Branch</label>
                        <select id="bulk_branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $selectedBranchId) === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="bulk_draw_id" class="block text-sm font-medium text-slate-700">Draw</label>
                        <select id="bulk_draw_id" name="draw_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($draws as $draw)
                                <option value="{{ $draw->id }}" @selected((string) old('draw_id', $selectedDrawId) === (string) $draw->id)>{{ $draw->name }}</option>
                            @endforeach
                        </select>
                        @error('draw_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="bulk_max_amount" class="block text-sm font-medium text-slate-700">Max amount</label>
                        <input id="bulk_max_amount" name="max_amount" type="number" step="0.01" min="0.01" value="{{ old('max_amount') }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="1000">
                        @error('max_amount')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="apply_to" class="block text-sm font-medium text-slate-700">Apply to</label>
                        <select id="apply_to" name="apply_to" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="all" @selected(old('apply_to', 'all') === 'all')>All numbers 00-99</option>
                            <option value="missing" @selected(old('apply_to') === 'missing')>Only numbers without limit</option>
                        </select>
                        @error('apply_to')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        Bulk create will fill every number in the selected scope. The "missing" option keeps existing limits untouched.
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-4">
                        <div class="text-sm font-semibold text-slate-900">Optional restriction fields</div>
                        <p class="text-xs text-slate-500">Leave these empty to keep the current restriction settings on existing rows.</p>

                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="checkbox" name="bulk_is_restricted" value="1" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500" @checked(old('bulk_is_restricted'))>
                            <span>Mark generated limits as restricted</span>
                        </label>

                        <div>
                            <label for="bulk_restriction_type" class="block text-sm font-medium text-slate-700">Restriction type</label>
                            <select id="bulk_restriction_type" name="bulk_restriction_type" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                <option value="">Leave unchanged</option>
                                <option value="normal" @selected(old('bulk_restriction_type') === 'normal')>Normal</option>
                                <option value="restricted" @selected(old('bulk_restriction_type') === 'restricted')>Restricted</option>
                                <option value="hot" @selected(old('bulk_restriction_type') === 'hot')>Hot</option>
                                <option value="blocked" @selected(old('bulk_restriction_type') === 'blocked')>Blocked</option>
                            </select>
                            @error('bulk_restriction_type')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="bulk_restriction_reason" class="block text-sm font-medium text-slate-700">Restriction reason</label>
                            <textarea id="bulk_restriction_reason" name="bulk_restriction_reason" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Optional">{{ old('bulk_restriction_reason') }}</textarea>
                            @error('bulk_restriction_reason')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-start gap-3 text-sm text-slate-700">
                                <input type="checkbox" name="bulk_requires_manual_review" value="1" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500" @checked(old('bulk_requires_manual_review'))>
                                <span>Requires manual review</span>
                            </label>

                            <label class="flex items-start gap-3 text-sm text-slate-700">
                                <input type="checkbox" name="bulk_is_blocked" value="1" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500" @checked(old('bulk_is_blocked'))>
                                <span>Blocked</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Apply bulk limit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
