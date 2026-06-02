document.addEventListener('DOMContentLoaded', function () {
    const tipoField = document.querySelector('select[name="tipo"]');
    const usoField = document.querySelector('select[name="uso"]');
    const funcionarioBlock = document.getElementById('field-funcionario');
    const localidadeBlock = document.getElementById('field-localidade');
    const observacaoBlock = document.getElementById('field-observacao');

    function updateFormFields() {
        const uso = usoField.value;
        const isEmprestimo = uso === 'Empréstimo';

        funcionarioBlock.classList.toggle('hidden', !isEmprestimo);
        localidadeBlock.classList.toggle('hidden', !isEmprestimo);
        observacaoBlock.classList.toggle('hidden', !isEmprestimo);

        const funcionarioInput = funcionarioBlock.querySelector('select');
        const localidadeInput = localidadeBlock.querySelector('select');
        const observacaoInput = observacaoBlock.querySelector('textarea');

        if (funcionarioInput) {
            funcionarioInput.required = isEmprestimo;
        }
        if (localidadeInput) {
            localidadeInput.required = isEmprestimo;
        }
        if (observacaoInput) {
            observacaoInput.required = isEmprestimo;
        }
    }

    if (tipoField && usoField) {
        tipoField.addEventListener('change', updateFormFields);
        usoField.addEventListener('change', updateFormFields);
        updateFormFields();
    }

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
