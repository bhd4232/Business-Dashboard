<template>
  <AppLayout>
    <Head title="Create Sales Order" />

    <!-- ═══════════════════════════════════════════════════════════
         STEP 3 — CHECKOUT
    ═══════════════════════════════════════════════════════════ -->
    <template v-if="step === 'checkout'">
      <div class="flex gap-8 min-h-[calc(100vh-120px)]">

        <!-- Left: summary info -->
        <div class="w-96 flex-shrink-0 space-y-4">
          <div class="flex items-center gap-2 mb-2">
            <button @click="step = 'delivery'"
              class="p-1.5 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
              <ChevronLeftIcon class="w-5 h-5" />
            </button>
            <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Checkout</h1>
          </div>

          <!-- Customer -->
          <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-4 space-y-3 bg-white dark:bg-slate-800">
            <div>
              <div class="text-xs text-slate-400 dark:text-slate-500">Customer Name</div>
              <div class="flex items-center gap-2 mt-1">
                <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ selectedCustomer?.business_name || selectedCustomer?.name }}</span>
                <span :class="tierBadgeCls" class="text-xs font-bold px-1.5 py-0.5 rounded">{{ tierLabel }}</span>
              </div>
            </div>
            <div>
              <div class="text-xs text-slate-400 dark:text-slate-500">Mobile Number</div>
              <div class="text-sm font-mono text-slate-800 dark:text-slate-200 mt-0.5">{{ selectedCustomer?.phone }}</div>
            </div>
            <div>
              <div class="text-xs text-slate-400 dark:text-slate-500">Customer Address</div>
              <div class="text-sm text-slate-700 dark:text-slate-300 mt-0.5 leading-snug">{{ customerFullAddress }}</div>
            </div>
          </div>

          <!-- Delivery -->
          <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-4 space-y-3 bg-white dark:bg-slate-800">
            <div>
              <div class="text-xs text-slate-400 dark:text-slate-500">Delivery Address</div>
              <div class="text-sm text-slate-700 dark:text-slate-300 mt-0.5 leading-snug">{{ form.delivery_address }}</div>
            </div>
            <div>
              <div class="text-xs text-slate-400 dark:text-slate-500">Shipping Date</div>
              <div class="text-sm text-slate-700 dark:text-slate-300 mt-0.5">{{ form.shipping_date || todayDisplay }}</div>
            </div>
            <div>
              <div class="text-xs text-slate-400 dark:text-slate-500">Pick-Up Location</div>
              <div class="text-sm text-slate-700 dark:text-slate-300 mt-0.5">ZamZam International</div>
            </div>
            <div>
              <div class="text-xs text-slate-400 dark:text-slate-500">Preferred Delivery Partner</div>
              <div class="text-sm font-medium text-slate-700 dark:text-slate-300 mt-0.5 capitalize">{{ form.delivery_partner || 'Steadfast' }}</div>
            </div>
            <div>
              <div class="text-xs text-slate-400 dark:text-slate-500">Order Source</div>
              <div class="flex items-center gap-1.5 mt-0.5">
                <span v-if="form.source === 'whatsapp'" class="text-emerald-500">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </span>
                <span class="text-sm text-slate-700 dark:text-slate-300 capitalize">{{ sourceLabel || '—' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Order Summary -->
        <div class="flex-1">
          <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100 mb-6">Order Summary</h2>

          <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden mb-6">
            <div v-for="(item, idx) in cart" :key="idx"
              class="flex items-start gap-4 p-4 border-b border-slate-100 dark:border-slate-700 last:border-0">
              <span class="text-sm text-slate-400 w-5 shrink-0">{{ idx + 1 }}</span>
              <!-- Image -->
              <div class="w-16 h-16 rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-700 flex-shrink-0 flex items-center justify-center">
                <img v-if="item.product.image" :src="item.product.image" class="w-full h-full object-cover" alt="" />
                <span v-else class="text-2xl font-bold text-slate-300 dark:text-slate-600">{{ item.product.name.charAt(0) }}</span>
              </div>
              <!-- Info -->
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-slate-800 dark:text-slate-200">
                  {{ item.product.name }}{{ item.variant ? ` — ${item.variant.variant_name}` : '' }}
                </div>
                <div class="text-sm text-primary-600 dark:text-primary-400 font-mono mt-0.5">
                  SKU: {{ item.variant?.sku || item.product.sku }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 space-x-3">
                  <span>Amount: {{ formatNum(item.unit_price_bdt) }}</span>
                  <span v-if="item.product.weight">Weight: {{ item.product.weight }} kg</span>
                  <span>Qty: {{ item.quantity }}</span>
                </div>
                <div v-if="item.discount_percent > 0" class="text-xs text-emerald-600 dark:text-emerald-400 mt-0.5">
                  {{ item.discount_percent.toFixed(2) }}% discount applied
                </div>
              </div>
              <!-- Price -->
              <div class="text-right shrink-0">
                <div v-if="item.originalPrice > item.unit_price_bdt" class="text-xs text-slate-400 line-through">
                  {{ formatNum(item.originalPrice * item.quantity) }}
                </div>
                <div class="font-bold text-slate-800 dark:text-slate-200">{{ formatNum(itemSubtotal(item)) }}</div>
              </div>
            </div>
          </div>

          <!-- Price breakdown -->
          <div class="max-w-sm ml-auto space-y-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4">
            <div class="flex justify-between text-sm text-slate-600 dark:text-slate-400">
              <span>Subtotal:</span>
              <span class="font-mono">{{ formatNum(cartSubtotal) }}</span>
            </div>
            <div class="flex justify-between text-sm text-slate-600 dark:text-slate-400">
              <span>Delivery Fee:</span>
              <span class="font-mono">{{ formatNum(deliveryFee) }}</span>
            </div>
            <div class="flex justify-between text-sm text-slate-600 dark:text-slate-400">
              <span>Discount:</span>
              <span class="font-mono text-red-500">-{{ formatNum(effectiveDiscount) }}</span>
            </div>
            <div class="flex justify-between text-sm text-slate-600 dark:text-slate-400">
              <span>Advance Payment:</span>
              <span class="font-mono text-red-500">-{{ formatNum(form.paid_bdt) }}</span>
            </div>
            <div class="flex justify-between font-bold text-base border-t border-slate-200 dark:border-slate-700 pt-2 text-slate-800 dark:text-slate-200">
              <span>Total:</span>
              <span class="font-mono">{{ formatNum(orderTotal) }}</span>
            </div>

            <!-- Auto-approve -->
            <label class="flex items-center gap-2 mt-2 cursor-pointer">
              <input type="checkbox" v-model="autoApprove"
                class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-primary-600 focus:ring-primary-500" />
              <span class="text-sm text-slate-600 dark:text-slate-400">Auto-Approve Order</span>
            </label>
          </div>

          <!-- Action buttons -->
          <div class="flex items-center justify-end gap-3 mt-6">
            <Link :href="route('sales-orders.index')"
              class="px-5 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
              Cancel
            </Link>
            <button @click="submit" :disabled="saving"
              class="px-8 py-2 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition-colors flex items-center gap-2">
              <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
              Place Order
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- ═══════════════════════════════════════════════════════════
         STEP 1 & 2 — MAIN 3-COLUMN LAYOUT
    ═══════════════════════════════════════════════════════════ -->
    <template v-else>
      <!-- Page title bar -->
      <div class="flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 mb-3">
        <Link :href="route('sales-orders.index')" class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">Orders</Link>
        <span>•</span>
        <span class="text-slate-800 dark:text-slate-200 font-medium">Create Orders</span>
      </div>

      <!-- 3-column grid — negative margins cancel AppLayout p-6 -->
      <div class="-mx-6 -mb-6 flex border-t border-slate-200 dark:border-slate-700"
        style="height: calc(100vh - 105px)">

        <!-- ────────────────────────────────────────────────────────
             LEFT COLUMN — Customer Panel
        ──────────────────────────────────────────────────────── -->
        <div class="w-[340px] flex-shrink-0 border-r border-slate-200 dark:border-slate-700 flex flex-col bg-white dark:bg-slate-800">
          <!-- Header -->
          <div class="px-4 pt-4 pb-3 border-b border-slate-100 dark:border-slate-700 flex-shrink-0">
            <h1 class="text-base font-bold text-slate-900 dark:text-slate-100 mb-3">Create Sales Order</h1>

            <!-- Customer search -->
            <div class="border-2 border-red-300 dark:border-red-700/70 rounded-lg bg-slate-50 dark:bg-slate-700/50 px-3 py-2 relative">
              <div class="text-xs text-slate-400 dark:text-slate-500 mb-0.5">Search Customer</div>
              <input
                v-model="customerSearch"
                @input="onCustomerSearch"
                @focus="onCustomerFocus"
                @blur="hideCustDrop"
                type="text"
                placeholder="+880"
                class="w-full text-sm text-slate-800 dark:text-slate-200 bg-transparent outline-none placeholder:text-slate-400 dark:placeholder:text-slate-500"
              />
              <!-- Suggestions dropdown -->
              <div v-if="showCustDrop"
                class="absolute z-50 top-full left-0 right-0 mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl max-h-52 overflow-y-auto">
                <!-- Recent header (when no search text) -->
                <div v-if="!customerSearch.trim()"
                  class="px-3 py-1.5 text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50">
                  Recent Customers
                </div>
                <button v-for="c in dropdownCustomers" :key="c.id"
                  @mousedown.prevent="selectCustomer(c)"
                  class="w-full text-left px-3 py-2 hover:bg-primary-50 dark:hover:bg-primary-900/20 border-b border-slate-100 dark:border-slate-700 last:border-0 transition-colors">
                  <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ c.business_name || c.name }}</div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ c.phone }}</div>
                </button>
              </div>
            </div>
          </div>

          <!-- Customer info (scrollable) -->
          <div class="flex-1 overflow-y-auto">
            <template v-if="selectedCustomer">
              <!-- Address (shown when searching) -->
              <div v-if="selectedCustomer.address" class="px-4 py-2 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/30 border-b border-slate-100 dark:border-slate-700">
                {{ [selectedCustomer.address, selectedCustomer.city, selectedCustomer.area].filter(Boolean).join(', ') }}
              </div>

              <div class="p-4 space-y-4">

                <!-- Delivery Success Rate -->
                <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                  <div class="px-3 py-2 flex items-center justify-between bg-white dark:bg-slate-800">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Delivery Success Rate</span>
                    <div class="flex items-center gap-1.5">
                      <button class="w-6 h-6 bg-primary-600 text-white rounded text-xs flex items-center justify-center font-bold">C</button>
                      <button @click="showDeliveryStats = !showDeliveryStats"
                        class="text-xs text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-700 rounded px-2 py-0.5 flex items-center gap-0.5 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors">
                        <ChevronUpIcon v-if="showDeliveryStats" class="w-3 h-3" />
                        <ChevronDownIcon v-else class="w-3 h-3" />
                        Details
                      </button>
                    </div>
                  </div>
                  <div class="px-3 pb-3">
                    <!-- Progress bar -->
                    <div class="w-full bg-slate-200 dark:bg-slate-600 rounded-full h-1.5 mb-1">
                      <div class="bg-primary-600 h-1.5 rounded-full" style="width: 0%"></div>
                    </div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 mb-2">0.00 %</div>
                    <!-- Courier stats table (toggleable) -->
                    <table v-show="showDeliveryStats" class="w-full text-[10px] leading-tight">
                      <thead>
                        <tr class="text-slate-400 dark:text-slate-500">
                          <th class="text-left font-semibold pb-1 pr-1">Courier</th>
                          <th class="text-right font-semibold pb-1 px-0.5">Total</th>
                          <th class="text-right font-semibold pb-1 px-0.5 text-emerald-600 dark:text-emerald-500">Delivered</th>
                          <th class="text-right font-semibold pb-1 px-0.5 text-red-500 dark:text-red-400">Undel.</th>
                          <th class="text-right font-semibold pb-1 pl-0.5">Conf.</th>
                        </tr>
                      </thead>
                      <tbody class="text-slate-600 dark:text-slate-400">
                        <tr v-for="row in deliveryStats" :key="row.name">
                          <td class="py-px font-semibold text-slate-500 dark:text-slate-400 pr-1">{{ row.name }}</td>
                          <td class="text-right py-px px-0.5">{{ row.total }}</td>
                          <td class="text-right py-px px-0.5">{{ row.delivered }}</td>
                          <td class="text-right py-px px-0.5">{{ row.undelivered }}</td>
                          <td class="text-right py-px pl-0.5">{{ row.confidence }}%</td>
                        </tr>
                        <tr class="font-bold border-t border-slate-100 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                          <td class="pt-0.5 pr-1">Total</td>
                          <td class="text-right pt-0.5 px-0.5">0</td>
                          <td class="text-right pt-0.5 px-0.5">0</td>
                          <td class="text-right pt-0.5 px-0.5">0</td>
                          <td class="text-right pt-0.5 pl-0.5">0.00%</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Customer ID & details -->
                <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                  <div class="px-3 py-2 bg-slate-50 dark:bg-slate-700/50 flex items-center justify-between border-b border-slate-100 dark:border-slate-700">
                    <div>
                      <div class="text-xs text-slate-400 dark:text-slate-500">Customer ID</div>
                      <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-sm font-mono font-semibold text-slate-700 dark:text-slate-200">{{ selectedCustomer.customer_code || 'C-' + selectedCustomer.id }}</span>
                        <span :class="tierBadgeCls" class="text-xs font-bold px-1.5 py-0.5 rounded">{{ tierLabel }}</span>
                      </div>
                    </div>
                    <button @click="openEditDrawer"
                      class="text-xs text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-700 rounded px-3 py-1 font-medium hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors">
                      Edit
                    </button>
                  </div>
                  <div class="p-3 space-y-2.5">
                    <div>
                      <div class="text-xs text-slate-400 dark:text-slate-500">Customer Name</div>
                      <div class="text-sm text-slate-700 dark:text-slate-300 mt-0.5">{{ selectedCustomer.business_name || selectedCustomer.name }}</div>
                    </div>
                    <div>
                      <div class="text-xs text-slate-400 dark:text-slate-500">Mobile Number</div>
                      <div class="text-sm font-mono text-slate-700 dark:text-slate-300 mt-0.5">{{ selectedCustomer.phone }}</div>
                    </div>
                    <div>
                      <div class="text-xs text-slate-400 dark:text-slate-500">Customer Address</div>
                      <div class="text-sm text-slate-700 dark:text-slate-300 mt-0.5 leading-snug">{{ customerFullAddress }}</div>
                    </div>
                  </div>
                </div>

                <!-- Order stats -->
                <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-3 space-y-2">
                  <div class="flex justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400">Order History</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ orderStats.total }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400">Delivered</span>
                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ orderStats.delivered }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400">Flagged</span>
                    <span class="font-semibold text-rose-600 dark:text-rose-400">{{ orderStats.flagged }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400">Cancelled</span>
                    <span class="font-semibold text-red-600 dark:text-red-400">{{ orderStats.cancelled }}</span>
                  </div>
                </div>

                <!-- Recent orders -->
                <div v-if="customerOrders.length" class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                  <div v-for="order in customerOrders.slice(0, 8)" :key="order.id"
                    class="px-3 py-2 border-b border-slate-100 dark:border-slate-700 last:border-0">
                    <div class="flex items-center justify-between">
                      <button @click="openOrderDetail(order.id)"
                        class="text-sm font-mono font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                        {{ order.order_no }}
                      </button>
                      <span :class="orderStatusCls(order.status)" class="text-xs font-medium px-2 py-0.5 rounded-full">
                        {{ order.status }}
                      </span>
                    </div>
                    <div class="flex items-center justify-between mt-0.5">
                      <span class="text-xs font-mono text-slate-700 dark:text-slate-300">BDT {{ formatNum(order.total_bdt) }}</span>
                      <span class="text-xs text-slate-400">{{ formatDate(order.created_at) }}</span>
                    </div>
                  </div>
                </div>

              </div>
            </template>

            <!-- No customer selected -->
            <div v-else class="flex-1 flex items-center justify-center p-8 text-center text-slate-400 dark:text-slate-500">
              <div>
                <UserIcon class="w-10 h-10 mx-auto mb-2 opacity-30" />
                <p class="text-sm">Search for a customer<br/>by phone number or name</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ────────────────────────────────────────────────────────
             MIDDLE COLUMN — Product Catalog
        ──────────────────────────────────────────────────────── -->
        <div class="flex-1 flex flex-col bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 min-w-0">
          <!-- Search bar (sticky) -->
          <div class="px-3 py-2 border-b border-slate-200 dark:border-slate-700 flex gap-2 flex-shrink-0">
            <div class="flex-1 relative">
              <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500" />
              <input
                v-model="productSearch"
                @input="onProductSearch"
                type="text"
                placeholder="Search by product name or SKU"
                class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 dark:text-slate-100 dark:placeholder:text-slate-500 focus:outline-none focus:border-primary-400 focus:ring-1 focus:ring-primary-200 dark:focus:ring-primary-900/30"
              />
            </div>
            <button title="Filter"
              class="p-2 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
              <SlidersHorizontalIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Product list (scrollable) -->
          <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/50">
            <div v-if="productsLoading" class="py-16 text-center text-slate-400 dark:text-slate-500">
              <LoaderIcon class="w-6 h-6 animate-spin mx-auto mb-2" />
              <p class="text-sm">Loading products...</p>
            </div>

            <div v-else-if="visibleProducts.length === 0" class="py-16 text-center text-slate-400 dark:text-slate-500 text-sm">
              No products found
            </div>
            <div v-for="p in visibleProducts" :key="p._key" class="p-4">
              <div class="flex items-start gap-3">
                <!-- Thumbnail -->
                <div class="w-14 h-14 rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-700 flex-shrink-0 flex items-center justify-center">
                  <img v-if="p.image" :src="p.image" class="w-full h-full object-cover" alt="" />
                  <span v-else class="text-xl font-bold text-slate-300 dark:text-slate-600 uppercase">{{ p.name.charAt(0) }}</span>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-semibold text-primary-600 dark:text-primary-400 leading-tight">
                    {{ p.name }}{{ p._variantName ? ` — ${p._variantName}` : '' }}
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    SKU: <span class="font-mono text-primary-500 dark:text-primary-400">{{ p._sku }}</span>
                  </div>
                  <!-- Prices -->
                  <div class="flex items-center flex-wrap gap-x-3 gap-y-0.5 mt-1.5">
                    <div>
                      <span class="text-xs text-slate-400">Regular</span>
                      <span class="text-sm font-medium text-slate-700 dark:text-slate-300 ml-1">BDT {{ formatNum(p.price_bdt) }}</span>
                    </div>
                    <div v-if="p._salePrice && Number(p._salePrice) < Number(p.price_bdt)">
                      <span class="text-xs text-slate-400">Sale</span>
                      <span class="text-sm font-medium text-primary-600 dark:text-primary-400 ml-1">BDT {{ formatNum(p._salePrice) }}</span>
                    </div>
                  </div>
                  <!-- Weight -->
                  <div v-if="p.weight" class="mt-1.5">
                    <span class="text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400 px-2 py-0.5 rounded">
                      Weight: {{ p.weight }} KG
                    </span>
                  </div>
                </div>

                <!-- Add to cart / qty controls -->
                <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                  <template v-if="!getCartEntry(p)">
                    <button @click="addToCart(p)"
                      class="flex items-center gap-1.5 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors">
                      <ShoppingCartIcon class="w-3.5 h-3.5" />
                      Add to Cart
                    </button>
                  </template>
                  <template v-else>
                    <div class="flex items-center gap-1.5">
                      <button @click="decCart(p)"
                        class="w-7 h-7 bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 rounded-lg flex items-center justify-center text-base font-bold hover:bg-primary-200 dark:hover:bg-primary-900/60 transition-colors">
                        −
                      </button>
                      <span class="w-7 text-center text-sm font-bold text-slate-800 dark:text-slate-200">
                        {{ getCartEntry(p).quantity }}
                      </span>
                      <button @click="incCart(p)"
                        class="w-7 h-7 bg-primary-600 hover:bg-primary-700 text-white rounded-lg flex items-center justify-center text-base font-bold transition-colors">
                        +
                      </button>
                    </div>
                  </template>
                  <button class="text-xs text-slate-400 dark:text-slate-500 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                    Check Availability
                  </button>
                </div>
              </div>
            </div>

            <!-- Infinite scroll sentinel -->
            <div ref="productSentinel" class="py-4 text-center">
              <LoaderIcon v-if="loadingMore" class="w-4 h-4 animate-spin mx-auto text-slate-400 dark:text-slate-500" />
              <span v-else-if="!hasMoreProducts && visibleProducts.length > 0"
                class="text-xs text-slate-300 dark:text-slate-600">
                — সকল পণ্য লোড হয়েছে —
              </span>
            </div>
          </div>
        </div>

        <!-- ────────────────────────────────────────────────────────
             RIGHT COLUMN — Cart (step 1) or Delivery & Payment (step 2)
        ──────────────────────────────────────────────────────── -->

        <!-- CART -->
        <div v-if="step === 'cart'" class="w-[340px] flex-shrink-0 flex flex-col bg-white dark:bg-slate-800">
          <!-- Header -->
          <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
            <h2 class="font-bold text-slate-900 dark:text-slate-100">Cart ({{ cart.length }})</h2>
          </div>

          <!-- Empty -->
          <div v-if="cart.length === 0" class="flex-1 flex flex-col items-center justify-center gap-3 text-slate-300 dark:text-slate-600">
            <ShoppingCartIcon class="w-16 h-16" />
            <span class="text-sm text-slate-400 dark:text-slate-500">No Products in Cart</span>
          </div>

          <!-- Items (scrollable) -->
          <div v-else class="flex-1 overflow-y-auto p-3 space-y-3">
            <div v-for="(item, idx) in cart" :key="idx"
              class="border border-slate-200 dark:border-slate-700 rounded-xl p-3">
              <!-- Top row -->
              <div class="flex gap-2.5">
                <div class="w-12 h-12 rounded-lg bg-slate-100 dark:bg-slate-700 flex-shrink-0 overflow-hidden flex items-center justify-center">
                  <img v-if="item.product.image" :src="item.product.image" class="w-full h-full object-cover" alt="" />
                  <span v-else class="font-bold text-slate-300 text-lg">{{ item.product.name.charAt(0) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate">
                    {{ item.product.name }}{{ item.variant ? ` — ${item.variant.variant_name}` : '' }}
                  </div>
                  <div class="text-xs text-primary-600 dark:text-primary-400 font-mono">
                    SKU: {{ item.variant?.sku || item.product.sku }}
                  </div>
                  <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-sm font-bold text-slate-800 dark:text-slate-200">BDT {{ formatNum(item.unit_price_bdt) }}</span>
                    <span v-if="item.originalPrice > item.unit_price_bdt"
                      class="text-xs text-slate-400 line-through">BDT {{ formatNum(item.originalPrice) }}</span>
                  </div>
                  <div v-if="item.product.weight" class="text-xs text-slate-400 mt-0.5">
                    Weight: {{ item.product.weight }} KG
                  </div>
                </div>
              </div>

              <!-- Unit price label -->
              <div class="mt-2.5">
                <div class="text-xs text-slate-400 dark:text-slate-500">Unit Price</div>
                <div class="text-sm font-mono text-slate-700 dark:text-slate-300 mt-0.5">BDT {{ formatNum(item.unit_price_bdt) }}</div>
              </div>

              <!-- Discount info -->
              <div v-if="item.discount_percent > 0" class="mt-1.5 flex items-center gap-2">
                <span class="text-xs text-emerald-600 dark:text-emerald-400">{{ item.discount_percent.toFixed(2) }}% discount applied</span>
                <button @click="removeDiscount(idx)" class="text-xs text-red-500 hover:underline">Remove</button>
              </div>

              <!-- Qty + delete -->
              <div class="flex items-center justify-between mt-2.5">
                <div class="flex items-center gap-1.5">
                  <button @click="item.quantity > 1 ? item.quantity-- : removeFromCart(idx)"
                    class="w-7 h-7 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-600 dark:text-slate-400 flex items-center justify-center text-base font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    −
                  </button>
                  <span class="w-7 text-center text-sm font-bold text-slate-800 dark:text-slate-200">{{ item.quantity }}</span>
                  <button @click="item.quantity++"
                    class="w-7 h-7 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-600 dark:text-slate-400 flex items-center justify-center text-base font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    +
                  </button>
                </div>
                <button @click="removeFromCart(idx)" class="text-red-400 hover:text-red-600 dark:text-red-500 dark:hover:text-red-400 transition-colors p-1">
                  <Trash2Icon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div v-if="cart.length > 0" class="border-t border-slate-200 dark:border-slate-700 p-4 flex-shrink-0">
            <div class="flex items-center justify-between font-bold text-base text-slate-800 dark:text-slate-200 mb-3">
              <span>Total: {{ formatNum(cartSubtotal) }}</span>
            </div>
            <button @click="goToDelivery"
              class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-2.5 rounded-xl transition-colors">
              Next
            </button>
          </div>
        </div>

        <!-- DELIVERY & PAYMENT -->
        <div v-else class="w-[340px] flex-shrink-0 flex flex-col bg-white dark:bg-slate-800">
          <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
            <h2 class="font-bold text-slate-900 dark:text-slate-100">Delivery &amp; Payment</h2>
          </div>

          <!-- Scrollable form fields -->
          <div class="flex-1 overflow-y-auto p-4 space-y-3">

            <!-- Delivery Type -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Delivery Type</label>
              <select v-model="form.delivery_type"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400">
                <option value="regular">Regular</option>
                <option value="express">Express</option>
              </select>
            </div>

            <!-- Customer (display) -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Search Customer</label>
              <div class="mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                {{ selectedCustomer?.phone || customerSearch || '—' }}
              </div>
            </div>

            <!-- Delivery Address -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Delivery Address</label>
              <div class="relative">
                <textarea v-model="form.delivery_address" rows="2"
                  class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400 resize-none pr-7" />
                <ChevronDownIcon class="absolute right-2 top-4 w-4 h-4 text-slate-400 pointer-events-none" />
              </div>
            </div>

            <!-- Pick Up Address -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Pick Up Address</label>
              <div class="mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-slate-50 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                ZamZam International
              </div>
            </div>

            <!-- Preferred Delivery Partner -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Preferred Delivery Partner</label>
              <select v-model="form.delivery_partner"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400">
                <option value="steadfast">Steadfast</option>
                <option value="pathao">Pathao</option>
                <option value="redx">REDX</option>
                <option value="sundarban">Sundarban</option>
                <option value="other">Other</option>
              </select>
            </div>

            <!-- Order Source -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Order Source</label>
              <select v-model="form.source"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400">
                <option value="">Select source</option>
                <option v-for="s in sources" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>

            <!-- Shipping Date -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Shipping Date</label>
              <input v-model="form.shipping_date" type="datetime-local"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400" />
            </div>

            <!-- Payment Method -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Payment Method</label>
              <select v-model="form.payment_method"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400">
                <option value="cod">Cash On Delivery (COD)</option>
                <option value="bkash">bKash</option>
                <option value="nagad">Nagad</option>
                <option value="bank">Bank Transfer</option>
                <option value="cash">Cash</option>
              </select>
            </div>

            <!-- Advance Payment -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Advance Payment Amount</label>
              <div class="mt-1 flex items-center border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 focus-within:border-primary-400">
                <span class="text-sm text-slate-400 dark:text-slate-500 mr-1">BDT</span>
                <input v-model.number="form.paid_bdt" type="number" min="0" step="0.01"
                  class="flex-1 text-sm text-slate-800 dark:text-slate-100 font-mono outline-none bg-transparent" />
              </div>
              <div class="flex items-center gap-4 mt-1.5">
                <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400 cursor-pointer">
                  <input type="radio" v-model="advanceType" value="flat" class="w-3.5 h-3.5 text-primary-600 focus:ring-primary-500" />
                  Flat
                </label>
                <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400 cursor-pointer">
                  <input type="radio" v-model="advanceType" value="percentage" class="w-3.5 h-3.5" />
                  Percentage
                </label>
              </div>
            </div>

            <!-- Discount -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Discount (optional)</label>
              <div class="mt-1 flex items-center border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 focus-within:border-primary-400">
                <span class="text-sm text-slate-400 dark:text-slate-500 mr-1">BDT</span>
                <input v-model.number="form.discount_bdt" type="number" min="0" step="0.01"
                  class="flex-1 text-sm text-slate-800 dark:text-slate-100 font-mono outline-none bg-transparent" />
              </div>
            </div>

            <!-- Additional Notes -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Additional Notes</label>
              <textarea v-model="form.notes" rows="3"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400 resize-none" />
            </div>

            <!-- Internal Notes -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Internal Notes</label>
              <textarea v-model="form.internal_notes" rows="3"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400 resize-none" />
            </div>

            <!-- Delivery Fee -->
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Delivery Fee</label>
              <div class="mt-1 flex items-center border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 focus-within:border-primary-400"
                :class="freeDelivery ? 'opacity-50' : ''">
                <span class="text-sm text-slate-400 dark:text-slate-500 mr-1">BDT</span>
                <input v-model.number="form.delivery_charge_bdt" type="number" min="0" step="0.01"
                  :disabled="freeDelivery"
                  class="flex-1 text-sm text-slate-800 dark:text-slate-100 font-mono outline-none bg-transparent disabled:cursor-not-allowed" />
              </div>
              <label class="flex items-center gap-2 mt-1.5 cursor-pointer">
                <input type="checkbox" v-model="freeDelivery" @change="onFreeDelivery"
                  class="w-3.5 h-3.5 rounded border-slate-300 dark:border-slate-600 text-primary-600 focus:ring-primary-500" />
                <span class="text-xs text-slate-600 dark:text-slate-400">Free Delivery</span>
              </label>
            </div>

            <!-- Attachments -->
            <div class="space-y-1.5">
              <input ref="attachmentInputRef" type="file" multiple class="hidden"
                @change="onAttachmentChange" />
              <button type="button"
                class="flex items-center gap-1.5 text-xs text-primary-600 dark:text-primary-400 font-medium hover:underline"
                @click="attachmentInputRef.click()">
                <PlusCircleIcon class="w-3.5 h-3.5" /> Attachments
                <span v-if="attachmentFiles.length" class="ml-1 bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 rounded-full px-1.5 py-0">
                  {{ attachmentFiles.length }}
                </span>
              </button>
              <!-- File list -->
              <ul v-if="attachmentFiles.length" class="space-y-1">
                <li v-for="(f, i) in attachmentFiles" :key="i"
                  class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-700/50 rounded px-2 py-1">
                  <span class="flex-1 truncate max-w-[160px]" :title="f.name">{{ f.name }}</span>
                  <span class="text-slate-400 dark:text-slate-500 shrink-0">{{ formatFileSize(f.size) }}</span>
                  <button type="button" @click="removeAttachment(i)"
                    class="ml-0.5 text-slate-400 hover:text-red-500 dark:hover:text-red-400 transition-colors">
                    <XIcon class="w-3 h-3" />
                  </button>
                </li>
              </ul>
            </div>
          </div>

          <!-- Summary + action buttons (fixed footer) -->
          <div class="border-t border-slate-200 dark:border-slate-700 px-4 py-3 flex-shrink-0 space-y-1.5">
            <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400">
              <span>Subtotal:</span>
              <span class="font-mono">{{ formatNum(cartSubtotal) }}</span>
            </div>
            <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400">
              <span>Discount:</span>
              <span class="font-mono text-red-500">- {{ formatNum(effectiveDiscount) }}</span>
            </div>
            <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400">
              <span>Delivery Fee<span v-if="selectedCustomer?.district" class="text-primary-500"> ({{ selectedCustomer.district }})</span>:</span>
              <span class="font-mono">{{ formatNum(deliveryFee) }}</span>
            </div>
            <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400">
              <span>Advance Payment:</span>
              <span class="font-mono text-primary-600 dark:text-primary-400">{{ formatNum(form.paid_bdt) }}</span>
            </div>
            <div class="flex justify-between text-sm font-bold text-slate-800 dark:text-slate-200 border-t border-slate-100 dark:border-slate-700 pt-1.5">
              <span>Total:</span>
              <span class="font-mono">{{ formatNum(orderTotal) }}</span>
            </div>

            <div class="flex gap-2 pt-1">
              <button @click="step = 'cart'"
                class="flex-1 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                Back
              </button>
              <button @click="step = 'checkout'"
                class="flex-1 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-lg transition-colors">
                Proceed to Checkout
              </button>
            </div>
          </div>
        </div>

      </div><!-- end 3-col -->
    </template>

    <!-- ═══════════════════════════════════════════════════════════
         CUSTOMER EDIT DRAWER
    ═══════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150"
        leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showEditDrawer" class="fixed inset-0 z-50 bg-black/40 dark:bg-black/60" @click="showEditDrawer = false" />
      </Transition>

      <Transition
        enter-active-class="transition-transform duration-250 ease-out"
        enter-from-class="translate-x-full" enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-from-class="translate-x-0" leave-to-class="translate-x-full">
        <div v-if="showEditDrawer"
          class="fixed right-0 top-0 h-full z-50 w-full max-w-sm bg-white dark:bg-slate-900 shadow-2xl flex flex-col">

          <!-- Drawer header -->
          <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between flex-shrink-0">
            <div>
              <h3 class="font-bold text-slate-900 dark:text-slate-100">Edit Customer</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ selectedCustomer?.customer_code }}</p>
            </div>
            <button @click="showEditDrawer = false"
              class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
              <XIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Drawer body -->
          <div class="flex-1 overflow-y-auto p-5 space-y-4">
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Customer Name <span class="text-red-500">*</span></label>
              <input v-model="editForm.name" type="text"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400 focus:ring-1 focus:ring-primary-200 dark:focus:ring-primary-900/30" />
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Business Name</label>
              <input v-model="editForm.business_name" type="text"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400 focus:ring-1 focus:ring-primary-200 dark:focus:ring-primary-900/30" />
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Phone <span class="text-red-500">*</span></label>
              <input v-model="editForm.phone" type="text"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 font-mono focus:outline-none focus:border-primary-400 focus:ring-1 focus:ring-primary-200 dark:focus:ring-primary-900/30" />
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
              <input v-model="editForm.email" type="email"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400 focus:ring-1 focus:ring-primary-200 dark:focus:ring-primary-900/30" />
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Address</label>
              <textarea v-model="editForm.address" rows="2"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400 focus:ring-1 focus:ring-primary-200 dark:focus:ring-primary-900/30 resize-none" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-medium text-slate-500 dark:text-slate-400">City</label>
                <input v-model="editForm.city" type="text"
                  class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400" />
              </div>
              <div>
                <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Area</label>
                <input v-model="editForm.area" type="text"
                  class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400" />
              </div>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">District</label>
              <input v-model="editForm.district" type="text"
                class="w-full mt-1 text-sm border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-400" />
            </div>
          </div>

          <!-- Drawer footer -->
          <div class="px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex gap-3 flex-shrink-0">
            <button @click="showEditDrawer = false"
              class="flex-1 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
              Cancel
            </button>
            <button @click="saveCustomer" :disabled="editSaving"
              class="flex-1 py-2 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
              <LoaderIcon v-if="editSaving" class="w-4 h-4 animate-spin" />
              {{ editSaving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ═══════════════════════════════════════════════════════════
         ORDER DETAIL MODAL
    ═══════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-all duration-200"
        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
        leave-active-class="transition-all duration-150"
        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
        <div v-if="showOrderModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
          <!-- Backdrop -->
          <div class="absolute inset-0 bg-black/50 dark:bg-black/70" @click="closeOrderModal" />

          <!-- Modal -->
          <div class="relative z-10 w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-2xl max-h-[90vh] flex flex-col">

            <!-- Loading state -->
            <div v-if="orderDetailLoading" class="flex items-center justify-center py-20">
              <LoaderIcon class="w-7 h-7 animate-spin text-primary-500" />
            </div>

            <template v-else-if="orderDetail">
              <!-- Modal header -->
              <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-3">
                  <span class="text-base font-bold font-mono text-primary-600 dark:text-primary-400">{{ orderDetail.order_no }}</span>
                  <span :class="orderStatusCls(orderDetail.status)"
                    class="text-xs font-semibold px-2.5 py-1 rounded-full capitalize">
                    {{ orderDetail.status }}
                  </span>
                </div>
                <button @click="closeOrderModal"
                  class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                  <XIcon class="w-5 h-5" />
                </button>
              </div>

              <!-- Modal body -->
              <div class="flex-1 overflow-y-auto p-5 space-y-5">

                <!-- Info row -->
                <div class="grid grid-cols-3 gap-4">
                  <div>
                    <div class="text-xs text-slate-400 dark:text-slate-500">Type</div>
                    <div class="text-sm font-medium text-slate-700 dark:text-slate-300 capitalize mt-0.5">{{ orderDetail.type }}</div>
                  </div>
                  <div>
                    <div class="text-xs text-slate-400 dark:text-slate-500">Source</div>
                    <div class="text-sm font-medium text-slate-700 dark:text-slate-300 capitalize mt-0.5">{{ orderDetail.source || '—' }}</div>
                  </div>
                  <div>
                    <div class="text-xs text-slate-400 dark:text-slate-500">Created</div>
                    <div class="text-sm text-slate-700 dark:text-slate-300 mt-0.5">{{ formatDate(orderDetail.created_at) }}</div>
                  </div>
                </div>

                <!-- Delivery address -->
                <div v-if="orderDetail.delivery_address">
                  <div class="text-xs text-slate-400 dark:text-slate-500 mb-1">Delivery Address</div>
                  <div class="text-sm text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 rounded-lg px-3 py-2 leading-snug">
                    {{ [orderDetail.delivery_address, orderDetail.delivery_city, orderDetail.delivery_area].filter(Boolean).join(', ') }}
                  </div>
                </div>

                <!-- Order Items -->
                <div>
                  <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">Items</div>
                  <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                    <div v-for="(item, idx) in orderDetail.items" :key="idx"
                      class="flex items-center gap-3 px-3 py-2.5 border-b border-slate-100 dark:border-slate-700 last:border-0">
                      <span class="text-xs text-slate-400 w-4 shrink-0">{{ idx + 1 }}</span>
                      <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">
                          {{ item.product?.name }}
                          <span v-if="item.variant" class="text-slate-500"> — {{ item.variant?.variant_name }}</span>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                          {{ item.variant?.sku || item.product?.sku }}
                        </div>
                      </div>
                      <div class="text-right shrink-0">
                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ item.quantity }} × {{ formatNum(item.unit_price_bdt) }}</div>
                        <div class="text-sm font-semibold font-mono text-slate-800 dark:text-slate-200">{{ formatNum(item.subtotal_bdt) }}</div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Payment summary -->
                <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-4 space-y-2">
                  <div class="flex justify-between text-sm text-slate-600 dark:text-slate-400">
                    <span>Subtotal</span>
                    <span class="font-mono">{{ formatNum(orderDetail.subtotal_bdt) }}</span>
                  </div>
                  <div v-if="Number(orderDetail.discount_bdt) > 0" class="flex justify-between text-sm text-slate-600 dark:text-slate-400">
                    <span>Discount</span>
                    <span class="font-mono text-red-500">− {{ formatNum(orderDetail.discount_bdt) }}</span>
                  </div>
                  <div v-if="Number(orderDetail.delivery_charge_bdt) > 0" class="flex justify-between text-sm text-slate-600 dark:text-slate-400">
                    <span>Delivery Fee</span>
                    <span class="font-mono">{{ formatNum(orderDetail.delivery_charge_bdt) }}</span>
                  </div>
                  <div class="flex justify-between text-sm font-bold text-slate-800 dark:text-slate-200 border-t border-slate-200 dark:border-slate-700 pt-2">
                    <span>Total</span>
                    <span class="font-mono">{{ formatNum(orderDetail.total_bdt) }}</span>
                  </div>
                  <div class="flex justify-between text-sm text-slate-600 dark:text-slate-400">
                    <span>Paid</span>
                    <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ formatNum(orderDetail.paid_bdt) }}</span>
                  </div>
                  <div class="flex justify-between text-sm font-semibold">
                    <span :class="Number(orderDetail.due_bdt) > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-600 dark:text-slate-400'">Due</span>
                    <span :class="Number(orderDetail.due_bdt) > 0 ? 'text-red-600 dark:text-red-400 font-mono' : 'text-slate-600 dark:text-slate-400 font-mono'">
                      {{ formatNum(orderDetail.due_bdt) }}
                    </span>
                  </div>
                </div>

                <!-- Notes -->
                <div v-if="orderDetail.notes" class="text-sm text-slate-600 dark:text-slate-400 bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-800 rounded-lg px-3 py-2">
                  {{ orderDetail.notes }}
                </div>
              </div>

              <!-- Modal footer -->
              <div class="px-5 py-3 border-t border-slate-200 dark:border-slate-700 flex-shrink-0">
                <Link :href="route('sales-orders.show', orderDetail.id)"
                  class="w-full flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium py-2 rounded-lg transition-colors">
                  View Full Order Details
                </Link>
              </div>
            </template>
          </div>
        </div>
      </Transition>
    </Teleport>

  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
  SearchIcon, SlidersHorizontalIcon, ShoppingCartIcon, Trash2Icon,
  LoaderIcon, ChevronLeftIcon, ChevronDownIcon, ChevronUpIcon,
  PlusCircleIcon, UserIcon, XIcon,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useToast } from '@/Composables/useToast'

// ─── Props ────────────────────────────────────────────────────────────────

const props = defineProps({
  customers:  { type: Array, default: () => [] },
  priceTiers: { type: Array, default: () => [] },
  sources:    { type: Array, default: () => [] },
})

const { success, error: showError } = useToast()

// ─── Step state ───────────────────────────────────────────────────────────
// 'cart' → 'delivery' → 'checkout'
const step      = ref('cart')
const saving    = ref(false)
const autoApprove = ref(false)
const advanceType = ref('flat')
const freeDelivery = ref(false)

// ─── Attachments ──────────────────────────────────────────────────────────
const attachmentInputRef = ref(null)
const attachmentFiles    = ref([]) // File[]

function onAttachmentChange(e) {
  const picked = Array.from(e.target.files || [])
  attachmentFiles.value.push(...picked)
  // Reset input so same file can be re-added if removed
  e.target.value = ''
}

function removeAttachment(idx) {
  attachmentFiles.value.splice(idx, 1)
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

// ─── Delivery stats toggle ────────────────────────────────────────────────
const showDeliveryStats = ref(false)

// ─── Customer search ──────────────────────────────────────────────────────

const customerSearch   = ref('')
const customerResults  = ref([])
const recentCustomers  = ref([])
const showCustDrop     = ref(false)
const selectedCustomer = ref(null)
const customerOrders   = ref([])

let custTimer = null

// load latest 5 on mount
onMounted(async () => {
  try {
    const { data } = await window.axios.get('/api/v1/customers', { params: { per_page: 5 } })
    recentCustomers.value = data.data ?? data
  } catch { /* silent */ }
  await loadProducts('')
})

function onCustomerSearch() {
  showCustDrop.value = false
  clearTimeout(custTimer)
  // empty → show recent customers
  if (!customerSearch.value.trim()) {
    customerResults.value = []
    showCustDrop.value = recentCustomers.value.length > 0
    return
  }
  if (customerSearch.value.trim().length < 2) { customerResults.value = []; return }
  custTimer = setTimeout(async () => {
    try {
      const { data } = await window.axios.get('/api/v1/customers/search', {
        params: { q: normalizePhone(customerSearch.value) },
      })
      customerResults.value = data
      showCustDrop.value = data.length > 0
    } catch {
      customerResults.value = []
    }
  }, 300)
}

function onCustomerFocus() {
  // show recent if no search text, else show existing results
  if (!customerSearch.value.trim()) {
    showCustDrop.value = recentCustomers.value.length > 0
  } else {
    showCustDrop.value = customerResults.value.length > 0
  }
}

function hideCustDrop() {
  setTimeout(() => { showCustDrop.value = false }, 150)
}

// show recent when search is empty, otherwise show search results
const dropdownCustomers = computed(() =>
  customerSearch.value.trim() ? customerResults.value : recentCustomers.value
)

async function selectCustomer(c) {
  selectedCustomer.value = c
  customerSearch.value   = c.phone || c.name
  showCustDrop.value     = false
  customerResults.value  = []
  // Auto-fill delivery address
  if (c.address) form.delivery_address = [c.address, c.city, c.area].filter(Boolean).join(', ')
  if (c.city)    form.delivery_city    = c.city
  if (c.area)    form.delivery_area    = c.area
  // Load full details
  try {
    const { data } = await window.axios.get(`/api/v1/customers/${c.id}`)
    selectedCustomer.value = data
    customerOrders.value   = data.sales_orders || []
  } catch { /* keep partial */ }
}

const customerFullAddress = computed(() => {
  const c = selectedCustomer.value
  if (!c) return ''
  return [c.address, c.area, c.city, c.district].filter(Boolean).join(', ')
})

const tierLabel = computed(() => {
  const n = selectedCustomer.value?.total_orders ?? 0
  return n >= 50 ? 'VIP' : n <= 2 ? 'NEW' : 'REGULAR'
})
const tierBadgeCls = computed(() => {
  const t = tierLabel.value
  return t === 'VIP'     ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
       : t === 'NEW'     ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
       : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
})

const orderStats = computed(() => {
  const orders = customerOrders.value
  return {
    total:     selectedCustomer.value?.total_orders ?? orders.length,
    delivered: orders.filter(o => o.status === 'delivered').length,
    flagged:   orders.filter(o => o.status === 'flagged').length,
    cancelled: orders.filter(o => o.status === 'cancelled').length,
  }
})

const deliveryStats = [
  { name: 'STEADFAST', total: 0, delivered: 0, undelivered: 0, confidence: '0.00' },
  { name: 'PATHAO',    total: 0, delivered: 0, undelivered: 0, confidence: '0.00' },
  { name: 'REDX',      total: 0, delivered: 0, undelivered: 0, confidence: '0.00' },
]

// ─── Product catalog ──────────────────────────────────────────────────────

const productSearch   = ref('')
const products        = ref([])
const productsLoading = ref(false)
const loadingMore     = ref(false)
const productPage     = ref(1)
const hasMoreProducts = ref(true)
const productSentinel = ref(null)   // sentinel div for IntersectionObserver
let prodTimer     = null
let prodObserver  = null

async function loadProducts(q = '', page = 1, append = false) {
  if (page === 1) { productsLoading.value = true; hasMoreProducts.value = true }
  else loadingMore.value = true
  try {
    const params = { page, per_page: 20 }
    if (q.trim()) params.q = q.trim()
    if (form.price_tier_id) params.price_tier_id = form.price_tier_id
    const { data } = await window.axios.get('/api/v1/products/browse', { params })

    const flat = flattenProducts(data.data ?? [])
    products.value = append ? [...products.value, ...flat] : flat
    productPage.value     = data.current_page ?? page
    hasMoreProducts.value = (data.current_page ?? 1) < (data.last_page ?? 1)
  } catch {
    if (!append) products.value = []
    hasMoreProducts.value = false
  } finally {
    productsLoading.value = false
    loadingMore.value     = false
  }
}

function setupProductObserver() {
  if (!productSentinel.value) return
  prodObserver = new IntersectionObserver((entries) => {
    if (
      entries[0].isIntersecting &&
      hasMoreProducts.value &&
      !loadingMore.value &&
      !productsLoading.value
    ) {
      loadProducts(productSearch.value, productPage.value + 1, true)
    }
  }, { threshold: 0.1 })
  prodObserver.observe(productSentinel.value)
}

onMounted(async () => {
  /* recent customers */
  try {
    const { data } = await window.axios.get('/api/v1/customers', { params: { per_page: 5 } })
    recentCustomers.value = data.data ?? data
  } catch { /* silent */ }

  /* initial product load */
  await loadProducts('', 1, false)
  await nextTick()
  setupProductObserver()
})

onUnmounted(() => { if (prodObserver) prodObserver.disconnect() })

function onProductSearch() {
  clearTimeout(prodTimer)
  productPage.value = 1
  prodTimer = setTimeout(() => loadProducts(productSearch.value, 1, false), 350)
}

// Flatten variants → one row per sellable unit
function flattenProducts(raw) {
  const flat = []
  for (const p of raw) {
    if (p.has_variants && p.variants?.length) {
      for (const v of p.variants) {
        flat.push({
          ...p,
          _key:        `${p.id}-${v.id}`,
          _variantId:  v.id,
          _variantName: v.variant_name,
          _sku:        v.sku || p.sku,
          _salePrice:  v.price_bdt ?? p.price_bdt,
          _variant:    v,
        })
      }
    } else {
      flat.push({
        ...p,
        _key:        `${p.id}`,
        _variantId:  null,
        _variantName: null,
        _sku:        p.sku,
        _salePrice:  p.price_bdt,
        _variant:    null,
      })
    }
  }
  return flat
}

// search is server-side now — products ref already holds results
const visibleProducts = computed(() => products.value)

// ─── Cart ─────────────────────────────────────────────────────────────────

const cart = ref([])

function getCartEntry(p) {
  return cart.value.find(i =>
    i.product.id === p.id &&
    (i.variant?.id ?? null) === (p._variantId ?? null)
  )
}

function addToCart(p) {
  const existing = getCartEntry(p)
  if (existing) { existing.quantity++; return }

  const base  = parseFloat(p._salePrice     ?? p.price_bdt ?? 0)
  const orig  = parseFloat(p.price_bdt ?? base)
  const disc  = orig > 0 && base < orig ? ((orig - base) / orig) * 100 : 0

  cart.value.push({
    product:          p,
    variant:          p._variant,
    quantity:         1,
    unit_price_bdt:   base,
    originalPrice:    orig,
    discount_percent: parseFloat(disc.toFixed(2)),
  })
}

function incCart(p) {
  const e = getCartEntry(p)
  if (e) e.quantity++
  else addToCart(p)
}

function decCart(p) {
  const idx = cart.value.findIndex(i =>
    i.product.id === p.id && (i.variant?.id ?? null) === (p._variantId ?? null)
  )
  if (idx === -1) return
  if (cart.value[idx].quantity > 1) cart.value[idx].quantity--
  else cart.value.splice(idx, 1)
}

function removeFromCart(idx) { cart.value.splice(idx, 1) }

function removeDiscount(idx) {
  const item = cart.value[idx]
  item.unit_price_bdt   = item.originalPrice
  item.discount_percent = 0
}

// ─── Form (delivery & payment) ────────────────────────────────────────────

const form = reactive({
  source:              '',
  price_tier_id:       '',
  delivery_type:       'regular',
  delivery_partner:    'steadfast',
  delivery_address:    '',
  delivery_city:       '',
  delivery_area:       '',
  delivery_charge_bdt: 70,
  paid_bdt:            0,
  discount_bdt:        0,
  discount_percent:    0,
  notes:               '',
  internal_notes:      '',
  shipping_date:       '',
  payment_method:      'cod',
})

function onFreeDelivery() {
  form.delivery_charge_bdt = freeDelivery.value ? 0 : 70
}

// ─── Computed totals ──────────────────────────────────────────────────────

function itemSubtotal(item) {
  return item.quantity * item.unit_price_bdt
}

const cartSubtotal = computed(() =>
  cart.value.reduce((s, i) => s + itemSubtotal(i), 0)
)

const effectiveDiscount = computed(() => {
  if (form.discount_percent > 0) return cartSubtotal.value * (form.discount_percent / 100)
  return form.discount_bdt || 0
})

const deliveryFee = computed(() =>
  freeDelivery.value ? 0 : (form.delivery_charge_bdt || 0)
)

const orderTotal = computed(() =>
  cartSubtotal.value - effectiveDiscount.value + deliveryFee.value
)

// ─── Step navigation ──────────────────────────────────────────────────────

function goToDelivery() {
  if (!selectedCustomer.value) { showError('Please select a customer first.'); return }
  if (cart.value.length === 0) { showError('Cart is empty. Add at least one product.'); return }
  step.value = 'delivery'
}

// ─── Submit ───────────────────────────────────────────────────────────────

async function submit() {
  if (!selectedCustomer.value) { showError('No customer selected.'); return }
  if (cart.value.length === 0) { showError('Cart is empty.'); return }

  saving.value = true
  try {
    const payload = {
      customer_id:         selectedCustomer.value.id,
      type:                selectedCustomer.value.type || 'retail',
      source:              form.source || null,
      price_tier_id:       form.price_tier_id || null,
      delivery_address:    form.delivery_address || null,
      delivery_city:       form.delivery_city || null,
      delivery_area:       form.delivery_area || null,
      delivery_charge_bdt: deliveryFee.value,
      discount_bdt:        effectiveDiscount.value > 0 && !form.discount_percent ? effectiveDiscount.value : null,
      discount_percent:    form.discount_percent > 0 ? form.discount_percent : null,
      paid_bdt:            form.paid_bdt || 0,
      notes:               form.notes || null,
      internal_notes:      form.internal_notes || null,
      delivery_partner:    form.delivery_partner || null,
      delivery_type:       form.delivery_type || 'regular',
      items: cart.value.map(i => ({
        product_id:         i.product.id,
        product_variant_id: i.variant?.id ?? null,
        quantity:           i.quantity,
        unit_price_bdt:     i.unit_price_bdt,
        discount_percent:   i.discount_percent || 0,
      })),
    }

    const { data } = await window.axios.post('/api/v1/sales-orders', payload)

    if (autoApprove.value) {
      await window.axios.post(`/api/v1/sales-orders/${data.order.id}/confirm`).catch(() => {})
    }

    // Upload attachments if any
    if (attachmentFiles.value.length) {
      for (const file of attachmentFiles.value) {
        const fd = new FormData()
        fd.append('file', file)
        await window.axios.post(
          `/api/v1/sales-orders/${data.order.id}/attachments`,
          fd,
          { headers: { 'Content-Type': 'multipart/form-data' } },
        ).catch(() => {}) // non-blocking — order already saved
      }
    }

    success('Sales order created successfully!')
    router.visit(route('sales-orders.show', data.order.id))
  } catch (err) {
    const msgs = err.response?.data?.errors
    const first = msgs ? Object.values(msgs)[0]?.[0] : (err.response?.data?.message || 'Failed to create order.')
    showError(first)
    saving.value = false
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────

// ── Customer Edit Drawer ──────────────────────────────────────────────────
const showEditDrawer = ref(false)
const editSaving     = ref(false)
const editForm = reactive({
  name: '', business_name: '', phone: '', email: '',
  address: '', city: '', area: '', district: '',
})

function openEditDrawer() {
  if (!selectedCustomer.value) return
  const c = selectedCustomer.value
  editForm.name          = c.name          || ''
  editForm.business_name = c.business_name || ''
  editForm.phone         = c.phone         || ''
  editForm.email         = c.email         || ''
  editForm.address       = c.address       || ''
  editForm.city          = c.city          || ''
  editForm.area          = c.area          || ''
  editForm.district      = c.district      || ''
  showEditDrawer.value   = true
}

async function saveCustomer() {
  if (!selectedCustomer.value) return
  editSaving.value = true
  try {
    const { data } = await window.axios.put(
      `/api/v1/customers/${selectedCustomer.value.id}`,
      editForm,
    )
    Object.assign(selectedCustomer.value, data)
    // Update form delivery address if changed
    if (editForm.address) {
      form.delivery_address = [editForm.address, editForm.city, editForm.area].filter(Boolean).join(', ')
    }
    showEditDrawer.value = false
    success('Customer updated successfully!')
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to update customer.')
  } finally {
    editSaving.value = false
  }
}

// ── Order Detail Modal ────────────────────────────────────────────────────
const showOrderModal     = ref(false)
const orderDetailLoading = ref(false)
const orderDetail        = ref(null)

async function openOrderDetail(orderId) {
  showOrderModal.value     = true
  orderDetailLoading.value = true
  orderDetail.value        = null
  try {
    const { data } = await window.axios.get(`/api/v1/sales-orders/${orderId}`)
    orderDetail.value = data
  } catch {
    showError('Failed to load order details.')
    showOrderModal.value = false
  } finally {
    orderDetailLoading.value = false
  }
}

function closeOrderModal() {
  showOrderModal.value = false
  orderDetail.value    = null
}

function formatNum(v) {
  if (v == null) return '0.00'
  return Number(v).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ── Phone normalizer: Bengali numerals + +880 prefix handling ─────────────
function normalizePhone(q) {
  if (!q) return q
  // Bengali digit map: ০→0 … ৯→9
  const BN = { '০':'0','১':'1','২':'2','৩':'3','৪':'4','৫':'5','৬':'6','৭':'7','৮':'8','৯':'9' }
  let s = q.replace(/[০-৯]/g, d => BN[d] ?? d)
  // Strip leading +880 or 880 so the stored format (01XXXXXXXX) is matched
  s = s.replace(/^\+?880/, '')
  return s.trim()
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const todayDisplay = computed(() =>
  new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
)

const sourceLabel = computed(() => {
  return props.sources.find(s => s.value === form.source)?.label || form.source
})

const ORDER_STATUS_CLS = {
  draft:      'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
  confirmed:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  delivered:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
  cancelled:  'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  flagged:    'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
  processing: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  dispatched: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
}
function orderStatusCls(s) {
  return ORDER_STATUS_CLS[s] || 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
}
</script>
