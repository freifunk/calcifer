import { Tooltip } from 'bootstrap';
import $ from 'jquery';

function setupFormValidation() {
    // Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new Tooltip(tooltipTriggerEl);
    });

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', function(event) {
            let isValid = true;
            
            if (!form.checkValidity()) {
                isValid = false;
            }
            
            const locationSelectize = $('#event_location')[0]?.selectize;
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
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', setupFormValidation);

// Export function
export { setupFormValidation }; 