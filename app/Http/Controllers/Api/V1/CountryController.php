<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $countries = Country::orderBy('name')->get();
        return response()->json([
            'message' => 'success',
            'countries' => $countries,
        ], 200);
    }
}
