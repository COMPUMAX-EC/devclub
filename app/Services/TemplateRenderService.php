<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\JsonDecode;
use Illuminate\Support\Facades\Blade;

class TemplateRenderService
{
	public function buildMergedData(Template $template, ?TemplateVersion $version = null): array
	{
		$original['branding'] = \App\Models\Company::defaultBranding();
		$original['branding']['logo'] = $original['branding']['logo']->localPath();

		$base = $this->decodeJsonText($template->test_data_json ?? null);
		$ver  = $version ? $this->decodeJsonText($version->test_data_json ?? null) : [];

		// Preferencia a keys de la versión (pisan a plantilla)
		return array_replace($original, $base, $ver);
	}

	public function renderBladeString(string $bladeContent, array $data = []): string
	{
		return Blade::render($bladeContent, $data);
	}

	/**
	 * Decodifica JSON almacenado como TEXT. Si es vacío/null => [].
	 * Si es inválido => [] (la validación debería evitarlo, esto es fallback).
	 */
	public function decodeJsonText($json): array
	{
		return JsonDecode::get($json);
	}
}
