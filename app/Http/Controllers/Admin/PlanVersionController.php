<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\PlanVersion;
use App\Models\Product;
use App\Services\PdfService;
use App\Services\UploadedFileService;
use App\Support\Breadcrumbs;
use Illuminate\Http\Request;

class PlanVersionController extends Controller
{
	/**
	 * Listado de versiones de un producto tipo plan_regular / plan_capitado.
	 */
	public function index(Product $product)
	{
		$planVersions = PlanVersion::where('product_id', $product->id)
			->orderBy('id', 'desc')
			->get();

		$company = $product->company;
		if(!empty($company))
		{
			Breadcrumbs::add('Empresas', route('admin.companies.index'));
			Breadcrumbs::add($company->name, route('admin.companies.capitated-products.index', $company));
		}
		else
		{
			Breadcrumbs::add('Productos', route('admin.products.index'));
		}

		Breadcrumbs::add('Plan ' . $product->name, route('admin.products.plans.index', $product));

		return view('admin.plans.index', [
			'product'      => $product,
			'planVersions' => $planVersions,
		]);
	}

	public function pdf_preview(Product $product, PlanVersion $planVersion, PdfService $pdf, Request $request)
    {
		$planVersion->load([
			'product',
			'coverages.coverage.unit',
			'coverages.coverage.category',
		]);

		$coverageCategories = $this->buildCoverageCategories($planVersion);
		
		$company = $product->company;

		$id_contrato	 = "YTB-000001";
		$emitido_por	 = "Nombre Emisor";
		$branding		 = [];
		$template = null;
		if(!empty($company))
		{
			$branding		 = $company->branding();
			$id_contrato	 = "{$company->short_code}-000001";
			$emitido_por	 = $company->name;
			$template		 = $company->pdfTemplate ?? \App\Models\Template::searchTemplate("principal");
		}
		else
		{
			$branding = \App\Models\Company::defaultBranding();
			$template = \App\Models\Template::searchTemplate("principal");
		}

		
		if(empty($template))
		{
            abort(503);
		}
		
		$templateVersion = $template->activeTemplateVersion;
		if(empty($templateVersion))
		{
            abort(504);
		}
		
		$data = \App\Support\JsonDecode::get($template->test_data_json);

		$branding['logo'] = $branding['logo']->localPath();
		
		$data['product'] = $product;
		$data['planVersion'] = $planVersion;
		$data['coverageCategories'] = $coverageCategories;
		$data['contrato']['id'] = $id_contrato;
		$data['contrato']['emitido_por'] = $emitido_por;
		$data['contrato']['codigo_plan'] = "{$product->id}v{$planVersion->id}";
		$data['branding'] = $branding;
				
		switch($request->get('debug'))
		{
			case 'html': echo $pdf->renderBladeString($templateVersion->content, $data); exit;
			case 'data': return response(json_encode($data))->header('Content-Type', 'application/json');
		}

		$options = [
			'orientation' => 'P',
			'format'      => 'LETTER',
			'margin'      => [10, 10, 10, 10],
		];

		$binary = $pdf->makePdfFromTemplateString($templateVersion->content, $data, $options);

		return response($binary)->header('Content-Type', 'application/pdf');
    }	

	/**
	 * Crear una versión nueva (siempre status = inactive).
	 * Se llama vía AJAX desde el modal con sólo el campo name.
	 */
	public function store(Request $request, Product $product)
	{
		$validated = $request->validate([
			'name' => ['required', 'string', 'max:255'],
		]);

		$version = new PlanVersion();
		$version->product_id = $product->id;
		$version->name       = $validated['name'];
		$version->status     = PlanVersion::STATUS_INACTIVE;
		$version->save();
		$version->refresh();

		$redirectUrl = route('admin.products.plans.edit', [
			'product'     => $product->id,
			'planVersion' => $version->id,
		]);

		return $this->jsonToastSuccess(
			['data' => $version, 'redirect_url' => $redirectUrl],
			'Versión creada.'
		);
	}

	/**
	 * Clonar una versión existente.
	 */
	public function clone(Request $request, Product $product, PlanVersion $planVersion)
	{
		if ($planVersion->product_id !== $product->id) {
			abort(404);
		}

		$validated = $request->validate([
			'name' => ['required', 'string', 'max:255'],
		]);

		$planVersion->loadMissing([
			'coverages',
			'termsFileEs',
			'termsFileEn',
		]);

		$clone             = $planVersion->replicate();
		$clone->name       = $validated['name'];
		$clone->status     = PlanVersion::STATUS_INACTIVE;
		$clone->created_at = now();
		$clone->updated_at = now();
		$clone->save();

		foreach ($planVersion->coverages as $pivot) {
			$newPivot                  = $pivot->replicate();
			$newPivot->plan_version_id = $clone->id;
			$newPivot->created_at      = now();
			$newPivot->updated_at      = now();
			$newPivot->save();
		}

		$this->cloneTermsFileRelation($planVersion->termsFileEs, $clone, 'terms_file_es_id');
		$this->cloneTermsFileRelation($planVersion->termsFileEn, $clone, 'terms_file_en_id');

		$clone->refresh();
		$clone->loadMissing([
			'coverages.coverage.unit',
			'coverages.coverage.category',
			'country',
			'zone',
			'termsFileEs',
			'termsFileEn',
		]);

		$payload = $clone->toArray();

		$redirectUrl = route('admin.products.plans.edit', [
			'product'     => $product->id,
			'planVersion' => $clone->id,
		]);

		return $this->jsonToastSuccess(
			['data' => $payload, 'redirect_url' => $redirectUrl],
			'Versión clonada.'
		);
	}

	protected function cloneTermsFileRelation(?File $originalFile, PlanVersion $clone, string $targetField): void
	{
		if (!$originalFile) return;

		$disk    = $originalFile->disk ?: config('filesystems.default');
		$origPath = $originalFile->path;

		if (!$origPath || !\Storage::disk($disk)->exists($origPath)) return;

		$newUuid = (string) \Str::uuid();

		$extFromPath = pathinfo($origPath, PATHINFO_EXTENSION);
		$extFromName = pathinfo($originalFile->original_name, PATHINFO_EXTENSION);
		$ext         = $extFromPath ?: $extFromName;
		$ext         = $ext ? '.' . ltrim($ext, '.') : '';

		$dir = trim(dirname($origPath), '/');
		if ($dir === '.' || $dir === '/') $dir = '';

		$newPath = ($dir ? $dir . '/' : '') . $newUuid . $ext;

		\Storage::disk($disk)->copy($origPath, $newPath);

		$newFile = File::create([
			'uuid'          => $newUuid,
			'disk'          => $disk,
			'path'          => $newPath,
			'original_name' => $originalFile->original_name,
			'mime_type'     => $originalFile->mime_type,
			'size'          => $originalFile->size,
			'uploaded_by'   => $originalFile->uploaded_by,
			'meta'          => $originalFile->meta,
		]);

		$clone->{$targetField} = $newFile->id;
		$clone->save();
	}

	public function destroy(Product $product, PlanVersion $planVersion)
	{
		if ($planVersion->product_id !== $product->id) {
			abort(404);
		}

		if (!$planVersion->isDeletable()) {
			return $this->jsonToastError(
				'Esta versión está en uso y no puede eliminarse.',
				422
			);
		}

		$planVersion->delete();

		return $this->jsonToastSuccess([], 'Versión eliminada correctamente.');
	}

	public function edit(Product $product, PlanVersion $planVersion)
	{
		if ($planVersion->product_id !== $product->id) {
			abort(404);
		}

		$planVersion->load([
			'product',
			'coverages.coverage.unit',
			'coverages.coverage.category',
			'country',
			'zone',
			'termsFileEs',
			'termsFileEn',
		]);

		$coverageCategories = $this->buildCoverageCategories($planVersion);

		$company = $product->company;
		if (!empty($company)) {
			Breadcrumbs::add('Empresas', route('admin.companies.index'));
			Breadcrumbs::add($company->name, route('admin.companies.capitated-products.index', $company));
		} else {
			Breadcrumbs::add('Productos', route('admin.products.index'));
		}

		Breadcrumbs::add('Plan ' . $product->name, route('admin.products.plans.index', $product));
		Breadcrumbs::add('Versión #' . $planVersion->id, route('admin.products.plans.edit', [$product, $planVersion]));

		return view('admin.plans.edit', [
			'product'            => $product,
			'planVersion'        => $planVersion,
			'coverageCategories' => $coverageCategories,
		]);
	}

	public function buildCoverageCategories(PlanVersion $planVersion): array
	{
		$categories = [];

		$planVersion->loadMissing([
			'coverages.coverage.unit',
			'coverages.coverage.category',
		]);

		$planVersionCoverages = $planVersion->coverages
			->sortBy('sort_order')
			->values();

		foreach ($planVersionCoverages as $pivot) {
			$coverage = $pivot->coverage;
			if (!$coverage) continue;

			$category = $coverage->category;
			if (!$category) continue;

			$categoryId = $category->id;

			if (!isset($categories[$categoryId])) {
				$categories[$categoryId] = [
					'id'          => $categoryId,
					'sort_order'  => $category->sort_order ?? 0,
					'name'        => $category->name,
					'description' => $category->description,
					'coverages'   => [],
				];
			}

			$unit = $coverage->unit;

			$categories[$categoryId]['coverages'][] = [
				'id'                   => $pivot->id,
				'plan_version_id'      => $pivot->plan_version_id,
				'coverage_id'          => $pivot->coverage_id,
				'sort_order'           => $pivot->sort_order,

				'value_int'            => $pivot->value_int,
				'value_decimal'        => $pivot->value_decimal,
				'value_text'           => $this->decodeTranslatable($pivot->getRawOriginal('value_text')),
				'notes'                => $this->decodeTranslatable($pivot->getRawOriginal('notes')),
				'notes_t'              => $pivot->notes,
				'display_value'        => $pivot->display_value,

				'coverage_name'        => $coverage->name,
				'coverage_description' => $coverage->description,
				'unit_name'            => $unit ? $unit->name : null,
				'unit_measure_type'    => $unit ? $unit->measure_type : null,

				'category_id'          => $categoryId,
				'category_name'        => $category->name,
				'category_description' => $category->description,
			];
		}

		$categoriesList = array_values($categories);

		usort($categoriesList, function (array $a, array $b) {
			$sa = $a['sort_order'] ?? 0;
			$sb = $b['sort_order'] ?? 0;

			if ($sa === $sb) {
				return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
			}

			return $sa <=> $sb;
		});

		return $categoriesList;
	}

	protected function decodeTranslatable($raw): array
	{
		if (empty($raw)) {
			return ['es' => null, 'en' => null];
		}

		$decoded = json_decode($raw, true);
		if (is_array($decoded)) {
			return [
				'es' => $decoded['es'] ?? null,
				'en' => $decoded['en'] ?? null,
			];
		}

		return ['es' => $raw, 'en' => null];
	}

	/**
	 * API: devolver términos HTML (ES/EN) de una versión.
	 */
	public function showTermsHtml(Product $product, PlanVersion $planVersion)
	{
		if ($planVersion->product_id !== $product->id) {
			abort(404);
		}

		// Siempre leemos el valor CRUDO de BD para obtener el JSON completo
		$terms = [
			'es' => null,
			'en' => null,
		];

		$raw = $planVersion->getRawOriginal('terms_html');

		if (is_string($raw) && $raw !== '') {
			$decoded = json_decode($raw, true);

			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				// JSON correcto con claves es/en
				$terms['es'] = $decoded['es'] ?? null;
				$terms['en'] = $decoded['en'] ?? null;
			} else {
				// Caso legacy: se guardó una cadena simple, asumimos ES
				$terms['es'] = $raw;
			}
		}

		return response()->json([
			'data' => [
				'terms_html' => $terms,
			],
		]);
	}

	/**
	 * API: actualizar términos HTML (ES o EN) de una versión.
	 */
	public function updateTermsHtml(Request $request, Product $product, PlanVersion $planVersion)
	{
		if ($planVersion->product_id !== $product->id) {
			abort(404);
		}

		$data = $request->validate([
			'locale' => ['required', 'in:es,en'],
			'html'   => ['nullable', 'string'],
		]);

		$locale = $data['locale'];
		$html   = $data['html'] ?? null;

		// Leer SIEMPRE el JSON crudo desde DB para no depender del tipo devuelto por el cast
		$raw = $planVersion->getRawOriginal('terms_html');

		if (is_string($raw) && $raw !== '') {
			$current = json_decode($raw, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				$current = null;
			}
		} else {
			$current = null;
		}

		if (!is_array($current)) {
			$current = [];
		}

		// Normalizamos a estructura con ambas claves, preservando lo que ya exista
		$current = array_merge(
			[
				'es' => $current['es'] ?? null,
				'en' => $current['en'] ?? null,
			],
			$current
		);

		// Sólo tocamos el idioma indicado
		$current[$locale] = $html;

		$planVersion->terms_html = $current;
		$planVersion->save();

		$planVersion->refresh();

		$message = $locale === 'en'
			? 'Términos (EN) actualizados.'
			: 'Términos (ES) actualizados.';

		return $this->jsonToastSuccess(
			[
				'data' => [
					'terms_html' => $planVersion->terms_html,
				],
			],
			$message,
			'terms_html_' . $locale
		);
	}

	public function update(Request $request, Product $product, PlanVersion $planVersion, PdfService $pdf)
	{
		if ($planVersion->product_id !== $product->id) {
			abort(404);
		}

		$originalStatus = $planVersion->status;

		$data = $request->validate([
			'name'                         => ['sometimes', 'string', 'max:255'],
			'status'                       => ['sometimes', 'in:' . implode(',', [
				PlanVersion::STATUS_INACTIVE,
				PlanVersion::STATUS_ACTIVE,
				PlanVersion::STATUS_ARCHIVED,
			])],

			'max_entry_age'                => ['sometimes', 'nullable', 'integer', 'min:0'],
			'max_renewal_age'              => ['sometimes', 'nullable', 'integer', 'min:0'],
			'wtime_suicide'                => ['sometimes', 'nullable', 'integer', 'min:0'],
			'wtime_preexisting_conditions' => ['sometimes', 'nullable', 'integer', 'min:0'],
			'wtime_accident'               => ['sometimes', 'nullable', 'integer', 'min:0'],

			'country_id'                   => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
			'zone_id'                      => ['sometimes', 'nullable', 'integer', 'exists:zones,id'],

			'price_1'                      => ['sometimes', 'nullable', 'numeric', 'min:0'],
			'price_2'                      => ['sometimes', 'nullable', 'numeric', 'min:0'],
			'price_3'                      => ['sometimes', 'nullable', 'numeric', 'min:0'],
			'price_4'                      => ['sometimes', 'nullable', 'numeric', 'min:0'],

			'terms_file_es_id'             => ['sometimes', 'nullable', 'integer'],
			'terms_file_en_id'             => ['sometimes', 'nullable', 'integer'],
			'terms_file_es'                => PlanVersion::termsFileValidationRules(),
			'terms_file_en'                => PlanVersion::termsFileValidationRules(),
		]);

		// flags para construir un toast específico
		$toastField   = null;
		$toastMessage = null;

		$planVersion->fill(
			collect($data)->except([
				'terms_file_es',
				'terms_file_en',
				'terms_file_es_id',
				'terms_file_en_id',
			])->toArray()
		);

		/** @var UploadedFileService $fileService */
		$fileService = app(UploadedFileService::class);

		$fileToDeleteEs = null;
		$fileToDeleteEn = null;

		// ES: limpiar archivo si terms_file_es_id viene explícitamente null
		// EN: limpiar archivo si terms_file_en_id viene explícitamente null
		if (array_key_exists('terms_file_es_id', $data) && $data['terms_file_es_id'] === null) {
			if ($planVersion->termsFileEs) $fileToDeleteEs = $planVersion->termsFileEs;
			$planVersion->terms_file_es_id = null;

			$toastField   = 'terms_file_es';
			$toastMessage = 'Términos (ES) removidos.';
		}

		// EN: limpiar archivo si terms_file_en_id viene explícitamente null
		if (array_key_exists('terms_file_en_id', $data) && $data['terms_file_en_id'] === null) {
			if ($planVersion->termsFileEn) $fileToDeleteEn = $planVersion->termsFileEn;
			$planVersion->terms_file_en_id = null;

			$toastField   = 'terms_file_en';
			$toastMessage = 'Términos (EN) removidos.';
		}

		// Subir / reemplazar términos ES
		if ($request->hasFile('terms_file_es')) {
			$uploadedEs = $request->file('terms_file_es');

			try {
				$pdf->assertPdfMergeCompatibleUpload($uploadedEs);
			} catch (\Throwable $ex) {
				return $this->jsonToastError($ex->getMessage(), 422, 'terms_file_es');
			}

			$metaEs = [
				'context'     => 'product_version_terms',
				'model'       => PlanVersion::class,
				'model_field' => 'terms_file_es_id',
				'lang'        => 'es',
			];

			if ($planVersion->termsFileEs) {
				$fileService->replace(
					$planVersion->termsFileEs,
					$uploadedEs,
					$metaEs,
					optional($request->user())->id,
					true,
					null,
					$planVersion->storagePath('terms')
				);
			} else {
				$storedEs = $fileService->store(
					$uploadedEs,
					$metaEs,
					optional($request->user())->id,
					null,
					$planVersion->storagePath('terms')
				);
				$planVersion->terms_file_es_id = $storedEs->id;
			}

			$toastField   = 'terms_file_es';
			$toastMessage = 'Términos (ES) actualizados.';
		}

		// Subir / reemplazar términos EN
		if ($request->hasFile('terms_file_en')) {
			$uploadedEn = $request->file('terms_file_en');

			try {
				$pdf->assertPdfMergeCompatibleUpload($uploadedEn);
			} catch (\Throwable $ex) {
				return $this->jsonToastError($ex->getMessage(), 422, 'terms_file_en');
			}

			$metaEn = [
				'context'     => 'product_version_terms',
				'model'       => PlanVersion::class,
				'model_field' => 'terms_file_en_id',
				'lang'        => 'en',
			];

			if ($planVersion->termsFileEn) {
				$fileService->replace(
					$planVersion->termsFileEn,
					$uploadedEn,
					$metaEn,
					optional($request->user())->id,
					true,
					null,
					$planVersion->storagePath('terms')
				);
			} else {
				$storedEn = $fileService->store(
					$uploadedEn,
					$metaEn,
					optional($request->user())->id,
					null,
					$planVersion->storagePath('terms')
				);
				$planVersion->terms_file_en_id = $storedEn->id;
			}

			$toastField   = 'terms_file_en';
			$toastMessage = 'Términos (EN) actualizados.';
		}

		// Validación extra al intentar activar
		if (
			array_key_exists('status', $data)
			&& $originalStatus !== PlanVersion::STATUS_ACTIVE
			&& $data['status'] === PlanVersion::STATUS_ACTIVE
			&& !$planVersion->canBeActivated()
		) {
			return $this->jsonToastError(
				'No se puede activar esta versión porque faltan datos obligatorios (edades, plazos de espera, país, precios y archivos de términos ES/EN).',
				422,
				'status'
			);
		}

		$planVersion->save();

		// Si activamos, desactivar otras del mismo producto
		if ($originalStatus !== PlanVersion::STATUS_ACTIVE && $planVersion->status === PlanVersion::STATUS_ACTIVE) {
			PlanVersion::query()
				->where('product_id', $planVersion->product_id)
				->where('id', '!=', $planVersion->id)
				->where('status', PlanVersion::STATUS_ACTIVE)
				->update(['status' => PlanVersion::STATUS_INACTIVE]);
		}

		if ($fileToDeleteEs) $fileService->delete($fileToDeleteEs);
		if ($fileToDeleteEn) $fileService->delete($fileToDeleteEn);

		$planVersion->refresh();
		$planVersion->loadMissing([
			'coverages.coverage.unit',
			'coverages.coverage.category',
			'country',
			'zone',
			'termsFileEs',
			'termsFileEn',
		]);

		$payload = $planVersion->toArray();

		// Si no hubo mensaje por upload/remove, construye mensaje por el/los campos editados.
		if (!$toastMessage) {
			$toastMessage = $this->buildUpdateToastMessage($data, $originalStatus, $planVersion->status);
			$toastField   = $toastField ?: $this->guessToastField($data);
		}

		return $this->jsonToastSuccess(
			['data' => $payload],
			$toastMessage ?: 'Cambios guardados.',
			$toastField
		);
	}

	protected function buildUpdateToastMessage(array $validatedData, string $originalStatus, string $newStatus): ?string
	{
		// status (más prioritario)
		if (array_key_exists('status', $validatedData) && $validatedData['status'] !== $originalStatus) {
			return $validatedData['status'] === PlanVersion::STATUS_ACTIVE
				? 'Versión activada correctamente.'
				: 'Versión desactivada correctamente.';
		}

		// un solo campo -> mensaje específico
		$keys = array_keys($validatedData);
		if (count($keys) === 1) {
			return $this->fieldToastMessage($keys[0]);
		}

		// múltiples campos -> lista corta (no genérica)
		$labels = [];
		foreach ($keys as $k) {
			$m = $this->fieldToastMessage($k);
			if ($m) {
				// convierte "X actualizado." -> "X"
				$labels[] = rtrim(str_replace(['actualizada.', 'actualizado.', 'actualizados.', 'removidos.', 'removido.'], '', $m));
			}
		}

		$labels = array_values(array_filter($labels));
		if (!count($labels)) return null;

		return 'Cambios guardados: ' . implode(', ', $labels) . '.';
	}

	protected function fieldToastMessage(string $field): ?string
	{
		return match ($field) {
			'name'                         => 'Nombre de la versión actualizado.',
			'max_entry_age'                => 'Edad máxima de entrada actualizada.',
			'max_renewal_age'              => 'Edad máxima de renovación actualizada.',
			'wtime_suicide'                => 'Carencia por suicidio actualizada.',
			'wtime_preexisting_conditions' => 'Carencia por preexistencias actualizada.',
			'wtime_accident'               => 'Carencia por accidente actualizada.',
			'country_id'                   => 'País actualizado.',
			'zone_id'                      => 'Zona actualizada.',
			'price_1'                      => 'Precio 1 actualizado.',
			'price_2'                      => 'Precio 2 actualizado.',
			'price_3'                      => 'Precio 3 actualizado.',
			'price_4'                      => 'Precio 4 actualizado.',
			'terms_file_es_id'             => 'Términos (ES) actualizados.',
			'terms_file_en_id'             => 'Términos (EN) actualizados.',
			default                        => null,
		};
	}

	protected function guessToastField(array $validatedData): ?string
	{
		$keys = array_keys($validatedData);
		if (count($keys) === 1) return $keys[0];
		return null;
	}
}
