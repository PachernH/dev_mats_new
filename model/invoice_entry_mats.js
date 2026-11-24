const FUNC = document.getElementById('func').value;

document.addEventListener('DOMContentLoaded', async () => {
    await loadMasters();

    document.getElementById('CSTMSPPL_ID').addEventListener('change', onCustomerChange);
    document.getElementById('BOOK_NO').addEventListener('change', onBookingChange);
    document.getElementById('btnCreateSave').addEventListener('click', saveNewInvoice);
});

async function loadMasters() {
    try {
        const res = await fetch(`model/invoice_api_mats.php?action=get_masters&func=${encodeURIComponent(FUNC)}`);
        const json = await res.json();
        if (json.status !== 'success') {
            alert('ไม่สามารถโหลดข้อมูล Master ได้: ' + (json.message || 'Unknown Error'));
            return;
        }
        const d = json.data;

        fillSelect('CSTMSPPL_ID', d.customers, 'CSTMSPPL_ID', r => `${r.CSTMSPPL_ID.trim()}: ${(r.CONSIGNEE_COMPANY || '').trim()}`, '-- Select Customer --');
        fillSelect('CURRENCY_ID', d.currencies, 'CURRENCY_ID', r => r.CURRENCY_ID.trim());
        fillSelect('BANK_ID', d.banks, 'BANK_ID', r => `${r.BANK_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '00: NONE');
        fillSelect('PAYMENT_ID', d.payments, 'PAYMENT_ID', r => `${r.PAYMENT_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '00: NONE');
        fillSelect('SHIPMENT_ID', d.shipments, 'SHIPMENT_ID', r => `${r.SHIPMENT_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '000: NONE');
        fillSelect('INV1_PORTLOADING', d.ports, 'PORT_ID', r => `${r.PORT_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '000: NONE PORT');
        fillSelect('INV1_PORTDISCHARGE', d.ports, 'PORT_ID', r => `${r.PORT_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '000: NONE PORT');
        fillSelect('INV1_PLACEDESTINATION', d.places, 'PLACE_ID', r => `${r.PLACE_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '000: NONE PLACE');
        fillSelect('INV1_COUNTRYDESTINATION', d.places, 'PLACE_ID', r => `${r.PLACE_ID.trim()}: ${(r.DESCRIPTION || '').trim()}`, '000: NONE PLACE');
    } catch (err) {
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ API: ' + err.message);
    }
}

function fillSelect(id, list, valKey, labelFn, emptyLabel = null) {
    const sel = document.getElementById(id);
    sel.innerHTML = emptyLabel ? `<option value="${emptyLabel.split(':')[0].trim()}">${emptyLabel}</option>` : '';
    list.forEach(item => {
        const val = (item[valKey] || '').trim();
        sel.innerHTML += `<option value="${val}">${labelFn(item)}</option>`;
    });
}

async function onCustomerChange() {
    const custId = document.getElementById('CSTMSPPL_ID').value;
    if (!custId) return;

    const res = await fetch(`model/invoice_api_mats.php?action=get_customer&customer_id=${encodeURIComponent(custId)}&func=${encodeURIComponent(FUNC)}`);
    const json = await res.json();
    if (json.status !== 'success') {
        alert(json.message);
        return;
    }

    const c = json.customer;
    document.getElementById('ACCOUNT_CODE').value = (c.ACCOUNT_CODE || '').trim();
    document.getElementById('CURRENCY_ID').value = (c.CURRENCY_ID || 'BAHT').trim();
    document.getElementById('INV1_TAXPERCENT').value = (c.CSSP_TAXPERCENT || '0').toString().trim();
    if (c.PAYMENT_ID) document.getElementById('PAYMENT_ID').value = c.PAYMENT_ID.trim();
    if (c.SHIPMENT_ID) document.getElementById('SHIPMENT_ID').value = c.SHIPMENT_ID.trim();

    const loc = (c.CSTMSPPL_LOCATION || '').trim();
    document.getElementById('lblLocation').textContent = (loc === 'L') ? 'LOCAL (L)' : 'EXPORT';

    ['CONSIGNEE', 'NOTIFY', 'ALSONOTIFY'].forEach(prefix => {
        ['COMPANY', 'ADDRESS1', 'ADDRESS2', 'ADDRESS3'].forEach(field => {
            const el = document.getElementById(`${prefix}_${field}`);
            if (el) el.value = (c[`${prefix}_${field}`] || '').trim();
        });
    });

    const bookSel = document.getElementById('BOOK_NO');
    if (loc === 'L') {
        bookSel.innerHTML = '<option value="">-- Local Customer (No Booking) --</option>';
        bookSel.disabled = true;
    } else {
        bookSel.disabled = false;
        bookSel.innerHTML = '<option value="">-- Select Booking No --</option>';
        json.bookings.forEach(b => {
            const bNo = (b.BOOK_NO || '').trim();
            bookSel.innerHTML += `<option value="${bNo}">${bNo}</option>`;
        });
    }
}

async function onBookingChange() {
    const bookNo = document.getElementById('BOOK_NO').value;
    const custId = document.getElementById('CSTMSPPL_ID').value;
    if (!bookNo) return;

    const res = await fetch(`model/invoice_api_mats.php?action=get_booking&book_no=${encodeURIComponent(bookNo)}&customer_id=${encodeURIComponent(custId)}&func=${encodeURIComponent(FUNC)}`);
    const json = await res.json();
    if (json.status !== 'success') {
        alert(json.message);
        document.getElementById('BOOK_NO').value = '';
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

async function saveNewInvoice() {
    const custId = document.getElementById('CSTMSPPL_ID').value;
    if (!custId) {
        alert('กรุณาเลือกรหัสลูกค้าก่อนบันทึก');
        return;
    }

    const payload = {};
    document.querySelectorAll('#entryForm input, #entryForm select').forEach(el => {
        if (el.id) payload[el.id] = el.value;
    });

    const btn = document.getElementById('btnCreateSave');
    btn.disabled = true;
    btn.textContent = 'SAVING...';

    try {
        const res = await fetch(`model/invoice_api_mats.php?action=create_heading&func=${encodeURIComponent(FUNC)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.status === 'success') {
            window.location.assign(`invoice_detail_mats.php?func=${encodeURIComponent(FUNC)}&invno=${encodeURIComponent(json.invoice_no)}`);
        } else {
            alert('Error: ' + json.message);
            btn.disabled = false;
            btn.textContent = 'SAVE & CONTINUE TO ITEMS →';
        }
    } catch (err) {
        alert('Network Error: ' + err.message);
        btn.disabled = false;
        btn.textContent = 'SAVE & CONTINUE TO ITEMS →';
    }
}