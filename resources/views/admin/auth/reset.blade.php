@extends('admin.public')
@section('title','Restablecer contraseña')

@section('content')

    {{-- Mensajes globales --}}
    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form id="reset-form" method="POST" action="{{ url('/admin/reset-password') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" id="emailHidden" name="email" value="{{ $email }}">

        <div class="mb-10">
            <label class="form-label fs-6 fw-bold text-gray-900">Nueva contraseña</label>
            <input id="password" name="password" type="password" class="form-control form-control-lg form-control-solid" required autocomplete="new-password">
            <div class="form-text" id="pwdHelp">
                La contraseña debe cumplir los requisitos de seguridad.
            </div>
        </div>

        <div class="mb-6">
            <label class="form-label fs-6 fw-bold text-gray-900">Confirmar contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="form-control form-control-lg form-control-solid" required autocomplete="new-password">
            <div class="invalid-feedback" id="confirmFeedback" style="display:none;">
                Las contraseñas no coinciden.
            </div>
        </div>

        {{-- Checklist de requisitos (se llena con la policy del backend) --}}
        <div class="mb-10">
            <div class="fw-bold mb-2">Requisitos:</div>
            <ul class="mb-0" id="policyList" style="list-style: none; padding-left: 0;">
                <li data-check="min">• Debe tener <span data-min>al menos 12</span> caracteres</li>
                <li data-check="uppercase" style="display:none;">• Incluir al menos una mayúscula</li>
                <li data-check="lowercase" style="display:none;">• Incluir al menos una minúscula</li>
                <li data-check="numbers"   style="display:none;">• Incluir al menos un número</li>
                <li data-check="symbols"   style="display:none;">• Incluir al menos un símbolo</li>
                <li data-check="mixed_case" style="display:none;">• Contener mayúsculas y minúsculas</li>
                <li data-check="max" style="display:none;">• No exceder <span data-max>128</span> caracteres</li>
                <li data-check="noPersonal">• No incluir partes de tu correo</li>
            </ul>
        </div>

        <button id="submitBtn" class="btn btn-lg btn-primary w-100 mb-5" type="submit">Actualizar</button>
    </form>

    {{-- Script de validación en vivo contra la política expuesta por el backend --}}
    <script>
        (function() {
            const POLICY_URL = "{{ url('/api/password-policy') }}"; // nombre de ruta opcional: route('api.password.policy')
            const pwd = document.getElementById('password');
            const pwd2 = document.getElementById('password_confirmation');
            const emailHidden = document.getElementById('emailHidden');
            const submitBtn = document.getElementById('submitBtn');
            const confirmFeedback = document.getElementById('confirmFeedback');
            const policyList = document.getElementById('policyList');
            const minSpan = policyList.querySelector('[data-min]');
            const maxSpan = policyList.querySelector('[data-max]');

            let policy = {
                min: 12,
                max: 128,
                require: { uppercase:true, lowercase:true, numbers:true, symbols:true, mixed_case:false },
                messages: {}
            };

            function setVisible(checkKey, visible) {
                const li = policyList.querySelector(`li[data-check="${checkKey}"]`);
                if (li) li.style.display = visible ? '' : 'none';
            }

            function setPassed(checkKey, passed) {
                const li = policyList.querySelector(`li[data-check="${checkKey}"]`);
                if (!li) return;
                li.style.color = passed ? 'var(--bs-success)' : 'inherit';
                li.style.textDecoration = passed ? 'none' : 'none';
                li.classList.toggle('text-success', passed);
            }

            function evaluate() {
                const val = (pwd.value || '');
                const email = (emailHidden.value || '');
                const emailLocal = email.toLowerCase().split('@')[0] || '';

                const checks = {
                    min: val.length >= policy.min,
                    max: policy.max ? (val.length <= policy.max) : true,
                    uppercase: !policy.require.uppercase || /[A-Z]/.test(val),
                    lowercase: !policy.require.lowercase || /[a-z]/.test(val),
                    numbers:   !policy.require.numbers   || /\d/.test(val),
                    symbols:   !policy.require.symbols   || /[^A-Za-z0-9]/.test(val),
                    mixed_case: !policy.require.mixed_case || (/[A-Z]/.test(val) && /[a-z]/.test(val)),
                    noPersonal: emailLocal ? !val.toLowerCase().includes(emailLocal) : true,
                };

                // pintar checks
                Object.keys(checks).forEach(k => setPassed(k, !!checks[k]));

                // confirmar coincidencia
                const match = pwd.value && pwd.value === pwd2.value;
                confirmFeedback.style.display = match ? 'none' : (pwd2.value ? '' : 'none');

                // habilitar botón solo si todo OK
                const allOk = Object.values(checks).every(Boolean) && match;
                submitBtn.disabled = !allOk;
            }

            function applyPolicy(p) {
                policy = p;

                // actualizar textos/visibilidad
                if (minSpan) minSpan.textContent = `al menos ${policy.min}`;
                setVisible('uppercase', !!policy.require.uppercase);
                setVisible('lowercase', !!policy.require.lowercase);
                setVisible('numbers',   !!policy.require.numbers);
                setVisible('symbols',   !!policy.require.symbols);
                setVisible('mixed_case',!!policy.require.mixed_case);

                if (policy.max && Number(policy.max) > 0) {
                    if (maxSpan) maxSpan.textContent = policy.max;
                    setVisible('max', true);
                    pwd.maxLength = Number(policy.max);
                    pwd2.maxLength = Number(policy.max);
                } else {
                    setVisible('max', false);
                }

                pwd.minLength = Number(policy.min) || 1;

                evaluate();
            }

            // eventos
            pwd.addEventListener('input', evaluate);
            pwd2.addEventListener('input', evaluate);

            // cargar policy desde el backend
            fetch(POLICY_URL, { credentials: 'same-origin' })
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(json => applyPolicy(json))
                .catch(() => evaluate()); // si falla, seguimos con defaults

            // evaluación inicial
            evaluate();
        })();
    </script>
@endsection
