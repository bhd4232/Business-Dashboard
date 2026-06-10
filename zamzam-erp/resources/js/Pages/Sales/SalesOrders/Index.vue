<template>
  <AppLayout>
    <Head title="Sales Orders" />

    <!-- ── Page Header ──────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <ThreeDIcon name="sales" size="lg" />
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Sales Orders</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Manage wholesale &amp; retail sales orders</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <!-- Action Dropdown -->
        <div class="relative" ref="actionMenuRef">
          <button @click="showActionMenu = !showActionMenu"
            class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-sm font-medium px-4 py-2 rounded-lg transition-colors bg-white dark:bg-slate-800">
            <ZapIcon class="w-4 h-4" />
            Action
            <ChevronDownIcon class="w-3.5 h-3.5" />
          </button>
          <!-- Dropdown menu -->
          <div v-if="showActionMenu"
            class="absolute right-0 top-full mt-1.5 w-56 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-50 py-1.5 overflow-hidden">
            <button @click="openChangeStatus"
              class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors text-left">
              <ArrowRightLeftIcon class="w-4 h-4 text-primary-500" />
              Change Status
              <span v-if="selectedIds.length > 0"
                class="ml-auto text-xs bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 rounded-full px-1.5 py-0.5 font-semibold">
                {{ selectedIds.length }}
              </span>
            </button>
            <button @click="printInvoices"
              class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors text-left">
              <PrinterIcon class="w-4 h-4 text-slate-400" />
              Print Invoice
            </button>
            <button @click="exportCsv"
              class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors text-left">
              <DownloadIcon class="w-4 h-4 text-slate-400" />
              Export As CSV
            </button>
            <button @click="exportSummary"
              class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors text-left">
              <FileTextIcon class="w-4 h-4 text-slate-400" />
              Export Summary
            </button>
            <div class="border-t border-slate-100 dark:border-slate-700/60 my-1"></div>
            <p class="px-4 pt-1 pb-0.5 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide">Fast Action</p>
            <button @click="approveOrders"
              :disabled="selectedIds.length === 0 || approvingOrders"
              :class="selectedIds.length === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-emerald-50 dark:hover:bg-emerald-900/20'"
              class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 transition-colors text-left">
              <LoaderIcon v-if="approvingOrders" class="w-4 h-4 animate-spin text-emerald-500" />
              <CheckCircleIcon v-else class="w-4 h-4 text-emerald-500" />
              Approve Order(s)
            </button>
          </div>
        </div>

        <button @click="toggleTrash"
          :class="showTrash
            ? 'bg-red-50 border-red-300 text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300'
            : 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50'"
          class="inline-flex items-center gap-2 border text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <TrashIcon class="w-4 h-4" />
          Trash
          <span v-if="trashedCount" class="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5">{{ trashedCount }}</span>
        </button>
        <Link :href="route('sales-orders.create')"
          class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <PlusIcon class="w-4 h-4" /> New Order
        </Link>
      </div>
    </div>

    <!-- ── Main content (non-trash) ─────────────────────────────────────── -->
    <template v-if="!showTrash">

      <!-- Status Tab Bar + Sub-filters card -->
      <div class="bg-white dark:bg-slate-800 rounded-t-xl shadow-sm border border-slate-200 dark:border-slate-700">

        <!-- Status Tabs -->
        <div class="flex overflow-x-auto border-b border-slate-200 dark:border-slate-700 scrollbar-hide">
          <button
            v-for="tab in statusTabs" :key="tab.value"
            @click="setStatusTab(tab.value)"
            :class="[
              'flex items-center gap-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors flex-shrink-0',
              activeTab === tab.value
                ? 'text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400 -mb-px bg-primary-50/50 dark:bg-primary-900/10'
                : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/30'
            ]">
            {{ tab.label }}
            <span :class="[
              'text-xs font-bold rounded-full px-1.5 py-0.5 min-w-[20px] text-center',
              activeTab === tab.value
                ? 'bg-primary-600 text-white dark:bg-primary-500'
                : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
            ]">{{ tab.count }}</span>
          </button>
        </div>

        <!-- Sub-filter: Pending — duplicate phone warning -->
        <div v-if="activeTab === 'draft'" class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700/50">
          <div class="inline-flex items-center gap-2 text-xs bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-1.5 text-amber-700 dark:text-amber-300">
            <span class="w-3 h-3 bg-amber-400 rounded-sm inline-block shrink-0"></span>
            Multiple orders with the same phone number
          </div>
        </div>

        <!-- Sub-filter: Approved / Ready To Ship — district chips -->
        <div v-if="(activeTab === 'confirmed' || activeTab === 'picked') && districtCounts.length"
          class="px-4 py-2.5 flex flex-wrap gap-2 border-b border-slate-100 dark:border-slate-700/50">
          <button
            v-for="d in districtCounts" :key="d.district"
            @click="toggleSubFilter('district', d.district)"
            :class="localFilters.district === d.district
              ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:border-primary-500 dark:text-primary-300'
              : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:border-primary-400'"
            class="flex items-center gap-1.5 text-xs font-medium border rounded-lg px-3 py-1.5 transition-colors bg-white dark:bg-slate-800">
            {{ d.district }} <span class="font-bold">{{ d.count }}</span>
          </button>
        </div>

        <!-- Sub-filter: Cancelled — cancel reason chips -->
        <div v-if="activeTab === 'cancelled' && cancelReasonCounts.length"
          class="px-4 py-2.5 flex flex-wrap gap-2 border-b border-slate-100 dark:border-slate-700/50">
          <button
            v-for="r in cancelReasonCounts" :key="r.reason"
            @click="toggleSubFilter('cancel_reason', r.reason)"
            :class="localFilters.cancel_reason === r.reason
              ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:border-primary-500 dark:text-primary-300'
              : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:border-primary-400'"
            class="flex items-center gap-1.5 text-xs font-medium border rounded-lg px-3 py-1.5 transition-colors bg-white dark:bg-slate-800">
            {{ r.reason }} <span class="font-bold">{{ r.count }}</span>
          </button>
        </div>

        <!-- Sub-filter: On Hold — on_hold reason chips -->
        <div v-if="activeTab === 'on_hold' && onHoldReasonCounts.length"
          class="px-4 py-2.5 flex flex-wrap gap-2 border-b border-slate-100 dark:border-slate-700/50">
          <button
            v-for="r in onHoldReasonCounts" :key="r.reason"
            @click="toggleSubFilter('on_hold_reason', r.reason)"
            :class="localFilters.on_hold_reason === r.reason
              ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:border-primary-500 dark:text-primary-300'
              : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:border-primary-400'"
            class="flex items-center gap-1.5 text-xs font-medium border rounded-lg px-3 py-1.5 transition-colors bg-white dark:bg-slate-800">
            {{ r.reason }} <span class="font-bold">{{ r.count }}</span>
          </button>
        </div>

        <!-- Sub-filter: Delivered — payment status chips -->
        <div v-if="activeTab === 'delivered' && deliveredStats"
          class="px-4 py-2.5 flex flex-wrap items-center gap-2 border-b border-slate-100 dark:border-slate-700/50">
          <button
            @click="toggleSubFilter('payment', 'due')"
            :class="localFilters.payment === 'due'
              ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:border-primary-500 dark:text-primary-300'
              : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:border-primary-400'"
            class="flex items-center gap-1.5 text-xs font-medium border rounded-lg px-3 py-1.5 transition-colors bg-white dark:bg-slate-800">
            Payment Due <span class="font-bold">{{ deliveredStats.due_count }}</span>
          </button>
          <button
            @click="toggleSubFilter('payment', 'collected')"
            :class="localFilters.payment === 'collected'
              ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:border-primary-500 dark:text-primary-300'
              : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:border-primary-400'"
            class="flex items-center gap-1.5 text-xs font-medium border rounded-lg px-3 py-1.5 transition-colors bg-white dark:bg-slate-800">
            Payment Collected <span class="font-bold">{{ deliveredStats.collected_count }}</span>
          </button>
        </div>

        <!-- Sub-filter: Flagged — return type chips -->
        <div v-if="activeTab === 'flagged' && flaggedStats"
          class="px-4 py-2.5 flex flex-wrap gap-2 border-b border-slate-100 dark:border-slate-700/50">
          <button
            @click="toggleSubFilter('flag_reason', 'pending_return')"
            :class="localFilters.flag_reason === 'pending_return'
              ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:border-primary-500 dark:text-primary-300'
              : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:border-primary-400'"
            class="flex items-center gap-1.5 text-xs font-medium border rounded-lg px-3 py-1.5 transition-colors bg-white dark:bg-slate-800">
            Pending Returned <span class="font-bold">{{ flaggedStats.pending_returned }}</span>
          </button>
          <button
            @click="toggleSubFilter('flag_reason', 'returned')"
            :class="localFilters.flag_reason === 'returned'
              ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:border-primary-500 dark:text-primary-300'
              : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:border-primary-400'"
            class="flex items-center gap-1.5 text-xs font-medium border rounded-lg px-3 py-1.5 transition-colors bg-white dark:bg-slate-800">
            Returned <span class="font-bold">{{ flaggedStats.returned }}</span>
          </button>
          <button
            @click="toggleSubFilter('flag_reason', 'damaged')"
            :class="localFilters.flag_reason === 'damaged'
              ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:border-primary-500 dark:text-primary-300'
              : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:border-primary-400'"
            class="flex items-center gap-1.5 text-xs font-medium border rounded-lg px-3 py-1.5 transition-colors bg-white dark:bg-slate-800">
            Damaged <span class="font-bold">{{ flaggedStats.damaged }}</span>
          </button>
        </div>

        <!-- Sub-filter: In-Transit — delivery partner chips -->
        <div v-if="activeTab === 'dispatched' && deliveryPartnerCounts.length"
          class="px-4 py-2.5 flex flex-wrap gap-2 border-b border-slate-100 dark:border-slate-700/50">
          <button
            v-for="dp in deliveryPartnerCounts" :key="dp.delivery_partner"
            @click="toggleSubFilter('delivery_partner_filter', dp.delivery_partner)"
            :class="localFilters.delivery_partner_filter === dp.delivery_partner
              ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:border-primary-500 dark:text-primary-300'
              : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:border-primary-400'"
            class="flex items-center gap-2 text-xs font-medium border rounded-lg px-3 py-1.5 transition-colors bg-white dark:bg-slate-800">
            <span class="w-4 h-4 rounded-full bg-emerald-500 flex items-center justify-center shrink-0">
              <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
            <span class="capitalize">{{ dp.delivery_partner }}</span>
            <span class="font-bold">{{ dp.count }}</span>
          </button>
        </div>
      </div>

      <!-- Toolbar -->
      <div class="bg-white dark:bg-slate-800 border-l border-r border-slate-200 dark:border-slate-700 px-4 py-2.5 flex items-center gap-3 flex-wrap">
        <!-- Left -->
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <button @click="refreshOrders"
            class="p-2 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
            title="Refresh">
            <RefreshCwIcon :class="['w-4 h-4 transition-transform', refreshing ? 'animate-spin' : '']" />
          </button>

          <button @click="showAdvanced = !showAdvanced"
            :class="showAdvanced ? 'border-primary-400 text-primary-600 bg-primary-50 dark:bg-primary-900/20 dark:text-primary-400' : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700'"
            class="inline-flex items-center gap-1.5 text-sm font-medium border px-3 py-1.5 rounded-lg transition-colors">
            <SlidersHorizontalIcon class="w-4 h-4" /> Filter Column
          </button>

          <!-- Search -->
          <div class="relative flex-1 min-w-40 max-w-xs">
            <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500" />
            <input
              v-model="localFilters.search"
              @input="debouncedSearch"
              type="text"
              placeholder="Search order no, customer..."
              class="w-full pl-9 pr-4 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 bg-white dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
            />
          </div>
        </div>

        <!-- Right: per-page + pagination summary -->
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300">
            Show
            <select v-model="localFilters.per_page" @change="applyFilters"
              class="border border-slate-300 dark:border-slate-600 rounded-lg px-2 py-1 bg-white dark:bg-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:border-primary-500">
              <option value="10">10</option>
              <option value="20">20</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>

          <!-- Compact pagination nav -->
          <div v-if="orders.meta?.last_page > 1" class="flex items-center gap-1">
            <Link
              v-for="link in orders.links" :key="link.label"
              :href="link.url || '#'"
              :class="[
                'px-2.5 py-1 text-sm rounded-lg transition-colors',
                link.active ? 'bg-primary-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700',
                !link.url ? 'opacity-30 pointer-events-none' : '',
              ]"
              v-html="link.label"
            />
          </div>
        </div>
      </div>

      <!-- Advanced filters (toggleable) -->
      <div v-if="showAdvanced"
        class="bg-slate-50 dark:bg-slate-700/50 border-l border-r border-slate-200 dark:border-slate-700 px-4 py-3 flex flex-wrap gap-3">
        <select v-model="localFilters.customer_id" @change="applyFilters"
          class="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-1.5 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500">
          <option value="">All Customers</option>
          <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} {{ c.phone ? `(${c.phone})` : '' }}</option>
        </select>
        <select v-model="localFilters.source" @change="applyFilters"
          class="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-1.5 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500">
          <option value="">All Sources</option>
          <option v-for="s in sources" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
        <select v-model="localFilters.type" @change="applyFilters"
          class="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-1.5 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500">
          <option value="">All Types</option>
          <option value="wholesale">Wholesale</option>
          <option value="retail">Retail</option>
        </select>
        <button @click="resetFilters"
          class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 px-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
          Reset All
        </button>
      </div>

      <!-- Orders Table -->
      <div class="bg-white dark:bg-slate-800 rounded-b-xl shadow-sm border border-t-0 border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1300px] text-sm">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                <th class="px-3 py-3 w-10">
                  <input type="checkbox"
                    :checked="allSelected && orders.data.length > 0"
                    :indeterminate="selectedIds.length > 0 && !allSelected"
                    @change="toggleSelectAll"
                    class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-primary-600 focus:ring-primary-500 cursor-pointer" />
                </th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide">Invoice No</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide">Date</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide">Customer</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide">Pick Up Address</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide">Payments Info</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide">Delivery Partner</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide">Delivery Fee</th>
                <th v-if="showReasonColumn"
                  class="px-3 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide">
                  {{ reasonColumnLabel }}
                </th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide w-28">Internal Notes</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
              <!-- Empty state -->
              <tr v-if="orders.data.length === 0">
                <td :colspan="showReasonColumn ? 10 : 9" class="text-center py-16">
                  <ThreeDIcon name="sales" size="xl" class="mx-auto mb-3 opacity-30" />
                  <p class="text-sm text-slate-400 dark:text-slate-500">No sales orders found</p>
                  <Link :href="route('sales-orders.create')"
                    class="mt-3 inline-flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:underline">
                    <PlusIcon class="w-4 h-4" /> Create your first order
                  </Link>
                </td>
              </tr>

              <!-- Rows -->
              <tr v-for="order in orders.data" :key="order.id"
                class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors cursor-pointer"
                @click="$inertia.visit(route('sales-orders.show', order.id))">

                <!-- Checkbox -->
                <td class="px-3 py-3" @click.stop>
                  <input type="checkbox"
                    :value="order.id"
                    v-model="selectedIds"
                    class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-primary-600 focus:ring-primary-500 cursor-pointer" />
                </td>

                <!-- Invoice No -->
                <td class="px-3 py-3" @click.stop>
                  <!-- Row action icons -->
                  <div class="flex items-center gap-0.5 mb-1.5">
                    <Link :href="route('sales-orders.show', order.id)"
                      class="p-1 text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="View">
                      <InfoIcon class="w-3.5 h-3.5" />
                    </Link>
                    <button @click.stop="copyToClipboard(order.order_no)"
                      class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="Copy order no">
                      <CopyIcon class="w-3.5 h-3.5" />
                    </button>
                    <a
                      :href="route('invoices.create') + '?sales_order_id=' + order.id"
                      class="p-1 text-slate-400 hover:text-amber-600 dark:hover:text-amber-400 rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="Invoice">
                      <ReceiptIcon class="w-3.5 h-3.5" />
                    </a>
                    <Link v-if="order.status === 'draft'" :href="route('sales-orders.edit', order.id)"
                      class="p-1 text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="Edit">
                      <PencilIcon class="w-3.5 h-3.5" />
                    </Link>
                  </div>
                  <!-- Order number link -->
                  <Link :href="route('sales-orders.show', order.id)"
                    class="text-sm font-bold font-mono text-primary-600 dark:text-primary-400 hover:underline">
                    {{ order.order_no }}
                  </Link>
                  <!-- Source badge -->
                  <div class="mt-1">
                    <span :class="sourceBadge(order.source).cls"
                      class="text-xs font-medium px-2 py-0.5 rounded">
                      {{ sourceBadge(order.source).label }}
                    </span>
                  </div>
                </td>

                <!-- Date -->
                <td class="px-3 py-3">
                  <div class="space-y-1 text-xs whitespace-nowrap">
                    <div class="flex gap-2">
                      <span class="text-slate-400 dark:text-slate-500 w-14 shrink-0">Created</span>
                      <span class="text-slate-700 dark:text-slate-300">{{ formatDateTime(order.created_at) }}</span>
                    </div>
                    <div v-if="order.confirmed_at" class="flex gap-2">
                      <span class="text-slate-400 dark:text-slate-500 w-14 shrink-0">Shipping</span>
                      <span class="text-slate-700 dark:text-slate-300">{{ formatDateTime(order.confirmed_at) }}</span>
                    </div>
                    <div v-if="order.shipping_at" class="flex gap-2">
                      <span class="text-slate-400 dark:text-slate-500 w-14 shrink-0 capitalize">{{ statusDateLabel(order.status) }}</span>
                      <span class="text-slate-700 dark:text-slate-300">{{ formatDateTime(order.shipping_at) }}</span>
                    </div>
                    <div v-else-if="order.delivered_at" class="flex gap-2">
                      <span class="text-slate-400 dark:text-slate-500 w-14 shrink-0">Delivered</span>
                      <span class="text-slate-700 dark:text-slate-300">{{ formatDateTime(order.delivered_at) }}</span>
                    </div>
                  </div>
                </td>

                <!-- Customer -->
                <td class="px-3 py-3 max-w-[220px]">
                  <!-- Name + badge -->
                  <div class="flex items-start flex-wrap gap-1">
                    <button
                      @click.stop="openCustomerQuickView(order.customer?.id)"
                      class="text-sm font-semibold text-primary-600 dark:text-primary-400 hover:underline leading-tight text-left">
                      {{ order.customer?.business_name || order.customer?.name }}
                    </button>
                    <span :class="customerBadge(order.customer).cls"
                      class="text-xs font-bold px-1.5 py-0.5 rounded leading-none self-start mt-0.5">
                      {{ customerBadge(order.customer).label }}
                    </span>
                  </div>
                  <!-- Phone + copy + whatsapp -->
                  <div class="flex items-center gap-1 mt-1" @click.stop>
                    <span class="text-xs font-mono text-slate-600 dark:text-slate-400">{{ order.customer?.phone }}</span>
                    <button @click="copyToClipboard(order.customer?.phone)"
                      class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5 rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="Copy phone">
                      <CopyIcon class="w-3 h-3" />
                    </button>
                    <a v-if="order.customer?.phone"
                      :href="`https://wa.me/${formatPhoneWa(order.customer.phone)}`"
                      target="_blank"
                      @click.stop
                      class="text-emerald-500 hover:text-emerald-600 p-0.5 rounded hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors" title="WhatsApp">
                      <MessageCircleIcon class="w-3.5 h-3.5" />
                    </a>
                  </div>
                  <!-- Address -->
                  <div v-if="order.customer?.address || order.customer?.city"
                    class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-snug line-clamp-2">
                    {{ [order.customer?.address, order.customer?.city, order.customer?.area].filter(Boolean).join(', ') }}
                  </div>
                </td>

                <!-- Pick Up Address -->
                <td class="px-3 py-3">
                  <span class="inline-block bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-medium px-2 py-0.5 rounded">
                    Warehouse
                  </span>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-tight">ZamZam International</div>
                </td>

                <!-- Payments Info -->
                <td class="px-3 py-3 whitespace-nowrap">
                  <div class="space-y-0.5 text-xs">
                    <div class="flex gap-2">
                      <span class="text-slate-500 dark:text-slate-400 w-20 shrink-0">Sales Amount:</span>
                      <span class="text-slate-800 dark:text-slate-200 font-mono font-medium">BDT {{ formatNumber(order.total_bdt) }}</span>
                    </div>
                    <div class="flex gap-2">
                      <span class="text-slate-500 dark:text-slate-400 w-20 shrink-0">Paid Amount:</span>
                      <span class="text-slate-800 dark:text-slate-200 font-mono">BDT {{ formatNumber(order.paid_bdt) }}</span>
                    </div>
                    <div class="flex gap-2">
                      <span class="text-slate-500 dark:text-slate-400 w-20 shrink-0">Due Amount:</span>
                      <span :class="Number(order.due_bdt) > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-700 dark:text-slate-300'" class="font-mono">
                        BDT {{ formatNumber(order.due_bdt) }}
                      </span>
                    </div>
                  </div>
                </td>

                <!-- Delivery Partner -->
                <td class="px-3 py-3">
                  <template v-if="order.delivery_partner">
                    <div class="flex items-start gap-1.5">
                      <span class="w-5 h-5 rounded-full bg-emerald-500 flex items-center justify-center mt-0.5 shrink-0">
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                        </svg>
                      </span>
                      <div class="text-xs">
                        <div class="font-semibold text-slate-700 dark:text-slate-200 capitalize">{{ order.delivery_partner }}</div>
                        <div class="flex items-center gap-1 mt-0.5">
                          <span class="text-slate-400 dark:text-slate-500">Status:</span>
                          <span :class="deliveryStatusBadge(order.delivery_partner_status)"
                            class="text-xs font-medium px-1.5 py-0.5 rounded">
                            {{ order.delivery_partner_status || 'Pending' }}
                          </span>
                        </div>
                        <div v-if="order.delivery_partner_id" class="text-slate-400 dark:text-slate-500 mt-0.5">
                          ID: {{ order.delivery_partner_id }}
                        </div>
                      </div>
                    </div>
                  </template>
                  <span v-else class="text-slate-300 dark:text-slate-600 text-xs">—</span>
                </td>

                <!-- Delivery Fee -->
                <td class="px-3 py-3">
                  <div v-if="Number(order.delivery_charge_bdt) > 0" class="text-xs">
                    <div class="font-mono font-medium text-slate-800 dark:text-slate-200">
                      BDT {{ formatNumber(order.delivery_charge_bdt) }}
                    </div>
                    <div class="mt-1">
                      <span :class="order.delivery_type === 'express'
                        ? 'border-orange-300 text-orange-600 dark:border-orange-700 dark:text-orange-400'
                        : 'border-slate-300 dark:border-slate-600 text-slate-500 dark:text-slate-400'"
                        class="text-xs border rounded px-1.5 py-0.5 capitalize">
                        {{ order.delivery_type || 'Regular' }}
                      </span>
                    </div>
                  </div>
                  <span v-else class="text-slate-300 dark:text-slate-600 text-xs">—</span>
                </td>

                <!-- Reason column (Cancelled / On Hold) -->
                <td v-if="showReasonColumn" class="px-3 py-3 max-w-[180px]">
                  <span class="text-xs text-slate-600 dark:text-slate-400 leading-snug">
                    {{ activeTab === 'cancelled' ? order.cancel_reason : (activeTab === 'on_hold' ? order.on_hold_reason : order.flag_reason) }}
                  </span>
                </td>

                <!-- Internal Notes -->
                <td class="px-3 py-3" @click.stop>
                  <div class="flex items-start justify-between gap-1">
                    <span v-if="order.internal_notes" class="text-xs text-slate-500 dark:text-slate-400 leading-snug line-clamp-2 max-w-[70px]">
                      {{ order.internal_notes }}
                    </span>
                    <button
                      class="p-1 text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors ml-auto"
                      title="Add note">
                      <PlusIcon class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Bottom Pagination -->
        <div v-if="orders.meta && orders.meta.last_page > 1"
          class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-700">
          <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ orders.meta.from }}–{{ orders.meta.to }} of {{ orders.meta.total }} orders
          </p>
          <div class="flex gap-1">
            <Link
              v-for="link in orders.links" :key="link.label"
              :href="link.url || '#'"
              :class="[
                'px-3 py-1 text-sm rounded-lg transition-colors',
                link.active ? 'bg-primary-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700',
                !link.url ? 'opacity-40 pointer-events-none' : '',
              ]"
              v-html="link.label"
            />
          </div>
        </div>

        <!-- Bulk action bar (shown when items selected) -->
        <div v-if="selectedIds.length > 0"
          class="flex items-center gap-3 px-4 py-2.5 bg-primary-50 dark:bg-primary-900/20 border-t border-primary-200 dark:border-primary-800">
          <span class="text-sm font-medium text-primary-700 dark:text-primary-300">
            {{ selectedIds.length }} order{{ selectedIds.length > 1 ? 's' : '' }} selected
          </span>
          <button @click="selectedIds = []"
            class="text-xs text-primary-600 dark:text-primary-400 hover:underline">Clear</button>
        </div>
      </div>
    </template>

    <!-- ── Trash Section ──────────────────────────────────────────────── -->
    <div v-if="showTrash">
      <div class="flex items-center gap-3 mb-4">
        <button @click="toggleTrash"
          class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 border border-slate-300 dark:border-slate-600 px-3 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50">
          <ArrowLeftIcon class="w-4 h-4" /> Back
        </button>
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Trash</h2>
        <span class="text-sm text-slate-500 dark:text-slate-400">Deleted sales orders</span>
      </div>

      <div v-if="trashedLoading" class="text-center py-8 text-slate-400 dark:text-slate-500 text-sm">Loading...</div>

      <div v-else-if="trashedItems.length === 0"
        class="bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-10 text-center text-slate-400 dark:text-slate-500">
        <p class="text-sm">Trash is empty</p>
      </div>

      <div v-else class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="bg-red-50 dark:bg-red-900/20 border-b border-red-100 dark:border-red-800">
              <th class="text-left text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">Order No</th>
              <th class="text-left text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">Customer</th>
              <th class="text-left text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">Status</th>
              <th class="text-left text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">Deleted</th>
              <th class="text-right text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in trashedItems" :key="item.id"
              class="border-b border-slate-100 dark:border-slate-700 opacity-80 hover:opacity-100">
              <td class="px-6 py-4">
                <p class="text-sm font-semibold font-mono text-slate-700 dark:text-slate-300 line-through decoration-red-300">{{ item.order_no }}</p>
              </td>
              <td class="px-6 py-4">
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ item.customer?.name }}</p>
              </td>
              <td class="px-6 py-4">
                <span :class="statusColors[item.status] || 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'"
                  class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                  {{ item.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-xs text-red-500 dark:text-red-400">{{ formatDate(item.deleted_at) }}</td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button @click="restoreItem(item)"
                    class="inline-flex items-center gap-1 text-xs text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 font-medium px-2.5 py-1 border border-emerald-200 dark:border-emerald-800 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20">
                    <RotateCcwIcon class="w-3 h-3" /> Restore
                  </button>
                  <button v-if="canPurge" @click="confirmPurge(item)"
                    class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700 dark:text-red-400 font-medium px-2.5 py-1 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                    <XIcon class="w-3 h-3" /> Delete Forever
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Dialogs ────────────────────────────────────────────────────── -->
    <CustomerQuickViewModal
      :customer-id="customerQuickViewId"
      @close="customerQuickViewId = null"
    />
    <ConfirmDialog
      :show="!!deleteTarget"
      title="Move to trash?"
      :description="deleteTarget ? `Order '${deleteTarget.order_no}' will be moved to trash.` : ''"
      confirm-text="Move to Trash"
      variant="danger"
      :loading="deleting"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />
    <ConfirmDialog
      :show="!!purgeTarget"
      title="Permanently delete?"
      :description="purgeTarget ? `Order '${purgeTarget.order_no}' will be permanently deleted. This cannot be undone.` : ''"
      confirm-text="Delete Forever"
      variant="danger"
      :loading="purging"
      @confirm="executePurge"
      @cancel="purgeTarget = null"
    />

    <!-- ── Change Status Modal ─────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-150"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showStatusModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 dark:bg-black/60 backdrop-blur-sm"
          @click.self="showStatusModal = false">
          <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <div v-if="showStatusModal"
              class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md">
              <!-- Header -->
              <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                <div class="flex items-center gap-2.5">
                  <ArrowRightLeftIcon class="w-5 h-5 text-primary-500" />
                  <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Change Status</h3>
                </div>
                <button @click="showStatusModal = false"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                  <XIcon class="w-4 h-4" />
                </button>
              </div>

              <!-- Body -->
              <div class="px-6 py-5 space-y-4">
                <!-- Selected orders info -->
                <div v-if="selectedIds.length > 0"
                  class="flex items-center gap-2 text-sm bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg px-3 py-2 text-primary-700 dark:text-primary-300">
                  <CheckCircleIcon class="w-4 h-4 shrink-0" />
                  <span><strong>{{ selectedIds.length }}</strong> order{{ selectedIds.length > 1 ? 's' : '' }} selected</span>
                </div>
                <div v-else
                  class="flex items-center gap-2 text-sm bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2 text-amber-700 dark:text-amber-300">
                  <span class="w-4 h-4 shrink-0">⚠️</span>
                  No orders selected. The change will apply to all visible orders.
                </div>

                <!-- Status select -->
                <div>
                  <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">New Status</label>
                  <select v-model="statusModalForm.status"
                    class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30">
                    <option value="" disabled>-- Select status --</option>
                    <option value="draft">Pending (Draft)</option>
                    <option value="on_hold">On Hold</option>
                    <option value="confirmed">Approved</option>
                    <option value="processing">Processing</option>
                    <option value="picked">Ready To Ship</option>
                    <option value="dispatched">In-Transit</option>
                    <option value="delivered">Delivered</option>
                    <option value="flagged">Flagged</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="returned">Returned</option>
                  </select>
                </div>

                <!-- Reason field (conditional) -->
                <div v-if="statusModalNeedsReason">
                  <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    {{ statusModalReasonLabel }}
                    <span class="text-red-500">*</span>
                  </label>
                  <textarea
                    v-model="statusModalForm.reason"
                    rows="3"
                    :placeholder="`Enter ${statusModalReasonLabel.toLowerCase()}...`"
                    class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 resize-none"
                  ></textarea>
                </div>
              </div>

              <!-- Footer -->
              <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 dark:border-slate-700">
                <button @click="showStatusModal = false"
                  class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                  Cancel
                </button>
                <button @click="applyStatusChange"
                  :disabled="!statusModalForm.status || changingStatus || (statusModalNeedsReason && !statusModalForm.reason.trim())"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                  <LoaderIcon v-if="changingStatus" class="w-4 h-4 animate-spin" />
                  <ArrowRightLeftIcon v-else class="w-4 h-4" />
                  {{ changingStatus ? 'Applying...' : 'Apply Change' }}
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import {
  PlusIcon, TrashIcon, RotateCcwIcon, XIcon, ArrowLeftIcon,
  PencilIcon, InfoIcon, CopyIcon, PrinterIcon, MessageCircleIcon,
  RefreshCwIcon, SlidersHorizontalIcon, SearchIcon,
  ZapIcon, ChevronDownIcon, ArrowRightLeftIcon, CheckCircleIcon,
  DownloadIcon, FileTextIcon, LoaderIcon, ReceiptIcon,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import CustomerQuickViewModal from '@/Components/Sales/CustomerQuickViewModal.vue'
import { useToast } from '@/Composables/useToast'

// ─── Props ────────────────────────────────────────────────────────────────

const props = defineProps({
  orders:                { type: Object, required: true },
  customers:             { type: Array,  default: () => [] },
  statuses:              { type: Array,  default: () => [] },
  sources:               { type: Array,  default: () => [] },
  filters:               { type: Object, default: () => ({}) },
  statusTabs:            { type: Array,  default: () => [] },
  districtCounts:        { type: Array,  default: () => [] },
  cancelReasonCounts:    { type: Array,  default: () => [] },
  onHoldReasonCounts:    { type: Array,  default: () => [] },
  deliveredStats:        { type: Object, default: null },
  flaggedStats:          { type: Object, default: null },
  deliveryPartnerCounts: { type: Array,  default: () => [] },
})

const page = usePage()
const { success, error: showError } = useToast()

// ─── Permissions ──────────────────────────────────────────────────────────

const canPurge = computed(() =>
  page.props.auth?.user?.permissions?.includes('admin.trash.purge') ||
  page.props.auth?.user?.roles?.includes('admin')
)

// ─── Status colour map (used in trash view) ───────────────────────────────

const statusColors = {
  draft:      'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
  on_hold:    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
  confirmed:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  processing: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  picked:     'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
  dispatched: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  delivered:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
  flagged:    'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
  cancelled:  'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  returned:   'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300',
}

// ─── Active tab & sub-filter helpers ─────────────────────────────────────

const activeTab = computed(() => localFilters.value.status || '')

const showReasonColumn = computed(() =>
  ['cancelled', 'on_hold', 'flagged'].includes(activeTab.value)
)

const reasonColumnLabel = computed(() => {
  const map = { cancelled: 'Cancel Reason', on_hold: 'Hold Reason', flagged: 'Flag Reason' }
  return map[activeTab.value] || 'Reason'
})

function setStatusTab(value) {
  localFilters.value.status            = value
  localFilters.value.district          = ''
  localFilters.value.cancel_reason     = ''
  localFilters.value.on_hold_reason    = ''
  localFilters.value.flag_reason       = ''
  localFilters.value.payment           = ''
  localFilters.value.delivery_partner_filter = ''
  applyFilters()
}

function toggleSubFilter(key, value) {
  localFilters.value[key] = localFilters.value[key] === value ? '' : value
  applyFilters()
}

// ─── Filters ──────────────────────────────────────────────────────────────

const localFilters = ref({
  search:                  props.filters.search                  || '',
  status:                  props.filters.status                  || '',
  customer_id:             props.filters.customer_id             || '',
  source:                  props.filters.source                  || '',
  type:                    props.filters.type                    || '',
  per_page:                props.filters.per_page                || '20',
  district:                props.filters.district                || '',
  cancel_reason:           props.filters.cancel_reason           || '',
  on_hold_reason:          props.filters.on_hold_reason          || '',
  flag_reason:             props.filters.flag_reason             || '',
  payment:                 props.filters.payment                 || '',
  delivery_partner_filter: props.filters.delivery_partner_filter || '',
})

const showAdvanced = ref(false)

let searchTimer = null
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 400)
}

function applyFilters() {
  const params = {}
  const fieldsToKeep = [
    'search', 'status', 'customer_id', 'source', 'type', 'per_page',
    'district', 'cancel_reason', 'on_hold_reason', 'flag_reason',
    'payment', 'delivery_partner_filter',
  ]
  for (const key of fieldsToKeep) {
    if (localFilters.value[key]) params[key] = localFilters.value[key]
  }
  router.get(route('sales-orders.index'), params, { preserveState: true, replace: true })
}

function resetFilters() {
  localFilters.value = {
    search: '', status: '', customer_id: '', source: '', type: '',
    per_page: '20', district: '', cancel_reason: '', on_hold_reason: '',
    flag_reason: '', payment: '', delivery_partner_filter: '',
  }
  router.get(route('sales-orders.index'), {}, { preserveState: false })
}

// ─── Bulk select ──────────────────────────────────────────────────────────

const selectedIds = ref([])

const allSelected = computed(() =>
  orders.value.length > 0 && selectedIds.value.length === orders.value.length
)

// expose orders.data as reactive alias for allSelected
const orders = computed(() => props.orders)

function toggleSelectAll(e) {
  if (e.target.checked) {
    selectedIds.value = props.orders.data.map(o => o.id)
  } else {
    selectedIds.value = []
  }
}

// ─── Action Menu ──────────────────────────────────────────────────────────

const showActionMenu = ref(false)
const actionMenuRef  = ref(null)

function handleClickOutside(e) {
  if (actionMenuRef.value && !actionMenuRef.value.contains(e.target)) {
    showActionMenu.value = false
  }
}

onMounted(()  => document.addEventListener('click', handleClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside))

// ─── Change Status Modal ──────────────────────────────────────────────────

const showStatusModal = ref(false)
const changingStatus  = ref(false)
const statusModalForm = ref({ status: '', reason: '' })

const STATUS_REASON_MAP = {
  on_hold:   'Hold Reason',
  cancelled: 'Cancel Reason',
  flagged:   'Flag Reason',
}

const statusModalNeedsReason = computed(() =>
  Object.keys(STATUS_REASON_MAP).includes(statusModalForm.value.status)
)

const statusModalReasonLabel = computed(() =>
  STATUS_REASON_MAP[statusModalForm.value.status] || 'Reason'
)

function openChangeStatus() {
  showActionMenu.value   = false
  statusModalForm.value  = { status: '', reason: '' }
  showStatusModal.value  = true
}

async function applyStatusChange() {
  if (!statusModalForm.value.status) return
  if (statusModalNeedsReason.value && !statusModalForm.value.reason.trim()) return

  const targetIds = selectedIds.value.length > 0
    ? selectedIds.value
    : props.orders.data.map(o => o.id)

  if (targetIds.length === 0) {
    showError('No orders to update.')
    return
  }

  changingStatus.value = true
  try {
    const res = await window.axios.post('/api/v1/sales-orders/bulk-status', {
      ids:    targetIds,
      status: statusModalForm.value.status,
      reason: statusModalForm.value.reason || null,
    })
    success(res.data.message)
    showStatusModal.value = false
    selectedIds.value     = []
    router.reload()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to update status.')
  } finally {
    changingStatus.value = false
  }
}

// ─── Approve Orders (Fast Action) ─────────────────────────────────────────

const approvingOrders = ref(false)

async function approveOrders() {
  if (selectedIds.value.length === 0) return
  showActionMenu.value = false
  approvingOrders.value = true
  try {
    const res = await window.axios.post('/api/v1/sales-orders/bulk-status', {
      ids:    selectedIds.value,
      status: 'confirmed',
      reason: null,
    })
    success(res.data.message)
    selectedIds.value = []
    router.reload()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to approve orders.')
  } finally {
    approvingOrders.value = false
  }
}

// ─── Print Invoices ────────────────────────────────────────────────────────

function printInvoices() {
  showActionMenu.value = false

  if (selectedIds.value.length === 0) {
    // No selection — go to invoices list
    router.visit(route('invoices.index'))
    return
  }

  if (selectedIds.value.length > 1) {
    success('Select a single order to generate or view its invoice.')
    return
  }

  // 1 order selected — navigate to invoice create/show for that SO
  const soId = selectedIds.value[0]
  window.location.href = route('invoices.create') + '?sales_order_id=' + soId
}

// ─── Export CSV ────────────────────────────────────────────────────────────

function exportCsv() {
  showActionMenu.value = false
  const rows = selectedIds.value.length > 0
    ? props.orders.data.filter(o => selectedIds.value.includes(o.id))
    : props.orders.data

  const headers = [
    'Invoice No', 'Date', 'Customer', 'Phone',
    'Status', 'Sales Amount (BDT)', 'Paid Amount (BDT)', 'Due Amount (BDT)',
    'Delivery Partner', 'Delivery Fee (BDT)', 'Source',
  ]

  const csvRows = rows.map(o => [
    o.order_no,
    formatDateTime(o.created_at),
    o.customer?.business_name || o.customer?.name || '',
    o.customer?.phone || '',
    o.status,
    o.total_bdt || 0,
    o.paid_bdt  || 0,
    o.due_bdt   || 0,
    o.delivery_partner || '',
    o.delivery_charge_bdt || 0,
    o.source || '',
  ])

  const csvContent = [headers, ...csvRows]
    .map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(','))
    .join('\n')

  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = `sales-orders-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
  success('CSV exported.')
}

// ─── Export Summary ────────────────────────────────────────────────────────

function exportSummary() {
  showActionMenu.value = false
  const rows = selectedIds.value.length > 0
    ? props.orders.data.filter(o => selectedIds.value.includes(o.id))
    : props.orders.data

  const totalSales = rows.reduce((s, o) => s + Number(o.total_bdt || 0), 0)
  const totalPaid  = rows.reduce((s, o) => s + Number(o.paid_bdt  || 0), 0)
  const totalDue   = rows.reduce((s, o) => s + Number(o.due_bdt   || 0), 0)

  const lines = [
    `ZamZam ERP — Sales Orders Summary`,
    `Generated: ${new Date().toLocaleString()}`,
    `Orders: ${rows.length}`,
    ``,
    `Total Sales Amount : BDT ${formatNumber(totalSales)}`,
    `Total Paid Amount  : BDT ${formatNumber(totalPaid)}`,
    `Total Due Amount   : BDT ${formatNumber(totalDue)}`,
    ``,
    `--- Order List ---`,
    ...rows.map(o =>
      `${o.order_no}  |  ${o.customer?.name || ''}  |  BDT ${formatNumber(o.total_bdt)}  |  ${o.status}`
    ),
  ]

  const blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = `orders-summary-${new Date().toISOString().slice(0, 10)}.txt`
  a.click()
  URL.revokeObjectURL(url)
  success('Summary exported.')
}

// ─── Refresh ──────────────────────────────────────────────────────────────

const refreshing = ref(false)

function refreshOrders() {
  refreshing.value = true
  router.reload({ onFinish: () => { refreshing.value = false } })
}

// ─── Trash ────────────────────────────────────────────────────────────────

const showTrash      = ref(false)
const trashedItems   = ref([])
const trashedCount   = ref(0)
const trashedLoading = ref(false)

async function toggleTrash() {
  showTrash.value = !showTrash.value
  if (showTrash.value) loadTrashed()
}

async function loadTrashed() {
  trashedLoading.value = true
  try {
    const res = await window.axios.get('/api/v1/sales-orders/trashed')
    trashedItems.value = res.data
    trashedCount.value = res.data.length
  } catch {
    showError('Failed to load trash.')
  } finally {
    trashedLoading.value = false
  }
}

// ─── Customer Quick View ──────────────────────────────────────────────────

const customerQuickViewId = ref(null)

function openCustomerQuickView(id) {
  if (!id) return
  customerQuickViewId.value = id
}

// ─── Delete (soft) ────────────────────────────────────────────────────────

const deleteTarget = ref(null)
const deleting     = ref(false)

function confirmDelete(order) { deleteTarget.value = order }

async function executeDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await window.axios.delete(`/api/v1/sales-orders/${deleteTarget.value.id}`)
    success(`Order "${deleteTarget.value.order_no}" moved to trash.`)
    deleteTarget.value = null
    router.reload()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to delete.')
  } finally {
    deleting.value = false
  }
}

// ─── Restore ──────────────────────────────────────────────────────────────

async function restoreItem(item) {
  try {
    await window.axios.post(`/api/v1/sales-orders/${item.id}/restore`)
    success(`Order "${item.order_no}" restored.`)
    trashedItems.value = trashedItems.value.filter(i => i.id !== item.id)
    trashedCount.value = trashedItems.value.length
    router.reload()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to restore.')
  }
}

// ─── Purge ────────────────────────────────────────────────────────────────

const purgeTarget = ref(null)
const purging     = ref(false)

function confirmPurge(item) { purgeTarget.value = item }

async function executePurge() {
  if (!purgeTarget.value) return
  purging.value = true
  try {
    await window.axios.delete(`/api/v1/sales-orders/${purgeTarget.value.id}/force`)
    success(`Order "${purgeTarget.value.order_no}" permanently deleted.`)
    trashedItems.value = trashedItems.value.filter(i => i.id !== purgeTarget.value.id)
    trashedCount.value = trashedItems.value.length
    purgeTarget.value = null
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to delete permanently.')
  } finally {
    purging.value = false
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────

function formatNumber(v) {
  if (v === null || v === undefined) return '0'
  return Number(v).toLocaleString('en-BD')
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(d) {
  if (!d) return ''
  return new Date(d).toLocaleString('en-GB', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit', hour12: false,
  })
}

function formatPhoneWa(phone) {
  if (!phone) return ''
  let p = phone.replace(/[\s\-()]/g, '')
  if (p.startsWith('01')) p = '88' + p
  if (p.startsWith('+')) p = p.slice(1)
  return p
}

async function copyToClipboard(text) {
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    success('Copied!')
  } catch {
    showError('Copy failed.')
  }
}

// Customer badge (New / Regular / VIP) derived from total_orders
function customerBadge(customer) {
  const n = customer?.total_orders ?? 0
  if (n >= 50) return { label: 'VIP',     cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' }
  if (n  <= 2) return { label: 'NEW',     cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }
  return             { label: 'REGULAR', cls: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }
}

// Source badge
const SOURCE_BADGES = {
  erp:        { label: 'ERP',       cls: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' },
  storefront: { label: 'Web',       cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' },
  whatsapp:   { label: 'Whatsapp',  cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' },
  messenger:  { label: 'Messenger', cls: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300' },
  woocommerce:{ label: 'WOO',       cls: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300' },
  reseller:   { label: 'Reseller',  cls: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300' },
}
function sourceBadge(source) {
  return SOURCE_BADGES[source] || { label: source || 'ERP', cls: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }
}

// Delivery partner status badge
function deliveryStatusBadge(status) {
  const map = {
    pending:   'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    delivered: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    in_review: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  }
  const key = (status || 'pending').toLowerCase().replace(/\s+/g, '_')
  return map[key] || 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
}

// Label for the third row in Date column
function statusDateLabel(status) {
  const map = {
    picked:     'Ready',
    dispatched: 'In-Transit',
    delivered:  'Delivered',
    cancelled:  'Cancelled',
    flagged:    'Flagged',
    returned:   'Returned',
  }
  return map[status] || status
}
</script>
