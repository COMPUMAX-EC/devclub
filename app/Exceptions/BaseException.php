<?php

namespace App\Exceptions;

use Exception;

/**
 * Description of BaseException
 *
 * @author ariel
 */
class BaseException extends Exception
{
	private $context = null;
	private $error_code = null;

	public function __construct($message='', $error_code=null, $context=null)
	{
		parent::__construct($message);
		$this->context	  = $context;
		$this->error_code = $error_code;
	}

	public function setContext($data)
	{
		$this->context = $data;
		return $this;
	}
	
	public function setErrorCode($error_code)
	{
		$this->error_code = $error_code;
		return $this;
	}
	
	public function getErrorCode()
	{
		return $this->error_code;
	}
	
	public function getContext()
	{
		return $this->context;
	}
}
