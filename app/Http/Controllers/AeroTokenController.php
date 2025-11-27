<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AeroToken;

class AeroTokenController extends Controller
{
    function index()
    {
        $aero_tokens = AeroToken::where('user_id', auth()->user()->id)->with('user')->get();

        return inertia('AeroToken/Index', [
            'aero_tokens' => $aero_tokens,
        ]);
    }

    function create()
    {
        return inertia('AeroToken/Create', [

        ]);
    }

    function store(Request $request)
    {

        $data = [];

        if ($request->type == 'videcom') {
            $data = [
                'url' => $request->token['url'],
                'api_token' => $request->token['api_token'],

                'currency_code' => $request->pricing['currency_code'],
                'profit_from' => $request->pricing['profit_from'],
                'profit_percentage_international' => $request->pricing['profit_percentage_international'],
                'profit_percentage_domestic' => $request->pricing['profit_percentage_domestic'],
                'added_tax' => $request->pricing['added_tax'],
            ];
        }

        if ($request->type == 'amadeus') {
            $data = [
                'url' => $request->token['url'],
                'api_key' => $request->token['api_key'],
                'api_secret' => $request->token['api_secret'],

                'currency_code' => $request->pricing['currency_code'],
                'profit_from' => $request->pricing['profit_from'],
                'profit_percentage_international' => $request->pricing['profit_percentage_international'],
                'profit_percentage_domestic' => $request->pricing['profit_percentage_domestic'],
                'added_tax' => $request->pricing['added_tax'],
            ];
        }

       $token = AeroToken::create([
            'name' => $request->name,
            'iata' => $request->iata,
            'type' => $request->type,
            'data' => $data,
            'user_id' => auth()->user()->id
        ]);

        $token->setMeta('cabins', $request->get('cabins', []));

        return redirect()->route('aero-tokens.index');
    }

    function edit($id)
    {
        $aero_token = AeroToken::where('user_id', auth()->user()->id)->where('id', $id)->first();

        // dd(in_array('IST', $aero_token->getMeta('execluded_airports')->toArray()));
        // dd($aero_token->getMeta('execluded_airports')->contains('IST'));

        if ($aero_token->getMeta('cabins') === null) {
            $aero_token->setMeta('cabins', [
                'economy' =>[],
                'premium' =>[],
                'business' =>[],
                'first' =>[],
            ]);
        }
        return inertia('AeroToken/Edit', [
            'aero_token' => $aero_token,
            'metas' => $aero_token->getMetas(),
        ]);
    }

    function update(Request $request, $id)
    {

        $aero_token = AeroToken::where('user_id', auth()->user()->id)->where('id', $id)->first();

        $data = [];

        if ($request->type == 'videcom') {
            $data = [
                'mode' => $request->token['mode'],
                'url' => $request->token['url'],
                'currency_code' => $request->pricing['currency_code'],
                'profit_from' => $request->pricing['profit_from'],
                'profit_percentage_international' => $request->pricing['profit_percentage_international'],
                'profit_percentage_domestic' => $request->pricing['profit_percentage_domestic'],
                'added_tax' => $request->pricing['added_tax'],
            ];

            if ($request->token['mode'] == 'api') {
                $data['api_token'] = $request->token['api_token'];
            }

            if ($request->token['mode'] == 'user_auth') {
                $data['auth_user'] = $request->token['auth_user'];
                $data['auth_pass'] = $request->token['auth_pass'];
            }

            $data['airport_management_type'] = $request->airport_management_type;

            if ($request->airport_management_type == 'execulde') {

                if ($request->has('execluded_airports')) {
                    $aero_token->setMeta('execluded_airports', $request->execluded_airports);
                } else {
                    $aero_token->setMeta('execluded_airports', []);
                }
            }

            if ($request->airport_management_type == 'include') {

                if ($request->has('included_airports')) {
                    $aero_token->setMeta('included_airports', $request->included_airports);
                } else {
                    $aero_token->setMeta('included_airports', []);
                }
            }
            
            if ($request->has('cabins') ) {
                $aero_token->setMeta('cabins', $request->cabins);
            } else {
                $aero_token->setMeta('cabins', []);
            }
        }

        if ($request->type == 'amadeus') {
            $data = [
                'url' => $request->token['url'],
                'api_key' => $request->token['api_key'],
                'api_secret' => $request->token['api_secret'],

                'currency_code' => $request->pricing['currency_code'],
                'profit_from' => $request->pricing['profit_from'],
                'profit_percentage_international' => $request->pricing['profit_percentage_international'],
                'profit_percentage_domestic' => $request->pricing['profit_percentage_domestic'],
                'added_tax' => $request->pricing['added_tax'],
            ];
        }

        $aero_token->update([
            'name' => $request->name,
            'iata' => $request->iata,
            'type' => $request->type,
            'data' => $data,
            'user_id' => auth()->user()->id
        ]);

        return redirect()->route('aero-tokens.index');

    }
}
