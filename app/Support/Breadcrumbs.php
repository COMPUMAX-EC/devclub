<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace App\Support;

/**
 * Description of Breadcrumbs
 *
 * @author ariel
 */
class Breadcrumbs
{
	private static $items = [];
	
	public static function add($title, $url='')
	{
		self::init();
		self::$items[] = ['title'=>$title, 'url'=>$url];
	}
	
	public static function getAll()
	{
		self::init();
		return self::$items;
	}
	
	public static function clear()
	{
		$items = [];
	}
	
	private static function init()
	{
		if(empty(self::$items))
		{
			$realm = realm();
			
			$route = match ($realm)
			{
				Realm::ADMIN => route('admin.home'),
				Realm::CUSTOMER => route('customer.home'),
				default => route('home'),
			};
			
			self::$items[] = ['title'=>'Home','url'=>$route];
		}
	}
}
