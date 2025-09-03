class GTAMap {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.options = {
            center: [2048, 2048],
            zoom: -2,
            minZoom: -4,
            maxZoom: 3,
            ...options
        };
        this.map = null;
        this.characterMarker = null;
        
        // GTA V world bounds (actual game coordinates)
        this.GTA_BOUNDS = {
            minX: -3000,
            maxX: 4500,
            minY: -4500,
            maxY: 8000
        };
        
        // Map image dimensions
        this.MAP_SIZE = 4096;
        
        this.init();
    }
    
    init() {
        // Initialize Leaflet map with custom CRS
        this.map = L.map(this.containerId, {
            crs: L.CRS.Simple,
            center: this.options.center,
            zoom: this.options.zoom,
            minZoom: this.options.minZoom,
            maxZoom: this.options.maxZoom,
            zoomControl: true,
            attributionControl: false
        });
        
        // Map bounds for 4096x4096 image
        const bounds = [[0, 0], [this.MAP_SIZE, this.MAP_SIZE]];
        
        // Determine correct path for map image
        const currentPath = window.location.pathname;
        let mapImagePath;
        
        if (currentPath.includes('/admin/')) {
            mapImagePath = '../assets/images/map.png';
        } else {
            mapImagePath = './assets/images/map.png';
        }
        
        console.log('Loading map from:', mapImagePath);
        
        // Load map image directly
        L.imageOverlay(mapImagePath, bounds).addTo(this.map);
        
        this.map.fitBounds(bounds);
        
        // Add custom controls
        this.addCustomControls();
        
        // Add notable locations
        this.addNotableLocations();
        
        // Run calibration test
        this.testCoordinateConversion();
        
        // Add manual calibration helper
        window.calibrateMap = function(gtaX, gtaY, description = 'Test Location') {
            const mapCoords = gtaMap.convertGTACoords(gtaX, gtaY);
            console.log(`${description}: GTA(${gtaX}, ${gtaY}) -> Map(${mapCoords[1]}, ${mapCoords[0]})`);
            
            // Add temporary marker for testing
            const testMarker = L.marker(mapCoords, {
                icon: L.divIcon({
                    className: 'test-marker',
                    html: `<div class="w-8 h-8 bg-yellow-500 rounded-full border-2 border-white shadow-lg flex items-center justify-center">
                        <i class="fas fa-crosshairs text-white text-xs"></i>
                    </div>`,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                })
            }).addTo(gtaMap.map);
            
            testMarker.bindPopup(`
                <div class="text-center p-2">
                    <strong>${description}</strong><br>
                    <small>GTA: ${gtaX}, ${gtaY}</small><br>
                    <small>Map: ${mapCoords[1].toFixed(1)}, ${mapCoords[0].toFixed(1)}</small>
                </div>
            `);
            
            return testMarker;
        };
        
        console.log('Map calibration ready. Use calibrateMap(x, y, "Location Name") to test coordinates.');
    }
    
    testCoordinateConversion() {
        console.log('=== 🎯 Coordinate Calibration Test ===');
        
        // Test known GTA V locations for accuracy
        const testLocations = [
            { name: 'Map Center (0,0)', gta: { x: 0, y: 0 } },
            { name: 'Mission Row PD', gta: { x: 428.23, y: -984.28 } },
            { name: 'Sandy Shores Sheriff', gta: { x: 1853.18, y: 3686.63 } },
            { name: 'Paleto Bay Sheriff', gta: { x: -448.04, y: 6008.55 } },
            { name: 'Los Santos Airport', gta: { x: -1037.86, y: -2737.6 } },
            { name: 'Mount Chiliad', gta: { x: 501.77, y: 5593.86 } }
        ];
        
        testLocations.forEach(location => {
            const mapCoords = this.convertGTACoords(location.gta.x, location.gta.y);
            console.log(`📍 ${location.name}: GTA(${location.gta.x}, ${location.gta.y}) -> Map(${mapCoords[1].toFixed(1)}, ${mapCoords[0].toFixed(1)})`);
        });
        
        console.log('=== ✅ Calibration Test Complete ===');
    }
    
    addCustomControls() {
        // Custom zoom control
        const customZoomControl = L.control({ position: 'topright' });
        customZoomControl.onAdd = function(map) {
            const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
            div.innerHTML = `
                <a class="leaflet-control-zoom-in" href="#" title="Zoom in" role="button" aria-label="Zoom in">
                    <i class="fas fa-plus"></i>
                </a>
                <a class="leaflet-control-zoom-out" href="#" title="Zoom out" role="button" aria-label="Zoom out">
                    <i class="fas fa-minus"></i>
                </a>
            `;
            return div;
        };
        customZoomControl.addTo(this.map);
    }
    
    addNotableLocations() {
        // Load locations from JSON file
        fetch('assets/gta-locations.json')
            .then(response => response.json())
            .then(data => {
                // Add police stations
                data.police_stations?.forEach(location => {
                    this.addLocationMarker(location, '#3b82f6', 'fa-shield-alt');
                });
                
                // Add hospitals
                data.hospitals?.forEach(location => {
                    this.addLocationMarker(location, '#ef4444', 'fa-hospital');
                });
                
                // Add garages
                data.garages?.forEach(location => {
                    this.addLocationMarker(location, '#10b981', 'fa-warehouse');
                });
                
                // Add shops
                data.shops?.forEach(location => {
                    this.addLocationMarker(location, '#f59e0b', 'fa-shopping-cart');
                });
                
                // Add landmarks
                data.landmarks?.forEach(location => {
                    this.addLocationMarker(location, '#8b5cf6', 'fa-landmark');
                });
            })
            .catch(error => {
                console.error('Failed to load location data:', error);
                // Add default locations if JSON fails
                this.addDefaultLocations();
            });
    }
    
    addLocationMarker(location, color, icon) {
        const mapPos = this.convertGTACoords(location.coords.x, location.coords.y);
        
        const marker = L.marker(mapPos, {
            icon: L.divIcon({
                className: 'location-marker',
                html: `<div class="w-6 h-6 rounded-full border-2 border-white shadow-lg flex items-center justify-center" style="background-color: ${color}">
                    <i class="fas ${icon} text-white text-xs"></i>
                </div>`,
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            })
        }).addTo(this.map);
        
        marker.bindPopup(`
            <div class="text-center p-2">
                <strong class="text-white">${location.name}</strong><br>
                <small class="text-gray-300">${location.type}</small>
            </div>
        `);
    }
    
    addDefaultLocations() {
        const defaultLocations = [
            { name: 'LSPD Mission Row', coords: { x: 428.23, y: -984.28 }, color: '#3b82f6', icon: 'fa-shield-alt' },
            { name: 'Pillbox Medical', coords: { x: 298.68, y: -584.50 }, color: '#ef4444', icon: 'fa-hospital' },
            { name: 'Legion Square', coords: { x: 215.94, y: -810.05 }, color: '#10b981', icon: 'fa-landmark' },
            { name: 'Los Santos Customs', coords: { x: -362.71, y: -131.87 }, color: '#f59e0b', icon: 'fa-wrench' },
            { name: 'Diamond Casino', coords: { x: 1089.74, y: 206.30 }, color: '#8b5cf6', icon: 'fa-dice' }
        ];
        
        defaultLocations.forEach(location => {
            this.addLocationMarker(location, location.color, location.icon);
        });
    }
    
    addCharacterMarker(x, y, characterName) {
        // Convert GTA coordinates to map coordinates
        const mapPos = this.convertGTACoords(x, y);
        
        if (this.characterMarker) {
            this.map.removeLayer(this.characterMarker);
        }
        
        this.characterMarker = L.marker(mapPos, {
            icon: L.divIcon({
                className: 'character-marker',
                html: `<div class="w-12 h-12 bg-red-500 rounded-full border-4 border-white shadow-2xl flex items-center justify-center animate-pulse">
                    <i class="fas fa-user text-white text-lg"></i>
                </div>`,
                iconSize: [48, 48],
                iconAnchor: [24, 24]
            })
        }).addTo(this.map);
        
        this.characterMarker.bindPopup(`
            <div class="text-center p-4">
                <div class="w-12 h-12 bg-gradient-to-r from-fivem-primary to-yellow-500 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-user text-white"></i>
                </div>
                <strong class="text-white text-lg block mb-2">${characterName}</strong>
                <small class="text-gray-300 block mb-2">Last Known Location</small>
                <div class="text-xs text-gray-400 bg-gray-800 rounded px-2 py-1">
                    X: ${x.toFixed(2)}, Y: ${y.toFixed(2)}
                </div>
            </div>
        `);
        
        return mapPos;
    }
    
    convertGTACoords(gtaX, gtaY) {
        // Calibrated GTA V coordinate conversion for 4096x4096 map
        // These bounds are calibrated for accurate positioning
        
        const GTA_WORLD = {
            minX: -4000,  // Western boundary
            maxX: 4000,   // Eastern boundary
            minY: -4000,  // Southern boundary  
            maxY: 8000    // Northern boundary
        };
        
        // Calculate map position with proper scaling
        const mapX = ((gtaX - GTA_WORLD.minX) / (GTA_WORLD.maxX - GTA_WORLD.minX)) * this.MAP_SIZE;
        const mapY = this.MAP_SIZE - ((gtaY - GTA_WORLD.minY) / (GTA_WORLD.maxY - GTA_WORLD.minY)) * this.MAP_SIZE;
        
        console.log(`🗺️ Converting GTA coords (${gtaX}, ${gtaY}) to map coords (${mapX.toFixed(1)}, ${mapY.toFixed(1)})`);
        
        return [mapY, mapX];
    }
    
    centerOnCharacter() {
        if (this.characterMarker) {
            this.map.setView(this.characterMarker.getLatLng(), 0);
            this.characterMarker.openPopup();
        }
    }
    
    getCharacterCoords() {
        if (this.characterMarker) {
            const latlng = this.characterMarker.getLatLng();
            
            // Convert map coordinates back to GTA coordinates
            const GTA_WORLD = {
                minX: -4000, maxX: 4000,
                minY: -4000, maxY: 8000
            };
            
            const gtaX = GTA_WORLD.minX + (latlng.lng / this.MAP_SIZE) * (GTA_WORLD.maxX - GTA_WORLD.minX);
            const gtaY = GTA_WORLD.minY + ((this.MAP_SIZE - latlng.lat) / this.MAP_SIZE) * (GTA_WORLD.maxY - GTA_WORLD.minY);
            
            return { x: gtaX, y: gtaY };
        }
        return null;
    }
}

// Global map instance
window.GTAMap = GTAMap;