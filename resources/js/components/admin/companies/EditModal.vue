<!-- /resources/js/components/admin/companies/EditModal.vue -->
<template>
    <div
        class="modal fade"
        tabindex="-1"
        ref="modal"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span v-if="isCreateMode">Nueva empresa</span>
                        <span v-else>Editar empresa</span>
                    </h5>
                    <button
                        type="button"
                        class="btn btn-icon btn-sm btn-active-light-primary ms-2"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                        @click="close"
                    >
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <div v-if="formError" class="alert alert-danger">
                        {{ formError }}
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Nombre de la empresa</label>
                            <input
                                type="text"
                                class="form-control form-control-solid"
                                v-model="form.name"
                            >
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Código único</label>
                            <div class="input-group">
                                <input
                                    type="text"
                                    class="form-control form-control-solid text-uppercase"
									:class="{'is-invalid': (shortCodeStatus !== 'available' && shortCodeStatus!=null), 'is-valid': (shortCodeStatus == 'available')}"
                                    maxlength="5"
                                    v-model="form.short_code"
                                >
                            </div>
                            <div v-if="shortCodeMessage" class="form-text text-muted">
                                {{ shortCodeMessage }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal"
                        @click="close"
                    >
                        Cerrar
                    </button>

                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="submit"
                        :disabled="isSubmitting"
                    >
                        <span v-if="isSubmitting" class="spinner-border spinner-border-sm align-middle me-1"></span>
                        <span v-if="isCreateMode">Crear empresa</span>
                        <span v-else>Guardar cambios</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'AdminCompaniesEditModal',

    data() {
        return {
            bootstrapModal: null,
            isCreateMode: true,
            companyId: null,
            isSubmitting: false,
            formError: null,

            form: this.emptyForm(),

            shortCodeChecking: false,
            shortCodeStatus: null, // null | 'available' | 'taken'
            shortCodeMessage: null,
            shortCodeTimeoutId: null,
        };
    },

    watch: {
        'form.short_code'(newValue) {
            this.handleShortCodeChange(newValue);
        },
    },

    mounted() {
        this.setupModal();
    },

    methods: {
        emptyForm() {
            return {
                name: '',
                short_code: '',
            };
        },

        setupModal() {
            if (!window.bootstrap || !this.$refs.modal) {
                return;
            }

            this.bootstrapModal = new window.bootstrap.Modal(this.$refs.modal, {
                backdrop: 'static',
            });
        },

        openForCreate() {
            this.isCreateMode = true;
            this.companyId = null;
            this.formError = null;
            this.form = this.emptyForm();

            this.shortCodeStatus = null;
            this.shortCodeMessage = null;

            if (!this.bootstrapModal) {
                this.setupModal();
            }

            this.bootstrapModal.show();
        },

        async openForEdit(companyId) {
            // Preparado para uso futuro (si quieres abrir este modal para editar básicos).
            this.isCreateMode = false;
            this.companyId = companyId;
            this.formError = null;
            this.isSubmitting = false;

            if (!this.bootstrapModal) {
                this.setupModal();
            }

            try {
                const url = this.route('admin.companies.show', { company: companyId });
                const { data } = await axios.get(url);
                const company = data.data;

                this.form = this.emptyForm();
                this.form.name = company.name || '';
                this.form.short_code = company.short_code || '';

                this.shortCodeStatus = null;
                this.shortCodeMessage = null;
            } catch (e) {
                console.error(e);
                this.formError = 'No se pudieron cargar los datos de la empresa.';
            }

            this.bootstrapModal.show();
        },

        close() {
            if (this.bootstrapModal) {
                this.bootstrapModal.hide();
            }
        },

        async submit() {
            this.isSubmitting = true;
            this.formError = null;

            try {
                if (this.isCreateMode) {
                    await this.submitCreate();
                } else {
                    await this.submitUpdate();
                }
            } catch (e) {
                console.error(e);
                this.formError = 'Ha ocurrido un error al guardar la empresa.';
            } finally {
                this.isSubmitting = false;
            }
        },

        async submitCreate() {
            try {
                const url = this.route('admin.companies.store');

                const payload = {
                    name: this.form.name,
                    short_code: this.form.short_code,
                };

                const { data } = await axios.post(url, payload);

                this.$emit('created', data.data);
                this.close();
            } catch (error) {
                if (error.response && error.response.data && error.response.data.message) {
                    this.formError = error.response.data.message;
                } else {
                    this.formError = 'No se pudo crear la empresa.';
                }
            }
        },

        async submitUpdate() {
            if (!this.companyId) {
                return;
            }

            try {
                const url = this.route('admin.companies.update', { company: this.companyId });

                const payload = {
                    name: this.form.name,
                    short_code: this.form.short_code,
                };

                const { data } = await axios.put(url, payload);

                this.$emit('updated', data.data);
                this.close();
            } catch (error) {
                if (error.response && error.response.data && error.response.data.message) {
                    this.formError = error.response.data.message;
                } else {
                    this.formError = 'No se pudo actualizar la empresa.';
                }
            }
        },

        // ---- Check de código único con debounce ----

        handleShortCodeChange(value) {
            this.shortCodeStatus = null;
            this.shortCodeMessage = null;

            if (this.shortCodeTimeoutId) {
                clearTimeout(this.shortCodeTimeoutId);
            }

            const trimmed = (value || '').trim();
            if (trimmed.length < 3) {
                if (trimmed.length > 0) {
                    this.shortCodeMessage = 'El código debe tener al menos 3 caracteres.';
                } else {
                    this.shortCodeMessage = null;
                }
                return;
            }

            this.shortCodeChecking = true;

            this.shortCodeTimeoutId = setTimeout(() => {
                this.checkShortCodeAvailability(trimmed);
            }, this.autosaveDelayMs);
        },

        async checkShortCodeAvailability(value) {
            try {
                const params = {
                    short_code: value,
                };

                if (!this.isCreateMode && this.companyId) {
                    params.ignore_id = this.companyId;
                }

                const url = this.route('admin.companies.check-short-code', params);
                const { data } = await axios.get(url);

                if (data.is_available) {
                    this.shortCodeStatus = 'available';
                    this.shortCodeMessage = null;
                } else if (data.reason === 'too_short') {
                    this.shortCodeStatus = null;
                    this.shortCodeMessage = 'El código es demasiado corto.';
                } else {
                    this.shortCodeStatus = 'taken';
                    this.shortCodeMessage = 'El código ya está en uso.';
                }
            } catch (e) {
                console.error(e);
                this.shortCodeStatus = null;
                this.shortCodeMessage = 'No se pudo verificar el código.';
            } finally {
                this.shortCodeChecking = false;
            }
        },
    },
};
</script>
