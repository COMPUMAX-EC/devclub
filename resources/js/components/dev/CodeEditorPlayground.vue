<!-- resources/js/components/dev/CodeEditorPlayground.vue -->
<template>
	<div>
		<div class="mb-2 text-muted small">
			TAB inserta <code>\t</code> real (solo cuando es editable).
		</div>

		<code-editor
			:id="editorId"
			:name="editorName"
			:language="language"
			:debounce-ms="debounceMs"
			:disabled="disabled"
			:readonly="readonly"
			:line-numbers="lineNumbers"
			:highlight-active="highlightActive"
			:highlight-occurrences="highlightOccurrences"
			:autocomplete="autocomplete"
			:auto-close="autoClose"
			:auto-indent="autoIndent"
			:reindent="reindent"
			:lint="lint"
			v-model="vModelProxy"
		/>

		<hr class="my-3">

		<div class="card">
			<div class="card-body">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<h2 class="h6 mb-0">Eventos del editor</h2>

					<div class="d-flex gap-2">
						<button type="button" class="btn btn-sm btn-outline-secondary" @click="clear">
							Limpiar
						</button>
					</div>
				</div>

				<div v-if="!events.length" class="text-muted small">
					Aún no hay eventos. Edita el contenido y espera la inactividad (debounce) para que se actualice el <code>v-model</code>.
				</div>

				<div v-else>
					<div class="row g-3">
						<div class="col-12 col-lg-4">
							<div class="small text-muted mb-2">Último evento (raw)</div>

							<div class="border rounded p-2 small bg-light">
								<div><strong>type:</strong> update:modelValue</div>
								<div><strong>id:</strong> {{ events[0].id }}</div>
								<div><strong>name:</strong> {{ events[0].name }}</div>
								<div><strong>language:</strong> {{ events[0].language }}</div>
								<div><strong>length:</strong> {{ (events[0].value || '').length }}</div>
								<div><strong>lines:</strong> {{ lineCountOf(events[0].value || '') }}</div>
							</div>

							<div class="small text-muted mt-3 mb-2">Historial ({{ events.length }})</div>
							<div class="list-group">
								<button
									v-for="(ev, idx) in events"
									:key="idx"
									type="button"
									class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
									:class="{ active: selectedIndex === idx }"
									@click="selectedIndex = idx"
								>
									<span class="text-truncate" style="max-width: 70%;">
										{{ ev.language }} · {{ (ev.value || '').length }} chars
									</span>
									<span class="badge bg-secondary">
										{{ (ev.value || '').length }}
									</span>
								</button>
							</div>
						</div>

						<div class="col-12 col-lg-8">
							<div class="small text-muted mb-2">Payload (raw JSON)</div>
							<pre class="border rounded p-2 bg-light small mb-0" style="max-height: 420px; overflow:auto;">{{ prettySelected }}</pre>
						</div>
					</div>
				</div>

				<div class="text-muted small mt-3">
					Nota: el editor actualiza el <code>v-model</code> tras inactividad total y solo si hubo cambios.
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
	language: { type: String, required: true },
	debounceMs: { type: Number, default: 1000 },

	disabled: { type: Boolean, default: false },
	readonly: { type: Boolean, default: false },

	lineNumbers: { type: Boolean, default: true },
	highlightActive: { type: Boolean, default: true },
	highlightOccurrences: { type: Boolean, default: true },

	autocomplete: { type: Boolean, default: true },
	autoClose: { type: Boolean, default: true },
	autoIndent: { type: Boolean, default: true },
	reindent: { type: Boolean, default: true },

	lint: { type: Boolean, default: true },

	// El playground se mantiene controlable desde afuera si quieres:
	modelValue: { type: String, default: '' },

	// Identidad del editor (se refleja en el payload estricto del v-model)
	id: { type: String, default: 'playground-editor' },
	name: { type: String, default: 'code' },
});

const emit = defineEmits(['update:modelValue']);

const events = ref([]);
const selectedIndex = ref(0);

const editorId = computed(() => props.id || 'playground-editor');
const editorName = computed(() => props.name || 'code');

// Este es el “modelo” local (string) que se edita; el CodeEditor commitea por debounce.
const localValue = ref(props.modelValue ?? '');

// Proxy para v-model del CodeEditor:
// - Getter: entrega un objeto con shape estricto (para que el editor edite .value)
// - Setter: recibe ese mismo objeto desde CodeEditor y lo guardamos exacto como “evento”
const vModelProxy = computed({
	get() {
		return {
			id: editorId.value,
			name: editorName.value,
			language: props.language,
			value: localValue.value ?? '',
		};
	},
	set(payload) {
		events.value = [payload, ...events.value].slice(0, 25);
		selectedIndex.value = 0;

		localValue.value = payload?.value ?? '';
		emit('update:modelValue', localValue.value);
	},
});

function clear() {
	events.value = [];
	selectedIndex.value = 0;
}

function lineCountOf(text) {
	let count = 1;
	for (let i = 0; i < text.length; i++) {
		if (text.charCodeAt(i) === 10) count++;
	}
	return count;
}

const selected = computed(() => events.value[selectedIndex.value] ?? null);

const prettySelected = computed(() => {
	if (!selected.value) return '';
	try {
		return JSON.stringify(selected.value, null, 2);
	} catch {
		return String(selected.value);
	}
});

watch(
	() => props.modelValue,
	(newVal) => {
		localValue.value = newVal ?? '';
	},
	{ immediate: true },
);
</script>
