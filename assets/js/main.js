// =====================================================================
// FindPoint — shared site JS (loaded on every page via footer.php)
// Each block below checks the element exists first, since this same
// file loads on pages that don't have that element.
// =====================================================================

document.addEventListener('DOMContentLoaded', function () {

    // -------------------------------------------------------------
    // Register page: Individual / Institution toggle.
    // Switches which extra field shows, and relabels "Full Name" to
    // "Institution / Organisation Name" when Institution is picked.
    // -------------------------------------------------------------
    var typeIndividual = document.getElementById('typeIndividual');
    var typeInstitution = document.getElementById('typeInstitution');

    if (typeIndividual && typeInstitution) {
        var nameLabel = document.getElementById('fullNameLabel');
        var nameInput = document.getElementById('full_name');
        var institutionTypeWrap = document.getElementById('institutionTypeWrap');
        var institutionTypeSelect = document.getElementById('institution_type');

        function applyAccountType(type) {
            if (type === 'institution') {
                nameLabel.textContent = 'Institution / Organisation Name';
                nameInput.placeholder = 'e.g. ACK Jericho Church';
                institutionTypeWrap.classList.remove('d-none');
                institutionTypeSelect.setAttribute('required', 'required');
            } else {
                nameLabel.textContent = 'Full Name';
                nameInput.placeholder = 'e.g. Wanjiku Kamau';
                institutionTypeWrap.classList.add('d-none');
                institutionTypeSelect.removeAttribute('required');
            }
        }

        typeIndividual.addEventListener('change', function () { applyAccountType('individual'); });
        typeInstitution.addEventListener('change', function () { applyAccountType('institution'); });

        // Set the correct state on page load (handles browser back-button
        // restoring a previous selection, or a validation-error reload).
        applyAccountType(typeInstitution.checked ? 'institution' : 'individual');
    }

});
