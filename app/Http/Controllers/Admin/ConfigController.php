<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfigItem;
use App\Models\File;
use App\Services\UploadedFileService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConfigController extends Controller
{
	public function index(Request $request)
	{
		$categories  = config('config_categories', []);
		$permissions = $this->buildPermissions($request);

		if ($request->wantsJson())
		{
			$items = ConfigItem::with(['filePlain', 'fileEs', 'fileEn'])
				->orderBy('category')
				->orderBy('name')
				->get();

			$serialized = $items->map(function (ConfigItem $item) use ($categories) {
				return $this->serializeItem($item, $categories);
			})->values();

			return response()->json([
				'categories'  => $categories,
				'permissions' => $permissions,
				'items'       => $serialized,
			]);
		}

		return view('admin.config.index', [
			'categories'  => $categories,
			'permissions' => $permissions,
		]);
	}

	public function show(ConfigItem $item, Request $request)
	{
		$categories = config('config_categories', []);

		$item->loadMissing(['filePlain', 'fileEs', 'fileEn']);

		return response()->json([
			'item' => $this->serializeItem($item, $categories, full: true),
		]);
	}

	public function store(Request $request)
	{
		$categories = array_keys(config('config_categories', []));

		$data = $request->validate([
			'category' => ['required', 'string', Rule::in($categories)],
			'name'     => ['required', 'string', 'max:255'],
			'token'    => [
				'required',
				'string',
				'max:191',
				Rule::unique('config_items')
					->where(fn($q) => $q->where('category', $request->input('category'))),
			],
			'type'     => ['required', 'string', 'max:100'],
			'config'   => ['nullable', 'array'],
		]);

		$item           = new ConfigItem();
		$item->category = $data['category'];
		$item->name     = $data['name'];
		$item->token    = $data['token'];
		$item->type     = $data['type'];
		$item->config   = $data['config'] ?? [];
		$item->save();

		$categoriesMap = config('config_categories', []);

		return response()->json([
			'message' => 'Variable de configuración creada correctamente.',
			'item'    => $this->serializeItem($item, $categoriesMap),
		], 201);
	}

	/**
	 * Actualiza solo el valor (no la definición base).
	 * Maneja todos los tipos, incluyendo limpiar archivos.
	 */
	public function updateValue(ConfigItem $item, Request $request)
	{
		$type = $item->type;

		/** @var UploadedFileService $fileService */
		$fileService = app(UploadedFileService::class);

		$fileToDeletePlain = null;
		$fileToDeleteEs    = null;
		$fileToDeleteEn    = null;

		if ($type === 'integer')
		{
			$data = $request->validate([
				'value' => ['nullable', 'integer'],
			]);

			$item->value_int = $data['value'] !== null ? (int) $data['value'] : null;
		}
		elseif ($type === 'boolean')
		{
			$data = $request->validate([
				'value' => ['nullable', 'boolean'],
			]);

			$value           = $data['value'];
			$item->value_int = $value === null ? null : ($value ? 1 : 0);
		}
		elseif ($type === 'decimal')
		{
			$data = $request->validate([
				'value' => ['nullable', 'numeric'],
			]);
			$item->value_decimal = $data['value'] !== null ? (float) $data['value'] : null;
		}
		elseif (in_array($type, [
			'input_text_plain',
			'textarea_plain',
			'html_plain',
			'email',
			'url',
			'phone',
			'color',
			'json',
			'model_reference',
			'enum',
		], true))
		{
			$data            = $request->validate([
				'value' => ['nullable', 'string'],
			]);
			$item->value_text = $data['value'] ?? null;
		}
		elseif (in_array($type, [
			'input_text_translated',
			'textarea_translated',
			'html_translated',
		], true))
		{
			$data         = $request->validate([
				'translations' => ['nullable', 'array'],
			]);
			$translations = $data['translations'] ?? [];
			$item->value_trans = $translations;
		}
		elseif ($type === 'date')
		{
			$data          = $request->validate([
				'value' => ['nullable', 'date'],
			]);
			$item->value_date = $data['value'] ?? null;
		}
		elseif ($type === 'file_plain')
		{
			$data = $request->validate([
				'value_file_plain_id' => ['sometimes', 'nullable', 'integer'],
			]);

			if (array_key_exists('value_file_plain_id', $data) && $data['value_file_plain_id'] === null)
			{
				if ($item->filePlain)
				{
					$fileToDeletePlain = $item->filePlain;
				}
				$item->value_file_plain_id = null;
			}
			else
			{
				return response()->json([
					'message' => 'Para archivos use el endpoint de subida o envíe *_id = null para limpiar.',
				], 422);
			}
		}
		elseif ($type === 'file_translated')
		{
			$data = $request->validate([
				'value_file_es_id' => ['sometimes', 'nullable', 'integer'],
				'value_file_en_id' => ['sometimes', 'nullable', 'integer'],
			]);

			if (array_key_exists('value_file_es_id', $data) && $data['value_file_es_id'] === null)
			{
				if ($item->fileEs)
				{
					$fileToDeleteEs = $item->fileEs;
				}
				$item->value_file_es_id = null;
			}

			if (array_key_exists('value_file_en_id', $data) && $data['value_file_en_id'] === null)
			{
				if ($item->fileEn)
				{
					$fileToDeleteEn = $item->fileEn;
				}
				$item->value_file_en_id = null;
			}

			if ($data === [])
			{
				return response()->json([
					'message' => 'Para archivos use el endpoint de subida o envíe *_id = null para limpiar.',
				], 422);
			}
		}
		else
		{
			return response()->json([
				'message' => 'Tipo de configuración no soportado para actualización de valor.',
			], 422);
		}

		$item->save();

		if ($fileToDeletePlain)
		{
			$fileService->delete($fileToDeletePlain);
		}
		if ($fileToDeleteEs)
		{
			$fileService->delete($fileToDeleteEs);
		}
		if ($fileToDeleteEn)
		{
			$fileService->delete($fileToDeleteEn);
		}

		// Recarga forzada de relaciones para devolver file_* coherentes
		$item->load(['filePlain', 'fileEs', 'fileEn']);

		$categoriesMap = config('config_categories', []);

		return response()->json([
			'message' => 'Valor actualizado correctamente.',
			'item'    => $this->serializeItem($item, $categoriesMap),
		]);
	}

	/**
	 * Actualiza la definición base (name, token, type, category, config).
	 */
	public function updateDefinition(ConfigItem $item, Request $request)
	{
		$categories = array_keys(config('config_categories', []));

		$data = $request->validate([
			'category' => ['required', 'string', Rule::in($categories)],
			'name'     => ['required', 'string', 'max:255'],
			'token'    => [
				'required',
				'string',
				'max:191',
				Rule::unique('config_items')
					->where(fn($q) => $q->where('category', $request->input('category')))
					->ignore($item->id),
			],
			'type'     => ['required', 'string', 'max:100'],
			'config'   => ['nullable', 'array'],
		]);

		$item->category = $data['category'];
		$item->name     = $data['name'];
		$item->token    = $data['token'];
		$item->type     = $data['type'];
		$item->config   = $data['config'] ?? [];
		$item->save();

		$categoriesMap = config('config_categories', []);

		return response()->json([
			'message' => 'Definición actualizada correctamente.',
			'item'    => $this->serializeItem($item, $categoriesMap),
		]);
	}

	/**
	 * Subida / reemplazo de archivo para file_plain y file_translated.
	 * Usa restricciones de config (extensiones y tamaño máx.).
	 */
	public function uploadFile(ConfigItem $item, Request $request)
	{
		if (!in_array($item->type, ['file_plain', 'file_translated'], true))
		{
			return response()->json([
				'message' => 'Este registro no es de tipo archivo.',
			], 422);
		}

		// Restricciones desde config
		$constraints = $item->fileConstraints();
		$exts        = $constraints['extensions'];
		$maxSizeKb   = $constraints['max_size_kb'];

		$fileRules = ['required', 'file'];

		if ($maxSizeKb && $maxSizeKb > 0)
		{
			// Laravel: max para archivos está en KB
			$fileRules[] = 'max:' . $maxSizeKb;
		}

		if (!empty($exts))
		{
			// mimes:pdf,jpg,png,...
			$fileRules[] = 'mimes:' . implode(',', $exts);
		}

		$data = $request->validate([
			'file'   => $fileRules,
			'locale' => ['nullable', 'in:es,en'],
		]);

		$uploaded = $data['file'];
		$locale   = $data['locale'] ?? null;

		/** @var UploadedFileService $uploader */
		$uploader = app(UploadedFileService::class);

		$userId = optional($request->user())->id;

		// Determinar campo y File actual
		$field   = null;
		$current = null;
		$meta    = [
			'context'  => 'config_item',
			'model'    => ConfigItem::class,
			'model_id' => $item->id,
		];

		if ($item->type === 'file_plain')
		{
			$field          = 'value_file_plain_id';
			$current        = $item->filePlain;
			$meta['field']  = 'value_file_plain_id';
			$meta['lang']   = null;
		}
		else
		{
			$effectiveLocale = $locale ?: app()->getLocale();
			if ($effectiveLocale === 'es')
			{
				$field          = 'value_file_es_id';
				$current        = $item->fileEs;
				$meta['field']  = 'value_file_es_id';
				$meta['lang']   = 'es';
			}
			else
			{
				$field          = 'value_file_en_id';
				$current        = $item->fileEn;
				$meta['field']  = 'value_file_en_id';
				$meta['lang']   = 'en';
			}
		}

		$basePath = $item->storagePath($field ?: 'file');

		if ($current instanceof File)
		{
			$uploader->replace(
				$current,
				$uploaded,
				$meta,
				$userId,
				true,
				null,
				$basePath
			);
			$current->refresh();
			$fileModel = $current;
		}
		else
		{
			$fileModel = $uploader->store(
				$uploaded,
				$meta,
				$userId,
				null,
				$basePath
			);

			$item->{$field} = $fileModel->id;
			$item->save();
		}

		// Recarga forzada de relaciones para devolver file_* coherentes
		$item->load(['filePlain', 'fileEs', 'fileEn']);

		$categoriesMap = config('config_categories', []);

		return response()->json([
			'message' => 'Archivo subido correctamente.',
			'item'    => $this->serializeItem($item, $categoriesMap, full: true),
		]);
	}

	public function destroy(ConfigItem $item)
	{
		$item->delete();

		return response()->json([
			'message' => 'Variable de configuración eliminada.',
		]);
	}

	// -------------------------------------
	// Helpers internos
	// -------------------------------------

	protected function buildPermissions(Request $request): array
	{
		$user = $request->user();

		return [
			'create' => $user?->can('admin.config.create') ?? false,
			'read'   => $user?->can('admin.config.read') ?? false,
			'fill'   => $user?->can('admin.config.fill') ?? false,
			'edit'   => $user?->can('admin.config.edit') ?? false,
			'delete' => $user?->can('admin.config.delete') ?? false,
		];
	}

	protected function serializeItem(ConfigItem $item, array $categories, bool $full = false): array
	{
		$config       = $item->configArray();
		$translations = $item->getTranslations('value_trans');

		$displayValue = null;

		switch ($item->type)
		{
			case 'integer':
			case 'decimal':
			case 'boolean':
				$displayValue = $item->value();
				break;

			case 'date':
				$displayValue = $item->value_date ? $item->value_date->format('Y-m-d') : null;
				break;

			case 'input_text_plain':
			case 'email':
			case 'url':
			case 'phone':
			case 'color':
			case 'json':
			case 'model_reference':
				$displayValue = $item->value_text;
				break;

			case 'enum':
				$displayValue = $item->enumLabel();
				break;

			case 'input_text_translated':
			case 'textarea_translated':
			case 'html_translated':
				$displayValue = $item->value(); // string en locale actual
				break;

			case 'textarea_plain':
			case 'html_plain':
				$displayValue = null; // no preview
				break;

			case 'file_plain':
			case 'file_translated':
				$displayValue = '[archivo]';
				break;
		}

		if (is_string($displayValue) && mb_strlen($displayValue) > 80)
		{
			$displayValue = mb_substr($displayValue, 0, 77) . '...';
		}

		$filePlain = $item->filePlain;
		$fileEs    = $item->fileEs;
		$fileEn    = $item->fileEn;

		$base = [
			'id'                  => $item->id,
			'category'            => $item->category,
			'category_label'      => $categories[$item->category] ?? $item->category,
			'token'               => $item->token,
			'name'                => $item->name,
			'type'                => $item->type,
			'config'              => $config,
			'value_int'           => $item->value_int,
			'value_decimal'       => $item->value_decimal,
			'value_text'          => $item->value_text,
			'value_translations'  => $translations,
			'value_date'          => $item->value_date ? $item->value_date->format('Y-m-d') : null,
			'value_file_plain_id' => $item->value_file_plain_id,
			'value_file_es_id'    => $item->value_file_es_id,
			'value_file_en_id'    => $item->value_file_en_id,
			'file_plain'          => $filePlain ? [
				'id'            => $filePlain->id,
				'original_name' => $filePlain->original_name,
				'url'           => $filePlain->url(),
				'temporary_url' => $filePlain->temporaryUrl(60),
			] : null,
			'file_es'             => $fileEs ? [
				'id'            => $fileEs->id,
				'original_name' => $fileEs->original_name,
				'url'           => $fileEs->url(),
				'temporary_url' => $fileEs->temporaryUrl(60),
			] : null,
			'file_en'             => $fileEn ? [
				'id'            => $fileEn->id,
				'original_name' => $fileEn->original_name,
				'url'           => $fileEn->url(),
				'temporary_url' => $fileEn->temporaryUrl(60),
			] : null,
			'display_value'       => $displayValue,
		];

		return $base;
	}
}
