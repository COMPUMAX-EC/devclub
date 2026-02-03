<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coverage;
use App\Models\CoverageCategory;
use App\Models\UnitOfMeasure;
use App\Support\Breadcrumbs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoverageCatalogController extends Controller
{
    /**
     * Página principal del catálogo de coberturas.
     */
    public function index(Request $request)
    {
        $categories = CoverageCategory::query()
            ->where('status', 'active')
            ->with([
                'coverages' => function ($q) {
                    $q->orderBy('sort_order')
                      ->orderBy('id')
                      ->with('unit'); // Coverage::unit() -> UnitOfMeasure
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Usamos tu modelo real UnitOfMeasure + scope active() + tabla units_of_measure
        $units = UnitOfMeasure::query()
            ->active()
            ->orderBy('id')
            ->get();

        // Estos SON los nombres que la vista / Vue esperan
        $initialCategories = $categories
            ->map(fn (CoverageCategory $cat) => $this->serializeCategory($cat, true))
            ->all();

        $initialUnits = $units
            ->map(fn (UnitOfMeasure $unit) => $this->serializeUnit($unit))
            ->all();
		
		Breadcrumbs::add('Coberturas', route('admin.coverages.index'));
		
        return view('admin.coverages.index', [
            'initialCategories' => $initialCategories,
            'initialUnits'      => $initialUnits,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers de serialización
    // -------------------------------------------------------------------------

    protected function serializeUnit(?UnitOfMeasure $unit): ?array
    {
        if (!$unit) {
            return null;
        }

        return [
            'id'           => $unit->id,
            'name'         => $unit->getTranslations('name'),
            'description'  => $unit->getTranslations('description'),
            'measure_type' => $unit->measure_type, // integer, decimal, text, none
            'status'       => $unit->status,
        ];
    }

    protected function serializeCoverage(Coverage $coverage): array
    {
        return [
            'id'          => $coverage->id,
            'category_id' => $coverage->category_id,
            'unit_id'     => $coverage->unit_id,
            'name'        => $coverage->getTranslations('name'),
            'description' => $coverage->getTranslations('description'),
            'status'      => $coverage->status,
            'sort_order'  => $coverage->sort_order,
            'unit'        => $this->serializeUnit($coverage->unit),
        ];
    }

    protected function serializeCategory(CoverageCategory $category, bool $withCoverages = false): array
    {
        $data = [
            'id'          => $category->id,
            'name'        => $category->getTranslations('name'),
            'description' => $category->getTranslations('description'),
            'status'      => $category->status,
            'sort_order'  => $category->sort_order,
        ];

        if ($withCoverages) {
            $data['coverages'] = $category->coverages
                ->sortBy('sort_order')
                ->values()
                ->map(fn (Coverage $cov) => $this->serializeCoverage($cov))
                ->all();
        }

        return $data;
    }

    protected function decodeLocalizedName(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $arr = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($arr)) {
            return $raw;
        }

        $loc = app()->getLocale();
        return $arr[$loc]
            ?? $arr['es']
            ?? $arr['en']
            ?? reset($arr);
    }

    // -------------------------------------------------------------------------
    // Categorías
    // -------------------------------------------------------------------------

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name.es'        => ['required', 'string', 'max:255'],
            'name.en'        => ['nullable', 'string', 'max:255'],
            'description.es' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
        ]);

        $nextOrder = (int) CoverageCategory::max('sort_order') + 1;

        $cat = new CoverageCategory();
        $cat->name        = $data['name'];
        $cat->description = $data['description'] ?? [];
        $cat->status      = 'active';
        $cat->sort_order  = $nextOrder;
        $cat->save();

        return response()->json([
            'data' => $this->serializeCategory($cat, false),
        ]);
    }

    public function updateCategory(CoverageCategory $category, Request $request)
    {
        $data = $request->validate([
            'name.es'        => ['required', 'string', 'max:255'],
            'name.en'        => ['nullable', 'string', 'max:255'],
            'description.es' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
        ]);

        $category->name        = $data['name'];
        $category->description = $data['description'] ?? [];
        $category->save();

        return response()->json([
            'data' => $this->serializeCategory($category, false),
        ]);
    }

    public function archiveCategory(CoverageCategory $category)
    {
        $category->status = 'archived';
        $category->save();

        return response()->json([
            'message' => 'Categoría archivada correctamente.',
        ]);
    }

    public function restoreCategory(CoverageCategory $category)
    {
        $category->status = 'active';
        $category->save();

        // Al restaurar, cargamos coberturas para que Vue pueda pintar todo sin refrescar
        $category->load(['coverages.unit']);

        return response()->json([
            'data' => $this->serializeCategory($category, true),
        ]);
    }

    public function archivedCategories(Request $request)
    {
        $categories = CoverageCategory::query()
            ->where('status', 'archived')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CoverageCategory $cat) => $this->serializeCategory($cat, false));

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Reordenar coberturas dentro de una categoría (drag & drop).
     */
    public function reorderCoverages(CoverageCategory $category, Request $request)
    {
        $data = $request->validate([
            'items'              => ['required', 'array'],
            'items.*.id'         => ['required', 'integer', 'distinct'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        $items = collect($data['items']);
        $ids   = $items->pluck('id')->all();

        $coverages = Coverage::query()
            ->where('category_id', $category->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $cov = $coverages->get($item['id']);
            if (!$cov) {
                continue;
            }
            $cov->sort_order = (int) $item['sort_order'];
            $cov->save();
        }

        return response()->json([
            'message' => 'Orden actualizado.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Coberturas
    // -------------------------------------------------------------------------

    public function storeCoverage(Request $request)
    {
        $data = $request->validate([
            'category_id'      => ['required', 'integer', 'exists:coverage_categories,id'],
            // tabla real: units_of_measure
            'unit_id'          => ['required', 'integer', 'exists:units_of_measure,id'],
            'name.es'          => ['required', 'string', 'max:255'],
            'name.en'          => ['nullable', 'string', 'max:255'],
            'description.es'   => ['nullable', 'string'],
            'description.en'   => ['nullable', 'string'],
        ]);

        $categoryId = $data['category_id'];

        $nextOrder = (int) Coverage::where('category_id', $categoryId)->max('sort_order') + 1;

        $coverage = new Coverage();
        $coverage->category_id = $categoryId;
        $coverage->unit_id     = $data['unit_id'];
        $coverage->name        = $data['name'];
        $coverage->description = $data['description'] ?? [];
        $coverage->status      = 'active';
        $coverage->sort_order  = $nextOrder;
        $coverage->save();

        $coverage->load('unit');

        return response()->json([
            'data' => $this->serializeCoverage($coverage),
        ]);
    }

    public function updateCoverage(Coverage $coverage, Request $request)
    {
        $data = $request->validate([
            'category_id'      => ['required', 'integer', 'exists:coverage_categories,id'],
            'unit_id'          => ['required', 'integer', 'exists:units_of_measure,id'],
            'name.es'          => ['required', 'string', 'max:255'],
            'name.en'          => ['nullable', 'string', 'max:255'],
            'description.es'   => ['nullable', 'string'],
            'description.en'   => ['nullable', 'string'],
        ]);

        $coverage->category_id = $data['category_id'];
        $coverage->unit_id     = $data['unit_id'];
        $coverage->name        = $data['name'];
        $coverage->description = $data['description'] ?? [];
        $coverage->save();

        $coverage->load('unit');

        return response()->json([
            'data' => $this->serializeCoverage($coverage),
        ]);
    }

    public function archiveCoverage(Coverage $coverage)
    {
        $coverage->status = 'archived';
        $coverage->save();

        return response()->json([
            'message' => 'Cobertura archivada correctamente.',
        ]);
    }

    public function restoreCoverage(Coverage $coverage)
    {
        $coverage->status = 'active';
        $coverage->save();
        $coverage->load('unit');

        return response()->json([
            'data' => $this->serializeCoverage($coverage),
        ]);
    }

    public function destroyCoverage(Coverage $coverage)
    {
        // Gancho para validar si está en uso
        if (method_exists($coverage, 'productVersionCoverages')) {
            if ($coverage->productVersionCoverages()->exists()) {
                return response()->json([
                    'message' => 'La cobertura está en uso en uno o más planes/versión. Archívala en lugar de eliminarla.',
                ], 409);
            }
        }

        $coverage->delete();

        return response()->json([
            'message' => 'Cobertura eliminada.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Usos de cobertura en planes / versiones
    // -------------------------------------------------------------------------

    public function coverageUsages(Coverage $coverage)
    {
        $rows = DB::table('product_version_coverages as pvc')
            ->join('product_versions as pv', 'pv.id', '=', 'pvc.product_version_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('pvc.coverage_id', $coverage->id)
            ->selectRaw('
                pvc.product_version_id,
                pv.id as version_id,
                pv.product_id,
                p.name as product_name
            ')
            ->orderBy('p.id')
            ->orderBy('pv.id')
            ->get();

        $usages = $rows->map(function ($row) {
            return [
                'product_version_id' => $row->product_version_id,
                'version_id'         => $row->version_id,
                'product_id'         => $row->product_id,
                'product_name'       => $this->decodeLocalizedName($row->product_name),
                'product_link'       => null,
                'version_link'       => null,
            ];
        });

        return response()->json([
            'data' => $usages,
        ]);
    }
}
