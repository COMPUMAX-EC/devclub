<?php
// config/company_branding.php
declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Branding por defecto de empresas
    |--------------------------------------------------------------------------
    |
    | Cada clave apunta a un ConfigItem (category + token) desde el que se
    | obtendrá el valor por defecto. Los colores deben tener tipo "color"
    | y el logo un archivo asociado.
    |
    */

	'category' => 'branding',

	// Color de texto oscuro
	'text_dark' => 'company_branding_text_dark',

	// Color de fondo claro
	'bg_light' => 'company_branding_bg_light',

	// Color de texto claro
	'text_light' => 'company_branding_text_light',

	// Color de fondo oscuro
	'bg_dark' => 'company_branding_bg_dark',

	// Logo por defecto
	'logo' => 'company_branding_logo',

];
