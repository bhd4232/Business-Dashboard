<template>
  <AppLayout>
    <Head :title="`Edit Order #${order.order_no}`" />

    <div class="mb-6">
      <Link :href="route('sales-orders.show', order.id)"
        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-slate-200 hover:border-slate-300 dark:hover:border-slate-600 transition-all shadow-sm group mb-4">
        <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
        <span>{{ order.order_no }}</span>
      </Link>
      <div class="flex items-center gap-3">
        <ThreeDIcon name="sales-order" size="md" />
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">
          Edit Order
          <span class="font-mono text-primary-600 dark:text-primary-400 ml-2">#{{ order.order_no }}</span>
        </h1>
      </div>
    </div>

    <form @submit.prevent="submit">
      <!-- Card 1: Order Information -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-4">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <ClipboardListIcon class="w-4 h-4 text-primary-600" />
          <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Order Information</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Customer -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
              Customer <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.customer_id"
              @change="onCustomerChange"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 bg-white"
              :class="{ 'border-red-400': errors.customer_id }">
              <option value="">Select customer</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">
                {{ c.name }}{{ c.business_name ? ` — ${c.business_name}` : '' }}{{ c.phone ? ` (${c.phone})` : '' }}
              </option>
            </select>
            <p v-if="errors.customer_id" class="mt-1 text-xs text-red-600">{{ errors.customer_id }}</p>
          </div>

          <!-- Order Type -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
              Order Type <span class="text-red-500">*</span>
            </label>
            <div class="flex gap-3">
              <label
                v-for="type in orderTypes"
                :key="type.value"
                class="flex items-center gap-2 cursor-pointer px-4 py-2 rounded-lg border text-sm font-medium transition-colors"
                :class="form.type === type.value
                  ? 'border-primary-500 bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300'
                  : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50'"
              >
                <input type="radio" v-model="form.type" :value="type.value" class="sr-only" />
                {{ type.label }}
              </label>
            </div>
            <p v-if="errors.type" class="mt-1 text-xs text-red-600">{{ errors.type }}</p>
          </div>

          <!-- Source -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Source</label>
            <select
              v-model="form.source"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 bg-white">
              <option value="">Select source</option>
              <option v-for="s in sources" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <p v-if="errors.source" class="mt-1 text-xs text-red-600">{{ errors.source }}</p>
          </div>

          <!-- Price Tier -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Price Tier</label>
            <select
              v-model="form.price_tier_id"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 bg-white">
              <option value="">No specific tier</option>
              <option v-for="t in priceTiers" :key="t.id" :value="t.id">
                {{ t.name }}{{ t.code ? ` (${t.code})` : '' }}{{ t.discount_percent > 0 ? ` — ${t.discount_percent}% off` : '' }}
              </option>
            </select>
            <p v-if="errors.price_tier_id" class="mt-1 text-xs text-red-600">{{ errors.price_tier_id }}</p>
          </div>

          <!-- Delivery Address -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Delivery Address</label>
            <textarea
              v-model="form.delivery_address"
              rows="2"
              placeholder="Full delivery address..."
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 resize-none"
              :class="{ 'border-red-400': errors.delivery_address }">
            </textarea>
            <p v-if="errors.delivery_address" class="mt-1 text-xs text-red-600">{{ errors.delivery_address }}</p>
          </div>

          <!-- City -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">City</label>
            <input
              v-model="form.delivery_city"
              type="text"
              placeholder="e.g. Dhaka"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30"
              :class="{ 'border-red-400': errors.delivery_city }" />
            <p v-if="errors.delivery_city" class="mt-1 text-xs text-red-600">{{ errors.delivery_city }}</p>
          </div>

          <!-- Area -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Area</label>
            <input
              v-model="form.delivery_area"
              type="text"
              placeholder="e.g. Mirpur"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30"
              :class="{ 'border-red-400': errors.delivery_area }" />
            <p v-if="errors.delivery_area" class="mt-1 text-xs text-red-600">{{ errors.delivery_area }}</p>
          </div>

          <!-- Discount BDT -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Discount (BDT)</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 dark:text-slate-400 text-sm font-medium pointer-events-none">৳</span>
              <input
                v-model.number="form.discount_bdt"
                type="number"
                min="0"
                step="0.01"
                placeholder="0.00"
                :disabled="discountPercentActive"
                class="w-full pl-7 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 font-mono disabled:opacity-40 disabled:cursor-not-allowed"
                :class="{ 'border-red-400': errors.discount_bdt }" />
            </div>
            <p v-if="errors.discount_bdt" class="mt-1 text-xs text-red-600">{{ errors.discount_bdt }}</p>
          </div>

          <!-- Discount % -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Discount (%)</label>
            <div class="relative">
              <input
                v-model.number="form.discount_percent"
                type="number"
                min="0"
                max="100"
                step="0.01"
                placeholder="0.00"
                :disabled="discountBdtActive"
                class="w-full pr-7 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 font-mono disabled:opacity-40 disabled:cursor-not-allowed"
                :class="{ 'border-red-400': errors.discount_percent }" />
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 dark:text-slate-400 text-sm pointer-events-none">%</span>
            </div>
            <p v-if="errors.discount_percent" class="mt-1 text-xs text-red-600">{{ errors.discount_percent }}</p>
          </div>

          <!-- Delivery Charge -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Delivery Charge (BDT)</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 dark:text-slate-400 text-sm font-medium pointer-events-none">৳</span>
              <input
                v-model.number="form.delivery_charge_bdt"
                type="number"
                min="0"
                step="0.01"
                placeholder="0.00"
                class="w-full pl-7 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 font-mono"
                :class="{ 'border-red-400': errors.delivery_charge_bdt }" />
            </div>
            <p v-if="errors.delivery_charge_bdt" class="mt-1 text-xs text-red-600">{{ errors.delivery_charge_bdt }}</p>
          </div>

          <!-- Paid Amount -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Paid Amount (BDT)</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 dark:text-slate-400 text-sm font-medium pointer-events-none">৳</span>
              <input
                v-model.number="form.paid_bdt"
                type="number"
                min="0"
                step="0.01"
                placeholder="0.00"
                class="w-full pl-7 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 font-mono"
                :class="{ 'border-red-400': errors.paid_bdt }" />
            </div>
            <p v-if="errors.paid_bdt" class="mt-1 text-xs text-red-600">{{ errors.paid_bdt }}</p>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes</label>
            <textarea
              v-model="form.notes"
              rows="2"
              placeholder="Customer-facing notes..."
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 resize-none">
            </textarea>
          </div>

          <!-- Internal Notes -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Internal Notes</label>
            <textarea
              v-model="form.internal_notes"
              rows="2"
              placeholder="Internal staff notes (not visible to customer)..."
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 resize-none">
            </textarea>
          </div>

        </div>
      </div>

      <!-- Card 2: Order Items -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-4">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <PackageIcon class="w-4 h-4 text-primary-600" />
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Order Items</h2>
          </div>
          <button
            type="button"
            @click="addItem"
            class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium flex items-center gap-1 transition-colors">
            <PlusIcon class="w-3 h-3" />
            Add Item
          </button>
        </div>
        <div class="p-4">
          <p v-if="errors.items" class="text-sm text-red-600 mb-3">{{ errors.items }}</p>

          <div v-if="form.items.length > 0" class="overflow-x-auto">
            <table class="w-full mb-3">
              <thead>
                <tr class="bg-slate-50 dark:bg-slate-700/50">
                  <th class="text-left text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2 w-8">#</th>
                  <th class="text-left text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2">Product</th>
                  <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2 w-24">Qty</th>
                  <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2 w-32">Unit Price (৳)</th>
                  <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2 w-24">Disc%</th>
                  <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2 w-32">Subtotal (৳)</th>
                  <th class="w-8"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in form.items" :key="idx" class="border-b border-slate-100 dark:border-slate-700 last:border-0">

                  <!-- Row Number -->
                  <td class="px-3 py-2 text-xs text-slate-400 dark:text-slate-500 font-mono">{{ idx + 1 }}</td>

                  <!-- Product Search -->
                  <td class="px-3 py-2 relative" style="min-width: 240px;">
                    <div class="relative">
                      <input
                        v-model="item.product_search"
                        type="text"
                        placeholder="Type to search product..."
                        autocomplete="off"
                        @input="onProductInput(idx)"
                        @focus="item.showDropdown = item.searchResults.length > 0"
                        @blur="closeDropdown(idx)"
                        class="w-full rounded-lg border px-3 py-1.5 text-sm focus:outline-none pr-7 dark:bg-slate-700 dark:text-slate-100 dark:placeholder:text-slate-500"
                        :class="[
                          item.product_id
                            ? 'border-green-400 bg-green-50 dark:bg-green-900/20 dark:border-green-600 focus:border-green-500'
                            : errors[`items.${idx}.product_id`]
                              ? 'border-red-400 dark:border-red-500'
                              : 'border-slate-300 dark:border-slate-600 focus:border-primary-500 dark:focus:border-primary-500'
                        ]"
                      />
                      <!-- Icon: spinner / check / search -->
                      <span class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                        <LoaderIcon v-if="item.searching" class="w-3.5 h-3.5 animate-spin text-primary-400" />
                        <CheckIcon v-else-if="item.product_id" class="w-3.5 h-3.5 text-green-500" />
                        <SearchIcon v-else class="w-3.5 h-3.5" />
                      </span>

                      <!-- Dropdown Results -->
                      <div
                        v-if="item.showDropdown && item.searchResults.length > 0"
                        class="absolute z-50 top-full mt-1 left-0 right-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl max-h-60 overflow-y-auto"
                      >
                        <template v-for="p in item.searchResults" :key="p.id">
                          <!-- Product without variants -->
                          <button
                            v-if="!p.has_variants || !p.variants?.length"
                            type="button"
                            @mousedown.prevent="selectProduct(idx, p)"
                            class="w-full text-left px-3 py-2 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors border-b border-slate-100 dark:border-slate-700 last:border-0"
                          >
                            <div class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{{ p.name }}</div>
                            <div class="text-xs text-slate-400 dark:text-slate-500 mt-0.5 flex gap-3">
                              <span class="font-mono">{{ p.sku }}</span>
                              <span v-if="p.price_bdt" class="text-primary-600 dark:text-primary-400 font-medium">৳{{ Number(p.price_bdt).toLocaleString() }}</span>
                            </div>
                          </button>
                          <!-- Product with variants -->
                          <template v-else>
                            <div class="px-3 py-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/50 sticky top-0 border-b border-slate-100 dark:border-slate-700">
                              {{ p.name }}
                            </div>
                            <button
                              v-for="v in p.variants"
                              :key="v.id"
                              type="button"
                              @mousedown.prevent="selectProduct(idx, p, v)"
                              class="w-full text-left px-3 py-1.5 pl-6 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors border-b border-slate-100 dark:border-slate-700 last:border-0"
                            >
                              <div class="text-sm text-slate-700 dark:text-slate-300">{{ v.variant_name }}</div>
                              <div class="text-xs text-slate-400 dark:text-slate-500 mt-0.5 flex gap-3">
                                <span class="font-mono">{{ v.sku }}</span>
                                <span v-if="v.price_bdt" class="text-primary-600 dark:text-primary-400 font-medium">৳{{ Number(v.price_bdt).toLocaleString() }}</span>
                              </div>
                            </button>
                          </template>
                        </template>
                      </div>

                      <!-- No results -->
                      <div
                        v-if="item.showDropdown && !item.searching && item.searchResults.length === 0 && item.product_search.length >= 2"
                        class="absolute z-50 top-full mt-1 left-0 right-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl px-3 py-3 text-sm text-slate-500 dark:text-slate-400 text-center"
                      >
                        No products found
                      </div>
                    </div>
                    <p v-if="errors[`items.${idx}.product_id`]" class="mt-1 text-xs text-red-600">
                      {{ errors[`items.${idx}.product_id`][0] }}
                    </p>
                  </td>

                  <!-- Qty -->
                  <td class="px-3 py-2">
                    <input
                      v-model.number="item.quantity"
                      type="number"
                      min="1"
                      step="1"
                      class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 px-2 py-1.5 text-sm focus:outline-none focus:border-primary-500 font-mono text-right" />
                  </td>

                  <!-- Unit Price -->
                  <td class="px-3 py-2">
                    <div class="relative">
                      <span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 text-xs pointer-events-none">৳</span>
                      <input
                        v-model.number="item.unit_price_bdt"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-full pl-5 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 px-2 py-1.5 text-sm focus:outline-none focus:border-primary-500 font-mono text-right" />
                    </div>
                  </td>

                  <!-- Discount % -->
                  <td class="px-3 py-2">
                    <div class="relative">
                      <input
                        v-model.number="item.discount_percent"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        class="w-full pr-5 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 px-2 py-1.5 text-sm focus:outline-none focus:border-primary-500 font-mono text-right" />
                      <span class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 text-xs pointer-events-none">%</span>
                    </div>
                  </td>

                  <!-- Subtotal -->
                  <td class="px-3 py-2 text-right text-sm font-mono text-slate-700 dark:text-slate-300 font-medium">
                    ৳{{ itemSubtotal(item).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </td>

                  <!-- Remove -->
                  <td class="px-3 py-2">
                    <button
                      type="button"
                      @click="removeItem(idx)"
                      class="text-slate-400 hover:text-red-500 dark:hover:text-red-400 transition-colors">
                      <Trash2Icon class="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="text-center py-10 text-slate-400 dark:text-slate-500 text-sm border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
            No items added yet. Click <strong class="text-slate-500 dark:text-slate-400">Add Item</strong> to begin.
          </div>
        </div>

        <!-- Order Summary -->
        <div v-if="form.items.length > 0" class="border-t border-slate-200 dark:border-slate-700 px-6 py-4">
          <div class="flex justify-end">
            <div class="w-80 space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-slate-600 dark:text-slate-400">Subtotal</span>
                <span class="font-mono text-slate-700 dark:text-slate-300">৳{{ orderSubtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
              </div>
              <div v-if="effectiveDiscount > 0" class="flex justify-between text-sm">
                <span class="text-slate-600 dark:text-slate-400">Discount</span>
                <span class="font-mono text-red-500 dark:text-red-400">− ৳{{ effectiveDiscount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
              </div>
              <div v-if="(form.delivery_charge_bdt || 0) > 0" class="flex justify-between text-sm">
                <span class="text-slate-600 dark:text-slate-400">Delivery Charge</span>
                <span class="font-mono text-slate-700 dark:text-slate-300">৳{{ Number(form.delivery_charge_bdt).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
              </div>
              <div class="flex justify-between text-sm font-bold pt-2 border-t border-slate-200 dark:border-slate-600">
                <span class="text-slate-800 dark:text-slate-200">Total</span>
                <span class="font-mono text-slate-900 dark:text-slate-100">৳{{ orderTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-slate-600 dark:text-slate-400">Paid</span>
                <span class="font-mono text-green-600 dark:text-green-400">৳{{ Number(form.paid_bdt || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
              </div>
              <div class="flex justify-between text-sm font-semibold">
                <span :class="orderDue > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-600 dark:text-slate-400'">Due</span>
                <span class="font-mono" :class="orderDue > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-300'">
                  ৳{{ orderDue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sticky Submit Bar -->
      <div class="sticky bottom-0 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 shadow-lg -mx-6 px-6 py-4 flex items-center justify-end gap-3">
        <Link
          :href="route('sales-orders.show', order.id)"
          class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
          Cancel
        </Link>
        <button
          type="submit"
          :disabled="saving"
          class="px-6 py-2 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
          <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
          {{ saving ? 'Saving...' : 'Save Changes' }}
        </button>
      </div>
    </form>

  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
  ClipboardListIcon, PackageIcon, PlusIcon, Trash2Icon,
  LoaderIcon, SearchIcon, CheckIcon, ArrowLeftIcon,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  order:      { type: Object, required: true },
  customers:  { type: Array, default: () => [] },
  priceTiers: { type: Array, default: () => [] },
  sources:    { type: Array, default: () => [] },
})

const { success, error: showError } = useToast()
const saving = ref(false)
const errors = ref({})

const orderTypes = [
  { value: 'wholesale', label: 'Wholesale' },
  { value: 'retail',    label: 'Retail' },
]

// ─── Map existing items ───────────────────────────────────────────────────────

function mapItem(existing) {
  const productName = existing.product?.name ?? ''
  const variantName = existing.variant?.variant_name ?? ''
  const displayName = variantName ? `${productName} — ${variantName}` : productName
  return {
    product_id:          existing.product_id,
    product_variant_id:  existing.product_variant_id ?? null,
    quantity:            existing.quantity,
    unit_price_bdt:      parseFloat(existing.unit_price_bdt),
    discount_percent:    parseFloat(existing.discount_percent ?? 0),
    // UI-only state
    product_search:      displayName,
    searchResults:       [],
    searching:           false,
    showDropdown:        false,
    selectedProductName: displayName,
    _searchTimer:        null,
  }
}

// ─── Pre-populate form from existing order ────────────────────────────────────

const form = reactive({
  customer_id:         props.order.customer_id,
  type:          props.order.type ?? 'retail',
  source:              props.order.source ?? '',
  price_tier_id:       props.order.price_tier_id ?? '',
  delivery_address:    props.order.delivery_address ?? '',
  delivery_city:       props.order.delivery_city ?? '',
  delivery_area:       props.order.delivery_area ?? '',
  discount_bdt:        props.order.discount_bdt ? parseFloat(props.order.discount_bdt) : null,
  discount_percent:    props.order.discount_percent ? parseFloat(props.order.discount_percent) : null,
  delivery_charge_bdt: parseFloat(props.order.delivery_charge_bdt ?? 0),
  paid_bdt:            parseFloat(props.order.paid_bdt ?? 0),
  notes:               props.order.notes ?? '',
  internal_notes:      props.order.internal_notes ?? '',
  items:               (props.order.items ?? []).map(mapItem),
})

// ─── Mutually exclusive discount ─────────────────────────────────────────────

const discountBdtActive     = computed(() => form.discount_bdt     != null && form.discount_bdt     > 0)
const discountPercentActive = computed(() => form.discount_percent != null && form.discount_percent > 0)

// ─── Customer auto-fill ───────────────────────────────────────────────────────

function onCustomerChange() {
  const customer = props.customers.find(c => c.id === form.customer_id)
  if (!customer) return
  if (customer.price_tier_id) form.price_tier_id    = customer.price_tier_id
  if (customer.address) form.delivery_address = customer.address
  if (customer.city)    form.delivery_city    = customer.city
  if (customer.area)    form.delivery_area    = customer.area
}

// ─── Item helpers ─────────────────────────────────────────────────────────────

function newItem() {
  return {
    product_id:          null,
    product_variant_id:  null,
    quantity:            1,
    unit_price_bdt:      0,
    discount_percent:    0,
    product_search:      '',
    searchResults:       [],
    searching:           false,
    showDropdown:        false,
    selectedProductName: '',
    _searchTimer:        null,
  }
}

function addItem() {
  form.items.push(newItem())
}

function removeItem(idx) {
  form.items.splice(idx, 1)
}

// ─── Product autocomplete ─────────────────────────────────────────────────────

function onProductInput(idx) {
  const item = form.items[idx]
  item.product_id          = null
  item.product_variant_id  = null
  item.selectedProductName = ''
  item.showDropdown        = false

  clearTimeout(item._searchTimer)

  if (item.product_search.trim().length < 2) {
    item.searchResults = []
    return
  }

  item._searchTimer = setTimeout(() => doSearch(idx), 300)
}

async function doSearch(idx) {
  const item = form.items[idx]
  item.searching = true
  try {
    const params = { q: item.product_search }
    if (form.price_tier_id) params.price_tier_id = form.price_tier_id
    const { data } = await window.axios.get('/api/v1/products/search', { params })
    item.searchResults = data
    item.showDropdown  = data.length > 0
  } catch {
    item.searchResults = []
  } finally {
    item.searching = false
  }
}

function selectProduct(idx, product, variant = null) {
  const item = form.items[idx]
  item.product_id         = product.id
  item.product_variant_id = variant?.id ?? null

  const displayName = variant
    ? `${product.name} — ${variant.variant_name}`
    : product.name

  item.product_search      = displayName
  item.selectedProductName = displayName

  const price = variant?.price_bdt ?? product.price_bdt ?? 0
  item.unit_price_bdt = parseFloat(price) || 0

  item.searchResults = []
  item.showDropdown  = false
}

function closeDropdown(idx) {
  setTimeout(() => {
    if (form.items[idx]) form.items[idx].showDropdown = false
  }, 150)
}

// ─── Computed totals ──────────────────────────────────────────────────────────

function itemSubtotal(item) {
  const qty     = item.quantity       || 0
  const price   = item.unit_price_bdt || 0
  const discPct = item.discount_percent || 0
  return qty * price * (1 - discPct / 100)
}

const orderSubtotal = computed(() =>
  form.items.reduce((sum, i) => sum + itemSubtotal(i), 0)
)

const effectiveDiscount = computed(() => {
  if (discountPercentActive.value) {
    return orderSubtotal.value * (form.discount_percent / 100)
  }
  return form.discount_bdt || 0
})

const orderTotal = computed(() =>
  orderSubtotal.value - effectiveDiscount.value + (form.delivery_charge_bdt || 0)
)

const orderDue = computed(() =>
  orderTotal.value - (form.paid_bdt || 0)
)

// ─── Submit ───────────────────────────────────────────────────────────────────

async function submit() {
  saving.value = true
  errors.value = {}

  const payload = {
    customer_id:         form.customer_id,
    type:          form.type,
    source:              form.source || null,
    price_tier_id:       form.price_tier_id || null,
    delivery_address:    form.delivery_address || null,
    delivery_city:       form.delivery_city || null,
    delivery_area:       form.delivery_area || null,
    discount_bdt:        discountBdtActive.value ? form.discount_bdt : null,
    discount_percent:    discountPercentActive.value ? form.discount_percent : null,
    delivery_charge_bdt: form.delivery_charge_bdt || 0,
    paid_bdt:            form.paid_bdt || 0,
    notes:               form.notes || null,
    internal_notes:      form.internal_notes || null,
    items: form.items.map(i => ({
      product_id:         i.product_id,
      product_variant_id: i.product_variant_id,
      quantity:           i.quantity,
      unit_price_bdt:     i.unit_price_bdt,
      discount_percent:   i.discount_percent || 0,
    })),
  }

  try {
    await window.axios.put(`/api/v1/sales-orders/${props.order.id}`, payload)
    success('Sales order updated successfully!')
    router.visit(route('sales-orders.show', props.order.id))
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {}
      const firstMsg = Object.values(errors.value)[0]?.[0]
      if (firstMsg) showError(firstMsg)
    } else {
      showError(err.response?.data?.message || 'Failed to update sales order.')
    }
    saving.value = false
  }
}
</script>
