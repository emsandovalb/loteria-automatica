<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        return view('users.index', [
            'users' => User::query()
                ->with('branch')
                ->where('organization_id', $request->user()->organization_id)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        return view('users.form', [
            'user' => new User(['role' => User::ROLE_SELLER]),
            'roles' => $request->user()->assignableRoles(),
            'branches' => $this->branches($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $this->validated($request, null);

        $user = User::create([
            'organization_id' => $request->user()->organization_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'branch_id' => $validated['branch_id'],
            'is_active' => true,
            'password' => $validated['password'],
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return redirect()
            ->route('users.index')
            ->with('status', __('User :name created.', ['name' => $user->name]));
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('update', $user);

        return view('users.form', [
            'user' => $user,
            'roles' => $request->user()->assignableRoles(),
            'branches' => $this->branches($request->user()),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $this->validated($request, $user);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'branch_id' => $validated['branch_id'],
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()
            ->route('users.index')
            ->with('status', __('User :name updated.', ['name' => $user->name]));
    }

    /**
     * @return array{name:string, email:string, role:string, branch_id:?int, password:?string}
     */
    private function validated(Request $request, ?User $user): array
    {
        $actor = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user?->id)],
            'role' => ['required', Rule::in($actor->assignableRoles())],
            // Sellers work for one branch; the other roles see the whole organization.
            'branch_id' => [
                Rule::requiredIf($request->input('role') === User::ROLE_SELLER),
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('organization_id', $actor->organization_id),
            ],
            'password' => [$user === null ? 'required' : 'nullable', 'confirmed', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['branch_id'] = $validated['role'] === User::ROLE_SELLER ? (int) $validated['branch_id'] : null;
        $validated['password'] ??= null;

        return $validated;
    }

    private function branches(User $actor)
    {
        return Branch::query()
            ->where('organization_id', $actor->organization_id)
            ->orderBy('name')
            ->get();
    }
}
