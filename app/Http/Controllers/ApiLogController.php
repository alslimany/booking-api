<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use Illuminate\Http\Request;

class ApiLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $api_logs = ApiLog::orderBy("created_at", "desc")
            ->with(['user:id,name'])
            ->paginate(15);

        return inertia('ApiLog/Index', [
            'api_logs' => $api_logs
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
