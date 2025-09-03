<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();

// Create chat table if it doesn't exist
$create_chat_table = "CREATE TABLE IF NOT EXISTS ucp_chat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
)";
$db->exec($create_chat_table);

// Get recent messages
$messages_query = "SELECT * FROM ucp_chat ORDER BY created_at DESC LIMIT 50";
$messages_stmt = $db->prepare($messages_query);
$messages_stmt->execute();
$messages = array_reverse($messages_stmt->fetchAll(PDO::FETCH_ASSOC));

$page_title = 'Community Chat';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">
            <i class="fas fa-comments text-green-500 mr-3"></i>Community Chat
        </h1>
        <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Connect with other players in real-time</p>
    </div>
    
    <div class="rounded-2xl border overflow-hidden shadow-2xl theme-transition" 
         :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
        <!-- Chat Header -->
        <div class="px-6 py-4 border-b theme-transition" :class="darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200'">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-emerald-500 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-comments text-white"></i>
                    </div>
                    <div>
                        <h2 class="font-bold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">Live Chat</h2>
                        <p class="text-sm theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Community discussion</p>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                    <span class="text-sm font-medium text-green-500">Online</span>
                    <span class="ml-3 text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'" id="user-count">Loading...</span>
                </div>
            </div>
        </div>
        
        <!-- Chat Messages -->
        <div id="chat-messages" class="h-96 overflow-y-auto p-6 space-y-4 theme-transition" :class="darkMode ? 'bg-gray-800' : 'bg-white'">
            <?php if (empty($messages)): ?>
                <div class="text-center py-12">
                    <div class="w-16 h-16 bg-gradient-to-r from-gray-400 to-gray-500 rounded-full flex items-center justify-center mx-auto mb-4 opacity-50">
                        <i class="fas fa-comments text-white text-xl"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'">No Messages Yet</h3>
                    <p class="theme-transition" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">Be the first to start the conversation!</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="flex items-start space-x-3 animate-fade-in">
                        <div class="w-10 h-10 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-full flex items-center justify-center flex-shrink-0 shadow-md">
                            <i class="fas fa-user text-white text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 mb-1">
                                <span class="font-semibold theme-transition" :class="darkMode ? 'text-white' : 'text-gray-900'"><?php echo htmlspecialchars($message['username']); ?></span>
                                <span class="text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-500'"><?php echo date('g:i A', strtotime($message['created_at'])); ?></span>
                            </div>
                            <div class="rounded-lg p-3 theme-transition" :class="darkMode ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-800'">
                                <p class="text-sm break-words"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Chat Input -->
        <div class="px-6 py-4 border-t theme-transition" :class="darkMode ? 'bg-gray-700 border-gray-600' : 'bg-gray-50 border-gray-200'">
            <form id="chat-form" class="flex space-x-3">
                <div class="flex-1 relative">
                    <input type="text" id="message-input" maxlength="500" required
                           class="w-full px-4 py-3 rounded-xl border transition-all duration-200 theme-transition focus:ring-2 focus:ring-fivem-primary focus:border-transparent"
                           :class="darkMode ? 'bg-gray-600 border-gray-500 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'"
                           placeholder="Type your message...">
                    <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                        <span id="char-count" class="text-xs theme-transition" :class="darkMode ? 'text-gray-500' : 'text-gray-400'">0/500</span>
                    </div>
                </div>
                <button type="submit" id="send-button"
                        class="px-6 py-3 bg-gradient-to-r from-fivem-primary to-yellow-500 hover:from-yellow-500 hover:to-fivem-primary text-white font-semibold rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
            <p class="text-xs theme-transition mt-3 flex items-center" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">
                <i class="fas fa-shield-alt mr-2 text-green-500"></i>
                Be respectful and follow community guidelines
            </p>
        </div>
    </div>
</div>

<script>
class RealtimeChat {
    constructor() {
        this.lastMessageId = <?php echo !empty($messages) ? max(array_column($messages, 'id')) : 0; ?>;
        this.chatContainer = document.getElementById('chat-messages');
        this.messageInput = document.getElementById('message-input');
        this.sendButton = document.getElementById('send-button');
        this.charCount = document.getElementById('char-count');
        this.userCount = document.getElementById('user-count');
        this.isScrolledToBottom = true;
        this.updateInterval = null;
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.startRealtimeUpdates();
        this.scrollToBottom();
        this.updateUserCount();
    }
    
    setupEventListeners() {
        // Form submission
        document.getElementById('chat-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
        
        // Character counter
        this.messageInput.addEventListener('input', () => {
            const length = this.messageInput.value.length;
            this.charCount.textContent = `${length}/500`;
            if (length > 450) {
                this.charCount.className = 'text-red-500 text-xs font-bold';
            } else {
                this.charCount.className = 'text-xs theme-transition';
            }
        });
        
        // Enter key to send
        this.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Scroll detection
        this.chatContainer.addEventListener('scroll', () => {
            const { scrollTop, scrollHeight, clientHeight } = this.chatContainer;
            this.isScrolledToBottom = scrollTop + clientHeight >= scrollHeight - 10;
        });
        
        // Page visibility handling
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopUpdates();
            } else {
                this.startRealtimeUpdates();
            }
        });
    }
    
    async sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message || message.length === 0) return;
        
        this.sendButton.disabled = true;
        this.sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        try {
            const formData = new FormData();
            formData.append('message', message);
            
            const response = await fetch('api/send_message.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            if (result.success) {
                this.messageInput.value = '';
                this.charCount.textContent = '0/500';
                this.charCount.className = 'text-xs theme-transition';
                // Immediately fetch new messages
                setTimeout(() => this.fetchNewMessages(), 100);
            } else {
                this.showError(result.error || 'Failed to send message');
            }
        } catch (error) {
            console.error('Send message error:', error);
            this.showError('Network error occurred');
        } finally {
            this.sendButton.disabled = false;
            this.sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
        }
    }
    
    async fetchNewMessages() {
        try {
            const response = await fetch(`api/chat_messages.php?since=${this.lastMessageId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.messages && data.messages.length > 0) {
                data.messages.forEach(message => {
                    this.addMessageToChat(message);
                    this.lastMessageId = Math.max(this.lastMessageId, parseInt(message.id));
                });
                
                if (this.isScrolledToBottom) {
                    this.scrollToBottom();
                }
            }
        } catch (error) {
            console.error('Failed to fetch messages:', error);
        }
    }
    
    addMessageToChat(message) {
        // Remove empty state if it exists
        const emptyState = this.chatContainer.querySelector('.text-center.py-12');
        if (emptyState) {
            emptyState.remove();
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'flex items-start space-x-3 animate-fade-in';
        messageDiv.setAttribute('data-message-id', message.id);
        
        const isDark = document.documentElement.classList.contains('dark');
        const bubbleClass = isDark ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-800';
        const nameClass = isDark ? 'text-white' : 'text-gray-900';
        const timeClass = isDark ? 'text-gray-500' : 'text-gray-500';
        
        messageDiv.innerHTML = `
            <div class="w-10 h-10 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-full flex items-center justify-center flex-shrink-0 shadow-md">
                <i class="fas fa-user text-white text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center space-x-2 mb-1">
                    <span class="font-semibold ${nameClass}">${this.escapeHtml(message.username)}</span>
                    <span class="text-xs ${timeClass}">${this.formatTime(message.created_at)}</span>
                </div>
                <div class="rounded-lg p-3 ${bubbleClass}">
                    <p class="text-sm break-words">${this.escapeHtml(message.message).replace(/\n/g, '<br>')}</p>
                </div>
            </div>
        `;
        
        this.chatContainer.appendChild(messageDiv);
        
        // Remove old messages if too many (keep last 100)
        const messages = this.chatContainer.querySelectorAll('[data-message-id]');
        if (messages.length > 100) {
            messages[0].remove();
        }
    }
    
    scrollToBottom() {
        this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatTime(timestamp) {
        return new Date(timestamp).toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true 
        });
    }
    
    showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 animate-fade-in';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle mr-2"></i>${message}`;
        document.body.appendChild(errorDiv);
        setTimeout(() => {
            errorDiv.style.opacity = '0';
            setTimeout(() => errorDiv.remove(), 300);
        }, 3000);
    }
    
    showSuccess(message) {
        const successDiv = document.createElement('div');
        successDiv.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 animate-fade-in';
        successDiv.innerHTML = `<i class="fas fa-check-circle mr-2"></i>${message}`;
        document.body.appendChild(successDiv);
        setTimeout(() => {
            successDiv.style.opacity = '0';
            setTimeout(() => successDiv.remove(), 300);
        }, 3000);
    }
    
    async updateUserCount() {
        try {
            const response = await fetch('api/server_status.php');
            if (!response.ok) return;
            
            const data = await response.json();
            if (data.players) {
                this.userCount.textContent = `${data.players.online} online`;
            }
        } catch (error) {
            this.userCount.textContent = 'Unknown';
        }
    }
    
    startRealtimeUpdates() {
        if (this.updateInterval) return;
        
        // Fetch new messages every 2 seconds
        this.updateInterval = setInterval(() => {
            if (!document.hidden) {
                this.fetchNewMessages();
            }
        }, 2000);
        
        // Update user count every 30 seconds
        setInterval(() => {
            if (!document.hidden) {
                this.updateUserCount();
            }
        }, 30000);
    }
    
    stopUpdates() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }
}

// Initialize real-time chat when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.realtimeChat = new RealtimeChat();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.realtimeChat) {
        window.realtimeChat.stopUpdates();
    }
});
</script>

<style>
.animate-fade-in {
    animation: fadeInUp 0.3s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Enhanced scrollbar for chat */
#chat-messages::-webkit-scrollbar {
    width: 6px;
}

#chat-messages::-webkit-scrollbar-track {
    background: transparent;
}

#chat-messages::-webkit-scrollbar-thumb {
    background: #6b7280;
    border-radius: 3px;
}

#chat-messages::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

.dark #chat-messages::-webkit-scrollbar-thumb {
    background: #4b5563;
}

.dark #chat-messages::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
}
</style>

<?php include 'includes/footer.php'; ?>