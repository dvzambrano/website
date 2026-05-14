<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <base href="{{ url('laravel/ZentroTraderBot/' . config('zentrotraderbot.theme', 'FlexStart')) }}/" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('zentrotraderbot::landing.title') }}</title>

    @yield('htmlhead')

</head>

<body id="@yield('htmlbodyid')" class="@yield('htmlbodycss')">

    @yield('htmlbody')


    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <script>
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": true, // Los nuevos aparecen arriba
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false, // Queremos ver todos los hallazgos
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000", // 5 segundos visibles
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };
    </script>

</body>

</html>
