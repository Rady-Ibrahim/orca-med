<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Orca Med') — توزيع الأدوية</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.orca-styles')
    @stack('head')
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            font-family: 'Cairo', sans-serif;
        }
    </style>
</head>
<body class="orca-ui">
    @yield('content')
    <script>
        window.ORCA_API_BASE = '{{ url('/api/v1') }}';
    </script>
    @stack('scripts')
</body>
</html>
