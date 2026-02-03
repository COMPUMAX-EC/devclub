<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coverage;
use App\Models\CoverageCategory;
use App\Models\PlanVersion;
use App\Models\PlanVersionCoverage;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanVersionCoverageController extends Controller
{

	/**
	 * Listado de coberturas disponibles para añadir a una versión de plan.
	 * Devuelve categorías + coberturas, indicando cuáles ya están asociadas.
	 */
	public function available(Product $product, PlanVersion $planVersion)
	{
		// Mapeo de coverage_id => PlanVersionCoverage (pivot)
		$attached = $planVersion->coverages()
				->get(['id', 'coverage_id'])
				->keyBy('coverage_id');

		$categories = CoverageCategory::query()
				->where('status', 'active')
				->orderBy('sort_order')
				->orderBy('id')
				->get();

		$data = [];

		foreach ($categories as $category)
		{
			$coverages = Coverage::query()
					->where('category_id', $category->id)
					->where('status', 'active')
					->orderBy('sort_order')
					->orderBy('id')
					->with('unit')
					->get();

			$data[] = [
				'id'		  => $category->id,
				'name'		  => $category->name,
				'description' => $category->description,
				'coverages'	  => $coverages->map(function (Coverage $coverage) use ($attached)
				{
					$unit  = $coverage->unit;
					$pivot = $attached->get($coverage->id); // puede ser null

					return [
						'id'					   => $coverage->id, // ID de coverage (catálogo)
						'name'					   => $coverage->name,
						'description'			   => $coverage->description,
						'unit'					   => $unit ? [
					'id'		   => $unit->id,
					'name'		   => $unit->name,
					'measure_type' => $unit->measure_type,
						] : null,
						'attached'				   => $pivot !== null,
						'plan_version_coverage_id' => $pivot?->id, // ID del pivot, si existe
					];
				})->values()->all(),
			];
		}

		return response()->json([
					'data' => $data,
		]);
	}

	/**
	 * Añadir una cobertura a la versión de plan.
	 */
	public function store(Request $request, Product $product, PlanVersion $planVersion)
	{
		$validated = $request->validate([
			'coverage_id' => 'required|exists:coverages,id',
		]);

		$coverage = Coverage::query()
				->with(['category', 'unit'])
				->findOrFail($validated['coverage_id']);

		// Evitar duplicados
		$existing = PlanVersionCoverage::query()
				->where('plan_version_id', $planVersion->id)
				->where('coverage_id', $coverage->id)
				->first();

		if ($existing)
		{
			// Ya existe, devolvemos el existente serializado
			return response()->json([
						'data' => $this->serializeCoverage($existing->load(['coverage.category', 'coverage.unit'])),
			]);
		}

		// sort_order al final dentro de la categoría de esa versión
		$maxSort = PlanVersionCoverage::query()
				->where('plan_version_id', $planVersion->id)
				->whereHas('coverage', function ($q) use ($coverage)
				{
					$q->where('category_id', $coverage->category_id);
				})
				->max('sort_order');

		$pvc = PlanVersionCoverage::create([
			'plan_version_id' => $planVersion->id,
			'coverage_id'	  => $coverage->id,
			'sort_order'	  => ($maxSort ?? 0) + 1,
			'value_int'		  => null,
			'value_decimal'	  => null,
			'value_text'	  => ['es' => null, 'en' => null],
			'notes'			  => ['es' => null, 'en' => null],
		]);

		$pvc->load(['coverage.category', 'coverage.unit']);

		// Log
		Audit::log('plan_version.coverage.added', [
			'product_id'			   => $product->id,
			'plan_version_id'		   => $planVersion->id,
			'plan_version_coverage_id' => $pvc->id,
			'coverage_id'			   => $coverage->id,
		]);

		return response()->json([
					'data' => $this->serializeCoverage($pvc),
		]);
	}

	/**
	 * Eliminar una cobertura de la versión de plan.
	 */
	public function destroy(Product $product, PlanVersion $planVersion, PlanVersionCoverage $coverage)
	{
		if ($coverage->plan_version_id !== $planVersion->id)
		{
			abort(404);
		}

		$id	   = $coverage->id;
		$covId = $coverage->coverage_id;

		$coverage->delete();

		Audit::log('plan_version.coverage.removed', [
			'product_id'			   => $product->id,
			'plan_version_id'		   => $planVersion->id,
			'plan_version_coverage_id' => $id,
			'coverage_id'			   => $covId,
		]);

		return response()->json([
					'status'  => 'ok',
					'message' => 'Cobertura eliminada de la versión.',
		]);
	}

	public function reorder(Request $request, Product $product, PlanVersion $planVersion)
	{
		$this->ensureBelongs($product, $planVersion);

		$ids = $request->input('coverage_ids', []);
		if (!is_array($ids))
		{
			return response()->json(['message' => 'Formato inválido'], 422);
		}

		foreach ($ids as $index => $id)
		{
			PlanVersionCoverage::where('plan_version_id', $planVersion->id)
					->where('id', $id)
					->update(['sort_order' => $index + 1]);
		}

		Audit::log('plan_version.coverages.reordered', [
			'product_id'	  => $product->id,
			'plan_version_id' => $planVersion->id,
			'ids'			  => $ids,
		]);

		return response()->json(['status' => 'ok']);
	}

	/**
	 * Actualizar valor / notas de una cobertura concreta (autosave).
	 * Request puede traer cualquier combinación de:
	 * - value_int
	 * - value_decimal
	 * - value_text[es|en]
	 * - notes[es|en]
	 */
	public function updateValue(
			Request $request,
			Product $product,
			PlanVersion $planVersion,
			PlanVersionCoverage $coverage
	)
	{
		$this->ensureBelongs($product, $planVersion, $coverage);

		$coverage->loadMissing(['coverage.unit', 'coverage.category']);

		$data = [];

		if ($request->has('value_int'))
		{
			$val			   = $request->input('value_int');
			$data['value_int'] = ($val === null || $val === '') ? null : (int) $val;
		}

		if ($request->has('value_decimal'))
		{
			$val				   = $request->input('value_decimal');
			$data['value_decimal'] = ($val === null || $val === '') ? null : $val;
		}

		if ($request->has('value_text'))
		{
			// viene como array ['es' => ..., 'en' => ...]
			$data['value_text'] = $request->input('value_text') ?: null;
		}

		if ($request->has('notes'))
		{
			$data['notes'] = $request->input('notes') ?: null;
		}

		if (!empty($data))
		{
			$coverage->fill($data);
			$coverage->save();

			Audit::log('plan_version.coverage.value_updated', [
				'product_id'			   => $product->id,
				'plan_version_id'		   => $planVersion->id,
				'plan_version_coverage_id' => $coverage->id,
				'changed'				   => array_keys($data),
			]);
		}

		$coverage->refresh();

		return response()->json([
					'data' => $this->serializeCoverage($coverage),
		]);
	}

	/**
	 * Serializar un PlanVersionCoverage para Vue.
	 */
	protected function serializeCoverage(PlanVersionCoverage $coverage): array
	{
		$coverage->loadMissing(['coverage.category', 'coverage.unit']);

		// Helpers para decodificar JSON translatable
		$decode = function ($raw)
		{
			if (is_array($raw))
			{
				return $raw;
			}
			if ($raw === null || $raw === '')
			{
				return [];
			}
			if (is_string($raw))
			{
				$decoded = json_decode($raw, true);
				return is_array($decoded) ? $decoded : [];
			}
			return [];
		};

		// value_text y notes (translatable)
		$valueTextRaw = $coverage->getRawOriginal('value_text');
		$notesRaw	  = $coverage->getRawOriginal('notes');

		$valueText = $decode($valueTextRaw);
		$notes	   = $decode($notesRaw);

		// aseguramos claves es/en
		$valueText = array_merge(['es' => '', 'en' => ''], $valueText);
		$notes	   = array_merge(['es' => '', 'en' => ''], $notes);

		// Coverage name/description (también translatable en Coverage)
		$cov	  = $coverage->coverage;
		$unit	  = $cov?->unit;
		$category = $cov?->category;

		$covNameRaw		 = $cov?->getRawOriginal('name');
		$covDescRaw		 = $cov?->getRawOriginal('description');
		$unitNameRaw	 = $unit?->getRawOriginal('name');
		$categoryNameRaw = $category?->getRawOriginal('name');
		$categoryDescRaw = $category?->getRawOriginal('description');

		$covName	  = $decode($covNameRaw);
		$covDesc	  = $decode($covDescRaw);
		$unitName	  = $decode($unitNameRaw);
		$categoryName = $decode($categoryNameRaw);
		$categoryDesc = $decode($categoryDescRaw);

		return [
			'id'				   => $coverage->id,
			'plan_version_id'	   => $coverage->plan_version_id,
			'coverage_id'		   => $coverage->coverage_id,
			'sort_order'		   => $coverage->sort_order,
			'value_int'			   => $coverage->value_int,
			'value_decimal'		   => $coverage->value_decimal,
			// LO IMPORTANTE: aquí ya van como objeto {es, en}
			'value_text'		   => $valueText,
			'notes'				   => $notes,
			// Nombres/descripciones translatables como objeto también:
			'coverage_name'		   => $covName,
			'coverage_description' => $covDesc,
			'unit_name'			   => $unitName,
			'unit_measure_type'	   => $unit?->measure_type ?? UnitOfMeasure::TYPE_NONE,
			'category_id'		   => $category?->id,
			'category_name'		   => $categoryName,
			'category_description' => $categoryDesc,
			'category_sort_order'  => $category?->sort_order ?? 0,
		];
	}

	protected function ensureBelongs(Product $product, PlanVersion $planVersion, ?PlanVersionCoverage $coverage = null): void
	{
		if ($planVersion->product_id !== $product->id)
		{
			abort(404);
		}

		if ($coverage && $coverage->plan_version_id !== $planVersion->id)
		{
			abort(404);
		}
	}
}
