<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GigaEsimController extends Controller
{
    // private $url = "https://sandbox.giga.ly/partner/v1/esim/";
    // private $token = "123938|XKTRE9tg4VxqjPjA4Ujl9tw7lmfyq4nj97AbhE9U287b745b";

    // private function esim_api($version = "v1")
    // {
    //     return \Illuminate\Support\Facades\Http::withToken($this->token)
    //         ->baseUrl("https://sandbox.giga.ly/partner/" . $version . "/")
    //         ->withHeader('Accept-Language', 'en-us')
    //         ->withOptions([
    //             'verify' => false,
    //             'timeout' => 60
    //         ]);
    // }

    private $url = "https://sandbox.giga.ly/partner/v1/esim/";
    // private $token = "149694|L21q7Nd7A4c43rL9JTVQNJzefgKm616JPiKrWKLyccaa0dd0";
    // private $token = "149695|MFkw6Z9pk4qR8DbtCoAuhk1IWQOKYrA5iD3vPt955969491b";
    private $token = "149697|xxuQsy75I0y4RxLagJAkqyjcNneGKfZ3NCzQ6FU518c4606d";

    private function esim_api($version = "v1")
    {
        $lang_header = request()->header("Accept-Language", 'en-us');

        return \Illuminate\Support\Facades\Http::withToken($this->token)
            ->baseUrl("https://median.giga.ly:4443/partner/" . $version . "/")
            ->withHeader('Accept-Language', $lang_header)
            ->withOptions([
                'verify' => false,
                'timeout' => 60
            ]);
    }
    public function get_countries()
    {
        $response = $this->esim_api()->get('esim/countries');

        return $response->json();
    }

    public function get_products($code)
    {
        $country_code = "";
        
        if (strlen($code) == 2) {
            $country_code = $code;
        }

        if (strlen($code) == 3) {
            $airport = getDbAirport($code);
            $country_code = $airport->country;
        }
        
        # Get countries
        $response = $this->esim_api()->get('esim/countries');

        $countries = collect($response->json('data'));

        // $country = $country_code;


        $country = $countries->firstWhere('iso', strtoupper($country_code));

        # Get products for country
        $products_response = $this->esim_api()->get('esim/countries/' . $country['id']);

        $products = [];
        foreach ($products_response->json('data')['products'] as $product) {
            $product['provider'] = "GIGA_NET";
            $products[] = $product;
        }

        // foreach ($products_response->json('data')['roaming_products'] as $product) {
        //     $product['provider'] = "GIGA_NET";
        //     $products[] = $product;
        // }

        $result = [
            'meta' => [
                'count' => count($products),
            ],
            'data' => $products
        ];

        return response()->json($result, 200);
        // return $products_response->json('data');

    }

    public function get_product_details($product_id)
    {
        # Get product details
        $response = $this->esim_api()->get("esim/products/$product_id");

        return $response->json('data');
    }

    public function get_orders(Request $request)
    {
        $response = $this->esim_api()->get("esim/orders");

        return $response->json('data');
    }

    public function view_balance(Request $request): mixed
    {
        $response = $this->esim_api()->get("balance");

        return $response->json('data');
    }

    public function create_order(Request $request, $product_id)
    {
        $response = $this->esim_api()->post("esim/purchase", [
            "product_id" => $product_id,
        ]);

        return $response->json('data');
    }

    public function get_order(Request $request, $order_id)
    {
        $response = $this->esim_api()->get("esim/orders/$order_id");

        return $response->json('data');
    }

    public function refund_order(Request $request, $order_id) {
        $response = $this->esim_api()->delete("esim/orders/$order_id/refund");

        return $response->json('data');
    }
}
