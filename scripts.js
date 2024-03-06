jQuery(document).ready(function($) {
    console.log('Script cargado'); // Verifica que el script se carga

    var countryInput = $('select#billing_country');
    console.log('País seleccionado inicialmente: ' + countryInput.val()); // Verifica el país seleccionado inicialmente

    countryInput.change(function() {
        var selectedCountry = $(this).val();
        console.log('País seleccionado: ' + selectedCountry); // Verifica el país seleccionado al cambiar

        var allowedCountries = svnfw_settings.allowed_countries;
        console.log('Países permitidos: ' + allowedCountries); // Verifica los países permitidos

        if (allowedCountries.includes(selectedCountry)) {
            $('input#vat_number').closest('.form-row').show();
        } else {
            $('input#vat_number').closest('.form-row').hide();
        }
    });

    // Llamar a la función de cambio inmediatamente después de la carga de la página
    countryInput.change();
});
