<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CapitatedMonthlyRecord;
use App\Models\Company;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

class CapitatedMonthlyReportController extends Controller
{
    /**
     * Lista de meses disponibles para reportes, con agregados:
     * - active_count: cantidad de registros STATUS_ACTIVE por mes
     * - active_total: suma de price_final de registros STATUS_ACTIVE por mes
     */
    public function months(Company $company, Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->can('capitados.reporte.mensual')) {
            abort(403);
        }

        $statusActive = CapitatedMonthlyRecord::STATUS_ACTIVE;

        $rows = CapitatedMonthlyRecord::query()
            ->where('company_id', $company->id)
            ->selectRaw('DATE_FORMAT(coverage_month, "%Y-%m-01") as month')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_count,
                 SUM(CASE WHEN status = ? THEN price_final ELSE 0 END) as active_total',
                [$statusActive, $statusActive]
            )
            ->groupBy('month')
            ->orderByDesc('month')
            ->get();

        $months = $rows->map(function ($row) {
            return [
                'month'        => $row->month,
                'active_count' => (int) $row->active_count,
                'active_total' => $row->active_total !== null ? (float) $row->active_total : 0.0,
            ];
        });

        return response()->json([
            'months' => $months,
        ]);
    }

    /**
     * Descarga del reporte mensual en Excel.
     */
    public function download(Company $company, string $month, Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->can('capitados.reporte.mensual')) {
            abort(403);
        }

        $coverageMonth = Carbon::parse($month)->startOfMonth();

        $records = CapitatedMonthlyRecord::query()
            ->with([
                'product',
                'residenceCountry',
                'repatriationCountry',
                'contract',
            ])
            ->where('company_id', $company->id)
            ->whereDate('coverage_month', $coverageMonth)
            ->orderBy('product_id')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($r) => $r->product_id . '|' . $r->status);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($records as $key => $rows) {
            $first   = $rows->first();
            $product = $first->product;

            $status = $first->status;
            $title  = '(' . $product->id . ') ' . $product->name;

            if ($status === CapitatedMonthlyRecord::STATUS_ROLLED_BACK) {
                $title = '(' . $product->id . ') ROLLED BACK - ' . $product->name;
            }

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle(mb_substr($title, 0, 31));

            $headers = [
                'ID',
                '# Contrato',
                'Mes',
                'ID Persona',
                'Persona',
                'Genero',
                'Edad reportada',
                'Residencia ISO3',
                'Residencia ISO2',
                'Residencia',
                'Repatriacion ISO3',
                'Repatriacion ISO2',
                'Repatriacion',
                'Fuente del precio',
                'Precio base',
                'Recargo por edad',
                'Precio total',
                'PDF URL',
            ];

            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:R1')->getFont()->setBold(true);

            // Letra de la última columna (en este caso será "R")
            $lastColumnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

            $rowIndex = 2;

            foreach ($rows as $r) {
                // Forzar 0 cuando no haya recargo definido
                $ageSurchargePercent = $r->age_surcharge_percent;
                if (empty($ageSurchargePercent)) {
                    $ageSurchargePercent = "0.00";
                }

                // URL al PDF del contrato
                $contract = $r->contract;
				$pdfUrl= "";

				if($contract->canBeExtended())
				{
                    $pdfUrl = route('capitated.contracts.show_pdf_by_uuid', $contract->uuid);
                }

                // Escribir la fila completa (incluyendo el texto del URL en la última columna)
                $sheet->fromArray([
                    $r->id,
                    $r->contract_id,
                    Carbon::parse($r->coverage_month)->format('m/Y'),
                    $r->person_id,
                    $r->full_name,
                    $r->sex,
                    $r->age_reported,
                    $r->residenceCountry?->iso3,
                    $r->residenceCountry?->iso2,
                    $r->residenceCountry?->name,
                    $r->repatriationCountry?->iso3,
                    $r->repatriationCountry?->iso2,
                    $r->repatriationCountry?->name,
                    $r->price_source,
                    $r->price_base,
                    $ageSurchargePercent,
                    $r->price_final,
                    $pdfUrl,
                ], null, 'A' . $rowIndex);

				if($contract->canBeExtended())
				{
                    // Marcar la celda de la última columna como hyperlink
                    $cell = $sheet->getCell($lastColumnLetter . $rowIndex);
                    $cell->getHyperlink()->setUrl($pdfUrl);
                }

                $rowIndex++;
            }
        }

        $filename = 'capitados_reporte_' . $coverageMonth->format('Y_m') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename);
    }

}
