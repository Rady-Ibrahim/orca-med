<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return UserResource::collection(
            $this->userService->list($request->only(['role', 'company_id', 'per_page']))
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'company_id' => ['nullable', 'required_if:role,company', 'exists:companies,id'],
            'warehouse_id' => ['nullable', 'required_if:role,warehouse', 'exists:warehouses,id'],
        ]);

        return (new UserResource($this->userService->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): JsonResponse
    {
        return (new UserResource($user->load(['company', 'warehouse'])))->response();
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'company_id' => ['nullable', 'exists:companies,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
        ]);

        return (new UserResource($this->userService->update($user, $data)))->response();
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'لا يمكن حذف حسابك.'], 422);
        }

        $this->userService->delete($user);

        return response()->json(['message' => 'تم الحذف.']);
    }
}
