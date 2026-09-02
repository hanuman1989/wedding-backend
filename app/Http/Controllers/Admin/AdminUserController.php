<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\AdminUser;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    // Register a new admin user
    public function store(AdminUserRequest $request)
    {
        DB::beginTransaction(); // Start a transaction
        try {
            $validatedData = $request->validated();

            $adminUser = AdminUser::create($request->validated());

            // Create the user record
            $response = [
                'status' => true,
                'data' => new AdminUserResource($adminUser),
                'message' => 'User registered successfully.',
            ];

            DB::commit(); // Commit the transaction

            return response()->json($response, 201); // Return response with HTTP status 201 (Created)

        } catch (\Exception $e) {  // Catch any general exception (not just QueryException)
            DB::rollBack();

            // Return error response
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);  // Return HTTP status 500 for server errors
        }
    }

    // Login admin user
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // $adminUser = AdminUser::create([
        //     'email' => $request->email,
        //     'password' => Hash::make($request->password),
        //     'email_verified_at' => now(),
        //     'status' => 1,
        //     'first_name' => 'Hanuman',
        //     'last_name' => 'Yadav',
        //     'mobile' => '1234567890',
        // ]);

        $adminUser = AdminUser::where('email', $request->email)->first();

        if (! $adminUser || ! Hash::check($request->password, $adminUser->password)) {

             return response()->json([
                'status' => false,
                'message' => 'Invalid email or password.',
                'data' => null,
            ], 401);
        }

        $token = $adminUser->createToken('admin-auth-token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => (new AdminUserResource($adminUser))->resolve(),
                'token' => $token,
            ],
        ]);
    }

    // Logout admin user
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logout successful',
            'data' => [],
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $direction = request('direction', 'desc');
        $sort = request('sort', 'created_at');
        $adminUsers = AdminUser::orderBy($sort, $direction)->get();

        return AdminUserResource::collection($adminUsers);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AdminUserRequest $request, AdminUser $adminUser)
    {
        DB::beginTransaction();
        try {
            // Update the Team with validated data
            $adminUser->update($request->validated());
            DB::commit();
            // Return a resource response with the updated team
            $response = ['status' => true, 'data' => new AdminUserResource($adminUser), 'message' => 'User has been updated successfully.'];
        } catch (QueryException $e) {
            DB::rollBack();
            $response = ['status' => false, 'message' => $e->getMessage()];
        }

        return response()->json($response, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AdminUser $adminUser)
    {
        try {

            // Check if the user is referenced in other tables
            $isReferenced = $adminUser->leagueTeamsAsOwner()->exists() || $adminUser->leagueTeamsAsCoach()->exists();

            if ($isReferenced) {
                return response()->json([
                    'status' => false,
                    'message' => 'The user cannot be deleted because they are assigned to a league team or referenced in other records',
                ], 400);
            }

            $adminUser->delete();

            return response()->json(['status' => true, 'data' => [], 'message' => 'This user has been deleted successfully.'], 200);
        } catch (\Exception $e) {
            // Rollback the transaction if something goes wrong
            return response()->json(['status' => false, 'error' => 'Something went wrong. Please try again later', 'message' => $e->getMessage()], 500);
        }
    }


}
