<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">{{ __('Draw settings') }}</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">{{ __('Edit draw') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('Update closing settings for this draw.') }}</p>
            </div>
            <a href="{{ route('draws.index') }}" class="brand-btn-secondary">{{ __('Back to draws') }}</a>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('draws.update', $draw) }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="name">{{ __('Name') }}</label>
                            <input id="name" name="name" value="{{ old('name', $draw->name) }}" class="brand-input mt-1 block w-full rounded-xl">
                            @error('name')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="status">{{ __('Status') }}</label>
                            <select id="status" name="status" class="brand-input mt-1 block w-full rounded-xl">
                                <option value="active" @selected(old('status', $draw->status) === \App\Models\Draw::STATUS_ACTIVE)>{{ __('Active') }}</option>
                                <option value="inactive" @selected(old('status', $draw->status) === \App\Models\Draw::STATUS_INACTIVE)>{{ __('Inactive') }}</option>
                            </select>
                            @error('status')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="draw_time">{{ __('Draw time') }}</label>
                            <input id="draw_time" name="draw_time" type="time" step="1" value="{{ old('draw_time', $draw->draw_time) }}" class="brand-input mt-1 block w-full rounded-xl">
                            @error('draw_time')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="close_time">{{ __('Close time') }}</label>
                            <input id="close_time" name="close_time" type="time" step="1" value="{{ old('close_time', $draw->close_time) }}" class="brand-input mt-1 block w-full rounded-xl">
                            @error('close_time')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="cutoff_minutes_before">{{ __('Cutoff minutes before') }}</label>
                            <input id="cutoff_minutes_before" name="cutoff_minutes_before" type="number" min="0" value="{{ old('cutoff_minutes_before', $draw->cutoff_minutes_before) }}" class="brand-input mt-1 block w-full rounded-xl">
                            @error('cutoff_minutes_before')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="prize_multiplier">{{ __('Prize multiplier') }}</label>
                            <input id="prize_multiplier" name="prize_multiplier" type="number" step="0.01" min="1" value="{{ old('prize_multiplier', $draw->prize_multiplier) }}" class="brand-input mt-1 block w-full rounded-xl">
                            <p class="mt-1 text-xs text-slate-500">{{ __('A winning ₡100 bet pays ₡100 × this value.') }}</p>
                            @error('prize_multiplier')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="reventado_multiplier">{{ __('Reventado multiplier') }}</label>
                            <input id="reventado_multiplier" name="reventado_multiplier" type="number" step="0.01" min="1" value="{{ old('reventado_multiplier', $draw->reventado_multiplier) }}" class="brand-input mt-1 block w-full rounded-xl">
                            <p class="mt-1 text-xs text-slate-500">{{ __('Leave empty if this draw does not offer reventado.') }}</p>
                            @error('reventado_multiplier')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <label class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-slate-50 px-4 py-3">
                        <input type="checkbox" name="is_accepting_requests" value="1" @checked(old('is_accepting_requests', $draw->is_accepting_requests)) class="rounded border-slate-300 text-brand-primary focus:ring-brand-primary">
                        <span>
                            <span class="block text-sm font-medium text-slate-800">{{ __('Accepting requests') }}</span>
                            <span class="block text-xs text-slate-500">{{ __('Disable this to close the draw manually.') }}</span>
                        </span>
                    </label>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('draws.index') }}" class="brand-btn-secondary">{{ __('Cancel') }}</a>
                        <button type="submit" class="brand-btn-primary">{{ __('Save draw') }}</button>
                    </div>
                </form>
            </div>

            <div class="space-y-4">
                <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('Current state') }}</h2>
                    <dl class="mt-3 space-y-2 text-sm text-slate-700">
                        <div class="flex justify-between gap-4"><dt>{{ __('Open') }}</dt><dd>{{ $draw->isOpenForIntake() ? __('Yes') : __('No') }}</dd></div>
                        <div class="flex justify-between gap-4"><dt>{{ __('Reason') }}</dt><dd>{{ $draw->intakeStatusLabel() }}</dd></div>
                        <div class="flex justify-between gap-4"><dt>{{ __('Timezone') }}</dt><dd>{{ $draw->timezone }}</dd></div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
