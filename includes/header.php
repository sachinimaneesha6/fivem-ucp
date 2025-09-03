<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>FiveM UCP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php 
    $base_path = '';
    if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
        $base_path = '../';
    }
    ?>
    <script src="<?php echo $base_path; ?>js/realtime.js" defer></script>
    <script src="<?php echo $base_path; ?>js/theme.js" defer></script>
    <script src="<?php echo $base_path; ?>js/inventory.js" defer></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'fivem': {
                            'primary': '#f39c12',
                            'secondary': '#2c3e50',
                            'dark': '#1a1a1a',
                            'accent': '#e74c3c'
                        },
                        'light': {
                            'bg': '#f8fafc',
                            'card': '#ffffff',
                            'border': '#e2e8f0',
                            'text': '#1f2937'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
        }
        
        .dark .card-hover:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
        }
        
        .light .card-hover:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Theme transitions */
        body, .theme-transition {
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #374151;
        }
        ::-webkit-scrollbar-thumb {
            background: #6b7280;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
        
        /* Light mode scrollbar */
        .light ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .light ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
        }
        .light ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Enhanced animations */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Enhanced card hover effects */
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
        }
        
        .dark .card-hover:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
        }
        
        .light .card-hover:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Better gradient backgrounds */
        .bg-gray-650 {
            background-color: #4a5568;
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Better button hover effects */
        .btn-hover {
            transition: all 0.3s ease;
        }
        
        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* Fix Alpine.js theme transitions */
        [x-cloak] { display: none !important; }
        
        /* Ensure proper theme inheritance */
        .dark {
            color-scheme: dark;
        }
        
        .light {
            color-scheme: light;
        }
    </style>
</head>
<body class="min-h-screen theme-transition" 
      x-data="{ darkMode: localStorage.getItem('darkMode') !== 'false' }" 
      x-init="
        $watch('darkMode', val => { 
          localStorage.setItem('darkMode', val); 
          document.documentElement.classList.toggle('dark', val);
          document.body.className = document.body.className.replace(/bg-\S+/g, '').replace(/text-\S+/g, '');
          document.body.classList.add(val ? 'bg-gray-900' : 'bg-gray-50');
          document.body.classList.add(val ? 'text-white' : 'text-gray-900');
        });
        document.documentElement.classList.toggle('dark', darkMode);
        document.body.classList.add(darkMode ? 'bg-gray-900' : 'bg-gray-50');
        document.body.classList.add(darkMode ? 'text-white' : 'text-gray-900');
      " 
      :class="{ 
        'dark': darkMode,
        'bg-gray-900': darkMode,
        'bg-gray-50': !darkMode,
        'text-white': darkMode,
        'text-gray-900': !darkMode
      }">

<script>
// Initialize theme immediately to prevent flash
(function() {
    const darkMode = localStorage.getItem('darkMode') !== 'false';
    document.documentElement.classList.toggle('dark', darkMode);
    document.body.classList.add(darkMode ? 'bg-gray-900' : 'bg-gray-50');
    document.body.classList.add(darkMode ? 'text-white' : 'text-gray-900');
})();
</script>