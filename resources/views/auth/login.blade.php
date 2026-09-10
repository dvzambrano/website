@extends('templates.NiceAdmin.layout')

@section('mainstyle', 'margin-left:auto;margin-top:auto;')

@section('layoutjsincludes')
    <script src="{{ asset(config('walletconnect.assets.path', 'vendor/dvzambrano/walletconnect/js') . '/appkit-reown.js') }}"></script>
    @include('walletconnect::partials.appkit-init', app(\Dvzambrano\WalletConnect\Services\AppKitService::class)->getConfig())
    @include('walletconnect::partials.wallet-actions')
@endsection

@section('maincontent')
    <div class="container">

        <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-4 col-md-6 d-flex flex-column align-items-center justify-content-center">

                        <div class="d-flex justify-content-center py-4">
                            @include("templates.NiceAdmin.logo")
                        </div><!-- End Logo -->

                        <div class="card mb-3">

                            <div class="card-body">

                                <div class="pt-4 pb-2">
                                    <h5 class="card-title text-center pb-0 fs-4">{{ trans("messages.auth.login.title") }}
                                    </h5>
                                    <p class="text-center small">{{ trans("messages.auth.login.hint") }}</p>
                                </div>

                                <form method="POST" action="{{ route('login') }}" class="row g-3 needs-validation">

                                    @csrf

                                    <div class="col-md-12">
                                        <div class="form-floating">
                                            <input id="email" name="email" type="email" :value="old('email')"
                                                autocomplete="username"
                                                placeholder="{{ trans("messages.form.field.email.label") }}"
                                                class="form-control" required autofocus>
                                            <label for="code">{{ trans("messages.form.field.email.label") }}</label>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="form-floating">
                                            <input id="password" name="password" type="password"
                                                autocomplete="current-password"
                                                placeholder="{{ trans("messages.form.field.password.label") }}"
                                                class="form-control" required>
                                            <label for="code">{{ trans("messages.form.field.password.label") }}</label>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="remember" value="true"
                                                id="remember_me">
                                            <label class="form-check-label"
                                                for="rememberMe">{{ trans("messages.form.field.rememberme.label") }}</label>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <button class="btn btn-primary w-100"
                                            type="submit">{{ trans("messages.form.field.login.label") }}</button>
                                    </div>

                                    <div class="col-12">

                                        @if (Route::has('password.request'))

                                            <p class="small mb-0">

                                                <a href="{{ route('password.request') }}">
                                                    {{ trans("messages.form.field.forgotpassword.label") }}
                                                </a>
                                            </p>
                                            <br />
                                        @endif
                                        <p class="small mb-0">{{ trans("messages.form.field.donthaveaccount.label") }} <a
                                                href="{{ route('register') }}">
                                                {{ trans("messages.form.field.createaccount.label") }}
                                            </a></p>
                                        <br />

                                        <p class="small mb-0"><a href="{{ route('auth.google') }}"><i
                                                    class="bi bi-google"></i>
                                                {{ trans("messages.form.field.loginwithgoogle.label") }}</a></p>

                                        <p class="small mb-0"><a href="#web3" onclick="window.appKit.open(); return false;"><i
                                                    class="bi bi-wallet2"></i>
                                                {{ trans("messages.form.field.loginwithweb3.label") }}</a></p>
                                    </div>

                                </form>

                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </section>

    </div>
@endsection

<script>
    @section('ondocumentready')
        // window.onWalletConnected / window.onWalletDisconnected son los hooks
        // globales que appkit-init.blade.php invoca al conectar/desconectar la
        // wallet. wallet-actions.blade.php ya define una versión genérica (solo
        // actualiza el DOM) bajo esos mismos nombres; la guardamos aquí antes de
        // reemplazarla para poder seguir usándola y sumarle el flujo de login.
        (function () {
            var genericOnWalletConnected = window.onWalletConnected;
            // subscribeAccount() puede notificar más de una vez para la misma
            // conexión; sin esta guarda, dos llamadas casi simultáneas a
            // checkIsRegistered/register (ambas con Auth::login(), que migra
            // la sesión) compiten por el mismo token CSRF y la segunda puede
            // fallar en silencio. El login solo debe intentarse una vez por
            // carga de página.
            var loginAttempted = false;

            window.onWalletConnected = function (account, chainId) {
                genericOnWalletConnected(account, 8, function () {
                    if (loginAttempted) { return; }
                    loginAttempted = true;

                    checkIsRegistered(account, function () {
                        window.location.href = "{{ route('dashboard') }}";
                    }, function () {
                        register(account, "{{ request()->query('code') }}", function () {
                            window.location.href = "{{ route('dashboard') }}";
                        });
                    });
                });
            };
        })();
    @endsection
</script>