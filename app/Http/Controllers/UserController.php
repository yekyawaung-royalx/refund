<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(){
        $users = DB::table('users')->paginate(10);

        return inertia('users/page', [
            'users' => $users,
        ]);
    }

    public function create(){
        
        return inertia('users/create', [
           
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_no' => 'required|string|max:255',
            'role' => 'required|string',
            'status' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $validated['password'] = bcrypt($validated['password']);

        DB::transaction(function () use ($validated) {

            $user = User::create($validated);

            // Role Based Permission IDs
            $defaultPermissions = [];

            if ($validated['role'] === 'manager') {
                $defaultPermissions = [1,2,3,4,5,6];
            }

            if ($validated['role'] === 'staff') {
                $defaultPermissions = [1,5];
            }

            // Attach to pivot table
            $user->permissions()->attach($defaultPermissions);

        });

        //User::create($validated);

        return redirect()->route('users.index');
    }

    public function view(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // example: user permissions (if you store as array/json column)
        //$permissions = $user->permissions ?? [];
        // $permissions = DB::table('user_permissions')
        //     ->where('user_id', $id)
        //     ->pluck('permission')   // 👈 only permission column
        //     ->toArray();
        $user = User::with('permissions')->findOrFail($id);

        //return  $user->permissions->pluck('name');

        return inertia('users/view', [
            'user' => $user,
            'userPermissions' => $user->permissions->pluck('name'),
        ]);
    }
}
