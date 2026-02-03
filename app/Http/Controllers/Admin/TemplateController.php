<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Breadcrumbs;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
	public function index(Request $request)
	{
		$templates = Template::query()
			->with([
				// Relación esperada en el modelo Template: activeVersion
				// Se limita a campos necesarios para el listado (evita cargar content/test_data_json)
				'activeVersion:id,template_id,name',
			])
			->orderBy('id', 'desc')
			->get();

		if ($request->expectsJson() || $request->ajax()) {
			return response()->json(['data' => $templates]);
		}

		// Breadcrumbs
		Breadcrumbs::add('Plantillas', route('admin.templates.index'));

		return view('admin.templates.index', [
			'templates' => $templates,
		]);
	}

	public function edit(Template $template)
	{
		// Breadcrumbs
		Breadcrumbs::add('Plantillas', route('admin.templates.index'));
		Breadcrumbs::add($template->name, route('admin.templates.edit', ['template' => $template->id]));

		return view('admin.templates.edit', [
			'template'   => $template,
		]);
	}

	/**
	 * Endpoint JSON para data fresca de plantilla + versiones (orden ID asc).
	 */
	public function show(Request $request, Template $template)
	{
		$versions = TemplateVersion::query()
			->where('template_id', $template->id)
			->orderBy('id', 'asc')
			->get();

		return response()->json([
			'data' => [
				'template'  => $template,
				'versions'  => $versions,
			],
		]);
	}

	public function store(Request $request)
	{
		$validated = $request->validate([
			'name' => ['required', 'string', 'max:255'],
			'slug' => ['required', 'string', 'max:255', 'unique:templates,slug'],
			'type' => ['required', 'in:HTML,PDF'],
		]);

		$template = new Template();
		$template->name = $validated['name'];
		$template->slug = $validated['slug'];
		$template->type = $validated['type'];
		$template->save();
		$template->refresh();

		return $this->jsonToastSuccess(
			['data' => ['template' => $template]],
			'Plantilla creada.'
		);
	}

	/**
	 * Editar básicos (sin type).
	 * Regla: en edición NO devolver el modelo.
	 */
	public function updateBasic(Request $request, Template $template)
	{
		$validated = $request->validate([
			'name' => ['required', 'string', 'max:255'],
			'slug' => ['required', 'string', 'max:255', 'unique:templates,slug,' . $template->id],
		]);

		$template->name = $validated['name'];
		$template->slug = $validated['slug'];
		$template->save();

		return $this->jsonToastSuccess([], 'Plantilla actualizada.');
	}

	/**
	 * Regla: en edición NO devolver el modelo.
	 */
	public function updateTestData(Request $request, Template $template)
	{
		$validated = $request->validate([
			'test_data_json' => ['nullable', 'string'],
		]);

		$raw = isset($validated['test_data_json']) ? trim((string) $validated['test_data_json']) : '';
		if ($raw !== '' && json_decode($raw, true) === null && json_last_error() !== JSON_ERROR_NONE) {
			return $this->jsonToastError('JSON inválido.', 422, 'test_data_json');
		}

		$template->test_data_json = ($raw === '') ? null : $raw;
		$template->save();

		return $this->jsonToastSuccess([], 'JSON guardado.');
	}

	public function destroy(Template $template)
	{
		$template->delete();
		return $this->jsonToastSuccess([], 'Plantilla eliminada.');
	}

	public function clone(Template $template)
	{
		$template->loadMissing(['activeVersion']);

		$clone = $template->replicate([
			'active_template_version_id',
		]);

		// Slug único (sin depender del id)
		$clone->slug = $this->makeUniqueCloneSlug($template->slug);
		$clone->active_template_version_id = null;
		$clone->created_at = now();
		$clone->updated_at = now();
		$clone->save();
		$clone->refresh();

		// Nombre final con ID ya conocido
		$clone->name = $template->name . ' (Copia) #' . $clone->id;
		$clone->save();

		// Clonar versión activa si existe
		$active = $template->activeVersion;
		if ($active) {
			$newV = $active->replicate();
			$newV->template_id = $clone->id;
			$newV->created_at = now();
			$newV->updated_at = now();
			$newV->save();
			$newV->refresh();

			$newV->name = $active->name . ' (Copia) #' . $newV->id;
			$newV->save();

			$clone->active_template_version_id = $newV->id;
			$clone->save();
		}

		return $this->jsonToastSuccess(
			['data' => ['template' => $clone]],
			'Plantilla clonada.'
		);
	}

	protected function makeUniqueCloneSlug(string $baseSlug): string
	{
		$baseSlug = trim($baseSlug);
		$baseSlug = $baseSlug !== '' ? $baseSlug : 'template';

		for ($i = 0; $i < 20; $i++) {
			$rand = Str::lower(Str::random(6));
			$slug = $baseSlug . '-copia-' . $rand;

			$exists = Template::withTrashed()->where('slug', $slug)->exists();
			if (!$exists) return $slug;
		}

		// Fallback extremo
		return $baseSlug . '-copia-' . Str::lower(Str::random(12));
	}
}
