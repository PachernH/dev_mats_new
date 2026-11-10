let currentMasterData = {};
let dropdownMasters = {};
let currentAddTable = '';
const API_URL = 'model/api_price_master.php';

function initPage(period, coil, cust) {
    loadCustomers(cust).then(() => {
        return loadCurrencies();
    }).then(() => {
        return loadDropdownMasters();
    }).then(() => {
        if (period && cust) {
            validateCodeAndFetch();
        }
    });

    const elCode = document.getElementById('txtCode');
    const elCust = document.getElementById('cmbCustomerID');
    const elCoil = document.getElementById('cmbCoilFrom');
    const elEx   = document.getElementById('txtExchangeRate');
    const elLme  = document.getElementById('txtLMEAverage');
    const elRef  = document.getElementById('txtRefundInterest');

    if (elCode) elCode.addEventListener('change', validateCodeAndFetch);
    if (elCust) elCust.addEventListener('change', validateCodeAndFetch);
    if (elCoil) elCoil.addEventListener('change', validateCodeAndFetch);
    if (elEx) elEx.addEventListener('input', runLocalCalculation);
    if (elLme) elLme.addEventListener('input', runLocalCalculation);
    if (elRef) elRef.addEventListener('input', runLocalCalculation);
}

function loadCustomers(selectedCust) {
    return fetch(`${API_URL}?action=get_customers`)
        .then(res => {
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            return res.json();
        })
        .then(res => {
            if (res.status) {
                const cmb = document.getElementById('cmbCustomerID');
                if (!cmb) return;
                cmb.innerHTML = '<option value="">-- Select Customer --</option>';
                res.data.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.CSTMSPPL_ID;
                    opt.innerText = `${c.CSTMSPPL_ID}: ${c.CONSIGNEE_COMPANY}`;
                    
                    if (selectedCust && selectedCust.trim().toUpperCase() === c.CSTMSPPL_ID.trim().toUpperCase()) {
                        opt.selected = true;
                    }
                    cmb.appendChild(opt);
                });
            }
        })
        .catch(err => console.error('Error loading customers:', err));
}

function loadCurrencies() {
    return fetch(`${API_URL}?action=get_currencies`)
        .then(res => {
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            return res.json();
        })
        .then(res => {
            if (res.status) {
                const cmb = document.getElementById('cmbCurrency');
                if (!cmb) return;
                cmb.innerHTML = '';
                res.data.forEach(cur => {
                    const opt = document.createElement('option');
                    opt.value = cur.CURRENCY_ID;
                    opt.innerText = cur.CURRENCY_ID;
                    cmb.appendChild(opt);
                });
            }
        })
        .catch(err => console.error('Error loading currencies:', err));
}

function loadDropdownMasters() {
    return fetch(`${API_URL}?action=get_dropdown_masters`)
        .then(res => res.json())
        .then(res => {
            if (res.status) {
                dropdownMasters = res.data;
            }
        })
        .catch(err => console.error('Error loading dropdown masters:', err));
}

function validateCodeAndFetch() {
    const elCode = document.getElementById('txtCode');
    if (!elCode) return;
    const code = elCode.value.trim();
    if (code.length !== 8 && code.length !== 9) return;

    const year   = parseInt(code.substring(0, 4), 10);
    const month  = parseInt(code.substring(4, 6), 10);
    const hyphen = code.substring(6, 7);
    const suffix = parseInt(code.substring(7, 9), 10);

    if (year < 2006 || month < 1 || month > 12 || hyphen !== '-') {
        return;
    }

    const cmbCoil = document.getElementById('cmbCoilFrom');
    if (cmbCoil) {
        if (suffix >= 1 && suffix <= 9) {
            cmbCoil.value = 'MAT';
        } else if (suffix >= 50 && suffix <= 59) {
            cmbCoil.value = 'MAL';
        }
    }

    const custEl = document.getElementById('cmbCustomerID');
    let custVal = custEl ? custEl.value : '';
    if (!custVal) {
        const urlParams = new URLSearchParams(window.location.search);
        custVal = urlParams.get('cust') || '';
    }
    if (!custVal) return;

    const custId = custVal.includes(':') ? custVal.split(':')[0].trim() : custVal.trim();
    const coilVal = cmbCoil ? cmbCoil.value : 'MAT';

    fetch(`${API_URL}?action=get_main_price&price_period=${encodeURIComponent(code)}&coil_from=${encodeURIComponent(coilVal)}&customer_id=${encodeURIComponent(custId)}`)
        .then(res => res.json())
        .then(res => {
            if (!res.status) return;

            const elRecStatus = document.getElementById('record_status');
            if (elRecStatus) elRecStatus.value = res.record_status;

            const btnDelete = document.getElementById('btnDelete');
            const btnCalc   = document.getElementById('btnCalcOrders');

            if (res.record_status === 'Old Record') {
                if (btnDelete) btnDelete.style.display = 'block';
                if (btnCalc) btnCalc.style.display = 'block';

                const elExRate = document.getElementById('txtExchangeRate');
                const elLmeAvg = document.getElementById('txtLMEAverage');
                const elRefInt = document.getElementById('txtRefundInterest');
                const elCurr   = document.getElementById('cmbCurrency');
                const elLtFrom = document.getElementById('dtpLifetimeFrom');
                const elLtTo   = document.getElementById('dtpLifetimeTo');

                if (elExRate) elExRate.value = parseFloat(res.data.EXCHANGE_RATE || 1).toFixed(5);
                if (elLmeAvg) elLmeAvg.value = parseFloat(res.data.LME_AVERAGE || 0).toFixed(2);
                if (elRefInt) elRefInt.value = parseFloat(res.data.REFUND_INTEREST || 0).toFixed(4);
                if (elCurr && res.data.CURRENCY_ID) elCurr.value = res.data.CURRENCY_ID.trim();
                if (elLtFrom && res.data.LIFETIME_FROM) elLtFrom.value = res.data.LIFETIME_FROM.substring(0, 10);
                if (elLtTo && res.data.LIFETIME_TO) elLtTo.value = res.data.LIFETIME_TO.substring(0, 10);
            } else {
                if (btnDelete) btnDelete.style.display = 'none';
                if (btnCalc) btnCalc.style.display = 'none';
            }

            currentMasterData = res.data || {};
            runLocalCalculation();
        })
        .catch(err => console.error('Fetch error:', err));
}

function runLocalCalculation() {
    const elLme = document.getElementById('txtLMEAverage');
    const elRef = document.getElementById('txtRefundInterest');

    const lme = elLme ? parseFloat(elLme.value) || 0 : 0;
    const ref = elRef ? parseFloat(elRef.value) || 0 : 0;

    const ingPrem = parseFloat(currentMasterData.INGOT_PREMIUM) || 0;
    const ingDuty = parseFloat(currentMasterData.INGOT_IMPORTDUTY) || 0;
    const ingFrgt = parseFloat(currentMasterData.INGOT_FREIGHT) || 0;
    const meltPct = parseFloat(currentMasterData.INGOT_MELTLOSS) || 0;
    const prodPak = parseFloat(currentMasterData.PRODUCT_PACKING) || 0;
    const prodFrt = parseFloat(currentMasterData.PRODUCT_FREIGHT) || 0;
    const fuelSur = parseFloat(currentMasterData.FUEL_SURCHARGE) || 0;

    const baseCir = parseFloat(currentMasterData.BASE_CONVERSIONCIRCLE) || 0;
    const baseSht = parseFloat(currentMasterData.BASE_CONVERSIONSHEET) || 0;
    const baseCol = parseFloat(currentMasterData.BASE_CONVERSIONCOIL) || 0;

    const duty  = ((lme + ingPrem) * ingDuty) / 100.0;
    const melt1 = ((lme + ingPrem + duty + ingFrgt) * meltPct) / 100.0;
    const melt2 = ((lme + ingPrem + ingFrgt) * meltPct) / 100.0;

    const setTxt = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.innerText = val.toFixed(2);
    };

    setTxt('lblCircle1', lme + ingPrem + duty + ingFrgt + melt1 + prodPak + prodFrt + fuelSur + ref + baseCir);
    setTxt('lblCircle2', lme + ingPrem + ingFrgt + melt2 + prodPak + prodFrt + fuelSur + ref + baseCir);
    setTxt('lblSheet1',  lme + ingPrem + duty + ingFrgt + melt1 + prodPak + prodFrt + fuelSur + ref + baseSht);
    setTxt('lblSheet2',  lme + ingPrem + ingFrgt + melt2 + prodPak + prodFrt + fuelSur + ref + baseSht);
    setTxt('lblCoil1',   lme + ingPrem + duty + ingFrgt + melt1 + prodPak + prodFrt + fuelSur + ref + baseCol);
    setTxt('lblCoil2',   lme + ingPrem + ingFrgt + melt2 + prodPak + prodFrt + fuelSur + ref + baseCol);
}

function saveData() {
    const codeEl    = document.getElementById('txtCode');
    const coilEl    = document.getElementById('cmbCoilFrom');
    const custEl    = document.getElementById('cmbCustomerID');
    const dFromEl   = document.getElementById('dtpLifetimeFrom');
    const dToEl     = document.getElementById('dtpLifetimeTo');
    const currEl    = document.getElementById('cmbCurrency');
    const exRateEl  = document.getElementById('txtExchangeRate');
    const lmeEl     = document.getElementById('txtLMEAverage');
    const refIntEl  = document.getElementById('txtRefundInterest');

    const code      = (codeEl ? codeEl.value : '').trim();
    const coil      = (coilEl ? coilEl.value : '').trim();
    const custVal   = (custEl ? custEl.value : '').trim();
    const dFrom     = (dFromEl ? dFromEl.value : '').trim();
    const dTo       = (dToEl ? dToEl.value : '').trim();
    const curr      = (currEl ? currEl.value : '').trim();
    const exRateStr = (exRateEl ? exRateEl.value : '').trim();
    const lmeStr    = (lmeEl ? lmeEl.value : '').trim();
    const refIntStr = (refIntEl ? refIntEl.value : '').trim();

    if (!code) {
        alert('กรุณากรอก Price Period');
        if (codeEl) codeEl.focus();
        return;
    }
    if (code.length !== 8 && code.length !== 9) {
        alert('รูปแบบ Price Period ไม่ถูกต้อง (ต้องมีความยาว 8 หรือ 9 หลัก เช่น 202610-50)');
        if (codeEl) codeEl.focus();
        return;
    }
    const year   = parseInt(code.substring(0, 4), 10);
    const month  = parseInt(code.substring(4, 6), 10);
    const hyphen = code.substring(6, 7);
    const suffix = parseInt(code.substring(7, 9), 10);

    if (isNaN(year) || year < 2006 || isNaN(month) || month < 1 || month > 12 || hyphen !== '-') {
        alert('รูปแบบ Price Period ไม่ถูกต้อง (ปีต้อง >= 2006 และเดือน 01-12 เช่น YYYYMM-DD)');
        if (codeEl) codeEl.focus();
        return;
    }

    if (suffix >= 1 && suffix <= 9) {
        if (coil !== 'MAT') {
            alert('Price Period รหัสลงท้าย 01-09 ต้องเลือก Coil From เป็น "MAT"');
            if (coilEl) coilEl.focus();
            return;
        }
    } else if (suffix >= 50 && suffix <= 59) {
        if (coil !== 'MAL') {
            alert('Price Period รหัสลงท้าย 50-59 ต้องเลือก Coil From เป็น "MAL"');
            if (coilEl) coilEl.focus();
            return;
        }
    } else {
        alert('Price Period รหัสลงท้ายต้องอยู่ระหว่าง 01-09 (MAT) หรือ 50-59 (MAL)');
        if (codeEl) codeEl.focus();
        return;
    }

    if (!custVal) {
        alert('กรุณาเลือกลูกค้า (Customer)');
        if (custEl) custEl.focus();
        return;
    }
    const custId = custVal.includes(':') ? custVal.split(':')[0].trim() : custVal.trim();

    if (!dFrom) {
        alert('กรุณาระบุ Lifetime From');
        if (dFromEl) dFromEl.focus();
        return;
    }
    if (!dTo) {
        alert('กรุณาระบุ Lifetime To');
        if (dToEl) dToEl.focus();
        return;
    }
    if (new Date(dFrom) > new Date(dTo)) {
        alert('วันที่ Lifetime From ต้องไม่มากกว่า Lifetime To');
        if (dFromEl) dFromEl.focus();
        return;
    }

    if (!curr) {
        alert('กรุณาเลือกสกุลเงิน (Currency)');
        if (currEl) currEl.focus();
        return;
    }

    const exRate = parseFloat(exRateStr);
    if (isNaN(exRate) || exRate < 1 || exRate > 150) {
        alert('Exchange Rate ต้องเป็นตัวเลขระหว่าง 1.00000 ถึง 150.00000');
        if (exRateEl) exRateEl.focus();
        return;
    }

    const lme = parseFloat(lmeStr);
    if (isNaN(lme) || lme < 0 || lme > 5000) {
        alert('LME Average ต้องเป็นตัวเลขระหว่าง 0.00 ถึง 5000.00');
        if (lmeEl) lmeEl.focus();
        return;
    }

    const refInt = parseFloat(refIntStr);
    if (isNaN(refInt) || refInt < -50 || refInt > 50) {
        alert('Refund Interest ต้องเป็นตัวเลขระหว่าง -50.0000 ถึง 50.0000');
        if (refIntEl) refIntEl.focus();
        return;
    }

    const payload = {
        record_status: document.getElementById('record_status') ? document.getElementById('record_status').value : 'New Record',
        price_period: code,
        coil_from: coil,
        customer_id: custId,
        lifetime_from: dFrom,
        lifetime_to: dTo,
        currency_id: curr,
        exchange_rate: exRate,
        lme_average: lme,
        refund_interest: refInt
    };

    const btnSave = document.getElementById('btnSave');
    if (btnSave) {
        btnSave.disabled = true;
        btnSave.innerText = 'SAVING...';
    }

    fetch(`${API_URL}?action=save_price`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        alert(res.message);
        if (res.status) {
            const funcVal = document.getElementById('func') ? document.getElementById('func').value : '';
            window.location.assign(`price_period_detail.php?func=${encodeURIComponent(funcVal)}&mode=view&period=${encodeURIComponent(code)}&coil=${encodeURIComponent(coil)}&cust=${encodeURIComponent(custId)}`);
        } else if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerText = 'SAVE CHANGES';
        }
    })
    .catch(err => {
        alert('Save failed: ' + err);
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerText = 'SAVE CHANGES';
        }
    });
}

function deleteData() {
    if (!confirm('Do you commit data delete?')) return;
    const delAll  = confirm('Do you want to delete all premium data of this month and customer?');
    const custVal = document.getElementById('cmbCustomerID') ? document.getElementById('cmbCustomerID').value : '';
    const custId  = custVal.includes(':') ? custVal.split(':')[0].trim() : custVal.trim();

    fetch(`${API_URL}?action=delete_price`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            price_period: document.getElementById('txtCode') ? document.getElementById('txtCode').value.trim() : '',
            coil_from: document.getElementById('cmbCoilFrom') ? document.getElementById('cmbCoilFrom').value : '',
            customer_id: custId,
            delete_all_premiums: delAll
        })
    })
    .then(res => res.json())
    .then(res => {
        alert(res.message);
        if (res.status) window.location.assign('price_period_list.php');
    })
    .catch(err => alert('Delete failed: ' + err));
}

function calcOrders() {
    const custVal = document.getElementById('cmbCustomerID') ? document.getElementById('cmbCustomerID').value : '';
    const custId  = custVal.includes(':') ? custVal.split(':')[0].trim() : custVal.trim();

    const payload = {
        price_period: document.getElementById('txtCode') ? document.getElementById('txtCode').value.trim() : '',
        coil_from: document.getElementById('cmbCoilFrom') ? document.getElementById('cmbCoilFrom').value : '',
        customer_id: custId,
        lifetime_from: document.getElementById('dtpLifetimeFrom') ? document.getElementById('dtpLifetimeFrom').value : '',
        lifetime_to: document.getElementById('dtpLifetimeTo') ? document.getElementById('dtpLifetimeTo').value : ''
    };

    if (!confirm('Start calculating price for matching Sale Orders?')) return;

    fetch(`${API_URL}?action=calculate_price`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        if (res.status) {
            alert(`Calculation finished! Total orders: ${res.total}, Processed items: ${res.processed}`);
        } else {
            alert(`Error: ${res.message}`);
        }
    })
    .catch(err => alert('Calculation failed: ' + err));
}

// -------------------------------------------------------------
// Modal Add Row & Delete Row Functions
// -------------------------------------------------------------
function openAddModal(tbl) {
    currentAddTable = tbl;
    const body = document.getElementById('modalAddBody');
    if (!body) return;
    let html = '';

    const buildOptions = (arr) => (arr || []).map(v => `<option value="${v}">${v}</option>`).join('');

    switch (tbl) {
        case 'PRICMSTR2':
            document.getElementById('modalAddTitle').innerText = 'Add Alloy Premium';
            html = `
                <div class="form-group"><label>Alloy</label><select id="m_alloy" class="form-control">${buildOptions(dropdownMasters.alloys)}</select></div>
                <div class="form-group"><label>Premium (USD/Ton)</label><input type="number" step="0.01" id="m_premium" class="form-control" value="0.00" required/></div>`;
            break;
        case 'PRICMSTR3':
            document.getElementById('modalAddTitle').innerText = 'Add Temper Premium';
            html = `
                <div class="form-group"><label>Alloy</label><select id="m_alloy" class="form-control">${buildOptions(dropdownMasters.alloys)}</select></div>
                <div class="form-group"><label>Temper</label><select id="m_temper" class="form-control">${buildOptions(dropdownMasters.tempers)}</select></div>
                <div class="form-group"><label>Premium (USD/Ton)</label><input type="number" step="0.01" id="m_premium" class="form-control" value="0.00" required/></div>`;
            break;
        case 'PRICMSTR4':
            document.getElementById('modalAddTitle').innerText = 'Add Surface Premium';
            html = `
                <div class="form-group"><label>Alloy</label><select id="m_alloy" class="form-control">${buildOptions(dropdownMasters.alloys)}</select></div>
                <div class="form-group"><label>Surface Grade</label><select id="m_surface" class="form-control">${buildOptions(dropdownMasters.surfaces)}</select></div>
                <div class="form-group"><label>Premium (USD/Ton)</label><input type="number" step="0.01" id="m_premium" class="form-control" value="0.00" required/></div>`;
            break;
        case 'PRICMSTR5':
            document.getElementById('modalAddTitle').innerText = 'Add Metallurgical Premium';
            html = `
                <div class="form-group"><label>Metallurgical Grade</label><select id="m_meta" class="form-control">${buildOptions(dropdownMasters.metallurgicals)}</select></div>
                <div class="form-group"><label>Premium (USD/Ton)</label><input type="number" step="0.01" id="m_premium" class="form-control" value="0.00" required/></div>`;
            break;
        case 'PRICMSTR6':
            document.getElementById('modalAddTitle').innerText = 'Add Thickness Range';
            html = `
                <div class="form-group"><label>Thickness From (mm)</label><input type="number" step="0.001" id="m_thk_from" class="form-control" value="0.001" required/></div>
                <div class="form-group"><label>Thickness To (mm)</label><input type="number" step="0.001" id="m_thk_to" class="form-control" value="0.001" required/></div>
                <div class="form-group"><label>Premium (USD/Ton)</label><input type="number" step="0.01" id="m_premium" class="form-control" value="0.00" required/></div>`;
            break;
        case 'PRICMSTR7':
            document.getElementById('modalAddTitle').innerText = 'Add Diameter Range';
            html = `
                <div class="form-group"><label>Product</label><select id="m_pid" class="form-control">${buildOptions(dropdownMasters.products)}</select></div>
                <div class="form-group"><label>Diameter From (mm)</label><input type="number" step="0.001" id="m_dia_from" class="form-control" value="1.000" required/></div>
                <div class="form-group"><label>Diameter To (mm)</label><input type="number" step="0.001" id="m_dia_to" class="form-control" value="1.000" required/></div>
                <div class="form-group"><label>Premium (USD/Ton)</label><input type="number" step="0.01" id="m_premium" class="form-control" value="0.00" required/></div>`;
            break;
        case 'PRICMSTR8':
            document.getElementById('modalAddTitle').innerText = 'Add Length Range';
            html = `
                <div class="form-group"><label>Length From (mm)</label><input type="number" step="0.001" id="m_len_from" class="form-control" value="1.000" required/></div>
                <div class="form-group"><label>Length To (mm)</label><input type="number" step="0.001" id="m_len_to" class="form-control" value="1.000" required/></div>
                <div class="form-group"><label>Premium (USD/Ton)</label><input type="number" step="0.01" id="m_premium" class="form-control" value="0.00" required/></div>`;
            break;
        case 'PRICMSTR9':
            document.getElementById('modalAddTitle').innerText = 'Add Quantity Range';
            html = `
                <div class="form-group"><label>Quantity From (kg)</label><input type="number" id="m_qty_from" class="form-control" value="1" required/></div>
                <div class="form-group"><label>Quantity To (kg)</label><input type="number" id="m_qty_to" class="form-control" value="1" required/></div>
                <div class="form-group"><label>Premium (USD/Ton)</label><input type="number" step="0.01" id="m_premium" class="form-control" value="0.00" required/></div>`;
            break;
        case 'PRICMSTR10':
            document.getElementById('modalAddTitle').innerText = 'Add Tolerance Range';
            html = `
                <div class="form-group"><label>Tolerance From (%)</label><input type="number" id="m_tol_from" class="form-control" value="0" required/></div>
                <div class="form-group"><label>Tolerance To (%)</label><input type="number" id="m_tol_to" class="form-control" value="0" required/></div>
                <div class="form-group"><label>Premium (USD/Ton)</label><input type="number" step="1" id="m_premium" class="form-control" value="0" required/></div>`;
            break;
        case 'PRICMSTR11':
            document.getElementById('modalAddTitle').innerText = 'Add Full Coil';
            html = `
                <div class="form-group"><label>Full Coil Option</label><select id="m_fullcoil" class="form-control"><option value="F">F - Full Coil</option><option value="N">N - Non Full Coil</option></select></div>
                <div class="form-group"><label>Premium (USD/Ton)</label><input type="number" step="0.01" id="m_premium" class="form-control" value="0.00" required/></div>`;
            break;
    }

    body.innerHTML = html;
    $('#addPremiumModal').appendTo("body").modal('show');
}

function submitAddPremiumRow() {
    const btnSubmit = document.querySelector('#addPremiumModal .btn-primary');
    if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.innerText = 'Adding...';
    }

    let custId = '';
    const custEl = document.getElementById('cmbCustomerID');
    if (custEl && custEl.value) {
        custId = custEl.value.includes(':') ? custEl.value.split(':')[0].trim() : custEl.value.trim();
    }
    if (!custId) {
        const urlParams = new URLSearchParams(window.location.search);
        custId = (urlParams.get('cust') || '').trim();
    }

    const periodEl = document.getElementById('txtCode');
    const coilEl   = document.getElementById('cmbCoilFrom');

    let period = periodEl ? periodEl.value.trim() : '';
    let coil   = coilEl ? coilEl.value.trim() : '';

    if (!period || !coil) {
        const urlParams = new URLSearchParams(window.location.search);
        if (!period) period = (urlParams.get('period') || '').trim();
        if (!coil) coil = (urlParams.get('coil') || '').trim();
    }

    if (!period || !coil || !custId) {
        alert('กรุณากรอก Price Period, Coil From และเลือกลูกค้าให้ครบถ้วนก่อนเพิ่มรายการ');
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.innerText = 'Add Record';
        }
        return;
    }

    const premiumInput = document.getElementById('m_premium');
    const data = {
        price_period: period,
        coil_from: coil,
        customer_id: custId,
        premium: premiumInput ? parseFloat(premiumInput.value) || 0 : 0
    };

    if (document.getElementById('m_alloy')) data.alloy = document.getElementById('m_alloy').value.trim();
    if (document.getElementById('m_temper')) data.temper = document.getElementById('m_temper').value.trim();
    if (document.getElementById('m_surface')) data.surface_grade = document.getElementById('m_surface').value.trim();
    if (document.getElementById('m_meta')) data.metallurgical_grade = document.getElementById('m_meta').value.trim();
    if (document.getElementById('m_thk_from')) data.thickness_from = document.getElementById('m_thk_from').value.trim();
    if (document.getElementById('m_thk_to')) data.thickness_to = document.getElementById('m_thk_to').value.trim();
    if (document.getElementById('m_pid')) data.product_id = document.getElementById('m_pid').value.trim();
    if (document.getElementById('m_dia_from')) data.diameter_from = document.getElementById('m_dia_from').value.trim();
    if (document.getElementById('m_dia_to')) data.diameter_to = document.getElementById('m_dia_to').value.trim();
    if (document.getElementById('m_len_from')) data.length_from = document.getElementById('m_len_from').value.trim();
    if (document.getElementById('m_len_to')) data.length_to = document.getElementById('m_len_to').value.trim();
    if (document.getElementById('m_qty_from')) data.quantity_from = document.getElementById('m_qty_from').value.trim();
    if (document.getElementById('m_qty_to')) data.quantity_to = document.getElementById('m_qty_to').value.trim();
    if (document.getElementById('m_tol_from')) data.tolerance_from = document.getElementById('m_tol_from').value.trim();
    if (document.getElementById('m_tol_to')) data.tolerance_to = document.getElementById('m_tol_to').value.trim();
    if (document.getElementById('m_fullcoil')) data.full_coil = document.getElementById('m_fullcoil').value.trim();

    fetch(`${API_URL}?action=add_premium_row`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ table: currentAddTable, data: data })
    })
    .then(async res => {
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Server response: ' + text);
        }
    })
    .then(res => {
        if (res.status) {
            $('#addPremiumModal').modal('hide');
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css('padding-right', '');
            window.location.reload();
        } else {
            alert('ไม่สามารถเพิ่มข้อมูลได้: ' + res.message);
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerText = 'Add Record';
            }
        }
    })
    .catch(err => {
        alert('เกิดข้อผิดพลาดในการส่งข้อมูล: ' + err.message);
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.innerText = 'Add Record';
        }
    });
}

function deletePremiumRow(tbl, keys) {
    if (!confirm('Are you sure you want to delete this row?')) return;

    let custId = '';
    const custEl = document.getElementById('cmbCustomerID');
    if (custEl && custEl.value) {
        custId = custEl.value.includes(':') ? custEl.value.split(':')[0].trim() : custEl.value.trim();
    }
    if (!custId) {
        const urlParams = new URLSearchParams(window.location.search);
        custId = (urlParams.get('cust') || '').trim();
    }

    const periodEl = document.getElementById('txtCode');
    const coilEl   = document.getElementById('cmbCoilFrom');

    let period = periodEl ? periodEl.value.trim() : '';
    let coil   = coilEl ? coilEl.value.trim() : '';

    if (!period || !coil) {
        const urlParams = new URLSearchParams(window.location.search);
        if (!period) period = (urlParams.get('period') || '').trim();
        if (!coil) coil = (urlParams.get('coil') || '').trim();
    }

    keys.price_period = period;
    keys.coil_from    = coil;
    keys.customer_id  = custId;

    fetch(`${API_URL}?action=delete_premium_row`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ table: tbl, keys: keys })
    })
    .then(res => res.json())
    .then(res => {
        if (res.status) {
            window.location.reload();
        } else {
            alert('Delete failed: ' + res.message);
        }
    })
    .catch(err => alert('Delete error: ' + err));
}

// ผูกฟังก์ชันเข้า window เพื่อให้เรียกผ่าน onclick ใน HTML ได้ตลอดเวลา
window.openAddModal = openAddModal;
window.submitAddPremiumRow = submitAddPremiumRow;
window.deletePremiumRow = deletePremiumRow;
window.saveData = saveData;
window.deleteData = deleteData;
window.calcOrders = calcOrders;