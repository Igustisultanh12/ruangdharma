<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#7c3aed">
    <meta name="description" content="Ruang Dharma Studio — buat naskah, prompt visual & caption video cerita Hindu untuk anak.">
    <title>{{ config('app.name', 'Ruang Dharma Studio') }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='14' r='10' fill='%23f8f5ee' stroke='%2315101f' stroke-width='3'/%3E%3Ccircle cx='50' cy='32' r='10' fill='%23ef4444' stroke='%2315101f' stroke-width='3'/%3E%3Ccircle cx='32' cy='50' r='10' fill='%23facc15' stroke='%2315101f' stroke-width='3'/%3E%3Ccircle cx='14' cy='32' r='10' fill='%233b82f6' stroke='%2315101f' stroke-width='3'/%3E%3Ccircle cx='32' cy='32' r='8' fill='%23c026d3' stroke='%2315101f' stroke-width='3'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <script>
        // Terapkan tema sebelum render agar tidak berkedip.
        try {
            var t = localStorage.getItem('rd_theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
        } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
    <noscript>Aktifkan JavaScript untuk memakai Ruang Dharma Studio.</noscript>
</body>
</html>
