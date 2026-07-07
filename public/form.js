document.addEventListener('DOMContentLoaded', function () {
    const tipoField = document.querySelector('select[name="tipo"]');
    const usoField = document.getElementById('uso');
    const funcionarioBlock = document.getElementById('field-funcionario');
    const itemConditionBlock = document.getElementById('field-item-condition');
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

    const NO_SETOR_VALUE = 'Sem setor disponível';

    function setHiddenLocalidadePath() {
        const secretaria = localidadeSecretaria?.value || '';
        const divisao = localidadeDivisao?.value || '';
        let setor = localidadeSetor?.value || '';

        if (secretaria && divisao && !setor) {
            setor = NO_SETOR_VALUE;
        }

        const parts = [secretaria, divisao, setor].filter(Boolean);
        if (localidadePathInput) {
            localidadePathInput.value = parts.join(' > ');
        }
    }

    function createOption(value, label) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        option.title = label;
        return option;
    }

    function updateSelectTitle(select) {
        if (!select) {
            return;
        }
        const selectedOption = select.options[select.selectedIndex];
        select.title = selectedOption ? selectedOption.text : '';
    }

    function setSelectOptions(select, options, placeholder) {
        select.innerHTML = '';
        select.appendChild(createOption('', placeholder));
        options.forEach(function (option) {
            select.appendChild(createOption(option.value, option.label));
        });
        updateSelectTitle(select);
    }

    function fillSetorOptions() {
        const secretariaIndex = localidadeSecretaria?.selectedIndex - 1;
        const divisaoIndex = localidadeDivisao?.selectedIndex - 1;

        if (typeof secretariaIndex !== 'number' || secretariaIndex < 0 || typeof divisaoIndex !== 'number' || divisaoIndex < 0) {
            localidadeSetor.disabled = true;
            setSelectOptions(localidadeSetor, [], 'Selecione o setor');
            setHiddenLocalidadePath();
            return;
        }

        const secretariaData = localidadeHierarchy[secretariaIndex];
        const divisaoData = secretariaData?.divisoes[divisaoIndex];
        const setores = divisaoData?.setores || [];

        if (setores.length === 0) {
            localidadeSetor.disabled = true;
            localidadeSetor.appendChild(createOption(NO_SETOR_VALUE, NO_SETOR_VALUE));
            localidadeSetor.value = NO_SETOR_VALUE;
            localidadePathInput.value = `${secretariaData.secretaria} > ${divisaoData.name} > ${NO_SETOR_VALUE}`;
            return;
        }

        localidadeSetor.disabled = false;
        setSelectOptions(localidadeSetor, setores.map(function (setor) {
            return { value: setor, label: setor };
        }), 'Selecione o setor');
        setHiddenLocalidadePath();
    }

    function fillDivisaoOptions() {
        const secretariaIndex = localidadeSecretaria?.selectedIndex - 1;

        if (typeof secretariaIndex !== 'number' || secretariaIndex < 0) {
            localidadeDivisao.disabled = true;
            setSelectOptions(localidadeDivisao, [], 'Selecione a divisão');
            localidadeSetor.disabled = true;
            setSelectOptions(localidadeSetor, [], 'Selecione o setor');
            setHiddenLocalidadePath();
            return;
        }

        const secretariaData = localidadeHierarchy[secretariaIndex];
        const divisaoOptions = (secretariaData?.divisoes || []).map(function (divisao) {
            return { value: divisao.name, label: divisao.name };
        });

        localidadeDivisao.disabled = false;
        setSelectOptions(localidadeDivisao, divisaoOptions, 'Selecione a divisão');
        localidadeSetor.disabled = true;
        setSelectOptions(localidadeSetor, [], 'Selecione o setor');
        setHiddenLocalidadePath();
    }

    function populateSecretarias() {
        if (!localidadeSecretaria) {
            return;
        }

        const secretariaOptions = localidadeHierarchy.map(function (secretaria) {
            return { value: secretaria.secretaria, label: secretaria.secretaria };
        });
        setSelectOptions(localidadeSecretaria, secretariaOptions, 'Selecione a secretaria');
    }

    function updateFormFields() {
        const tipoValue = normalizeText(tipoField?.value);
        const usoValue = normalizeText(usoField?.value);
        const isEmprestimo = tipoValue === 'saida' && usoValue === 'emprestimo';
        const showItemCondition = usoValue === 'emprestimo';

        if (funcionarioBlock) {
            funcionarioBlock.classList.toggle('hidden', !isEmprestimo);
            funcionarioBlock.style.display = isEmprestimo ? 'block' : 'none';
        }
        if (itemConditionBlock) {
            itemConditionBlock.classList.toggle('hidden', !showItemCondition);
            itemConditionBlock.style.display = showItemCondition ? 'block' : 'none';
            const conditionSelect = itemConditionBlock.querySelector('select[name="condicao_item"]');
            if (conditionSelect) {
                conditionSelect.required = showItemCondition;
                if (!showItemCondition) {
                    conditionSelect.value = '';
                }
            }
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
            updateSelectTitle(localidadeSecretaria);
            setHiddenLocalidadePath();
        });
    }

    if (localidadeDivisao) {
        localidadeDivisao.addEventListener('change', function () {
            fillSetorOptions();
            updateSelectTitle(localidadeDivisao);
        });
    }

    if (localidadeSetor) {
        localidadeSetor.addEventListener('change', function () {
            setHiddenLocalidadePath();
            updateSelectTitle(localidadeSetor);
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
    const saveButton = document.querySelector('.btn-salvar');
    const undoSubmitWrapper = document.getElementById('submit-undo-wrapper');
    const undoButton = document.getElementById('undo-button');
    const undoTimerSpan = document.getElementById('undo-timer');
    const saldoDisponivelText = document.getElementById('saldo-disponivel');
    const comprimentoFieldWrapper = document.getElementById('field-comprimento');
    const quantidadeErro = document.getElementById('quantidade-erro');
    const UNDO_COUNTDOWN_SECONDS = 5;
    let signaturePad = null;
    let undoTimeout = null;
    let undoInterval = null;

    function resetUndoInterface() {
        if (saveButton) {
            saveButton.style.display = 'block';
        }
        if (undoSubmitWrapper) {
            undoSubmitWrapper.style.display = 'none';
        }
        if (undoTimerSpan) {
            undoTimerSpan.textContent = String(UNDO_COUNTDOWN_SECONDS);
        }
        if (quantidadeErro) {
            quantidadeErro.style.display = 'none';
        }
        if (undoTimeout) {
            clearTimeout(undoTimeout);
            undoTimeout = null;
        }
        if (undoInterval) {
            clearInterval(undoInterval);
            undoInterval = null;
        }
    }

    function hideStockMessages() {
        if (saldoDisponivelText) {
            saldoDisponivelText.style.display = 'none';
        }
        if (quantidadeErro) {
            quantidadeErro.style.display = 'none';
        }
    }

    function submitMovementForm() {
        if (signaturePad && assinaturaInput) {
            assinaturaInput.value = signaturePad.toDataURL('image/png');
        }
        if (movementForm) {
            movementForm.submit();
        }
    }

    function startUndoCountdown() {
        let remainingSeconds = UNDO_COUNTDOWN_SECONDS;
        if (!saveButton || !undoSubmitWrapper || !undoTimerSpan) {
            submitMovementForm();
            return;
        }

        saveButton.style.display = 'none';
        undoSubmitWrapper.style.display = 'flex';
        undoSubmitWrapper.style.alignItems = 'center';
        undoTimerSpan.textContent = String(remainingSeconds);

        undoInterval = window.setInterval(function () {
            remainingSeconds -= 1;
            if (remainingSeconds <= 0) {
                clearInterval(undoInterval);
                undoInterval = null;
                if (undoTimerSpan) {
                    undoTimerSpan.textContent = '0';
                }
                submitMovementForm();
                return;
            }
            if (undoTimerSpan) {
                undoTimerSpan.textContent = String(remainingSeconds);
            }
        }, 1000);

        undoTimeout = window.setTimeout(function () {
            if (undoInterval) {
                clearInterval(undoInterval);
                undoInterval = null;
            }
            undoTimeout = null;
            submitMovementForm();
        }, UNDO_COUNTDOWN_SECONDS * 1000);
    }

    function handleUndoClick() {
        resetUndoInterface();
    }

    function updateSaldoDisponivel() {
        if (!itemSelect || !saldoDisponivelText) {
            return;
        }

        const selectedOption = itemSelect.selectedOptions[0];
        if (!selectedOption || !itemSelect.value) {
            saldoDisponivelText.style.display = 'none';
            if (comprimentoFieldWrapper) {
                comprimentoFieldWrapper.style.display = 'none';
            }
            return;
        }

        const saldo = Number(selectedOption.dataset.stock || 0);
        const unidade = String(selectedOption.dataset.unidade || 'un').toLowerCase();
        const requerComprimentoValue = selectedOption.dataset.requerComprimento ?? selectedOption.dataset.requer_comprimento;
        const explicitRequer = requerComprimentoValue !== undefined && String(requerComprimentoValue) === '1';
        const requerComprimento = explicitRequer || unidade === 'm';

        saldoDisponivelText.textContent = `Disponível: ${saldo} ${unidade}`;
        saldoDisponivelText.style.display = 'block';

        if (comprimentoFieldWrapper) {
            comprimentoFieldWrapper.style.display = requerComprimento ? 'block' : 'none';
            if (!requerComprimento) {
                const comprimentoInput = comprimentoFieldWrapper.querySelector('input[name="comprimento"]');
                if (comprimentoInput) {
                    comprimentoInput.value = '';
                }
            }
        }
    }

    function showQuantidadeErro(message) {
        if (!quantidadeErro) {
            return;
        }
        quantidadeErro.textContent = message;
        quantidadeErro.style.display = 'block';
    }

    function validateQuantityAgainstStock(event) {
        if (!quantityInput || !itemSelect) {
            return false;
        }

        const tipoValue = normalizeText(tipoField?.value || '');
        const quantity = Number(quantityInput.value);
        const stock = getSelectedItemStock();

        if (tipoValue !== 'saida') {
            if (quantidadeErro) {
                quantidadeErro.style.display = 'none';
            }
            return false;
        }

        if (quantity > stock) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            showQuantidadeErro('Quantidade superior ao saldo disponível!');
            return true;
        }

        if (quantidadeErro) {
            quantidadeErro.style.display = 'none';
        }
        return false;
    }

    function confirmHighQuantity(event) {
        if (validateQuantityAgainstStock(event)) {
            return true;
        }

        if (!quantityInput || !itemSelect) {
            return false;
        }

        const tipoValue = normalizeText(tipoField?.value || '');
        const quantity = Number(quantityInput.value);
        const stock = getSelectedItemStock();
        if (!quantity || quantity < 1 || stock <= 0 || tipoValue !== 'saida') {
            return false;
        }

        if (quantity >= stock * 0.5) {
            const percent = Math.round((quantity / stock) * 100);
            if (!window.confirm(`Tem certeza? Essa saída representa ${percent}% do saldo atual (${stock}).`)) {
                event.preventDefault();
                return true;
            }
        }
        return false;
    }

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
    }

    if (undoButton) {
        undoButton.addEventListener('click', function () {
            handleUndoClick();
        });
    }

    if (movementForm) {
        movementForm.addEventListener('submit', function (event) {
            if (undoTimeout) {
                event.preventDefault();
                return;
            }

            if (signaturePad && signaturePad.isEmpty()) {
                alert('Assinatura é obrigatória.');
                event.preventDefault();
                return;
            }

            if (confirmHighQuantity(event)) {
                return;
            }

            event.preventDefault();
            resetUndoInterface();
            startUndoCountdown();
        });
    }

    const itemSelect = document.getElementById('item_search');
    const quantityInput = document.querySelector('input[name="quantidade"]');
    let itemTomSelect = null;

    if (itemSelect) {
        itemSelect.addEventListener('change', function () {
            updateSaldoDisponivel();
            if (quantidadeErro) {
                quantidadeErro.style.display = 'none';
            }
        });
    }

    if (quantityInput) {
        quantityInput.addEventListener('input', function () {
            validateQuantityAgainstStock();
        });
    }

    if (tipoField) {
        tipoField.addEventListener('change', function () {
            validateQuantityAgainstStock();
        });
    }

    updateSaldoDisponivel();

    function getSelectedItemStock() {
        if (!itemSelect || !itemSelect.value) {
            return 0;
        }
        const selectedOption = itemSelect.selectedOptions[0];
        return selectedOption ? parseFloat(selectedOption.dataset.stock || '0') : 0;
    }

    if (itemSelect && typeof TomSelect !== 'undefined' && Array.isArray(window.ITEMS)) {
            itemTomSelect = new TomSelect(itemSelect, {
                valueField: 'value',
                labelField: 'text',
                searchField: ['text', 'tokens'],
                maxOptions: 8,
                maxItems: 1,
                itemClass: 'ts-single-selected',
                placeholder: 'Pesquisar item...',
                allowEmptyOption: true,
                openOnFocus: false,
                closeAfterSelect: true,
                hideSelected: true,
                create: false,
                options: Array.isArray(window.INITIAL_ITEMS) ? window.INITIAL_ITEMS : [],
                itemClass: 'ts-single-selected',
                render: {
                    option: function(data, escape) {
                        return '<div class="ts-option-item">' + escape(data.text) + '</div>';
                    },
                    item: function(data, escape) {
                        return '<div>' + escape(data.text) + '</div>';
                    }
                },
                onItemAdd: function(value) {
                    if (!itemSelect || !value) {
                        return;
                    }

                    let existingOption = itemSelect.querySelector('option[value="' + value.replace(/"/g, '\\"') + '"]');
                    if (!existingOption) {
                        existingOption = document.createElement('option');
                        existingOption.value = value;
                        existingOption.textContent = (this.options[value] && this.options[value].text) ? this.options[value].text : value;
                        const data = this.options[value] || {};
                        existingOption.dataset.stock = data.stock || '0';
                        existingOption.dataset.unidade = data.unidade || 'un';
                        existingOption.dataset.requerComprimento = String(data.requer_comprimento || data.requerComprimento || 0);
                        itemSelect.appendChild(existingOption);
                    }

                    itemSelect.value = value;
                    existingOption.selected = true;
                    itemSelect.dispatchEvent(new Event('change', { bubbles: true }));

                    setTimeout(() => {
                        if (typeof this.close === 'function') {
                            this.close();
                        }
                        if (this.control_input && typeof this.control_input.blur === 'function') {
                            this.control_input.blur();
                        }
                    }, 0);
                },
                onChange: function(value) {
                    if (itemSelect) {
                        itemSelect.value = value || '';
                        itemSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                },
                load: function(query, callback) {
                    if (!query.length) {
                        return callback();
                    }
                    const search = query.toLowerCase();
                    const filtered = window.ITEMS.filter(function(row) {
                        return row.text.toLowerCase().includes(search) || row.tokens.toLowerCase().includes(search);
                    });
                    callback(filtered.slice(0, 30));
                }
            });
        } else if (itemSelect) {
            console.warn('TomSelect não foi encontrado para o campo de item. Verifique se o script foi carregado.');
        }
    });
