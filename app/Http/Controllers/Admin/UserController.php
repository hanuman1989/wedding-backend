<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Requests\Admin\UserStatusRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use App\Queries\UserQuery;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class UserController extends Controller
{
    public function __construct(private readonly UserQuery $userQuery) {}

    public function index(UserIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $users = $this->userQuery->forList($filters)->paginate($filters['per_page'] ?? 15);

        return response()->json([
            'status' => true,
            'data' => UserResource::collection($users),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'next_page_url' => $users->nextPageUrl(),
                'prev_page_url' => $users->previousPageUrl(),
            ],
            'message' => 'Users retrieved successfully.',
        ]);
    }

    public function export(UserIndexRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new UsersExport($this->userQuery->forExport($request->validated())),
            sprintf('users-%s.xlsx', now()->format('Y-m-d_His')),
        );
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new UserResource($user),
            'message' => 'User details retrieved successfully.',
        ]);
    }

    public function updateStatus(UserStatusRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return response()->json([
            'status' => true,
            'data' => new UserResource($user),
            'message' => 'User status updated successfully.',
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        try {
            return $user->getConnection()->transaction(function () use ($user): JsonResponse {
                $user = User::query()->lockForUpdate()->findOrFail($user->id);

                if ($user->weddings()->exists() || $user->subscriptions()->exists()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'The user cannot be deleted because they are referenced in wedding or subscription records.',
                    ], 400);
                }

                $user->tokens()->delete();
                $user->getConnection()->table('sessions')->where('user_id', $user->id)->delete();
                $user->getConnection()->table('password_reset_tokens')->where('email', $user->email)->delete();
                $user->delete();

                return response()->json([
                    'status' => true,
                    'data' => [],
                    'message' => 'This user has been deleted successfully.',
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete user. Please try again.',
                'error' => 'Something went wrong. Please try again later',
            ], 500);
        }
    }
}
