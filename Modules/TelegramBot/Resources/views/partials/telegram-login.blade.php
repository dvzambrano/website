{{--
Uso:
@include('telegrambot::partials.telegram-login', [
'bot' => $botModel, // Pasamos el objeto completo del bot
])

'size' => small, medium, large
--}}

<div id="telegram-login-container" class="d-flex justify-content-center align-items-center my-3">
    @if (session('telegram_user'))
        @php $user = session('telegram_user'); @endphp

        <div class="user-logged-info d-flex align-items-center p-2 shadow-sm rounded border bg-light">
            @if (!empty($user['photo_url']))
                <img src="{{ route('avatar.proxy', ['file_path' => session('telegram_user.photo_url'), 'bot_token' => $bot->token]) }}"
                    class="rounded-circle me-2" referrerpolicy="no-referrer"
                    style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #0088cc;">
            @else
                <div class="rounded-circle me-2 bg-primary d-flex align-items-center justify-content-center text-white"
                    style="width: 40px; height: 40px;">
                    {{ substr($user['name'], 0, 1) }}
                </div>
            @endif

            <div class="text-start">
                <small class="text-muted d-block"
                    style="font-size: 0.7rem; line-height: 1;">{{ __('zentrotraderbot::landing.menu.user.identifiedas') }}</small>
                <span class="fw-bold text-dark">{{ $user['name'] }}</span>
            </div>

            {{-- Ruta para limpiar la sesión --}}
            <a href="{{ route('telegram.logout') }}" class="ms-3 text-danger" title="Salir">
                <i class="bi bi-x-circle"></i>
            </a>
        </div>
    @else
        <script async src="https://telegram.org/js/telegram-widget.js?22" data-telegram-login="{{ $bot->code }}"
            {{-- El
            username para el botón --}} data-size="{{ $size ?? 'large' }}" data-radius="{{ $radius ?? '10' }}" {{-- La URL
            de retorno usa la KEY interna, no el username --}}
            data-auth-url="{{ route('telegram.callback', ['key' => $bot->key]) }}" data-request-access="write"></script>
    @endif
</div>

<style>
    #telegram-login-container iframe {
        margin: 0 auto;
        display: block;
    }

    .user-logged-info {
        transition: all 0.3s ease;
    }

    .user-logged-info:hover {
        background-color: #f8f9fa !important;
    }
</style>
