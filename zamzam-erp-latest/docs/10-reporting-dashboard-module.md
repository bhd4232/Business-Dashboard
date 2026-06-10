# Module 10: Reporting, Dashboard & Monthly Auto-Report

## Overview

Central reporting module providing role-based dashboards, detailed reports with PDF/Excel export, and a **monthly auto-generated clean responsive dashboard report** that is automatically delivered via WhatsApp, Telegram, and Email on the 1st of every month at 00:01.

---

## Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                Report System Architecture                         │
│                                                                   │
│  ┌─────────────┐    ┌─────────────┐    ┌──────────────┐         │
│  │  Scheduler   │    │  Report     │    │  Delivery    │         │
│  │  (Laravel    │───▶│  Builder    │───▶│  Channels    │         │
│  │   Cron)      │    │  Service    │    │              │         │
│  └─────────────┘    └──────┬──────┘    └──────────────┘         │
│         │                  │                     │               │
│         │                  ▼                     ▼               │
│   1st month 00:01   ┌──────────────┐    ┌──────────────┐        │
│                     │  Report Data │    │  WhatsApp    │        │
│                     │  Aggregator  │    │  (Multi-     │        │
│                     └──────┬───────┘    │  Provider)   │        │
│                            │            ├──────────────┤        │
│                            ▼            │  Telegram    │        │
│                     ┌──────────────┐    │  Bot API     │        │
│                     │  PDF/HTML    │    ├──────────────┤        │
│                     │  Generator   │    │  Email       │        │
│                     └──────┬───────┘    │  (SMTP)      │        │
│                            │            └──────────────┘        │
│                            ▼                                     │
│                     ┌──────────────┐                            │
│                     │  Report      │                            │
│                     │  Storage     │                            │
│                     │  (DB + File) │                            │
│                     └──────────────┘                            │
│                            │                                     │
│                     ┌──────┴───────┐                            │
│                     ▼              ▼                             │
│              👁️ Dashboard    📱 Message                         │
│              (History)      (WhatsApp/                          │
│                              Telegram/Email)                    │
└──────────────────────────────────────────────────────────────────┘
```

---

## Dashboard Types

### 1. Admin/Manager Dashboard
- KPI Cards: Today's Revenue, Monthly Profit, Stock Value, Overdue Amount (with trend %)
- Revenue Chart (Last 30 days line chart)
- Top Products by revenue
- Pending Shipments
- Low Stock Alerts
- Credit Overview (outstanding, overdue)
- Recent Orders
- **Monthly Report Card** (latest month summary with quick view)

### 2. Accountant Dashboard
- Cash & bank balances
- Today's collections
- Pending supplier payments
- Aged receivables summary
- Recent journal entries
- Monthly P&L summary

### 3. Salesman Dashboard
- Today's sales
- My customer list with balances
- Pending collections
- Target vs achievement
- Recent orders

### 4. Storekeeper Dashboard
- Today's goods received
- Stock alerts
- Pending transfers
- Recent stock movements
- Warehouse capacity

### 5. Procurement Dashboard
- Active purchase orders
- In-transit shipments
- Expected deliveries this week
- Supplier payment due
- Exchange rate trends

---

## Monthly Auto-Report Dashboard

### Concept

Every month on the 1st at 00:01, the system automatically:
1. Generates a comprehensive monthly report from all module data
2. Creates a clean, mobile-responsive HTML dashboard
3. Generates a professional PDF
4. Delivers summary + PDF via WhatsApp, Telegram, and Email to configured recipients

### Report Sections

| Section | Data |
|---------|------|
| **Sales Summary** | Total revenue, order count, avg order value, cash vs credit, collection, WoW/MoM comparison, top 5 products, top 5 customers |
| **Customer Report** | New customers, repeat customers, top spenders, credit utilization, aging breakdown |
| **Product Report** | Top sellers by revenue/qty, worst sellers, margin analysis, category breakdown |
| **Inventory Report** | Stock value, critical stock alerts, dead stock, new arrivals, stock movement summary |
| **Credit Report** | Outstanding, collected, overdue, aging breakdown, bad debt risk |
| **Shipping Report** | POs created, in-transit, cleared, avg transit time, landing cost efficiency |
| **Profit Report** | Gross profit, net profit, margin %, category-wise profitability, MoM comparison |

### Dashboard Desktop Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│  📊 Monthly Report — May 2026                          [PDF] [Share] │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────┐│
│  │ 💰 Total Revenue    │  │ 📦 Total Orders     │  │ 💹 Net Profit   ││
│  │                     │  │                     │  │                 ││
│  │     ৳24.5L          │  │     347             │  │     ৳5.8L       ││
│  │  ▲ 12% vs Apr      │  │  ▲ 8% vs Apr        │  │  ▲ 15% vs Apr  ││
│  │  [━━━━━━━━━━░░░░]  │  │  [━━━━━━━━░░░░░░]  │  │ [━━━━━━━░░░░░] ││
│  └─────────────────────┘  └─────────────────────┘  └─────────────────┘│
│                                                                         │
│  ┌── Sales Trend ─────────────────────────────────────────────────────┐│
│  │  Chart.js line: Daily/Weekly revenue, wholesale vs retail split   ││
│  └────────────────────────────────────────────────────────────────────┘│
│                                                                         │
│  ┌── Top 5 Products ──────────────┐  ┌── Top 5 Customers ──────────┐│
│  │ Product          │ Rev  │ Mgn  │  │ Customer        │ Rev  │ Bal ││
│  │ Ceramic Mug      │4.5L  │ 32%  │  │ Rahim Store     │6.2L  │1.2L ││
│  │ Glass Cup        │3.8L  │ 28%  │  │ Karim Traders   │4.8L  │80K  ││
│  │ Dinner Plate     │3.2L  │ 35%  │  │ Ali Enterprise  │3.5L  │45K  ││
│  │ Tea Set          │2.9L  │ 25%  │  │ Fatema Store    │2.8L  │0    ││
│  │ Bowl Set         │2.5L  │ 30%  │  │ Hassan Bros     │2.1L  │30K  ││
│  └─────────────────────────────────┘  └────────────────────────────────┘│
│                                                                         │
│  ┌── Inventory Snapshot ─────────────────────────────────────────────┐ │
│  │  🟢 In Stock: 125 products  │  🟡 Low Stock: 23  │  🔴 Out: 5 │ │
│  │  Stock Value: ৳45.2L  │  New This Month: 12  │  Dead: ৳80K    │ │
│  │  ⚠️ Critical Stock Table                                          │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌── Credit Summary ───────────────────────────────────────────────┐   │
│  │  Outstanding: ৳12.5L  │  Collected: ৳8.3L  │  Overdue: ৳2.1L   │   │
│  │  Aging: Current: 6.5L │ 1-30d: 3.2L │ 31-60d: 1.5L │ 60+: 1.3L│   │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  Generated: 01 Jun 2026 00:01 AM │ ✅ WhatsApp ✅ Telegram ✅ Email   │
└─────────────────────────────────────────────────────────────────────────┘
```

### Mobile Responsive Layout

```
┌──────────────────────┐
│  📊 May 2026 Report  │
│              [PDF][📤]│
├──────────────────────┤
│                      │
│  💰 Revenue           │
│  ৳24.5L ▲12%         │
│  [━━━━━━━━░░░░]      │
│                      │
│  📦 Orders: 347 ▲8%  │
│  💹 Profit: ৳5.8L ▲15%│
│                      │
├──────────────────────┤
│  📈 Sales Trend       │
│  ┌──────────────────┐│
│  │  ╭─╮            ││
│  │ ╭╯ ╰╮           ││
│  │╭╯   ╰─          ││
│  │╯                 ││
│  └──────────────────┘│
├──────────────────────┤
│  🏆 Top Products     │
│  1. Ceramic Mug 4.5L │
│  2. Glass Cup 3.8L    │
│  3. Dinner Plate 3.2L │
│  [View All →]        │
├──────────────────────┤
│  📦 Inventory         │
│  🟢 125  🟡 23  🔴 5 │
│  Value: ৳45.2L       │
│  [View Details →]     │
├──────────────────────┤
│  💳 Credit Summary    │
│  Outstanding: ৳12.5L │
│  Collected: ৳8.3L     │
│  Overdue: ৳2.1L 🔴    │
│  [View Aging →]       │
├──────────────────────┤
│  Sent: 01 Jun 00:01   │
│  ✅ WhatsApp ✅ Email  │
└──────────────────────┘
```

### WhatsApp Message Format

```
══════════════════════════════
📊 *ZAMZAM MONTHLY REPORT*
    *May 2026*
══════════════════════════════

💰 *Revenue:* ৳24.5 Lac
   ▲ 12% vs April

📦 *Orders:* 347
   ▲ 8% vs April

💹 *Net Profit:* ৳5.8 Lac
   ▲ 15% vs April

━━━━━━━━━━━━━━━━━━━━━━

🏆 *Top 5 Products:*
1. Ceramic Mug — ৳4.5L (32%)
2. Glass Cup — ৳3.8L (28%)
3. Dinner Plate — ৳3.2L (35%)
4. Tea Set — ৳2.9L (25%)
5. Bowl Set — ৳2.5L (30%)

👥 *Top 5 Customers:*
1. Rahim Store — ৳6.2L
2. Karim Traders — ৳4.8L
3. Ali Enterprise — ৳3.5L
4. Fatema Store — ৳2.8L
5. Hassan Bros — ৳2.1L

━━━━━━━━━━━━━━━━━━━━━━

📦 *Inventory:*
🟢 In Stock: 125
🟡 Low Stock: 23
🔴 Out of Stock: 5
💰 Stock Value: ৳45.2L

💳 *Credit:*
Outstanding: ৳12.5L
Collected: ৳8.3L
⚠️ Overdue: ৳2.1L

🚢 *Shipping:*
POs: 8 (৳15.2L CNY)
In Transit: 3
Avg Transit: 18 days

━━━━━━━━━━━━━━━━━━━━━━

📥 *Full PDF Report:*
https://erp.zamzam.com/reports/2026-05

📱 *View Dashboard:*
https://erp.zamzam.com/reports/2026-05/dashboard

══════════════════════════════
Auto-generated by ZamZam ERP
```

### Email Format

```
Subject: 📊 ZamZam Monthly Report — May 2026

Professional HTML email with:
- Company logo header
- KPI summary cards (styled, mobile responsive)
- Top 5 products & customers tables
- Inventory & credit summary
- Chart images (rendered server-side, embedded as base64)
- [Download Full PDF] button
- [View Dashboard] button

Footer:
This report was auto-generated by ZamZam ERP on 01 Jun 2026 00:01 AM
```

---

## Report Categories

### 1. Sales Reports

#### Daily/Weekly/Monthly Sales Summary
| Field | Description |
|-------|-------------|
| period | Date range |
| total_orders | Count of orders |
| total_revenue_bdt | Sum of all sales |
| wholesale_revenue | Wholesale only |
| retail_revenue | Retail only |
| avg_order_value | Revenue / orders |
| cash_sales | Cash payment amount |
| credit_sales | Credit payment amount |
| collection_amount | Payments received |
| top_products | By revenue/qty |
| top_customers | By revenue |

#### Sales by Product
| Column | Description |
|--------|-------------|
| product_name | Product name |
| category | Category |
| qty_sold | Total quantity sold |
| revenue_bdt | Total revenue |
| landing_cost_bdt | Total cost |
| gross_profit_bdt | Revenue - cost |
| margin_percent | (profit / revenue) * 100 |

#### Sales by Customer
| Column | Description |
|--------|-------------|
| customer_name | Business name |
| tier | Price tier |
| total_orders | Order count |
| total_revenue | Revenue from customer |
| outstanding_balance | Current balance |
| last_order_date | Most recent order |

#### Sales by Salesman
| Column | Description |
|--------|-------------|
| salesman_name | User name |
| total_orders | Orders handled |
| total_revenue | Revenue generated |
| total_collection | Payments collected |
| target_amount | Monthly target |
| achievement_percent | Revenue / target * 100 |

### 2. Inventory Reports

#### Stock Summary
| Column | Description |
|--------|-------------|
| product_name | Product name |
| warehouse | Warehouse name |
| qty_available | Available stock |
| qty_reserved | Reserved stock |
| landing_cost_per_unit | Unit cost |
| total_value | qty * cost |
| min_stock_alert | Alert threshold |
| status | Normal / Low / Out of stock |

#### Stock Valuation
Total stock value by warehouse, category, product with period comparison.

#### Stock Movement Report
| Column | Description |
|--------|-------------|
| date | Transaction date |
| product | Product name |
| type | receive/sale/transfer/adjust |
| qty | Quantity |
| warehouse | Warehouse |
| reference | PO/SO/Transfer number |

#### Slow-Moving Stock
Products with no sales in last 30/60/90 days with stock value and action recommendation.

### 3. Credit & Collection Reports

#### Aging Report
Customer-wise breakdown: Current | 1-30 days | 31-60 days | 61-90 days | 90+ days

#### Collection Report
| Column | Description |
|--------|-------------|
| date | Payment date |
| customer | Customer name |
| amount_bdt | Payment amount |
| method | Cash/bKash/Bank |
| reference | Transaction reference |
| salesman | Who collected |

#### Credit Limit Utilization
Customer | Credit Limit | Outstanding | Available | Utilization %

#### Bad Debt Risk Report
Customers with 90+ days overdue, total at-risk amount.

### 4. Profit Reports

#### Product-wise Profitability
| Column | Description |
|--------|-------------|
| product_name | Product |
| qty_sold | Quantity sold |
| avg_selling_price | Average sale price |
| landing_cost_per_unit | Cost per unit |
| gross_profit_per_unit | Sale - cost |
| total_profit | Profit * qty |
| margin_percent | Margin % |

#### Category-wise Profitability
Category | Revenue | COGS | Gross Profit | Margin %

#### Monthly P&L
Income (Wholesale + Retail + Other) minus COGS (Purchase + Freight + Duty) minus Operating Expenses = Net Profit.

### 5. Shipping & Procurement Reports

#### Shipment Status Summary
In Transit | At Customs | Cleared | Delivered This Month | Average transit time | Cost per shipment

#### Purchase Order Summary
Period | # POs | Total CNY | Total BDT | Received | Pending (by supplier, by status)

#### Landing Cost Analysis
Product | Purchase Cost | Freight | Duty | VAT | AIT | Total Landing | Per Unit | Trend

#### Supplier Performance
Supplier | # Orders | On-time % | Quality Rating | Avg Lead Time | Total Spent

### 6. Financial Reports

#### Trial Balance
Account Code | Account Name | Debit BDT | Credit BDT (must balance)

#### Balance Sheet
Assets (Cash, Receivables, Inventory) = Liabilities (Payables, VAT) + Equity

#### Cash Flow Statement
Operating + Investing + Financing activities = Net Cash Flow

---

## Database Tables

### monthly_reports
```sql
CREATE TABLE monthly_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_uuid VARCHAR(50) NOT NULL,
    period_year INT NOT NULL,
    period_month INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    type ENUM('full','sales','inventory','credit','shipping') DEFAULT 'full',
    status ENUM('generating','ready','sent','failed') DEFAULT 'generating',
    data_json JSON NOT NULL,
    summary_json JSON NULL,
    pdf_path VARCHAR(500) NULL,
    pdf_generated_at TIMESTAMP NULL,
    html_generated_at TIMESTAMP NULL,
    generated_by BIGINT UNSIGNED NULL,
    generation_duration_ms INT NULL,
    sent_at TIMESTAMP NULL,
    sent_channels JSON NULL,
    sent_to JSON NULL,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX monthly_reports_uuid_unique (report_uuid),
    INDEX idx_monthly_reports_period (period_year, period_month),
    INDEX idx_monthly_reports_status (status),
    FOREIGN KEY (generated_by) REFERENCES users(id)
);
```

### report_delivery_settings
```sql
CREATE TABLE report_delivery_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    channel ENUM('whatsapp','telegram','email') NOT NULL,
    channel_address VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    report_type ENUM('full','sales_only','inventory_only','credit_only') DEFAULT 'full',
    send_day INT DEFAULT 1,
    send_time TIME DEFAULT '00:01:00',
    include_pdf_attachment BOOLEAN DEFAULT TRUE,
    include_dashboard_link BOOLEAN DEFAULT TRUE,
    include_summary_in_message BOOLEAN DEFAULT TRUE,
    last_sent_at TIMESTAMP NULL,
    last_report_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX report_delivery_unique (user_id, channel, channel_address),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (last_report_id) REFERENCES monthly_reports(id)
);
```

---

## API Routes

### Dashboard (Role-based)
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/dashboard/admin | Admin dashboard data | admin |
| GET | /api/dashboard/accountant | Accountant dashboard | accountant |
| GET | /api/dashboard/salesman | Salesman dashboard | salesman |
| GET | /api/dashboard/storekeeper | Storekeeper dashboard | storekeeper |
| GET | /api/dashboard/procurement | Procurement dashboard | procurement |

### Monthly Reports
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/reports/monthly | Monthly report list | report.view |
| GET | /api/reports/monthly/{year}/{month} | Specific month dashboard data | report.view |
| GET | /api/reports/monthly/{id}/pdf | Download PDF | report.view |
| GET | /api/reports/monthly/{id}/share-link | Generate shareable link | report.manage |
| POST | /api/reports/monthly/generate | Manually trigger generation | report.manage |
| POST | /api/reports/monthly/{id}/resend | Resend to channels | report.manage |

### Delivery Settings
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/report-delivery/settings | User's delivery settings | report.manage |
| POST | /api/report-delivery/settings | Create delivery setting | report.manage |
| PUT | /api/report-delivery/settings/{id} | Update delivery setting | report.manage |
| DELETE | /api/report-delivery/settings/{id} | Delete delivery setting | report.manage |
| POST | /api/report-delivery/test | Send test message | report.manage |

### Sales Reports
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/reports/sales/summary | Sales summary (date range) | report.sales |
| GET | /api/reports/sales/by-product | Product-wise sales | report.sales |
| GET | /api/reports/sales/by-customer | Customer-wise sales | report.sales |
| GET | /api/reports/sales/by-salesman | Salesman performance | report.sales |
| GET | /api/reports/sales/daily | Daily sales breakdown | report.sales |

### Inventory Reports
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/reports/inventory/stock-summary | Stock summary | report.inventory |
| GET | /api/reports/inventory/stock-valuation | Stock valuation | report.inventory |
| GET | /api/reports/inventory/movement | Stock movement | report.inventory |
| GET | /api/reports/inventory/slow-moving | Slow moving products | report.inventory |

### Credit Reports
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/reports/credit/aging | Aging report | report.credit |
| GET | /api/reports/credit/collection | Collection report | report.credit |
| GET | /api/reports/credit/utilization | Credit utilization | report.credit |
| GET | /api/reports/credit/bad-debt-risk | Bad debt risk | report.credit |

### Profit Reports
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/reports/profit/by-product | Product profitability | report.profit |
| GET | /api/reports/profit/by-category | Category profitability | report.profit |
| GET | /api/reports/profit/monthly-pl | Monthly P&L | report.profit |
| GET | /api/reports/profit/margin-analysis | Margin analysis | report.profit |

### Shipping & Procurement Reports
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/reports/shipping/status-summary | Shipment summary | report.shipping |
| GET | /api/reports/shipping/landing-cost | Landing cost analysis | report.shipping |
| GET | /api/reports/procurement/po-summary | PO summary | report.view |
| GET | /api/reports/procurement/supplier-performance | Supplier performance | report.view |

### Financial Reports
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/reports/finance/trial-balance | Trial balance | report.view |
| GET | /api/reports/finance/balance-sheet | Balance sheet | report.view |
| GET | /api/reports/finance/cash-flow | Cash flow | report.view |
| GET | /api/reports/finance/general-ledger | General ledger | report.view |
| GET | /api/reports/finance/bank-book | Bank book | report.view |
| GET | /api/reports/finance/cash-book | Cash book | report.view |

### Export
All report endpoints support `?export=pdf` and `?export=excel` query parameters.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/reports/{reportType}?export=pdf | Export as PDF |
| GET | /api/reports/{reportType}?export=excel | Export as Excel |

---

## Frontend Pages

```
📈 Reports (Main Menu)
  ├── 📊 Dashboard (role-based)
  ├── 📅 Monthly Reports
  │   ├── Report List (year/month card grid)
  │   ├── Report Dashboard (interactive charts)
  │   └── Report PDF Preview
  ├── 🛒 Sales Reports
  │   ├── Sales Summary
  │   ├── By Product
  │   ├── By Customer
  │   └── By Salesman
  ├── 📦 Inventory Reports
  │   ├── Stock Summary
  │   ├── Stock Valuation
  │   ├── Movement Report
  │   └── Slow Moving
  ├── 💳 Credit Reports
  │   ├── Aging Report
  │   ├── Collection Report
  │   └── Credit Utilization
  ├── 💹 Profit Reports
  │   ├── Product Profitability
  │   ├── Category Profitability
  │   └── Monthly P&L
  ├── 🚢 Shipping Reports
  │   ├── Shipment Summary
  │   └── Landing Cost Analysis
  ├── 💰 Financial Reports
  │   ├── Trial Balance
  │   ├── Balance Sheet
  │   └── Cash Flow
  └── ⚙️ Delivery Settings
      ├── WhatsApp Recipients
      ├── Telegram Recipients
      ├── Email Recipients
      └── Test Delivery
```

---

## Business Logic

### Monthly Report Generation (Scheduled)

```php
// app/Console/Commands/GenerateMonthlyReport.php

class GenerateMonthlyReport extends Command
{
    protected $signature = 'report:monthly {--month=} {--year=}';
    protected $description = 'Generate monthly report and deliver to configured channels';

    public function handle(): int
    {
        $month = $this->option('month') ?? now()->subMonth()->month;
        $year = $this->option('year') ?? now()->subMonth()->year;

        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();

        $this->info("Generating report for {$startDate->format('F Y')}...");

        $startTime = microtime(true);

        $data = app(MonthlyReportService::class)->generate($startDate, $endDate);

        $report = MonthlyReport::create([
            'report_uuid' => Str::uuid(),
            'period_year' => $year,
            'period_month' => $month,
            'period_start' => $startDate,
            'period_end' => $endDate,
            'type' => 'full',
            'status' => 'generating',
            'data_json' => $data,
            'summary_json' => app(MonthlyReportService::class)->generateSummary($data),
        ]);

        $pdfPath = app(MonthlyReportPdfService::class)->generate($report);
        $report->update([
            'pdf_path' => $pdfPath,
            'status' => 'ready',
            'pdf_generated_at' => now(),
            'generation_duration_ms' => (int)((microtime(true) - $startTime) * 1000),
        ]);

        app(ReportDeliveryService::class)->deliver($report);
        $report->update(['status' => 'sent', 'sent_at' => now()]);

        $this->info("Report generated and delivered successfully.");
        return self::SUCCESS;
    }
}
```

### Scheduler

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    $schedule->command('report:monthly')
        ->monthlyOn(1, '00:01')
        ->withoutOverlapping()
        ->onOneServer();
}
```

### MonthlyReportService

```php
class MonthlyReportService
{
    public function generate(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'month_name' => $startDate->format('F'),
                'year' => $startDate->year,
            ],
            'sales' => $this->getSalesData($startDate, $endDate),
            'customers' => $this->getCustomerData($startDate, $endDate),
            'products' => $this->getProductData($startDate, $endDate),
            'inventory' => $this->getInventoryData($startDate, $endDate),
            'credit' => $this->getCreditData($startDate, $endDate),
            'shipping' => $this->getShippingData($startDate, $endDate),
            'comparison' => $this->getComparisonData($startDate, $endDate),
        ];
    }

    public function generateSummary(array $data): array
    {
        return [
            'period' => $data['period'],
            'sales' => [
                'total_revenue' => $data['sales']['total_revenue_bdt'],
                'total_orders' => $data['sales']['total_orders'],
                'revenue_change_percent' => $data['comparison']['revenue_change_percent'],
                'order_change_percent' => $data['comparison']['order_change_percent'],
                'top_products' => $data['sales']['top_products'],
                'top_customers' => $data['sales']['top_customers'],
            ],
            'inventory' => [
                'in_stock' => $data['inventory']['in_stock_count'],
                'low_stock' => $data['inventory']['low_stock_count'],
                'out_of_stock' => $data['inventory']['out_of_stock_count'],
                'stock_value' => $data['inventory']['total_value_bdt'],
            ],
            'credit' => [
                'outstanding' => $data['credit']['outstanding_bdt'],
                'collected' => $data['credit']['collected_bdt'],
                'overdue' => $data['credit']['overdue_bdt'],
            ],
            'shipping' => [
                'pos_count' => $data['shipping']['pos_count'],
                'in_transit' => $data['shipping']['in_transit_count'],
                'avg_transit_days' => $data['shipping']['avg_transit_days'],
            ],
        ];
    }
}
```

### ReportDeliveryService

```php
class ReportDeliveryService
{
    public function deliver(MonthlyReport $report): void
    {
        $settings = ReportDeliverySetting::where('is_active', true)->get();

        foreach ($settings as $setting) {
            try {
                match ($setting->channel) {
                    'whatsapp' => $this->sendWhatsApp($report, $setting),
                    'telegram' => $this->sendTelegram($report, $setting),
                    'email' => $this->sendEmail($report, $setting),
                };

                $setting->update([
                    'last_sent_at' => now(),
                    'last_report_id' => $report->id,
                ]);
            } catch (\Throwable $e) {
                Log::error("Report delivery failed", [
                    'channel' => $setting->channel,
                    'recipient' => $setting->channel_address,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function sendWhatsApp(MonthlyReport $report, ReportDeliverySetting $setting): void
    {
        $summary = $report->summary_json;
        $dashboardUrl = route('reports.monthly.dashboard', [
            'year' => $report->period_year,
            'month' => $report->period_month,
        ]);

        if ($setting->include_summary_in_message) {
            $message = app(WhatsAppReportFormatter::class)->format($summary, $dashboardUrl);
            app(WhatsAppService::class)->sendViaDefault($setting->channel_address, $message);
        }

        if ($setting->include_pdf_attachment) {
            app(WhatsAppService::class)->sendMedia(
                $setting->channel_address,
                Storage::url($report->pdf_path),
                'document',
                "ZamZam_Monthly_Report_{$report->period_year}_{$report->period_month}.pdf"
            );
        }
    }

    private function sendTelegram(MonthlyReport $report, ReportDeliverySetting $setting): void
    {
        $summary = $report->summary_json;
        $message = app(TelegramReportFormatter::class)->format($summary);

        Http::post("https://api.telegram.org/bot" . config('services.telegram.bot_token') . "/sendMessage", [
            'chat_id' => $setting->channel_address,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);

        if ($setting->include_pdf_attachment) {
            Http::attach(
                'document',
                Storage::get($report->pdf_path),
                "ZamZam_Report_{$report->period_year}_{$report->period_month}.pdf"
            )->post("https://api.telegram.org/bot" . config('services.telegram.bot_token') . "/sendDocument", [
                'chat_id' => $setting->channel_address,
            ]);
        }
    }

    private function sendEmail(MonthlyReport $report, ReportDeliverySetting $setting): void
    {
        Mail::to($setting->channel_address)->send(new MonthlyReportMail($report));
    }
}
```

### Delivery Settings Page (Admin UI)

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ⚙️ Report Delivery Settings                                           │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📅 Report Schedule                                                     │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │  Generate on: [1st ▼] of every month at [00:01 ▼]             │   │
│  │  ☑ Auto-generate (uncheck for manual only)                      │   │
│  │                                                                  │   │
│  │  Report types:                                                   │   │
│  │  ☑ Full Report (all sections)                                    │   │
│  │  ☐ Sales Only                                                   │   │
│  │  ☐ Inventory Only                                               │   │
│  │  ☐ Credit Only                                                  │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  📱 Delivery Channels                                                   │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                                                                  │   │
│  │  ✅ WhatsApp                                                     │   │
│  │  Recipient: +880 17XX-XXXXXX (Admin)                            │   │
│  │  ☑ Include summary in message                                   │   │
│  │  ☑ Include PDF as document                                      │   │
│  │  ☑ Include dashboard link                                        │   │
│  │  [Test Send] ✅ Working                                         │   │
│  │  ──────────────────────────────────────────────────────           │   │
│  │                                                                  │   │
│  │  ✅ WhatsApp                                                     │   │
│  │  Recipient: +880 18XX-XXXXXX (Manager)                          │   │
│  │  ☐ Include summary (summary too long for non-admin)             │   │
│  │  ☑ Include PDF as document                                      │   │
│  │  ☑ Include dashboard link                                        │   │
│  │  ─────────────────────────────────────────────────────────────── │   │
│  │                                                                  │   │
│  │  ✅ Telegram                                                     │   │
│  │  Chat ID: -100XXXXXXXXXX (ZamZam Admin Group)                  │   │
│  │  ☑ Include summary in message                                   │   │
│  │  ☑ Include PDF as document                                      │   │
│  │  ────────────────────────────────────────────────                 │   │
│  │                                                                  │   │
│  │  ✅ Email                                                        │   │
│  │  Recipient: admin@zamzam.com                                    │   │
│  │  ☑ Include PDF as attachment                                    │   │
│  │  ☑ Include dashboard link                                        │   │
│  │                                                                  │   │
│  │  [+ Add Channel]                                                 │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  [💾 Save Settings]                                                      │
└─────────────────────────────────────────────────────────────────────────┘
```

### Report History (Card Grid)

```
┌──────────────────────────────────────────────────────────────────────────┐
│  📅 Monthly Reports                                         [Generate] │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  2026                                                                    │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ │
│  │  May   │ │  Apr   │ │  Mar   │ │  Feb   │ │  Jan   │ │ 2025   │ │
│  │  ৳24.5L│ │ ৳21.8L│ │ ৳19.2L│ │ ৳17.5L│ │ ৳15.8L│ │  →     │ │
│  │ ▲12%  │ │ ▲14%  │ │ ▲10%  │ │ ▲8%   │ │ ▲6%   │ │         │ │
│  │ ✅ Sent │ │ ✅ Sent│ │ ✅ Sent│ │ ✅ Sent│ │ ✅ Sent│ │         │ │
│  │ 3 ch   │ │ 3 ch   │ │ 3 ch   │ │ 2 ch   │ │ 2 ch   │ │         │ │
│  └────────┘ └────────┘ └────────┘ └────────┘ └────────┘ └────────┘ │
│                                                                          │
│  Click any card to view dashboard ↓                                      │
│                                                                          │
│  Last generated: 01 Jun 2026 00:01 AM                                    │
│  Channels: ✅ WhatsApp (2) ✅ Telegram (1) ✅ Email (1)                │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## Caching Strategy

```
Dashboard data: Cache for 5 minutes (Redis)
Report summaries: Cache for 15 minutes with date-range key
Detailed reports: No caching (real-time queries)
Monthly reports: Cache for 1 hour (generated data is immutable)
Export files: Generated on-demand, not cached
```

## Developer Notes

1. Dashboard queries must be fast — use Redis caching (5 min TTL)
2. Use database read replicas for heavy report queries if available
3. Add proper indexes on date columns for all transaction tables
4. PDF exports use `barryvdh/laravel-dompdf` with custom Blade templates following ZamZam design system (see 13-design-system.md)
5. Excel exports use `maatwebsite/excel` (laravel-excel) package
6. Monthly report PDF uses Invoice design template from 13-design-system.md
7. All report endpoints accept common query params: `from_date`, `to_date`, `warehouse_id`, `category_id`, `export`
8. Large exports (1000+ rows) should be queued and emailed to user
9. Dashboard widgets are loaded via AJAX (not server-rendered) for better UX
10. Chart data returned as JSON arrays compatible with Chart.js
11. Monthly report generation is queued and runs at 00:01 on the 1st of each month
12. WhatsApp delivery uses the multi-provider system from Module 12
13. Telegram delivery uses Bot API with Markdown formatting
14. Email uses Laravel Mail with HTML template
15. Aging report is the most query-intensive — consider materialized view or daily snapshot
16. Report delivery settings are per-user, allowing different recipients to receive different report types on different channels