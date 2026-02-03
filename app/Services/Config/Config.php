<?php

namespace App\Services\Config;


class Config
{
	private static $brandingWeb=null;
	public static function getBrandingWeb()
	{
		if(empty(self::$brandingWeb))
		{
			self::$brandingWeb = array_fill_keys(['favicon','favicon_claro','logo_header','logo_email','logo_invertido','imagen_login','texto_bienvenida'], null);
			self::$brandingWeb = array_merge(self::$brandingWeb, \App\Models\ConfigItem::searchByCategory('branding_web'));
		}
		
		return self::$brandingWeb;
	}
}
