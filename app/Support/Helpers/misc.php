<?php

use App\Support\Realm;
use Illuminate\Http\Request;

if (! function_exists('realm')) {

	/**
	 * Busca el REALM actual si existe uno
	 * @param Request|null $request
	 * @return type
	 */
	function realm(?Request $request = null)
	{
		return Realm::current($request);
	}

	function isRealmAdmin(?Request $request = null)
	{
		return Realm::isAdmin($request);
	}

	function isRealmCustomer(?Request $request = null)
	{
		return Realm::isCustomer($request);
	}
}

if (! function_exists('audit_log')) {
    /**
     * Alias conveniente para App\Support\Audit::log().
     *
     * @param  string      $action
     * @param  array       $context
     * @param  int|null    $targetUserId
     * @param  string|null $realm
     * @return void
     */
    function audit_log(
        string $action,
        array $context = [],
        ?int $targetUserId = null,
        ?string $realm = null
    ): void {
	 App\Support\Audit::log($action, $context, $targetUserId, $realm);
    }
}



if (! function_exists('env_any')) {
    /**
     * busca si tienes en true por lo menos uno de los env
     *
     * @param  string      $action
     * @param  array       $context
     * @param  int|null    $targetUserId
     * @param  string|null $realm
     * @return void
     */
    function env_any(...$params)
	{
		foreach($params as $env)
		{
			if(is_scalar($env))
			{
				if(env($env, true))
				{
					return true;
				}
			}
			elseif(is_array($env))
			{
				if(env_any(...$env))
				{
					return true;
				}
			}
			else
			{
				throw new Exception("ENV ERROR");
			}
		}
		
		return false;
    }
}