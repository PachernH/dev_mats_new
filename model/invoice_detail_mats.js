const FUNC = document.getElementById('func').value;
const INVOICE_NO = document.getElementById('INVOICE_NO').value;
const IS_AUTH = document.getElementById('isAuthorized').value === '1';

let invoiceData = null;
let selectedItemNo = null;
let soListCache = [];
let selectedSOPairs = new Set();
let soSearchTimer = null;
let stockListCache = [];

document.addEventListener('DOMContentLoaded', async () => {
    await loadMasters();
    await loadInvoiceDetail();

    // Financial real-time calculation
    document.querySelectorAll('.fin-input, #INV1_TAXPERCENT').forEach(el => {
        el.addEventListener('input', calculateFinancialSummary);
        el.addEventListener('change', calculateFinancialSummary);
    });

    // Heading Edit/Save/Cancel/Delete buttons
    const btnEdit = document.getElementById('btnEditHead');
    if (btnEdit) {
        btnEdit.addEventListener('click', () => setHeadingEditable(true));
        document.getElementById('btnCancelHead').addEventListener('click', () => {
            setHeadingEditable(false);
            renderInvoiceDetail();
        });
        document.getElementById('btnSaveHead').addEventListener('click', saveHeading);
        document.getElementById('btnDeleteHead').addEventListener('click', deleteHeading);
    }

    document.getElementById('BOOK_NO').addEventListener('change', onBookingChange);

    // Modal 1: Sale Order Lookup Events (Server-Side Search & TOP 200)
    const btnSO = document.getElementById('btnOpenSOModal');
    if (btnSO) btnSO.addEventListener('click', openSOModal);

    document.getElementById('soModalSearch').addEventListener('input', () => {
        clearTimeout(soSearchTimer);
        soSearchTimer = setTimeout(() => fetchSOItemsFromServer(false), 400);
    });

    document.getElementById('soModalSearch').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(soSearchTimer);
            fetchSOItemsFromServer(false);
        }
    });

    document.getElementById('btnSearchSOServer').addEventListener('click', () => {
        clearTimeout(soSearchTimer);
        fetchSOItemsFromServer(false);
    });

    document.getElementById('soModalCategoryFilter').addEventListener('change', () => fetchSOItemsFromServer(false));
    document.getElementById('soModalProdFilter').addEventListener('change', () => fetchSOItemsFromServer(false));
    document.getElementById('chkHideAddedSO').addEventListener('change', renderSOTable);
    document.getElementById('btnResetSOFilter').addEventListener('click', resetSOFilter);

    document.getElementById('chkAllSO').addEventListener('change', (e) => {
        document.querySelectorAll('.chk-so-item:not(:disabled)').forEach(cb => {
            cb.checked = e.target.checked;
            if (e.target.checked) {
                selectedSOPairs.add(cb.value);
            } else {
                selectedSOPairs.delete(cb.value);
            }
        });
        updateSOSelectionCount();
    });

    document.getElementById('btnConfirmMultiSO').addEventListener('click', addSelectedSOItems);

    // Modal 2: Stock Products Lookup Events
    const btnStock = document.getElementById('btnOpenStockModal');
    if (btnStock) btnStock.addEventListener('click', openStockModal);

    document.getElementById('btnShowAllProducts').addEventListener('click', () => {
        selectedItemNo = null;
        document.getElementById('selectedItemQuotaBox').classList.add('hidden');
        const btnSt = document.getElementById('btnOpenStockModal');
        if (btnSt) btnSt.disabled = true;
        renderItemsTable();
        renderProductsTable();
    });

    document.getElementById('stockModalSearch').addEventListener('input', filterStockTable);

    document.getElementById('chkAllStock').addEventListener('change', (e) => {
        document.querySelectorAll('#stockLookupBody tr').forEach(tr => {
            if (tr.style.display !== 'none') {
                const cb = tr.querySelector('.chk-stock-item:not(:disabled)');
                if (cb) cb.checked = e.target.checked;
            }
        });
        updateModalStockSummary();
    });

    document.getElementById('btnConfirmAddProducts').addEventListener('click', confirmAddProducts);
});

// =============================================================================
// LOAD MASTERS & INVOICE DETAIL
// =============================================================================
async function loadMasters() {
    const res = await fetch(`model/invoice_api_mats.php?action=get_masters&func=${encodeURIComponent(FUNC)}`);
    const json = await res.json();
    if (json.status !== 'success') return;
    const d = json.data;

    fillSelect('CURRENCY_ID', d.currencies, 'CURRENCY_ID', r => r.CURRENCY_ID.trim());
    fillSelect('BANK_ID', d.banks, 'BANK_ID', r => `${r.BANK_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '00: NONE');
    fillSelect('PAYMENT_ID', d.payments, 'PAYMENT_ID', r => `${r.PAYMENT_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '00: NONE');
    fillSelect('SHIPMENT_ID', d.shipments, 'SHIPMENT_ID', r => `${r.SHIPMENT_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '000: NONE');
    fillSelect('INV1_PORTLOADING', d.ports, 'PORT_ID', r => `${r.PORT_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '000: NONE PORT');
    fillSelect('INV1_PORTDISCHARGE', d.ports, 'PORT_ID', r => `${r.PORT_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '000: NONE PORT');
    fillSelect('INV1_PLACEDESTINATION', d.places, 'PLACE_ID', r => `${r.PLACE_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '000: NONE PLACE');
    fillSelect('INV1_COUNTRYDESTINATION', d.places, 'PLACE_ID', r => `${r.PLACE_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '000: NONE PLACE');
}

function fillSelect(id, list, valKey, labelFn, emptyLabel = null) {
    const sel = document.getElementById(id);
    sel.innerHTML = emptyLabel ? `<option value="${emptyLabel.split(':')[0].trim()}">${emptyLabel}</option>` : '';
    list.forEach(item => {
        const val = (item[valKey] || '').trim();
        sel.innerHTML += `<option value="${val}">${labelFn(item)}</option>`;
    });
}

async function loadInvoiceDetail() {
    const res = await fetch(`model/invoice_api_mats.php?action=get_detail&invoice_no=${encodeURIComponent(INVOICE_NO)}&func=${encodeURIComponent(FUNC)}`);
    const json = await res.json();
    if (json.status !== 'success') {
        alert(json.message);
        return;
    }
    invoiceData = json.data;
    await loadCustomerBookings();
    renderInvoiceDetail();
}

async function loadCustomerBookings() {
    const custId = (invoiceData.heading.CSTMSPPL_ID || '').trim();
    const res = await fetch(`model/invoice_api_mats.php?action=get_customer&customer_id=${encodeURIComponent(custId)}&invoice_no=${encodeURIComponent(INVOICE_NO)}&func=${encodeURIComponent(FUNC)}`);
    const json = await res.json();
    if (json.status === 'success') {
        const bookSel = document.getElementById('BOOK_NO');
        bookSel.innerHTML = '<option value="">-- No Booking --</option>';
        json.bookings.forEach(b => {
            const bNo = (b.BOOK_NO || '').trim();
            bookSel.innerHTML += `<option value="${bNo}">${bNo}</option>`;
        });
    }
}

function renderInvoiceDetail() {
    const h = invoiceData.heading;
    const isOP = (h.INV1_STATUS || '').trim() === 'OP';

    const badge = document.getElementById('badgeStatus');
    badge.textContent = (h.INV1_STATUS || 'OP').trim();
    badge.style.backgroundColor = isOP ? '#2563eb' : '#64748b';

    document.getElementById('lblCustomerSummary').textContent = `${(h.CSTMSPPL_ID || '').trim()} : ${(h.CONSIGNEE_COMPANY || '').trim()}`;

    if (!isOP && IS_AUTH) {
        document.getElementById('btnEditHead').classList.add('hidden');
        document.getElementById('btnDeleteHead').classList.add('hidden');
        document.getElementById('btnOpenSOModal').classList.add('hidden');
        document.getElementById('btnOpenStockModal').classList.add('hidden');
    }

    Object.keys(h).forEach(key => {
        const el = document.getElementById(key);
        if (!el) return;
        let val = h[key] ?? '';
        if (el.type === 'date' && val) {
            el.value = val.substring(0, 10);
        } else if (el.type === 'number') {
            el.value = parseFloat(val || 0).toFixed(2);
        } else {
            el.value = val.toString().trim();
        }
    });

    document.getElementById('INV1_PACKAGE').value = parseInt(h.INV1_PACKAGE || 0, 10);
    document.getElementById('INV1_NETPIECE').value = parseFloat(h.INV1_NETPIECE || 0).toLocaleString('en-US', { minimumFractionDigits: 0 });
    document.getElementById('INV1_NETWEIGHT').value = parseFloat(h.INV1_NETWEIGHT || 0).toFixed(2);
    document.getElementById('INV1_GROSSWEIGHT').value = parseFloat(h.INV1_GROSSWEIGHT || 0).toFixed(2);
    document.getElementById('INV1_TOTALAMOUNT').value = parseFloat(h.INV1_TOTALAMOUNT || 0).toFixed(4);

    const isLocal = (h.CSTMSPPL_LOCATION || '').trim() === 'L';
    document.getElementById('lblCustLoc').textContent = isLocal ? 'LOCAL (L)' : 'EXPORT';
    document.getElementById('viewConsignee').innerHTML = formatAddressBlock(h, 'CONSIGNEE');
    document.getElementById('viewNotify').innerHTML = formatAddressBlock(h, 'NOTIFY');
    document.getElementById('viewAlsoNotify').innerHTML = formatAddressBlock(h, 'ALSONOTIFY');

    calculateFinancialSummary();
    renderItemsTable();
    if (selectedItemNo) {
        renderSelectedItemQuota(selectedItemNo);
    }
    renderProductsTable();
}

function formatAddressBlock(h, prefix) {
    const lines = [
        `<strong>${h[`${prefix}_COMPANY`] || '-'}</strong>`,
        h[`${prefix}_ADDRESS1`] || '',
        h[`${prefix}_ADDRESS2`] || '',
        h[`${prefix}_ADDRESS3`] || '',
        h[`${prefix}_CONTACT`] ? `Contact: ${h[`${prefix}_CONTACT`]} (${h[`${prefix}_POSITION`] || ''})` : '',
        h[`${prefix}_TELEPHONE`] ? `Tel: ${h[`${prefix}_TELEPHONE`]} | Fax: ${h[`${prefix}_FAX`] || '-'}` : ''
    ].filter(x => x.trim() !== '');
    return lines.join('<br>');
}

function calculateFinancialSummary() {
    if (!invoiceData) return;
    const isLocal = (invoiceData.heading.CSTMSPPL_LOCATION || '').trim() === 'L';
    const totAmt = parseFloat(invoiceData.heading.INV1_TOTALAMOUNT || 0);
    const trans = parseFloat(document.getElementById('INV1_TRANSPORTATION').value || 0);
    const disc = parseFloat(document.getElementById('INV1_DISCOUNT').value || 0);
    const freight = parseFloat(document.getElementById('INV1_OVERSEAFREIGHT').value || 0);
    const insur = parseFloat(document.getElementById('INV1_INSURANCE').value || 0);
    const cert = parseFloat(document.getElementById('INV1_CERTIFICATE').value || 0);
    const taxPct = parseFloat(document.getElementById('INV1_TAXPERCENT').value || 0);
    const mGrand = parseFloat(document.getElementById('INV1_MGRANDTOTALAMOUNT').value || 0);

    const subTotal = totAmt + trans - disc;
    let taxAmt = 0, subFOB = 0, grandTot = 0;

    if (isLocal) {
        taxAmt = subTotal * (taxPct / 100.0);
        subFOB = 0;
        grandTot = subTotal + taxAmt;
    } else {
        taxAmt = 0;
        subFOB = subTotal - freight - insur;
        grandTot = subTotal + cert;
    }

    const calInsur = mGrand * 1.1 * 0.000215;

    document.getElementById('SUBTOTAL_AMOUNT').value = subTotal.toFixed(4);
    document.getElementById('TAX_AMOUNT').value = taxAmt.toFixed(4);
    document.getElementById('SUBTOTAL_FOB').value = subFOB.toFixed(4);
    document.getElementById('GRAND_TOTAL_AMOUNT').value = grandTot.toFixed(4);
    document.getElementById('CAL_INSURANCE').value = calInsur.toFixed(4);
}

function setHeadingEditable(editable) {
    const isLocal = (invoiceData.heading.CSTMSPPL_LOCATION || '').trim() === 'L';
    document.querySelectorAll('.head-editable').forEach(el => {
        if (el.id === 'BOOK_NO' && isLocal) {
            el.disabled = true;
        } else {
            el.disabled = !editable;
        }
    });
    document.getElementById('btnEditHead').classList.toggle('hidden', editable);
    document.getElementById('btnSaveHead').classList.toggle('hidden', !editable);
    document.getElementById('btnCancelHead').classList.toggle('hidden', !editable);
}

async function onBookingChange() {
    const bookNo = document.getElementById('BOOK_NO').value;
    const custId = document.getElementById('CSTMSPPL_ID').value;
    if (!bookNo) return;

    const res = await fetch(`model/invoice_api_mats.php?action=get_booking&book_no=${encodeURIComponent(bookNo)}&customer_id=${encodeURIComponent(custId)}&invoice_no=${encodeURIComponent(INVOICE_NO)}&func=${encodeURIComponent(FUNC)}`);
    const json = await res.json();
    if (json.status !== 'success') {
        alert(json.message);
        document.getElementById('BOOK_NO').value = (invoiceData.heading.BOOK_NO || '').trim();
        return;
    }
    const b = json.data;
    if (b.BSH1_PORTLOADING) document.getElementById('INV1_PORTLOADING').value = b.BSH1_PORTLOADING.trim();
    if (b.BSH1_PORTDISCHARGE) document.getElementById('INV1_PORTDISCHARGE').value = b.BSH1_PORTDISCHARGE.trim();
    if (b.BSH1_PLACEDESTINATION) document.getElementById('INV1_PLACEDESTINATION').value = b.BSH1_PLACEDESTINATION.trim();
    if (b.BSH1_COUNTRYDESTINATION) document.getElementById('INV1_COUNTRYDESTINATION').value = b.BSH1_COUNTRYDESTINATION.trim();
    document.getElementById('INV1_SHIPPINGMARK').value = (b.BSH1_SHIPPINGMARK || '').trim();
    for (let i = 1; i <= 7; i++) {
        document.getElementById(`INV1_SHIPPINGMARK${i}`).value = (b[`BSH1_SHIPPINGMARK${i}`] || '').trim();
    }
}

async function saveHeading() {
    const payload = { INVOICE_NO: INVOICE_NO };
    document.querySelectorAll('.head-editable').forEach(el => {
        if (el.type === 'checkbox') {
            payload[el.id] = el.checked ? 1 : 0;
        } else {
            payload[el.id] = el.value;
        }
    });

    const res = await fetch(`model/invoice_api_mats.php?action=update_heading&func=${encodeURIComponent(FUNC)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const json = await res.json();
    alert(json.message);
    if (json.status === 'success') {
        setHeadingEditable(false);
        await loadInvoiceDetail();
    }
}

async function deleteHeading() {
    if (!confirm(`ยืนยันการลบใบแจ้งหนี้ ${INVOICE_NO} ทั้งหมดหรือไม่?`)) return;
    const res = await fetch(`model/invoice_api_mats.php?action=delete_heading&func=${encodeURIComponent(FUNC)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ invoice_no: INVOICE_NO })
    });
    const json = await res.json();
    alert(json.message);
    if (json.status === 'success') {
        window.location.assign(`invoice_list_mats.php?func=${encodeURIComponent(FUNC)}`);
    }
}

// =============================================================================
// SECTION 2: ITEM DETAIL TABLE & SERVER-SIDE SO LOOKUP MODAL
// =============================================================================
function renderItemsTable() {
    const tbody = document.getElementById('itemTableBody');
    const items = invoiceData.items || [];
    const isOP = (invoiceData.heading.INV1_STATUS || '').trim() === 'OP';

    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="14" class="text-center text-muted">ยังไม่มีรายการสินค้า กดปุ่ม "+ ADD ITEM FROM SALE ORDER" ด้านบนเพื่อเพิ่มรายการ</td></tr>';
        return;
    }

    tbody.innerHTML = items.map(it => {
        const itemNo = (it.INVOICE_ITEM || '').trim();
        const isSelected = selectedItemNo === itemNo;
        const sizeStr = `${parseFloat(it.THICKNESS || 0).toFixed(3)} x ${parseFloat(it.WIDTH || 0).toFixed(3)} x ${parseFloat(it.LENGTH || 0).toFixed(3)}`;
        const quotaWt = parseFloat(it.CTM2_ORDERWEIGHT || 0) + parseFloat(it.CTM2_REPLACEWEIGHT || 0);

        return `
            <tr class="${isSelected ? 'row-selected' : ''}" onclick="selectInvoiceItem('${itemNo}')" style="cursor:pointer;">
                <td class="text-center"><strong>${itemNo}</strong></td>
                <td>${(it.SALEORDER_NO || '').trim()}-${(it.SALEORDER_ITEM || '').trim()}</td>
                <td>${(it.INV2_PONO || '-').trim()}</td>
                <td class="text-center">${(it.PRODUCT_ID || '').trim()}</td>
                <td>${(it.ALLOY || '').trim()} / ${(it.TEMPER || '').trim()} / ${(it.SURFACE_GRADE || '').trim()} / ${(it.METALLURGICAL_GRADE || '').trim()}</td>
                <td>${sizeStr}</td>
                <td class="text-right">${quotaWt.toFixed(2)}</td>
                <td class="text-right" style="font-weight:700; color:#047857;">${parseFloat(it.INV2_NETWEIGHT || 0).toFixed(2)}</td>
                <td class="text-right">${parseFloat(it.INV2_INVOICEPRICE || 0).toFixed(4)}</td>
                <td class="text-right">
                    <input type="number" step="0.00001" id="fob_${itemNo}" value="${parseFloat(it.INV2_FOBPRICE || 0).toFixed(5)}"
                           class="form-control input-sm text-right" style="width:90px; height:26px;" onclick="event.stopPropagation()" ${(!IS_AUTH || !isOP) ? 'disabled' : ''}>
                </td>
                <td>
                    <input type="text" id="uom_${itemNo}" value="${(it.INV2_UOM || '').trim()}"
                           class="form-control input-sm" style="width:70px; height:26px;" onclick="event.stopPropagation()" ${(!IS_AUTH || !isOP) ? 'disabled' : ''}>
                </td>
                <td>
                    <input type="text" id="skid_${itemNo}" value="${(it.INV2_SKIDNO || '').trim()}"
                           class="form-control input-sm" style="width:70px; height:26px;" onclick="event.stopPropagation()" ${(!IS_AUTH || !isOP) ? 'disabled' : ''}>
                </td>
                <td>
                    <input type="text" id="desc_${itemNo}" value="${(it.INV2_DESCRIPTION || '').trim()}"
                           class="form-control input-sm" style="width:160px; height:26px;" onclick="event.stopPropagation()" ${(!IS_AUTH || !isOP) ? 'disabled' : ''}>
                </td>
                <td class="text-center" onclick="event.stopPropagation()">
                    ${(IS_AUTH && isOP) ? `
                        <button type="button" class="btn btn-primary btn-xs" onclick="saveItemRow('${itemNo}')">Save</button>
                        <button type="button" class="btn btn-danger btn-xs" onclick="deleteItemRow('${itemNo}')">Del</button>
                    ` : '-'}
                </td>
            </tr>
        `;
    }).join('');
}

function selectInvoiceItem(itemNo) {
    selectedItemNo = itemNo;
    renderItemsTable();
    renderSelectedItemQuota(itemNo);
    renderProductsTable();

    const btnStock = document.getElementById('btnOpenStockModal');
    if (btnStock) btnStock.disabled = false;
}

function renderSelectedItemQuota(itemNo) {
    const it = (invoiceData.items || []).find(i => (i.INVOICE_ITEM || '').trim() === itemNo);
    const box = document.getElementById('selectedItemQuotaBox');
    if (!it) {
        box.classList.add('hidden');
        return;
    }

    const prodId = (it.PRODUCT_ID || '').trim();
    const maxTol = parseFloat(it.CTM2_MAXTOLERANCE || 0);
    const minTol = parseFloat(it.CTM2_MINTOLERANCE || 0);

    document.getElementById('qItemNo').textContent = itemNo;
    document.getElementById('qSONo').textContent = `${(it.SALEORDER_NO || '').trim()}-${(it.SALEORDER_ITEM || '').trim()}`;
    document.getElementById('qTol').textContent = `+${maxTol}% / -${minTol}%`;

    if (prodId === 'CO') {
        const basePc = parseFloat(it.CTM2_ORDERPIECE || 0) + parseFloat(it.CTM2_REPLACEPIECE || 0);
        const minPc = basePc - ((basePc * minTol) / 100.0);
        const maxPc = basePc + ((basePc * maxTol) / 100.0);
        document.getElementById('qOrdQty').textContent = `${basePc.toFixed(0)} Coils`;
        document.getElementById('qMinQty').textContent = `${minPc.toFixed(0)} Coils`;
        document.getElementById('qMaxQty').textContent = `${maxPc.toFixed(0)} Coils`;
        document.getElementById('qInvQty').textContent = `${parseFloat(it.CTM2_INVOICEPIECE || 0).toFixed(0)} Coils (${parseFloat(it.CTM2_INVOICEWEIGHT || 0).toFixed(2)} kg)`;
    } else {
        const baseWt = parseFloat(it.CTM2_ORDERWEIGHT || 0) + parseFloat(it.CTM2_REPLACEWEIGHT || 0);
        const minWt = baseWt - ((baseWt * minTol) / 100.0);
        const maxWt = baseWt + ((baseWt * maxTol) / 100.0);
        document.getElementById('qOrdQty').textContent = `${baseWt.toFixed(2)} kg`;
        document.getElementById('qMinQty').textContent = `${minWt.toFixed(2)} kg`;
        document.getElementById('qMaxQty').textContent = `${maxWt.toFixed(2)} kg`;
        document.getElementById('qInvQty').textContent = `${parseFloat(it.CTM2_INVOICEWEIGHT || 0).toFixed(2)} kg`;
    }

    const st = (it.CTM2_STATUS || 'OP').trim();
    const qStBadge = document.getElementById('qStatus');
    qStBadge.textContent = st;
    qStBadge.style.backgroundColor = (st === 'CP') ? '#15803d' : '#1d4ed8';

    box.classList.remove('hidden');
}

async function openSOModal() {
    selectedSOPairs.clear();
    document.getElementById('soModalSearch').value = '';
    document.getElementById('soModalCategoryFilter').value = '';
    document.getElementById('soModalProdFilter').value = '';
    document.getElementById('chkHideAddedSO').checked = false;
    document.getElementById('chkAllSO').checked = false;

    $('#modalAddSO').modal('show');
    await fetchSOItemsFromServer(true);
}

function resetSOFilter() {
    document.getElementById('soModalSearch').value = '';
    document.getElementById('soModalCategoryFilter').value = '';
    document.getElementById('soModalProdFilter').value = '';
    document.getElementById('chkHideAddedSO').checked = false;
    fetchSOItemsFromServer(false);
}

async function fetchSOItemsFromServer(isInitial = false) {
    const search = (document.getElementById('soModalSearch').value || '').trim();
    const category = (document.getElementById('soModalCategoryFilter').value || '').trim();
    const prodId = (document.getElementById('soModalProdFilter').value || '').trim();

    const tbody = document.getElementById('soLookupBody');
    tbody.innerHTML = '<tr><td colspan="13" class="text-center text-muted" style="padding: 24px;">กำลังโหลดข้อมูล Sale Order...</td></tr>';

    const params = new URLSearchParams({
        action: 'lookup_so_items',
        invoice_no: INVOICE_NO,
        search: search,
        category: category,
        prod_id: prodId,
        func: FUNC
    });

    try {
        const res = await fetch(`model/invoice_api_mats.php?${params.toString()}`);
        const json = await res.json();
        if (json.status !== 'success') {
            tbody.innerHTML = `<tr><td colspan="13" class="text-center text-danger" style="padding: 24px;">${json.message}</td></tr>`;
            return;
        }

        soListCache = json.data || [];

        if (isInitial) {
            const catSel = document.getElementById('soModalCategoryFilter');
            catSel.innerHTML = '<option value="">-- ทั้งหมด (All) --</option>' +
                (json.categories || []).map(c => `<option value="${c}">${c}</option>`).join('');

            const prodSel = document.getElementById('soModalProdFilter');
            prodSel.innerHTML = '<option value="">-- ทั้งหมด (All) --</option>' +
                (json.products || []).map(p => `<option value="${p}">${p}</option>`).join('');
        }

        renderSOTable();
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="13" class="text-center text-danger" style="padding: 24px;">เกิดข้อผิดพลาดในการเชื่อมต่อ: ${err.message}</td></tr>`;
    }
}

function renderSOTable() {
    const hideAdded = document.getElementById('chkHideAddedSO').checked;
    const isFiltered = (document.getElementById('soModalSearch').value.trim() !== '') ||
                       (document.getElementById('soModalCategoryFilter').value !== '') ||
                       (document.getElementById('soModalProdFilter').value !== '');

    const existingSet = new Set(
        (invoiceData.items || []).map(it => `${(it.SALEORDER_NO || '').trim()}-${(it.SALEORDER_ITEM || '').trim()}`)
    );

    const displayRows = hideAdded
        ? soListCache.filter(r => !existingSet.has(`${(r.SALEORDER_NO || '').trim()}-${(r.SALEORDER_ITEM || '').trim()}`))
        : soListCache;

    const badgeText = isFiltered
        ? `พบ ${displayRows.length} รายการ (สูงสุด 200)`
        : `แสดง ${displayRows.length} รายการล่าสุด`;
    document.getElementById('lblSOCountBadge').textContent = badgeText;
    document.getElementById('chkAllSO').checked = false;

    const tbody = document.getElementById('soLookupBody');
    if (displayRows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="13" class="text-center text-muted" style="padding: 24px;">ไม่พบรายการ Sale Order ที่ตรงกับเงื่อนไขการค้นหา</td></tr>';
        updateSOSelectionCount();
        return;
    }

    tbody.innerHTML = displayRows.map(r => {
        const soNo = (r.SALEORDER_NO || '').trim();
        const soItem = (r.SALEORDER_ITEM || '').trim();
        const pairKey = `${soNo}|${soItem}`;
        const cat = (r.ORDER_CATEGORY || '').trim();
        const isAdded = existingSet.has(`${soNo}-${soItem}`);
        const isChecked = !isAdded && selectedSOPairs.has(pairKey);

        const ordWt = parseFloat(r.CTM2_ORDERWEIGHT || 0) + parseFloat(r.CTM2_REPLACEWEIGHT || 0);
        const invWt = parseFloat(r.CTM2_INVOICEWEIGHT || 0);
        const maxTol = parseFloat(r.CTM2_MAXTOLERANCE || 0);
        const maxQuota = ordWt + ((ordWt * maxTol) / 100.0);

        const catBadge = (cat === 'ORDR')
            ? '<span class="label label-primary">ORDR</span>'
            : `<span class="label label-default">${cat}</span>`;

        return `
            <tr class="${isAdded ? 'row-so-added' : ''}" onclick="toggleSOCheckbox(this)" style="cursor: pointer;">
                <td class="text-center" onclick="event.stopPropagation()">
                    <input type="checkbox" class="chk-so-item" value="${pairKey}"
                           ${isAdded ? 'disabled' : ''} ${isChecked ? 'checked' : ''}
                           onchange="onSOCheckboxChange(this)">
                </td>
                <td><strong style="color: ${isAdded ? '#94a3b8' : '#0f172a'};">${soNo}</strong></td>
                <td class="text-center">${soItem}</td>
                <td class="text-center">${catBadge}</td>
                <td>${(r.CTM1_PONO || '-').trim()}</td>
                <td class="text-center"><strong>${(r.PRODUCT_ID || '').trim()}</strong></td>
                <td>${(r.ALLOY || '').trim()} / ${(r.TEMPER || '').trim()}</td>
                <td>${parseFloat(r.THICKNESS || 0).toFixed(3)} x ${parseFloat(r.WIDTH || 0).toFixed(3)} x ${parseFloat(r.LENGTH || 0).toFixed(3)}</td>
                <td class="text-right">${ordWt.toFixed(2)}</td>
                <td class="text-right">${invWt.toFixed(2)}</td>
                <td class="text-right" style="font-weight: 700; color: ${isAdded ? '#94a3b8' : '#047857'};">${maxQuota.toFixed(2)}</td>
                <td class="text-right">${parseFloat(r.CTM2_ACTUALPRICE || 0).toFixed(4)}</td>
                <td class="text-center" onclick="event.stopPropagation()">
                    ${isAdded
                        ? '<span class="label label-info">เพิ่มแล้ว</span>'
                        : `<button type="button" class="btn btn-success btn-xs" style="font-weight: 700;" onclick="addSOItem('${soNo}', '${soItem}')">+ เลือก</button>`
                    }
                </td>
            </tr>
        `;
    }).join('');

    updateSOSelectionCount();
}

function toggleSOCheckbox(tr) {
    const cb = tr.querySelector('.chk-so-item');
    if (cb && !cb.disabled) {
        cb.checked = !cb.checked;
        onSOCheckboxChange(cb);
    }
}

function onSOCheckboxChange(cb) {
    if (cb.checked) {
        selectedSOPairs.add(cb.value);
    } else {
        selectedSOPairs.delete(cb.value);
    }
    updateSOSelectionCount();
}

function updateSOSelectionCount() {
    const count = selectedSOPairs.size;
    document.getElementById('lblSelectedSOCount').textContent = count;
    const btn = document.getElementById('btnConfirmMultiSO');
    if (btn) btn.disabled = (count === 0);
}

async function addSOItem(soNo, soItem) {
    const res = await fetch(`model/invoice_api_mats.php?action=add_item&func=${encodeURIComponent(FUNC)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ invoice_no: INVOICE_NO, so_no: soNo, so_item: soItem })
    });
    const json = await res.json();
    if (json.status === 'success') {
        if (json.warning) alert(json.warning);
        $('#modalAddSO').modal('hide');
        selectedItemNo = json.invoice_item;
        await loadInvoiceDetail();
        const btnStock = document.getElementById('btnOpenStockModal');
        if (btnStock) btnStock.disabled = false;
    } else {
        alert('Error: ' + json.message);
    }
}

async function addSelectedSOItems() {
    if (selectedSOPairs.size === 0) return;

    const itemsPayload = Array.from(selectedSOPairs).map(pair => {
        const [so_no, so_item] = pair.split('|');
        return { so_no, so_item };
    });

    const btn = document.getElementById('btnConfirmMultiSO');
    btn.disabled = true;
    btn.textContent = 'กำลังบันทึก...';

    try {
        const res = await fetch(`model/invoice_api_mats.php?action=add_multiple_items&func=${encodeURIComponent(FUNC)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invoice_no: INVOICE_NO, items: itemsPayload })
        });
        const json = await res.json();
        if (json.status === 'success') {
            if (json.warning) alert(json.warning);
            $('#modalAddSO').modal('hide');
            selectedItemNo = json.last_invoice_item || selectedItemNo;
            await loadInvoiceDetail();
            const btnStock = document.getElementById('btnOpenStockModal');
            if (btnStock && selectedItemNo) btnStock.disabled = false;
        } else {
            alert('Error: ' + json.message);
        }
    } finally {
        btn.disabled = false;
        btn.textContent = '+ เพิ่มรายการที่เลือกเข้า Invoice';
    }
}

async function saveItemRow(itemNo) {
    const payload = {
        INVOICE_NO: INVOICE_NO,
        INVOICE_ITEM: itemNo,
        INV2_FOBPRICE: document.getElementById(`fob_${itemNo}`).value,
        INV2_UOM: document.getElementById(`uom_${itemNo}`).value,
        INV2_SKIDNO: document.getElementById(`skid_${itemNo}`).value,
        INV2_DESCRIPTION: document.getElementById(`desc_${itemNo}`).value
    };
    const res = await fetch(`model/invoice_api_mats.php?action=update_item&func=${encodeURIComponent(FUNC)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const json = await res.json();
    alert(json.message);
    if (json.status === 'success') await loadInvoiceDetail();
}

async function deleteItemRow(itemNo) {
    if (!confirm(`ยืนยันการลบ Item ${itemNo} หรือไม่?`)) return;
    const res = await fetch(`model/invoice_api_mats.php?action=delete_item&func=${encodeURIComponent(FUNC)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ invoice_no: INVOICE_NO, invoice_item: itemNo })
    });
    const json = await res.json();
    alert(json.message);
    if (json.status === 'success') {
        if (selectedItemNo === itemNo) {
            selectedItemNo = null;
            document.getElementById('selectedItemQuotaBox').classList.add('hidden');
        }
        await loadInvoiceDetail();
    }
}

// =============================================================================
// SECTION 3: PRODUCT DETAIL TABLE & MULTI-SELECT STOCK MODAL
// =============================================================================
function renderProductsTable() {
    const tbody = document.getElementById('productTableBody');
    const badge = document.getElementById('lblSelectedItemBadge');
    const isOP = (invoiceData.heading.INV1_STATUS || '').trim() === 'OP';

    let products = invoiceData.products || [];
    if (selectedItemNo) {
        badge.textContent = `Filtered by Item: ${selectedItemNo}`;
        products = products.filter(p => (p.INVOICE_ITEM || '').trim() === selectedItemNo);
    } else {
        badge.textContent = 'Showing All Items';
    }

    if (products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="13" class="text-center text-muted">ยังไม่มีรายการตัวสินค้า (คลิกเลือกแถวใน Item Detail แล้วกด "+ SELECT STOCK / PRODUCTS")</td></tr>';
        return;
    }

    tbody.innerHTML = products.map(p => {
        const pNo = (p.PRODUCT_NO || '').trim();
        const itemNo = (p.INVOICE_ITEM || '').trim();
        const cbm = (parseFloat(p.INV3_DIMENSIONWIDTH || 0) * parseFloat(p.INV3_DIMENSIONLENGTH || 0) * parseFloat(p.INV3_DIMENSIONHIGH || 0)) / 1000000000.0;
        const amt = parseFloat(p.IN3U_NETWEIGHT || 0) * parseFloat(p.INV3_INVOICEPRICE || 0);
        const specStr = `${(p.ALLOY || '').trim()}/${(p.TEMPER || '').trim()} (${parseFloat(p.THICKNESS || 0).toFixed(3)}x${parseFloat(p.WIDTH || 0).toFixed(3)}x${parseFloat(p.LENGTH || 0).toFixed(3)})`;

        return `
            <tr>
                <td class="text-center">${itemNo}</td>
                <td class="text-center">
                    <input type="text" value="${(p.SKID_NO || '').trim()}" class="form-control input-sm text-center"
                           style="width:55px; height:26px; display:inline-block;"
                           onchange="updateProductSkid('${pNo}', this.value)" ${(!IS_AUTH || !isOP) ? 'disabled' : ''}>
                </td>
                <td><strong>${pNo}</strong></td>
                <td>${(p.COIL_NO || '-').trim()}</td>
                <td class="text-center">${(p.PRODUCT_ID || '').trim()}</td>
                <td>${specStr}</td>
                <td class="text-right">${p.INV3_PACKAGE}</td>
                <td class="text-right">${parseFloat(p.INV3_NETPIECE || 0).toFixed(0)}</td>
                <td class="text-right" style="font-weight:700;">${parseFloat(p.INV3_NETWEIGHT || 0).toFixed(2)}</td>
                <td class="text-right">${parseFloat(p.INV3_GROSSWEIGHT || 0).toFixed(2)}</td>
                <td class="text-right">${cbm.toFixed(4)}</td>
                <td class="text-right" style="font-weight:700; color:#1e3a8a;">${amt.toFixed(4)}</td>
                <td class="text-center">
                    ${(IS_AUTH && isOP) ? `
                        <button type="button" class="btn btn-danger btn-xs" onclick="deleteProductRow('${itemNo}', '${pNo}')">
                            Del (คืนสต็อก)
                        </button>
                    ` : '-'}
                </td>
            </tr>
        `;
    }).join('');
}

async function openStockModal() {
    if (!selectedItemNo) {
        alert('กรุณาคลิกเลือกรายการในตาราง Item Detail ก่อน');
        return;
    }

    const itemObj = invoiceData.items.find(i => (i.INVOICE_ITEM || '').trim() === selectedItemNo);
    const prodId = (itemObj.PRODUCT_ID || '').trim();
    const maxTol = parseFloat(itemObj.CTM2_MAXTOLERANCE || 0);
    const minTol = parseFloat(itemObj.CTM2_MINTOLERANCE || 0);

    document.getElementById('modalStockItemNo').textContent = `${selectedItemNo} (SO: ${itemObj.SALEORDER_NO}-${itemObj.SALEORDER_ITEM})`;
    if (prodId === 'CO') {
        const basePc = parseFloat(itemObj.CTM2_ORDERPIECE || 0) + parseFloat(itemObj.CTM2_REPLACEPIECE || 0);
        const maxPc = basePc + ((basePc * maxTol) / 100.0);
        const minPc = basePc - ((basePc * minTol) / 100.0);
        const curPc = parseFloat(itemObj.CTM2_INVOICEPIECE || 0);
        document.getElementById('lblModalQuotaInfo').textContent =
            `Min: ${minPc.toFixed(0)} Coils | Max: ${maxPc.toFixed(0)} Coils | ออกบิลแล้ว: ${curPc.toFixed(0)} Coils`;
    } else {
        const baseWt = parseFloat(itemObj.CTM2_ORDERWEIGHT || 0) + parseFloat(itemObj.CTM2_REPLACEWEIGHT || 0);
        const maxWt = baseWt + ((baseWt * maxTol) / 100.0);
        const minWt = baseWt - ((baseWt * minTol) / 100.0);
        const curWt = parseFloat(itemObj.CTM2_INVOICEWEIGHT || 0);
        document.getElementById('lblModalQuotaInfo').textContent =
            `Min: ${minWt.toFixed(2)} kg | Max: ${maxWt.toFixed(2)} kg | ออกบิลแล้ว: ${curWt.toFixed(2)} kg (คงเหลือสูงสุด: ${(maxWt - curWt).toFixed(2)} kg)`;
    }

    document.getElementById('stockModalSearch').value = '';
    document.getElementById('chkAllStock').checked = false;
    document.getElementById('modalCloseSO').checked = false;

    const res = await fetch(`model/invoice_api_mats.php?action=lookup_stock_products&invoice_no=${encodeURIComponent(INVOICE_NO)}&invoice_item=${encodeURIComponent(selectedItemNo)}&func=${encodeURIComponent(FUNC)}`);
    const json = await res.json();
    if (json.status !== 'success') {
        alert(json.message);
        return;
    }

    stockListCache = json.data;
    const tbody = document.getElementById('stockLookupBody');
    if (stockListCache.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted" style="padding: 20px;">ไม่พบสินค้าคงคลัง (ST/DR) ของ Sale Order นี้</td></tr>';
    } else {
        tbody.innerHTML = stockListCache.map((s, idx) => {
            const pNo = (s.PRODUCT_NO || '').trim();
            const cNo = (s.COIL_NO || '').trim();
            const disabled = !s.SPEC_VALID ? 'disabled' : '';
            const badge = s.SPEC_VALID
                ? '<span class="label label-success">PASS</span>'
                : `<span class="label label-danger" title="${s.SPEC_MESSAGE}">${s.SPEC_MESSAGE}</span>`;

            return `
                <tr class="${!s.SPEC_VALID ? 'danger' : ''}" data-search="${(pNo + ' ' + cNo).toUpperCase()}">
                    <td class="text-center">
                        <input type="checkbox" class="chk-stock-item" data-idx="${idx}" value="${pNo}" ${disabled} onchange="updateModalStockSummary()">
                    </td>
                    <td><strong>${pNo}</strong></td>
                    <td>${cNo || '-'}</td>
                    <td class="text-center">${(s.PACK_STATUS || '').trim()}</td>
                    <td>${(s.ALLOY || '').trim()} / ${(s.TEMPER || '').trim()} / ${(s.SURFACE_GRADE || '').trim()} / ${(s.METALLURGICAL_GRADE || '').trim()}</td>
                    <td>${parseFloat(s.THICKNESS || 0).toFixed(3)} x ${parseFloat(s.WIDTH || 0).toFixed(3)} x ${parseFloat(s.LENGTH || 0).toFixed(3)}</td>
                    <td class="text-right">${parseFloat(s.PACK_NETPIECE || 0).toFixed(0)}</td>
                    <td class="text-right" style="font-weight:700;">${parseFloat(s.PACK_NETWEIGHT || 0).toFixed(2)}</td>
                    <td class="text-right">${(parseFloat(s.PACK_NETWEIGHT || 0) + parseFloat(s.PACK_PACKAGEWEIGHT || 0)).toFixed(2)}</td>
                    <td>${badge}</td>
                </tr>
            `;
        }).join('');
    }
    updateModalStockSummary();
    $('#modalSelectStock').modal('show');
}

function filterStockTable() {
    const kw = (document.getElementById('stockModalSearch').value || '').toUpperCase().trim();
    document.querySelectorAll('#stockLookupBody tr').forEach(tr => {
        const blob = tr.getAttribute('data-search') || '';
        tr.style.display = (!kw || blob.includes(kw)) ? '' : 'none';
    });
}

function updateModalStockSummary() {
    let count = 0;
    let sumWt = 0;
    document.querySelectorAll('.chk-stock-item:checked').forEach(cb => {
        const idx = parseInt(cb.getAttribute('data-idx'), 10);
        const item = stockListCache[idx];
        if (item) {
            count++;
            sumWt += parseFloat(item.PACK_NETWEIGHT || 0);
        }
    });
    document.getElementById('lblModalSelectedCount').textContent = count;
    document.getElementById('lblModalSelectedWeight').textContent = sumWt.toFixed(2);
}

async function confirmAddProducts() {
    const selectedNos = Array.from(document.querySelectorAll('.chk-stock-item:checked')).map(cb => cb.value);
    if (selectedNos.length === 0) {
        alert('กรุณาติ๊กเลือกสินค้าอย่างน้อย 1 รายการ');
        return;
    }

    const skidNo = document.getElementById('modalSkidNo').value;
    let closeSO = document.getElementById('modalCloseSO').checked;

    const itemObj = invoiceData.items.find(i => (i.INVOICE_ITEM || '').trim() === selectedItemNo);
    const prodId = (itemObj.PRODUCT_ID || '').trim();
    const minTol = parseFloat(itemObj.CTM2_MINTOLERANCE || 0);

    if (!closeSO && (itemObj.CTM2_STATUS || '').trim() !== 'CP') {
        if (prodId === 'CO') {
            const totalInvPc = parseFloat(itemObj.CTM2_INVOICEPIECE || 0) + selectedNos.length;
            const basePc = parseFloat(itemObj.CTM2_ORDERPIECE || 0) + parseFloat(itemObj.CTM2_REPLACEPIECE || 0);
            const minPc = basePc - ((basePc * minTol) / 100.0);
            if (totalInvPc >= minPc) {
                if (confirm('จำนวน Coil ที่ทำ Invoice มากกว่าหรือเท่ากับจำนวนต่ำสุดของ Sale Order แล้ว\nคุณต้องการปิด Sale Order Item นี้เป็น (CP: Complete) หรือไม่?')) {
                    closeSO = true;
                }
            }
        } else if (['CC', 'SH', 'NC'].includes(prodId)) {
            const addedWt = parseFloat(document.getElementById('lblModalSelectedWeight').textContent || 0);
            const totalInvWt = parseFloat(itemObj.CTM2_INVOICEWEIGHT || 0) + addedWt;
            const baseWt = parseFloat(itemObj.CTM2_ORDERWEIGHT || 0) + parseFloat(itemObj.CTM2_REPLACEWEIGHT || 0);
            const minWt = baseWt - ((baseWt * minTol) / 100.0);
            if (totalInvWt >= minWt) {
                if (confirm('จำนวนน้ำหนักที่ทำ Invoice มากกว่าหรือเท่ากับจำนวนต่ำสุดของ Sale Order แล้ว\nคุณต้องการปิด Sale Order Item นี้เป็น (CP: Complete) หรือไม่?')) {
                    closeSO = true;
                }
            }
        }
    }

    const btn = document.getElementById('btnConfirmAddProducts');
    btn.disabled = true;
    btn.textContent = 'PROCESSING...';

    try {
        const res = await fetch(`model/invoice_api_mats.php?action=add_products&func=${encodeURIComponent(FUNC)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                invoice_no: INVOICE_NO,
                invoice_item: selectedItemNo,
                product_nos: selectedNos,
                skid_no: skidNo,
                close_so: closeSO
            })
        });
        const json = await res.json();
        alert(json.message);
        if (json.status === 'success') {
            $('#modalSelectStock').modal('hide');
            await loadInvoiceDetail();
        }
    } finally {
        btn.disabled = false;
        btn.textContent = 'CONFIRM & DEDUCT STOCK';
    }
}

async function updateProductSkid(productNo, skidNo) {
    const res = await fetch(`model/invoice_api_mats.php?action=update_product_skid&func=${encodeURIComponent(FUNC)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ invoice_no: INVOICE_NO, product_no: productNo, skid_no: skidNo })
    });
    const json = await res.json();
    if (json.status !== 'success') alert(json.message);
}

async function deleteProductRow(itemNo, productNo) {
    if (!confirm(`ยืนยันการลบตัวสินค้า ${productNo} และคืนสถานะกลับเข้าสต็อกหรือไม่?`)) return;
    const res = await fetch(`model/invoice_api_mats.php?action=delete_product&func=${encodeURIComponent(FUNC)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ invoice_no: INVOICE_NO, invoice_item: itemNo, product_no: productNo })
    });
    const json = await res.json();
    alert(json.message);
    if (json.status === 'success') await loadInvoiceDetail();
}