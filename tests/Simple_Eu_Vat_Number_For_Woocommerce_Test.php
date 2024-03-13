<?php

class Simple_Eu_Vat_Number_For_Woocommerce_Test extends \PHPUnit\Framework\TestCase
{
    public function testIsVatExempt(){
        // Crear un objeto de prueba para la clase WC_Customer
        $customer = $this->createMock('WC_Customer');

        // Establecer el valor devuelto para el método get_id()
        $customer->method('get_id')->willReturn(1);

        // Establecer el valor devuelto para get_user_meta()
        add_filter('get_user_meta', function($null, $object_id, $meta_key, $single) {
            if ($object_id == 1 && $meta_key == 'is_vat_exempt' && $single) {
                return 'yes';
            }
        }, 10, 4);

        // Crear una instancia de la clase que estás probando
        $vat = new Simple_EU_VAT_Number_For_WooCommerce();

        // Llamar al método que estás probando y almacenar el resultado
        $result = $vat->svnfw_set_vat_exemption(false, $customer);

        // Comprobar que el método devolvió el resultado esperado
        $this->assertTrue($result);
    }

}
