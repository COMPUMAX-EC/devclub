<?php

namespace App\Http\Controllers\Dev;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CodeEditorPlaygroundController extends Controller
{
	public function index()
	{
		$languages = [
			'css'      => 'CSS',
			'html'     => 'HTML',
			'json'     => 'JSON',
			'markdown' => 'Markdown',
			'php'      => 'PHP',
			'xml'      => 'XML',
			'yaml'     => 'YAML',
			'blade'    => 'Blade',
		];

		return view('dev.code-editor.index', [
			'languages' => $languages,
		]);
	}

	public function edit(Request $request, string $language)
	{
		$languages = [
			'css'      => 'CSS',
			'html'     => 'HTML',
			'json'     => 'JSON',
			'markdown' => 'Markdown',
			'php'      => 'PHP',
			'xml'      => 'XML',
			'yaml'     => 'YAML',
			'blade'    => 'Blade',
		];

		abort_unless(array_key_exists($language, $languages), 404);

		$defaults = [
			'debounceMs'           => 1000,

			'disabled'             => false,
			'readonly'             => false,

			'lineNumbers'          => true,
			'highlightActive'      => true,
			'highlightOccurrences' => true,

			'autocomplete'         => true,
			'autoClose'            => true,

			'autoIndent'           => true,
			'reindent'             => true,

			'lint'                 => true,
		];

		$options = [
			'debounceMs'            => (int) $request->query('debounceMs', $defaults['debounceMs']),

			'disabled'              => $this->toBool($request->query('disabled', $defaults['disabled'])),
			'readonly'              => $this->toBool($request->query('readonly', $defaults['readonly'])),

			'lineNumbers'           => $this->toBool($request->query('lineNumbers', $defaults['lineNumbers'])),
			'highlightActive'       => $this->toBool($request->query('highlightActive', $defaults['highlightActive'])),
			'highlightOccurrences'  => $this->toBool($request->query('highlightOccurrences', $defaults['highlightOccurrences'])),

			'autocomplete'          => $this->toBool($request->query('autocomplete', $defaults['autocomplete'])),
			'autoClose'             => $this->toBool($request->query('autoClose', $defaults['autoClose'])),

			'autoIndent'            => $this->toBool($request->query('autoIndent', $defaults['autoIndent'])),
			'reindent'              => $this->toBool($request->query('reindent', $defaults['reindent'])),

			'lint'                  => $this->toBool($request->query('lint', $defaults['lint'])),
		];

		$samples = [
			'css' => <<<CSS
:root {
\t--primary: #0d6efd;
}

.container {
\tdisplay: grid;
\tgap: 12px;
}
CSS,
			'html' => <<<HTML
<!doctype html>
<html lang="es">
<head>
\t<meta charset="utf-8" />
\t<title>Demo</title>
</head>
<body>
\t<div class="container">
\t\t<h1>Hola</h1>
\t\t<p>Probando <strong>CodeEditor</strong>.</p>
\t</div>
</body>
</html>
HTML,
			'json' => <<<JSON
{
\t"enabled": true,
\t"items": [
\t\t{"id": 1, "name": "A"},
\t\t{"id": 2, "name": "B"}
\t]
}
JSON,
			'markdown' => <<<MD
# CodeEditor

- Item 1
- Item 2

**Negrita**, _cursiva_, `inline code`

```json
{"hello":"world"}
```
MD,
			'php' => <<<PHP
<?php

declare(strict_types=1);

function greet(string \$name): string
{
\treturn "Hola, {\$name}";
}

echo greet("Ariel");
PHP,
			'xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root>
\t<item id="1">A</item>
\t<item id="2">B</item>
</root>
XML,
			'yaml' => <<<YAML
enabled: true
items:
  - id: 1
    name: A
  - id: 2
    name: B
YAML,
			'blade' => <<<BLADE
@extends('layouts.app')

@section('content')
\t<div class="container">
\t\t<h1>{{ __('Hola') }}</h1>

\t\t@if(\$user)
\t\t\t<p>Usuario: {{ \$user->email }}</p>
\t\t@else
\t\t\t<p>No autenticado</p>
\t\t@endif

\t\t{!! '<span>Raw HTML</span>' !!}
\t</div>
@endsection
BLADE,
		];

		$initialCode = (string) $request->query('code', $samples[$language] ?? '');

		return view('dev.code-editor.edit', [
			'languageKey'  => $language,
			'languageName' => $languages[$language],
			'languages'    => $languages,
			'options'      => $options,
			'initialCode'  => $initialCode,
		]);
	}

	private function toBool($value): bool
	{
		if (is_bool($value)) return $value;

		$v = strtolower((string) $value);

		return in_array($v, ['1', 'true', 'on', 'yes'], true);
	}
}
