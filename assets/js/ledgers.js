/**
 * Advanced Financial Ledger & Currency Conversion Engine
 * Aeroheights Travels & Tours - Umrah ERP
 */

// Live PKR to SAR Conversion with Standard Mathematical Rounding
function calculateLiveConversion() {
    const pkrInput = document.getElementById('modal_pay_pkr');
    const rateInput = document.getElementById('modal_pay_rate');
    const displayElement = document.getElementById('modal_converted_sar');

    if (!pkrInput || !displayElement) return;

    const pkr = parseFloat(pkrInput.value) || 0;
    const rate = parseFloat(rateInput ? rateInput.value : 76.00) || 76.00;

    if (rate <= 0 || pkr <= 0) {
        displayElement.textContent = '0 SAR';
        return;
    }

    // Standard Math Rounding: < .50 rounds down, >= .50 rounds up
    const sar = Math.round(pkr / rate);
    displayElement.textContent = `${sar.toLocaleString('en-US')} SAR`;
}

// Modal: Receive Payment from Agent (Accounts Receivable)
function openAgentPaymentModal(agentId, agentName) {
    let modal = document.getElementById('dynamicModalContainer');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'dynamicModalContainer';
        document.body.appendChild(modal);
    }

    const today = new Date().toISOString().split('T')[0];

    modal.innerHTML = `
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Receive Payment (Receipt)</h3>
                        <p class="text-[11px] text-slate-500">Agent: <strong>${escapeHtml(agentName)}</strong></p>
                    </div>
                    <button type="button" onclick="closeActiveModal()" class="text-slate-400 hover:text-slate-600 transition">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>
                <form id="agentPaymentForm" onsubmit="submitAgentPaymentDirect(event, ${agentId})" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Deposit Bank Account</label>
                        <select id="modal_pay_bank" class="w-full border border-slate-300 rounded-xl p-2.5 bg-slate-50 font-medium focus:bg-white outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="Meezan Bank">Meezan Bank</option>
                            <option value="UBL (United Bank Limited)">UBL (United Bank Limited)</option>
                            <option value="Allied Bank">Allied Bank</option>
                            <option value="Cash in Hand">Cash in Hand</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Amount (PKR)</label>
                            <input type="number" id="modal_pay_pkr" oninput="calculateLiveConversion()" placeholder="e.g. 300000" required class="w-full border border-slate-300 rounded-xl p-2.5 font-mono font-bold text-slate-800 outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Exchange Rate (PKR/SAR)</label>
                            <input type="number" step="0.01" id="modal_pay_rate" oninput="calculateLiveConversion()" value="76.00" required class="w-full border border-slate-300 rounded-xl p-2.5 font-mono font-bold text-emerald-700 outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-200 p-3 rounded-xl flex items-center justify-between">
                        <span class="font-semibold text-emerald-800">SAR Converted Credit (Rounded):</span>
                        <span id="modal_converted_sar" class="font-black text-sm text-emerald-900 font-mono">0 SAR</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Payment Date</label>
                            <input type="date" id="modal_pay_date" value="${today}" required class="w-full border border-slate-300 rounded-xl p-2.5 font-mono outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Deposit Slip / Ref #</label>
                            <input type="text" id="modal_pay_receipt" placeholder="e.g. UBL-7689" class="w-full border border-slate-300 rounded-xl p-2.5 font-mono outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Remarks / Description</label>
                        <input type="text" id="modal_pay_remarks" placeholder="Optional notes..." class="w-full border border-slate-300 rounded-xl p-2.5 outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <button type="button" onclick="closeActiveModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition">
                            Cancel
                        </button>
                        <button type="submit" id="btnSubmitAgentPay" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-md">
                            <i class="fa-solid fa-check mr-1.5"></i> Commit Payment Receipt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
}

// API Submission for Agent Payment
async function submitAgentPaymentDirect(e, agentId) {
    e.preventDefault();
    e.stopPropagation();

    const submitBtn = document.getElementById('btnSubmitAgentPay');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i> Processing...';
    }

    const payload = {
        agent_id: agentId,
        bank_name: document.getElementById('modal_pay_bank').value,
        amount_pkr: document.getElementById('modal_pay_pkr').value,
        exchange_rate: document.getElementById('modal_pay_rate').value,
        payment_date: document.getElementById('modal_pay_date').value,
        receipt_number: document.getElementById('modal_pay_receipt').value,
        remarks: document.getElementById('modal_pay_remarks').value
    };

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch('index.php?api=save_agent_payment', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        if (data.success) {
            closeActiveModal();
            window.location.reload();
        } else {
            alert(data.message || 'Error recording agent payment.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-check mr-1.5"></i> Commit Payment Receipt';
            }
        }
    } catch (err) {
        alert('Network or Server Error: ' + err.message);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-check mr-1.5"></i> Commit Payment Receipt';
        }
    }
}

function editAgentPayment(payment) {
    let modal = document.getElementById('dynamicModalContainer');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'dynamicModalContainer';
        document.body.appendChild(modal);
    }
    modal.innerHTML = `
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3"><h3 class="font-bold text-slate-800 text-sm">Edit Payment Receipt</h3><button type="button" onclick="closeActiveModal()" class="text-slate-400"><i class="fa-solid fa-xmark text-base"></i></button></div>
                <form id="editAgentPaymentForm" class="space-y-3 text-xs">
                    <input type="hidden" name="payment_id" value="${payment.payment_id}"><input type="hidden" name="agent_id" value="${payment.agent_id}">
                    <div><label class="block font-semibold mb-1 text-slate-700">Payment Date</label><input type="date" name="payment_date" value="${escapeHtml(payment.payment_date)}" required class="w-full border rounded-xl p-2.5 font-mono"></div>
                    <div><label class="block font-semibold mb-1 text-slate-700">Amount (PKR)</label><input type="number" step="0.01" min="1" name="amount_pkr" value="${payment.amount_pkr}" required class="w-full border rounded-xl p-2.5 font-mono"></div>
                    <div><label class="block font-semibold mb-1 text-slate-700">Exchange Rate (PKR/SAR)</label><input type="number" step="0.01" min="0.01" name="exchange_rate" value="${payment.exchange_rate}" required class="w-full border rounded-xl p-2.5 font-mono"></div>
                    <div><label class="block font-semibold mb-1 text-slate-700">Bank Name / Cash Vault</label><input type="text" name="bank_name" value="${escapeHtml(payment.bank_name)}" required class="w-full border rounded-xl p-2.5"></div>
                    <div><label class="block font-semibold mb-1 text-slate-700">Receipt / Reference No.</label><input type="text" name="receipt_number" value="${escapeHtml(payment.receipt_number)}" class="w-full border rounded-xl p-2.5"></div>
                    <div><label class="block font-semibold mb-1 text-slate-700">Remarks</label><input type="text" name="remarks" value="${escapeHtml(payment.remarks)}" class="w-full border rounded-xl p-2.5"></div>
                    <div class="flex justify-end gap-2 pt-2 border-t"><button type="button" onclick="closeActiveModal()" class="px-4 py-2 bg-slate-100 rounded-xl font-semibold">Cancel</button><button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-xl font-bold">Update Receipt</button></div>
                </form>
            </div>
        </div>`;
    document.getElementById('editAgentPaymentForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const response = await fetch('index.php?api=update_agent_payment', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]')?.content || ''}, body:JSON.stringify(Object.fromEntries(new FormData(event.target).entries()))});
        const result = await response.json();
        if (result.success) { closeActiveModal(); window.location.reload(); } else alert(result.message || 'Unable to update payment receipt.');
    });
}

async function deleteAgentPayment(paymentId, agentId) {
    if (!confirm('Delete this payment receipt? This will reduce the agent credit balance.')) return;
    const response = await fetch('index.php?api=delete_agent_payment', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]')?.content || ''}, body:JSON.stringify({payment_id:paymentId, agent_id:agentId})});
    const result = await response.json();
    if (result.success) window.location.reload(); else alert(result.message || 'Unable to delete payment receipt.');
}

function openAgentAdjustmentModal(agentId, agentName) {
    let modal = document.getElementById('dynamicModalContainer');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'dynamicModalContainer';
        document.body.appendChild(modal);
    }

    const today = new Date().toISOString().split('T')[0];
    modal.innerHTML = `
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Add Amount to Ledger</h3>
                        <p class="text-[11px] text-slate-500">Agent: <strong>${escapeHtml(agentName)}</strong></p>
                    </div>
                    <button type="button" onclick="closeActiveModal()" class="text-slate-400 hover:text-slate-600 transition">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>
                <form id="agentAdjustmentForm" onsubmit="submitAgentAdjustment(event, ${agentId})" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Entry Date</label>
                        <input type="date" id="adjustment_date" value="${today}" required class="w-full border border-slate-300 rounded-xl p-2.5 font-mono outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Amount (SAR)</label>
                        <input type="number" min="0.01" step="0.01" id="adjustment_amount" placeholder="e.g. 250" required class="w-full border border-slate-300 rounded-xl p-2.5 font-mono font-bold text-amber-700 outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Reason</label>
                        <input type="text" maxlength="255" id="adjustment_reason" placeholder="e.g. Previous pending amount, fee, minor service" required class="w-full border border-slate-300 rounded-xl p-2.5 outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    <div class="bg-amber-50 border border-amber-200 p-3 rounded-xl text-amber-900 text-[11px]">
                        This amount will be added as a debit to the agent's receivable balance.
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <button type="button" onclick="closeActiveModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition">Cancel</button>
                        <button type="submit" id="btnSubmitAgentAdjustment" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-md">
                            <i class="fa-solid fa-plus mr-1.5"></i> Save Amount
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
}

async function submitAgentAdjustment(e, agentId) {
    e.preventDefault();
    e.stopPropagation();
    const submitButton = document.getElementById('btnSubmitAgentAdjustment');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i> Saving...';
    }

    try {
        const response = await fetch('index.php?api=save_agent_adjustment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                agent_id: agentId,
                adjustment_date: document.getElementById('adjustment_date').value,
                amount_sar: document.getElementById('adjustment_amount').value,
                reason: document.getElementById('adjustment_reason').value
            })
        });
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Unable to save amount.');
        closeActiveModal();
        window.location.reload();
    } catch (error) {
        alert(error.message || 'Network or server error.');
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fa-solid fa-plus mr-1.5"></i> Save Amount';
        }
    }
}

// Modal: Disburse Payment to Supplier (Accounts Payable)
function openVendorPaymentModal(vendorId, vendorName) {
    let modal = document.getElementById('dynamicModalContainer');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'dynamicModalContainer';
        document.body.appendChild(modal);
    }

    const today = new Date().toISOString().split('T')[0];

    modal.innerHTML = `
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm">Disburse Supplier Payment</h3>
                        <p class="text-[11px] text-slate-500">Supplier: <strong>${escapeHtml(vendorName)}</strong></p>
                    </div>
                    <button type="button" onclick="closeActiveModal()" class="text-slate-400 hover:text-slate-600 transition">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>
                <form id="vendorPaymentForm" onsubmit="submitVendorDisbursement(event, ${vendorId})" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Disbursement Amount (SAR)</label>
                        <input type="number" step="1" id="vmodal_amount_sar" placeholder="e.g. 2000" required class="w-full border border-slate-300 rounded-xl p-2.5 font-mono font-bold text-cyan-800 outline-none focus:ring-2 focus:ring-cyan-600">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Disbursement Mode</label>
                        <select id="vmodal_payment_mode" class="w-full border border-slate-300 rounded-xl p-2.5 bg-slate-50 font-medium focus:bg-white outline-none focus:ring-2 focus:ring-cyan-600">
                            <option value="Direct Bank Transfer">Direct Bank Transfer</option>
                            <option value="Cash / Hand-to-Hand">Cash / Hand-to-Hand</option>
                            <option value="Online Al-Rajhi Transfer">Online Al-Rajhi Transfer</option>
                            <option value="Third-Party Remittance">Third-Party Remittance</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Payment Date</label>
                            <input type="date" id="vmodal_payment_date" value="${today}" required class="w-full border border-slate-300 rounded-xl p-2.5 font-mono outline-none focus:ring-2 focus:ring-cyan-600">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1 text-slate-700">Transaction Ref #</label>
                            <input type="text" id="vmodal_ref_number" placeholder="e.g. TXN-8902" class="w-full border border-slate-300 rounded-xl p-2.5 font-mono outline-none focus:ring-2 focus:ring-cyan-600">
                        </div>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-700">Remarks / Notes</label>
                        <input type="text" id="vmodal_remarks" placeholder="Optional transaction notes..." class="w-full border border-slate-300 rounded-xl p-2.5 outline-none focus:ring-2 focus:ring-cyan-600">
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <button type="button" onclick="closeActiveModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition">
                            Cancel
                        </button>
                        <button type="submit" id="btnSubmitVendorPay" class="bg-cyan-700 hover:bg-cyan-800 text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-md">
                            <i class="fa-solid fa-paper-plane mr-1.5"></i> Commit Supplier Disbursement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
}

// API Submission for Vendor Disbursement
async function submitVendorDisbursement(e, vendorId) {
    e.preventDefault();
    e.stopPropagation();

    const submitBtn = document.getElementById('btnSubmitVendorPay');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i> Processing...';
    }

    const payload = {
        vendor_id: vendorId,
        amount_sar: Math.round(parseFloat(document.getElementById('vmodal_amount_sar').value) || 0),
        payment_mode: document.getElementById('vmodal_payment_mode').value,
        payment_date: document.getElementById('vmodal_payment_date').value,
        reference_number: document.getElementById('vmodal_ref_number').value,
        remarks: document.getElementById('vmodal_remarks').value
    };

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch('index.php?api=save_vendor_payment', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        if (data.success) {
            closeActiveModal();
            window.location.reload();
        } else {
            alert(data.message || 'Error recording vendor payment.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane mr-1.5"></i> Commit Supplier Disbursement';
            }
        }
    } catch (err) {
        alert('Network or Server Error: ' + err.message);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane mr-1.5"></i> Commit Supplier Disbursement';
        }
    }
}

function closeActiveModal() {
    const modal = document.getElementById('dynamicModalContainer');
    if (modal) modal.innerHTML = '';
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#26206F;' };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}
