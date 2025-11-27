<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FareRuleController extends Controller
{
    public function index()
    {
        $fare_rules = \App\Models\FareRule::with('aero_token')
            ->when(request()->get('status'), function ($q, $v) {
                $q->where('status', $v);
            })
            ->when(request()->get('search'), function ($q, $v) {
                $q->where('fare_id', $v);
            })
            ->paginate(30);

        return inertia("FareRule/Index", [
            'fare_rules' => $fare_rules,
            'filters' => [
                'status' => request()->get('status'),
                'search' => request()->get('search'),
            ],
            'counts' => [
                'new' => \App\Models\FareRule::where('status', 'new')->count(),
                'changed' => \App\Models\FareRule::where('status', 'changed')->count(),
                'updated' => \App\Models\FareRule::where('status', 'updated')->count(),
            ]
        ]);
    }

    public function create()
    {

    }

    public function store(Request $request)
    {

    }

    public function show($id)
    {

    }

    public function edit($id)
    {
        $fare_rule = \App\Models\FareRule::with('items')->where('id', $id)->first();

        $fare_note = cache()->remember("fare_rule_" . $fare_rule->carrier . "_" . $fare_rule->fare_id, now()->addDays(1), function () use ($fare_rule) {
            $result = $fare_rule->aero_token->build()->runCommand("FN" . $fare_rule->fare_id);
            return (string) $result->response;
        });

        return inertia("FareRule/Edit", [
            'fare_rule' => $fare_rule,
            'fare_note' => $fare_note,
        ]);
    }

    public function update(Request $request, $id)
    {
        $fare_rule = \App\Models\FareRule::find($id);

        $fare_rule->rules = $request->rules;
        $fare_rule->note = $request->note;
        $fare_rule->status = $request->status;
        $fare_rule->update();

        return redirect()->route('fare-rules.index');
    }
}
