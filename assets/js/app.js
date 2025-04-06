/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import $ from 'jquery';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import '@fontsource/lato/index.min.css';
import '@fontsource/lato/700.css';
import '@fontsource/lato/900.css';
import '../styles/app.css';

// Import component modules
import './components/forms.js';
import './components/selectize.js';
import './components/map.js';

// Additional global initialization code if needed
document.addEventListener('DOMContentLoaded', function() {
    // Global app initialization
    console.log('App initialized with modular structure');
}); 