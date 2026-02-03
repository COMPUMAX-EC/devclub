<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CapitatedContract;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapitatedContractController extends Controller
{
    /**
     * Listado paginado de contratos (suscripciones) para una company.
     *
     * Por defecto solo contratos activos.
     * Puede filtrar por product_id opcionalmente.
     */
    public function index(Company $company, Request $request): JsonResponse
    {
        $status    = $request->query('status', CapitatedContract::STATUS_ACTIVE);
        $productId = $request->query('product_id');
        $q         = trim((string) $request->query('q', ''));

        $query = CapitatedContract::query()
            ->with(['person', 'product'])
            ->where('company_id', $company->id);

        if ($status) {
            $query->where('status', $status);
        }

        if ($productId) {
            $query->where('product_id', $productId);
        }

        // Nombre real de la tabla (evita hardcodear capitados/capitated)
        $table = $query->getModel()->getTable();

        // Filtro de búsqueda (AJAX, con debounce en Vue)
        //
        // Busca en:
        // - número de contrato (id)
        // - persona.full_name
        // - persona.document_number
        // - país residencia del ÚLTIMO monthly record
        // - país repatriación del ÚLTIMO monthly record
        if ($q !== '') {
            $qLower = mb_strtolower($q, 'UTF-8');
            $like   = '%' . $qLower . '%';

            $query->where(function ($w) use ($q, $like, $table) {
                // Número de contrato (id) exacto si es numérico
                if (ctype_digit($q)) {
                    $w->orWhere($table . '.id', (int) $q);
                }

                // Persona: nombre y documento
                $w->orWhereHas('person', function ($p) use ($like) {
                    $p->whereRaw('LOWER(full_name) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(document_number) LIKE ?', [$like]);
                });

                // Países en el ÚLTIMO monthly record del contrato
                $w->orWhereHas('monthlyRecords', function ($m) use ($like) {
                    $monthlyTable = $m->getModel()->getTable();

                    // Restringir a UN solo registro: el último por contrato
                    // (mismo criterio que el dashboard: coverage_month DESC, id DESC)
                    $m->whereRaw($monthlyTable . '.id = (
                        SELECT m2.id
                        FROM ' . $monthlyTable . ' AS m2
                        WHERE m2.contract_id = ' . $monthlyTable . '.contract_id
                        ORDER BY m2.coverage_month DESC, m2.id DESC
                        LIMIT 1
                    )');

                    // Dentro de ese último registro, buscar por nombre de país
                    $m->where(function ($mm) use ($like) {
                        $mm->whereHas('residenceCountry', function ($c) use ($like) {
                                $c->whereRaw('LOWER(name) LIKE ?', [$like]);
                            })
                           ->orWhereHas('repatriationCountry', function ($c) use ($like) {
                                $c->whereRaw('LOWER(name) LIKE ?', [$like]);
                            });
                    });
                });
            });
        }

        // Respeta el per_page que manda Vue (por defecto 15 si no viene).
        $perPage = (int) $request->query('per_page', 15);
        if ($perPage <= 0) {
            $perPage = 15;
        }

        $contracts = $query
            ->orderByDesc($table . '.id')
            ->paginate($perPage);

        $payload = [
            'data' => $contracts->items(),
            'meta' => [
                'current_page' => $contracts->currentPage(),
                'last_page'    => $contracts->lastPage(),
                'per_page'     => $contracts->perPage(),
                'total'        => $contracts->total(),
            ],
        ];

        return response()->json($payload);
    }

	/**
	 * Dashboard de suscripción (para modal).
	 *
	 * Devuelve el contrato y su ÚLTIMO registro mensual ya resuelto,
	 * incluyendo los países (Countries) asociados al registro mensual.
	 */
	public function show(Company $company, CapitatedContract $contract): JsonResponse
	{
		if ($contract->company_id !== $company->id) {
			abort(404);
		}

		$contract->load([
			'person',
			'product',
		]);

		// Obtenemos el último registro mensual con una consulta directa,
		// evitando el limit(1) dentro de un eager load de hasMany.
		$lastMonthlyRecord = $contract->monthlyRecords()
			->with(['residenceCountry', 'repatriationCountry'])
			->orderByDesc('coverage_month')
			->orderByDesc('id')
			->first();

		return response()->json([
			'contract'            => $contract,
			'last_monthly_record' => $lastMonthlyRecord,
		]);
	}

    /**
     * Muestra un contrato capitado usando su uuid.
     *
     * Acceso por URL pública interna (no por ID).
     */
    public function showPdfByUuid(string $uuid, Request $request, \App\Services\PdfService $pdf, PlanVersionController $planVersionController)
    {
		/* @var $contract CapitatedContract */
        $contract = CapitatedContract::query()
            ->where('uuid', $uuid)
            ->whereIn('status', [CapitatedContract::STATUS_ACTIVE, CapitatedContract::STATUS_EXPIRED])
            ->first();

        if (!$contract) {
            abort(404, 'Contrato no encontrado.');
        }
		
        $contract->load([
            'person',
            'product',
        ]);

		// Obtenemos el último registro mensual con una consulta directa,
		// evitando el limit(1) dentro de un eager load de hasMany.
		$lastMonthlyRecord = $contract->monthlyRecords()
			->with(['residenceCountry', 'repatriationCountry'])
			->orderByDesc('coverage_month')
			->orderByDesc('id')
			->first();
		
        if (!$lastMonthlyRecord) {
            abort(404, 'Registros mensuales no encontrado.');
        }
		
		$company = $lastMonthlyRecord->company;
		
		$planVersion = $lastMonthlyRecord->planVersion;
		$planVersion->load([
			'product',
			'coverages.coverage.unit',
			'coverages.coverage.category',
		]);
		
		$product = $planVersion->product;

		$coverageCategories = $planVersionController->buildCoverageCategories($planVersion);

		$persona = $contract->person;
		$data = [
			'product'            => $product,
			'planVersion'        => $planVersion,
			'coverageCategories' => $coverageCategories,
			'contrato'			 => [
				'id'			=> "{$contract->company->short_code}-".sprintf('%05d', $contract->id),
				'full_name'		=> $persona->full_name,
				'age'			=> $persona->age_reported." años",
				'fecha_nacimiento' => null,//$contract->created_at->format('d/m/Y'),
				'repatriation'  => $lastMonthlyRecord->repatriationCountry->name,
				'residence'     => $lastMonthlyRecord->residenceCountry->name,
				'document'      => $persona->document_number,
				'date_start'    => $contract->entry_date->format('d/m/Y'),
				'date_end'      => $contract->valid_until->format('d/m/Y'),
				'emision'		=> $contract->created_at->format('d/m/Y H:i'),
				'prefix'	    => $contract->company->short_code,
				'emitido_por'	=> $company->name,
				'codigo_plan'	=> "{$product->id}v{$planVersion->id}",
				'renovacion'	=> $lastMonthlyRecord->id,
			],
		];
		
		$data['branding'] = $company->branding();
		$data['branding']['logo'] = $data['branding']['logo']->localPath();

		/* @var $company Company */
		/* @var $template \App\Models\Template */
		$template = $company->pdfTemplate ?? \App\Models\Template::searchTemplate("principal");
		if(empty($template))
		{
            abort(500);
		}
		
		/* @var $templateVersion \App\Models\TemplateVersion */
		$templateVersion = $template->activeTemplateVersion;
		if(empty($templateVersion))
		{
            abort(504);
		}
		
		//dd($data['contrato']);
		
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

}
