<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') · Peaje IA</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚧</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: radial-gradient(circle at 20% 20%, #1e3a8a 0, #0f172a 55%); }
        .tarjeta { max-width: 440px; border: 0; border-radius: 1.25rem; }
        .btn-google { background: #fff; border: 1px solid #dadce0; color: #3c4043; font-weight: 600; }
        .btn-google:hover { background: #f8f9fa; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
@yield('contenido')
</body>
</html>
