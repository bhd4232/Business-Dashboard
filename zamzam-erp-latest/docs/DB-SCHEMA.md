# ZamZam ERP - Complete Database Schema

## Overview

This document contains the complete database schema for all modules. Tables are grouped by module with foreign key relationships noted.

## Naming Conventions

- Table names: snake_case, plural (e.g., `purchase_orders`)
- Column names: snake_case (e.g., `outstanding_balance_bdt`)
- Primary keys: `id` (bigint, auto-increment)
- Foreign keys: `{table_name_singular}_id` (e.g., `customer_id`)
- Timestamp columns: `created_at`, `updated_at`
- Monetary columns: suffix `_bdt`, `_cny` to indicate currency
- Status columns: enum type with explicit values

## Indexes (Critical)

```sql
-- High-traffic indexes
CREATE INDEX idx_stock_items_product_warehouse ON stock_items(product_id, warehouse_id);
CREATE INDEX idx_stock_transactions_product_date ON stock_transactions(product_id, created_at);
CREATE INDEX idx_credit_ledger_customer_date ON credit_ledger(customer_id, date);
CREATE INDEX idx_sales_orders_customer_status ON sales_orders(customer_id, status);
CREATE INDEX idx_sales_orders_type_source ON sales_orders(type, source);
CREATE INDEX idx_invoices_status_due ON invoices(status, due_date);
CREATE INDEX idx_payments_payer_method ON payments(payer_id, method);
CREATE INDEX idx_purchase_orders_supplier_status ON purchase_orders(supplier_id, status);
CREATE INDEX idx_shipments_status_eta ON shipments(status, eta);
-- Note: sync_logs index removed — table does not exist (WooCommerce uses woocommerce_import_logs)
CREATE INDEX idx_journal_entries_account ON journal_entries(account_id);
CREATE INDEX idx_courier_parcels_provider_status ON courier_parcels(courier_provider_id, status);
CREATE INDEX idx_courier_parcels_customer ON courier_parcels(customer_id);
CREATE INDEX idx_courier_parcels_order ON courier_parcels(sales_order_id);
CREATE INDEX idx_courier_parcels_status ON courier_parcels(status);
CREATE INDEX idx_courier_performance_period ON courier_performance_metrics(courier_provider_id, period_type, period_start);
CREATE INDEX idx_delivery_zones_type ON delivery_zones(zone_type, is_active);
CREATE INDEX idx_fake_detections_unresolved ON fake_order_detections(is_resolved, detection_type);
CREATE INDEX idx_ip_blacklist_active ON ip_blacklist(ip_address, is_active);
CREATE INDEX idx_conversations_channel_customer ON conversations(channel, channel_customer_id);
CREATE INDEX idx_conversations_status ON conversations(status);
CREATE INDEX idx_conv_msgs_conversation ON conversation_messages(conversation_id, created_at);
CREATE INDEX idx_agent_actions_type_status ON agent_actions(action_type, status);
CREATE INDEX idx_wa_logs_provider_date ON whatsapp_provider_logs(provider_id, created_at);
CREATE INDEX idx_workflows_trigger_status ON chatbot_workflows(trigger_type, status);
CREATE INDEX idx_wf_exec_workflow ON chatbot_workflow_executions(workflow_id, created_at);
CREATE INDEX idx_monthly_reports_period ON monthly_reports(period_year, period_month);
CREATE INDEX idx_monthly_reports_status ON monthly_reports(status);
CREATE INDEX idx_report_delivery_user_channel ON report_delivery_settings(user_id, channel);
CREATE INDEX idx_customers_phone ON customers(phone);
CREATE INDEX idx_customers_source ON customers(source);
CREATE INDEX idx_customers_type ON customers(type);
CREATE INDEX idx_customers_last_order ON customers(last_order_at);
CREATE INDEX idx_data_imports_entity ON data_imports(entity_type);
CREATE INDEX idx_data_imports_status ON data_imports(status);
CREATE INDEX idx_data_import_errors_import ON data_import_errors(data_import_id);
CREATE INDEX idx_wc_import_logs_import ON woocommerce_import_logs(woocommerce_import_id);
CREATE INDEX idx_wc_import_logs_entity ON woocommerce_import_logs(entity_type, action);
```

---

## Module 1: Auth & User Management

### users
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    phone_verified_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP NULL,
    profile_photo_path VARCHAR(2048) NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX users_email_unique (email),
    UNIQUE INDEX users_phone_unique (phone)
);
```

### roles (Spatie Laravel Permission)
```sql
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL DEFAULT 'web',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX roles_name_guard_unique (name, guard_name)
);
```

### permissions (Spatie + custom module column)
```sql
CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL DEFAULT 'web',
    module VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX permissions_name_guard_unique (name, guard_name)
);
```

### model_has_roles, role_has_permissions, model_has_permissions
Standard Spatie Laravel Permission pivot tables.

### activity_log
```sql
CREATE TABLE activity_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    subject_type VARCHAR(255) NULL,
    subject_id BIGINT UNSIGNED NULL,
    event VARCHAR(255) NOT NULL,
    properties JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_activity_log_subject (subject_type, subject_id),
    INDEX idx_activity_log_user (user_id)
);
```

---

## Module 2: Supplier & Procurement

### currencies
```sql
CREATE TABLE currencies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(3) NOT NULL,
    name VARCHAR(100) NOT NULL,
    symbol VARCHAR(5) NOT NULL,
    is_base BOOLEAN DEFAULT FALSE,
    decimal_places TINYINT DEFAULT 2,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX currencies_code_unique (code)
);
```

### exchange_rates
```sql
CREATE TABLE exchange_rates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_currency_id BIGINT UNSIGNED NOT NULL,
    to_currency_id BIGINT UNSIGNED NOT NULL,
    rate DECIMAL(12,6) NOT NULL,
    effective_date DATE NOT NULL,
    source VARCHAR(50) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX exchange_rates_unique (from_currency_id, to_currency_id, effective_date),
    FOREIGN KEY (from_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (to_currency_id) REFERENCES currencies(id)
);
```

### suppliers
```sql
CREATE TABLE suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_chinese VARCHAR(255) NOT NULL,
    name_english VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) NULL,
    wechat_id VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(255) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    country VARCHAR(2) DEFAULT 'CN',
    website VARCHAR(500) NULL,
    rating TINYINT NULL,
    payment_terms VARCHAR(255) NULL,
    preferred_currency VARCHAR(3) DEFAULT 'CNY',
    bank_details JSON NULL,
    notes TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### supplier_contacts
```sql
CREATE TABLE supplier_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    designation VARCHAR(100) NULL,
    wechat_id VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(255) NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);
```

### categories
```sql
CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT NULL,
    image VARCHAR(500) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX categories_slug_unique (slug),
    FOREIGN KEY (parent_id) REFERENCES categories(id)
);
```

### products
```sql
CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_chinese VARCHAR(255) NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    unit VARCHAR(20) DEFAULT 'piece',
    weight_kg DECIMAL(10,3) NULL,
    volume_cm3 DECIMAL(12,3) NULL,
    description TEXT NULL,
    image VARCHAR(500) NULL,
    barcode VARCHAR(100) NULL,
    has_variants BOOLEAN DEFAULT FALSE,
    min_stock_alert INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX products_sku_unique (sku),
    UNIQUE INDEX products_barcode_unique (barcode),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### product_variants
```sql
CREATE TABLE product_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    variant_name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) NOT NULL,
    barcode VARCHAR(100) NULL,
    attributes JSON NULL,
    weight_kg DECIMAL(10,3) NULL,
    volume_cm3 DECIMAL(12,3) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX product_variants_sku_unique (sku),
    UNIQUE INDEX product_variants_barcode_unique (barcode),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
```

### product_suppliers
```sql
CREATE TABLE product_suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    price_cny DECIMAL(12,2) NOT NULL,
    moq INT DEFAULT 1,
    lead_time_days INT NULL,
    supplier_sku VARCHAR(100) NULL,
    product_url VARCHAR(500) NULL,
    is_preferred BOOLEAN DEFAULT FALSE,
    last_purchased_at TIMESTAMP NULL,
    last_purchase_price_cny DECIMAL(12,2) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);
```

### purchase_orders
```sql
CREATE TABLE purchase_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    currency_id BIGINT UNSIGNED NOT NULL,
    exchange_rate DECIMAL(12,6) NOT NULL DEFAULT 0,
    status ENUM('draft','confirmed','partially_shipped','shipped','received','completed','cancelled') DEFAULT 'draft',
    order_date DATE NOT NULL,
    expected_delivery_date DATE NULL,
    subtotal_cny DECIMAL(14,2) DEFAULT 0,
    total_cny DECIMAL(14,2) DEFAULT 0,
    total_bdt DECIMAL(14,2) DEFAULT 0,
    notes TEXT NULL,
    terms_and_conditions TEXT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX purchase_orders_po_number_unique (po_number),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### po_items
```sql
CREATE TABLE po_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NULL,
    supplier_price_cny DECIMAL(12,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal_cny DECIMAL(14,2) NOT NULL,
    received_qty INT DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id)
);
```

### product_price_history
```sql
CREATE TABLE product_price_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    price_cny DECIMAL(12,2) NOT NULL,
    price_bdt DECIMAL(12,2) NOT NULL,
    exchange_rate DECIMAL(12,6) NOT NULL,
    qty INT NOT NULL,
    recorded_at DATE NOT NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_product_price_history_product (product_id, recorded_at),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
);
```

---

## Module 3A: International Shipping (China → Bangladesh)

### shipments
```sql
CREATE TABLE shipments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_no VARCHAR(50) NOT NULL,
    purchase_order_id BIGINT UNSIGNED NULL,
    carrier VARCHAR(255) NULL,
    container_no VARCHAR(50) NULL,
    container_type VARCHAR(20) NULL,
    bl_number VARCHAR(100) NULL,
    shipping_type ENUM('sea','air','rail','courier') NOT NULL,
    port_loading VARCHAR(100) NULL,
    port_discharge VARCHAR(100) NULL,
    etd DATE NULL,
    eta DATE NULL,
    atd DATE NULL,
    ata DATE NULL,
    status ENUM('booked','loaded','departed','in_transit','arrived','clearing','cleared','delivered_to_warehouse') DEFAULT 'booked',
    customs_agent VARCHAR(255) NULL,
    customs_declaration_no VARCHAR(100) NULL,
    tracking_url VARCHAR(500) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX shipments_no_unique (shipment_no),
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### shipment_items, shipment_costs, shipment_documents, shipment_status_history, landing_cost_allocations
(Full schemas as defined in 03a-international-shipping.md)

---

## Module 3B: Domestic Logistics (Bangladesh Courier Services)

### courier_providers
```sql
CREATE TABLE courier_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL,
    logo_path VARCHAR(500) NULL,
    api_url VARCHAR(500) NULL,
    api_key VARCHAR(500) NULL,
    api_secret VARCHAR(500) NULL,
    api_enabled BOOLEAN DEFAULT FALSE,
    default_delivery_charge_inside_bdt DECIMAL(8,2) NULL,
    default_delivery_charge_outside_bdt DECIMAL(8,2) NULL,
    cod_charge_percent DECIMAL(5,2) DEFAULT 0,
    weight_charge_per_kg_bdt DECIMAL(8,2) NULL,
    return_charge_bdt DECIMAL(8,2) DEFAULT 0,
    max_delivery_days INT NULL,
    coverage_areas JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX courier_providers_code_unique (code)
);
```

### courier_parcels
```sql
CREATE TABLE courier_parcels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcel_no VARCHAR(50) NOT NULL,
    courier_provider_id BIGINT UNSIGNED NOT NULL,
    sales_order_id BIGINT UNSIGNED NULL,
    invoice_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    shipment_type ENUM('regular','express','same_day') NOT NULL,
    delivery_type ENUM('inside_dhaka','outside_dhaka','sub_city') NOT NULL,
    payment_type ENUM('prepaid','cod') NOT NULL,
    cod_amount_bdt DECIMAL(14,2) NULL,
    weight_kg DECIMAL(8,3) NULL,
    parcel_content VARCHAR(255) NULL,
    parcel_value_bdt DECIMAL(14,2) NULL,
    number_of_items INT DEFAULT 1,
    sender_name VARCHAR(255) NOT NULL,
    sender_phone VARCHAR(20) NOT NULL,
    sender_address TEXT NOT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_alt_phone VARCHAR(20) NULL,
    recipient_address TEXT NOT NULL,
    recipient_city VARCHAR(100) NULL,
    recipient_area VARCHAR(100) NULL,
    recipient_zone VARCHAR(100) NULL,
    recipient_district VARCHAR(100) NULL,
    courier_tracking_id VARCHAR(255) NULL,
    courier_consignment_id VARCHAR(255) NULL,
    delivery_charge_bdt DECIMAL(8,2) NOT NULL,
    cod_charge_bdt DECIMAL(8,2) DEFAULT 0,
    total_charge_bdt DECIMAL(8,2) NOT NULL,
    status ENUM('pending','picked_up','in_transit','out_for_delivery','delivered','partial_delivery','returned','cancelled','lost') DEFAULT 'pending',
    courier_status VARCHAR(50) NULL,
    courier_status_updated_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    returned_at TIMESTAMP NULL,
    return_reason VARCHAR(255) NULL,
    cancellation_reason VARCHAR(255) NULL,
    pod_image_path VARCHAR(500) NULL,
    pod_signature_path VARCHAR(500) NULL,
    pod_submitted_by BIGINT UNSIGNED NULL,
    pod_submitted_at TIMESTAMP NULL,
    delivery_attempt_count INT DEFAULT 0,
    label_generated BOOLEAN DEFAULT FALSE,
    label_path VARCHAR(500) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX courier_parcels_no_unique (parcel_no),
    FOREIGN KEY (courier_provider_id) REFERENCES courier_providers(id),
    FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (pod_submitted_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### courier_parcel_items
```sql
CREATE TABLE courier_parcel_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    courier_parcel_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NULL,
    qty INT NOT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (courier_parcel_id) REFERENCES courier_parcels(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id)
);
```

### courier_status_history
```sql
CREATE TABLE courier_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    courier_parcel_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL,
    location VARCHAR(255) NULL,
    notes TEXT NULL,
    courier_raw_data JSON NULL,
    source ENUM('manual','api_sync') NOT NULL,
    changed_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (courier_parcel_id) REFERENCES courier_parcels(id) ON DELETE CASCADE
);
```

### courier_bills
```sql
CREATE TABLE courier_bills (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    courier_provider_id BIGINT UNSIGNED NOT NULL,
    bill_number VARCHAR(50) NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_parcels INT NOT NULL,
    total_delivery_charge_bdt DECIMAL(14,2) NOT NULL,
    total_cod_charge_bdt DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_cod_collected_bdt DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_deduction_bdt DECIMAL(14,2) DEFAULT 0,
    net_payable_bdt DECIMAL(14,2) NOT NULL,
    status ENUM('draft','confirmed','paid','disputed') DEFAULT 'draft',
    paid_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (courier_provider_id) REFERENCES courier_providers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### courier_bill_items
```sql
CREATE TABLE courier_bill_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    courier_bill_id BIGINT UNSIGNED NOT NULL,
    courier_parcel_id BIGINT UNSIGNED NOT NULL,
    delivery_charge_bdt DECIMAL(8,2) NOT NULL,
    cod_charge_bdt DECIMAL(8,2) DEFAULT 0,
    cod_collected_bdt DECIMAL(14,2) NULL,
    deduction_bdt DECIMAL(8,2) DEFAULT 0,
    deduction_reason VARCHAR(255) NULL,
    net_amount_bdt DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (courier_bill_id) REFERENCES courier_bills(id) ON DELETE CASCADE,
    FOREIGN KEY (courier_parcel_id) REFERENCES courier_parcels(id)
);
```

### courier_performance_metrics
```sql
CREATE TABLE courier_performance_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    courier_provider_id BIGINT UNSIGNED NOT NULL,
    period_type ENUM('daily','weekly','monthly') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_parcels INT NOT NULL,
    delivered_count INT NOT NULL,
    returned_count INT NOT NULL DEFAULT 0,
    lost_count INT NOT NULL DEFAULT 0,
    cancelled_count INT NOT NULL DEFAULT 0,
    delivery_success_rate DECIMAL(5,2) NOT NULL,
    avg_delivery_hours_inside DECIMAL(8,2) NULL,
    avg_delivery_hours_outside DECIMAL(8,2) NULL,
    cod_collected_bdt DECIMAL(14,2) DEFAULT 0,
    cod_pending_bdt DECIMAL(14,2) DEFAULT 0,
    total_delivery_charge_bdt DECIMAL(14,2) DEFAULT 0,
    return_rate_percent DECIMAL(5,2) DEFAULT 0,
    on_time_rate_percent DECIMAL(5,2) NULL,
    calculated_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (courier_provider_id) REFERENCES courier_providers(id)
);
```

### delivery_zones
```sql
CREATE TABLE delivery_zones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    courier_provider_id BIGINT UNSIGNED NULL,
    zone_name VARCHAR(255) NOT NULL,
    zone_type ENUM('inside_dhaka','outside_dhaka','sub_city') NOT NULL,
    city VARCHAR(100) NULL,
    district VARCHAR(100) NULL,
    areas JSON NOT NULL,
    delivery_charge_bdt DECIMAL(8,2) NOT NULL,
    cod_charge_percent DECIMAL(5,2) DEFAULT 0,
    estimated_delivery_hours INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (courier_provider_id) REFERENCES courier_providers(id)
);
```

### fake_order_detections
```sql
CREATE TABLE fake_order_detections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    detection_type ENUM('ip_block','duplicate_order','suspicious_pattern','high_value_cod','manual_flag') NOT NULL,
    reason TEXT NOT NULL,
    action_taken ENUM('flagged','blocked_ip','order_cancelled','manual_review') NOT NULL,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_by BIGINT UNSIGNED NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (order_id) REFERENCES sales_orders(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id)
);
```

### ip_blacklist
```sql
CREATE TABLE ip_blacklist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    reason TEXT NOT NULL,
    blocked_by BIGINT UNSIGNED NOT NULL,
    blocked_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    UNIQUE INDEX ip_blacklist_ip_unique (ip_address),
    FOREIGN KEY (blocked_by) REFERENCES users(id)
);
```

---

## Module 4: Inventory & Warehouse

### warehouses, stock_items, stock_transactions, stock_transfers, transfer_items, stock_adjustments, adjustment_items, barcodes
(Full schemas as defined in 04-inventory-warehouse-module.md)

---

## Module 5A: Wholesale Sales

### customers
```sql
CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(50) NOT NULL,
    external_id VARCHAR(50) NULL,
    name VARCHAR(255) NOT NULL,
    business_name VARCHAR(255) NULL,
    type ENUM('wholesale','retail') NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    area VARCHAR(100) NULL,
    trade_license_no VARCHAR(100) NULL,
    nid_no VARCHAR(50) NULL,
    photo VARCHAR(500) NULL,
    credit_limit_bdt DECIMAL(14,2) DEFAULT 0,
    outstanding_balance_bdt DECIMAL(14,2) DEFAULT 0,
    price_tier_id BIGINT UNSIGNED NULL,
    source VARCHAR(50) NULL,
    source_detail VARCHAR(255) NULL,
    rating TINYINT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    assigned_salesman_id BIGINT UNSIGNED NULL,
    last_order_at TIMESTAMP NULL,
    total_orders INT DEFAULT 0,
    total_delivered_value_bdt DECIMAL(14,2) DEFAULT 0,
    sms_count INT DEFAULT 0,
    woo_customer_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX customers_customer_code_unique (customer_code),
    UNIQUE INDEX customers_external_id_unique (external_id),
    INDEX idx_customers_phone (phone),
    INDEX idx_customers_source (source),
    INDEX idx_customers_type (type),
    INDEX idx_customers_last_order (last_order_at),
    INDEX idx_customers_price_tier (price_tier_id),
    FOREIGN KEY (price_tier_id) REFERENCES price_tiers(id),
    FOREIGN KEY (assigned_salesman_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### customer_tags
```sql
CREATE TABLE customer_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6366F1',
    description TEXT NULL,
    is_auto_assign BOOLEAN DEFAULT FALSE,
    auto_assign_condition JSON NULL,
    linked_price_tier_id BIGINT UNSIGNED NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    customers_count INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX customer_tags_slug_unique (slug),
    FOREIGN KEY (linked_price_tier_id) REFERENCES price_tiers(id)
);
```

### customer_customer_tag (pivot)
```sql
CREATE TABLE customer_customer_tag (
    customer_id BIGINT UNSIGNED NOT NULL,
    customer_tag_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    PRIMARY KEY (customer_id, customer_tag_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_tag_id) REFERENCES customer_tags(id) ON DELETE CASCADE
);
```

### id_format_settings
```sql
CREATE TABLE id_format_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    prefix VARCHAR(10) DEFAULT '',
    suffix VARCHAR(10) DEFAULT '',
    separator VARCHAR(5) DEFAULT '-',
    include_year BOOLEAN DEFAULT FALSE,
    year_format VARCHAR(4) DEFAULT 'YYYY',
    include_month BOOLEAN DEFAULT FALSE,
    sequence_digits INT DEFAULT 4,
    sequence_start INT DEFAULT 1,
    reset_annually BOOLEAN DEFAULT FALSE,
    current_sequence INT DEFAULT 1,
    preview_example VARCHAR(50) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX id_format_entity_unique (entity_type)
);
```

### data_imports
```sql
CREATE TABLE data_imports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('customers','products','suppliers') NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NULL,
    file_size_kb INT NULL,
    total_rows INT NOT NULL,
    imported_count INT DEFAULT 0,
    updated_count INT DEFAULT 0,
    skipped_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    duplicate_action ENUM('skip','update','create_new') DEFAULT 'skip',
    column_mapping JSON NOT NULL,
    tag_mapping JSON NULL,
    source_mapping JSON NULL,
    default_values JSON NULL,
    error_report_path VARCHAR(500) NULL,
    status ENUM('uploading','mapping','validating','importing','completed','failed') DEFAULT 'uploading',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    duration_seconds INT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_data_imports_entity (entity_type),
    INDEX idx_data_imports_status (status),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### data_import_errors
```sql
CREATE TABLE data_import_errors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_import_id BIGINT UNSIGNED NOT NULL,
    row_number INT NOT NULL,
    error_type ENUM('validation','duplicate','format','missing_required','unknown') NOT NULL,
    field_name VARCHAR(100) NULL,
    field_value TEXT NULL,
    error_message TEXT NOT NULL,
    raw_row_data JSON NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (data_import_id) REFERENCES data_imports(id) ON DELETE CASCADE
);
```

### price_tiers, product_price_tiers, sales_orders, so_items, invoices, invoice_items, sales_returns, return_items
(Full schemas as defined in 05a-wholesale-sales-module.md)

---

## Module 5B: Retail Sales

### deliveries, online_payments
(Full schemas as defined in 05b-retail-sales-module.md)

---

## Module 6: Credit & Payment

### credit_ledger, payments, payment_allocations, supplier_payments, credit_adjustments
(Full schemas as defined in 06-credit-payment-module.md)

---

## Module 7: Accounts & Finance

### chart_of_accounts, accounts, journals, journal_entries, expenses, expense_categories
(Full schemas as defined in 07-accounts-finance-module.md)

---

## Module 8: WooCommerce Importer + Native Storefront

### module_settings
```sql
CREATE TABLE module_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    settings JSON NULL,
    activated_at TIMESTAMP NULL,
    deactivated_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX module_settings_module_unique (module)
);
```

**Default seed:**
```sql
INSERT INTO module_settings (module, is_active) VALUES
('wholesale_storefront', TRUE),
('retail_storefront', TRUE),
('reseller_panel', TRUE),
('conversation_ai', TRUE),
('woocommerce_importer', FALSE);
```

### storefront_settings
```sql
CREATE TABLE storefront_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module ENUM('wholesale_storefront','retail_storefront') NOT NULL,
    settings_key VARCHAR(100) NOT NULL,
    settings_value JSON NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX storefront_settings_unique (module, settings_key)
);
```

### woocommerce_imports
```sql
CREATE TABLE woocommerce_imports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_url VARCHAR(500) NOT NULL,
    store_type ENUM('wholesale','retail','both') DEFAULT 'both',
    consumer_key VARCHAR(500) NOT NULL,
    consumer_secret VARCHAR(500) NOT NULL,
    import_products BOOLEAN DEFAULT FALSE,
    import_categories BOOLEAN DEFAULT FALSE,
    import_customers BOOLEAN DEFAULT FALSE,
    import_orders BOOLEAN DEFAULT FALSE,
    products_total INT DEFAULT 0,
    products_imported INT DEFAULT 0,
    categories_total INT DEFAULT 0,
    categories_imported INT DEFAULT 0,
    customers_total INT DEFAULT 0,
    customers_imported INT DEFAULT 0,
    orders_total INT DEFAULT 0,
    orders_imported INT DEFAULT 0,
    error_count INT DEFAULT 0,
    error_report_path VARCHAR(500) NULL,
    status ENUM('connecting','scanning','ready','importing','completed','failed') DEFAULT 'connecting',
    connection_tested BOOLEAN DEFAULT FALSE,
    last_error TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### woocommerce_import_logs
```sql
CREATE TABLE woocommerce_import_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    woocommerce_import_id BIGINT UNSIGNED NOT NULL,
    entity_type ENUM('product','category','customer','order','image') NOT NULL,
    wc_entity_id BIGINT UNSIGNED NULL,
    erp_entity_id BIGINT UNSIGNED NULL,
    erp_entity_type VARCHAR(100) NULL,
    action ENUM('created','updated','skipped','failed') NOT NULL,
    wc_data JSON NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_wc_import_logs_import (woocommerce_import_id),
    INDEX idx_wc_import_logs_entity (entity_type, action),
    FOREIGN KEY (woocommerce_import_id) REFERENCES woocommerce_imports(id) ON DELETE CASCADE
);
```

### product_wc_mappings
```sql
CREATE TABLE product_wc_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NULL,
    wc_product_id BIGINT UNSIGNED NOT NULL,
    wc_product_sku VARCHAR(100) NULL,
    wc_store_url VARCHAR(500) NOT NULL,
    imported_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    UNIQUE INDEX product_wc_mappings_unique (product_id, product_variant_id, wc_store_url),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id)
);
```

### category_wc_mappings
```sql
CREATE TABLE category_wc_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    wc_category_id BIGINT UNSIGNED NOT NULL,
    wc_store_url VARCHAR(500) NOT NULL,
    imported_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    UNIQUE INDEX category_wc_mappings_unique (category_id, wc_store_url),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
```

### customer_wc_mappings
```sql
CREATE TABLE customer_wc_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    wc_customer_id BIGINT UNSIGNED NOT NULL,
    wc_store_url VARCHAR(500) NOT NULL,
    imported_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    UNIQUE INDEX customer_wc_mappings_unique (customer_id, wc_store_url),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);
```

### conversations
```sql
CREATE TABLE conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_uuid VARCHAR(50) NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    channel ENUM('messenger','whatsapp') NOT NULL,
    whatsapp_provider_id BIGINT UNSIGNED NULL,
    channel_conversation_id VARCHAR(255) NOT NULL,
    channel_customer_id VARCHAR(255) NOT NULL,
    channel_customer_name VARCHAR(255) NULL,
    channel_customer_avatar VARCHAR(500) NULL,
    status ENUM('active','idle','closed') DEFAULT 'active',
    assigned_to BIGINT UNSIGNED NULL,
    is_ai_active BOOLEAN DEFAULT TRUE,
    last_message_at TIMESTAMP NULL,
    last_human_reply_at TIMESTAMP NULL,
    last_ai_reply_at TIMESTAMP NULL,
    active_workflow_id BIGINT UNSIGNED NULL,
    tags JSON NULL,
    ai_context JSON NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX conversations_uuid_unique (conversation_uuid),
    INDEX idx_conversations_channel_customer (channel, channel_customer_id),
    INDEX idx_conversations_status (status),
    INDEX idx_conversations_customer (customer_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (whatsapp_provider_id) REFERENCES whatsapp_providers(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);
```

### conversation_messages
```sql
CREATE TABLE conversation_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    message_id VARCHAR(255) NOT NULL,
    sender_type ENUM('customer','ai_agent','human_agent','system') NOT NULL,
    sender_id BIGINT UNSIGNED NULL,
    content TEXT NOT NULL,
    content_type ENUM('text','image','file','product_card','order_card','payment_link','quick_reply','location','audio','video','sticker') DEFAULT 'text',
    attachments JSON NULL,
    intent_detected VARCHAR(100) NULL,
    confidence_score DECIMAL(5,2) NULL,
    replied_within_50s BOOLEAN NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    UNIQUE INDEX conversation_messages_mid_unique (message_id),
    INDEX idx_conv_msgs_conversation (conversation_id, created_at),
    INDEX idx_conv_msgs_sender (sender_type, sender_id),
    INDEX idx_conv_msgs_intent (intent_detected),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id)
);
```

### chat_carts
```sql
CREATE TABLE chat_carts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    status ENUM('active','converted_to_order','abandoned','expired') DEFAULT 'active',
    total_bdt DECIMAL(14,2) DEFAULT 0,
    notes TEXT NULL,
    converted_order_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_chat_carts_conversation (conversation_id),
    INDEX idx_chat_carts_status (status),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (converted_order_id) REFERENCES sales_orders(id)
);
```

### chat_cart_items
```sql
CREATE TABLE chat_cart_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_cart_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NULL,
    qty INT NOT NULL,
    price_bdt DECIMAL(12,2) NOT NULL,
    subtotal_bdt DECIMAL(14,2) NOT NULL,
    added_by ENUM('ai_agent','human_agent','customer') NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (chat_cart_id) REFERENCES chat_carts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id)
);
```

### agent_actions
```sql
CREATE TABLE agent_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NULL,
    action_type ENUM('product_search','add_to_cart','remove_from_cart','clear_cart','place_order','check_order_status','check_payment_status','send_payment_link','check_stock','get_price','create_customer','update_customer','send_return_request') NOT NULL,
    action_data JSON NULL,
    action_result JSON NULL,
    status ENUM('pending','executed','failed','requires_approval') DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    executed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_agent_actions_conversation (conversation_id),
    INDEX idx_agent_actions_type_status (action_type, status),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (message_id) REFERENCES conversation_messages(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);
```

### conversation_tags
```sql
CREATE TABLE conversation_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    UNIQUE INDEX conversation_tags_name_unique (name)
);
```

### quick_reply_templates
```sql
CREATE TABLE quick_reply_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(100) NULL,
    language VARCHAR(5) NOT NULL DEFAULT 'bn',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    INDEX idx_quick_replies_category_lang (category, language)
);
```

### whatsapp_providers
```sql
CREATE TABLE whatsapp_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    driver_class VARCHAR(255) NULL,
    api_type ENUM('official','unofficial','hybrid') NOT NULL,
    base_url VARCHAR(500) NULL,
    auth_config JSON NOT NULL,
    webhook_config JSON NULL,
    capabilities JSON NOT NULL,
    rate_limits JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 0,
    phone_number VARCHAR(20) NULL,
    phone_number_id VARCHAR(100) NULL,
    business_account_id VARCHAR(100) NULL,
    last_error TEXT NULL,
    last_connected_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX whatsapp_providers_slug_unique (slug),
    INDEX idx_wa_providers_active_default (is_active, is_default)
);
```

### whatsapp_provider_api_mappings
```sql
CREATE TABLE whatsapp_provider_api_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id BIGINT UNSIGNED NOT NULL,
    action ENUM('send_text','send_media','send_template','send_buttons','send_list','send_location','mark_read','check_number') NOT NULL,
    method ENUM('POST','GET','PUT') NOT NULL DEFAULT 'POST',
    endpoint VARCHAR(500) NOT NULL,
    headers_template JSON NOT NULL,
    body_template JSON NOT NULL,
    response_mapping JSON NULL,
    error_mapping JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE INDEX wa_api_mappings_provider_action (provider_id, action),
    FOREIGN KEY (provider_id) REFERENCES whatsapp_providers(id) ON DELETE CASCADE
);
```

### whatsapp_provider_logs
```sql
CREATE TABLE whatsapp_provider_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id BIGINT UNSIGNED NOT NULL,
    direction ENUM('incoming','outgoing') NOT NULL,
    message_id VARCHAR(255) NULL,
    conversation_id BIGINT UNSIGNED NULL,
    phone VARCHAR(20) NOT NULL,
    payload JSON NULL,
    status ENUM('sent','delivered','read','failed','rate_limited') NOT NULL,
    error_message TEXT NULL,
    latency_ms INT NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_wa_logs_provider_date (provider_id, created_at),
    INDEX idx_wa_logs_status (status),
    INDEX idx_wa_logs_phone (phone),
    FOREIGN KEY (provider_id) REFERENCES whatsapp_providers(id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
);
```

### chatbot_workflows
```sql
CREATE TABLE chatbot_workflows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    trigger_type ENUM('incoming_message','keyword','intent','schedule','event') NOT NULL,
    trigger_config JSON NULL,
    nodes JSON NOT NULL,
    edges JSON NOT NULL,
    status ENUM('active','draft','archived') DEFAULT 'draft',
    version INT DEFAULT 1,
    is_default BOOLEAN DEFAULT FALSE,
    channel ENUM('all','whatsapp','messenger') NULL,
    priority INT DEFAULT 0,
    execution_count INT DEFAULT 0,
    last_executed_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_workflows_trigger_status (trigger_type, status),
    INDEX idx_workflows_channel_priority (channel, priority DESC),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### chatbot_workflow_executions
```sql
CREATE TABLE chatbot_workflow_executions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NULL,
    executed_nodes JSON NULL,
    status ENUM('running','completed','failed','paused') DEFAULT 'running',
    error_message TEXT NULL,
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_wf_exec_workflow (workflow_id, created_at),
    INDEX idx_wf_exec_conversation (conversation_id),
    INDEX idx_wf_exec_status (status),
    FOREIGN KEY (workflow_id) REFERENCES chatbot_workflows(id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (message_id) REFERENCES conversation_messages(id)
);
```

---

## Module 10: Reporting & Monthly Auto-Report

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

## Entity Relationship Summary

```
users ──1:N── activity_log
users ──1:N── suppliers (created_by)
users ──1:N── purchase_orders (created_by, approved_by)
users ──1:N── shipments (created_by)
users ──1:N── sales_orders (created_by, confirmed_by)
users ──1:N── payments (created_by, received_by)
users ──1:N── courier_parcels (created_by)
users ──1:1── reseller_profiles
users ──1:N── conversations (assigned_to)
users ──1:N── conversation_messages (sender_id)
users ──1:N── chatbot_workflows (created_by)

suppliers ──1:N── supplier_contacts
suppliers ──1:N── product_suppliers
suppliers ──1:N── purchase_orders

categories ──1:N── categories (self-referential: parent_id)
categories ──1:N── products

products ──1:N── product_variants
products ──1:N── product_suppliers
products ──1:N── stock_items
products ──1:N── product_price_tiers
products ──1:N── product_wc_mappings
products ──1:N── courier_parcel_items

purchase_orders ──1:N── po_items
purchase_orders ──1:N── shipments
purchase_orders ──1:N── supplier_payments

shipments ──1:N── shipment_items
shipments ──1:N── shipment_costs
shipments ──1:N── shipment_documents
shipments ──1:N── landing_cost_allocations

courier_providers ──1:N── courier_parcels
courier_providers ──1:N── courier_bills
courier_providers ──1:N── courier_performance_metrics
courier_providers ──1:N── delivery_zones

courier_parcels ──1:N── courier_parcel_items
courier_parcels ──1:N── courier_status_history
courier_parcels ──1:N── courier_bill_items

courier_bills ──1:N── courier_bill_items

warehouses ──1:N── stock_items
warehouses ──1:N── stock_transactions

customers ──1:N── sales_orders
customers ──1:N── credit_ledger
customers ──1:N── payments (as payer)
customers ──1:N── courier_parcels
customers ──1:N── fake_order_detections
customers ──1:1── reseller_profiles
customers ──1:N── conversations
customers ──1:N── chat_carts
customers ──M:N── customer_tags (via customer_customer_tag)

customer_tags ──M:N── customers (via customer_customer_tag)
customer_tags ──1:1── price_tiers (linked_price_tier_id, optional)

price_tiers ──1:N── customers
price_tiers ──1:N── product_price_tiers

id_format_settings ── (singleton per entity_type, generates codes)

data_imports ──1:N── data_import_errors

sales_orders ──1:N── so_items
sales_orders ──1:1── invoices
sales_orders ──1:N── sales_returns
sales_orders ──1:N── courier_parcels
sales_orders ──1:N── fake_order_detections

invoices ──1:N── invoice_items
invoices ──1:N── payment_allocations
invoices ──1:N── courier_parcels

payments ──1:N── payment_allocations

chart_of_accounts ──1:N── journal_entries
chart_of_accounts ──1:N── accounts
chart_of_accounts ──1:N── expense_categories

journals ──1:N── journal_entries

woocommerce_imports ──1:N── woocommerce_import_logs
product_wc_mappings ──N:1── products
category_wc_mappings ──N:1── categories
customer_wc_mappings ──N:1── customers

module_settings ── (singleton per module, controls on/off)
storefront_settings ── (per-storefront configuration)

whatsapp_providers ──1:N── conversations
whatsapp_providers ──1:N── whatsapp_provider_api_mappings
whatsapp_providers ──1:N── whatsapp_provider_logs

conversations ──1:N── conversation_messages
conversations ──1:N── chat_carts
conversations ──1:N── agent_actions
conversations ──1:N── chatbot_workflow_executions
conversations ──1:N── whatsapp_provider_logs

chat_carts ──1:N── chat_cart_items

chatbot_workflows ──1:N── chatbot_workflow_executions
chatbot_workflows ──1:N── conversations (active_workflow_id)

users ──1:N── monthly_reports (generated_by)
users ──1:N── report_delivery_settings
monthly_reports ──1:N── report_delivery_settings (last_report_id)
```

## Migration Order

Migrations should be created in this order to satisfy foreign key constraints:

1. users, roles, permissions (Spatie tables)
2. currencies, exchange_rates
3. activity_log
4. suppliers, supplier_contacts
5. categories
6. products, product_variants
7. product_suppliers
8. warehouses
9. price_tiers
10. product_price_tiers
11. purchase_orders, po_items
12. shipments, shipment_items, shipment_costs, shipment_documents, shipment_status_history, landing_cost_allocations
13. stock_items, stock_transactions, stock_transfers, transfer_items, stock_adjustments, adjustment_items, barcodes
14. customers
15. customer_tags, customer_customer_tag
16. sales_orders, so_items
17. invoices, invoice_items
18. sales_returns, return_items
19. credit_ledger, payments, payment_allocations, supplier_payments, credit_adjustments
20. chart_of_accounts, accounts
21. journals, journal_entries
22. expenses, expense_categories
23. deliveries, online_payments
24. courier_providers, delivery_zones
25. courier_parcels, courier_parcel_items, courier_status_history
26. courier_bills, courier_bill_items, courier_performance_metrics
27. fake_order_detections, ip_blacklist
28. module_settings, storefront_settings
29. woocommerce_imports, woocommerce_import_logs
30. product_wc_mappings, category_wc_mappings, customer_wc_mappings
31. reseller_profiles, reseller_notifications
32. whatsapp_providers
33. whatsapp_provider_api_mappings, whatsapp_provider_logs
34. conversation_tags, quick_reply_templates
35. chatbot_workflows
36. conversations
37. conversation_messages
38. chat_carts, chat_cart_items
39. agent_actions
40. chatbot_workflow_executions
41. monthly_reports
42. report_delivery_settings
43. id_format_settings
44. data_imports, data_import_errors
45. product_price_history

## Seeder Order

1. Currencies (CNY, BDT, USD)
2. Exchange rates (initial)
3. Roles (admin, manager, accountant, salesman, storekeeper, procurement, reseller)
4. Permissions (all module permissions)
5. Role-Permission assignments
6. Admin user
7. Chart of Accounts (default COA)
8. Cash/Bank accounts
9. Expense categories
10. Price tiers (Bronze, Silver, Gold, Platinum)
11. Customer tags (VIP, Regular, New, Frozen)
12. ID format settings (customers: CUS-{YEAR}-{SEQ}, products: PRO-{YEAR}-{SEQ}, suppliers: SUP-{YEAR}-{SEQ})
13. Module settings (default activations: wholesale_storefront=true, retail_storefront=true, reseller_panel=true, conversation_ai=true, woocommerce_importer=false)
14. Storefront settings (default configurations)
15. Demo data (optional, for development)
