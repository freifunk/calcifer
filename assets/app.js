/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import $ from 'jquery';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap';
import {Tooltip, Modal} from 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import '@fontsource/lato/index.min.css';
import '@fontsource/lato/700.css';
import '@fontsource/lato/900.css';
import '@selectize/selectize';
import '@selectize/selectize/dist/css/selectize.bootstrap5.css';
import './styles/app.css';

// Leaflet imports
import L from 'leaflet';
import 'leaflet/dist/leaflet.min.css';



document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new Tooltip(tooltipTriggerEl);
    });
});

$("#event_tags").selectize({
    diacritics: true,
    valueField: 'name',
    labelField: 'name',
    searchField: 'name',
    create: true,
    load: function (query, callback) {
        if (!query.length) return callback();
        $.ajax({
            url: "/tags/",
            type: "GET",
            dataType: 'json',
            data: {
                q: query
            },
            headers: {
                'Accept': 'application/json'
            },
            error: function () {
                callback();
            },
            success: function (res) {
                callback(res.results);
            }
        });
    }
});

$("#event_location").selectize({
    create: true,
    createOnBlur: true,
    diacritics: true,
    maxItems: 1,
    valueField: 'name',
    labelField: 'name',
    searchField: 'name',
    render: {
        option: function(item, escape) {
            return '<div>' +
                '<span class="name">' + escape(item.name) + '</span>' +
                (item.address ? '<span class="text-muted ms-2">' + escape(item.address) + '</span>' : '') +
                '</div>';
        }
    },
    onInitialize: function() {
        var $wrapper = $(this.$wrapper);
        var $feedback = $wrapper.parent().find('.invalid-feedback');
        
        if (!this.getValue()) {
            if ($('#event_location').data('required')) {
                $wrapper.addClass('is-invalid');
            }
        }
    },
    load: function(query, callback) {
        if (!query.length) return callback();
        
        $.ajax({
            url: '/orte/',
            type: 'GET',
            dataType: 'json',
            headers: {
                'Accept': 'application/json'
            },
            data: {
                q: query
            },
            error: function() {
                callback();
            },
            success: function(res) {
                callback(res.results);
            }
        });
    },
    onChange: function(value) {
        if (!value || value.trim() === '') {
            $('#location_lat, #location_lon').val('');
            $('#location_details').addClass('d-none');
            
            if ($('#event_location').data('required')) {
                $(this.$wrapper).addClass('is-invalid');
            }
            
            // Karte zurücksetzen, falls sie existiert
            if (map !== null) {
                map = null;
            }
            
            return;
        }

        $(this.$wrapper).removeClass('is-invalid');
        
        const selectedItem = this.options[value];
        if (selectedItem && selectedItem.lat && selectedItem.lon) {
            $('#location_lat').val(selectedItem.lat);
            $('#location_lon').val(selectedItem.lon);
            
            $('.location-name').text(selectedItem.name);
            $('.location-coordinates').text(`Lat: ${selectedItem.lat}, Lon: ${selectedItem.lon}`);
            $('#location_details').removeClass('d-none');
            
            // Karte zurücksetzen, falls sie existiert
            if (map !== null) {
                map = null;
            }
        }
    },
    onType: function(str) {
        if (str && str.trim() !== '') {
            $(this.$wrapper).removeClass('is-invalid');
        }
    }
});

var styleEl = document.getElementById('selectize-validation-styles');
if (!styleEl) {
    styleEl = document.createElement('style');
    styleEl.id = 'selectize-validation-styles';
    styleEl.textContent = `
        /* Selectize Validierungsstile */
        .selectize-control.is-invalid .selectize-input {
            border-color: #dc3545 !important;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .was-validated .selectize-control.is-invalid ~ .invalid-feedback {
            display: block;
        }
    `;
    document.head.appendChild(styleEl);
}

document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', function(event) {
            let isValid = true;
            
            if (!form.checkValidity()) {
                isValid = false;
            }
            
            const locationSelectize = $('#event_location')[0].selectize;
            if (locationSelectize && $('#event_location').data('required')) {
                if (!locationSelectize.getValue()) {
                    $(locationSelectize.$wrapper).addClass('is-invalid');
                    isValid = false;
                }
            }
            
            if (!isValid) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
});

// Globale Variablen für die Karte
let map = null;
let marker = null;

// Karten-Funktionalität
document.addEventListener('DOMContentLoaded', function() {
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
});

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

(() => {
    'use strict'

    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    const forms = document.querySelectorAll('.needs-validation')

    // Loop over them and prevent submission
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }

            form.classList.add('was-validated')
        }, false)
    })
})()
