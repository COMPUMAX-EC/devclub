<?php

namespace App\Models;

use App\Models\Concerns\HasDirectory;
use App\Models\Concerns\HasTranslatableJson;

class Model extends \Illuminate\Database\Eloquent\Model
{
	use HasDirectory;
    use HasTranslatableJson;

	public function toArray($translated=false)
	{
		$arr = parent::toArray();
		if($translated == false)
		{
			return $arr;
		}
		
		$keys = $this->getTranslatableKeys();
		foreach ($keys as $key)
		{
			$arr[$key] = $this->{$key};
		}
		
		foreach($arr as $key=>$val)
		{
			if($key=='coverages')
			{
//				print_r($this->{$key});
///				die(is_array($this->{$key})?'si':'no');
			}
			
			if($this->{$key} instanceof Model)
			{
				$arr[$key] = $this->{$key}->toArray($translated);
			}
			elseif(is_array($this->{$key}) || ($this->{$key} instanceof \Illuminate\Database\Eloquent\Collection))
			{
				foreach ($this->{$key} as $key2=>$val2)
				{
					if($val2 instanceof Model)
					{
						$arr[$key][$key2] = $val2->toArray($translated);
					}
				}
			}
		}

		return $arr;
	}
}
