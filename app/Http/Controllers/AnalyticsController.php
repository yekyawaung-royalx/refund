<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AnalyticsController extends Controller
{
    public function analytics_accounts(){
        $analytics = DB::table('analytics')
            ->orderBy('account', 'asc')
            ->paginate(50);

        return Inertia::render('analytics/AnalyticsAccount', [
            'analytics' => $analytics
        ]);
    }

    public function create_analytics_accounts(){
        return Inertia::render('analytics/CreateAccount', [
            
        ]);
    }

    public function update_analytics_accounts(Request $request){

        DB::table('analytics')->where('id',$request->id)->update([
            'account' => $request->account,
            'reference' => $request->reference,
            'journal' => $request->journal,
        ]);

        $analytics = DB::table('analytics')
            ->orderBy('account', 'asc')
            ->paginate(50);

        return redirect()
            ->route('analytics')
            ->with('success', 'Account updated successfully.');
    }
    
}
