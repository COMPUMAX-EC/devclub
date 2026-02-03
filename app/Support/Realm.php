<?php

namespace App\Support;

use Illuminate\Http\Request;

final class Realm
{
    public const ADMIN    = 'admin';
    public const CUSTOMER = 'customer';

    /** Clave del atributo en el Request */
    public const ATTRIBUTE = '_current_realm';

    /** Valor cacheado (p. ej. útil si alguien lo consulta sin pasar Request) */
    protected static ?string $current = null;

    /** Lista de realms válidos */
    public static function all(): array
    {
        return [
            self::ADMIN,
            self::CUSTOMER,
        ];
    }

    public static function isValid(?string $name): bool
    {
        return in_array($name, self::all(), true);
    }

    /** Setea el valor actual (se usa desde el middleware) */
    public static function setCurrent(?string $name): void
    {
        self::$current = self::isValid($name) ? $name : null;
    }

    /**
     * Obtiene el realm actual desde el Request (si se pasa)
     * o desde el valor cacheado (si no hay Request).
     */
    public static function current(?Request $request = null): ?string
    {
		if(empty(self::$current))
		{
			self::$current = null;

			if(empty($request))
			{
				$request = request();
			}

			if ($request instanceof Request)
			{
				$val = $request->attributes->get(self::ATTRIBUTE);

				if(!self::isValid($val))
				{
					$val = null;
				}

				self::$current = $val;
			}
		}
        return self::$current;
    }
	
    public static function isAdmin(?Request $request = null): ?bool
	{
		return self::current($request) == Realm::ADMIN;
	}

    public static function isCustomer(?Request $request = null): ?bool
	{
		return self::current($request) == Realm::CUSTOMER;
	}

}
