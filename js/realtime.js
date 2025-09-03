class RealtimeUpdater {
    constructor() {
        this.updateInterval = 30000; // 30 seconds
        this.isActive = true;
        this.init();
    }
    
    init() {
        // Start real-time updates
        this.startUpdates();
        
        // Handle page visibility
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopUpdates();
            } else {
                this.startUpdates();
            }
        });
    }
    
    startUpdates() {
        if (this.isActive) return;
        
        this.isActive = true;
        this.updateLoop();
    }
    
    stopUpdates() {
        this.isActive = false;
    }
    
    async updateLoop() {
        while (this.isActive) {
            try {
                await this.fetchServerStatus();
                await this.fetchNotifications();
            } catch (error) {
                console.error('Update error:', error);
            }
            
            await this.sleep(this.updateInterval);
        }
    }
    
    async fetchServerStatus() {
        try {
            const response = await fetch('api/server_status.php');
            const data = await response.json();
            
            if (data.players) {
                this.updatePlayerCount(data.players.online, data.players.max);
            }
            
            if (data.performance) {
                this.updatePerformanceMetrics(data.performance);
            }
            
            // Update theme-aware elements
            this.updateThemeElements();
        } catch (error) {
            console.error('Failed to fetch server status:', error);
        }
    }
    
    async fetchNotifications() {
        // Placeholder for notification system
        // Would fetch new tickets, messages, etc.
    }
    
    updatePlayerCount(online, max) {
        const elements = document.querySelectorAll('[data-player-count]');
        elements.forEach(el => {
            el.textContent = `${online}/${max}`;
        });
    }
    
    updatePerformanceMetrics(performance) {
        // Update CPU usage
        const cpuElements = document.querySelectorAll('[data-cpu-usage]');
        cpuElements.forEach(el => {
            el.textContent = `${performance.cpu}%`;
            const bar = el.parentElement.querySelector('.cpu-bar');
            if (bar) {
                bar.style.width = `${performance.cpu}%`;
            }
        });
        
        // Update memory usage
        const memoryElements = document.querySelectorAll('[data-memory-usage]');
        memoryElements.forEach(el => {
            el.textContent = `${performance.memory}%`;
            const bar = el.parentElement.querySelector('.memory-bar');
            if (bar) {
                bar.style.width = `${performance.memory}%`;
            }
        });
        
        // Update disk usage
        const diskElements = document.querySelectorAll('[data-disk-usage]');
        diskElements.forEach(el => {
            el.textContent = `${performance.disk}%`;
            const bar = el.parentElement.querySelector('.disk-bar');
            if (bar) {
                bar.style.width = `${performance.disk}%`;
            }
        });
    }
    
    updateThemeElements() {
        // Update any theme-specific real-time elements
        const isDark = document.documentElement.classList.contains('dark');
        
        // Update notification styles based on theme
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(notification => {
            if (isDark) {
                notification.classList.add('dark');
            } else {
                notification.classList.remove('dark');
            }
        });
    }
    
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    showNotification(title, message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = 'notification fixed top-4 right-4 max-w-sm border rounded-lg shadow-lg p-4 z-50 transform translate-x-full transition-all duration-300 theme-transition';
        
        const iconClass = {
            'info': 'fas fa-info-circle text-blue-400',
            'success': 'fas fa-check-circle text-green-400',
            'warning': 'fas fa-exclamation-triangle text-yellow-400',
            'error': 'fas fa-times-circle text-red-400'
        }[type] || 'fas fa-info-circle text-blue-400';
        
        // Apply theme classes
        const isDark = document.documentElement.classList.contains('dark');
        notification.classList.add(isDark ? 'bg-gray-800' : 'bg-white');
        notification.classList.add(isDark ? 'border-gray-700' : 'border-gray-200');
        
        notification.innerHTML = `
            <div class="flex items-start">
                <i class="${iconClass} mt-1 mr-3"></i>
                <div class="flex-1">
                    <h4 class="font-semibold theme-transition ${isDark ? 'text-white' : 'text-gray-900'}">${title}</h4>
                    <p class="text-sm theme-transition ${isDark ? 'text-gray-300' : 'text-gray-600'}">${message}</p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-2 transition-colors theme-transition ${isDark ? 'text-gray-400 hover:text-white' : 'text-gray-600 hover:text-gray-900'}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
}

// Initialize real-time updates
const realtimeUpdater = new RealtimeUpdater();

// Global notification function
window.showNotification = (title, message, type) => {
    realtimeUpdater.showNotification(title, message, type);
};