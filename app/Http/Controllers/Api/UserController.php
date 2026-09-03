<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all employee accounts.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return response()->json(User::query()->orderBy('name')->get());
    }

    /**
     * Create a new employee account.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json($user, 201);
    }

    /**
     * Update an employee account.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->fill($request->safe()->except('password'));

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $user->save();

        return response()->json($user);
    }

    /**
     * Delete an employee account.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(null, 204);
    }
}
