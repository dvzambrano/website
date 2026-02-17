{{--
Uso: @include('telegrambot::partials.telegram-login', ['bot' => 'KashioBot', 'callback' =>
route('telegram.callback'),'size' => 'large'])
'size' => small, medium, large
--}}

<div id="telegram-login-container" class="d-flex justify-content-center my-3">
    <script async src="https://telegram.org/js/telegram-widget.js?22" data-telegram-login="{{ $bot }}"
        data-size="{{ $size ?? 'large' }}" data-radius="10" @if(isset($callback)) data-auth-url="{{ $callback }}" @endif
        data-request-access="write">
        </script>
</div>

<style>
    /* Ajuste por si quieres centrarlo o darle estilo extra */
    #telegram-login-container iframe {
        margin: 0 auto;
        display: block;
    }
</style>