jQuery(document).ready(function($) {
    //Country selected
    var countryInput = $('select#billing_country');
    countryInput.change(function() {
        var selectedCountry = $(this).val();
        var allowedCountries = Object.values(svnfw_settings.allowed_countries);
        if (allowedCountries.includes(selectedCountry)) {
            $('input#vat_number').closest('.form-row').show();
        } else {
            $('input#vat_number').closest('.form-row').hide();
        }
    });
    countryInput.change();


});
