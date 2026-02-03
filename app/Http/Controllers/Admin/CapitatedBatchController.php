<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CapitatedBatchItemLog;
use App\Models\CapitatedBatchLog;
use App\Models\CapitatedMonthlyRecord;
use App\Models\Company;
use App\Models\Product;
use App\Services\Capitated\CapitatedBatchProcessor;
use App\Support\Breadcrumbs;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CapitatedBatchController extends Controller
{
    public function __construct(
        protected CapitatedBatchProcessor $batchProcessor
    ) {
    }

    /**
     * Listado paginado de batches por company.
     */
    public function index(Company $company, Request $request): JsonResponse
    {
        $query = CapitatedBatchLog::query()
            ->with('file')
            ->where('company_id', $company->id)
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Respeta el per_page enviado por el front (Vue).
        $perPage = (int) $request->query('per_page', 15);
        if ($perPage <= 0) {
            $perPage = 15;
        }

        $batches = $query->paginate($perPage);

        $data = $batches->getCollection()->map(function (CapitatedBatchLog $batch) {
            return array_merge($batch->toArray(), [
                'file_temporary_url' => $batch->file ? $batch->file->temporaryUrl() : null,
            ]);
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $batches->currentPage(),
                'last_page'    => $batches->lastPage(),
                'per_page'     => $batches->perPage(),
                'total'        => $batches->total(),
            ],
        ]);
    }

    /**
     * Descarga una plantilla Excel con una pestaña por producto capitado:
     * - Producto activo
     * - Con versión activa (PlanVersion status=active)
     *
     * Encabezados por hoja:
     * ID | Nombre | Residencia | Nacionalidad | Sexo | Edad
     *
     * Nombre de hoja:
     * (id producto) Nombre producto
     */
    public function template(Company $company)
    {
        $products = $company->capitatedProducts()
            ->where('status', Product::STATUS_ACTIVE)
            ->with(['activePlanVersion'])
            ->orderBy('id')
            ->get()
            ->filter(fn (Product $p) => (bool) $p->activePlanVersion)
            ->values();

        $headers = ['ID', 'Nombre', 'Residencia', 'Nacionalidad', 'Sexo', 'Edad'];

        // Anchos de columnas según requerimiento (aprox. como la captura)
        $columnWidths = [
            'A' => 10, // ID
            'B' => 38, // Nombre
            'C' => 18, // Residencia
            'D' => 18, // Nacionalidad
            'E' => 19, // Sexo
            'F' => 12, // Edad
        ];

        $spreadsheet = new Spreadsheet();

        // Eliminar hoja por defecto
        $spreadsheet->removeSheetByIndex(0);

        $usedTitles = [];

        if ($products->isEmpty()) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Sin productos');

            // Encabezados
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:F1')->getFont()->setBold(true);
            $sheet->freezePane('A2');

            // Anchos de columnas
            foreach ($columnWidths as $col => $width) {
                $sheet->getColumnDimension($col)->setWidth($width);
            }
        } else {
            foreach ($products as $product) {
                $sheet = $spreadsheet->createSheet();

                $productName = trim((string) ($product->name ?? ''));
                if ($productName === '') {
                    $productName = 'Producto';
                }

                $baseTitle = '(' . $product->id . ') ' . $productName;
                $title = $this->makeUniqueExcelSheetTitle($baseTitle, $usedTitles);

                $sheet->setTitle($title);

                // Encabezados
                $sheet->fromArray($headers, null, 'A1');
                $sheet->getStyle('A1:F1')->getFont()->setBold(true);
                $sheet->freezePane('A2');

                // Anchos de columnas
                foreach ($columnWidths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            }
        }

        // Activar la primera hoja
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'capitados_estructura_company_' . $company->id . '_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Normaliza, acota a 31 caracteres, evita caracteres inválidos y asegura unicidad.
     */
    protected function makeUniqueExcelSheetTitle(string $baseTitle, array &$usedTitles): string
    {
        // Caracteres inválidos en nombres de hoja: \ / ? * [ ] :
        $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $baseTitle) ?? $baseTitle;
        $title = preg_replace('/\s+/', ' ', $title) ?? $title;
        $title = trim($title);

        if ($title === '') {
            $title = 'Hoja';
        }

        // Excel limita a 31 caracteres
        $title = mb_substr($title, 0, 31);

        $candidate = $title;
        $i = 2;

        while (isset($usedTitles[$candidate])) {
            $suffix = ' (' . $i . ')';
            $maxLen = 31 - mb_strlen($suffix);
            $prefix = $maxLen > 0 ? mb_substr($title, 0, $maxLen) : '';
            $candidate = $prefix . $suffix;
            $i++;
        }

        $usedTitles[$candidate] = true;

        return $candidate;
    }

    /**
     * Detalle de batch:
     * - Si la petición espera JSON (axios), devuelve payload JSON.
     * - Si es navegación normal, devuelve la vista Blade.
     */
    public function show(Company $company, CapitatedBatchLog $batch, Request $request)
    {
        if ($batch->company_id !== $company->id) {
            abort(404);
        }

        $batch->load(['file', 'createdBy']);

        // calcular flag de elegibilidad de rollback para el header (Vue y Blade)
        $batch->setAttribute('can_rollback', $this->canRollbackBatch($batch));

        // URL temporal del archivo fuente para la vista Vue
        $batch->setAttribute(
            'file_temporary_url',
            $batch->file ? $batch->file->temporaryUrl() : null
        );

        Breadcrumbs::add('Empresas', route('admin.companies.index'));
        Breadcrumbs::add($company->name, route('admin.companies.capitated-products.index', $company));
        Breadcrumbs::add('Lote #' . $batch->id);

        $payload = [
            'batch' => $batch->toArray(),
        ];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($payload);
        }

        return view('admin.capitated.batches.show', [
            'company' => $company,
            'batch'   => $batch,
        ]);
    }

    /**
     * Items por fila de un batch (para Página Batch o modal).
     *
     * Filtros soportados:
     * - result: applied / rejected / duplicated / incongruence
     * - sheet: nombre exacto de la hoja (sheet_name)
     *
     * Siempre devuelve:
     * - data: items de la hoja filtrada
     * - meta: paginación
     * - sheets: lista de todas las hojas del batch (distinct)
     * - filters: filtros aplicados (result, sheet)
     */
    public function items(Company $company, CapitatedBatchLog $batch, Request $request): JsonResponse
    {
        if ($batch->company_id !== $company->id) {
            abort(404);
        }

        // Lista de hojas disponibles para este batch (sin filtrar por resultado)
        $sheets = CapitatedBatchItemLog::query()
            ->where('batch_id', $batch->id)
            ->select('sheet_name')
            ->distinct()
            ->orderBy('sheet_name')
            ->pluck('sheet_name')
            ->values();

        $result        = $request->query('result'); // applied / rejected / ...
        $requestedSheet = $request->query('sheet'); // nombre de hoja enviado por el front

        // Determinar hoja seleccionada:
        // - si viene "sheet" y existe en $sheets => se respeta
        // - si no viene o es inválida => primera hoja de la lista (si existe)
        $selectedSheet = null;
        if ($requestedSheet && $sheets->contains($requestedSheet)) {
            $selectedSheet = $requestedSheet;
        } elseif ($sheets->isNotEmpty()) {
            $selectedSheet = $sheets->first();
        }

        $query = CapitatedBatchItemLog::query()
            ->with(['residenceCountry', 'repatriationCountry'])
            ->where('batch_id', $batch->id)
            ->orderBy('sheet_name')
            ->orderBy('row_number');

        if ($result) {
            $query->where('result', $result);
        }

        if ($selectedSheet !== null) {
            $query->where('sheet_name', $selectedSheet);
        }

        // También aquí respetamos per_page si viene del front.
        $perPage = (int) $request->query('per_page', 25);
        if ($perPage <= 0) {
            $perPage = 25;
        }

        $items = $query->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
            ],
            'sheets'  => $sheets,
            'filters' => [
                'result' => $result,
                'sheet'  => $selectedSheet,
            ],
        ]);
    }

    /**
     * Registros mensuales generados por el batch (para la pestaña "Cargas mensuales").
     *
     * Filtros soportados:
     * - status: active / rolled_back
     * - product_id: ID de producto
     *
     * Devuelve además:
     * - products: lista de productos usados en el batch (id, name)
     * - filters: filtros aplicados (status, product_id)
     */
    public function monthlyRecords(Company $company, CapitatedBatchLog $batch, Request $request): JsonResponse
    {
        if ($batch->company_id !== $company->id) {
            abort(404);
        }

        $status    = $request->query('status');
        $productId = $request->query('product_id');

        $query = CapitatedMonthlyRecord::query()
            ->with(['person', 'residenceCountry', 'repatriationCountry'])
            ->where('company_id', $company->id)
            ->where('load_batch_id', $batch->id);

        // Filtro de estado:
        // - status = null o "active" -> vigentes
        // - "rolled_back" -> revertidos
        // - vacío -> todos (vigentes + revertidos)
        if ($status === CapitatedMonthlyRecord::STATUS_ROLLED_BACK) {
            // Solo registros revertidos
            $query->where('status', CapitatedMonthlyRecord::STATUS_ROLLED_BACK);
        } elseif ($status === CapitatedMonthlyRecord::STATUS_ACTIVE) {
            // Solo vigentes (ACTIVE)
            $query->where('status', CapitatedMonthlyRecord::STATUS_ACTIVE);
        }
        // Si no viene status: no se filtra por estado → incluye todos.

        // Filtro por producto (capitados_monthly_records.product_id)
        if ($productId !== null && $productId !== '') {
            $query->where('product_id', (int) $productId);
        }

        $perPage = (int) $request->query('per_page', 25);
        if ($perPage <= 0) {
            $perPage = 25;
        }

        $paginator = $query->paginate($perPage);

        $rows = $paginator->getCollection()->map(function (CapitatedMonthlyRecord $record) {
            $data = $record->toArray();
            $data['can_rollback'] = $this->canRollbackMonthlyRecord($record);

            return $data;
        });

        // Lista de productos disponibles para este batch (para el filtro)
        $productIds = CapitatedMonthlyRecord::query()
            ->where('company_id', $company->id)
            ->where('load_batch_id', $batch->id)
            ->whereNotNull('product_id')
            ->select('product_id')
            ->distinct()
            ->orderBy('product_id')
            ->pluck('product_id');

        if ($productIds->isEmpty()) {
            $products = [];
        } else {
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(function (Product $product) {
                    return [
                        'id'   => $product->id,
                        'name' => $product->name,
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
            'products' => $products,
            'filters'  => [
                'status'     => $status,
                'product_id' => ($productId !== null && $productId !== '') ? (int) $productId : null,
            ],
        ]);
    }

    /**
     * Rollback completo del batch:
     * - Marca como "rolled_back" todas las cargas mensuales vigentes del lote.
     * - Marca el propio batch como "rolled_back".
     */
    public function rollback(Company $company, CapitatedBatchLog $batch, Request $request): JsonResponse
    {
        if ($batch->company_id !== $company->id) {
            abort(404);
        }

        if (! $this->canRollbackBatch($batch)) {
            return response()->json([
                'message' => 'El lote no es elegible para rollback.',
            ], 422);
        }

        $user   = $request->user();
        $userId = $user?->id;

        try {
            $batch = $this->batchProcessor->rollbackBatch($company, $batch, (int) $userId);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $batch->setAttribute('can_rollback', false);

        return response()->json([
            'batch' => $batch->toArray(),
        ]);
    }

    /**
     * Rollback de un registro mensual concreto del batch.
     */
    public function rollbackMonthlyRecord(
        Company $company,
        CapitatedBatchLog $batch,
        int $record,
        Request $request
    ): JsonResponse {
        if ($batch->company_id !== $company->id) {
            abort(404);
        }

        // Incluimos también los revertidos en la búsqueda, para poder dar 422 en vez de 404,
        // pero ya no dependemos de ningún scope global.
        $monthlyRecord = CapitatedMonthlyRecord::query()
            ->where('company_id', $company->id)
            ->where('load_batch_id', $batch->id)
            ->where('id', $record)
            ->firstOrFail();

        if (! $this->canRollbackMonthlyRecord($monthlyRecord)) {
            return response()->json([
                'message' => 'El registro no es elegible para rollback.',
            ], 422);
        }

        $user   = $request->user();
        $userId = $user?->id;

        try {
            $this->batchProcessor->rollbackMonthlyRecord(
                $company,
                $batch,
                $monthlyRecord,
                (int) $userId,
                true // rollback puntual: recalcula estadísticas del batch
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Registro mensual revertido correctamente.',
        ]);
    }

    /**
     * Upload Excel + creación/procesamiento de batch.
     *
     * Por simplicidad, aquí solo delego al servicio; la lectura real de Excel
     * está pendiente de implementación dentro de CapitatedBatchProcessor.
     */
    public function upload(Company $company, Request $request): JsonResponse
    {
        /** @var \Illuminate\Http\UploadedFile|null $file */
        $file = $request->file('file');

        $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx'],
            'coverage_month' => ['nullable', 'date'],
        ]);

        $user = $request->user();

        // Requiere al menos uno de estos permisos para poder crear batch
        if (!$user || (!$user->can('capitados.batch.create') && !$user->can('capitados.batch.create_any_month'))) {
            abort(403, 'No tiene permisos para crear batches de capitados.');
        }

        $isAnyMonthAllowed = $user->can('capitados.batch.create_any_month');
        $cutoffDay = 15; // Día de corte fijo según requerimiento

        // Mes de cobertura (si no viene, se toma el actual)
        $coverageMonth = $request->input('coverage_month')
            ? Carbon::parse($request->input('coverage_month'))
            : Carbon::now();

        // Normalizar a YYYY-MM-01
        $coverageMonth = $this->batchProcessor->normalizeCoverageMonth($coverageMonth);

        // Validación de ventana de carga cuando NO tiene permiso "cualquier mes"
        if (!$isAnyMonthAllowed) {
            $now = Carbon::now();
            $currentMonth = $this->batchProcessor->normalizeCoverageMonth($now);

            // Solo se permite el mes en curso
            if ($coverageMonth->format('Y-m') !== $currentMonth->format('Y-m')) {
                return response()->json([
                    'message' => 'Solo se permite cargar el mes en curso.',
                ], 422);
            }

            // Y dentro de la ventana (hasta el día 15 inclusive)
            if ($now->day > $cutoffDay) {
                return response()->json([
                    'message' => 'La ventana de carga para el mes en curso ha expirado.',
                ], 422);
            }
        }

        /** @var UploadedFile $file */
        $file = $file;

        if (!$file->isValid()) {
            return response()->json([
                'message' => 'El archivo subido no es válido.',
            ], 422);
        }

        $userId = (int) $user->id;

        // El servicio se encarga de:
        // - Guardar el archivo
        // - Crear CapitatedBatchLog
        // - Leer el Excel
        // - Aplicar filas y crear registros mensuales / contratos / logs
        $batch = $this->batchProcessor->processExcel(
            $company,
            $file,
            $coverageMonth,
            $isAnyMonthAllowed,
            $cutoffDay,
            $userId
        );

        return response()->json([
            'batch' => $batch,
        ]);
    }

    /**
     * Un registro mensual es elegible para rollback según la lógica de dominio
     * definida en CapitatedBatchProcessor.
     */
    protected function canRollbackMonthlyRecord(CapitatedMonthlyRecord $record): bool
    {
        return $this->batchProcessor->canRollbackMonthlyRecord($record);
    }

    /**
     * El batch es elegible para rollback si:
     * - Está en estado "processed".
     * - No tiene rollback previo.
     * - Todas las cargas mensuales vigentes generadas por él son rollbackeables (último mes por contrato).
     */
    protected function canRollbackBatch(CapitatedBatchLog $batch): bool
    {
        return $this->batchProcessor->canRollbackBatch($batch);
    }
}
