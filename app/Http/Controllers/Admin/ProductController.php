<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Breadcrumbs;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{

	public function __construct()
	{
		parent::__construct();

		$this->middleware(['can:admin.products.manage']);
	}

	/**
	 * Listado de productos regulares (vista con Vue).
	 * Aquí solo listamos y creamos productos TYPE_PLAN_REGULAR sin empresa.
	 */
// app/Http/Controllers/Admin/ProductController.php

	public function index()
	{
		Breadcrumbs::add('Productos', route('admin.products.index'));

		$products = Product::query()
				->ofType(Product::TYPE_PLAN_REGULAR) // ← solo productos regulares
				->orderByDesc('id')
				->get()
				->map(fn(Product $product) => $this->transformProduct($product))
				->values()
				->all();

		$productTypes = $this->productTypeOptions();

		return view('admin.products.index', [
			'products'	   => $products,
			'productTypes' => $productTypes,
			'editRouteMap' => Product::EDIT_ROUTES,
		]);
	}

	public function show(Product $product)
	{
		return response()->json([
					'data' => $this->transformProduct($product),
		]);
	}

	/**
	 * Crear producto (desde modal).
	 * - status siempre INACTIVO.
	 * - si product_type = TYPE_PLAN_CAPITADO => requiere company_id.
	 * - si company_id viene informado => solo se permite TYPE_PLAN_CAPITADO.
	 */
	public function store(Request $request)
	{
		$validated = $request->validate([
			'name.es'		 => ['required', 'string', 'max:255'],
			'name.en'		 => ['nullable', 'string', 'max:255'],
			'description.es' => ['nullable', 'string'],
			'description.en' => ['nullable', 'string'],
			'product_type'	 => ['required', 'string', Rule::in(Product::productTypes())],
			'show_in_widget' => ['sometimes', 'boolean'],
			'company_id'	 => ['nullable', 'integer', 'exists:companies,id'],
		]);

		$productType = $validated['product_type'];
		$companyId	 = $validated['company_id'] ?? null;

		// Reglas de consistencia tipo <-> empresa
		if ($productType === Product::TYPE_PLAN_CAPITADO && !$companyId)
		{
			return response()->json([
						'message' => 'Los productos capitados deben estar asociados a una empresa.',
							], 422);
		}

		if ($productType !== Product::TYPE_PLAN_CAPITADO && $companyId)
		{
			return response()->json([
						'message' => 'Solo los productos capitados pueden asociarse a una empresa.',
							], 422);
		}

		$data = [
			'company_id'	 => $companyId,
			'name'			 => $validated['name'] ?? [],
			'description'	 => $validated['description'] ?? [],
			'product_type'	 => $productType,
			'show_in_widget' => $validated['show_in_widget'] ?? false,
			'status'		 => Product::STATUS_INACTIVE,
		];

		$product = Product::create($data);

		return response()->json([
					'data'	  => $this->transformProduct($product),
					'message' => 'Producto creado correctamente.',
		]);
	}

	/**
	 * Actualizar producto (desde modal).
	 * - product_type NO se modifica aquí.
	 * - company_id tampoco se toca aquí.
	 */
	public function update(Request $request, Product $product)
	{
		$validated = $request->validate([
			'name.es'		 => ['required', 'string', 'max:255'],
			'name.en'		 => ['nullable', 'string', 'max:255'],
			'description.es' => ['nullable', 'string'],
			'description.en' => ['nullable', 'string'],
			'show_in_widget' => ['sometimes', 'boolean'],
			'status'		 => [
				'required',
				'string',
				Rule::in([
					Product::STATUS_ACTIVE,
					Product::STATUS_INACTIVE,
				]),
			],
		]);

		$product->fill([
			'name'			 => $validated['name'] ?? [],
			'description'	 => $validated['description'] ?? [],
			'show_in_widget' => $validated['show_in_widget'] ?? false,
			'status'		 => $validated['status'],
		]);

		$product->save();

		return response()->json([
					'data'	  => $this->transformProduct($product),
					'message' => 'Producto actualizado correctamente.',
		]);
	}

	protected function transformProduct(Product $product): array
	{
		return [
			'id'			 => $product->id,
			'company_id'	 => $product->company_id,
			'status'		 => $product->status,
			'product_type'	 => $product->product_type,
			'show_in_widget' => (bool) $product->show_in_widget,
			'name'			 => $product->getTranslations('name'),
			'description'	 => $product->getTranslations('description'),
		];
	}

	/**
	 * Opciones para el select de tipo de producto.
	 * Si se pasa $only, se limita a esos tipos.
	 */
	protected function productTypeOptions(?array $only = null): array
	{
		$types = Product::productTypes();

		if ($only !== null)
		{
			$types = array_values(array_intersect($types, $only));
		}

		return collect($types)
						->map(function (string $type)
						{
							return [
								'value' => $type,
								'label' => match ($type)
								{
									Product::TYPE_PLAN_REGULAR	=> 'Plan regular',
									Product::TYPE_PLAN_CAPITADO => 'Plan capitado',
									default						=> ucfirst(str_replace('_', ' ', $type)),
								},
							];
						})
						->values()
						->all();
	}
}
