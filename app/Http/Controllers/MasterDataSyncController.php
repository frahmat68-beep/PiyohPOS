<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MasterDataSyncController extends Controller
{
    /**
     * Handle master data sync trigger.
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'sync endpoint ready',
        ]);
    }
}
