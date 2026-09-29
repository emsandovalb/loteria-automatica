<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ $user->exists ? __('Edit user') : __('New user') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('Sellers only see and sell for their branch. Admins and viewers see the whole organization.') }}</p>
            </div>
            <a href="{{ route('users.index') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                {{ __('Back to users') }}
            </a>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" x-data="{ role: @js(old('role', $user->role)) }">
            <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="space-y-4">
                @csrf
                @if ($user->exists)
                    @method('PUT')
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('email')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-medium text-slate-700">{{ __('Role') }}</label>
                        <select id="role" name="role" x-model="role" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ __($role) }}</option>
                            @endforeach
                        </select>
                        @error('role')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div x-show="role === 'seller'">
                        <label for="branch_id" class="block text-sm font-medium text-slate-700">{{ __('Branch') }}</label>
                        <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="">{{ __('Select a branch') }}</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) old('branch_id', $user->branch_id) === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700">
                            {{ $user->exists ? __('New password (leave empty to keep the current one)') : __('Password') }}
                        </label>
                        <input id="password" name="password" type="password" autocomplete="new-password" @required(! $user->exists) class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-700">{{ __('Confirm Password') }}</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>

                    @if ($user->exists)
                        <label class="md:col-span-2 flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500" @checked(old('is_active', $user->is_active))>
                            <span>
                                {{ __('Active') }}
                                <span class="block text-xs text-slate-500">{{ __('Deactivated users cannot log in and are signed out immediately. Their history is kept.') }}</span>
                            </span>
                        </label>
                    @endif
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="brand-btn-primary">{{ $user->exists ? __('Save changes') : __('Create user') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
