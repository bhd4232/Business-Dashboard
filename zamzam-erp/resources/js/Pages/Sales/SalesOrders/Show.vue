<template>
  <AppLayout>
    <Head :title="order.order_no" />

    <!-- Back + Header -->
    <div class="mb-6">
      <Link :href="route('sales-orders.index')"
        class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all shadow-sm group mb-4">
        <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
        Sales Orders
      </Link>

      <div class="flex items-start justify-between">
        <div class="flex items-start gap-3">
          <ThreeDIcon name="sales" size="lg" />
          <div>
            <div class="flex items-center gap-3 flex-wrap">
              <h1 class="text-2xl font-semibold font-mono text-slate-900 dark:text-slate-100">{{ order.order_no }}</h1>
              <span :class="statusColors[order.status] || 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'"
                class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                {{ order.status }}
              </span>
              <span :class="order.type === 'wholesale'
                  ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                  : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'"
                class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                {{ order.type }}
              </span>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
              {{ order.customer?.name }}
              <span v-if="order.customer?.phone"> · {{ order.customer.phone }}</span>
            </p>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex items-center gap-2 flex-wrap justify-end">
          <Link
            v-if="order.status === 'draft'"
            :href="route('sales-orders.edit', order.id)"
            class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <EditIcon class="w-4 h-4" /> Edit
          </Link>

          <!-- Generate Invoice -->
          <Link
            v-if="!order.invoice && ['confirmed','processing','picked','dispatched','delivered'].includes(order.status)"
            :href="route('invoices.create') + '?sales_order_id=' + order.id"
            class="inline-flex items-center gap-2 border border-primary-300 dark:border-primary-700 hover:bg-primary-50 dark:hover:bg-primary-900/20 text-primary-700 dark:text-primary-400 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <ReceiptIcon class="w-4 h-4" /> Generate Invoice
          </Link>

          <!-- View Invoice (if already generated) -->
          <Link
            v-if="order.invoice"
            :href="route('invoices.show', order.invoice.id)"
            class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <ReceiptIcon class="w-4 h-4" /> View Invoice
          </Link>

          <button
            v-if="canReceivePayment"
            @click="openPaymentModal"
            class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <BanknoteIcon class="w-4 h-4" />
            Receive Payment
          </button>

          <button
            v-if="order.status === 'draft' && order.items && order.items.length > 0"
            @click="confirmOrder"
            :disabled="confirming"
            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            <LoaderIcon v-if="confirming" class="w-4 h-4 animate-spin" />
            <CheckCircleIcon v-else class="w-4 h-4" />
            {{ confirming ? 'Confirming...' : 'Confirm Order' }}
          </button>

          <button
            v-if="canCancel"
            @click="showCancelDialog = true"
            class="inline-flex items-center gap-2 border border-red-300 dark:border-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <XCircleIcon class="w-4 h-4" /> Cancel Order
          </button>
        </div>
      </div>
    </div>

    <!-- Info + Items grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Left column -->
      <div class="lg:col-span-1 space-y-4">

        <!-- Customer & Order Info card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
          <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4 pb-2 border-b border-slate-100 dark:border-slate-700">
            Order Information
          </h2>
          <dl class="space-y-3">
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Customer</dt>
              <dd class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ order.customer?.name }}</dd>
              <dd v-if="order.customer?.business_name" class="text-xs text-slate-500 dark:text-slate-400">{{ order.customer.business_name }}</dd>
            </div>
            <div v-if="order.customer?.phone">
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Phone</dt>
              <dd class="text-sm font-mono text-slate-700 dark:text-slate-300">{{ order.customer.phone }}</dd>
            </div>
            <div v-if="order.customer?.email">
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Email</dt>
              <dd class="text-sm text-slate-700 dark:text-slate-300">{{ order.customer.email }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Type</dt>
              <dd>
                <span :class="order.type === 'wholesale'
                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                    : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'"
                  class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                  {{ order.type }}
                </span>
              </dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Source</dt>
              <dd>
                <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                  {{ order.source }}
                </span>
              </dd>
            </div>
            <div v-if="order.priceTier">
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Price Tier</dt>
              <dd class="text-sm text-slate-700 dark:text-slate-300">{{ order.priceTier.name }}</dd>
            </div>
            <div v-if="order.delivery_address">
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Delivery Address</dt>
              <dd class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ order.delivery_address }}</dd>
            </div>
            <div v-if="order.notes">
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Notes</dt>
              <dd class="text-sm text-slate-700 dark:text-slate-300">{{ order.notes }}</dd>
            </div>
            <div v-if="order.internal_notes">
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Internal Notes</dt>
              <dd class="text-sm text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2">{{ order.internal_notes }}</dd>
            </div>
          </dl>
        </div>

        <!-- Attachments card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
          <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100 dark:border-slate-700">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Attachments</h2>
            <button type="button" @click="attachmentInputRef.click()"
              class="flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 font-medium hover:underline">
              <PlusCircleIcon class="w-3.5 h-3.5" /> Add
            </button>
            <input ref="attachmentInputRef" type="file" multiple class="hidden" @change="uploadAttachments" />
          </div>

          <!-- uploading indicator -->
          <p v-if="uploadingAttachment" class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mb-2">
            <LoaderIcon class="w-3.5 h-3.5 animate-spin" /> Uploading...
          </p>

          <ul v-if="localAttachments.length" class="space-y-1.5">
            <li v-for="att in localAttachments" :key="att.id"
              class="flex items-center gap-2 text-sm bg-slate-50 dark:bg-slate-700/50 rounded-lg px-3 py-2">
              <a :href="att.url" target="_blank" rel="noopener"
                class="flex-1 truncate text-primary-600 dark:text-primary-400 hover:underline text-xs"
                :title="att.original_name">{{ att.original_name }}</a>
              <span v-if="att.file_size" class="text-xs text-slate-400 dark:text-slate-500 shrink-0">
                {{ formatFileSize(att.file_size) }}
              </span>
              <button type="button" @click="deleteAttachment(att)"
                class="ml-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 transition-colors">
                <XIcon class="w-3.5 h-3.5" />
              </button>
            </li>
          </ul>
          <p v-else-if="!uploadingAttachment" class="text-xs text-slate-400 dark:text-slate-500">No attachments yet.</p>
        </div>

        <!-- Totals card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
          <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4 pb-2 border-b border-slate-100 dark:border-slate-700">
            Order Totals
          </h2>
          <dl class="space-y-2">
            <div class="flex justify-between items-center">
              <dt class="text-sm text-slate-500 dark:text-slate-400">Subtotal</dt>
              <dd class="text-sm font-mono text-slate-700 dark:text-slate-300">৳{{ formatNumber(order.subtotal_bdt) }}</dd>
            </div>
            <div v-if="Number(order.discount_bdt) > 0" class="flex justify-between items-center">
              <dt class="text-sm text-slate-500 dark:text-slate-400">Discount</dt>
              <dd class="text-sm font-mono text-red-600 dark:text-red-400">−৳{{ formatNumber(order.discount_bdt) }}</dd>
            </div>
            <div v-if="Number(order.delivery_charge_bdt) > 0" class="flex justify-between items-center">
              <dt class="text-sm text-slate-500 dark:text-slate-400">Delivery</dt>
              <dd class="text-sm font-mono text-slate-700 dark:text-slate-300">৳{{ formatNumber(order.delivery_charge_bdt) }}</dd>
            </div>
            <div class="flex justify-between items-center border-t border-slate-200 dark:border-slate-700 pt-2 mt-2">
              <dt class="text-sm font-semibold text-slate-800 dark:text-slate-200">Total</dt>
              <dd class="text-base font-bold font-mono text-slate-900 dark:text-slate-100">৳{{ formatNumber(order.total_bdt) }}</dd>
            </div>
            <div class="flex justify-between items-center">
              <dt class="text-sm text-slate-500 dark:text-slate-400">Paid</dt>
              <dd class="text-sm font-mono text-emerald-600 dark:text-emerald-400">৳{{ formatNumber(localPaidBdt) }}</dd>
            </div>

            <!-- Due / Fully Paid row -->
            <div class="flex justify-between items-center border-t border-slate-200 dark:border-slate-700 pt-2 mt-2">
              <dt class="text-sm font-semibold text-slate-800 dark:text-slate-200">Due</dt>
              <dd :class="Number(localDueBdt) > 0
                  ? 'text-base font-bold font-mono text-red-600 dark:text-red-400'
                  : 'text-base font-bold font-mono text-emerald-600 dark:text-emerald-400'">
                {{ Number(localDueBdt) > 0 ? '৳' + formatNumber(localDueBdt) : 'Paid' }}
              </dd>
            </div>

            <!-- Overpaid indicator -->
            <div v-if="overpaidAmount > 0"
              class="mt-2 flex items-center justify-between rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-3 py-2">
              <div class="flex items-center gap-1.5">
                <AlertTriangleIcon class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 shrink-0" />
                <span class="text-xs font-semibold text-amber-700 dark:text-amber-400">Overpaid</span>
              </div>
              <span class="text-xs font-mono font-bold text-amber-700 dark:text-amber-400">
                +৳{{ formatNumber(overpaidAmount) }}
              </span>
            </div>

            <!-- Underpaid / partial indicator (due but not zero) -->
            <div v-if="Number(localDueBdt) > 0"
              class="mt-1 flex items-center justify-between rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-3 py-2">
              <div class="flex items-center gap-1.5">
                <AlertCircleIcon class="w-3.5 h-3.5 text-red-500 dark:text-red-400 shrink-0" />
                <span class="text-xs font-semibold text-red-600 dark:text-red-400">Balance Due</span>
              </div>
              <span class="text-xs font-mono font-bold text-red-600 dark:text-red-400">
                ৳{{ formatNumber(localDueBdt) }}
              </span>
            </div>
          </dl>

          <!-- Quick Receive Payment -->
          <button
            v-if="canReceivePayment"
            @click="openPaymentModal"
            class="mt-4 w-full inline-flex items-center justify-center gap-2 text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 border border-primary-200 dark:border-primary-800 hover:bg-primary-50 dark:hover:bg-primary-950/30 py-2 rounded-lg transition-colors">
            <BanknoteIcon class="w-4 h-4" />
            Receive Payment
          </button>
        </div>

        <!-- Payment History card -->
        <div v-if="payments.length > 0" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Payment History</h2>
            <span class="text-xs text-slate-400 dark:text-slate-500">{{ payments.length }} record{{ payments.length !== 1 ? 's' : '' }}</span>
          </div>
          <ul class="divide-y divide-slate-100 dark:divide-slate-700">
            <li v-for="p in payments" :key="p.id" class="px-5 py-3.5 group">
              <!-- Row: badges + amount + edit button -->
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0 flex-wrap">
                  <span :class="methodColors[p.method] || 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                    class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium">
                    {{ methodLabel(p.method) }}
                  </span>
                  <span v-if="p.payment_type === 'advance'"
                    class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                    Advance
                  </span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <span class="font-mono text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                    ৳{{ formatNumber(p.amount_bdt) }}
                  </span>
                  <!-- Edit button — always visible on mobile, hover on desktop -->
                  <button
                    @click="openEditPaymentModal(p)"
                    title="Edit payment"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors opacity-100 lg:opacity-0 lg:group-hover:opacity-100">
                    <PencilIcon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
              <!-- Meta row -->
              <div class="mt-1 flex items-center gap-3 text-xs text-slate-400 dark:text-slate-500 flex-wrap">
                <span>{{ formatDate(p.payment_date) }}</span>
                <span v-if="p.reference" class="font-mono">Ref: {{ p.reference }}</span>
                <span v-if="p.received_by">by {{ p.received_by.name }}</span>
              </div>
              <p v-if="p.notes" class="mt-1 text-xs text-slate-500 dark:text-slate-400 italic">{{ p.notes }}</p>
            </li>
          </ul>
        </div>

        <!-- Timestamps card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
          <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4 pb-2 border-b border-slate-100 dark:border-slate-700">
            Activity
          </h2>
          <dl class="space-y-3">
            <div v-if="order.createdBy">
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Created By</dt>
              <dd class="text-sm text-slate-700 dark:text-slate-300">{{ order.createdBy.name }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Created At</dt>
              <dd class="text-sm text-slate-700 dark:text-slate-300">{{ formatDateTime(order.created_at) }}</dd>
            </div>
            <div v-if="order.confirmedBy">
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Confirmed By</dt>
              <dd class="text-sm text-slate-700 dark:text-slate-300">{{ order.confirmedBy.name }}</dd>
            </div>
            <div v-if="order.confirmed_at">
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Confirmed At</dt>
              <dd class="text-sm text-slate-700 dark:text-slate-300">{{ formatDateTime(order.confirmed_at) }}</dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Right column: Items table -->
      <div class="lg:col-span-2">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">
              Order Items
              <span class="ml-2 text-xs font-normal text-slate-400 dark:text-slate-500">
                {{ order.items ? order.items.length : 0 }} item{{ order.items && order.items.length !== 1 ? 's' : '' }}
              </span>
            </h2>
          </div>

          <div v-if="!order.items || order.items.length === 0"
            class="py-12 text-center text-slate-400 dark:text-slate-500 text-sm">
            No items in this order.
          </div>

          <table v-else class="w-full">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Product</th>
                <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-3">SKU</th>
                <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-3">Qty</th>
                <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-3">Unit Price</th>
                <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-3">Disc%</th>
                <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in order.items" :key="item.id"
                class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                <td class="px-6 py-4">
                  <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ item.product?.name }}</p>
                  <p v-if="item.variant" class="text-xs text-slate-500 dark:text-slate-400">{{ item.variant.variant_name }}</p>
                </td>
                <td class="px-4 py-4 text-xs font-mono text-slate-500 dark:text-slate-400">
                  {{ item.variant?.sku || item.product?.sku || '—' }}
                </td>
                <td class="px-4 py-4 text-right text-sm font-mono text-slate-700 dark:text-slate-300">{{ item.quantity }}</td>
                <td class="px-4 py-4 text-right text-sm font-mono text-slate-700 dark:text-slate-300">
                  ৳{{ formatNumber(item.unit_price_bdt) }}
                </td>
                <td class="px-4 py-4 text-right text-sm font-mono text-slate-500 dark:text-slate-400">
                  {{ item.discount_percent ? item.discount_percent + '%' : '—' }}
                </td>
                <td class="px-6 py-4 text-right text-sm font-mono font-semibold text-slate-800 dark:text-slate-200">
                  ৳{{ formatNumber(item.subtotal_bdt) }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                <td colspan="5" class="px-6 py-3 text-right text-sm font-semibold text-slate-700 dark:text-slate-300">Total</td>
                <td class="px-6 py-3 text-right text-sm font-mono font-bold text-slate-900 dark:text-slate-100">
                  ৳{{ formatNumber(order.subtotal_bdt) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- ─── Receive Payment Modal ──────────────────────────────────────── -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0">
        <div
          v-if="showPaymentModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4"
          @click.self="closePaymentModal">
          <div class="absolute inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm" @click="closePaymentModal" />

          <div class="relative z-10 w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center shrink-0">
                  <BanknoteIcon class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                  <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Receive Payment</h3>
                  <p class="text-xs text-slate-500 dark:text-slate-400">{{ order.order_no }} · Due: ৳{{ formatNumber(localDueBdt) }}</p>
                </div>
              </div>
              <button @click="closePaymentModal"
                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <XIcon class="w-4 h-4" />
              </button>
            </div>

            <form @submit.prevent="submitPayment" class="px-6 py-5 space-y-4">
              <!-- Payment Type -->
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2">Payment Type</label>
                <div class="grid grid-cols-2 gap-2">
                  <button type="button" @click="paymentForm.payment_type = 'payment'"
                    :class="paymentForm.payment_type === 'payment'
                      ? 'bg-primary-600 text-white border-primary-600'
                      : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
                    class="py-2 text-sm font-medium rounded-lg border transition-colors">
                    Payment
                  </button>
                  <button type="button" @click="paymentForm.payment_type = 'advance'"
                    :class="paymentForm.payment_type === 'advance'
                      ? 'bg-violet-600 text-white border-violet-600'
                      : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
                    class="py-2 text-sm font-medium rounded-lg border transition-colors">
                    Advance
                  </button>
                </div>
              </div>

              <!-- Amount -->
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">
                  Amount (৳) <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 text-sm font-medium">৳</span>
                  <input
                    v-model="paymentForm.amount_bdt"
                    type="number" min="0.01" step="0.01" required placeholder="0.00"
                    class="w-full pl-8 pr-4 py-2.5 text-sm font-mono border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                </div>
                <div v-if="Number(localDueBdt) > 0" class="mt-1.5 flex gap-1.5 flex-wrap">
                  <button type="button" @click="paymentForm.amount_bdt = localDueBdt"
                    class="text-xs px-2 py-1 rounded-md bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-colors font-mono">
                    Full Due ৳{{ formatNumber(localDueBdt) }}
                  </button>
                </div>
              </div>

              <!-- Method -->
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">
                  Payment Method <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-4">
                  <button
                    v-for="m in paymentMethods" :key="m.value"
                    type="button"
                    @click="paymentForm.method = m.value"
                    :class="paymentForm.method === m.value
                      ? 'bg-primary-600 text-white border-primary-600'
                      : 'bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
                    class="py-1.5 text-xs font-medium rounded-lg border transition-colors">
                    {{ m.label }}
                  </button>
                </div>
              </div>

              <!-- Date -->
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">
                  Payment Date <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="paymentForm.payment_date"
                  type="date" required
                  class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
              </div>

              <!-- Reference -->
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">
                  Reference / Transaction ID
                  <span class="text-slate-400 font-normal">(optional)</span>
                </label>
                <input
                  v-model="paymentForm.reference"
                  type="text" maxlength="100" placeholder="e.g. TXN123456"
                  class="w-full px-3 py-2.5 text-sm font-mono border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
              </div>

              <!-- Notes -->
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">
                  Notes <span class="text-slate-400 font-normal">(optional)</span>
                </label>
                <textarea
                  v-model="paymentForm.notes"
                  rows="2" maxlength="500" placeholder="Any additional notes..."
                  class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none" />
              </div>

              <div v-if="paymentError" class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3">
                <p class="text-sm text-red-700 dark:text-red-400">{{ paymentError }}</p>
              </div>

              <div class="flex items-center gap-3 pt-1">
                <button type="button" @click="closePaymentModal"
                  class="flex-1 py-2.5 text-sm font-medium border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                  Cancel
                </button>
                <button type="submit" :disabled="submittingPayment"
                  class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 text-sm font-semibold bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                  <LoaderIcon v-if="submittingPayment" class="w-4 h-4 animate-spin" />
                  <BanknoteIcon v-else class="w-4 h-4" />
                  {{ submittingPayment ? 'Saving...' : 'Record Payment' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ─── Edit Payment Modal ────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0">
        <div
          v-if="showEditPaymentModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4"
          @click.self="closeEditPaymentModal">
          <div class="absolute inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm" @click="closeEditPaymentModal" />

          <div class="relative z-10 w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                  <PencilIcon class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                  <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Edit Payment</h3>
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ order.order_no }}
                    <span v-if="editingPayment"> · Original: ৳{{ formatNumber(editingPayment.amount_bdt) }}</span>
                  </p>
                </div>
              </div>
              <button @click="closeEditPaymentModal"
                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <XIcon class="w-4 h-4" />
              </button>
            </div>

            <!-- Body -->
            <form @submit.prevent="submitEditPayment" class="px-6 py-5 space-y-4">
              <!-- Payment Type -->
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2">Payment Type</label>
                <div class="grid grid-cols-2 gap-2">
                  <button type="button" @click="editPaymentForm.payment_type = 'payment'"
                    :class="editPaymentForm.payment_type === 'payment'
                      ? 'bg-primary-600 text-white border-primary-600'
                      : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
                    class="py-2 text-sm font-medium rounded-lg border transition-colors">
                    Payment
                  </button>
                  <button type="button" @click="editPaymentForm.payment_type = 'advance'"
                    :class="editPaymentForm.payment_type === 'advance'
                      ? 'bg-violet-600 text-white border-violet-600'
                      : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
                    class="py-2 text-sm font-medium rounded-lg border transition-colors">
                    Advance
                  </button>
                </div>
              </div>

              <!-- Amount -->
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">
                  Amount (৳) <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 text-sm font-medium">৳</span>
                  <input
                    v-model="editPaymentForm.amount_bdt"
                    type="number" min="0.01" step="0.01" required placeholder="0.00"
                    class="w-full pl-8 pr-4 py-2.5 text-sm font-mono border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                </div>
                <!-- Live balance preview -->
                <div v-if="editAmountPreview !== null" class="mt-1.5">
                  <span v-if="editAmountPreview > 0"
                    class="inline-flex items-center gap-1 text-xs font-mono px-2 py-1 rounded-md bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400">
                    <AlertCircleIcon class="w-3 h-3" /> Due after edit: ৳{{ formatNumber(editAmountPreview) }}
                  </span>
                  <span v-else-if="editAmountPreview < 0"
                    class="inline-flex items-center gap-1 text-xs font-mono px-2 py-1 rounded-md bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400">
                    <AlertTriangleIcon class="w-3 h-3" /> Overpaid by: ৳{{ formatNumber(Math.abs(editAmountPreview)) }}
                  </span>
                  <span v-else
                    class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-md bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">
                    <CheckCircleIcon class="w-3 h-3" /> Fully paid
                  </span>
                </div>
              </div>

              <!-- Method -->
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">
                  Payment Method <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-4">
                  <button
                    v-for="m in paymentMethods" :key="m.value"
                    type="button"
                    @click="editPaymentForm.method = m.value"
                    :class="editPaymentForm.method === m.value
                      ? 'bg-primary-600 text-white border-primary-600'
                      : 'bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600'"
                    class="py-1.5 text-xs font-medium rounded-lg border transition-colors">
                    {{ m.label }}
                  </button>
                </div>
              </div>

              <!-- Date -->
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">
                  Payment Date <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="editPaymentForm.payment_date"
                  type="date" required
                  class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
              </div>

              <!-- Reference -->
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">
                  Reference / Transaction ID
                  <span class="text-slate-400 font-normal">(optional)</span>
                </label>
                <input
                  v-model="editPaymentForm.reference"
                  type="text" maxlength="100" placeholder="e.g. TXN123456"
                  class="w-full px-3 py-2.5 text-sm font-mono border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
              </div>

              <!-- Notes -->
              <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">
                  Notes <span class="text-slate-400 font-normal">(optional)</span>
                </label>
                <textarea
                  v-model="editPaymentForm.notes"
                  rows="2" maxlength="500" placeholder="Any additional notes..."
                  class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none" />
              </div>

              <!-- Error -->
              <div v-if="editPaymentError" class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3">
                <p class="text-sm text-red-700 dark:text-red-400">{{ editPaymentError }}</p>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-3 pt-1">
                <button type="button" @click="closeEditPaymentModal"
                  class="flex-1 py-2.5 text-sm font-medium border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                  Cancel
                </button>
                <button type="submit" :disabled="updatingPayment"
                  class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 text-sm font-semibold bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                  <LoaderIcon v-if="updatingPayment" class="w-4 h-4 animate-spin" />
                  <PencilIcon v-else class="w-4 h-4" />
                  {{ updatingPayment ? 'Saving...' : 'Update Payment' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Cancel Order Confirm Dialog -->
    <ConfirmDialog
      :show="showCancelDialog"
      title="Cancel this order?"
      :description="`Order '${order.order_no}' will be cancelled. This action cannot be undone.`"
      confirm-text="Cancel Order"
      variant="danger"
      :loading="cancelling"
      @confirm="executeCancelOrder"
      @cancel="showCancelDialog = false"
    />

  </AppLayout>
</template>

<script setup>
import { ref, computed, reactive, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
  ArrowLeftIcon,
  AlertCircleIcon,
  AlertTriangleIcon,
  BanknoteIcon,
  CheckCircleIcon,
  XCircleIcon,
  XIcon,
  EditIcon,
  PencilIcon,
  LoaderIcon,
  PlusCircleIcon,
  ReceiptIcon,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  order: { type: Object, required: true },
})

const { success, error: showError } = useToast()

// ─── Local reactive totals ─────────────────────────────────────────────────
const localPaidBdt = ref(props.order.paid_bdt)
const localDueBdt  = ref(props.order.due_bdt)
const payments     = ref(props.order.payments ?? [])

// ─── Attachments ──────────────────────────────────────────────────────────
const localAttachments   = ref(props.order.attachments ?? [])
const attachmentInputRef = ref(null)
const uploadingAttachment = ref(false)

async function uploadAttachments(e) {
  const files = Array.from(e.target.files || [])
  if (!files.length) return
  uploadingAttachment.value = true
  for (const file of files) {
    try {
      const fd = new FormData()
      fd.append('file', file)
      const { data } = await window.axios.post(
        `/api/v1/sales-orders/${props.order.id}/attachments`,
        fd,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )
      localAttachments.value.push(data.attachment)
    } catch {
      showError(`Failed to upload ${file.name}.`)
    }
  }
  e.target.value = ''
  uploadingAttachment.value = false
  success('Attachment(s) uploaded.')
}

async function deleteAttachment(att) {
  try {
    await window.axios.delete(`/api/v1/sales-orders/${props.order.id}/attachments/${att.id}`)
    localAttachments.value = localAttachments.value.filter(a => a.id !== att.id)
  } catch {
    showError('Failed to delete attachment.')
  }
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

// overpaid: paid > total
const overpaidAmount = computed(() => {
  const over = Number(localPaidBdt.value) - Number(props.order.total_bdt)
  return over > 0 ? over : 0
})

// ─── Status colours ────────────────────────────────────────────────────────
const statusColors = {
  draft:      'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
  confirmed:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  processing: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  picked:     'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
  dispatched: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  delivered:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
  cancelled:  'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  returned:   'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
}

// ─── Payment method config ─────────────────────────────────────────────────
const paymentMethods = [
  { value: 'cash',          label: 'Cash' },
  { value: 'bkash',         label: 'bKash' },
  { value: 'nagad',         label: 'Nagad' },
  { value: 'rocket',        label: 'Rocket' },
  { value: 'bank_transfer', label: 'Bank' },
  { value: 'cheque',        label: 'Cheque' },
  { value: 'other',         label: 'Other' },
]

const methodColors = {
  cash:          'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
  bkash:         'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300',
  nagad:         'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  rocket:        'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
  bank_transfer: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  cheque:        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  other:         'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
}

function methodLabel(method) {
  return paymentMethods.find(m => m.value === method)?.label ?? method
}

// ─── Permission helpers ────────────────────────────────────────────────────
const canCancel = computed(() =>
  ['draft', 'confirmed', 'processing'].includes(props.order.status)
)

const canReceivePayment = computed(() =>
  !['cancelled', 'returned'].includes(props.order.status)
)

// ─── Confirm Order ─────────────────────────────────────────────────────────
const confirming = ref(false)

async function confirmOrder() {
  confirming.value = true
  try {
    await window.axios.post(`/api/v1/sales-orders/${props.order.id}/confirm`)
    success('Order confirmed successfully!')
    router.visit(route('sales-orders.show', props.order.id))
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to confirm order.')
  } finally {
    confirming.value = false
  }
}

// ─── Cancel Order ──────────────────────────────────────────────────────────
const showCancelDialog = ref(false)
const cancelling       = ref(false)

async function executeCancelOrder() {
  cancelling.value = true
  try {
    await window.axios.post(`/api/v1/sales-orders/${props.order.id}/cancel`)
    success('Order cancelled.')
    showCancelDialog.value = false
    router.visit(route('sales-orders.show', props.order.id))
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to cancel order.')
  } finally {
    cancelling.value = false
  }
}

// ─── Receive Payment ───────────────────────────────────────────────────────
const showPaymentModal   = ref(false)
const submittingPayment  = ref(false)
const paymentError       = ref(null)

const paymentForm = reactive({
  amount_bdt:   '',
  method:       'cash',
  payment_type: 'payment',
  reference:    '',
  payment_date: todayISO(),
  notes:        '',
})

function openPaymentModal() {
  paymentError.value       = null
  paymentForm.amount_bdt   = ''
  paymentForm.method       = 'cash'
  paymentForm.payment_type = 'payment'
  paymentForm.reference    = ''
  paymentForm.payment_date = todayISO()
  paymentForm.notes        = ''
  showPaymentModal.value   = true
}

function closePaymentModal() {
  if (submittingPayment.value) return
  showPaymentModal.value = false
}

async function submitPayment() {
  paymentError.value      = null
  submittingPayment.value = true
  try {
    const res = await window.axios.post(
      `/api/v1/sales-orders/${props.order.id}/payments`,
      { ...paymentForm }
    )

    localPaidBdt.value = res.data.order.paid_bdt
    localDueBdt.value  = res.data.order.due_bdt

    if (res.data.payment) {
      payments.value = [...payments.value, res.data.payment]
        .sort((a, b) => String(a.payment_date).localeCompare(String(b.payment_date)) || a.id - b.id)
    }

    success('Payment recorded successfully!')
    showPaymentModal.value = false
  } catch (err) {
    paymentError.value = err.response?.data?.message
      || err.response?.data?.errors?.amount_bdt?.[0]
      || 'Failed to record payment.'
  } finally {
    submittingPayment.value = false
  }
}

// ─── Edit Payment ──────────────────────────────────────────────────────────
const showEditPaymentModal = ref(false)
const updatingPayment      = ref(false)
const editPaymentError     = ref(null)
const editingPayment       = ref(null)   // the payment object being edited

const editPaymentForm = reactive({
  amount_bdt:   '',
  method:       'cash',
  payment_type: 'payment',
  reference:    '',
  payment_date: todayISO(),
  notes:        '',
})

// Live balance preview: what would due_bdt be if this edit went through?
const editAmountPreview = computed(() => {
  const newAmt    = parseFloat(editPaymentForm.amount_bdt)
  const oldAmt    = editingPayment.value ? parseFloat(editingPayment.value.amount_bdt) : 0
  if (isNaN(newAmt) || !editingPayment.value) return null

  const newPaid = Math.max(0, Number(localPaidBdt.value) - oldAmt + newAmt)
  const due     = Number(props.order.total_bdt) - newPaid   // can be negative (overpaid)
  return due
})

function openEditPaymentModal(payment) {
  editingPayment.value          = payment
  editPaymentError.value        = null
  editPaymentForm.amount_bdt    = payment.amount_bdt
  editPaymentForm.method        = payment.method
  editPaymentForm.payment_type  = payment.payment_type
  editPaymentForm.reference     = payment.reference ?? ''
  // payment_date may come as "2026-05-20T00:00:00.000000Z" or "2026-05-20"
  editPaymentForm.payment_date  = String(payment.payment_date).split('T')[0]
  editPaymentForm.notes         = payment.notes ?? ''
  showEditPaymentModal.value    = true
}

function closeEditPaymentModal() {
  if (updatingPayment.value) return
  showEditPaymentModal.value = false
  editingPayment.value       = null
}

async function submitEditPayment() {
  editPaymentError.value = null
  updatingPayment.value  = true
  try {
    const res = await window.axios.put(
      `/api/v1/sales-orders/${props.order.id}/payments/${editingPayment.value.id}`,
      { ...editPaymentForm }
    )

    // Update local totals
    localPaidBdt.value = res.data.order.paid_bdt
    localDueBdt.value  = res.data.order.due_bdt

    // Replace the payment in the list and re-sort
    if (res.data.payment) {
      payments.value = payments.value
        .map(p => p.id === res.data.payment.id ? res.data.payment : p)
        .sort((a, b) => String(a.payment_date).localeCompare(String(b.payment_date)) || a.id - b.id)
    }

    success('Payment updated successfully!')
    showEditPaymentModal.value = false
    editingPayment.value       = null
  } catch (err) {
    editPaymentError.value = err.response?.data?.message
      || err.response?.data?.errors?.amount_bdt?.[0]
      || 'Failed to update payment.'
  } finally {
    updatingPayment.value = false
  }
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function todayISO() {
  return new Date().toISOString().split('T')[0]
}

function formatNumber(v) {
  if (v === null || v === undefined) return '0'
  return Number(v).toLocaleString('en-BD')
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('en-GB', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
</script>
