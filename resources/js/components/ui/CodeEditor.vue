<!-- resources/js/components/ui/CodeEditor.vue -->
<template>
	<div
		ref="hostEl"
		class="code-editor"
		:style="{ height: height }"
		:class="{
			'is-disabled': disabled,
			'is-readonly': readonly && !disabled,
		}"
		:aria-disabled="disabled ? 'true' : 'false'"
		:aria-readonly="readonly ? 'true' : 'false'"
	></div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';

import { EditorState, Compartment, RangeSetBuilder } from '@codemirror/state';
import { EditorView, keymap, highlightActiveLine, highlightActiveLineGutter, Decoration, ViewPlugin } from '@codemirror/view';

import {
	indentOnInput,
	indentUnit,
	bracketMatching,
	foldGutter,
	foldKeymap,
	syntaxHighlighting,
	defaultHighlightStyle,
} from '@codemirror/language';

import { closeBrackets, closeBracketsKeymap, autocompletion } from '@codemirror/autocomplete';
import { highlightSelectionMatches } from '@codemirror/search';
import {
	history,
	historyKeymap,
	defaultKeymap,
	insertTab,
	indentLess,
} from '@codemirror/commands';
import { lintGutter, linter } from '@codemirror/lint';
import { lineNumbers } from '@codemirror/view';

import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { javascript } from '@codemirror/lang-javascript';
import { markdown } from '@codemirror/lang-markdown';
import { php } from '@codemirror/lang-php';
import { xml } from '@codemirror/lang-xml';
import { yaml } from '@codemirror/lang-yaml';

/**
 * Props
 * - v-model: modelValue puede ser String (solo value) o Object (payload estricto con value)
 * - id/name: identifican la instancia
 */
const props = defineProps({
	// String => el editor edita ese string y emite update:modelValue(string)
	// Object => el editor edita modelValue.value y emite update:modelValue({id,name,language,value})
	modelValue: { type: [String, Object], default: '' },

	// Identidad del editor (para debug/telemetría del padre)
	id: { type: String, default: '' },
	name: { type: String, default: '' },

	// css | html | json | markdown | php | xml | yaml | blade
	language: { type: String, required: true },

	debounceMs: { type: Number, default: 1000 },

	disabled: { type: Boolean, default: false },
	readonly: { type: Boolean, default: false },

	/**
	 * Altura del área editable (acepta cualquier unidad CSS: px, %, vh, rem, etc.)
	 * Ej: "200px", "50%", "30vh"
	 */
	height: { type: String, default: '60vh' },

	// Visual (opcionales; default true)
	lineNumbers: { type: Boolean, default: true },
	highlightActive: { type: Boolean, default: true },
	highlightOccurrences: { type: Boolean, default: true },

	// Assist (opcionales; default true)
	autocomplete: { type: Boolean, default: true },
	autoClose: { type: Boolean, default: true },
	autoIndent: { type: Boolean, default: true },
	reindent: { type: Boolean, default: true },

	// Quality (opcionales; default true)
	lint: { type: Boolean, default: true },
});

// ✅ Opción A: un solo evento para v-model
const emit = defineEmits(['update:modelValue']);

const hostEl = ref(null);
let view = null;

let idleTimer = null;
let lastCommittedValue = '';
let internalValue = '';

/**
 * Compartments
 */
const languageCompartment = new Compartment();
const featuresCompartment = new Compartment();
const behaviorCompartment = new Compartment();
const lintCompartment = new Compartment();
const autocompleteCompartment = new Compartment();
const readonlyCompartment = new Compartment();
const themeCompartment = new Compartment();

/**
 * Helpers para soportar modelValue como String u Object
 */
function isObjectModel(v) {
	return v !== null && typeof v === 'object' && Object.prototype.hasOwnProperty.call(v, 'value');
}

function getIncomingValue(v) {
	if (typeof v === 'string') return v;
	if (isObjectModel(v)) return (v.value ?? '').toString();
	return '';
}

/**
 * Opciones: default=true
 */
function optEnabled(v) {
	return v !== false;
}

/**
 * Utils
 */
function clearIdleTimer() {
	if (idleTimer) {
		clearTimeout(idleTimer);
		idleTimer = null;
	}
}

function isDirty() {
	return internalValue !== lastCommittedValue;
}

function emitVModelCommit(value) {
	// Si el padre usa v-model con objeto, emitimos payload estricto.
	if (isObjectModel(props.modelValue)) {
		emit('update:modelValue', {
			id: props.id ?? '',
			name: props.name ?? '',
			language: props.language,
			value,
		});
		return;
	}

	// Si el padre usa v-model con string, emitimos string.
	emit('update:modelValue', value);
}

function scheduleIdleCommit() {
	if (!isDirty()) return;

	clearIdleTimer();
	idleTimer = setTimeout(() => {
		if (!isDirty()) return;

		lastCommittedValue = internalValue;
		emitVModelCommit(internalValue);
	}, Math.max(0, Number(props.debounceMs) || 0));
}

/**
 * Actividad TOTAL (reinicia timer SOLO si hay cambios pendientes)
 */
let lastPointerMoveTs = 0;
function touchActivity() {
	if (isDirty()) scheduleIdleCommit();
}

/**
 * Blade overlay + autocomplete
 */
const BLADE_DIRECTIVES = [
	'if', 'elseif', 'else', 'endif',
	'foreach', 'endforeach',
	'for', 'endfor',
	'while', 'endwhile',
	'forelse', 'empty', 'endforelse',
	'switch', 'case', 'default', 'endswitch',
	'isset', 'endisset',
	'unless', 'endunless',
	'auth', 'endauth',
	'guest', 'endguest',
	'section', 'endsection', 'show',
	'yield',
	'extends',
	'include', 'includeIf', 'includeWhen', 'includeUnless',
	'component', 'endcomponent', 'slot', 'endslot',
	'push', 'endpush', 'stack',
	'prepend', 'endprepend',
	'csrf', 'method',
	'php', 'endphp',
	'verbatim', 'endverbatim',
];

function bladeDecorationsPlugin() {
	const directiveRe = /@[A-Za-z_][A-Za-z0-9_]*/g;
	const echoRe = /\{\{\s*[\s\S]*?\s*\}\}/g;
	const rawEchoRe = /\{!!\s*[\s\S]*?\s*!!\}/g;
	const commentRe = /\{\{\-\-[\s\S]*?\-\-\}\}/g;

	function buildDecos(docText) {
		const builder = new RangeSetBuilder();
		const ranges = [];

		const collectAll = (re, cls) => {
			re.lastIndex = 0;
			let m;
			while ((m = re.exec(docText)) !== null) {
				const from = m.index;
				const to = m.index + m[0].length;
				if (to > from) ranges.push({ from, to, cls });
				if (m[0].length === 0) re.lastIndex++;
			}
		};

		collectAll(commentRe, 'cm-blade-comment');
		collectAll(rawEchoRe, 'cm-blade-raw');
		collectAll(echoRe, 'cm-blade-echo');
		collectAll(directiveRe, 'cm-blade-directive');

		ranges.sort((a, b) => {
			if (a.from !== b.from) return a.from - b.from;
			if (a.to !== b.to) return a.to - b.to;
			return a.cls < b.cls ? -1 : a.cls > b.cls ? 1 : 0;
		});

		for (const r of ranges) {
			builder.add(r.from, r.to, Decoration.mark({ class: r.cls }));
		}

		return builder.finish();
	}

	return ViewPlugin.fromClass(
		class {
			constructor(v) {
				this.decorations = buildDecos(v.state.doc.toString());
			}
			update(update) {
				if (update.docChanged) {
					this.decorations = buildDecos(update.state.doc.toString());
				}
			}
		},
		{ decorations: (v) => v.decorations },
	);
}

function bladeCompletionSource(context) {
	const { state, pos } = context;
	const before = state.doc.sliceString(Math.max(0, pos - 64), pos);

	const atMatch = /@([A-Za-z_]*)$/.exec(before);
	if (!atMatch) return null;

	const typed = atMatch[1] ?? '';
	const from = pos - typed.length;

	const options = BLADE_DIRECTIVES
		.filter((d) => d.startsWith(typed))
		.slice(0, 50)
		.map((d) => ({ label: d, type: 'keyword', apply: d }));

	return { from, options, validFor: /^[A-Za-z_]*$/ };
}

/**
 * Linters simples
 */
function jsonLinter() {
	return (viewInstance) => {
		const text = viewInstance.state.doc.toString();
		if (!text.trim()) return [];

		try {
			JSON.parse(text);
			return [];
		} catch (e) {
			const msg = (e && e.message) ? String(e.message) : 'JSON inválido';
			let pos = 0;
			const m = /position\s+(\d+)/i.exec(msg);
			if (m) pos = Math.max(0, Math.min(Number(m[1]) || 0, text.length));

			return [{
				from: pos,
				to: Math.min(pos + 1, text.length),
				severity: 'error',
				message: msg,
			}];
		}
	};
}

function xmlLinter() {
	return (viewInstance) => {
		const text = viewInstance.state.doc.toString();
		if (!text.trim()) return [];

		try {
			const parser = new DOMParser();
			const doc = parser.parseFromString(text, 'application/xml');
			const errorNode = doc.getElementsByTagName('parsererror')[0];
			if (!errorNode) return [];

			return [{
				from: 0,
				to: Math.min(1, text.length),
				severity: 'error',
				message: 'XML inválido (parsererror).',
			}];
		} catch {
			return [{
				from: 0,
				to: Math.min(1, text.length),
				severity: 'error',
				message: 'XML inválido.',
			}];
		}
	};
}

function bladeHeuristicLinter() {
	const pairs = [
		['@if', '@endif'],
		['@foreach', '@endforeach'],
		['@for', '@endfor'],
		['@while', '@endwhile'],
		['@forelse', '@endforelse'],
		['@switch', '@endswitch'],
		['@isset', '@endisset'],
		['@unless', '@endunless'],
		['@auth', '@endauth'],
		['@guest', '@endguest'],
		['@php', '@endphp'],
		['@verbatim', '@endverbatim'],
		['@section', '@endsection'],
		['@push', '@endpush'],
		['@prepend', '@endprepend'],
	];

	return (viewInstance) => {
		const text = viewInstance.state.doc.toString();
		if (!text.trim()) return [];

		const diags = [];
		const count = (needle) =>
			(text.match(new RegExp(needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g')) || []).length;

		const openEcho = count('{{');
		const closeEcho = count('}}');
		if (openEcho !== closeEcho) {
			diags.push({
				from: 0,
				to: Math.min(1, text.length),
				severity: 'warning',
				message: `Blade: desbalance {{ / }} (${openEcho} vs ${closeEcho}).`,
			});
		}

		const openRaw = count('{!!');
		const closeRaw = count('!!}');
		if (openRaw !== closeRaw) {
			diags.push({
				from: 0,
				to: Math.min(1, text.length),
				severity: 'warning',
				message: `Blade: desbalance {!! / !!} (${openRaw} vs ${closeRaw}).`,
			});
		}

		for (const [open, close] of pairs) {
			const o = count(open);
			const c = count(close);
			if (o !== c) {
				diags.push({
					from: 0,
					to: Math.min(1, text.length),
					severity: 'warning',
					message: `Blade: desbalance ${open} / ${close} (${o} vs ${c}).`,
				});
			}
		}

		return diags;
	};
}

/**
 * Lenguajes
 */
function languageExtension(lang) {
	switch ((lang || '').toLowerCase()) {
		case 'css': return css();
		case 'html': return html();
		case 'json': return javascript({ json: true });
		case 'markdown': return markdown();
		case 'php': return php();
		case 'xml': return xml();
		case 'yaml': return yaml();
		case 'blade': return html();
		default: return [];
	}
}

function buildLintExtension() {
	if (!optEnabled(props.lint)) return [];
	const lang = (props.language || '').toLowerCase();

	if (lang === 'json') return [lintGutter(), linter(jsonLinter())];
	if (lang === 'xml') return [lintGutter(), linter(xmlLinter())];
	if (lang === 'blade') return [lintGutter(), linter(bladeHeuristicLinter())];

	return [];
}

function buildAutocompleteExtension() {
	if (!optEnabled(props.autocomplete)) return [];

	const lang = (props.language || '').toLowerCase();
	if (lang === 'blade') {
		return [autocompletion({ override: [bladeCompletionSource], activateOnTyping: true })];
	}

	return [autocompletion({ activateOnTyping: true })];
}

function buildFeaturesExtension() {
	const exts = [];

	exts.push(syntaxHighlighting(defaultHighlightStyle, { fallback: true }));

	if (optEnabled(props.lineNumbers)) {
		exts.push(lineNumbers());
		exts.push(highlightActiveLineGutter());
	}

	if (optEnabled(props.highlightActive)) {
		exts.push(highlightActiveLine());
	}

	if (optEnabled(props.highlightOccurrences)) {
		exts.push(highlightSelectionMatches());
	}

	exts.push(history());
	exts.push(foldGutter());

	if (optEnabled(props.autoIndent) || optEnabled(props.reindent)) {
		exts.push(indentOnInput());
	}

	exts.push(bracketMatching());

	if (optEnabled(props.autoClose)) {
		exts.push(closeBrackets());
	}

	// TAB solo cuando es editable. En readonly/disabled NO interceptar.
	const isEditable = !props.disabled && !props.readonly;

	const km = [
		...(isEditable ? [
			{ key: 'Tab', run: insertTab },
			{ key: 'Shift-Tab', run: indentLess },
		] : []),

		...closeBracketsKeymap,
		...foldKeymap,
		...historyKeymap,
		...defaultKeymap,
	];

	exts.push(keymap.of(km));

	return exts;
}

function buildReadonlyExtension() {
	if (props.disabled) {
		return [EditorState.readOnly.of(true), EditorView.editable.of(false)];
	}

	if (props.readonly) {
		return [EditorState.readOnly.of(true), EditorView.editable.of(false)];
	}

	return [EditorState.readOnly.of(false), EditorView.editable.of(true)];
}

function buildBehaviorExtension() {
	return [
		EditorState.tabSize.of(4),
		indentUnit.of('\t'),
	];
}

function buildBladeLayerExtension() {
	if ((props.language || '').toLowerCase() !== 'blade') return [];
	return [bladeDecorationsPlugin()];
}

function buildThemeExtension() {
	return [
		EditorView.theme(
			{
				'&': {
					border: '1px solid var(--ce-border)',
					borderRadius: '6px',
					backgroundColor: 'var(--ce-bg)',
					color: 'var(--ce-fg)',
					fontFamily:
						'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
					fontSize: '13px',
					lineHeight: '1.5',
					height: '100%',
				},
				'.cm-scroller': {
					overflow: 'auto',
					padding: '10px 12px',
					height: '100%',
				},
				'.cm-content': {
					whiteSpace: 'pre',
					caretColor: 'var(--ce-caret)',
				},
				'.cm-gutters': {
					backgroundColor: 'transparent',
					borderRight: '1px solid var(--ce-gutter-border)',
					color: 'var(--ce-gutter-fg)',
				},

				'.cm-blade-directive': { fontWeight: '600' },
				'.cm-blade-echo': { opacity: '0.95' },
				'.cm-blade-raw': { opacity: '0.95' },
				'.cm-blade-comment': { opacity: '0.8', fontStyle: 'italic' },

				'&.cm-focused': {
					outline: 'none',
					boxShadow: '0 0 0 0.2rem var(--ce-focus-ring)',
					borderColor: 'var(--ce-focus-border)',
				},

				'&.cm-editor.cm-disabled': {
					opacity: '0.65',
					filter: 'grayscale(10%)',
				},
			},
			{ dark: false },
		),
	];
}

/**
 * Extensions completas
 */
function buildExtensions() {
	const exts = [];

	exts.push(languageCompartment.of(languageExtension(props.language)));
	exts.push(behaviorCompartment.of(buildBehaviorExtension()));
	exts.push(featuresCompartment.of(buildFeaturesExtension()));
	exts.push(autocompleteCompartment.of(buildAutocompleteExtension()));
	exts.push(lintCompartment.of(buildLintExtension()));
	exts.push(readonlyCompartment.of(buildReadonlyExtension()));
	exts.push(themeCompartment.of(buildThemeExtension()));
	exts.push(...buildBladeLayerExtension());

	exts.push(
		EditorView.updateListener.of((update) => {
			if (!update.docChanged) return;
			internalValue = update.state.doc.toString();
			scheduleIdleCommit();
		}),
	);

	return exts;
}

function reconfigure() {
	if (!view) return;
	view.dispatch({
		effects: [
			languageCompartment.reconfigure(languageExtension(props.language)),
			behaviorCompartment.reconfigure(buildBehaviorExtension()),
			featuresCompartment.reconfigure(buildFeaturesExtension()),
			autocompleteCompartment.reconfigure(buildAutocompleteExtension()),
			lintCompartment.reconfigure(buildLintExtension()),
			readonlyCompartment.reconfigure(buildReadonlyExtension()),
			themeCompartment.reconfigure(buildThemeExtension()),
		],
	});
}

function syncFromProps(model) {
	if (!view) return;

	const incoming = getIncomingValue(model);
	const current = view.state.doc.toString();
	if (incoming === current) return;

	view.dispatch({
		changes: { from: 0, to: current.length, insert: incoming },
	});

	internalValue = incoming;
	lastCommittedValue = incoming;
}

/**
 * Activity listeners (reinicia debounce por actividad total)
 */
function attachActivityListeners(el) {
	if (!el) return;

	const onKey = () => touchActivity();
	const onMouseDown = () => touchActivity();
	const onMouseUp = () => touchActivity();
	const onWheel = () => touchActivity();
	const onClick = () => touchActivity();
	const onScroll = () => touchActivity();
	const onTouch = () => touchActivity();
	const onPointerMove = () => {
		const now = Date.now();
		if (now - lastPointerMoveTs < 120) return;
		lastPointerMoveTs = now;
		touchActivity();
	};

	el.addEventListener('keydown', onKey, true);
	el.addEventListener('keyup', onKey, true);
	el.addEventListener('mousedown', onMouseDown, true);
	el.addEventListener('mouseup', onMouseUp, true);
	el.addEventListener('click', onClick, true);
	el.addEventListener('wheel', onWheel, { capture: true, passive: true });
	el.addEventListener('scroll', onScroll, true);
	el.addEventListener('touchstart', onTouch, { capture: true, passive: true });
	el.addEventListener('touchmove', onTouch, { capture: true, passive: true });
	el.addEventListener('pointermove', onPointerMove, { capture: true, passive: true });

	el.__ce_listeners__ = { onKey, onMouseDown, onMouseUp, onWheel, onClick, onScroll, onTouch, onPointerMove };
}

function detachActivityListeners(el) {
	if (!el || !el.__ce_listeners__) return;
	const l = el.__ce_listeners__;

	el.removeEventListener('keydown', l.onKey, true);
	el.removeEventListener('keyup', l.onKey, true);
	el.removeEventListener('mousedown', l.onMouseDown, true);
	el.removeEventListener('mouseup', l.onMouseUp, true);
	el.removeEventListener('click', l.onClick, true);
	el.removeEventListener('wheel', l.onWheel, true);
	el.removeEventListener('scroll', l.onScroll, true);
	el.removeEventListener('touchstart', l.onTouch, true);
	el.removeEventListener('touchmove', l.onTouch, true);
	el.removeEventListener('pointermove', l.onPointerMove, true);

	delete el.__ce_listeners__;
}

onMounted(() => {
	const el = hostEl.value;
	if (!el) return;

	internalValue = getIncomingValue(props.modelValue);
	lastCommittedValue = internalValue;

	const state = EditorState.create({
		doc: internalValue,
		extensions: buildExtensions(),
	});

	view = new EditorView({ state, parent: el });

	if (props.disabled) view.dom.classList.add('cm-disabled');

	attachActivityListeners(el);
});

onBeforeUnmount(() => {
	clearIdleTimer();
	detachActivityListeners(hostEl.value);

	if (view) {
		view.destroy();
		view = null;
	}
});

watch(
	() => props.modelValue,
	(newVal) => {
		syncFromProps(newVal);
	},
	{ immediate: false },
);

watch(
	() => [
		props.language,
		props.debounceMs,
		props.disabled,
		props.readonly,
		props.lineNumbers,
		props.highlightActive,
		props.highlightOccurrences,
		props.autocomplete,
		props.autoClose,
		props.autoIndent,
		props.reindent,
		props.lint,
		props.id,
		props.name,
	],
	() => {
		if (view) {
			if (props.disabled) view.dom.classList.add('cm-disabled');
			else view.dom.classList.remove('cm-disabled');
		}
		reconfigure();
	},
	{ deep: false },
);
</script>

<style scoped>
.code-editor {
	--ce-border: #ced4da;
	--ce-bg: #ffffff;
	--ce-fg: #212529;
	--ce-caret: #212529;

	--ce-gutter-border: rgba(0, 0, 0, 0.06);
	--ce-gutter-fg: rgba(0, 0, 0, 0.45);

	--ce-focus-ring: rgba(13, 110, 253, 0.25);
	--ce-focus-border: rgba(13, 110, 253, 0.65);

	/* fallback visual si alguien pasa height muy chico; el height real viene por style binding */
	min-height: 120px;
}

.code-editor :deep(.cm-editor) {
	height: 100%;
}

.code-editor.is-disabled {
	pointer-events: none;
	opacity: 0.75;
}

.code-editor.is-readonly {
	opacity: 0.95;
}
</style>
