import $ from 'jquery';
import '@selectize/selectize';
import '@selectize/selectize/dist/css/selectize.bootstrap5.css';

function setupSelectize() {
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
                if (typeof map !== 'undefined' && map !== null) {
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
                if (typeof map !== 'undefined' && map !== null) {
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
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', setupSelectize);

// Export function
export { setupSelectize }; 