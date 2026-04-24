<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\Permission;
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
        $user = User::with('permissions')->findOrFail($id);

        $permissions = Permission::all()->groupBy('section');

        return inertia('users/view', [
            'user' => $user,
            'permissionSections' => $permissions->map(function ($items, $groupName) {
                return [
                    'section' => $groupName,
                    'permissions' => $items->map(function ($p) {
                        return [
                            'label' => $p->label,
                            'value' => $p->value,
                        ];
                    })->values(),
                ];
            })->values(),

            'userPermissions' => $user->permissions
                ->where('pivot.active', 1)
                ->pluck('value')
                ->toArray(),
        ]);
    }

    public function update_permissions(Request $request, User $user)
    {
        $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $selected = $request->permissions ?? [];

        DB::transaction(function () use ($user, $selected) {

            // 🔥 deactivate all first
            UserPermission::where('user_id', $user->id)
                ->update(['active' => 0]);

            foreach ($selected as $permissionName) {

                // permission table ကနေ id ရယူ (assume Permission model exists)
                $permissionId = \App\Models\Permission::where('value', $permissionName)
                    ->value('id');

                if (!$permissionId) continue;

                UserPermission::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'permission_id' => $permissionId,
                    ],
                    [
                        'active' => 1
                    ]
                );
            }
        });

        return back()->with('success', 'Permissions updated successfully');
    }

    public function update_avatar(Request $request)
    {
        $request->validate([
            'profile' => 'required|string',
        ]);

        $user = $request->user();
        $user->profile = $request->profile;
        $user->save();

        return back()->with('success', 'Avatar updated');
    }

}
