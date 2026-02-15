<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <base href="{{ url('laravel/ZentroTraderBot/' . config('zentrotraderbot.theme', 'FlexStart')) }}/" />

    <title>{{ __('zentrotraderbot::landing.title') }}</title>

    <style>
        .navbar .dropdown ul img {
            width: 18px;
            height: auto;
            margin-right: 10px;
            vertical-align: middle;
        }

        .navbar .dropdown span i {
            font-size: 1.1rem;
            margin-right: 5px;
        }
    </style>

    @yield('head')
</head>

<body>
    @yield('body')
</body>

</html>