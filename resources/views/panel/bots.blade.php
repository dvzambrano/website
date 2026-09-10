<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bots</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8f9fa; }
        .page { max-width: 860px; margin: 2rem auto; padding: 0 1rem; }
    </style>
</head>
<body>
    <div class="page">
        <h1 class="h4 mb-3">Bots</h1>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Bot</th>
                            <th>Módulo</th>
                            <th>Creado</th>
                            <th class="text-end">Dashboard</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bots as $bot)
                            <tr>
                                <td>{{ $bot->name }}</td>
                                <td>{{ $bot->module }}</td>
                                <td>{{ $bot->created_at?->format('d/m/Y') }}</td>
                                <td class="text-end">
                                    @if ($bot->hasDashboard())
                                        <a href="{{ $bot->dashboardUrl() }}" class="btn btn-sm btn-primary">
                                            {{ $bot->dashboardLabel() }}
                                        </a>
                                    @else
                                        <span class="text-muted small">Sin dashboard</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No hay bots registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
