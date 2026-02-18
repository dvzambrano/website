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



    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                // Obtenemos el ID de la sección (ejemplo: #about)
                const targetId = this.getAttribute('href');

                // Si solo es "#", no hacemos nada (ya lo cubrimos con el Back to Top)
                if (targetId === '#') return;

                const targetElement = document.querySelector(targetId);

                if (targetElement) {
                    e.preventDefault(); // Evitamos que cambie la URL a esa ruta larga

                    // Calculamos la posición restando un poco de margen por si tienes el header fijo
                    const offset = 70;
                    const elementPosition = targetElement.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - offset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>

</html>