<?php

return [
    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    */

    // Delay en milisegundos para autosave de campos (ej: coberturas)
    'autosave_delay_ms' => (int) env('UI_AUTOSAVE_DELAY_MS', 700),
	
	'per_page'=>
	[
		'short'  => env('PER_PAGE_SHORT', 5),
		'medium' => env('PER_PAGE_MEDIUM', 10),
		'large'  => env('PER_PAGE_LARGE', 15),
	],
];
