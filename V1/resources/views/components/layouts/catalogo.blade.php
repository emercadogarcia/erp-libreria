<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('accounting.empresa.nombre') }} - Catálogo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fffbeb', 100: '#fef3c7', 500: '#f59e0b', 600: '#d97706', 700: '#b45309', 900: '#78350f'
                        }
                    }
                }
            }
        }
    </script>
    @livewireStyles
    <style>
        [x-cloak] { display: none !important; }
        .libro-card:hover .libro-cover { transform: scale(1.03); }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-stone-50 min-h-screen">
    {{ $slot }}
    @livewireScripts
</body>
</html>
