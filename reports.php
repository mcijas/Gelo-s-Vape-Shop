<?php
// Set Manila timezone for PHP and MySQL
date_default_timezone_set('Asia/Manila');

// Include database connection and set MySQL timezone
require_once __DIR__ . '/api/db.php';

try {
    // Try to set MySQL session timezone by name
    $pdo->exec("SET time_zone = 'Asia/Manila'");
} catch (Throwable $e) {
    // Fallback to numeric offset if timezone tables not available
    $pdo->exec("SET time_zone = '+08:00'");
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <title>Reports Overview - Dashboard</title>
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="inventory.css" />
    <link rel="stylesheet" href="reports.css" />
    <link rel="stylesheet" href="style.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  </head>
  <body>
    <div class="container">
      <aside class="sidebar">
        <div class="sidebar-top">
          <div class="brand"><img src="gelo.png" alt="Gelo's Vape Shop logo" /></div>
          <nav class="nav">
            <ul class="nav-list">
              <li><a href="Pages/dashboard.html"><span class="icon">🏠</span><span class="label">Dashboard</span></a></li>
              <li class="has-submenu">
                <a href="Pages/inventory.html"><span class="icon">📦</span><span class="label">Inventory</span></a>
                <ul class="submenu">
                  <li><a href="product_list.html">Product List</a></li>
                </ul>
              </li>
              <li><a href="Pages/pos.html"><span class="icon">🧾</span><span class="label">POS</span></a></li>
              <li><a href="customers.html"><span class="icon">👥</span><span class="label">Customer</span></a></li>
              <li><a href="suppliers.html"><span class="icon">🚚</span><span class="label">Suppliers</span></a></li>
              <li class="has-submenu">
                <a href="reports.php" class="active"><span class="icon">📊</span><span class="label">Reports</span></a>
                <ul class="submenu">
                  <li><a href="#" class="report-link" data-section="sales-reports">Sales Reports</a></li>
                  <li><a href="#" class="report-link" data-section="inventory-reports">Inventory Reports</a></li>
                  <li><a href="#" class="report-link" data-section="financial-reports">Financial Reports</a></li>
                  <li><a href="#" class="report-link" data-section="supplier-reports">Supplier Reports</a></li>
                  <li><a href="#" class="report-link" data-section="customer-reports">Customer Reports</a></li>
                  <li><a href="#" class="report-link" data-section="operational-reports">Operational Reports</a></li>
                </ul>
              </li>
              <li><a href="settings.html"><span class="icon">⚙️</span><span class="label">Settings</span></a></li>
            </ul>
          </nav>
        </div>
        <a class="logout" href="index.html"><span class="icon">⎋</span><span class="label">Logout</span></a>
      </aside>

      <main class="main-content">
        <header class="page-header">
          <div class="export-actions">
            <button class="btn export" data-export="csv">Export CSV</button>
            <button class="btn export" data-export="xlsx">Export Excel</button>
            <button class="btn export" data-export="pdf">Export PDF</button>
          </div>
        </header>

        <section class="reports-toolbar" aria-label="Filters">
          <div class="range">
            <label for="fromDate">From</label>
            <input id="fromDate" type="date" />
          </div>
          <div class="range">
            <label for="toDate">To</label>
            <input id="toDate" type="date" />
          </div>
          <div class="range">
            <label for="preset">Preset</label>
            <select id="preset">
              <option value="custom">Custom</option>
              <option value="today">Today</option>
              <option value="yesterday">Yesterday</option>
              <option value="this_week">This Week</option>
              <option value="this_month">This Month</option>
              <option value="7">Last 7 days</option>
              <option value="30" selected>Last 30 days</option>
            </select>
          </div>
          <button class="btn apply" id="applyDateFilter">Apply</button>
        </section>

        <section class="report-section" id="sales-reports">
          <h2 class="section-title">Sales Reports</h2>
          <div class="cards">
            <div class="report-card"><div class="label">Total Revenue</div><div class="value" id="revTotal">₱0.00</div></div>
            <div class="report-card"><div class="label">Transactions</div><div class="value" id="txnCount">0</div></div>
            <div class="report-card"><div class="label">Avg. Order Value</div><div class="value" id="aov">₱0.00</div></div>
          </div>
          <div class="two-col">
            <div class="card-table">
              <header>Sales by Product</header>
              <table>
                <thead><tr><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead>
                <tbody id="salesByProduct"><tr><td colspan="3">No data</td></tr></tbody>
              </table>
            </div>
            <div class="card-table">
              <header>Sales by Category</header>
              <table>
                <thead><tr><th>Category</th><th>Qty</th><th>Revenue</th></tr></thead>
                <tbody id="salesByCategory"><tr><td colspan="3">No data</td></tr></tbody>
              </table>
            </div>
          </div>
          <div class="two-col">
            <div class="card-table">
              <header>Sales by Employee / Cashier</header>
              <table>
                <thead><tr><th>Employee</th><th>Transactions</th><th>Revenue</th></tr></thead>
                <tbody id="salesByEmployee"><tr><td colspan="3">No data</td></tr></tbody>
              </table>
            </div>
            <div class="card-table">
              <header>Payment Method Breakdown</header>
              <table>
                <thead><tr><th>Method</th><th>Transactions</th><th>Revenue</th></tr></thead>
                <tbody id="paymentBreakdown"><tr><td colspan="3">No data</td></tr></tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="report-section" id="inventory-reports">
          <h2 class="section-title">Inventory Reports</h2>
          <div class="two-col">
            <div class="card-table">
              <header>Stock Levels</header>
              <table>
                <thead><tr><th>Product</th><th>Stock</th><th>Reorder Point</th></tr></thead>
                <tbody id="stockLevels"><tr><td colspan="3">No data</td></tr></tbody>
              </table>
            </div>
            <div class="card-table">
              <header>Low Stock Alerts</header>
              <table>
                <thead><tr><th>Product</th><th>Stock</th><th>Needed</th></tr></thead>
                <tbody id="lowStock"><tr><td colspan="3">No data</td></tr></tbody>
              </table>
            </div>
          </div>
          <div class="two-col">
            <div class="card-table">
              <header>Inventory Valuation</header>
              <table>
                <thead><tr><th>Category</th><th>Units</th><th>Cost Value</th></tr></thead>
                <tbody id="inventoryValuation"><tr><td colspan="3">No data</td></tr></tbody>
              </table>
            </div>
            <div class="card-table">
              <header>Stock Movement</header>
              <table>
                <thead><tr><th>Date</th><th>Product</th><th>Category</th><th>Type</th><th>Qty</th></tr></thead>
                <tbody id="stockMovement"><tr><td colspan="5">No data</td></tr></tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="report-section" id="financial-reports">
          <h2 class="section-title">Financial Reports</h2>
          <div class="cards">
            <div class="report-card"><div class="label">Profit & Loss</div><div class="value" id="pnl">₱0.00</div></div>
            <div class="report-card"><div class="label">Tax Collected</div><div class="value" id="taxCollected">₱0.00</div></div>
            <div class="report-card"><div class="label">Refunds & Discounts</div><div class="value" id="refundsDiscounts">₱0.00</div></div>
          </div>
          <div class="two-col">
            <div class="card-table">
              <header>Tax Reports</header>
              <table>
                <thead><tr><th>Tax Type</th><th>Base</th><th>Tax</th></tr></thead>
                <tbody id="taxReports"><tr><td colspan="3">No data</td></tr></tbody>
              </table>
            </div>
            <div class="card-table">
              <header>Refunds & Discounts</header>
              <table>
                <thead><tr><th>Type</th><th>Count</th><th>Amount</th></tr></thead>
                <tbody id="refundsTable"><tr><td colspan="3">No data</td></tr></tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="report-section" id="supplier-reports">
          <h2 class="section-title">Supplier Reports</h2>
          <div class="cards">
            <div class="report-card"><div class="label">Total Suppliers</div><div class="value" id="totalSuppliers">0</div></div>
            <div class="report-card"><div class="label">Active Suppliers</div><div class="value" id="activeSuppliers">0</div></div>
            <div class="report-card"><div class="label">Total Spent</div><div class="value" id="totalSpentSuppliers">₱0</div></div>
          </div>
          <div class="two-col">
            <div class="card-table">
              <header>Spending by Supplier</header>
              <table>
                <thead><tr><th>Supplier</th><th>Categories</th><th>Orders</th><th>Total Spent</th></tr></thead>
                <tbody id="spendingBySupplier"><tr><td colspan="4">No data</td></tr></tbody>
              </table>
            </div>
            <div class="card-table">
              <header>Spending by Category</header>
              <table>
                <thead><tr><th>Category</th><th>Suppliers</th><th>Products</th><th>Total Spent</th></tr></thead>
                <tbody id="spendingByCategory"><tr><td colspan="4">No data</td></tr></tbody>
              </table>
            </div>
          </div>
          <div class="card-table">
            <header>Supplier Performance</header>
            <table>
              <thead><tr><th>Supplier</th><th>Categories</th><th>Avg Order Value</th><th>Order Frequency</th><th>Quality Rating</th></tr></thead>
              <tbody id="supplierPerformance"><tr><td colspan="5">No data</td></tr></tbody>
            </table>
          </div>
        </section>

        <section class="report-section" id="customer-reports">
          <h2 class="section-title">Customer Reports</h2>
          <div class="two-col">
            <div class="card-table">
              <header>Purchase History</header>
              <table>
                <thead><tr><th>Date</th><th>Customer</th><th>Items</th><th>Total</th></tr></thead>
                <tbody id="purchaseHistory"><tr><td colspan="4">No data</td></tr></tbody>
              </table>
            </div>
            <div class="card-table">
              <header>Loyalty Points / Rewards</header>
              <table>
                <thead><tr><th>Customer</th><th>Points</th><th>Redeemed</th></tr></thead>
                <tbody id="loyalty"><tr><td colspan="3">No data</td></tr></tbody>
              </table>
            </div>
          </div>
          <div class="card-table">
            <header>Customer Demographics</header>
            <table>
              <thead><tr><th>Segment</th><th>Customers</th><th>Share</th></tr></thead>
              <tbody id="demographics"><tr><td colspan="3">No data</td></tr></tbody>
            </table>
          </div>
        </section>

        <section class="report-section" id="operational-reports">
          <h2 class="section-title">Operational Reports</h2>
          <div class="two-col">
            <div class="card-table">
              <header>Shift Reports / Z-Reports</header>
              <table>
                <thead><tr><th>Shift</th><th>Cash Count</th><th>Sales</th></tr></thead>
                <tbody id="shiftReports"><tr><td colspan="3">No data</td></tr></tbody>
              </table>
            </div>
            <div class="card-table">
              <header>Void / Cancelled Transactions</header>
              <table>
                <thead><tr><th>Date</th><th>Txn ID</th><th>Employee</th><th>Reason</th></tr></thead>
                <tbody id="voids"><tr><td colspan="4">No data</td></tr></tbody>
              </table>
            </div>
          </div>
          <div class="card-table">
            <header>Staff Hours</header>
            <table>
              <thead><tr><th>Employee</th><th>Hours</th><th>Shifts</th></tr></thead>
              <tbody id="staffHours"><tr><td colspan="3">No data</td></tr></tbody>
            </table>
          </div>
        </section>

        <style>
          /* Submenu hover styles */
          .sidebar .has-submenu .submenu {
            display: none;
            padding-left: 20px;
            margin-top: 5px;
            max-height: 400px;
            overflow-y: auto;
          }
          
          .sidebar .has-submenu:hover .submenu {
            display: block;
          }
          
          .sidebar .submenu li {
            margin-bottom: 8px;
          }
          
          .sidebar .submenu a {
            color: #a3a3a3;
            font-size: 16px;
            transition: color 0.2s;
          }
          
          .sidebar .submenu a:hover,
          .sidebar .submenu a.active {
            color: #ffffff;
          }
          
          /* Hide all report sections by default */
          .report-section {
            display: none;
          }
          
          /* Show active report section */
          .report-section.active {
            display: block;
          }
        </style>
        
        <script>
          (function(){
            const preset = document.getElementById('preset');
            const from = document.getElementById('fromDate');
            const to = document.getElementById('toDate');
            const apply = document.getElementById('applyDateFilter');

            preset.addEventListener('change', () => {
              const today = new Date();
              const yesterday = new Date(today);
              yesterday.setDate(yesterday.getDate() - 1);
              const weekStart = new Date(today);
              weekStart.setDate(weekStart.getDate() - today.getDay());
              const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);

              const yyyy = (d) => d.getFullYear();
              const mm = (d) => String(d.getMonth() + 1).padStart(2, '0');
              const dd = (d) => String(d.getDate()).padStart(2, '0');
              const formatDate = (d) => `${yyyy(d)}-${mm(d)}-${dd(d)}`;

              switch (preset.value) {
                case 'today':
                  from.value = formatDate(today);
                  to.value = formatDate(today);
                  break;
                case 'yesterday':
                  from.value = formatDate(yesterday);
                  to.value = formatDate(yesterday);
                  break;
                case 'this_week':
                  from.value = formatDate(weekStart);
                  to.value = formatDate(today);
                  break;
                case 'this_month':
                  from.value = formatDate(monthStart);
                  to.value = formatDate(today);
                  break;
                case '7':
                  from.value = formatDate(new Date(today.getTime() - 6 * 24 * 60 * 60 * 1000));
                  to.value = formatDate(today);
                  break;
                case '30':
                  from.value = formatDate(new Date(today.getTime() - 29 * 24 * 60 * 60 * 1000));
                  to.value = formatDate(today);
                  break;
              }
            });

            apply.addEventListener('click', renderAll);
            
            // Report section navigation
            const reportLinks = document.querySelectorAll('.report-link');
            const reportSections = document.querySelectorAll('.report-section');
            
            // Show only sales reports by default
            showReportSection('sales-reports');
            
            // Add click event to report links
            reportLinks.forEach(link => {
              link.addEventListener('click', (e) => {
                e.preventDefault();
                const sectionId = link.getAttribute('data-section');
                showReportSection(sectionId);
                
                // Update active state in submenu
                reportLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
              });
            });
            
            // Function to show only the selected report section
            function showReportSection(sectionId) {
              reportSections.forEach(section => {
                if (section.id === sectionId) {
                  section.classList.add('active');
                } else {
                  section.classList.remove('active');
                }
              });
            }
            
            // Export buttons functionality
            document.querySelectorAll('.export').forEach(btn => {
              btn.addEventListener('click', () => {
                const type = btn.getAttribute('data-export');
                const activeSection = document.querySelector('.report-section.active');
                const sectionTitle = activeSection ? activeSection.querySelector('.section-title').textContent : 'Reports';
                
                // Get the data from the active section
                const tables = activeSection ? activeSection.querySelectorAll('table') : [];
                let exportData = [];
                
                tables.forEach(table => {
                  const tableHeader = table.closest('.card-table').querySelector('header').textContent;
                  const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent);
                  const rows = Array.from(table.querySelectorAll('tbody tr')).map(tr => {
                    return Array.from(tr.querySelectorAll('td')).map(td => td.textContent);
                  });
                  
                  if (rows.length > 0 && !rows[0][0].includes('No data')) {
                    exportData.push({ title: tableHeader, headers, rows });
                  }
                });
                
                // Export based on type
                if (exportData.length > 0) {
                  if (type === 'csv') {
                    exportAsCSV(exportData, sectionTitle);
                  } else if (type === 'xlsx') {
                    exportAsExcel(exportData, sectionTitle);
                  } else if (type === 'pdf') {
                    exportAsPDF(exportData, sectionTitle);
                  }
                } else {
                  alert('No data to export');
                }
              });
            });
            
            // Function to export as CSV
            function exportAsCSV(data, sectionTitle) {
              let csvContent = '';
              
              data.forEach(table => {
                csvContent += table.title + '\n';
                csvContent += table.headers.join(',') + '\n';
                
                table.rows.forEach(row => {
                  csvContent += row.map(cell => `"${cell}"`).join(',') + '\n';
                });
                
                csvContent += '\n';
              });
              
              const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
              const url = URL.createObjectURL(blob);
              const link = document.createElement('a');
              link.setAttribute('href', url);
              link.setAttribute('download', `${sectionTitle.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0, 10)}.csv`);
              link.style.visibility = 'hidden';
              document.body.appendChild(link);
              link.click();
              document.body.removeChild(link);
            }

            // Function to export as Excel using SheetJS
            function exportAsExcel(data, sectionTitle) {
              const wb = XLSX.utils.book_new();
              
              data.forEach(table => {
                const wsData = [table.headers, ...table.rows];
                const ws = XLSX.utils.aoa_to_sheet(wsData);
                
                // Add some styling
                const range = XLSX.utils.decode_range(ws['!ref']);
                for (let R = range.s.r; R <= range.e.r; ++R) {
                  for (let C = range.s.c; C <= range.e.c; ++C) {
                    const cell = ws[XLSX.utils.encode_cell({ r: R, c: C })];
                    if (!cell) continue;
                    
                    // Style header row
                    if (R === 0) {
                      cell.s = {
                        font: { bold: true },
                        fill: { fgColor: { rgb: "E2E8F0" } },
                        alignment: { horizontal: "center" }
                      };
                    }
                    
                    // Style currency columns (assuming last column is currency)
                    if (C === range.e.c && R > 0) {
                      cell.s = cell.s || {};
                      cell.s.numFmt = '"₱"#,##0.00';
                      cell.s.alignment = { horizontal: "right" };
                    }
                  }
                }
                
                // Auto-size columns
                const colWidths = [];
                for (let C = range.s.c; C <= range.e.c; ++C) {
                  let maxWidth = 0;
                  for (let R = range.s.r; R <= range.e.r; ++R) {
                    const cell = ws[XLSX.utils.encode_cell({ r: R, c: C })];
                    if (cell && cell.v) {
                      maxWidth = Math.max(maxWidth, String(cell.v).length);
                    }
                  }
                  colWidths.push({ wch: Math.min(Math.max(maxWidth + 2, 10), 50) });
                }
                ws['!cols'] = colWidths;
                
                const safeSheetName = table.title.replace(/[:\\/?*[\]]/g, '_').substring(0, 31);
                 XLSX.utils.book_append_sheet(wb, ws, safeSheetName);
              });
              
              const filename = `${sectionTitle.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0, 10)}.xlsx`;
             XLSX.writeFile(wb, filename);
           }

           // Function to export as PDF using jsPDF
           function exportAsPDF(data, sectionTitle) {
             const { jsPDF } = window.jspdf;
             const doc = new jsPDF();
             
             // Set font and colors
             doc.setFont('helvetica');
             
             let yPosition = 20;
             const pageWidth = doc.internal.pageSize.getWidth();
             
             data.forEach((table, tableIndex) => {
               // Add table title
               doc.setFontSize(16);
               doc.setFont(undefined, 'bold');
               doc.setTextColor(41, 128, 185);
               doc.text(table.title, pageWidth / 2, yPosition, { align: 'center' });
               yPosition += 10;
               
               // Add report section title
               doc.setFontSize(12);
               doc.setFont(undefined, 'normal');
               doc.setTextColor(100, 100, 100);
               doc.text(`Report: ${sectionTitle}`, pageWidth / 2, yPosition, { align: 'center' });
               yPosition += 10;
               
               // Add date
               doc.setFontSize(10);
               doc.setTextColor(150, 150, 150);
               const today = new Date().toLocaleDateString();
               doc.text(`Generated: ${today}`, pageWidth / 2, yPosition, { align: 'center' });
               yPosition += 15;
               
               // Calculate column widths
               const tableWidth = pageWidth - 20; // 10px margin on each side
               const colCount = table.headers.length;
               const colWidth = tableWidth / colCount;
               
               // Add headers
               doc.setFontSize(10);
               doc.setFont(undefined, 'bold');
               doc.setTextColor(255, 255, 255);
               doc.setFillColor(41, 128, 185);
               
               table.headers.forEach((header, index) => {
                 const xPos = 10 + (index * colWidth);
                 doc.rect(xPos, yPosition - 5, colWidth, 8, 'F');
                 doc.text(header, xPos + 2, yPosition + 1);
               });
               yPosition += 8;
               
               // Add data rows
               doc.setFont(undefined, 'normal');
               doc.setTextColor(50, 50, 50);
               
               table.rows.forEach((row, rowIndex) => {
                 // Check if we need a new page
                 if (yPosition > 280) {
                   doc.addPage();
                   yPosition = 20;
                 }
                 
                 // Alternate row colors
                 const fillColor = rowIndex % 2 === 0 ? [245, 245, 245] : [255, 255, 255];
                 doc.setFillColor(...fillColor);
                 
                 row.forEach((cell, cellIndex) => {
                   const xPos = 10 + (cellIndex * colWidth);
                   doc.rect(xPos, yPosition - 4, colWidth, 6, 'F');
                   
                   // Handle currency formatting (assuming last column)
                   let displayText = cell;
                   if (cellIndex === row.length - 1 && cell.includes('₱')) {
                     displayText = cell.replace('₱', 'Php ');
                   }
                   
                   // Truncate text if too long
                   if (displayText.length > 15) {
                     displayText = displayText.substring(0, 12) + '...';
                   }
                   
                   doc.text(displayText, xPos + 2, yPosition + 1);
                 });
                 yPosition += 6;
               });
               
               yPosition += 10; // Space between tables
             });
             
             // Add footer
             const totalPages = doc.internal.getNumberOfPages();
             for (let i = 1; i <= totalPages; i++) {
               doc.setPage(i);
               doc.setFontSize(8);
               doc.setTextColor(150, 150, 150);
               doc.text(`Page ${i} of ${totalPages}`, pageWidth / 2, 290, { align: 'center' });
             }
             
             const filename = `${sectionTitle.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0, 10)}.pdf`;
             doc.save(filename);
           }

            async function getStockMovements() {
              try {
                const res = await fetch('api/stock_movements.php', { method: 'GET' });
                const data = await res.json();
                if (res.ok && data.ok) return data.data || [];
              } catch {}
              try { return JSON.parse(localStorage.getItem('stockMovements') || '[]'); } catch { return []; }
            }
            async function getTransactions() {
              try {
                const res = await fetch('api/transactions.php', { method: 'GET' });
                const data = await res.json();
                if (res.ok && data.ok) return data.data || [];
              } catch {}
              try { return JSON.parse(localStorage.getItem('transactions') || '[]'); } catch { return []; }
            }
            function inRange(iso, startISO, endISO) {
              if (!startISO && !endISO) return true;
              const d = new Date(iso);
              const s = startISO ? new Date(`${startISO}T00:00:00`) : null;
              const e = endISO ? new Date(`${endISO}T23:59:59.999`) : null;
              return (!s || d >= s) && (!e || d <= e);
            }
            function fmtPeso(n) { return '₱' + Number(n || 0).toLocaleString(); }

            function renderStockMovement(rows) {
              const body = document.getElementById('stockMovement');
              if (!body) return;
              if (!rows.length) { body.innerHTML = '<tr><td colspan="5">No data</td></tr>'; return; }
              body.innerHTML = rows.map(r => `
                <tr>
                  <td>${new Date(r.date).toLocaleString()}</td>
                  <td>${r.product}</td>
                  <td>${r.category || '—'}</td>
                  <td class="${r.type === 'IN' ? 'text-success' : 'text-danger'}">${r.type}</td>
                  <td class="${r.type === 'IN' ? 'text-success' : 'text-danger'}">${r.type === 'IN' ? '+' : '-'}${r.qty}</td>
                </tr>
              `).join('');
            }

            function renderInventoryValuation(rows) {
              const byCat = {};
              rows.forEach(r => { 
                byCat[r.category] = byCat[r.category] || { units: 0, cost: 0 }; 
                if (r.type === 'IN') {
                  byCat[r.category].units += parseInt(r.qty)||0;
                  byCat[r.category].cost += (parseInt(r.qty)||0) * (parseFloat(r.unitCost) || 0);
                }
              });
              const body = document.getElementById('inventoryValuation');
              if (!body) return;
              const cats = Object.keys(byCat);
              if (!cats.length) { body.innerHTML = '<tr><td colspan="3">No data</td></tr>'; return; }
              body.innerHTML = cats.map(c => `
                <tr>
                  <td>${c}</td>
                  <td>${byCat[c].units}</td>
                  <td>${fmtPeso(byCat[c].cost)}</td>
                </tr>
              `).join('');
            }

            function renderSpendingBySupplier(rows) {
              const bySup = {};
              rows.forEach(r => { if (r.type !== 'IN') return; const k = r.supplier || '—'; bySup[k] = bySup[k] || { cats: new Set(), orders: 0, spent: 0 }; bySup[k].cats.add(r.category); bySup[k].orders += 1; bySup[k].spent += (parseInt(r.qty)||0) * (parseFloat(r.unitCost) || 0); });
              const body = document.getElementById('spendingBySupplier');
              if (!body) return;
              const sups = Object.keys(bySup);
              if (!sups.length) { body.innerHTML = '<tr><td colspan="4">No data</td></tr>'; return; }
              body.innerHTML = sups.map(s => `
                <tr>
                  <td>${s}</td>
                  <td>${Array.from(bySup[s].cats).join(', ') || '—'}</td>
                  <td>${bySup[s].orders}</td>
                  <td>${fmtPeso(bySup[s].spent)}</td>
                </tr>
              `).join('');
            }

            function renderSpendingByCategory(rows) {
              const byCat = {};
              rows.forEach(r => { if (r.type !== 'IN') return; byCat[r.category] = byCat[r.category] || { sups: new Set(), products: new Set(), spent: 0 }; byCat[r.category].sups.add(r.supplier || '—'); byCat[r.category].products.add(r.product); byCat[r.category].spent += (parseInt(r.qty)||0) * (parseFloat(r.unitCost) || 0); });
              const body = document.getElementById('spendingByCategory');
              if (!body) return;
              const cats = Object.keys(byCat);
              if (!cats.length) { body.innerHTML = '<tr><td colspan="4">No data</td></tr>'; return; }
              body.innerHTML = cats.map(c => `
                <tr>
                  <td>${c}</td>
                  <td>${byCat[c].sups.size}</td>
                  <td>${byCat[c].products.size}</td>
                  <td>${fmtPeso(byCat[c].spent)}</td>
                </tr>
              `).join('');
            }

            // Supplier helpers
            async function getSuppliers() {
              try {
                const res = await fetch('api/suppliers.php', { method: 'GET' });
                const data = await res.json();
                if (res.ok && data.ok) return data.data || [];
              } catch {}
              try { return JSON.parse(localStorage.getItem('suppliers') || '[]'); } catch { return []; }
            }

            function renderSupplierCards(rows, suppliers) {
              const totalSupEl = document.getElementById('totalSuppliers');
              const activeSupEl = document.getElementById('activeSuppliers');
              const totalSpentEl = document.getElementById('totalSpentSuppliers');

              if (totalSupEl) totalSupEl.textContent = String((suppliers || []).length);

              const active = new Set();
              let spent = 0;
              (rows || []).forEach(r => {
                if (r.type !== 'IN') return;
                if (r.supplier) active.add(r.supplier);
                const qty = parseFloat(r.qty) || 0;
                const unit = parseFloat(r.unitCost) || 0;
                spent += qty * unit;
              });

              if (activeSupEl) activeSupEl.textContent = String(active.size);
              if (totalSpentEl) totalSpentEl.textContent = fmtPeso(spent);
            }

            function renderSupplierPerformance(rows) {
              const body = document.getElementById('supplierPerformance');
              if (!body) return;
              const bySup = {};
              (rows || []).forEach(r => {
                if (r.type !== 'IN') return;
                const s = r.supplier || '—';
                const d = new Date(r.date);
                const qty = parseFloat(r.qty) || 0;
                const unit = parseFloat(r.unitCost) || 0;
                bySup[s] = bySup[s] || { cats: new Set(), dates: [], orders: 0, spent: 0 };
                bySup[s].cats.add(r.category || '—');
                bySup[s].dates.push(d);
                bySup[s].orders += 1;
                bySup[s].spent += qty * unit;
              });
              const sups = Object.keys(bySup);
              if (!sups.length) { body.innerHTML = '<tr><td colspan="5">No data</td></tr>'; return; }
              body.innerHTML = sups.map(s => {
                const rec = bySup[s];
                const orders = rec.orders;
                const spent = rec.spent;
                const aov = orders ? spent / orders : 0;
                let freqText = '—';
                if (rec.dates.length) {
                  const minD = new Date(Math.min(...rec.dates));
                  const maxD = new Date(Math.max(...rec.dates));
                  const days = Math.max(1, Math.ceil((maxD - minD) / (1000 * 60 * 60 * 24)) + 1);
                  const perWeek = orders / (days / 7);
                  freqText = `${perWeek.toFixed(2)} / wk`;
                }
                const cats = Array.from(rec.cats).join(', ') || '—';
                return `
                  <tr>
                    <td>${s}</td>
                    <td>${cats}</td>
                    <td>${fmtPeso(aov)}</td>
                    <td>${freqText}</td>
                    <td>—</td>
                  </tr>
                `;
              }).join('');
            }

            function renderSalesSections(rowsTx) {
              const rev = rowsTx.reduce((s,t)=>s+(parseFloat(t.total)||0),0);
              const count = rowsTx.length;
              const aov = count ? rev / count : 0;
              document.getElementById('revTotal').textContent = fmtPeso(rev);
              document.getElementById('txnCount').textContent = String(count);
              document.getElementById('aov').textContent = fmtPeso(aov);

              const byProduct = {};
              const byCategory = {};
              const byEmployee = {};
              const byPayment = {};
              rowsTx.forEach(t=>{
                byEmployee[t.cashier||'—'] = byEmployee[t.cashier||'—'] || { txns: 0, rev: 0 };
                byEmployee[t.cashier||'—'].txns += 1;
                byEmployee[t.cashier||'—'].rev += parseFloat(t.total)||0;
                byPayment[t.paymentMethod||'—'] = byPayment[t.paymentMethod||'—'] || { txns: 0, rev: 0 };
                byPayment[t.paymentMethod||'—'].txns += 1;
                byPayment[t.paymentMethod||'—'].rev += parseFloat(t.total)||0;
                (t.items||[]).forEach(it=>{
                  byProduct[it.product] = byProduct[it.product] || { qty: 0, rev: 0 };
                  byProduct[it.product].qty += parseInt(it.qty)||0;
                  byProduct[it.product].rev += (parseInt(it.qty)||0)*(parseFloat(it.price)||0);
                  const cat = (it.category||'').toLowerCase();
                  byCategory[cat] = byCategory[cat] || { qty: 0, rev: 0 };
                  byCategory[cat].qty += parseInt(it.qty)||0;
                  byCategory[cat].rev += (parseInt(it.qty)||0)*(parseFloat(it.price)||0);
                });
              });

              const sbp = document.getElementById('salesByProduct');
              const products = Object.keys(byProduct);
              sbp.innerHTML = products.length ? products.map(p=>`<tr><td>${p}</td><td>${byProduct[p].qty}</td><td>${fmtPeso(byProduct[p].rev)}</td></tr>`).join('') : '<tr><td colspan="3">No data</td></tr>';

              const sbc = document.getElementById('salesByCategory');
              const cats = Object.keys(byCategory);
              sbc.innerHTML = cats.length ? cats.map(c=>`<tr><td>${c||'—'}</td><td>${byCategory[c].qty}</td><td>${fmtPeso(byCategory[c].rev)}</td></tr>`).join('') : '<tr><td colspan="3">No data</td></tr>';

              const sbe = document.getElementById('salesByEmployee');
              const emps = Object.keys(byEmployee);
              sbe.innerHTML = emps.length ? emps.map(e=>`<tr><td>${e}</td><td>${byEmployee[e].txns}</td><td>${fmtPeso(byEmployee[e].rev)}</td></tr>`).join('') : '<tr><td colspan="3">No data</td></tr>';

              const pbd = document.getElementById('paymentBreakdown');
              const pms = Object.keys(byPayment);
              pbd.innerHTML = pms.length ? pms.map(m=>`<tr><td>${m}</td><td>${byPayment[m].txns}</td><td>${fmtPeso(byPayment[m].rev)}</td></tr>`).join('') : '<tr><td colspan="3">No data</td></tr>';

              const ph = document.getElementById('purchaseHistory');
              ph.innerHTML = rowsTx.length ? rowsTx.map(t=>{
                let customerDisplay = 'Walk-in';
                if (t.customer_id && t.customer_id !== '0' && t.customer_id !== '') {
                  customerDisplay = t.customer || t.customer_name || '—';
                } else if (t.customer && t.customer !== 'Walk-in' && t.customer !== '') {
                  customerDisplay = t.customer;
                }
                return `<tr><td>${new Date(t.date).toLocaleString()}</td><td>${customerDisplay}</td><td>${(t.items||[]).reduce((s,i)=>s+(parseInt(i.qty)||0),0)}</td><td>${fmtPeso(parseFloat(t.total)||0)}</td></tr>`;
              }).join('') : '<tr><td colspan="4">No data</td></tr>';
            }

            async function renderAll() {
              const [allRows, suppliers] = await Promise.all([getStockMovements(), getSuppliers()]);
              const rows = (allRows || []).filter(r => inRange(r.date, from?.value, to?.value)).sort((a, b) => new Date(b.date) - new Date(a.date));
              renderStockMovement(rows);
              renderInventoryValuation(rows);
              renderSpendingBySupplier(rows);
              renderSpendingByCategory(rows);
              renderSupplierCards(rows, suppliers || []);
              renderSupplierPerformance(rows);

              const txns = (await getTransactions()).filter(t => inRange(t.date, from?.value, to?.value)).sort((a, b) => new Date(b.date) - new Date(a.date));
              renderSalesSections(txns);
            }

            // initial render
            renderAll();
          })();
        </script>
      </main>
    </div>
  </body>
</html>
