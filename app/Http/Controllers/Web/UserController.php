<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\UserService;
use App\Services\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        private UserService      $service,
        private CompanyService   $companies,
        private WarehouseService $warehouses,
    ) {}

    public function index(Request $request): View
    {
        $items      = $this->service->list($request->only(['role', 'company_id', 'per_page']));
        $companies  = $this->companies->list(['per_page' => 200])->getCollection();
        $roles      = UserRole::cases();
        return view('users.index', compact('items', 'companies', 'roles'));
    }

    public function create(): View
    {
        $roles      = UserRole::cases();
        $companies  = $this->companies->list(['per_page' => 200])->getCollection();
        $warehouses = $this->warehouses->list(['per_page' => 200])->getCollection();
        return view('users.create', compact('roles', 'companies', 'warehouses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8'],
            'role'       => ['required', Rule::in(['admin', 'company'])],
            'company_id' => ['required_if:role,company', 'nullable', 'exists:companies,id'],
        ]);
        $this->service->create($data);
        return redirect()->route('users.index')->with('status', 'تمت إضافة المستخدم بنجاح.');
    }

    public function edit(User $user): View
    {
        $roles      = UserRole::cases();
        $companies  = $this->companies->list(['per_page' => 200])->getCollection();
        $warehouses = $this->warehouses->list(['per_page' => 200])->getCollection();
        return view('users.edit', compact('user', 'roles', 'companies', 'warehouses'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'   => ['nullable', 'string', 'min:8'],
            'role'       => ['required', Rule::in(['admin', 'company'])],
            'company_id' => ['required_if:role,company', 'nullable', 'exists:companies,id'],
        ]);
        $this->service->update($user, $data);
        return redirect()->route('users.index')->with('status', 'تم تحديث بيانات المستخدم بنجاح.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->service->delete($user);
        return redirect()->route('users.index')->with('status', 'تم حذف المستخدم بنجاح.');
    }
}
