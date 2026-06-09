document.addEventListener('DOMContentLoaded', function () {
    const tipoField = document.querySelector('select[name="tipo"]');
    const usoField = document.getElementById('uso');
    const funcionarioBlock = document.getElementById('field-funcionario');
    const localidadeBlock = document.getElementById('field-localidade');
    const observacaoBlock = document.getElementById('field-observacao');

    const localidadeSecretaria = document.getElementById('localidade_secretaria');
    const localidadeDivisao = document.getElementById('localidade_divisao');
    const localidadeSetor = document.getElementById('localidade_setor');
    const localidadePathInput = document.getElementById('localidade_path');
    const localidadeHierarchy = window.LOCALIDADE_HIERARCHY || [];

    function normalizeText(value) {
        return String(value || '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
    }

    function setHiddenLocalidadePath() {
        const secretaria = localidadeSecretaria?.value || '';
        const divisao = localidadeDivisao?.value || '';
        const setor = localidadeSetor?.value || '';
        const parts = [secretaria, divisao, setor].filter(Boolean);
        if (localidadePathInput) {
            localidadePathInput.value = parts.join(' > ');
        }
    }

    function fillSetorOptions() {
        const secretariaIndex = localidadeSecretaria?.selectedIndex - 1;
        const divisaoIndex = localidadeDivisao?.selectedIndex - 1;

        if (typeof secretariaIndex !== 'number' || secretariaIndex < 0 || typeof divisaoIndex !== 'number' || divisaoIndex < 0) {
            localidadeSetor.disabled = true;
            localidadeSetor.innerHTML = '<option value="">Selecione o setor</option>';
            setHiddenLocalidadePath();
            return;
        }

        const secretariaData = localidadeHierarchy[secretariaIndex];
        const divisaoData = secretariaData?.divisoes[divisaoIndex];
        const setores = divisaoData?.setores || [];

        if (setores.length === 0) {
            localidadeSetor.disabled = false;
            localidadeSetor.innerHTML = '<option value="">Sem setor disponível</option>';
            localidadePathInput.value = `${secretariaData.secretaria} > ${divisaoData.name}`;
            return;
        }

        localidadeSetor.disabled = false;
        localidadeSetor.innerHTML = '<option value="">Selecione o setor</option>' + setores.map(function (setor) {
            return '<option value="' + setor.replace(/"/g, '&quot;') + '">' + setor + '</option>';
        }).join('');
        setHiddenLocalidadePath();
    }

    function fillDivisaoOptions() {
        const secretariaIndex = localidadeSecretaria?.selectedIndex - 1;

        if (typeof secretariaIndex !== 'number' || secretariaIndex < 0) {
            localidadeDivisao.disabled = true;
            localidadeDivisao.innerHTML = '<option value="">Selecione a divisão</option>';
            localidadeSetor.disabled = true;
            localidadeSetor.innerHTML = '<option value="">Selecione o setor</option>';
            setHiddenLocalidadePath();
            return;
        }

        const secretariaData = localidadeHierarchy[secretariaIndex];
        const divisaoOptions = (secretariaData?.divisoes || []).map(function (divisao) {
            return '<option value="' + divisao.name.replace(/"/g, '&quot;') + '">' + divisao.name + '</option>';
        });

        localidadeDivisao.disabled = false;
        localidadeDivisao.innerHTML = '<option value="">Selecione a divisão</option>' + divisaoOptions.join('');
        localidadeSetor.disabled = true;
        localidadeSetor.innerHTML = '<option value="">Selecione o setor</option>';
        setHiddenLocalidadePath();
    }

    function populateSecretarias() {
        if (!localidadeSecretaria) {
            return;
        }

        localidadeSecretaria.innerHTML = '<option value="">Selecione a secretaria</option>' + localidadeHierarchy.map(function (secretaria) {
            return '<option value="' + secretaria.secretaria.replace(/"/g, '&quot;') + '">' + secretaria.secretaria + '</option>';
        }).join('');
    }

    function updateFormFields() {
        const tipoValue = normalizeText(tipoField?.value);
        const usoValue = normalizeText(usoField?.value);
        const isEmprestimo = tipoValue === 'saida' && usoValue === 'emprestimo';

        if (funcionarioBlock) {
            funcionarioBlock.classList.toggle('hidden', !isEmprestimo);
            funcionarioBlock.style.display = isEmprestimo ? 'block' : 'none';
        }
        if (localidadeBlock) {
            localidadeBlock.classList.toggle('hidden', !isEmprestimo);
            localidadeBlock.style.display = isEmprestimo ? 'block' : 'none';
        }
        if (observacaoBlock) {
            observacaoBlock.classList.toggle('hidden', !isEmprestimo);
            observacaoBlock.style.display = isEmprestimo ? 'block' : 'none';
        }

        const funcionarioInput = funcionarioBlock?.querySelector('select');
        const secretariaSelect = localidadeSecretaria;
        const divisaoSelect = localidadeDivisao;
        const setorSelect = localidadeSetor;
        const observacaoInput = observacaoBlock?.querySelector('textarea');

        if (funcionarioInput) {
            funcionarioInput.required = isEmprestimo;
        }
        if (secretariaSelect) {
            secretariaSelect.required = isEmprestimo;
        }
        if (divisaoSelect) {
            divisaoSelect.required = isEmprestimo;
        }
        if (setorSelect) {
            setorSelect.required = isEmprestimo;
        }
        if (observacaoInput) {
            observacaoInput.required = isEmprestimo;
        }
    }

    if (localidadeSecretaria) {
        populateSecretarias();
        localidadeSecretaria.addEventListener('change', function () {
            fillDivisaoOptions();
            setHiddenLocalidadePath();
        });
    }

    if (localidadeDivisao) {
        localidadeDivisao.addEventListener('change', function () {
            fillSetorOptions();
        });
    }

    if (localidadeSetor) {
        localidadeSetor.addEventListener('change', function () {
            setHiddenLocalidadePath();
        });
    }

    if (usoField) {
        usoField.addEventListener('change', updateFormFields);
        usoField.addEventListener('input', updateFormFields);
    }

    if (tipoField) {
        tipoField.addEventListener('change', updateFormFields);
        tipoField.addEventListener('input', updateFormFields);
    }

    updateFormFields();
    setTimeout(updateFormFields, 100);

    const signatureCanvas = document.getElementById('signature-pad');
    const assinaturaInput = document.getElementById('assinatura_data');
    const clearButton = document.getElementById('signature-clear');
    const movementForm = document.querySelector('.movement-form');
    let signaturePad = null;

    if (signatureCanvas && window.SignaturePad) {
        signaturePad = new SignaturePad(signatureCanvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)'
        });

        function resizeSignatureCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const rect = signatureCanvas.getBoundingClientRect();
            signatureCanvas.width = rect.width * ratio;
            signatureCanvas.height = rect.height * ratio;
            signatureCanvas.getContext('2d').scale(ratio, ratio);
            signaturePad.clear();
        }

        window.addEventListener('resize', resizeSignatureCanvas);
        resizeSignatureCanvas();

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                signaturePad.clear();
                if (assinaturaInput) {
                    assinaturaInput.value = '';
                }
            });
        }

        window.signaturePad = signaturePad;

        if (movementForm) {
            movementForm.addEventListener('submit', function (event) {
                if (signaturePad.isEmpty()) {
                    alert('Assinatura é obrigatória.');
                    event.preventDefault();
                    return;
                }
                if (assinaturaInput) {
                    assinaturaInput.value = signaturePad.toDataURL('image/png');
                }
            });
        }
    }
});

function showSignature(src) {
    const win = window.open('', '_blank');
    if (!win) {
        alert('Não foi possível abrir a visualização da assinatura.');
        return;
    }
    win.document.write("<html><head><title>Assinatura</title></head><body style='margin:0; background:#111; display:flex; align-items:center; justify-content:center; height:100vh;'><img src='" + src + "' style='max-width:90vw; max-height:90vh; box-shadow:0 0 20px rgba(0,0,0,0.5); border-radius:8px; background:#fff; padding:12px;'/><button onclick='window.close()' style='position:absolute; top:20px; right:20px; padding:10px 16px; background:#22c55e; color:#fff; border:none; border-radius:8px; cursor:pointer;'>Fechar</button></body></html>");
    win.document.close();
}
