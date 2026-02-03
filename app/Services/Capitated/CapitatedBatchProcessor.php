<?php
// app/Services/Capitated/CapitatedBatchProcessor.php

namespace App\Services\Capitated;

use App\Models\CapitatedBatchItemLog;
use App\Models\CapitatedBatchLog;
use App\Models\CapitatedContract;
use App\Models\CapitatedMonthlyRecord;
use App\Models\CapitatedProductInsured;
use App\Models\Company;
use App\Models\Country;
use App\Models\File;
use App\Models\PlanVersion;
use App\Models\Product;
use App\Services\UploadedFileService;
use App\Support\CapitatedRejectionCodes;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CapitatedBatchProcessor
{
    public const SOURCE_EXCEL = 'excel';

    private UploadedFileService $uploadedFileService;

    public function __construct(UploadedFileService $uploadedFileService)
    {
        $this->uploadedFileService = $uploadedFileService;
    }

    /**
     * Procesa un Excel de carga mensual y devuelve el batch generado.
     *
     * - Crea un CapitatedBatchLog en estado draft.
     * - Lee el Excel y valida la estructura por hoja/producto/plan_version.
     * - Si hay errores de plan/estructura, marca el batch como failed.
     * - Si pasa la validación, procesa fila a fila:
     *   - Normaliza país residencia/repatriación
     *   - Crea/actualiza persona (CapitatedProductInsured)
     *   - Aplica validaciones básicas (sexo/edad/país permitido)
     *   - Aplica lógica de duplicados / retroactivo / continuidad simple
     *   - Crea/actualiza contratos y registros mensuales
     *   - Crea logs de ítems según flags .env
     *
     * No lanza excepciones hacia el controlador: siempre devuelve un CapitatedBatchLog
     * con status draft/processed/failed y campos de resumen rellenados.
     */
    public function processExcel(
        Company $company,
        UploadedFile $uploadedFile,
        Carbon $coverageMonth,
        bool $isAnyMonthAllowed,
        ?int $cutoffDay,
        int $userId
    ): CapitatedBatchLog {
        // Normalizar mes de cobertura a YYYY-MM-01
        $coverageMonth = $this->normalizeCoverageMonth($coverageMonth);

        if (!$uploadedFile->isValid()) {
            $batch = new CapitatedBatchLog([
                'company_id'           => $company->id,
                'coverage_month'       => $coverageMonth->toDateString(),
                'source'               => self::SOURCE_EXCEL,
                'source_file_id'       => null,
                'original_filename'    => $uploadedFile->getClientOriginalName(),
                'file_hash'            => null,
                'created_by_user_id'   => $userId,
                'status'               => CapitatedBatchLog::STATUS_FAILED,
                'is_any_month_allowed' => $isAnyMonthAllowed,
                'cutoff_day'           => $cutoffDay,
                'processed_at'         => Carbon::now(),
                'summary_json'         => json_encode([
                    'error_summary' => 'El archivo subido no es válido.',
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $batch->save();

            return $batch;
        }

        // 1) Crear batch en estado draft (aún sin asociar archivo ni hash)
        $batch = new CapitatedBatchLog([
            'company_id'           => $company->id,
            'coverage_month'       => $coverageMonth->toDateString(),
            'source'               => self::SOURCE_EXCEL,
            'source_file_id'       => null,
            'original_filename'    => $uploadedFile->getClientOriginalName(),
            'file_hash'            => null,
            'created_by_user_id'   => $userId,
            'status'               => CapitatedBatchLog::STATUS_DRAFT,
            'is_any_month_allowed' => $isAnyMonthAllowed,
            'cutoff_day'           => $cutoffDay,
        ]);

        $batch->save();

        // 2) Guardar archivo en Files usando la ruta definida por el modelo y obtener path local
        $storagePath = $batch->storagePath('source_file_id');

        /** @var File $fileModel */
        $fileModel = $this->uploadedFileService->store(
            $uploadedFile,
            null,
            $userId,
            null,
            $storagePath
        );

        $localPath = $this->uploadedFileService->getLocalPath($fileModel, true);

        $batch->source_file_id = $fileModel->id;
        $batch->file_hash      = is_file($localPath) ? sha1_file($localPath) : null;
        $batch->save();

        // 3) Intentar leer el Excel
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($localPath);
        } catch (\Throwable $e) {
            $batch->status       = CapitatedBatchLog::STATUS_FAILED;
            $batch->processed_at = Carbon::now();
            $batch->summary_json = json_encode([
                'error_summary' => 'No se pudo leer el archivo Excel: ' . mb_substr($e->getMessage(), 0, 500),
            ], JSON_UNESCAPED_UNICODE);
            $batch->save();

            return $batch;
        }

        // 4) Procesar dentro de una transacción para evitar efectos parciales
        try {
            DB::transaction(function () use ($batch, $company, $spreadsheet, $coverageMonth) {
                $this->processSpreadsheetRows($batch, $company, $spreadsheet, $coverageMonth);
				$this->refreshContractsStatus($company);
            });

            // Refrescar desde BD después de commit
            return $batch->fresh();
        } catch (\Throwable $e) {
            // Si dentro de processSpreadsheetRows marcamos el batch como failed y lanzamos excepción,
            // aquí solo persistimos ese estado. Si no lo marcamos, lo marcamos ahora como failed genérico.
            if ($batch->status !== CapitatedBatchLog::STATUS_FAILED) {
                $batch->status       = CapitatedBatchLog::STATUS_FAILED;
                $batch->processed_at = Carbon::now();
                $batch->summary_json = json_encode([
                    'error_summary' => mb_substr($e->getMessage(), 0, 1000),
                ], JSON_UNESCAPED_UNICODE);
            }

            $batch->save();

            return $batch->fresh();
        }
    }

    /**
     * Lógica principal fila a fila dentro de la transacción.
     */
    protected function processSpreadsheetRows(
        CapitatedBatchLog $batch,
        Company $company,
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        Carbon $coverageMonth
    ): void {
        // 1) Validar estructura por hoja/producto/plan_version
        [$sheetMetas, $planErrors] = $this->buildSheetMetas($company, $spreadsheet);

        if (!empty($planErrors)) {
            $batch->status              = CapitatedBatchLog::STATUS_FAILED;
            $batch->total_plan_errors   = count($planErrors);
            $batch->total_rows          = 0;
            $batch->total_applied       = 0;
            $batch->total_rejected      = 0;
            $batch->total_duplicated    = 0;
            $batch->total_incongruences = 0;
            $batch->total_rolled_back   = 0;
            $batch->processed_at        = Carbon::now();
            $batch->summary_json        = json_encode([
                'error_summary' => 'Errores de estructura/plan impiden procesar el archivo.',
                'plan_errors'   => $planErrors,
            ], JSON_UNESCAPED_UNICODE);

            // Lanzamos excepción para gatillar rollback de cualquier operación iniciada
            throw new \RuntimeException('Errores de estructura/plan en el Excel de capitados.');
        }

        // 2) Flags .env para logging de ítems
        $logApplied      = (bool) env('CAPITADOS_BATCH_ITEM_LOG_APPLIED', false);
        $logRejected     = (bool) env('CAPITADOS_BATCH_ITEM_LOG_REJECTED', true);
        $logIncongruence = (bool) env('CAPITADOS_BATCH_ITEM_LOG_INCONGRUENCE', true);
        $logDuplicated   = (bool) env('CAPITADOS_BATCH_ITEM_LOG_DUPLICATED', false);

        // 3) Contadores
        $totalRows          = 0;
        $totalApplied       = 0;
        $totalRejected      = 0;
        $totalDuplicated    = 0;
        $totalIncongruences = 0;

        $errorsByCode = [];

        foreach ($sheetMetas as $sheetName => $meta) {
            /** @var \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet */
            $sheet        = $meta['sheet'];
            /** @var Product $product */
            $product      = $meta['product'];
            /** @var PlanVersion $planVersion */
            $planVersion  = $meta['plan_version'];
            $columns      = $meta['columns'];
            $resAllowed   = $meta['residence_iso3'];
            $repAllowed   = $meta['repatriation_iso3'];
            $countryPrices = $meta['country_prices'];
            $ageSurcharges = $meta['age_surcharges'];

            $highestRow = $sheet->getHighestRow();
			logger("ROW", [$highestRow]);
            for ($row = 2; $row <= $highestRow; $row++) {
                // Leer valores de la fila
				$values = [
					'ID'           => $this->getCellString($sheet, $columns['ID'], $row),
					'NOMBRE'       => $this->getCellString($sheet, $columns['NOMBRE'], $row),
					'RESIDENCIA'   => $this->getCellString($sheet, $columns['RESIDENCIA'], $row),
					'NACIONALIDAD' => $this->getCellString($sheet, $columns['NACIONALIDAD'], $row),
					'SEXO'         => $this->getCellString($sheet, $columns['SEXO'], $row),
					'EDAD'         => $this->getCellString($sheet, $columns['EDAD'], $row),
				];
				
                // Fila totalmente vacía → ignorar
                if ($this->isRowEmpty($values)) {
                    break;
                }

                $totalRows++;

                $result          = 'applied';    // applied|rejected|incongruence|duplicated
                $rejectionCode   = null;
                $rejectionDetail = null;

                $person           = null;
                $contract         = null;
                $monthlyRecord    = null;
                $duplicatedRecord = null;

                $documentNumber = trim((string) $values['ID']);
                $fullName       = trim((string) $values['NOMBRE']);
                $sexRaw         = substr(strtoupper(trim((string) $values['SEXO'])), 0, 1);
                $ageRaw         = trim((string) $values['EDAD']);
                $resRaw         = $values['RESIDENCIA'];
                $repRaw         = $values['NACIONALIDAD'];

                // Validaciones básicas
                if ($documentNumber === '') {
                    $result          = 'rejected';
                    $rejectionCode   = CapitatedRejectionCodes::UNKNOWN_ERROR;
                    $rejectionDetail = 'El campo ID (número de documento) es obligatorio.';
                } elseif ($fullName === '') {
                    $result          = 'rejected';
                    $rejectionCode   = CapitatedRejectionCodes::UNKNOWN_ERROR;
                    $rejectionDetail = 'El campo Nombre es obligatorio.';
                } elseif (!in_array($sexRaw, ['M', 'F'], true)) {
                    $result          = 'rejected';
                    $rejectionCode   = CapitatedRejectionCodes::PERSON_SEX_INVALID;
                    $rejectionDetail = 'Sexo inválido, se espera M o F.';
                }

                $sex = $sexRaw;
                $age = null;

                // Edad
                if ($result === 'applied') {
                    if ($ageRaw === '' || !is_numeric($ageRaw)) {
                        $result          = 'rejected';
                        $rejectionCode   = CapitatedRejectionCodes::PERSON_AGE_INVALID;
                        $rejectionDetail = 'Edad inválida o vacía.';
                    } else {
                        $age = (int) $ageRaw;
                        if ($age < 0) {
                            $result          = 'rejected';
                            $rejectionCode   = CapitatedRejectionCodes::PERSON_AGE_INVALID;
                            $rejectionDetail = 'La edad debe ser mayor o igual a 0.';
                        }
                    }
                }

                // Países
                $resIso3     = null;
                $repIso3     = null;
                $resCountry  = null;
                $repCountry  = null;

                if ($result === 'applied') {
                    $resIso3 = $this->normalizeCountryToIso3($resRaw);
                    if (!$resIso3) {
                        $result          = 'rejected';
                        $rejectionCode   = CapitatedRejectionCodes::PERSON_COUNTRY_CODE_NOT_FOUND;
                        $rejectionDetail = 'No se pudo resolver país de residencia a ISO3.';
                    } else {
                        $resCountry = Country::where('iso3', $resIso3)->first();
                        if (!$resCountry) {
                            $result          = 'rejected';
                            $rejectionCode   = CapitatedRejectionCodes::PERSON_COUNTRY_CODE_NOT_FOUND;
                            $rejectionDetail = 'No se pudo resolver país de residencia a registro de país.';
                        }
                    }
                }

                if ($result === 'applied') {
                    $repIso3 = $this->normalizeCountryToIso3($repRaw);
                    if (!$repIso3) {
                        $result          = 'rejected';
                        $rejectionCode   = CapitatedRejectionCodes::PERSON_COUNTRY_CODE_NOT_FOUND;
                        $rejectionDetail = 'No se pudo resolver país de repatriación a ISO3.';
                    } else {
                        $repCountry = Country::where('iso3', $repIso3)->first();
                        if (!$repCountry) {
                            $result          = 'rejected';
                            $rejectionCode   = CapitatedRejectionCodes::PERSON_COUNTRY_CODE_NOT_FOUND;
                            $rejectionDetail = 'No se pudo resolver país de repatriación a registro de país.';
                        }
                    }
                }

                if ($result === 'applied') {
                    if (!isset($resAllowed[$resIso3])) {
                        $result          = 'rejected';
                        $rejectionCode   = CapitatedRejectionCodes::PERSON_RESIDENCE_NOT_ALLOWED;
                        $rejectionDetail = 'País de residencia no permitido para esta versión de plan.';
                    }
                }

                if ($result === 'applied' && !empty($repAllowed)) {
                    if (!isset($repAllowed[$repIso3])) {
                        $result          = 'rejected';
                        $rejectionCode   = CapitatedRejectionCodes::PERSON_REPATRIATION_NOT_ALLOWED;
                        $rejectionDetail = 'País de repatriación no permitido para esta versión de plan.';
                    }
                }

                // Persona: buscar/crear y detectar incongruencia de nombre/sexo
                if ($result === 'applied' || $result === 'incongruence') {
                    $person = CapitatedProductInsured::query()
                        ->where('company_id', $company->id)
                        ->where('product_id', $product->id)
                        ->where('document_number', $documentNumber)
						->where('status', CapitatedProductInsured::STATUS_ACTIVE)
                        ->first();

                    if ($person) {
                        $existingName = trim((string) $person->full_name);
                        $existingSex  = strtoupper((string) $person->sex);

                        if (
                            ($existingName !== '' && $existingName !== $fullName)
                            || ($existingSex !== '' && $existingSex !== $sex)
                        ) {
                            $result          = 'incongruence';
                            $rejectionCode   = CapitatedRejectionCodes::PERSON_INCONGRUENCE;
                            $rejectionDetail = 'Datos incongruentes con la ficha existente de la persona (nombre/sexo).';
                        } elseif ($result === 'applied') {
                            // Actualizar campos dinámicos si no hubo incongruencia
                            $person->full_name               = $fullName;
                            $person->sex                     = $sex;
                            $person->residence_country_id    = $resCountry ? $resCountry->id : null;
                            $person->repatriation_country_id = $repCountry ? $repCountry->id : null;
                            $person->age_reported            = $age;
                            $person->save();
                        }
                    } else {
                        if ($result === 'applied') {
                            $person = CapitatedProductInsured::create([
                                'company_id'			  => $company->id,
								'product_id'			  => $product->id,
								'document_number'		  => $documentNumber,
								'full_name'				  => $fullName,
								'sex'					  => $sex,
								'residence_country_id'	  => $resCountry ? $resCountry->id : null,
								'repatriation_country_id' => $repCountry ? $repCountry->id : null,
								'age_reported'			  => $age,
								'status'				  => CapitatedProductInsured::STATUS_ACTIVE,
							]);
                        }
                    }
                }

                // Si ya es rechazo o incongruencia, saltamos creación de contrato/registro mensual
                if ($result === 'applied' && $person) {
                    // 1) Duplicado exacto de mes (solo consideramos registros activos)
                    $existingRecord = CapitatedMonthlyRecord::query()
                        ->where('company_id', $company->id)
                        ->where('product_id', $product->id)
                        ->where('person_id', $person->id)
                        ->where('coverage_month', $coverageMonth->toDateString())
                        ->where('status', CapitatedMonthlyRecord::STATUS_ACTIVE)
                        ->first();

                    if ($existingRecord) {
                        $result           = 'duplicated';
                        $rejectionCode    = CapitatedRejectionCodes::PERSON_DUPLICATED;
                        $rejectionDetail  = 'Ya existe un registro aprobado para esta persona/producto y mes.';
                        $duplicatedRecord = $existingRecord;
                    } else {
                        // 2) Continuidad simple (último registro activo)
                        $lastRecord = CapitatedMonthlyRecord::query()
                            ->where('company_id', $company->id)
                            ->where('product_id', $product->id)
                            ->where('person_id', $person->id)
                            ->where('status', CapitatedMonthlyRecord::STATUS_ACTIVE)
                            ->orderByDesc('coverage_month')
                            ->orderByDesc('id')
                            ->first();

                        $contract        = null;
                        $continuityBreak = false;

                        if ($lastRecord) {
                            $lastMonth = Carbon::parse($lastRecord->coverage_month)->startOfMonth();

                            // Retroactivo → no permitido
                            if ($coverageMonth->lt($lastMonth)) {
                                $result          = 'rejected';
                                $rejectionCode   = CapitatedRejectionCodes::RETROACTIVE_NOT_ALLOWED;
                                $rejectionDetail = 'No se permite cargar retroactivo anterior al último mes aprobado.';
                            } else {
                                /** @var CapitatedContract|null $existingContract */
                                $existingContract = CapitatedContract::query()
								->where('id', $lastRecord->contract_id)
								->whereIn('status', [
									CapitatedContract::STATUS_ACTIVE,
									CapitatedContract::STATUS_EXPIRED,
								])
								->first();

                                // Quiebre de continuidad: hay meses en medio sin cargar
                                $expectedNext = (clone $lastMonth)->addMonthNoOverflow();
                                if ($coverageMonth->gt($expectedNext)) {
									if(!empty($planVersion->max_entry_age))
									{
										if ($age > $planVersion->max_entry_age)
										{
											$result          = 'rejected';
											$rejectionCode   = CapitatedRejectionCodes::PERSON_AGE_INVALID;
											$rejectionDetail = 'Excede edad máxima para contratacion';
										}
									}
									
									if($result == 'applied')
									{
										// Expirar contrato anterior si está activo
										if ($existingContract && $existingContract->status === CapitatedContract::STATUS_ACTIVE) {
											$existingContract->status             = CapitatedContract::STATUS_EXPIRED;
											$existingContract->terminated_at      = Carbon::now();
											$existingContract->termination_reason = 'Quiebre de continuidad al cargar el mes ' . $coverageMonth->format('Y-m') . '.';
											$existingContract->save();
										}

										// Crear nuevo contrato
										$contract = $this->createNewContract($company, $product, $person, $planVersion, $coverageMonth, $age);
	                                    $continuityBreak = true;
									}
                                } else {
                                    // Continuidad normal: mismo contrato, extendemos vigencia
                                    if ($existingContract)
									{
										if(!empty($planVersion->max_renewal_age))
										{
											if ($age > $planVersion->max_renewal_age)
											{
												$result          = 'rejected';
												$rejectionCode   = CapitatedRejectionCodes::PERSON_AGE_INVALID;
												$rejectionDetail = 'Excede edad máxima para renovación';
											}
										}
										
										if($result == 'applied')
										{
											$existingContract->status      = CapitatedContract::STATUS_ACTIVE;
											$existingContract->valid_until = $coverageMonth->copy()->endOfMonth()->toDateString();
											$existingContract->save();
											$contract = $existingContract;
										}
                                    } else {
                                        // De seguridad si por algún motivo no hay contrato asociado
										// puede ser porque justo el mes anterior estaba nulado
										if(!empty($planVersion->max_entry_age))
										{
											if ($age > $planVersion->max_entry_age)
											{
												$result          = 'rejected';
												$rejectionCode   = CapitatedRejectionCodes::PERSON_AGE_INVALID;
												$rejectionDetail = 'Excede edad máxima para contratación';
											}
										}
										
										if($result == 'applied')
										{
											$contract = $this->createNewContract($company, $product, $person, $planVersion, $coverageMonth, $age);
										}
                                    }
                                }

                                if ($continuityBreak) {
                                    // El resultado sigue siendo "applied", pero registramos el código en el detalle
                                    $rejectionCode   = CapitatedRejectionCodes::CONTINUITY_BREAK;
                                    $rejectionDetail = 'Se abrió un nuevo contrato por quiebre de continuidad (último mes ' . $lastMonth->format('Y-m') . ').';
                                }
                            }
                        } else {
                            // Primera vez: nuevo contrato
							if(!empty($planVersion->max_entry_age))
							{
								logger("",[$age, $planVersion->max_entry_age]);

								if ($age > $planVersion->max_entry_age)
								{
									$result          = 'rejected';
									$rejectionCode   = CapitatedRejectionCodes::PERSON_AGE_INVALID;
									$rejectionDetail = 'Excede edad máxima para contratación';
								}
							}
							
							if($result == 'applied')
							{
								$contract = $this->createNewContract($company, $product, $person, $planVersion, $coverageMonth, $age);
							}
                        }

                        // 3) Crear registro mensual y calcular precio solo si sigue siendo "applied"
                        if ($result === 'applied' && $contract) {
                            // Precio base: por país (pivot) o global (price_1)
                            $priceBase   = null;
                            $priceSource = null;

                            if (isset($countryPrices[$resIso3])) {
                                $priceBase   = (float) $countryPrices[$resIso3];
                                $priceSource = 'country';
                            } else {
                                $priceBase   = (float) ($planVersion->price_1 ?? 0);
                                $priceSource = 'global';
                            }

                            // Recargos por edad (simplificado):
                            $matchedRules = [];
                            foreach ($ageSurcharges as $rule) {
                                $from = $rule->age_from;
                                $to   = $rule->age_to;

                                if ($from === null || $to === null) {
                                    continue;
                                }

                                if ($age >= $from && $age <= $to) {
                                    $matchedRules[] = $rule;
                                }
                            }

                            $ageRuleId  = null;
                            $agePercent = null;
                            $ageAmount  = null;
                            $priceFinal = $priceBase;

                            if (count($matchedRules) > 1) {
                                // Incongruencia en configuración de recargos
                                $result          = 'incongruence';
                                $rejectionCode   = CapitatedRejectionCodes::PERSON_INCONGRUENCE;
                                $rejectionDetail = 'La edad coincide con más de un tramo de recargo en la versión de plan.';
                            } elseif (count($matchedRules) === 1) {
                                $rule       = $matchedRules[0];
                                $ageRuleId  = $rule->id;
                                $agePercent = (float) $rule->surcharge_percent;
                                $priceFinal = round($priceBase*(100.00+$agePercent)/100, 2);
                                $ageAmount  = $priceFinal - $priceBase;
							}

                            if ($result === 'applied') {
                                $monthlyRecord = new CapitatedMonthlyRecord();
                                $monthlyRecord->company_id              = $company->id;
                                $monthlyRecord->product_id              = $product->id;
                                $monthlyRecord->person_id               = $person->id;
                                $monthlyRecord->contract_id             = $contract->id;
                                $monthlyRecord->coverage_month          = $coverageMonth->toDateString();
                                $monthlyRecord->plan_version_id         = $planVersion->id;
                                $monthlyRecord->load_batch_id           = $batch->id;

                                $monthlyRecord->full_name               = $fullName;
                                $monthlyRecord->sex                     = $sex;
                                $monthlyRecord->age_reported            = $age;
                                $monthlyRecord->residence_country_id    = $resCountry ? $resCountry->id : null;
                                $monthlyRecord->repatriation_country_id = $repCountry ? $repCountry->id : null;

                                $monthlyRecord->price_base              = $priceBase;
                                $monthlyRecord->price_source            = $priceSource;
                                $monthlyRecord->age_surcharge_rule_id   = $ageRuleId;
                                $monthlyRecord->age_surcharge_percent   = $agePercent;
                                $monthlyRecord->age_surcharge_amount    = $ageAmount;
                                $monthlyRecord->price_final             = $priceFinal;

                                $monthlyRecord->status                  = CapitatedMonthlyRecord::STATUS_ACTIVE;
                                $monthlyRecord->save();
                            }
                        }
                    }
                }

				// Contadores por resultado
                switch ($result) {
                    case 'applied':
                        $totalApplied++;
                        break;
                    case 'rejected':
                        $totalRejected++;
                        break;
                    case 'duplicated':
                        $totalDuplicated++;
                        break;
                    case 'incongruence':
                        $totalIncongruences++;
                        break;
                }

                if ($rejectionCode) {
                    $errorsByCode[$rejectionCode] = ($errorsByCode[$rejectionCode] ?? 0) + 1;
                }

                // ¿Debemos loguear este ítem?
                $shouldLog = false;
                if ($result === 'applied' && $logApplied) {
                    $shouldLog = true;
                } elseif ($result === 'rejected' && $logRejected) {
                    $shouldLog = true;
                } elseif ($result === 'incongruence' && $logIncongruence) {
                    $shouldLog = true;
                } elseif ($result === 'duplicated' && $logDuplicated) {
                    $shouldLog = true;
                }

                if ($shouldLog) {
                    CapitatedBatchItemLog::create([
                        'batch_id'                    => $batch->id,
                        'sheet_name'                  => $sheetName,
                        'row_number'                  => $row,
                        'product_id'                  => $product->id,
                        'plan_version_id'             => $planVersion->id,
                        'residence_raw'               => $resRaw,
                        // Solo ISO normalizado en logs de batch items
                        'residence_code_extracted'    => $resIso3,
                        'repatriation_raw'            => $repRaw,
                        'repatriation_code_extracted' => $repIso3,
                        'residence_country_id'        => $resCountry ? $resCountry->id : null,
                        'repatriation_country_id'     => $repCountry ? $repCountry->id : null,
                        'document_number'             => $documentNumber,
                        'full_name'                   => $fullName,
                        // IMPORTANTE: solo guardamos M/F; si es inválido, dejamos vacío para no romper el tamaño de columna.
                        'sex'                         => ($sex === 'M' || $sex === 'F') ? $sex : '',
                        'age_reported'                => $age,
                        'result'                      => $result,
                        'rejection_code'              => $rejectionCode,
                        'rejection_detail'            => $rejectionDetail,
                        'person_id'                   => $person ? $person->id : null,
                        'contract_id'                 => $contract ? $contract->id : null,
                        'monthly_record_id'           => $monthlyRecord ? $monthlyRecord->id : null,
                        'duplicated_record_id'        => $duplicatedRecord ? $duplicatedRecord->id : null,
                    ]);
                }
            }
        }

		// 4) Actualizar batch con resumen
        $batch->status              = $totalApplied > 0
            ? CapitatedBatchLog::STATUS_PROCESSED
            : CapitatedBatchLog::STATUS_PROCESSED_ZERO;
        $batch->processed_at        = Carbon::now();
        $batch->total_rows          = $totalRows;
        $batch->total_applied       = $totalApplied;
        $batch->total_rejected      = $totalRejected;
        $batch->total_duplicated    = $totalDuplicated;
        $batch->total_incongruences = $totalIncongruences;
        $batch->total_plan_errors   = 0;
        $batch->total_rolled_back   = 0;
        $batch->summary_json        = json_encode([
            'errors_by_code' => $errorsByCode,
        ], JSON_UNESCAPED_UNICODE);

        $batch->save();
    }

    /**
     * Construye metadatos por hoja:
     * - Valida formato de nombre de hoja "(id) Nombre"
     * - Resuelve Product y PlanVersion activa (status=active)
     * - Valida encabezados requeridos
     * - Carga países permitidos y recargos por edad
     *
     * @return array{0: array<string,array>, 1: array<int,array>} [sheetMetas, planErrors]
     */
    protected function buildSheetMetas(
        Company $company,
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
    ): array {
        $sheetMetas = [];
        $planErrors = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            /** @var \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet */
            $sheetName = trim($sheet->getTitle());

            // Hojas que no tienen formato "(id) Nombre" se ignoran
            if (!preg_match('/^\s*\((\d+)\)\s*(.+)$/', $sheetName, $m)) {
                continue;
            }

            $productId = (int) $m[1];

            $product = Product::query()
                ->where('id', $productId)
                ->where('company_id', $company->id)
                ->where('product_type', 'plan_capitado')
                ->first();

            if (!$product) {
                $planErrors[] = [
                    'code'       => CapitatedRejectionCodes::PLAN_INVALID_PRODUCT,
                    'sheet'      => $sheetName,
                    'product_id' => $productId,
                    'message'    => 'No existe un producto plan_capitado válido para esta company en la hoja.',
                ];
                continue;
            }

            $planVersion = PlanVersion::query()
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->first();

            if (!$planVersion) {
                $planErrors[] = [
                    'code'       => CapitatedRejectionCodes::PLAN_NO_ACTIVE_VERSION,
                    'sheet'      => $sheetName,
                    'product_id' => $productId,
                    'message'    => 'El producto no tiene versión activa.',
                ];
                continue;
            }

            // Cargar países (residencia/repatriación) y recargos por edad
            $planVersion->loadMissing(['countries', 'repatriationCountries', 'ageSurcharges']);

            // Encabezados
            $headerRowIndex   = 1;
            $highestColumn    = $sheet->getHighestColumn($headerRowIndex);
            $highestColumnIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            $headers = [];
            for ($col = 1; $col <= $highestColumnIdx; $col++) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $value        = $sheet->getCell($columnLetter . $headerRowIndex)->getValue();

                if ($value === null) {
                    continue;
                }
                if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $value = $value->getPlainText();
                }
                $normalized = strtoupper(trim((string) $value));
                if ($normalized !== '') {
                    $headers[$normalized] = $col;
                }
            }

            $required = [
                'ID',
                'NOMBRE',
                'RESIDENCIA',
                'NACIONALIDAD',
                'SEXO',
                'EDAD',
            ];

            $missing = [];
            foreach ($required as $header) {
                if (!array_key_exists($header, $headers)) {
                    $missing[] = $header;
                }
            }

            if (!empty($missing)) {
                $planErrors[] = [
                    'code'            => CapitatedRejectionCodes::PLAN_STRUCTURE_INVALID,
                    'sheet'           => $sheetName,
                    'product_id'      => $productId,
                    'missing_headers' => $missing,
                    'message'         => 'Faltan encabezados obligatorios en la hoja.',
                ];
                continue;
            }

            // Mapas de países permitidos y precios por país (claves por ISO3)
            $resAllowed    = [];
            $countryPrices = [];
            foreach ($planVersion->countries as $country) {
                $iso3 = strtoupper($country->iso3);
                $resAllowed[$iso3] = true;

                if ($country->pivot && $country->pivot->price !== null && $country->pivot->price !== '') {
                    $countryPrices[$iso3] = (float) $country->pivot->price;
                }
            }

            $repAllowed = [];
            foreach ($planVersion->repatriationCountries as $country) {
                $repAllowed[strtoupper($country->iso3)] = true;
            }

            $sheetMetas[$sheetName] = [
                'sheet'            => $sheet,
                'product'          => $product,
                'plan_version'     => $planVersion,
                'columns'          => [
                    'ID'           => $headers['ID'],
                    'NOMBRE'       => $headers['NOMBRE'],
                    'RESIDENCIA'   => $headers['RESIDENCIA'],
                    'NACIONALIDAD' => $headers['NACIONALIDAD'],
                    'SEXO'         => $headers['SEXO'],
                    'EDAD'         => $headers['EDAD'],
                ],
                'residence_iso3'    => $resAllowed,
                'repatriation_iso3' => $repAllowed,
                'country_prices'    => $countryPrices,
                'age_surcharges'    => $planVersion->ageSurcharges ?? collect(),
            ];
        }

        return [$sheetMetas, $planErrors];
    }

    /**
     * Crea un nuevo contrato a partir de la fila (entrada inicial o nuevo contrato tras quiebre).
     */
    protected function createNewContract(
        Company $company,
        Product $product,
        CapitatedProductInsured $person,
        PlanVersion $planVersion,
        Carbon $coverageMonth,
        int $age
    ): CapitatedContract {
        $entryDate  = $coverageMonth->copy()->startOfMonth();
        $validUntil = $coverageMonth->copy()->endOfMonth();

        $contract = new CapitatedContract();
        $contract->company_id  = $company->id;
        $contract->product_id  = $product->id;
        $contract->person_id   = $person->id;
        $contract->status      = CapitatedContract::STATUS_ACTIVE;
        $contract->entry_date  = $entryDate->toDateString();
        $contract->valid_until = $validUntil->toDateString();
        $contract->entry_age   = $age;

        $contract->wtime_suicide_ends_at = $planVersion->wtime_suicide
            ? $entryDate->copy()->addDays((int) $planVersion->wtime_suicide)->toDateString()
            : null;

        $contract->wtime_preexisting_conditions_ends_at = $planVersion->wtime_preexisting_conditions
            ? $entryDate->copy()->addDays((int) $planVersion->wtime_preexisting_conditions)->toDateString()
            : null;

        $contract->wtime_accident_ends_at = $planVersion->wtime_accident
            ? $entryDate->copy()->addDays((int) $planVersion->wtime_accident)->toDateString()
            : null;

        $contract->save();

        return $contract;
    }

    /**
     * Devuelve el valor de una celda como string trimmeado.
     */
    protected function getCellString(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $column,
        int $row
    ): string {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
        $value        = $sheet->getCell($columnLetter . $row)->getValue();

        if ($value === null) {
            return '';
        }

        if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $value = $value->getPlainText();
        }

        return trim((string) $value);
    }

    /**
     * Determina si una fila está completamente vacía.
     */
    protected function isRowEmpty(array $values): bool
    {
        foreach ($values as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Normaliza un string de país a ISO3 usando el primer token alfabético.
     *
     * Ejemplos aceptados:
     *  - "CHL"
     *  - "CL Chile"
     *  - "Chile (CL)"
     *
     * Devuelve ISO3 o null si no resuelve.
     */
    public function normalizeCountryToIso3(?string $raw): ?string
    {
        if (!$raw) {
            return null;
        }

        $raw = strtoupper(trim($raw));
        if ($raw === '') {
            return null;
        }

        // Primer token alfabético (2–3 letras) desde el inicio
        if (!preg_match('/^([A-Z]{2,3})/', $raw, $m)) {
            return null;
        }

        $token = $m[1];

        // Si token >= 3 letras, probar ISO3
        if (strlen($token) >= 3) {
            $code3   = substr($token, 0, 3);
            $country = Country::where('iso3', $code3)->first();
            if ($country) {
                return $country->iso3;
            }
        }

        // Probar ISO2 (primeras 2 letras)
        $code2   = substr($token, 0, 2);
        $country = Country::where('iso2', $code2)->first();
        if ($country) {
            return $country->iso3;
        }

        return null;
    }

    /**
     * Normaliza un mes de cobertura a YYYY-MM-01.
     */
    public function normalizeCoverageMonth(\DateTimeInterface $date): Carbon
    {
        return Carbon::instance($date)->startOfMonth();
    }

    /**
     * Indica si un batch puede intentar rollback a nivel general (best effort).
     *
     * Regla:
     * - status === processed
     * - existe al menos un registro mensual vigente (status ACTIVE) asociado.
     *
     * El detalle de elegibilidad por registro se resuelve en canRollbackMonthlyRecord().
     */
    public function canRollbackBatch(CapitatedBatchLog $batch): bool
    {
        if ($batch->status !== CapitatedBatchLog::STATUS_PROCESSED) {
            return false;
        }

        // Al menos un registro mensual vigente (status ACTIVE) para este batch
        return CapitatedMonthlyRecord::query()
            ->where('company_id', $batch->company_id)
            ->where('load_batch_id', $batch->id)
            ->where('status', CapitatedMonthlyRecord::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Indica si un registro mensual puede ser revertido.
     *
     * Regla:
     * - status != rolled_back
     * - Tiene contrato y mes definidos
     * - Es el último mes vigente para ese contrato
     *   (no existe otro mes posterior con status ACTIVE para el mismo contrato).
     *
     * Solo usamos status como campo de verdad; rolled_back_at se usa solo como metadata.
     */
    public function canRollbackMonthlyRecord(CapitatedMonthlyRecord $record): bool
    {
        if ($record->status === CapitatedMonthlyRecord::STATUS_ROLLED_BACK) {
            return false;
        }

        if (!$record->contract_id || !$record->coverage_month) {
            return false;
        }

        return !CapitatedMonthlyRecord::query()
            ->where('company_id', $record->company_id)
            ->where('contract_id', $record->contract_id)
            ->where('coverage_month', '>', $record->coverage_month)
            ->where('status', CapitatedMonthlyRecord::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Rollback completo de un batch (best effort).
     *
     * - Verifica que el batch pertenezca a la empresa y sea elegible en términos generales.
     * - Intenta revertir cada registro mensual vigente del lote.
     * - Si un registro no es elegible según canRollbackMonthlyRecord(), se lo salta (no lanza excepción).
     * - Solo recalcula estadísticas del batch una vez al final.
     *
     * IMPORTANTE:
     * Cada rollback de registro se hace en su propia transacción dentro de rollbackMonthlyRecord().
     */
    public function rollbackBatch(
        Company $company,
        CapitatedBatchLog $batch,
        int $userId
    ): CapitatedBatchLog {
        if ($batch->company_id !== $company->id) {
            throw new \RuntimeException('El batch no pertenece a la empresa indicada.');
        }

        if (! $this->canRollbackBatch($batch)) {
            throw new \RuntimeException('El batch no es elegible para rollback.');
        }

        // Registros (scope global ya excluye rolled_back si lo tuvieras definido)
        $records = CapitatedMonthlyRecord::query()
            ->where('company_id', $company->id)
            ->where('load_batch_id', $batch->id)
            ->get();

        // Carrera defensiva: si por algún motivo ya no hay registros vigentes, no hacemos nada.
        if ($records->isEmpty()) {
            return $batch->fresh();
        }

        /** @var CapitatedMonthlyRecord $record */
        foreach ($records as $record) {
            // Best effort: si este registro no es elegible, lo saltamos y seguimos con los demás.
            if (! $this->canRollbackMonthlyRecord($record)) {
                continue;
            }

            // Revertimos cada registro sin recalcular stats en cada iteración
            $this->rollbackMonthlyRecord(
                $company,
                $batch,
                $record,
                $userId,
                false // no recalcular stats por registro; se recalculan al final
            );
        }

        // Recalcular estadísticas del batch una sola vez al final
        $this->recalculateBatchStats($batch, $userId);
		$this->refreshContractsStatus($company);

        return $batch->fresh();
    }

    /**
     * Rollback de un único registro mensual perteneciente a un batch.
     *
     * $recalculateBatchStats:
     *  - true  -> recalcula las estadísticas del batch y puede marcarlo como rolled_back si corresponde.
     *  - false -> no recalcula (usado cuando se invoca desde rollbackBatch).
     */
    public function rollbackMonthlyRecord(
        Company $company,
        CapitatedBatchLog $batch,
        CapitatedMonthlyRecord $record,
        int $userId,
        bool $recalculateBatchStats = true
    ): CapitatedMonthlyRecord {
        DB::transaction(function () use (&$record, $company, $batch, $userId, $recalculateBatchStats) {
            // 1) Validar pertenencia a empresa y batch
            if (
                $record->company_id !== $company->id ||
                $record->load_batch_id !== $batch->id
            ) {
                throw new \RuntimeException('El registro mensual no pertenece al batch/empresa indicados.');
            }

            // 2) Verificar elegibilidad
            if (! $this->canRollbackMonthlyRecord($record)) {
                throw new \RuntimeException('El registro mensual no es elegible para rollback.');
            }

            // 3) Marcar el registro mensual como rolled_back
            $record->status                 = CapitatedMonthlyRecord::STATUS_ROLLED_BACK;
            $record->rolled_back_at         = Carbon::now();
            $record->rolled_back_by_user_id = $userId;
            $record->save();

            // 4) Ajustar contrato y, si corresponde, cascada a contrato/persona
            $contractId = $record->contract_id;
            if ($contractId) {
                /** @var CapitatedContract|null $contract */
                $contract = CapitatedContract::query()->find($contractId);
                if ($contract) {
                    // ¿Quedan registros mensuales activos para este contrato?
                    $lastActiveRecord = CapitatedMonthlyRecord::query()
                        ->where('company_id', $record->company_id)
                        ->where('contract_id', $contract->id)
                        ->where('status', CapitatedMonthlyRecord::STATUS_ACTIVE)
                        ->orderByDesc('coverage_month')
                        ->orderByDesc('id')
                        ->first();

                    if ($lastActiveRecord) {
                        // El contrato sigue vigente; ajustar fecha de término al último mes activo
                        $contract->status      = CapitatedContract::STATUS_ACTIVE;
                        $contract->valid_until = Carbon::parse($lastActiveRecord->coverage_month)
                            ->endOfMonth()
                            ->toDateString();
                        $contract->save();
                    } else {
                        // No queda ningún mes activo → decisión de rollback de contrato
                        $this->rollbackContract($contract, $userId);
                    }
                }
            }

            // 5) Recalcular stats del batch si corresponde
            if ($recalculateBatchStats) {
                $this->recalculateBatchStats($batch, $userId);
				$this->refreshContractsStatus($company);
            }
        });

        return $record->fresh(['contract']);
    }

    /**
     * Rollback de contrato (se asume que ya no quedan registros mensuales ACTIVE
     * para este contrato). Marca el contrato como ROLLED_BACK y, si la persona
     * se queda sin contratos activos para el mismo producto, dispara rollbackPerson().
     */
    protected function rollbackContract(CapitatedContract $contract, int $userId): void
    {
        // Si ya está revertido, no hacemos nada
        if ($contract->status === CapitatedContract::STATUS_ROLLED_BACK) {
            return;
        }

        // Marcar contrato como rolled_back
        $contract->status                 = CapitatedContract::STATUS_ROLLED_BACK;
        $contract->rolled_back_at         = Carbon::now();
        $contract->rolled_back_by_user_id = $userId;
        $contract->save();

        // Verificar si aún existe algún otro contrato ACTIVE para esta persona/producto
        $hasActiveContract = CapitatedContract::query()
            ->where('company_id', $contract->company_id)
            ->where('product_id', $contract->product_id)
            ->where('person_id', $contract->person_id)
            ->whereIn('status', [
                CapitatedContract::STATUS_ACTIVE,
                CapitatedContract::STATUS_EXPIRED,
            ])
            ->exists();

        if (! $hasActiveContract) {
            // Decisión de cascada a persona
            $this->rollbackPerson(
//                $contract->company_id,
//                $contract->product_id,
                $contract->person_id,
                $userId
            );
        }
    }

    /**
     * Rollback de ficha de persona (se asume que ya no quedan contratos ACTIVE
     * para ese company/product/person). Marca la persona como ROLLED_BACK.
     */
    protected function rollbackPerson(
//        int $companyId,
//        int $productId,
        int $personId,
        int $userId
    ): void {
        if (! $personId) {
            return;
        }

        /** @var CapitatedProductInsured|null $person */
        $person = CapitatedProductInsured::query()
//            ->where('company_id', $companyId)
//            ->where('product_id', $productId)
            ->where('id', $personId)
            ->first();

        if (! $person) {
            return;
        }

        $person->status                 = CapitatedProductInsured::STATUS_ROLLED_BACK;
        $person->rolled_back_at         = Carbon::now();
        $person->rolled_back_by_user_id = $userId;
        $person->save();
    }

    /**
     * Recalcula las estadísticas del batch en base a los registros mensuales
     * (incluyendo revertidos) y, si corresponde, marca el batch como rolled_back
     * y setea rolled_back_at / rolled_back_by_user_id.
     *
     * Regla para marcar batch como rolled_back:
     * - Si no quedan registros vigentes (status ACTIVE) para el batch
     *   y existe al menos un registro monthly con status ROLLED_BACK.
     */
    protected function recalculateBatchStats(
        CapitatedBatchLog $batch,
        ?int $userId = null
    ): void {
        $baseQuery = CapitatedMonthlyRecord::withoutGlobalScope('not_rolled_back')
            ->where('company_id', $batch->company_id)
            ->where('load_batch_id', $batch->id);

        // Vigentes: status ACTIVE
        $totalActive = (clone $baseQuery)
            ->where('status', CapitatedMonthlyRecord::STATUS_ACTIVE)
            ->count();

        // Revertidos: status ROLLED_BACK
        $totalRolledBack = (clone $baseQuery)
            ->where('status', CapitatedMonthlyRecord::STATUS_ROLLED_BACK)
            ->count();

        // Actualizar contadores
        $batch->total_applied     = $totalActive;
        $batch->total_rolled_back = $totalRolledBack;

        // Si no queda ningún registro vigente pero hay al menos uno revertido,
        // el batch queda considerado como completamente revertido.
        if ($totalActive === 0 && $totalRolledBack > 0) {
            $batch->status				   = CapitatedBatchLog::STATUS_ROLLED_BACK;
            $batch->rolled_back_at		   = Carbon::now();
            $batch->rolled_back_by_user_id = $userId;
        }

        $batch->save();
    }
	
    /**
     * Recalcula en bloque el status de todos los contratos de una compañía (opcional)
     * en función de la fecha de vencimiento (`valid_until`) y la fecha actual.
     *
     * Regla:
     * - Si valid_until >= hoy  -> STATUS_ACTIVE
     * - Si valid_until <  hoy  -> STATUS_EXPIRED
     *
     * No modifica contratos con status ROLLED_BACK u otros distintos
     * de ACTIVE/EXPIRED.
     *
     * @return int Número de filas afectadas por el UPDATE.
     */
    public function refreshContractsStatus(Company $company): int
    {
        $today = Carbon::today()->toDateString();

		$query = CapitatedContract::query()
            ->whereIn('status', [
                CapitatedContract::STATUS_ACTIVE,
                CapitatedContract::STATUS_EXPIRED,
            ]);

		if(!empty($company))
		{
            $query->where('company_id', $company->id);
		}
		
        return $query->update([
                'status' => DB::raw(
                    "CASE " .
                    "WHEN valid_until >= '{$today}' THEN '" . CapitatedContract::STATUS_ACTIVE . "' " .
                    "ELSE '" . CapitatedContract::STATUS_EXPIRED . "' " .
                    "END"
                ),
            ]);
    }
}
