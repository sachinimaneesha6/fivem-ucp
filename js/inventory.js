class InventoryManager {
    constructor() {
        this.selectedItem = null;
        this.draggedItem = null;
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.enhanceTooltips();
    }
    
    setupEventListeners() {
        // Add click handlers for inventory slots
        document.querySelectorAll('.inventory-slot').forEach(slot => {
            slot.addEventListener('click', (e) => {
                this.selectSlot(slot);
            });
            
            // Add drag and drop functionality (for future use)
            slot.addEventListener('dragstart', (e) => {
                this.handleDragStart(e, slot);
            });
            
            slot.addEventListener('dragover', (e) => {
                e.preventDefault();
            });
            
            slot.addEventListener('drop', (e) => {
                this.handleDrop(e, slot);
            });
        });
    }
    
    selectSlot(slot) {
        // Remove previous selection
        document.querySelectorAll('.inventory-slot').forEach(s => {
            s.classList.remove('ring-2', 'ring-fivem-primary');
        });
        
        // Add selection to clicked slot
        slot.classList.add('ring-2', 'ring-fivem-primary');
        this.selectedItem = slot;
        
        // Show item details if available
        const itemData = this.getSlotItemData(slot);
        if (itemData) {
            this.showItemDetails(itemData);
        }
    }
    
    getSlotItemData(slot) {
        const itemName = slot.dataset.itemName;
        const itemAmount = slot.dataset.itemAmount;
        const itemType = slot.dataset.itemType;
        
        if (itemName) {
            return {
                name: itemName,
                amount: itemAmount,
                type: itemType
            };
        }
        return null;
    }
    
    showItemDetails(itemData) {
        // Create or update item details panel
        let detailsPanel = document.getElementById('item-details-panel');
        if (!detailsPanel) {
            detailsPanel = this.createDetailsPanel();
        }
        
        detailsPanel.innerHTML = `
            <div class="p-4">
                <h3 class="text-lg font-bold text-white mb-2">${this.formatItemName(itemData.name)}</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Amount:</span>
                        <span class="text-white font-medium">${itemData.amount}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Type:</span>
                        <span class="text-white font-medium">${itemData.type}</span>
                    </div>
                </div>
            </div>
        `;
        
        detailsPanel.style.display = 'block';
    }
    
    createDetailsPanel() {
        const panel = document.createElement('div');
        panel.id = 'item-details-panel';
        panel.className = 'fixed top-20 right-4 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-50 min-w-64';
        panel.style.display = 'none';
        document.body.appendChild(panel);
        return panel;
    }
    
    formatItemName(name) {
        return name.split('_').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    }
    
    enhanceTooltips() {
        // Enhanced tooltip positioning
        document.querySelectorAll('.inventory-slot').forEach(slot => {
            slot.addEventListener('mouseenter', (e) => {
                const tooltip = slot.querySelector('.tooltip');
                if (tooltip) {
                    this.positionTooltip(tooltip, slot);
                }
            });
        });
    }
    
    positionTooltip(tooltip, slot) {
        const rect = slot.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        // Check if tooltip would go off screen
        if (rect.top - tooltipRect.height < 10) {
            // Show below instead of above
            tooltip.classList.remove('bottom-full', 'mb-2');
            tooltip.classList.add('top-full', 'mt-2');
        }
        
        if (rect.left + tooltipRect.width > window.innerWidth - 10) {
            // Align to right edge
            tooltip.classList.remove('left-1/2', '-translate-x-1/2');
            tooltip.classList.add('right-0');
        }
    }
    
    handleDragStart(e, slot) {
        this.draggedItem = slot;
        slot.style.opacity = '0.5';
    }
    
    handleDrop(e, slot) {
        e.preventDefault();
        if (this.draggedItem && this.draggedItem !== slot) {
            // Future: Implement item moving/swapping
            console.log('Move item from', this.draggedItem.dataset.slot, 'to', slot.dataset.slot);
        }
        
        if (this.draggedItem) {
            this.draggedItem.style.opacity = '1';
            this.draggedItem = null;
        }
    }
}

// Item rarity colors
const ITEM_RARITIES = {
    'common': '#9ca3af',
    'uncommon': '#22c55e',
    'rare': '#3b82f6',
    'epic': '#a855f7',
    'legendary': '#f59e0b',
    'mythic': '#ef4444'
};

// Item category icons
const ITEM_ICONS = {
    'weapon': 'fa-gun',
    'food': 'fa-utensils',
    'drink': 'fa-glass-water',
    'tool': 'fa-wrench',
    'key': 'fa-key',
    'phone': 'fa-mobile-alt',
    'card': 'fa-id-card',
    'money': 'fa-dollar-sign',
    'drug': 'fa-pills',
    'misc': 'fa-cube'
};

// Initialize inventory manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.inventory-slot')) {
        window.inventoryManager = new InventoryManager();
    }
});