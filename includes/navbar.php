<?php if (isset($auth) && $auth->isLoggedIn()): ?>
<nav class="sticky top-0 z-50 backdrop-blur-sm bg-opacity-95 theme-transition" 
     :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'"
     class="border-b">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-fivem-primary rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-gamepad text-white"></i>
                        </div>
                        <h1 class="text-xl font-bold text-fivem-primary">FiveM UCP</h1>
                    </div>
                </div>
                <div class="hidden lg:ml-8 lg:flex lg:space-x-2">
                    <a href="dashboard.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="characters.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-users mr-2"></i>Characters
                    </a>
                    <a href="inventory.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-boxes mr-2"></i>Inventory
                    </a>
                    <a href="vehicles.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-car mr-2"></i>Vehicles
                    </a>
                    <a href="banking.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-university mr-2"></i>Banking
                    </a>
                    
                    <!-- Dropdown for More Options -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="px-3 py-2 rounded-lg text-sm font-medium transition-all theme-transition flex items-center"
                                :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                            <i class="fas fa-ellipsis-h mr-2"></i>More
                            <i class="fas fa-chevron-down ml-1 text-xs" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                             class="absolute top-full left-0 mt-1 w-48 rounded-lg shadow-lg py-2 z-50 theme-transition"
                             :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'"
                             class="border">
                            <a href="map.php" class="block px-4 py-2 transition-colors theme-transition"
                               :class="darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'">
                                <i class="fas fa-map mr-2"></i>Map
                            </a>
                            <a href="properties.php" class="block px-4 py-2 transition-colors theme-transition"
                               :class="darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'">
                                <i class="fas fa-home mr-2"></i>Properties
                            </a>
                            <a href="chat.php" class="block px-4 py-2 transition-colors theme-transition"
                               :class="darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'">
                                <i class="fas fa-comments mr-2"></i>Chat
                            </a>
                            <a href="tickets.php" class="block px-4 py-2 transition-colors theme-transition"
                               :class="darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'">
                                <i class="fas fa-ticket-alt mr-2"></i>Support
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Theme Toggle -->
                <button @click="darkMode = !darkMode" 
                        class="p-2 rounded-lg transition-all theme-transition" 
                        :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'"
                        title="Toggle Theme">
                    <i class="fas fa-sun" x-show="darkMode"></i>
                    <i class="fas fa-moon" x-show="!darkMode"></i>
                </button>
                
                <!-- User Menu -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center text-sm focus:outline-none transition-colors theme-transition"
                            :class="darkMode ? 'text-gray-300 hover:text-white' : 'text-gray-700 hover:text-gray-900'">
                        <div class="w-8 h-8 bg-fivem-primary rounded-full flex items-center justify-center mr-2">
                            <i class="fas fa-user text-white text-xs"></i>
                        </div>
                        <span class="font-semibold"><?php echo $_SESSION['username']; ?></span>
                        <i class="fas fa-chevron-down ml-2 text-xs" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 top-full mt-2 w-48 rounded-lg shadow-lg py-2 z-50 theme-transition border"
                         :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                        <?php if ($auth->isAdmin()): ?>
                            <a href="admin/dashboard.php" class="block px-4 py-2 text-red-400 hover:text-red-300 transition-colors theme-transition"
                               :class="darkMode ? 'hover:bg-gray-700' : 'hover:bg-red-50'">
                                <i class="fas fa-cog mr-2"></i>Admin Panel
                            </a>
                            <hr class="my-2 theme-transition" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                        <?php endif; ?>
                        <a href="dashboard.php" class="block px-4 py-2 transition-colors theme-transition"
                           :class="darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'">
                            <i class="fas fa-home mr-2"></i>Dashboard
                        </a>
                        <a href="characters.php" class="block px-4 py-2 transition-colors theme-transition"
                           :class="darkMode ? 'text-gray-300 hover:bg-gray-700 hover:text-white' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'">
                            <i class="fas fa-user mr-2"></i>My Characters
                        </a>
                        <hr class="my-2 theme-transition" :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                        <a href="logout.php" class="block px-4 py-2 text-red-400 hover:text-red-300 transition-colors theme-transition"
                           :class="darkMode ? 'hover:bg-gray-700' : 'hover:bg-red-50'">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile Navigation -->
        <div class="lg:hidden" x-data="{ mobileOpen: false }">
            <div class="flex items-center justify-between px-4 py-3 border-t theme-transition"
                 :class="darkMode ? 'border-gray-700' : 'border-gray-200'">
                <button @click="mobileOpen = !mobileOpen" class="focus:outline-none transition-colors theme-transition"
                        :class="darkMode ? 'text-gray-300 hover:text-white' : 'text-gray-700 hover:text-gray-900'">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <button @click="darkMode = !darkMode" class="p-2 rounded-lg transition-all theme-transition"
                        :class="darkMode ? 'text-gray-300 hover:text-white' : 'text-gray-700 hover:text-gray-900'">
                    <i class="fas fa-sun" x-show="darkMode"></i>
                    <i class="fas fa-moon" x-show="!darkMode"></i>
                </button>
            </div>
            
            <div x-show="mobileOpen" x-transition class="border-t theme-transition"
                 :class="darkMode ? 'border-gray-700 bg-gray-800' : 'border-gray-200 bg-white'">
                <div class="px-2 pt-2 pb-3 space-y-1">
                    <a href="dashboard.php" class="block px-3 py-2 rounded-lg text-base font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
                    </a>
                    <a href="characters.php" class="block px-3 py-2 rounded-lg text-base font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-users mr-3"></i>Characters
                    </a>
                    <a href="inventory.php" class="block px-3 py-2 rounded-lg text-base font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-boxes mr-3"></i>Inventory
                    </a>
                    <a href="vehicles.php" class="block px-3 py-2 rounded-lg text-base font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-car mr-3"></i>Vehicles
                    </a>
                    <a href="banking.php" class="block px-3 py-2 rounded-lg text-base font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-university mr-3"></i>Banking
                    </a>
                    <a href="map.php" class="block px-3 py-2 rounded-lg text-base font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-map mr-3"></i>Map
                    </a>
                    <a href="properties.php" class="block px-3 py-2 rounded-lg text-base font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-home mr-3"></i>Properties
                    </a>
                    <a href="chat.php" class="block px-3 py-2 rounded-lg text-base font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-comments mr-3"></i>Chat
                    </a>
                    <a href="tickets.php" class="block px-3 py-2 rounded-lg text-base font-medium transition-all theme-transition"
                       :class="darkMode ? 'text-gray-300 hover:text-white hover:bg-gray-700' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'">
                        <i class="fas fa-ticket-alt mr-3"></i>Support
                    </a>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="admin/dashboard.php" class="block px-3 py-2 rounded-lg text-base font-medium text-red-400 hover:text-red-300 transition-all theme-transition"
                           :class="darkMode ? 'hover:bg-gray-700' : 'hover:bg-red-50'">
                            <i class="fas fa-cog mr-3"></i>Admin Panel
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- Breadcrumb Navigation -->
<?php if (isset($auth) && $auth->isLoggedIn() && isset($page_title) && $page_title !== 'Dashboard'): ?>
<div class="border-b theme-transition" :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2">
                <li>
                    <a href="dashboard.php" class="transition-colors theme-transition"
                       :class="darkMode ? 'text-gray-400 hover:text-gray-300' : 'text-gray-500 hover:text-gray-700'">
                        <i class="fas fa-home"></i>
                    </a>
                </li>
                <li>
                    <i class="fas fa-chevron-right text-xs" :class="darkMode ? 'text-gray-400' : 'text-gray-400'"></i>
                </li>
                <li>
                    <span class="font-medium theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo $page_title; ?></span>
                </li>
            </ol>
        </nav>
    </div>
</div>
<?php endif; ?>

<!-- Mobile Bottom Navigation -->
<?php if (isset($auth) && $auth->isLoggedIn()): ?>
<div class="lg:hidden fixed bottom-0 left-0 right-0 z-40 border-t theme-transition"
     :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
    <div class="grid grid-cols-5 gap-1 p-2">
        <a href="dashboard.php" class="flex flex-col items-center py-2 px-1 hover:text-fivem-primary transition-colors theme-transition"
           :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
            <i class="fas fa-home text-lg mb-1"></i>
            <span class="text-xs">Home</span>
        </a>
        <a href="characters.php" class="flex flex-col items-center py-2 px-1 hover:text-fivem-primary transition-colors theme-transition"
           :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
            <i class="fas fa-users text-lg mb-1"></i>
            <span class="text-xs">Characters</span>
        </a>
        <a href="vehicles.php" class="flex flex-col items-center py-2 px-1 hover:text-fivem-primary transition-colors theme-transition"
           :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
            <i class="fas fa-car text-lg mb-1"></i>
            <span class="text-xs">Vehicles</span>
        </a>
        <a href="banking.php" class="flex flex-col items-center py-2 px-1 hover:text-fivem-primary transition-colors theme-transition"
           :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
            <i class="fas fa-university text-lg mb-1"></i>
            <span class="text-xs">Banking</span>
        </a>
        <a href="tickets.php" class="flex flex-col items-center py-2 px-1 hover:text-fivem-primary transition-colors theme-transition"
           :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
            <i class="fas fa-ticket-alt text-lg mb-1"></i>
            <span class="text-xs">Support</span>
        </a>
    </div>
</div>

<!-- Add bottom padding to prevent content from being hidden behind mobile nav -->
<style>
    @media (max-width: 1024px) {
        body {
            padding-bottom: 80px;
        }
    }
</style>
<?php endif; ?>

<!-- Old navigation code removed -->
<?php if (false): ?>
<nav class="bg-gray-800 border-b border-gray-700 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <h1 class="text-xl font-bold text-fivem-primary">FiveM UCP</h1>
                </div>
                <div class="hidden md:ml-6 md:flex md:space-x-8">
                    <a href="dashboard.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="characters.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-users mr-2"></i>Characters
                    </a>
                    <a href="inventory.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-boxes mr-2"></i>Inventory
                    </a>
                    <a href="tickets.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-ticket-alt mr-2"></i>Support
                    </a>
                    <a href="vehicles.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-car mr-2"></i>Vehicles
                    </a>
                    <a href="banking.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-university mr-2"></i>Banking
                    </a>
                    <a href="map.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-map mr-2"></i>Map
                    </a>
                    <a href="properties.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-home mr-2"></i>Properties
                    </a>
                    <a href="chat.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-comments mr-2"></i>Chat
                    </a>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="admin/dashboard.php" class="text-red-300 hover:text-red-200 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                            <i class="fas fa-cog mr-2"></i>Admin
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-sm text-gray-300">
                    Welcome, <span class="text-fivem-primary font-semibold"><?php echo $_SESSION['username']; ?></span>
                </div>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
    
    <!-- Mobile menu -->
    <div class="md:hidden" x-data="{ open: false }">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3" x-show="open">
            <a href="dashboard.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Dashboard</a>
            <a href="characters.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Characters</a>
            <a href="inventory.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Inventory</a>
            <a href="tickets.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Support</a>
            <a href="vehicles.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Vehicles</a>
            <a href="banking.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Banking</a>
            <a href="map.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Map</a>
            <a href="properties.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Properties</a>
            <a href="chat.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Chat</a>
            <?php if ($auth->isAdmin()): ?>
                <a href="admin/dashboard.php" class="text-red-300 hover:text-red-200 block px-3 py-2 rounded-md text-base font-medium">Admin</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- Remove old mobile menu code -->
<?php if (false): ?>
    <!-- Mobile menu -->
    <div class="md:hidden" x-data="{ open: false }">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3" x-show="open">
            <a href="dashboard.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Dashboard</a>
            <a href="characters.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Characters</a>
            <a href="inventory.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Inventory</a>
            <a href="tickets.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Support</a>
            <a href="vehicles.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Vehicles</a>
            <a href="banking.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Banking</a>
            <a href="map.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Map</a>
            <a href="properties.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Properties</a>
            <a href="chat.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Chat</a>
            <?php if ($auth->isAdmin()): ?>
                <a href="admin/dashboard.php" class="text-red-300 hover:text-red-200 block px-3 py-2 rounded-md text-base font-medium">Admin</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- Old code removed -->
<?php if (false): ?>
<nav class="bg-gray-800 border-b border-gray-700 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <h1 class="text-xl font-bold text-fivem-primary">FiveM UCP</h1>
                </div>
                <div class="hidden md:ml-6 md:flex md:space-x-8">
                    <a href="dashboard.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="characters.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-users mr-2"></i>Characters
                    </a>
                    <a href="inventory.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-boxes mr-2"></i>Inventory
                    </a>
                    <a href="tickets.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-ticket-alt mr-2"></i>Support
                    </a>
                    <a href="vehicles.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-car mr-2"></i>Vehicles
                    </a>
                    <a href="banking.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-university mr-2"></i>Banking
                    </a>
                    <a href="map.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-map mr-2"></i>Map
                    </a>
                    <a href="properties.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-home mr-2"></i>Properties
                    </a>
                    <a href="chat.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-comments mr-2"></i>Chat
                    </a>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="admin/dashboard.php" class="text-red-300 hover:text-red-200 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                            <i class="fas fa-cog mr-2"></i>Admin
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-sm text-gray-300">
                    Welcome, <span class="text-fivem-primary font-semibold"><?php echo $_SESSION['username']; ?></span>
                </div>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
    
    <!-- Mobile menu -->
    <div class="md:hidden" x-data="{ open: false }">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3" x-show="open">
            <a href="dashboard.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Dashboard</a>
            <a href="characters.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Characters</a>
            <a href="inventory.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Inventory</a>
            <a href="tickets.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Support</a>
            <a href="vehicles.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Vehicles</a>
            <a href="banking.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Banking</a>
            <a href="map.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Map</a>
            <a href="properties.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Properties</a>
            <a href="chat.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Chat</a>
            <?php if ($auth->isAdmin()): ?>
                <a href="admin/dashboard.php" class="text-red-300 hover:text-red-200 block px-3 py-2 rounded-md text-base font-medium">Admin</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- Remove old mobile menu -->
<?php if (false): ?>
    <!-- Mobile menu -->
    <div class="md:hidden" x-data="{ open: false }">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3" x-show="open">
            <a href="dashboard.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Dashboard</a>
            <a href="characters.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Characters</a>
            <a href="inventory.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Inventory</a>
            <a href="tickets.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Support</a>
            <a href="vehicles.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Vehicles</a>
            <a href="banking.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Banking</a>
            <a href="map.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Map</a>
            <a href="properties.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Properties</a>
            <a href="chat.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Chat</a>
            <?php if ($auth->isAdmin()): ?>
                <a href="admin/dashboard.php" class="text-red-300 hover:text-red-200 block px-3 py-2 rounded-md text-base font-medium">Admin</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
                    </a>
                    <a href="map.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-map mr-2"></i>Map
                    </a>
                    <a href="properties.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-home mr-2"></i>Properties
                    </a>
                    <a href="chat.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-comments mr-2"></i>Chat
                    </a>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="admin/dashboard.php" class="text-red-300 hover:text-red-200 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                            <i class="fas fa-cog mr-2"></i>Admin
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-sm text-gray-300">
                    Welcome, <span class="text-fivem-primary font-semibold"><?php echo $_SESSION['username']; ?></span>
                </div>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
    
    <!-- Mobile menu -->
    <div class="md:hidden" x-data="{ open: false }">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3" x-show="open">
            <a href="dashboard.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Dashboard</a>
            <a href="characters.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Characters</a>
            <a href="inventory.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Inventory</a>
            <a href="tickets.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Support</a>
            <a href="vehicles.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Vehicles</a>
            <a href="banking.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Banking</a>
            <a href="map.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Map</a>
            <a href="properties.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Properties</a>
            <a href="chat.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Chat</a>
            <?php if ($auth->isAdmin()): ?>
                <a href="admin/dashboard.php" class="text-red-300 hover:text-red-200 block px-3 py-2 rounded-md text-base font-medium">Admin</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<?php endif; ?>