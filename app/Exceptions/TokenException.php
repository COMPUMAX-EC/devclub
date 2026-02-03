<?php

namespace App\Exceptions;


/**
 * Description of TokenError
 *
 * @author ariel
 */
class TokenException extends BaseException
{
	
	//put your code here
	public function __construct($message='', $error_code=null, $context=null)
	{
		parent::__construct($message, $error_code, $context);
	}
}
