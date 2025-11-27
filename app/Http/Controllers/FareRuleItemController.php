<?php

namespace App\Http\Controllers;

use App\Models\FareRuleItem;
use Illuminate\Http\Request;

class FareRuleItemController extends Controller
{
    public function store(Request $request)
    {
        $item = new FareRuleItem();
        $item->fare_rule_id = $request->fare_rule_id;
        $item->key = $request->key;
        $item->value = $request->value;
        $item->status = $request->status;
        $item->note = $request->note ?? '-';

        $item->save();

        return redirect()->back();
    }
}
