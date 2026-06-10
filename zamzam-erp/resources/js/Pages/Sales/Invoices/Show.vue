<template>
  <AppLayout>
    <Head :title="invoice.invoice_no" />

    <!-- ══════════════════════════════════════════════════
         SCREEN VIEW — Action Header (hidden on print)
    ══════════════════════════════════════════════════ -->
    <div class="print:hidden">
      <Link
        :href="route('invoices.index')"
        class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all shadow-sm group mb-4">
        <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
        Invoices
      </Link>

      <div class="flex items-start justify-between mb-6">
        <div class="flex items-start gap-3">
          <ThreeDIcon name="sales" size="lg" />
          <div>
            <div class="flex items-center gap-3 flex-wrap">
              <h1 class="text-2xl font-semibold font-mono text-slate-900 dark:text-slate-100">{{ invoice.invoice_no }}</h1>
              <span :class="statusColors[invoice.status]" class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                {{ invoice.status }}
              </span>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
              {{ invoice.customer?.name }}
              <span v-if="invoice.customer?.phone"> · {{ invoice.customer.phone }}</span>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap justify-end">
          <Link
            v-if="['draft','issued'].includes(invoice.status)"
            :href="route('invoices.edit', invoice.id)"
            class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <EditIcon class="w-4 h-4" /> Edit
          </Link>

          <button
            v-if="invoice.status === 'draft'"
            @click="issueInvoice"
            :disabled="actionLoading"
            class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            <LoaderIcon v-if="actionLoading === 'issue'" class="w-4 h-4 animate-spin" />
            <SendIcon v-else class="w-4 h-4" />
            {{ actionLoading === 'issue' ? 'Issuing...' : 'Issue Invoice' }}
          </button>

          <button
            v-if="invoice.sales_order_id && ['issued','partial'].includes(invoice.status)"
            @click="syncPayment"
            :disabled="actionLoading"
            class="inline-flex items-center gap-2 border border-emerald-300 dark:border-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            <LoaderIcon v-if="actionLoading === 'sync'" class="w-4 h-4 animate-spin" />
            <RefreshCwIcon v-else class="w-4 h-4" />
            {{ actionLoading === 'sync' ? 'Syncing...' : 'Sync Payment' }}
          </button>

          <button
            v-if="['draft','issued','partial','overdue'].includes(invoice.status)"
            @click="cancelInvoice"
            :disabled="actionLoading"
            class="inline-flex items-center gap-2 border border-red-300 dark:border-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            <LoaderIcon v-if="actionLoading === 'cancel'" class="w-4 h-4 animate-spin" />
            <XCircleIcon v-else class="w-4 h-4" />
            Cancel
          </button>

          <button
            @click="printPage"
            class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-900 dark:bg-slate-200 dark:hover:bg-white dark:text-slate-900 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <PrinterIcon class="w-4 h-4" /> Print / PDF
          </button>
        </div>
      </div>

      <!-- Error / Success Banner -->
      <div v-if="actionError" class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/50 rounded-xl text-sm text-red-700 dark:text-red-400 flex items-center gap-2">
        <AlertCircleIcon class="w-4 h-4 shrink-0" /> {{ actionError }}
      </div>
      <div v-if="actionSuccess" class="mb-4 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700/50 rounded-xl text-sm text-emerald-700 dark:text-emerald-400">
        {{ actionSuccess }}
      </div>

      <!-- Screen: 2-column detail cards -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-4">
          <!-- Invoice Meta -->
          <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3 pb-2 border-b border-slate-100 dark:border-slate-700">Invoice Details</h2>
            <dl class="space-y-2 text-sm">
              <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Invoice No</dt><dd class="font-mono font-semibold text-slate-800 dark:text-slate-200">{{ invoice.invoice_no }}</dd></div>
              <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Issue Date</dt><dd class="text-slate-700 dark:text-slate-300">{{ formatDate(invoice.issue_date) }}</dd></div>
              <div v-if="invoice.due_date" class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Due Date</dt><dd class="text-slate-700 dark:text-slate-300">{{ formatDate(invoice.due_date) }}</dd></div>
              <div v-if="invoice.sales_order" class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Sales Order</dt><dd><Link :href="route('sales-orders.show', invoice.sales_order_id)" class="font-mono text-primary-600 dark:text-primary-400 hover:underline">{{ invoice.sales_order.order_no }}</Link></dd></div>
            </dl>
          </div>
          <!-- Customer -->
          <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3 pb-2 border-b border-slate-100 dark:border-slate-700">Customer</h2>
            <dl class="space-y-1 text-sm">
              <dd class="font-semibold text-slate-800 dark:text-slate-200">{{ invoice.customer?.name }}</dd>
              <dd v-if="invoice.customer?.business_name" class="text-slate-500 dark:text-slate-400 text-xs">{{ invoice.customer.business_name }}</dd>
              <dd v-if="invoice.customer?.phone" class="font-mono text-slate-700 dark:text-slate-300">{{ invoice.customer.phone }}</dd>
              <dd v-if="invoice.customer?.email" class="text-slate-600 dark:text-slate-400 text-xs">{{ invoice.customer.email }}</dd>
            </dl>
          </div>
          <!-- Financials -->
          <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3 pb-2 border-b border-slate-100 dark:border-slate-700">Summary</h2>
            <dl class="space-y-2 text-sm">
              <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Subtotal</dt><dd class="font-mono text-slate-700 dark:text-slate-300">৳{{ formatNumber(invoice.subtotal_bdt) }}</dd></div>
              <div v-if="Number(invoice.discount_bdt) > 0" class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Discount</dt><dd class="font-mono text-red-500">−৳{{ formatNumber(invoice.discount_bdt) }}</dd></div>
              <div v-if="Number(invoice.delivery_charge_bdt) > 0" class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Delivery</dt><dd class="font-mono text-slate-600 dark:text-slate-300">+৳{{ formatNumber(invoice.delivery_charge_bdt) }}</dd></div>
              <div class="flex justify-between border-t border-slate-100 dark:border-slate-700 pt-2"><dt class="font-semibold text-slate-700 dark:text-slate-300">Total</dt><dd class="font-mono font-bold text-slate-900 dark:text-slate-100">৳{{ formatNumber(invoice.total_bdt) }}</dd></div>
              <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Paid</dt><dd class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">৳{{ formatNumber(localPaid) }}</dd></div>
              <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Due</dt><dd class="font-mono font-semibold" :class="Number(localDue) > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">৳{{ formatNumber(localDue) }}</dd></div>
            </dl>
          </div>
          <div v-if="invoice.notes" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Notes</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">{{ invoice.notes }}</p>
          </div>
        </div>

        <div class="lg:col-span-2">
          <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
              <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Invoice Items</h2>
            </div>
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-100 dark:border-slate-700">
                  <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">#</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Product</th>
                  <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Unit Price</th>
                  <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Disc%</th>
                  <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Qty</th>
                  <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                <tr v-if="!invoice.items || invoice.items.length === 0">
                  <td colspan="6" class="text-center py-10 text-slate-400">No items.</td>
                </tr>
                <tr v-for="(item, idx) in invoice.items" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                  <td class="px-4 py-3 text-slate-400 dark:text-slate-500">{{ idx + 1 }}</td>
                  <td class="px-4 py-3">
                    <div class="font-medium text-slate-800 dark:text-slate-200">{{ item.product?.name }}</div>
                    <div v-if="item.variant?.variant_name" class="text-xs text-slate-400 dark:text-slate-500">{{ item.variant.variant_name }}</div>
                    <div v-if="item.product?.weight_kg" class="text-xs text-slate-400 dark:text-slate-500">{{ item.product.weight_kg }} kg</div>
                  </td>
                  <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">৳{{ formatNumber(item.unit_price_bdt) }}</td>
                  <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">{{ Number(item.discount_percent) > 0 ? item.discount_percent + '%' : '—' }}</td>
                  <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">{{ item.quantity }}</td>
                  <td class="px-4 py-3 text-right font-mono font-semibold text-slate-800 dark:text-slate-200">৳{{ formatNumber(item.subtotal_bdt) }}</td>
                </tr>
              </tbody>
              <tfoot class="border-t-2 border-slate-200 dark:border-slate-600">
                <tr>
                  <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-slate-700 dark:text-slate-300">Total</td>
                  <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-slate-100">৳{{ formatNumber(invoice.total_bdt) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div><!-- end print:hidden -->

    <!-- ══════════════════════════════════════════════════
         PRINT VIEW — Zamzam International Invoice Format
    ══════════════════════════════════════════════════ -->
    <div class="hidden print:block font-sans text-black bg-white" style="font-family: Arial, sans-serif; font-size: 12px;">

      <!-- ─── MAIN COPY ──────────────────────────────── -->
      <div class="invoice-page">

        <!-- Top Header -->
        <table style="width:100%; border-collapse:collapse; margin-bottom:8px;">
          <tr>
            <td style="width:60%; vertical-align:top; padding:0;">
              <!-- Logo + Company Name -->
              <div style="display:flex; align-items:center; gap:10px;">
                <div style="font-size:22px; font-weight:900; letter-spacing:-1px; color:#000;">
                  Za<span style="color:#e8a000;">▲</span>Zam
                </div>
                <div style="border-left:2px solid #ccc; padding-left:10px;">
                  <div style="font-size:18px; font-weight:700; color:#000;">{{ settings.company_name || 'Zamzam International' }}</div>
                  <div v-if="settings.company_tagline" style="font-size:10px; color:#555;">{{ settings.company_tagline }}</div>
                  <div style="font-size:10px; color:#555;">Hotline: {{ settings.hotline_1 || '01811754232' }}</div>
                </div>
              </div>
            </td>
            <td style="width:40%; text-align:right; vertical-align:top;">
              <!-- Barcode-style invoice number (CSS bars) -->
              <div style="text-align:right; margin-bottom:4px;">
                <div class="barcode-strip" style="display:inline-block; height:28px; overflow:hidden; margin-bottom:2px;">
                  <span v-for="n in 40" :key="n" :style="`display:inline-block; width:${n % 3 === 0 ? 3 : 1}px; height:28px; margin:0 0.5px; background:#000; opacity:${n % 4 === 0 ? 0.3 : 1};`"></span>
                </div>
              </div>
              <div style="font-size:11px; font-weight:700;">Invoice No: <span style="font-size:13px;">{{ invoice.invoice_no }}</span></div>
              <div v-if="settings.show_delivery_partner !== false && invoice.sales_order?.delivery_partner" style="font-size:10px;">Delivery Partner: <strong>{{ invoice.sales_order.delivery_partner }}</strong></div>
              <div style="font-size:10px;">Date: <strong>{{ formatDate(invoice.issue_date) }}</strong></div>
            </td>
          </tr>
        </table>

        <!-- Bill To -->
        <div style="background:#f7f7f7; border:1px solid #ddd; padding:8px 12px; margin-bottom:8px;">
          <div style="font-size:10px; color:#666; margin-bottom:2px;">Bill To:</div>
          <div style="font-weight:700; font-size:13px;">{{ invoice.customer?.business_name || invoice.customer?.name }}</div>
          <div style="font-size:11px;">{{ invoice.customer?.phone }}</div>
          <div v-if="invoice.customer?.address" style="font-size:10px; color:#444;">
            {{ [invoice.customer.address, invoice.customer.area, invoice.customer.city].filter(Boolean).join(', ') }}
          </div>
        </div>

        <!-- Items Table -->
        <table style="width:100%; border-collapse:collapse; margin-bottom:0; border:1px solid #999;">
          <thead>
            <tr style="background:#333; color:#fff;">
              <th style="padding:6px 8px; text-align:center; font-size:10px; font-weight:600; border-right:1px solid #555; width:30px;">SL</th>
              <th v-if="settings.show_product_images !== false" style="padding:6px 8px; text-align:center; font-size:10px; font-weight:600; border-right:1px solid #555; width:45px;">Image</th>
              <th style="padding:6px 8px; text-align:left; font-size:10px; font-weight:600; border-right:1px solid #555;">Item Name</th>
              <th v-if="settings.show_product_weight !== false" style="padding:6px 8px; text-align:center; font-size:10px; font-weight:600; border-right:1px solid #555; width:55px;">Weight</th>
              <th style="padding:6px 8px; text-align:right; font-size:10px; font-weight:600; border-right:1px solid #555; width:90px;">Unit Price (BDT)</th>
              <th style="padding:6px 8px; text-align:center; font-size:10px; font-weight:600; border-right:1px solid #555; width:45px;">Disc%</th>
              <th style="padding:6px 8px; text-align:center; font-size:10px; font-weight:600; border-right:1px solid #555; width:40px;">Qty</th>
              <th style="padding:6px 8px; text-align:right; font-size:10px; font-weight:600; width:90px;">Amount (BDT)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in invoice.items" :key="item.id" style="border-bottom:1px solid #ddd;">
              <td style="padding:6px 8px; text-align:center; font-size:11px; border-right:1px solid #ddd; vertical-align:middle;">{{ idx + 1 }}</td>
              <td v-if="settings.show_product_images !== false" style="padding:4px 6px; text-align:center; border-right:1px solid #ddd; vertical-align:middle;">
                <img v-if="item.product?.image"
                  :src="item.product.image"
                  style="width:36px; height:36px; object-fit:cover; border-radius:3px;"
                  alt="" />
                <div v-else style="width:36px; height:36px; background:#f0f0f0; border-radius:3px; display:inline-flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:#bbb;">
                  {{ (item.product?.name || 'P').charAt(0) }}
                </div>
              </td>
              <td style="padding:6px 8px; font-size:11px; border-right:1px solid #ddd; vertical-align:middle;">
                <div style="font-weight:600;">{{ item.product?.name }}</div>
                <div v-if="item.variant?.variant_name" style="font-size:9px; color:#666;">{{ item.variant.variant_name }}</div>
              </td>
              <td v-if="settings.show_product_weight !== false" style="padding:6px 8px; text-align:center; font-size:11px; border-right:1px solid #ddd; vertical-align:middle; color:#555;">
                {{ item.product?.weight_kg ? item.product.weight_kg + ' kg' : '—' }}
              </td>
              <td style="padding:6px 8px; text-align:right; font-family:monospace; font-size:11px; border-right:1px solid #ddd; vertical-align:middle;">{{ formatNumberPlain(item.unit_price_bdt) }}</td>
              <td style="padding:6px 8px; text-align:center; font-size:11px; border-right:1px solid #ddd; vertical-align:middle;">{{ Number(item.discount_percent) > 0 ? item.discount_percent + '%' : '—' }}</td>
              <td style="padding:6px 8px; text-align:center; font-size:11px; border-right:1px solid #ddd; vertical-align:middle;">{{ item.quantity }}</td>
              <td style="padding:6px 8px; text-align:right; font-family:monospace; font-size:11px; vertical-align:middle;">{{ formatNumberPlain(item.subtotal_bdt) }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Footer info + Totals side by side -->
        <table style="width:100%; border-collapse:collapse; border:1px solid #999; border-top:none;">
          <tr>
            <!-- Left: contact info -->
            <td style="width:55%; vertical-align:top; padding:8px 10px; border-right:1px solid #ccc;">
              <div style="display:flex; flex-direction:column; gap:4px; font-size:10px; color:#444;">
                <div v-if="settings.facebook" class="contact-row">
                  <span style="margin-right:6px;">f</span>
                  <span>{{ settings.facebook }}</span>
                </div>
                <div v-if="settings.email" class="contact-row">
                  <span style="margin-right:6px;">✉</span>
                  <span>{{ settings.email }}</span>
                </div>
                <div v-if="settings.website" class="contact-row">
                  <span style="margin-right:6px;">⊕</span>
                  <span>{{ settings.website }}</span>
                </div>
                <div v-if="settings.address" class="contact-row">
                  <span style="margin-right:6px;">◎</span>
                  <span>{{ settings.address }}</span>
                </div>
              </div>
            </td>
            <!-- Right: totals -->
            <td style="width:45%; vertical-align:top; padding:0;">
              <table style="width:100%; border-collapse:collapse; font-size:11px;">
                <tr style="border-bottom:1px solid #eee;">
                  <td style="padding:5px 10px; text-align:right; color:#555;">Sub Total</td>
                  <td style="padding:5px 10px; text-align:right; font-family:monospace; font-weight:600; min-width:90px;">{{ formatNumberPlain(invoice.subtotal_bdt) }}</td>
                </tr>
                <tr v-if="Number(invoice.discount_bdt) > 0" style="border-bottom:1px solid #eee;">
                  <td style="padding:5px 10px; text-align:right; color:#555;">Discount</td>
                  <td style="padding:5px 10px; text-align:right; font-family:monospace; color:#c00;">- {{ formatNumberPlain(invoice.discount_bdt) }}</td>
                </tr>
                <tr v-if="Number(invoice.delivery_charge_bdt) > 0" style="border-bottom:1px solid #eee;">
                  <td style="padding:5px 10px; text-align:right; color:#555;">Delivery Charge</td>
                  <td style="padding:5px 10px; text-align:right; font-family:monospace;">{{ formatNumberPlain(invoice.delivery_charge_bdt) }}</td>
                </tr>
                <tr style="border-bottom:1px solid #eee;">
                  <td style="padding:5px 10px; text-align:right; color:#555;">Grand Total</td>
                  <td style="padding:5px 10px; text-align:right; font-family:monospace; font-weight:700;">{{ formatNumberPlain(invoice.total_bdt) }}</td>
                </tr>
                <tr v-if="Number(localPaid) > 0" style="border-bottom:1px solid #eee;">
                  <td style="padding:5px 10px; text-align:right; color:#555;">Payment</td>
                  <td style="padding:5px 10px; text-align:right; font-family:monospace;">- {{ formatNumberPlain(localPaid) }}</td>
                </tr>
                <tr style="background:#222; color:#fff;">
                  <td style="padding:6px 10px; text-align:right; font-weight:700;">Due Amount</td>
                  <td style="padding:6px 10px; text-align:right; font-family:monospace; font-weight:700;">{{ formatNumberPlain(localDue) }}</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <!-- Notes -->
        <div v-if="invoice.notes || settings.default_notes" style="border:1px solid #ccc; border-top:none; padding:6px 10px; font-size:10px; color:#555;">
          <strong>Notes:</strong> {{ invoice.notes || settings.default_notes }}
        </div>

        <!-- Contact Bar -->
        <div style="background:#333; color:#fff; padding:6px 12px; display:flex; justify-content:space-between; font-size:10px; margin-top:0; align-items:center; gap:6px; flex-wrap:wrap;">
          <span v-if="settings.hotline_2">Hotline: <strong>{{ settings.hotline_2 }}</strong></span>
          <span v-if="settings.hotline_2 && (settings.facebook || settings.hotline_3)">|</span>
          <span v-if="settings.facebook">Facebook Page: <strong>{{ settings.facebook }}</strong></span>
          <span v-if="settings.hotline_3 && settings.facebook">|</span>
          <span v-if="settings.hotline_3">WhatsApp: <strong>{{ settings.hotline_3 }}</strong></span>
        </div>

        <!-- Thank You -->
        <div style="text-align:center; padding:10px; font-size:11px; font-weight:600; color:#333; letter-spacing:0.5px;">
          {{ settings.thank_you_message || 'Thank You For Purchasing From Us.' }}
        </div>
      </div><!-- end main copy -->

      <!-- ─── CUT LINE ─────────────────────────────────── -->
      <div style="border-top:2px dashed #aaa; margin:14px 0 10px; text-align:right; padding-right:4px;">
        <span style="font-size:9px; color:#aaa;">✂</span>
      </div>

      <!-- ─── SECOND COPY (compact) ─────────────────────── -->
      <div class="invoice-page" style="padding-top:4px;">
        <table style="width:100%; border-collapse:collapse;">
          <tr>
            <td style="width:50%; vertical-align:top;">
              <div style="font-size:16px; font-weight:900; letter-spacing:-0.5px;">Za<span style="color:#e8a000;">▲</span>Zam</div>
              <div style="font-size:11px; font-weight:700; margin-top:1px;">{{ settings.company_name || 'Zamzam International' }}</div>
              <div style="font-size:9px; color:#666; margin-top:3px;">Bill To:</div>
              <div style="font-weight:700; font-size:12px;">{{ invoice.customer?.business_name || invoice.customer?.name }}</div>
              <div style="font-size:11px;">{{ invoice.customer?.phone }}</div>
              <div v-if="invoice.customer?.address" style="font-size:9px; color:#444;">
                {{ [invoice.customer.address, invoice.customer.area, invoice.customer.city].filter(Boolean).join(', ') }}
              </div>
            </td>
            <td style="width:50%; text-align:right; vertical-align:top;">
              <!-- Mini barcode -->
              <div style="text-align:right; margin-bottom:3px;">
                <span v-for="n in 30" :key="n" :style="`display:inline-block; width:${n % 3 === 0 ? 2 : 1}px; height:18px; margin:0 0.3px; background:#000; opacity:${n % 4 === 0 ? 0.3 : 1};`"></span>
              </div>
              <div style="font-size:11px; font-weight:700;">Invoice No: {{ invoice.invoice_no }}</div>
              <div v-if="settings.show_delivery_partner !== false && invoice.sales_order?.delivery_partner" style="font-size:9px;">Delivery Partner: <strong>{{ invoice.sales_order.delivery_partner }}</strong></div>
              <div style="font-size:9px;">Date: <strong>{{ formatDate(invoice.issue_date) }}</strong></div>
              <!-- Due amount highlight -->
              <div style="background:#222; color:#fff; padding:4px 8px; margin-top:6px; font-size:11px; font-weight:700; display:inline-block; border-radius:3px;">
                Due Amount: BDT {{ formatNumberPlain(localDue) }}
              </div>
            </td>
          </tr>
        </table>
      </div><!-- end second copy -->

    </div><!-- end print-only block -->

  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
  ArrowLeftIcon, EditIcon, SendIcon, RefreshCwIcon,
  XCircleIcon, PrinterIcon, AlertCircleIcon, LoaderIcon,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'

const props = defineProps({
  invoice:  { type: Object, required: true },
  settings: { type: Object, default: () => ({}) },
})

const actionLoading  = ref(null)
const actionError    = ref(null)
const actionSuccess  = ref(null)
const localPaid      = ref(Number(props.invoice.paid_bdt ?? 0))
const localDue       = ref(Number(props.invoice.due_bdt  ?? 0))
const invoice        = ref({ ...props.invoice })

const statusColors = {
  draft:     'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
  issued:    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  partial:   'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  paid:      'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
  overdue:   'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  cancelled: 'bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400',
}

// ─── Actions ──────────────────────────────────────────────────────────────

async function issueInvoice() {
  actionError.value = null; actionSuccess.value = null; actionLoading.value = 'issue'
  try {
    const res = await window.axios.post(`/api/v1/invoices/${invoice.value.id}/issue`)
    invoice.value.status = res.data.invoice.status
    actionSuccess.value  = 'Invoice issued successfully.'
  } catch (err) {
    actionError.value = err.response?.data?.message ?? 'Failed to issue invoice.'
  } finally { actionLoading.value = null }
}

async function cancelInvoice() {
  if (!confirm('Cancel this invoice? This action cannot be undone.')) return
  actionError.value = null; actionSuccess.value = null; actionLoading.value = 'cancel'
  try {
    const res = await window.axios.post(`/api/v1/invoices/${invoice.value.id}/cancel`)
    invoice.value.status = res.data.invoice.status
    actionSuccess.value  = 'Invoice cancelled.'
  } catch (err) {
    actionError.value = err.response?.data?.message ?? 'Failed to cancel invoice.'
  } finally { actionLoading.value = null }
}

async function syncPayment() {
  actionError.value = null; actionSuccess.value = null; actionLoading.value = 'sync'
  try {
    const res      = await window.axios.post(`/api/v1/invoices/${invoice.value.id}/sync-payment`)
    const updated  = res.data.invoice
    invoice.value.status = updated.status
    localPaid.value      = Number(updated.paid_bdt)
    localDue.value       = Number(updated.due_bdt)
    actionSuccess.value  = 'Payment synced from Sales Order.'
  } catch (err) {
    actionError.value = err.response?.data?.message ?? 'Failed to sync payment.'
  } finally { actionLoading.value = null }
}

function printPage() { window.print() }

// ─── Helpers ──────────────────────────────────────────────────────────────

function formatDate(d) {
  if (!d) return '—'
  const date = typeof d === 'string' ? d.split('T')[0] : d
  return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatNumber(v) {
  if (v === null || v === undefined) return '0'
  return Number(v).toLocaleString('en-BD')
}

// For print: standard comma-formatted with 2 decimal places
function formatNumberPlain(v) {
  if (v === null || v === undefined) return '0.00'
  return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<style>
@media print {
  /* Hide everything except the print block */
  aside,
  nav,
  header,
  footer,
  .print\:hidden {
    display: none !important;
  }

  /* Ensure print block fills page */
  .hidden.print\:block {
    display: block !important;
  }

  body, html, #app, main {
    margin: 0 !important;
    padding: 0 !important;
    background: white !important;
  }

  /* Page margins */
  @page {
    margin: 10mm 12mm;
    size: A4;
  }

  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>
