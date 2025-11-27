<?php

namespace App\Http\Controllers;

use App\Models\HotelToken;
use App\Models\Provider;
use Illuminate\Http\Request;

class HotelTokenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $hotel_tokens = HotelToken::orderBy("created_at", "desc")
            ->with(['provider:id,name'])
            ->with(['user:id,name'])
            ->paginate(15);

        return inertia("HotelToken/Index", [
            'hotel_tokens' => $hotel_tokens,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $providers = Provider::all();

        return inertia("HotelToken/Create", [
            "providers" => $providers,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = [];

        if ($request->type == '3t') {
            $data = [
                'url' => $request->token['url'],
                'api_key' => $request->token['api_key'],
                'login' => $request->token['login'],
                'password' => $request->token['password'],

                // 'currency_code' => $request->pricing['currency_code'],
                // 'profit_from' => $request->pricing['profit_from'],
                // 'profit_percentage_international' => $request->pricing['profit_percentage_international'],
                // 'profit_percentage_domestic' => $request->pricing['profit_percentage_domestic'],
                // 'added_tax' => $request->pricing['added_tax'],
            ];
        }

        HotelToken::create([
            'provider_id' => 1,
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'data' => $data,
            'user_id' => auth()->user()->id
        ]);

        return redirect()->route('hotel-tokens.index');

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
