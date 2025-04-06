import L from 'leaflet';
import 'leaflet/dist/leaflet.min.css';
import { Modal } from 'bootstrap';
import $ from 'jquery';

// Überschreibe die Standard-Icon-URLs mit absoluten Pfaden
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconUrl: '/images/leaflet/marker-icon.png',
  iconRetinaUrl: '/images/leaflet/marker-icon-2x.png',
  shadowUrl: '/images/leaflet/marker-shadow.png'
});

// Globale Variablen für die Karte
let map = null;
let marker = null;

// Map initialization function
function initializeMap() {
    // Always make sure any existing map is properly destroyed
    if (map) {
        map.remove();
        map = null;
        marker = null;
    }
    
    // Make sure the map container exists
    const mapContainer = document.getElementById('map');
    if (!mapContainer) {
        return;
    }
    
    try {
        // Create map with explicit defaults
        map = L.map('map', {
            center: [51.1657, 10.4515],
            zoom: 6
        });
        
        // Add the tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        // Get coordinates from form or use defaults
        let lat = parseFloat($('#location_lat').val());
        let lon = parseFloat($('#location_lon').val());
        
        // Ensure we have valid coordinates
        if (isNaN(lat) || isNaN(lon)) {
            lat = 51.1657; // Deutschland-Zentrum
            lon = 10.4515;
        }
        
        let zoom = (lat === 51.1657 && lon === 10.4515) ? 6 : 15;
        
        map.setView([lat, lon], zoom);
        
        // Add marker
        marker = L.marker([lat, lon], {
            draggable: true
        }).addTo(map);
        
        // Update coordinates on marker drag
        marker.on('dragend', function() {
            let pos = marker.getLatLng();
            $('#modal-coordinates').text(`Lat: ${pos.lat.toFixed(6)}, Lon: ${pos.lng.toFixed(6)}`);
        });
        
        // Update marker position on map click
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            $('#modal-coordinates').text(`Lat: ${e.latlng.lat.toFixed(6)}, Lon: ${e.latlng.lng.toFixed(6)}`);
        });
        
        // Force map to recalculate its container size
        map.invalidateSize(true);
        
    } catch (error) {
        // Keep just one error log for critical errors
        console.error('Error initializing map:', error);
    }
}

// Karten-Funktionalität
function setupMapEventHandlers() {
    // Öffnen des Geo-Modals
    $(document).off('click', '.add_geo, .edit-on-map').on('click', '.add_geo, .edit-on-map', function(e) {
        e.preventDefault();
        
        // First check if modal actually exists
        const modalElement = document.getElementById('geoChooserModal');
        if (!modalElement) {
            return;
        }
        
        // Completely recreate the map container element
        const modalBody = document.getElementById('modal-body');
        if (modalBody) {
            // Remove any existing map element
            const existingMap = document.getElementById('map');
            if (existingMap) {
                existingMap.remove();
            }
            
            // Recreate the content of the modal body
            modalBody.innerHTML = `
                <p class="text-muted">Klicke auf die Karte, um den Standort zu setzen oder ziehe den Marker an die gewünschte Position.</p>
                <div id="map" style="height: 400px; width: 100%;"></div>
                <div class="mt-2">
                    <small class="text-muted" id="modal-coordinates"></small>
                </div>
            `;
        }
        
        // Modal öffnen
        const geoModal = new Modal(modalElement);
        geoModal.show();
        
        // Manually trigger the map initialization after a short delay
        setTimeout(() => {
            initializeMap();
        }, 500);
    });
    
    // Backup event listener for modal shown
    $('#geoChooserModal').off('shown.bs.modal').on('shown.bs.modal', function() {
        // This is just a backup, initialization is handled by the timeout above
    });

    // OK button handler to save coordinates
    $(document).off('click', '#geoChooserModal .btn-primary.ok').on('click', '#geoChooserModal .btn-primary.ok', function() {
        if (!marker) return;
        
        // Koordinaten speichern
        let position = marker.getLatLng();
        let lat = position.lat.toFixed(6);
        let lon = position.lng.toFixed(6);
        
        // In Formular übertragen
        $('#location_lat').val(lat);
        $('#location_lon').val(lon);
        
        // UI aktualisieren
        $('.location-coordinates').text(`Lat: ${lat}, Lon: ${lon}`);
        
        // Ortsname aktualisieren falls nötig
        if ($('.location-name').text().trim() === '') {
            $('.location-name').text($('#event_location').val() || 'Ausgewählter Standort');
        }
        
        // Details-Bereich anzeigen
        $('#location_details').removeClass('d-none');
        
        // Modal schließen
        const modalElement = document.getElementById('geoChooserModal');
        const modal = Modal.getInstance(modalElement);
        modal.hide();
    });

    // Cleanup when modal is closed
    $('#geoChooserModal').off('hidden.bs.modal').on('hidden.bs.modal', function() {
        if (map) {
            map.remove();
            map = null;
            marker = null;
        }
    });
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', setupMapEventHandlers);

// Export functions for use in other modules
export { initializeMap, setupMapEventHandlers }; 