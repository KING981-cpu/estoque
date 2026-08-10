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
    const addItemTipoField = document.getElementById('add-item-tipo_registro');
    const addItemConditionSelect = document.getElementById('add-item-condicao');
    const localidadeHierarchy = window.LOCALIDADE_HIERARCHY || [];

    function normalizeText(value) {
        return String(value || '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
    }

    const NO_SETOR_VALUE = '';

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
            setSelectOptions(localidadeSetor, [], 'Selecione o setor');
            setHiddenLocalidadePath();
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

        if (funcionarioBlock) {
            funcionarioBlock.classList.toggle('hidden', !isEmprestimo);
            funcionarioBlock.style.display = isEmprestimo ? 'block' : 'none';
        }
        if (itemConditionBlock) {
            itemConditionBlock.classList.remove('hidden');
            itemConditionBlock.style.display = 'block';
            const conditionSelect = itemConditionBlock.querySelector('select[name="condicao_item"]');
            if (conditionSelect) {
                conditionSelect.required = false;
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

        updateAddItemConditionField();
        updatePatrimonioRequirement();
    }

    function updateAddItemConditionField() {
        const addItemTipo = document.getElementById('add-item-tipo_registro');
        const addItemConditionBlock = document.getElementById('field-add-item-condicao');
        const addItemConditionSelect = document.getElementById('add-item-condicao');
        if (!addItemTipo || !addItemConditionBlock || !addItemConditionSelect) {
            return;
        }

        const isExistente = normalizeText(addItemTipo.value) === '0' || normalizeText(addItemTipo.value) === 'existente';
        addItemConditionBlock.style.display = isExistente ? 'block' : 'none';
        addItemConditionSelect.required = isExistente;
        if (!isExistente) {
            addItemConditionSelect.value = 'novo';
        }
    }

    function updatePatrimonioRequirement() {
        const patrimonioInput = document.querySelector('input[name="patrimonio"]');
        const localItemSelect = document.getElementById('item_search') || (typeof itemSelect !== 'undefined' ? itemSelect : null);
        if (!patrimonioInput || !localItemSelect) return;

        const selectedOption = localItemSelect.selectedOptions ? localItemSelect.selectedOptions[0] : null;
        const tipoItem = selectedOption ? (selectedOption.dataset.tipoItem || selectedOption.getAttribute('data-tipo_item') || '') : '';
        const tipoRegistro = selectedOption ? (selectedOption.dataset.tipoRegistro || selectedOption.getAttribute('data-tipo_registro')) : null;
        const isPermanent = String(tipoItem).toLowerCase() === 'permanente' || String(tipoRegistro) === '1' || Number(tipoRegistro) === 1;
        patrimonioInput.required = isPermanent;
        if (!isPermanent) {
            patrimonioInput.classList.remove('required');
        }
    }

    function updateQuantityConstraint() {
        const quantityInput = document.querySelector('input[name="quantidade"]');
        const localItemSelect = document.getElementById('item_search') || (typeof itemSelect !== 'undefined' ? itemSelect : null);
        if (!quantityInput || !localItemSelect) {
            return;
        }

        const selectedOption = localItemSelect.selectedOptions ? localItemSelect.selectedOptions[0] : null;
        const tipoItem = selectedOption ? (selectedOption.dataset.tipoItem || selectedOption.getAttribute('data-tipo_item') || '') : '';
        const tipoRegistro = selectedOption ? (selectedOption.dataset.tipoRegistro || selectedOption.getAttribute('data-tipo_registro')) : null;
        const isPermanent = String(tipoItem).toLowerCase() === 'permanente' || String(tipoRegistro) === '1' || Number(tipoRegistro) === 1;

        if (isPermanent) {
            quantityInput.setAttribute('max', '1');
            quantityInput.setAttribute('min', '1');
            if (Number(quantityInput.value) !== 1) {
                quantityInput.value = '1';
            }
        } else {
            quantityInput.removeAttribute('max');
            quantityInput.setAttribute('min', '1');
        }
    }

    function validatePermanentQuantity(event) {
        const quantityInput = document.querySelector('input[name="quantidade"]');
        const localItemSelect = document.getElementById('item_search') || (typeof itemSelect !== 'undefined' ? itemSelect : null);
        if (!quantityInput || !localItemSelect) {
            return false;
        }

        const selectedOption = localItemSelect.selectedOptions ? localItemSelect.selectedOptions[0] : null;
        const tipoItem = selectedOption ? (selectedOption.dataset.tipoItem || selectedOption.getAttribute('data-tipo_item') || '') : '';
        const tipoRegistro = selectedOption ? (selectedOption.dataset.tipoRegistro || selectedOption.getAttribute('data-tipo_registro')) : null;
        const isPermanent = String(tipoItem).toLowerCase() === 'permanente' || String(tipoRegistro) === '1' || Number(tipoRegistro) === 1;

        if (isPermanent && Number(quantityInput.value) !== 1) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            showQuantidadeErro('Para itens permanentes, a quantidade deve ser igual a 1.');
            return true;
        }

        if (quantidadeErro) {
            quantidadeErro.style.display = 'none';
        }
        return false;
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

    if (addItemTipoField) {
        addItemTipoField.addEventListener('change', updateAddItemConditionField);
    }

    const itemCategorySelect = document.getElementById('item_category');
    const itemSelect = document.getElementById('item_search');
    const quantityInput = document.querySelector('input[name="quantidade"]');
    let itemTomSelect = null;

    function setItemSelectDisabled(state) {
        if (itemSelect) {
            itemSelect.disabled = state;
        }
        if (itemTomSelect) {
            if (state && typeof itemTomSelect.disable === 'function') {
                itemTomSelect.disable();
            }
            if (!state && typeof itemTomSelect.enable === 'function') {
                itemTomSelect.enable();
            }
        }
    }

    function clearItemOptions() {
        if (!itemSelect) return;
        if (itemTomSelect && typeof itemTomSelect.clearOptions === 'function') {
            itemTomSelect.clearOptions();
            if (typeof itemTomSelect.clear === 'function') {
                itemTomSelect.clear(true);
            }
        }
        itemSelect.innerHTML = '';
        const placeholder = itemCategorySelect && itemCategorySelect.value ? 'Selecione o item' : 'Selecione a categoria primeiro';
        const option = document.createElement('option');
        option.value = '';
        option.disabled = true;
        option.selected = true;
        option.textContent = placeholder;
        itemSelect.appendChild(option);
    }

    function populateItemsByCategory(category) {
        if (!itemSelect || !Array.isArray(window.ITEMS)) return [];
        const filteredItems = window.ITEMS.filter(function (item) {
            return normalizeText(item.category) === normalizeText(category);
        });

        // Update native select placeholder/options when TomSelect is not available
        itemSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.disabled = true;
        placeholder.selected = true;
        placeholder.textContent = filteredItems.length ? 'Selecione o item' : 'Nenhum item disponível nesta categoria';
        itemSelect.appendChild(placeholder);

        if (!itemTomSelect) {
            filteredItems.forEach(function (item) {
                const option = document.createElement('option');
                option.value = String(item.value);
                option.textContent = item.text;
                option.dataset.tokens = item.tokens;
                option.dataset.stock = item.stock;
                option.dataset.tipo_registro = item.tipo_registro;
                option.dataset.tipo_item = item.tipo_item;
                option.dataset.unidade = item.unidade;
                option.dataset.requer_comprimento = item.requer_comprimento;
                option.dataset.condicao = item.condicao;
                itemSelect.appendChild(option);
            });
            return filteredItems;
        }

        // TomSelect path: refresh internal options and return list
        if (typeof itemTomSelect.clearOptions === 'function') {
            itemTomSelect.clearOptions();
        }
        if (filteredItems.length > 0 && typeof itemTomSelect.addOption === 'function') {
            itemTomSelect.addOption(filteredItems);
        }
        if (typeof itemTomSelect.refreshOptions === 'function') {
            itemTomSelect.refreshOptions(false);
        }
        return filteredItems;
    }

    function handleCategoryChange() {
        if (!itemCategorySelect) return;
        const category = itemCategorySelect.value;
        clearItemOptions();
        setItemSelectDisabled(!category);
        if (category) {
            const filtered = populateItemsByCategory(category);
            // If TomSelect is present, reset selection and open dropdown so user sees available items immediately
            if (itemTomSelect && typeof itemTomSelect.setValue === 'function') {
                try {
                    itemTomSelect.setValue('');
                } catch (e) {
                    // ignore
                }
            }
            if (itemTomSelect && filtered && filtered.length > 0 && typeof itemTomSelect.open === 'function') {
                itemTomSelect.open();
            }
        }
    }

    if (itemCategorySelect) {
        clearItemOptions();
        setItemSelectDisabled(true);

        itemCategorySelect.addEventListener('change', function () {
            handleCategoryChange();
            updatePatrimonioRequirement();
        });

        if (itemCategorySelect.value) {
            handleCategoryChange();
        }

        // Ensure TomSelect options stay in sync with category changes.
        if (itemTomSelect && typeof itemTomSelect.refreshOptions === 'function') {
            itemTomSelect.refreshOptions(false);
        }
    }

    updateFormFields();
    updateAddItemConditionField();
    updateQuantityConstraint();
    setTimeout(function () {
        updateFormFields();
        updateAddItemConditionField();
        updateQuantityConstraint();
    }, 100);

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

    if (signatureCanvas) {
        if (window.SignaturePad) {
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
        } else {
            // Fallback simple drawer when SignaturePad lib is not available
            const ctx = signatureCanvas.getContext('2d');
            let drawing = false;
            let lastX = 0;
            let lastY = 0;

            function resizeCanvasFallback() {
                const rect = signatureCanvas.getBoundingClientRect();
                signatureCanvas.width = rect.width;
                signatureCanvas.height = rect.height;
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, signatureCanvas.width, signatureCanvas.height);
                ctx.strokeStyle = '#000000';
                ctx.lineWidth = 2;
                ctx.lineJoin = 'round';
                ctx.lineCap = 'round';
            }

            function pointerDown(e) {
                drawing = true;
                const rect = signatureCanvas.getBoundingClientRect();
                lastX = (e.clientX || e.touches?.[0]?.clientX) - rect.left;
                lastY = (e.clientY || e.touches?.[0]?.clientY) - rect.top;
            }

            function pointerMove(e) {
                if (!drawing) return;
                const rect = signatureCanvas.getBoundingClientRect();
                const x = (e.clientX || e.touches?.[0]?.clientX) - rect.left;
                const y = (e.clientY || e.touches?.[0]?.clientY) - rect.top;
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(x, y);
                ctx.stroke();
                lastX = x;
                lastY = y;
            }

            function pointerUp() {
                drawing = false;
            }

            resizeCanvasFallback();
            window.addEventListener('resize', resizeCanvasFallback);

            signatureCanvas.addEventListener('pointerdown', pointerDown);
            signatureCanvas.addEventListener('pointermove', pointerMove);
            window.addEventListener('pointerup', pointerUp);
            // touch support
            signatureCanvas.addEventListener('touchstart', pointerDown);
            signatureCanvas.addEventListener('touchmove', pointerMove);
            window.addEventListener('touchend', pointerUp);

            const fallbackPad = {
                isEmpty: function () {
                    // simple check: compare to blank white canvas
                    const blank = document.createElement('canvas');
                    blank.width = signatureCanvas.width;
                    blank.height = signatureCanvas.height;
                    const bctx = blank.getContext('2d');
                    bctx.fillStyle = '#ffffff';
                    bctx.fillRect(0, 0, blank.width, blank.height);
                    return signatureCanvas.toDataURL() === blank.toDataURL();
                },
                toDataURL: function () {
                    return signatureCanvas.toDataURL('image/png');
                },
                clear: function () {
                    ctx.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, signatureCanvas.width, signatureCanvas.height);
                }
            };

            if (clearButton) {
                clearButton.addEventListener('click', function () {
                    fallbackPad.clear();
                    if (assinaturaInput) assinaturaInput.value = '';
                });
            }

            signaturePad = fallbackPad;
            window.signaturePad = signaturePad;
        }
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

            if (validatePermanentQuantity(event)) {
                return;
            }

            event.preventDefault();
            resetUndoInterface();
            startUndoCountdown();
        });
    }

    
    if (itemSelect) {
        itemSelect.addEventListener('change', function () {
            updateSaldoDisponivel();
            updatePatrimonioRequirement();
            updateQuantityConstraint();
            if (quantidadeErro) {
                quantidadeErro.style.display = 'none';
            }
        });
    }


    if (quantityInput) {
        quantityInput.addEventListener('input', function () {
            validateQuantityAgainstStock();
            validatePermanentQuantity();
        });
    }

    if (tipoField) {
        tipoField.addEventListener('change', function () {
            validateQuantityAgainstStock();
        });
    }

    updateFormulario();

    function updateFormulario() {
        updateFormFields();
        updateSaldoDisponivel();
    }

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
                openOnFocus: true,
                closeAfterSelect: true,
                hideSelected: true,
                create: false,
                options: [],
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
                    if (!itemSelect || value == null || value === '') {
                        return;
                    }

                    const valueString = String(value);
                    let existingOption = itemSelect.querySelector('option[value="' + valueString.replace(/"/g, '\\"') + '"]');
                    if (!existingOption) {
                        existingOption = document.createElement('option');
                        existingOption.value = valueString;
                        existingOption.textContent = (this.options[valueString] && this.options[valueString].text) ? this.options[valueString].text : valueString;
                        const data = this.options[valueString] || {};
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
                    onFocus: function() {
                    if (itemTomSelect && itemCategorySelect && itemCategorySelect.value) {
                        itemTomSelect.clearOptions();
                        const filtered = window.ITEMS.filter(function(row) {
                            return normalizeText(row.category) === normalizeText(itemCategorySelect.value);
                        });
                        if (typeof itemTomSelect.addOption === 'function') {
                            itemTomSelect.addOption(filtered);
                        }
                        if (typeof itemTomSelect.refreshOptions === 'function') {
                            itemTomSelect.refreshOptions(false);
                        }
                        if (typeof itemTomSelect.open === 'function') {
                            itemTomSelect.open();
                        }
                    }
                },
                load: function(query, callback) {
                    if (!itemCategorySelect || !itemCategorySelect.value) {
                        return callback();
                    }
                    const search = String(query || '').toLowerCase();
                    const filtered = window.ITEMS.filter(function(row) {
                        return normalizeText(row.category) === normalizeText(itemCategorySelect.value)
                            && (!search.length || row.text.toLowerCase().includes(search) || row.tokens.toLowerCase().includes(search));
                    });
                    callback(filtered.slice(0, 30));
                }
            });
        } else if (itemSelect) {
            console.warn('TomSelect não foi encontrado para o campo de item. Verifique se o script foi carregado.');
        }
    });
