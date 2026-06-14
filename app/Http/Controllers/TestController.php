<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function test(Request $request, $name = null)
    {
        return response()->json([
            "message"   => "Test successful",
            "metadatas" => \Config::get('metadata'),
            "app_name"  => \Config::get('app.name'),
        ]);
    }
}
