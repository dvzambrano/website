<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

class LandingController extends Controller
{
    public function index()
    {
        // Leemos el tema configurado (por defecto flexstart)
        $theme = config('zentrotraderbot.theme', 'FlexStart');

        // Retornamos la vista dinámica
        return view("zentrotraderbot::themes.{$theme}.index");
    }
}