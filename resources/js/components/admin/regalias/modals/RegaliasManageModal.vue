<!-- resources/js/components/admin/regalias/modals/RegaliasManageModal.vue -->
<template>
  <div class="modal fade" tabindex="-1" ref="modalEl">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <span v-if="currentStep === 1">Seleccionar beneficiario de regalías</span>
            <span v-else>Configurar regalías</span>
          </h5>
          <button type="button" class="btn-close" @click="close"></button>
        </div>

        <div class="modal-body">
          <!-- PASO 1: selector genérico de usuarios (beneficiario) -->
          <div v-if="currentStep === 1">
            <!--
              IMPORTANTE:
              Sustituye <admin-users-generic-user-selector> por el tag real de tu
              componente genérico de selector de usuarios (el que ya tienes creado).
            -->
            <user-search-selector
              v-if="isOpen && currentStep === 1"
              ref="beneficiarySelector"
              :status="fixedBeneficiaryStatus"
              @selected="onBeneficiarySelected"
            />
          </div>

          <!-- PASO 2: ficha del beneficiario + pestañas usuarios/unidades -->
          <div v-else>
            <!-- Ficha del beneficiario, con protagonismo -->
            <div class="card mb-3 border border-primary">
              <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-bold fs-5">
                    {{ selectedBeneficiary.display_name || 'error' }}
                  </div>
                  <div class="text-muted">
                    {{ selectedBeneficiary.email || 'error' }}
                  </div>
                  <span
                    v-if="selectedBeneficiary.status && selectedBeneficiary.status !== 'active'"
                    class="badge rounded-pill text-bg-danger mt-1"
                  >
                    {{ selectedBeneficiary.status }}
                  </span>
                </div>
                <div class="text-end">
                  <div class="text-muted small">ID beneficiario</div>
                  <div class="fw-semibold">#{{ selectedBeneficiary.id }}</div>
                </div>
              </div>
            </div>

            <!-- Tabs de origen de regalías -->
            <ul class="nav nav-tabs mb-3">
              <li class="nav-item" v-if="hasSourceType('unit')">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: activeTab === 'unit' }"
                  @click="setActiveTab('unit')"
                >
                  Regalias Directas
                </button>
              </li>
              <li class="nav-item" v-if="hasSourceType('user')">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: activeTab === 'user' }"
                  @click="setActiveTab('user')"
                >
                  Regalias Indirectas
                </button>
              </li>
            </ul>

            <div class="tab-content">
              <!-- Pestaña: usuarios origen -->
              <div
                class="tab-pane fade"
                :class="{ show: activeTab === 'user', active: activeTab === 'user' }"
                v-if="hasSourceType('user')"
              >
                <admin-regalias-tabs-regalias-users-tab
                  v-if="isOpen && activeTab === 'user'"
                  :beneficiary-id="selectedBeneficiary.id"
                  @changed="onAnyChange"
                />
              </div>

              <!-- Pestaña: unidades origen -->
              <div
                class="tab-pane fade"
                :class="{ show: activeTab === 'unit', active: activeTab === 'unit' }"
                v-if="hasSourceType('unit')"
              >
                <admin-regalias-tabs-regalias-units-tab
                  v-if="isOpen && activeTab === 'unit'"
                  :beneficiary-id="selectedBeneficiary.id"
                  @changed="onAnyChange"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button
            v-if="currentStep === 2 && !beneficiaryLocked"
            type="button"
            class="btn btn-light"
            @click="goBackToStep1"
          >
            Cambiar beneficiario
          </button>
          <button type="button" class="btn btn-primary" @click="close">
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AdminRegaliasModalsRegaliasManageModal',
  props: {
    // Tipos de origen que el backend/entorno permite (APP_REGALIAS)
    sources: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      modal: null,

      isOpen: false,            // <-- clave para que el selector (paso 1) se monte solo al abrir
      currentStep: 1,

      selectedBeneficiary: {
        id: null,
        display_name: '',
        email: '',
        status: null,
      },

      // Copia interna de los tipos de origen (user, unit, etc.)
      internalSources: [],

      // Se definirá dinámicamente en open() con resolveInitialTab
      activeTab: '',

      // Status fijo para el selector de beneficiario en paso 1
      fixedBeneficiaryStatus: 'active',

      // true cuando el beneficiario viene fijado desde la card (flujo B)
      // false cuando se eligió dentro del modal (flujo A)
      beneficiaryLocked: false,
    };
  },
  methods: {
    ensureModal() {
      if (!this.modal && typeof bootstrap !== 'undefined') {
        this.modal = bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl);
      }
    },

    /**
     * Abre el modal.
     * config puede incluir:
     *  - beneficiary: {id, display_name, email, status}
     *  - preferredSourceType: 'user' | 'unit'
     *  - sources: ['user','unit', ...] (opcional, si no se pasa usa this.sources)
     */
    open(config = {}) {
      this.ensureModal();

      const { beneficiary, preferredSourceType, sources } = config;

      // Fuentes internas
      if (Array.isArray(sources)) {
        this.internalSources = sources;
      } else {
        this.internalSources = Array.isArray(this.sources) ? this.sources : [];
      }

      // Beneficiario seleccionado (opcional)
      if (beneficiary && typeof beneficiary.id === 'number') {
        // Flujo B: viene desde la card, beneficiario fijado
        this.selectedBeneficiary = {
          id: beneficiary.id,
          display_name: beneficiary.display_name || '',
          email: beneficiary.email || '',
          status: beneficiary.status || null,
        };
        this.currentStep = 2;
        this.beneficiaryLocked = true;
      } else {
        // Flujo A: se seleccionará dentro del modal
        this.resetBeneficiary();
        this.currentStep = 1;
        this.beneficiaryLocked = false;
      }

      // Tab activa según preferredSourceType o primera source disponible
      this.activeTab = this.resolveInitialTab(preferredSourceType);

      // Importante: marcar como abierto ANTES de mostrar el modal para que
      // v-if monte los componentes de cada paso justo al abrir.
      this.isOpen = true;

      this.$nextTick(() => {
        this.modal.show();

        // Si estamos en paso 1, el selector se montará aquí (v-if con isOpen && currentStep === 1)
        // y su mounted() hará la carga inicial de usuarios vía AJAX.
      });
    },

    close() {
      if (this.modal) {
        this.modal.hide();
      }
      this.afterHidden();
    },

    afterHidden() {
      // Reiniciamos flags para que en la próxima apertura el selector vuelva a montarse
      this.isOpen = false;
      this.currentStep = 1;
      this.beneficiaryLocked = false;
      this.resetBeneficiary();
      this.activeTab = '';
    },

    resetBeneficiary() {
      this.selectedBeneficiary = {
        id: null,
        display_name: '',
        email: '',
        status: null,
      };
    },

    resolveInitialTab(preferred) {
      // 1) Si hay una pestaña preferida válida, usarla
      if (preferred && this.hasSourceType(preferred)) {
        return preferred;
      }

      // 2) Si hay una lista de tipos permitidos, usar el primero como default
      if (Array.isArray(this.internalSources) && this.internalSources.length > 0) {
        const first = this.internalSources[0];
        if (this.hasSourceType(first)) {
          return first;
        }
      }

      // 3) Fallback de seguridad por mala configuración:
      if (this.hasSourceType('user')) {
        return 'user';
      }
      if (this.hasSourceType('unit')) {
        return 'unit';
      }

      // 4) Si no hay ningún tipo disponible, devolvemos cadena vacía
      return '';
    },

    hasSourceType(type) {
      if (!type) return false;
      if (!Array.isArray(this.internalSources)) return false;
      return this.internalSources.includes(type);
    },

    setActiveTab(tab) {
      if (!this.hasSourceType(tab)) return;
      this.activeTab = tab;
    },

    // Evento desde el selector genérico del PASO 1
    onBeneficiarySelected(user) {
      if (!user || typeof user.id !== 'number') {
        return;
      }

      this.selectedBeneficiary = {
        id: user.id,
        display_name: user.display_name || '',
        email: user.email || '',
        status: user.status || null,
      };

      // Flujo A: beneficiario elegido dentro del modal => se puede cambiar
      this.beneficiaryLocked = false;

      this.currentStep = 2;
      // Mantener preferencia actual si es válida, o caer a la primera permitida
      this.activeTab = this.resolveInitialTab(this.activeTab);
    },

    goBackToStep1() {
      if (this.beneficiaryLocked) {
        // Por seguridad: si está bloqueado, no debería poder volver al paso 1
        return;
      }

      this.currentStep = 1;
      // El selector vuelve a montarse con v-if cuando currentStep === 1
      // y isOpen === true, por lo que hará otra carga por AJAX si así está programado.
      this.resetBeneficiary();
    },

    // Cualquier cambio en pestañas (attach/detach, etc.)
    onAnyChange() {
      this.$emit('changed');
    },
  },
  mounted() {
    // Escuchar evento de ocultar modal de Bootstrap para limpiar estado
    if (this.$refs.modalEl) {
      this.$refs.modalEl.addEventListener('hidden.bs.modal', () => {
        this.afterHidden();
      });
    }
  },
};
</script>
