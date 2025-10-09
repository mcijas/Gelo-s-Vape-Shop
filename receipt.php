<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt Preview</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            width: 280px;
            margin: 20px auto;
            border: 1px dashed #aaa;
            padding: 10px;
            background: #fff;
            color: #000;
            font-size: 11px;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            margin-bottom: 8px;
        }
        .header .logo {
            display: block;
            width: 40px;
            height: auto;
            margin: 0 auto 3px;
        }
        .header .store {
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 2px;
        }
        .header .details {
            font-size: 10px;
            line-height: 1.2;
            white-space: pre-line;
        }
        h2 { 
            text-align: center; 
            margin: 5px 0; 
            font-size: 14px;
        }
        .line { 
            border-top: 1px dashed #999; 
            margin: 8px 0; 
        }
        .separator {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .separator-double {
            border-top: 2px solid #000;
            margin: 6px 0;
        }
        .meta {
            font-size: 10px;
            margin: 6px 0;
        }
        .meta .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1px;
        }
        .items-header {
            display: flex;
            font-weight: 700;
            font-size: 10px;
            margin-bottom: 3px;
        }
        .items-header .col-item { flex: 1; }
        .items-header .col-qty { width: 30px; text-align: center; }
        .items-header .col-price { width: 60px; text-align: right; }
        .item-row {
            display: flex;
            font-size: 10px;
            margin-bottom: 2px;
        }
        .item-row .item-name { flex: 1; }
        .item-row .item-qty { width: 30px; text-align: center; }
        .item-row .item-price { width: 60px; text-align: right; }
        .totals {
            margin-top: 6px;
            font-size: 10px;
        }
        .totals .row {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
        }
        .totals .grand {
            font-weight: 700;
            font-size: 12px;
            margin-top: 3px;
        }
        .total { 
            text-align: right; 
            font-weight: bold; 
            margin-top: 8px;
        }
        .footer {
            text-align: center;
            margin-top: 8px;
            font-size: 10px;
        }
        .print-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        .btn {
            flex: 1;
            border: 1px solid #000;
            background: #fff;
            padding: 6px 8px;
            cursor: pointer;
            font-size: 11px;
        }
        @page { 
            margin: 2mm;
            size: 58mm auto;
        }
        @media print { 
            .print-actions { display: none !important; }
            body { 
                font-size: 10px;
                border: none;
                margin: 0;
                padding: 2mm 1mm;
                width: auto;
            }
        }
        
        /* Dynamic width support */
        .receipt-80mm {
            width: 300px;
        }
        .receipt-80mm .item-row .item-name { flex: 1.5; }
        .receipt-80mm .item-row .item-qty { width: 40px; }
        .receipt-80mm .item-row .item-price { width: 80px; }
        .receipt-80mm .items-header .col-item { flex: 1.5; }
        .receipt-80mm .items-header .col-qty { width: 40px; }
        .receipt-80mm .items-header .col-price { width: 80px; }
        
        @media print {
            .receipt-80mm {
                width: auto;
            }
        }
        
        @page.receipt-80mm {
            size: 80mm auto;
        }
    </style>
</head>
<body>
    <div class="receipt" id="receiptContainer">
        <div class="header">
            <img class="logo" src="gelo.png" alt="Logo" />
            <div class="store">Gelo's Vape Shop</div>
            <div class="details">Sample Address Line 1
Sample Phone Number
sample@email.com</div>
        </div>
        
        <div class="separator"></div>
        
        <div class="meta">
            <div class="row"><span>Date:</span><span><script>document.write(new Date().toLocaleDateString('en-CA'));</script></span></div>
            <div class="row"><span>Time:</span><span><script>document.write(new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }));</script></span></div>
            <div class="row"><span>Receipt:</span><span>#TEST-001</span></div>
            <div class="row"><span>Cashier:</span><span>Test User</span></div>
        </div>
        
        <div class="separator"></div>
        
        <div class="items-header">
            <span class="col-item">Item</span>
            <span class="col-qty">Qty</span>
            <span class="col-price">Price</span>
        </div>
        
        <div class="separator"></div>
        
        <div class="items">
            <div class="item-row">
                <span class="item-name">GeekVape Aegis Pod 2</span>
                <span class="item-qty">1</span>
                <span class="item-price">₱1,100.00</span>
            </div>
            <div class="item-row">
                <span class="item-name">NeXim Disposable</span>
                <span class="item-qty">2</span>
                <span class="item-price">₱700.00</span>
            </div>
            <div class="item-row">
                <span class="item-name">RELX Pod Pro Infinity</span>
                <span class="item-qty">1</span>
                <span class="item-price">₱190.00</span>
            </div>
        </div>
        
        <div class="separator-double"></div>
        
        <div class="totals">
            <div class="row"><span>Subtotal:</span><span>₱1,990.00</span></div>
            <div class="row grand"><span>TOTAL:</span><span>₱1,990.00</span></div>
        </div>
        
        <div class="separator"></div>
        
        <div class="payment-info" style="margin: 6px 0; font-size: 10px; text-align: center;">
            Payment: Cash
        </div>
        
        <div class="separator"></div>
        
        <div class="footer">
            Thank you for shopping!<br>
            Come back soon!
        </div>
        
        <div class="print-actions">
            <button class="btn" onclick="window.close()">Close</button>
            <button class="btn" onclick="window.print()">Print Receipt</button>
        </div>
    </div>

    <script>
        // Apply receipt width based on URL parameter or localStorage
        function applyReceiptWidth() {
            const urlParams = new URLSearchParams(window.location.search);
            const widthParam = urlParams.get('width');
            const savedWidth = localStorage.getItem('pos_receipt_width');
            const receiptWidth = widthParam || savedWidth || '58mm';
            
            const container = document.getElementById('receiptContainer');
            const body = document.body;
            
            if (receiptWidth === '80mm') {
                body.classList.add('receipt-80mm');
                container.classList.add('receipt-80mm');
            } else {
                body.classList.remove('receipt-80mm');
                container.classList.remove('receipt-80mm');
            }
        }
        

        // Load transaction data and populate receipt
        function loadTransactionData() {
            const urlParams = new URLSearchParams(window.location.search);
            const txnId = urlParams.get('txn_id');
            const isTest = urlParams.get('test') === 'true';
            
            if (isTest) {
                // Keep the default test data that's already in the HTML
                return;
            }
            
            if (txnId) {
                // Try to get transaction data from sessionStorage
                const receiptData = sessionStorage.getItem('current_receipt_data');
                if (receiptData) {
                    try {
                        const txn = JSON.parse(receiptData);
                        populateReceipt(txn);
                        // Clear the session data after use
                        sessionStorage.removeItem('current_receipt_data');
                    } catch (e) {
                        console.error('Error parsing receipt data:', e);
                    }
                }
            }
        }
        
        // Populate receipt with transaction data
        function populateReceipt(txn) {
            const fmt = (n) => `₱${Number(n||0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const d = new Date(txn.date);
            const dateStr = d.toLocaleDateString('en-CA');
            const timeStr = d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
            const subtotal = (txn.items||[]).reduce((s,it)=> s + (Number(it.price||0)*Number(it.qty||0)), 0);
            const discount = Number(txn.discount || 0);
            const grand = Math.max(0, subtotal - discount);
            
            // Update meta information
            document.querySelector('.meta .row:nth-child(1) span:last-child').textContent = dateStr;
            document.querySelector('.meta .row:nth-child(2) span:last-child').textContent = timeStr;
            document.querySelector('.meta .row:nth-child(3) span:last-child').textContent = '#' + (txn.ref || txn.id || '');
            document.querySelector('.meta .row:nth-child(4) span:last-child').textContent = txn.cashier || '-';
            
            // Update items
            const itemsContainer = document.querySelector('.items');
            itemsContainer.innerHTML = (txn.items||[]).map(it => `
                <div class="item-row">
                    <span class="item-name">${it.product}</span>
                    <span class="item-qty">${Number(it.qty||0)}</span>
                    <span class="item-price">${fmt((Number(it.qty)||0)*(Number(it.price)||0))}</span>
                </div>
            `).join('');
            
            // Update totals
            document.querySelector('.totals .row:nth-child(1) span:last-child').textContent = fmt(subtotal);
            if (discount > 0) {
                const discountRow = document.createElement('div');
                discountRow.className = 'row';
                discountRow.innerHTML = `<span>Discount:</span><span>-${fmt(discount)}</span>`;
                document.querySelector('.totals .grand').parentNode.insertBefore(discountRow, document.querySelector('.totals .grand'));
            }
            document.querySelector('.totals .grand span:last-child').textContent = fmt(grand);
            
            // Update payment method
            document.querySelector('.payment-info').textContent = `Payment: ${txn.paymentMethod}`;
            
            // Update store info if available
            try {
                const storeInfo = JSON.parse(localStorage.getItem('store_info') || '{}');
                if (storeInfo.name) {
                    document.querySelector('.header .store').textContent = storeInfo.name;
                }
                if (storeInfo.address || storeInfo.phone || storeInfo.email) {
                    const details = [storeInfo.address, storeInfo.phone, storeInfo.email].filter(Boolean).join('\n');
                    document.querySelector('.header .details').textContent = details;
                }
            } catch (e) {}
        }
        
        // Apply width on page load
        document.addEventListener('DOMContentLoaded', function() {
            applyReceiptWidth();
            loadTransactionData();
        });
        
        // Auto-print if requested via URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autoprint') === 'true') {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        }
    </script>
</body>
</html>