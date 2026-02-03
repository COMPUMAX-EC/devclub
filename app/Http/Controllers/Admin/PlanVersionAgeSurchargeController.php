<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanVersion;
use App\Models\PlanVersionAgeSurcharge;
use App\Models\Product;
use Illuminate\Http\Request;

class PlanVersionAgeSurchargeController extends Controller
{
	protected function ensureRelations(Product $product, PlanVersion $planVersion, ?PlanVersionAgeSurcharge $ageSurcharge = null): void
	{
		if ($planVersion->product_id !== $product->id) {
			abort(404);
		}

		if ($ageSurcharge && $ageSurcharge->plan_version_id !== $planVersion->id) {
			abort(404);
		}
	}

	/**
	 * Listado de recargos por rango de edad para una versión de plan.
	 */
	public function index(Product $product, PlanVersion $planVersion)
	{
		$this->ensureRelations($product, $planVersion);

		$items = PlanVersionAgeSurcharge::query()
			->where('plan_version_id', $planVersion->id)
			->orderBy('age_from')
			->orderBy('age_to')
			->get();

		return response()->json([
			'data' => $items,
		]);
	}

	/**
	 * Crear un nuevo rango de edad con su recargo %.
	 *
	 * El backend NO obliga a que los campos estén completos:
	 * puede recibir null en age_from, age_to o surcharge_percent y los guardará tal cual.
	 * La validación fuerte queda en el frontend (is-invalid).
	 */
	public function store(Request $request, Product $product, PlanVersion $planVersion)
	{
		$this->ensureRelations($product, $planVersion);

		$validated = $request->validate([
			'age_from'          => ['nullable', 'integer', 'min:0'],
			'age_to'            => ['nullable', 'integer', 'min:0'],
			'surcharge_percent' => ['nullable', 'numeric'],
		]);

		$item = new PlanVersionAgeSurcharge();
		$item->plan_version_id   = $planVersion->id;
		$item->age_from          = $validated['age_from']          ?? null;
		$item->age_to            = $validated['age_to']            ?? null;
		$item->surcharge_percent = $validated['surcharge_percent'] ?? null;
		$item->save();

		return $this->jsonToastSuccess(
			['data' => $item],
			'Rango de edad creado.'
		);
	}

	/**
	 * Actualizar parcialmente un rango de edad (Desde/Hasta/Recargo).
	 *
	 * Igual que en store, aquí tampoco son requeridos: se acepta null.
	 */
	public function update(
		Request $request,
		Product $product,
		PlanVersion $planVersion,
		PlanVersionAgeSurcharge $ageSurcharge
	) {
		$this->ensureRelations($product, $planVersion, $ageSurcharge);

		$validated = $request->validate([
			'age_from'          => ['sometimes', 'nullable', 'integer', 'min:0'],
			'age_to'            => ['sometimes', 'nullable', 'integer', 'min:0'],
			'surcharge_percent' => ['sometimes', 'nullable', 'numeric'],
		]);

		$ageSurcharge->fill($validated);
		$ageSurcharge->save();

		return $this->jsonToastSuccess(
			['data' => $ageSurcharge],
			'Rango de edad actualizado.'
		);
	}

	/**
	 * Eliminar un rango de edad.
	 */
	public function destroy(
		Product $product,
		PlanVersion $planVersion,
		PlanVersionAgeSurcharge $ageSurcharge
	) {
		$this->ensureRelations($product, $planVersion, $ageSurcharge);

		$ageSurcharge->delete();

		return $this->jsonToastSuccess(
			['data' => ['id' => $ageSurcharge->id]],
			'Rango de edad eliminado.'
		);
	}
}
