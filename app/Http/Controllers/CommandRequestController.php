<?php

namespace App\Http\Controllers;

use App\Models\CommandRequest;
use DB;
use Illuminate\Http\Request;

class CommandRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $command_requests = CommandRequest::orderBy("created_at", "desc")
            ->when(request()->get('search', null), function ($q, $v) {
                $q->where('command', 'like', '%' . $v . '%');
            })
            ->when(request()->get('aero_token', null), function ($q, $v) {
                $q->where('aero_token_id', $v);
            })
            ->with(['user:id,name', 'aero_token:id,name'])
            ->paginate(perPage: 100);

        $date = date('Y-m-d', strtotime(request()->get('date', now())));
        $extra_command = "";
        if (request()->filled('aero_token')) {
            $extra_command = 'and aero_token_id = ' . request()->get('aero_token') . ' ';
        }
        $command_requests_chart_data = DB::select("
            select aero_token_id, day(created_at) as Day, hour(created_at) as Hour, count(*) as Count from command_requests
            where created_at between '" . $date . " 00:00:00.000000' and '" . $date . " 23:59:59.000000'  $extra_command
            group by aero_token_id, day(created_at), hour(created_at)
            ");

        $tokens = [];
        foreach (collect($command_requests_chart_data)->sortBy('aero_token_id') as $row) {

            $token = \App\Models\AeroToken::withTrashed()->find($row->aero_token_id);

            $token_name = $token?->id . ' #' . $token?->name;
            if (!in_array($token_name, $tokens)) {
                $tokens[] = $token_name;
            }
        }


        $chart_data = [];
        $hours = range(0, 23); // Assuming you only want hours from 0 to 10

        foreach ($hours as $hour) {
            $series = [
                'name' => sprintf('%02d:00', $hour),
                'data' => []
            ];
            foreach (collect($command_requests_chart_data)->sortBy('aero_token_id') as $record) {
                if ($record->Hour == $hour) {
                    $series['data'][] = $record->Count;
                } else {
                    // $series['data'][] = 0; // If there's no data for the hour, add zero
                }
            }
            $chart_data['series'][] = $series;
        }

        // return response()->json($chart_data);

        return inertia('CommandRequest/Index', [
            'command_requests' => $command_requests,
            'command_requests_chart_data' => [
                'tokens' => $tokens,
                'data' => $chart_data
            ],
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
