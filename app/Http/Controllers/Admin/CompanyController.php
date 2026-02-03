<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\Template;
use App\Models\User;
use App\Services\UploadedFileService;
use App\Support\Breadcrumbs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{

	public function __construct(
			protected UploadedFileService $uploadedFileService,
	)
	{
		parent::__construct();
	}

	public function index(Request $request)
	{
		if ($request->wantsJson())
		{
			$status = $request->query('status', 'active');
			$search = trim((string) $request->query('search', ''));

			$query = Company::query();

			if ($status !== 'all')
			{
				if ($status === Company::STATUS_ACTIVE)
				{
					$query->where('status', Company::STATUS_ACTIVE);
				}
				elseif ($status === Company::STATUS_INACTIVE)
				{
					$query->where('status', Company::STATUS_INACTIVE);
				}
				elseif ($status === Company::STATUS_ARCHIVED)
				{
					$query->where('status', Company::STATUS_ARCHIVED);
				}
			}

			if ($search !== '')
			{
				$query->where(function ($q) use ($search)
				{
					$q->where('name', 'like', '%' . $search . '%')
							->orWhere('short_code', 'like', '%' . $search . '%')
							->orWhere('phone', 'like', '%' . $search . '%')
							->orWhere('email', 'like', '%' . $search . '%');
				});
			}

			$companies = $query->orderBy('name')->get();

			return response()->json([
						'companies' => $companies
								->map(fn(Company $company) => $this->transformCompany($company))
								->values(),
						'filters'	=> [
							'status' => $status,
							'search' => $search,
						],
			]);
		}

		Breadcrumbs::add('Empresas', route('admin.companies.index'));

		return view('admin.companies.index');
	}

	public function edit(Company $company)
	{
		Breadcrumbs::add('Empresas', route('admin.companies.index'));
		Breadcrumbs::add($company->name, route('admin.companies.capitated-products.index', $company));
		Breadcrumbs::add('Datos básicos', route('admin.companies.edit', $company));

		return view('admin.companies.edit', [
			'company' => $company,
		]);
	}

	public function show(Company $company): JsonResponse
	{
		$company->load([
			'users',
			'commissionBeneficiary',
			'brandingLogo',
			'pdfTemplate',
		]);

		$assignedUsers = $company->users()
				->orderBy('first_name')
				->orderBy('last_name')
				->orderBy('email')
				->get(['users.id', 'users.email', 'users.first_name', 'users.last_name'])
				->map(function (User $user)
				{
					return [
						'id'		   => $user->id,
						'email'		   => $user->email,
						'display_name' => $user->displayName(),
					];
				})
				->values()
				->all();

		$pdfTemplates = Template::query()
				->whereRaw('UPPER(type) = ?', [Template::TYPE_PDF])
				->orderBy('name')
				->get(['id', 'name'])
				->map(fn(Template $t) => [
					'id'   => $t->id,
					'name' => $t->name,
						])
				->values()
				->all();

		return response()->json([
					'data'				=> $this->transformCompany($company),
					'assigned_users'	=> $assignedUsers,
					'beneficiary_users' => $this->getBeneficiaryUsersList(),
					'branding_defaults' => Company::brandingDefaults(),
					'pdf_templates'		=> $pdfTemplates,
		]);
	}

	public function store(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'name'		 => ['required', 'string', 'max:255'],
			'short_code' => [
				'required',
				'string',
				'min:3',
				'max:5',
				'regex:/^[A-Za-z]+$/',
				Rule::unique('companies', 'short_code'),
			],
		]);

		$company			 = new Company();
		$company->name		 = $validated['name'];
		$company->short_code = strtoupper($validated['short_code']);
		$company->status	 = Company::STATUS_ACTIVE;
		$company->save();

		return response()->json([
					'data' => $this->transformCompany($company),
		]);
	}

	public function update(Request $request, Company $company): JsonResponse
	{
		$rules = [
			'name'							 => ['sometimes', 'required', 'string', 'max:255'],
			'short_code'					 => [
				'sometimes',
				'required',
				'string',
				'min:3',
				'max:5',
				'regex:/^[A-Za-z]+$/',
				Rule::unique('companies', 'short_code')->ignore($company->id),
			],
			'phone'							 => ['nullable', 'string', 'max:255'],
			'email'							 => ['nullable', 'email', 'max:255'],
			'description'					 => ['nullable', 'string'],
			'status'						 => [
				'nullable',
				'string',
				Rule::in([
					Company::STATUS_ACTIVE,
					Company::STATUS_INACTIVE,
					Company::STATUS_ARCHIVED,
				]),
			],
			'users'							 => ['sometimes', 'array'],
			'users.*'						 => ['integer', 'exists:users,id'],
			'commission_beneficiary_user_id' => ['nullable', 'integer', 'exists:users,id'],
			// Colores en formato hex opcional, con o sin '#', 3 o 6 dígitos.
			'branding_text_dark'			 => ['nullable', 'string', 'max:7', 'regex:/^#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/'],
			'branding_bg_light'				 => ['nullable', 'string', 'max:7', 'regex:/^#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/'],
			'branding_text_light'			 => ['nullable', 'string', 'max:7', 'regex:/^#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/'],
			'branding_bg_dark'				 => ['nullable', 'string', 'max:7', 'regex:/^#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/'],
			'branding_logo'					 => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:' . (5 * 1024)],
			'branding_logo_remove'			 => ['nullable', 'boolean'],
			'pdf_template_id' => [
				'nullable',
				'integer',
				Rule::exists('templates', 'id')->where(function ($q)
				{
					$q->whereRaw('UPPER(type) = ?', [Template::TYPE_PDF])
							->whereNull('deleted_at');
				}),
			],
		];

		$validated = $request->validate($rules);

		$company = DB::transaction(function () use ($company, $validated, $request)
		{
			$data = [];

			foreach (['name', 'phone', 'email', 'description', 'status', 'pdf_template_id'] as $field)
			{
				if (array_key_exists($field, $validated))
				{
					$data[$field] = $validated[$field];
				}
			}

			if (array_key_exists('short_code', $validated))
			{
				$data['short_code'] = strtoupper($validated['short_code']);
			}

			foreach (['branding_text_dark', 'branding_bg_light', 'branding_text_light', 'branding_bg_dark'] as $field)
			{
				if (array_key_exists($field, $validated))
				{
					$value		  = $validated[$field];
					$value		  = $value ? ltrim($value, '#') : null;
					$data[$field] = $value ?: null;
				}
			}

			if (array_key_exists('commission_beneficiary_user_id', $validated))
			{
				$data['commission_beneficiary_user_id'] = $validated['commission_beneficiary_user_id'] ?? null;
			}

			if (!empty($data))
			{
				foreach ($data as $key => $value)
				{
					$company->{$key} = $value;
				}

				$company->save();
			}

			// Soporte para sync completo si se envía el array.
			if (array_key_exists('users', $validated))
			{
				$company->users()->sync($validated['users'] ?? []);
			}

			if ($request->hasFile('branding_logo'))
			{
				$this->storeBrandingLogo($company, $request->file('branding_logo'));
			}
			elseif (!empty($validated['branding_logo_remove']))
			{
				$this->clearBrandingLogo($company);
			}

			return $company->fresh([
						'users',
						'commissionBeneficiary',
						'brandingLogo',
						'pdfTemplate',
			]);
		});

		return response()->json([
					'data' => $this->transformCompany($company),
		]);
	}

	public function suspend(Company $company): JsonResponse
	{
		if ($company->status !== Company::STATUS_ARCHIVED)
		{
			$company->status = Company::STATUS_INACTIVE;
			$company->save();
		}

		return response()->json([
					'data'	=> $this->transformCompany($company),
					'toast' => [
						'type'	  => 'success',
						'message' => 'Empresa suspendida correctamente.',
					],
		]);
	}

	public function archive(Company $company): JsonResponse
	{
		$company->status = Company::STATUS_ARCHIVED;
		$company->save();

		return response()->json([
					'data'	=> $this->transformCompany($company),
					'toast' => [
						'type'	  => 'success',
						'message' => 'Empresa archivada correctamente.',
					],
		]);
	}

	public function activate(Company $company): JsonResponse
	{
		$company->status = Company::STATUS_ACTIVE;
		$company->save();

		return response()->json([
					'data'	=> $this->transformCompany($company),
					'toast' => [
						'type'	  => 'success',
						'message' => 'Empresa activada correctamente.',
					],
		]);
	}

	public function checkShortCode(Request $request): JsonResponse
	{
		$shortCode = strtoupper(trim((string) $request->query('short_code', '')));
		$companyId = $request->query('company_id');

		if ($shortCode === '')
		{
			return response()->json([
						'short_code'   => $shortCode,
						'is_available' => false,
						'reason'	   => 'empty',
			]);
		}

		$query = Company::query()
				->whereRaw('UPPER(short_code) = ?', [$shortCode]);

		if ($companyId)
		{
			$query->where('id', '!=', $companyId);
		}

		$isAvailable = !$query->exists();

		return response()->json([
					'short_code'   => $shortCode,
					'is_available' => $isAvailable,
					'reason'	   => $isAvailable ? null : 'taken',
		]);
	}

	/**
	 * Búsqueda paginada de usuarios para el modal (añadir/quitar operadores).
	 */
	public function searchUsers(Request $request, Company $company): JsonResponse
	{
		$search	 = trim((string) $request->query('search', ''));
		$perPage = (int) $request->query('per_page', 10);
		$perPage = max(5, min(50, $perPage));

		// Solo usuarios activos del sistema
		$query = User::query()
				->where('status', 'active');

		if ($search !== '')
		{
			$query->where(function ($q) use ($search)
			{
				$q->where('email', 'like', '%' . $search . '%');
			});
		}

		$paginator = $query
				->orderBy('first_name')
				->orderBy('last_name')
				->orderBy('email')
				->paginate($perPage);

		$attachedIds = $company->users()->pluck('users.id')->all();

		$items = $paginator->getCollection()
				->map(function (User $user) use ($attachedIds)
				{
					return [
						'id'		   => $user->id,
						'email'		   => $user->email,
						'display_name' => $user->displayName(),
						'is_attached'  => in_array($user->id, $attachedIds, true),
					];
				})
				->values()
				->all();

		return response()->json([
					'data' => $items,
					'meta' => [
						'current_page' => $paginator->currentPage(),
						'last_page'	   => $paginator->lastPage(),
						'per_page'	   => $paginator->perPage(),
						'total'		   => $paginator->total(),
					],
		]);
	}

	public function attachUser(Company $company, User $user): JsonResponse
	{
		$company->users()->syncWithoutDetaching([$user->id]);

		$company->load(['users', 'commissionBeneficiary', 'brandingLogo', 'pdfTemplate']);

		return response()->json([
					'data' => $this->transformCompany($company),
		]);
	}

	public function detachUser(Company $company, User $user): JsonResponse
	{
		if ($company->commission_beneficiary_user_id === $user->id)
		{
			$company->commission_beneficiary_user_id = null;
			$company->save();
		}

		$company->users()->detach($user->id);

		$company->load(['users', 'commissionBeneficiary', 'brandingLogo', 'pdfTemplate']);

		return response()->json([
					'data' => $this->transformCompany($company),
		]);
	}

	protected function transformCompany(Company $company): array
	{
		return [
			'id'							 => $company->id,
			'name'							 => $company->name,
			'short_code'					 => $company->short_code,
			'phone'							 => $company->phone,
			'email'							 => $company->email,
			'description'					 => $company->description,
			'status'						 => $company->status,
			'status_label'					 => $company->status, // token crudo (para i18n)
			'users_ids'						 => $company->users->pluck('id')->values()->all(),
			'commission_beneficiary_user_id' => $company->commission_beneficiary_user_id,
			'branding_logo_file_id'			 => $company->branding_logo_file_id,
			'pdf_template_id'				 => $company->pdf_template_id,
			'branding'						 => $company->brandingConfig(),
		];
	}

	/**
	 * Lista de todos los usuarios del sistema para el select de beneficiario.
	 */
	protected function getBeneficiaryUsersList(): array
	{
		return User::query()
						->orderBy('first_name')
						->orderBy('last_name')
						->orderBy('email')
						->get(['id', 'email', 'first_name', 'last_name'])
						->map(function (User $user)
						{
							return [
								'id'		   => $user->id,
								'email'		   => $user->email,
								'display_name' => $user->displayName(),
							];
						})
						->values()
						->all();
	}

	protected function storeBrandingLogo(Company $company, UploadedFile $uploadedFile): void
	{
		// Ruta basada en HasDirectory
		$basePath = $company->storagePath('branding_logo');

		$meta = [
			'context'	 => 'company_branding_logo',
			'company_id' => $company->id,
		];

		$userId = auth()->id();

		if ($company->brandingLogo)
		{
			$file = $this->uploadedFileService->replace(
					$company->brandingLogo,
					$uploadedFile,
					$meta,
					$userId,
					true,
					null,
					$basePath
			);
		}
		else
		{
			$file = $this->uploadedFileService->store(
					$uploadedFile,
					$meta,
					$userId,
					null,
					$basePath
			);

			$company->branding_logo_file_id = $file->id;
			$company->save();
		}
	}

	protected function clearBrandingLogo(Company $company): void
	{
		// Si no hay logo asociado, no hay nada que limpiar
		if (!$company->branding_logo_file_id)
		{
			return;
		}

		// Guardamos el id antes de romper la FK
		$fileId = $company->branding_logo_file_id;

		// Intentamos recuperar el File actual (si la relación no está cargada, la cargamos)
		$file = $company->relationLoaded('brandingLogo') ? $company->brandingLogo : $company->brandingLogo()->first();

		// Rompemos la referencia en la empresa
		$company->branding_logo_file_id = null;
		$company->save();

		// Si no pudimos obtener el registro File, no intentamos borrarlo
		if (!$file || $file->id !== $fileId)
		{
			return;
		}

		// Comprobamos que el archivo sea realmente un logo de esta empresa
		$meta	   = is_array($file->meta) ? $file->meta : [];
		$context   = $meta['context'] ?? null;
		$companyId = $meta['company_id'] ?? null;

		// Verificamos que ninguna otra empresa lo esté usando
		$stillReferenced = Company::where('branding_logo_file_id', $fileId)->exists();

		if (
				!$stillReferenced &&
				$context === 'company_branding_logo' &&
				(int) $companyId === (int) $company->id
		)
		{
			// Eliminamos registro + archivo físico + caché
			$this->uploadedFileService->delete($file, true, true);
		}
	}

// App/Http/Controllers/Admin/CompanyController.php

	/**
	 * Vista administrativa de productos capitados asociados a una empresa.
	 */
	public function capitatedProducts(Company $company)
	{
		Breadcrumbs::add('Empresas', route('admin.companies.index'));
		Breadcrumbs::add($company->name, route('admin.companies.capitated-products.index', $company));

		$companyPayload = [
			'id'		   => $company->id,
			'name'		   => $company->name,
			'short_code'   => $company->short_code,
			'phone'		   => $company->phone,
			'email'		   => $company->email,
			'status'	   => $company->status,
			'status_label' => match ($company->status)
			{
				Company::STATUS_ACTIVE	 => 'Activa',
				Company::STATUS_INACTIVE => 'Suspendida',
				Company::STATUS_ARCHIVED => 'Archivada',
				default					 => ucfirst($company->status),
			},
		];

		$products = $company->capitatedProducts()
				->with(['activePlanVersion']) // <-- trae la versión activa (si existe) sin N+1
				->orderByDesc('id')
				->get()
				->map(function (Product $product)
				{
					$active = $product->activePlanVersion; // null si no existe

					return [
						'id'			 => $product->id,
						'company_id'	 => $product->company_id,
						'status'		 => $product->status,
						'product_type'	 => $product->product_type,
						'show_in_widget' => (bool) $product->show_in_widget,
						'name'			 => $product->getTranslations('name'),
						'description'	 => $product->getTranslations('description'),
						// <-- NUEVO: dato mínimo para la vista (tiene / no tiene versión activa)
						'active_plan_version_id'  => $active ? $active->id : null,
						'has_active_plan_version' => (bool) $active,
					];
				})
				->values()
				->all();

		$productTypes = [
			[
				'value' => Product::TYPE_PLAN_CAPITADO,
				'label' => 'Plan capitado',
			],
		];

		return view('admin.companies.capitated-products', [
			'company'	   => $companyPayload,
			'products'	   => $products,
			'productTypes' => $productTypes,
			'editRouteMap' => Product::EDIT_ROUTES,
		]);
	}
}
