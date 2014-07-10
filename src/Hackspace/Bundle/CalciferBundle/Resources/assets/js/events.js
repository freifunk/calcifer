/**
 * Created by tim on 06.07.14.
 */

// initializing with settings

function addGeoCoordinates(ev) {
    return false;
}
var map = null;
$(document).ready(function () {
    if ($('.icon.link').length > 0) {
        $('.icon.link').popup();
    }

    if (jQuery('input[type=datetime]').length > 0) {
        jQuery('input[type=datetime]').datetimepicker({lang: 'de', format: 'Y-m-d H:i'});
    }

    if (jQuery('#map').length == 1) {
        $('.add_geo').click(addGeoCoordinates);
        map = L.map('map');

        // add an OpenStreetMap tile layer
        L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        map.setView([51.505, -0.09], 0);

        L.Icon.Default.imagePath = '/css/images';
        var popup = L.popup();

        var marker = L.marker();

        function onMapClick(e) {
            marker
                .setLatLng(e.latlng)
                //.setContent("You clicked the map at " + e.latlng.toString())
                .addTo(map);
        }

        map.on('click', onMapClick);

        $('.geo.chooser').modal('setting', {
            closable: false,
            onApprove: function () {
                var coords = marker.getLatLng();
                $('input[name=location_lat]').val(coords.lat);
                $('input[name=location_lon]').val(coords.lng);
                $('input[name=location]').css('margin-bottom', '3.2rem');
                $('span.coords').text('Folgende Koordinaten sind angegeben: lat:' + coords.lat + ', lon:' + coords.lng);
            },
            onDeny: function () {

            },
            onVisible: function () {
                map.invalidateSize(true);
                var lat = parseFloat($('input[name=location_lat]').val());
                var lon = parseFloat($('input[name=location_lon]').val());
                if ((lat > 0) && (lon > 0)) {
                    map.setView([lat, lon], 16);
                    var latlng = new L.LatLng(lat, lon);
                    marker.setLatLng(latlng);
                    marker.addTo(map);
                } else {
                    map.locate({setView: true});
                }
            }
        }).modal('attach events', '.add_geo', 'show');
    }
});
