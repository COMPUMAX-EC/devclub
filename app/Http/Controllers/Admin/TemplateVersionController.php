<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Services\PdfService;
use App\Services\TemplateRenderService;
use App\Support\Breadcrumbs;
use Illuminate\Http\Request;

class TemplateVersionController extends Controller
{
	public function store(Request $request, Template $template)
	{
		// Nuevo comportamiento:
		// - No se pregunta nombre ni contenido al crear desde UI (solo confirm).
		// - Se crea con content vacío.
		// - Se asigna name = "Version #ID" como default.
		$validated = $request->validate([
			'content' => ['nullable', 'string'],
		]);

		$version = new TemplateVersion();
		$version->template_id = $template->id;
		$version->content = (string) ($validated['content'] ?? '');
		$version->name = ''; // se setea luego con el ID
		$version->save();
		$version->refresh();

		$version->name = 'Version #' . $version->id;
		$version->save();

		return $this->jsonToastSuccess(
			['data' => ['version' => $version]],
			'Versión creada.'
		);
	}

	/**
	 * Endpoint JSON para data fresca de una versión.
	 */
	public function show(Template $template, TemplateVersion $templateVersion)
	{
		$this->assertBelongs($template, $templateVersion);

		return response()->json([
			'data' => [
				'version' => $templateVersion,
			],
		]);
	}

	/**
	 * Regla: en edición NO devolver el modelo.
	 */
	public function updateBasic(Request $request, Template $template, TemplateVersion $templateVersion)
	{
		$this->assertBelongs($template, $templateVersion);

		$validated = $request->validate([
			'name'    => ['required', 'string', 'max:255'],
			'content' => ['required', 'string'],
		]);

		$templateVersion->name = $validated['name'];
		$templateVersion->content = $validated['content'];
		$templateVersion->save();

		return $this->jsonToastSuccess([], 'Versión actualizada.');
	}

	/**
	 * Regla: en edición NO devolver el modelo.
	 */
	public function updateTestData(Request $request, Template $template, TemplateVersion $templateVersion)
	{
		$this->assertBelongs($template, $templateVersion);

		$validated = $request->validate([
			'test_data_json' => ['nullable', 'string'],
		]);

		$raw = isset($validated['test_data_json']) ? trim((string) $validated['test_data_json']) : '';
		if ($raw !== '' && json_decode($raw, true) === null && json_last_error() !== JSON_ERROR_NONE) {
			return $this->jsonToastError('JSON inválido.', 422, 'test_data_json');
		}

		$templateVersion->test_data_json = ($raw === '') ? null : $raw;
		$templateVersion->save();

		return $this->jsonToastSuccess([], 'JSON guardado.');
	}

	/**
	 * Regla: en edición NO devolver el modelo.
	 */
	public function activate(Template $template, TemplateVersion $templateVersion)
	{
		$this->assertBelongs($template, $templateVersion);

		$template->active_template_version_id = $templateVersion->id;
		$template->save();

		return $this->jsonToastSuccess([], 'Versión activada.');
	}

	/**
	 * Regla: en edición NO devolver el modelo.
	 */
	public function deactivate(Template $template, TemplateVersion $templateVersion)
	{
		$this->assertBelongs($template, $templateVersion);

		if ((int) $template->active_template_version_id !== (int) $templateVersion->id) {
			return $this->jsonToastError('La versión no es la activa.', 422);
		}

		$template->active_template_version_id = null;
		$template->save();

		return $this->jsonToastSuccess([], 'Versión desactivada.');
	}

	public function clone(Template $template, TemplateVersion $templateVersion)
	{
		$this->assertBelongs($template, $templateVersion);

		$clone = $templateVersion->replicate();
		$clone->template_id = $template->id;
		$clone->created_at = now();
		$clone->updated_at = now();
		$clone->name = ''; // se setea luego con el ID
		$clone->save();
		$clone->refresh();

		// Nuevo comportamiento: nombre default igual que en creación
		$clone->name = 'Version #' . $clone->id;
		$clone->save();

		return $this->jsonToastSuccess(
			['data' => ['version' => $clone]],
			'Versión clonada.'
		);
	}

	public function destroy(Template $template, TemplateVersion $templateVersion)
	{
		$this->assertBelongs($template, $templateVersion);

		if ((int) $template->active_template_version_id === (int) $templateVersion->id) {
			return $this->jsonToastError('No se puede eliminar una versión activa.', 422);
		}

		$templateVersion->delete();

		return $this->jsonToastSuccess([], 'Versión eliminada.');
	}

	/* =============================================================
	 *                           PREVIEW
	 * ============================================================= */

	public function previewVersionPdf(Template $template, TemplateVersion $templateVersion, TemplateRenderService $render, PdfService $pdf)
	{
		$this->assertBelongs($template, $templateVersion);
		$options = [
			'orientation' => 'P',
			'format'      => 'LETTER',
			'margin'      => [10, 10, 10, 10],
		];
		$data = $render->buildMergedData($template, $templateVersion);
		$binary = $pdf->makePdfFromTemplateString($templateVersion->content, $data, $options);

		return response($binary)->header('Content-Type', 'application/pdf');
	}

	public function previewActivePdf(Template $template, TemplateRenderService $render, PdfService $pdf)
	{
		$active = $this->getActiveVersionOr404($template);
		$options = [
			'orientation' => 'P',
			'format'      => 'LETTER',
			'margin'      => [10, 10, 10, 10],
		];

		$data = $render->buildMergedData($template, $active);
		$binary = $pdf->makePdfFromTemplateString($active->content, $data, $options);

		return response($binary)->header('Content-Type', 'application/pdf');
	}

	protected function getActiveVersionOr404(Template $template): TemplateVersion
	{
		$id = $template->active_template_version_id;
		if (!$id) abort(404);

		$active = TemplateVersion::query()
			->where('template_id', $template->id)
			->where('id', $id)
			->first();

		if (!$active) abort(404);
		return $active;
	}

	protected function assertBelongs(Template $template, TemplateVersion $version): void
	{
		if ((int) $version->template_id !== (int) $template->id) {
			abort(404);
		}
	}
}
