<?php

namespace App\Models\Concerns;

trait HasDirectory
{
    public function storagePath($field): string
    {
		// traigo el config de /config/uploads
		$cl = class_basename(static::class);
		// defaults
		$basePath = $cl;
		$fieldPath = $field;
		$id = $this->getKey() ??  null;
		if(empty($id))
		{
			$id = "no-id";
		}
		
		$path = $basePath
				.DIRECTORY_SEPARATOR
				.$id
				.DIRECTORY_SEPARATOR
				.$fieldPath;
		
		return $path;
    }

}
