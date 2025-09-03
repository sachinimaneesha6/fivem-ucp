class ThemeManager {
    constructor() {
        this.darkMode = localStorage.getItem('darkMode') !== 'false';
        this.init();
    }
    
    init() {
        // Apply initial theme
        this.applyTheme(this.darkMode);
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (localStorage.getItem('darkMode') === null) {
                this.darkMode = e.matches;
                this.applyTheme(this.darkMode);
            }
        });
    }
    
    applyTheme(isDark) {
        localStorage.setItem('darkMode', isDark);
        document.documentElement.classList.toggle('dark', isDark);
        
        // Update body classes
        document.body.classList.remove('bg-gray-900', 'bg-gray-50', 'text-white', 'text-gray-900');
        document.body.classList.add(isDark ? 'bg-gray-900' : 'bg-gray-50');
        document.body.classList.add(isDark ? 'text-white' : 'text-gray-900');
        
        // Trigger Alpine.js reactivity if available
        if (window.Alpine) {
            // Force Alpine to re-evaluate
            const event = new CustomEvent('theme-changed', { detail: { darkMode: isDark } });
            document.dispatchEvent(event);
        }
    }
    
    toggle() {
        this.darkMode = !this.darkMode;
        this.applyTheme(this.darkMode);
        return this.darkMode;
    }
}

// Initialize theme manager
window.themeManager = new ThemeManager();

// Global theme toggle function
window.toggleTheme = () => {
    return window.themeManager.toggle();
};