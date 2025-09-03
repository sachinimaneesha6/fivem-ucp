<footer class="border-t mt-auto theme-transition" 
        :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                    © 2025 FiveM UCP. All rights reserved.
                </div>
                <div class="flex space-x-4 mt-4 md:mt-0">
                    <a href="#" class="transition-colors theme-transition"
                       :class="darkMode ? 'text-gray-400 hover:text-white' : 'text-gray-600 hover:text-gray-900'">
                        <i class="fab fa-discord"></i>
                    </a>
                    <a href="#" class="transition-colors theme-transition"
                       :class="darkMode ? 'text-gray-400 hover:text-white' : 'text-gray-600 hover:text-gray-900'">
                        <i class="fab fa-twitter"></i>
                    </a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>