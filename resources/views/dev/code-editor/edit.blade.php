{{-- resources/views/admin/tools/code-editor/edit.blade.php --}}
@extends('layouts.craft')

@section('content')
	<div class="container py-4">
		<div class="d-flex align-items-center justify-content-between mb-3">
			<div>
				<h1 class="h4 mb-1">CodeEditor: {{ $languageName }}</h1>
				<div class="text-muted small">
					language="{{ $languageKey }}" · debounceMs={{ (int) $options['debounceMs'] }}
				</div>
			</div>

			<div class="d-flex gap-2">
				<a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.tools.code-editor.index') }}">
					Volver
				</a>

				<div class="dropdown">
					<button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
						Abrir otro tipo
					</button>
					<ul class="dropdown-menu dropdown-menu-end">
						@foreach($languages as $k => $label)
							<li>
								<a class="dropdown-item" href="{{ route('admin.tools.code-editor.edit', ['language' => $k]) }}" rel="noopener">
									{{ $label }}
								</a>
							</li>
						@endforeach
					</ul>
				</div>
			</div>
		</div>

		<div class="row g-3">
			<div class="col-12 col-lg-4">
				<div class="card">
					<div class="card-body">
						<h2 class="h6 mb-3">Opciones</h2>

						<form method="GET" action="{{ route('admin.tools.code-editor.edit', ['language' => $languageKey]) }}" class="vstack gap-2">
							<div>
								<label class="form-label mb-1">Debounce (ms)</label>
								<input type="number" name="debounceMs" class="form-control form-control-sm" min="0" step="100" value="{{ (int) $options['debounceMs'] }}">
								<div class="form-text">Default: 1000</div>
							</div>

							<hr class="my-2">

							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="disabled" value="1" id="opt-disabled" @checked($options['disabled'])>
								<label class="form-check-label" for="opt-disabled">disabled</label>
							</div>

							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="readonly" value="1" id="opt-readonly" @checked($options['readonly'])>
								<label class="form-check-label" for="opt-readonly">readonly</label>
							</div>

							<hr class="my-2">

							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="lineNumbers" value="1" id="opt-lineNumbers" @checked($options['lineNumbers'])>
								<label class="form-check-label" for="opt-lineNumbers">lineNumbers</label>
							</div>

							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="highlightActive" value="1" id="opt-highlightActive" @checked($options['highlightActive'])>
								<label class="form-check-label" for="opt-highlightActive">highlightActive</label>
							</div>

							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="highlightOccurrences" value="1" id="opt-highlightOccurrences" @checked($options['highlightOccurrences'])>
								<label class="form-check-label" for="opt-highlightOccurrences">highlightOccurrences</label>
							</div>

							<hr class="my-2">

							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="autocomplete" value="1" id="opt-autocomplete" @checked($options['autocomplete'])>
								<label class="form-check-label" for="opt-autocomplete">autocomplete</label>
							</div>

							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="autoClose" value="1" id="opt-autoClose" @checked($options['autoClose'])>
								<label class="form-check-label" for="opt-autoClose">autoClose</label>
							</div>

							<hr class="my-2">

							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="autoIndent" value="1" id="opt-autoIndent" @checked($options['autoIndent'])>
								<label class="form-check-label" for="opt-autoIndent">autoIndent</label>
							</div>

							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="reindent" value="1" id="opt-reindent" @checked($options['reindent'])>
								<label class="form-check-label" for="opt-reindent">reindent</label>
							</div>

							<hr class="my-2">

							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="lint" value="1" id="opt-lint" @checked($options['lint'])>
								<label class="form-check-label" for="opt-lint">lint</label>
							</div>

							<hr class="my-2">

							<button type="submit" class="btn btn-sm btn-primary">Aplicar</button>

							<div class="text-muted small mt-2">
								El editor emite <code>change</code> tras inactividad total y solo si hubo cambios.
							</div>
						</form>
					</div>
				</div>

				<div class="card mt-3">
					<div class="card-body">
						<h2 class="h6 mb-2">Snippets</h2>
						<p class="text-muted small mb-2">Recargar con el snippet por defecto:</p>
						<a class="btn btn-sm btn-outline-danger"
						   href="{{ route('admin.tools.code-editor.edit', ['language' => $languageKey]) }}"
						   target="_self"
						>
							Reset
						</a>
					</div>
				</div>
			</div>

			<div class="col-12 col-lg-8">
				<div class="card">
					<div class="card-body">
						<dev-code-editor-playground
							language="{{ $languageKey }}"
							:debounce-ms="{{ (int) $options['debounceMs'] }}"
							:disabled="{{ $options['disabled'] ? 'true' : 'false' }}"
							:readonly="{{ $options['readonly'] ? 'true' : 'false' }}"
							:line-numbers="{{ $options['lineNumbers'] ? 'true' : 'false' }}"
							:highlight-active="{{ $options['highlightActive'] ? 'true' : 'false' }}"
							:highlight-occurrences="{{ $options['highlightOccurrences'] ? 'true' : 'false' }}"
							:autocomplete="{{ $options['autocomplete'] ? 'true' : 'false' }}"
							:auto-close="{{ $options['autoClose'] ? 'true' : 'false' }}"
							:auto-indent="{{ $options['autoIndent'] ? 'true' : 'false' }}"
							:reindent="{{ $options['reindent'] ? 'true' : 'false' }}"
							:lint="{{ $options['lint'] ? 'true' : 'false' }}"
							:model-value='@json($initialCode)'
						/>

						<hr class="my-3">

						<div class="text-muted small">
							Si el editor no aparece, verifica que los componentes Vue <code>CodeEditor</code> y <code>CodeEditorPlayground</code> estén registrados y que el layout cargue tus assets (Vite).
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection
