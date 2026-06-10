<template>
  <AppLayout>
    <Head :title="shipment.shipment_no" />

    <!-- Header -->
    <div class="mb-6">
      <BackButton label="Shipments" to="shipments.index" />
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100 font-mono">{{ shipment.shipment_no }}</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            {{ typeLabel(shipment.shipping_type) }}
            <span v-if="shipment.purchase_order">·
              <span class="font-mono text-indigo-600 dark:text-primary-400">{{ shipment.purchase_order.po_number }}</span>
            </span>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <!-- Advance Status Button -->
          <button v-if="canAdvance" @click="showAdvanceDialog = true"
            class="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <ArrowRightIcon class="w-4 h-4" />
            {{ nextStatusLabel }}
          </button>
          <!-- Landing Cost -->
          <Link :href="route('shipments.landing-cost', shipment.id)"
            class="inline-flex items-center gap-2 border border-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 text-emerald-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <CalculatorIcon class="w-4 h-4" /> Landing Cost
          </Link>
        </div>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Status</p>
        <span :class="statusBadge(shipment.status)" class="rounded-full px-2.5 py-0.5 text-xs font-medium">
          {{ statusLabel(shipment.status) }}
        </span>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">ETA</p>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ formatDate(shipment.eta) }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Route</p>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ shipment.port_loading || '?' }} → {{ shipment.port_discharge || '?' }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Total Costs</p>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 font-mono">৳{{ totalCostBdt }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
      <div class="flex border-b border-slate-200 dark:border-slate-700 px-6 gap-1 overflow-x-auto">
        <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
          :class="['py-3 px-4 text-sm font-medium border-b-2 whitespace-nowrap transition-colors -mb-px',
            activeTab === tab.id
              ? 'border-cyan-600 text-cyan-600'
              : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100']">
          {{ tab.label }}
          <span v-if="tab.count" class="ml-1 text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-full px-1.5 py-0.5">
            {{ tab.count }}
          </span>
        </button>
      </div>

      <!-- ═══ Items Tab ═══════════════════════════════════════════ -->
      <div v-if="activeTab === 'items'" class="p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Shipment Items</h3>
          <button @click="showAddItemForm = !showAddItemForm"
            class="inline-flex items-center gap-1 text-sm text-cyan-600 hover:text-cyan-700 font-medium">
            <PlusIcon class="w-3 h-3" /> Add Item
          </button>
        </div>

        <!-- Add Item Form -->
        <div v-if="showAddItemForm" class="mb-4 p-4 bg-slate-50 dark:bg-slate-700/50 rounded-lg border border-slate-200 dark:border-slate-700 grid grid-cols-2 md:grid-cols-4 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Product ID</label>
            <input v-model.number="newItem.product_id" type="number" placeholder="Product ID"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Quantity</label>
            <input v-model.number="newItem.quantity" type="number" min="1"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono text-right" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Weight (kg)</label>
            <input v-model.number="newItem.weight_kg" type="number" min="0" step="0.001"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono text-right" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Volume (cm³)</label>
            <input v-model.number="newItem.volume_cm3" type="number" min="0" step="0.001"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono text-right" />
          </div>
          <div class="col-span-full flex justify-end gap-2">
            <button @click="showAddItemForm = false" type="button"
              class="text-sm text-slate-600 dark:text-slate-400 px-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50">Cancel</button>
            <button @click="addItem" type="button" :disabled="addingItem"
              class="text-sm text-white bg-cyan-600 hover:bg-cyan-700 px-4 py-1.5 rounded-lg transition-colors flex items-center gap-1">
              <LoaderIcon v-if="addingItem" class="w-3 h-3 animate-spin" /> Add
            </button>
          </div>
        </div>

        <table class="w-full">
          <thead>
            <tr class="bg-slate-50 dark:bg-slate-700/50 rounded-lg">
              <th class="text-left text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2">Product</th>
              <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2">Qty</th>
              <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2">Cartons</th>
              <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2">Weight (kg)</th>
              <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2">Vol (cm³)</th>
              <th class="w-8"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="localItems.length === 0">
              <td colspan="6" class="text-center py-8 text-slate-400 text-sm">No items added yet</td>
            </tr>
            <tr v-for="item in localItems" :key="item.id" class="border-b border-slate-100 dark:border-slate-700">
              <td class="px-3 py-3">
                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ item.product?.name }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ item.product?.sku }}</p>
              </td>
              <td class="px-3 py-3 text-right text-sm font-mono text-slate-700 dark:text-slate-300">{{ item.quantity }}</td>
              <td class="px-3 py-3 text-right text-sm font-mono text-slate-500 dark:text-slate-400">{{ item.carton_count || '—' }}</td>
              <td class="px-3 py-3 text-right text-sm font-mono text-slate-700 dark:text-slate-300">{{ item.weight_kg || '—' }}</td>
              <td class="px-3 py-3 text-right text-sm font-mono text-slate-700 dark:text-slate-300">{{ item.volume_cm3 || '—' }}</td>
              <td class="px-3 py-3">
                <button @click="removeItem(item)" class="text-slate-400 hover:text-red-500">
                  <TrashIcon class="w-3.5 h-3.5" />
                </button>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="localItems.length">
            <tr class="border-t-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50">
              <td class="px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-400">Total</td>
              <td class="px-3 py-2 text-right text-sm font-mono font-semibold dark:text-slate-200">{{ totalQty }}</td>
              <td class="px-3 py-2 text-right text-sm font-mono font-semibold dark:text-slate-200">{{ totalCartons }}</td>
              <td class="px-3 py-2 text-right text-sm font-mono font-semibold dark:text-slate-200">{{ totalWeight }}</td>
              <td class="px-3 py-2 text-right text-sm font-mono font-semibold dark:text-slate-200">{{ totalVolume }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- ═══ Costs Tab ═══════════════════════════════════════════ -->
      <div v-if="activeTab === 'costs'" class="p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Shipment Costs</h3>
          <button @click="showAddCostForm = !showAddCostForm"
            class="inline-flex items-center gap-1 text-sm text-emerald-600 hover:text-emerald-700 font-medium">
            <PlusIcon class="w-3 h-3" /> Add Cost
          </button>
        </div>

        <!-- Add Cost Form -->
        <div v-if="showAddCostForm" class="mb-4 p-4 bg-slate-50 dark:bg-slate-700/50 rounded-lg border border-slate-200 dark:border-slate-700 grid grid-cols-2 md:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Cost Type <span class="text-red-500">*</span></label>
            <select v-model="newCost.cost_type"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 bg-white dark:bg-slate-800 dark:text-slate-100">
              <option v-for="ct in costTypes" :key="ct.value" :value="ct.value">{{ ct.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Amount (BDT) <span class="text-red-500">*</span></label>
            <input v-model.number="newCost.amount_bdt" type="number" min="0" step="0.01" placeholder="0.00"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono text-right" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Description</label>
            <input v-model="newCost.description" type="text" placeholder="Optional note"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Voucher No.</label>
            <input v-model="newCost.voucher_no" type="text" placeholder="Receipt / voucher"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Paid Date</label>
            <input v-model="newCost.paid_at" type="date"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500" />
          </div>
          <div class="flex items-end justify-end gap-2">
            <button @click="showAddCostForm = false" type="button"
              class="text-sm text-slate-600 dark:text-slate-400 px-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50">Cancel</button>
            <button @click="addCost" type="button" :disabled="addingCost"
              class="text-sm text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-1.5 rounded-lg transition-colors flex items-center gap-1">
              <LoaderIcon v-if="addingCost" class="w-3 h-3 animate-spin" /> Add
            </button>
          </div>
        </div>

        <table class="w-full">
          <thead>
            <tr class="bg-slate-50 dark:bg-slate-700/50 rounded-lg">
              <th class="text-left text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2">Type</th>
              <th class="text-left text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2">Description</th>
              <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2">Amount (BDT)</th>
              <th class="text-left text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2">Status</th>
              <th class="w-8"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="localCosts.length === 0">
              <td colspan="5" class="text-center py-8 text-slate-400 text-sm">No cost entries yet</td>
            </tr>
            <tr v-for="cost in localCosts" :key="cost.id" class="border-b border-slate-100 dark:border-slate-700">
              <td class="px-3 py-3">
                <span class="text-xs font-medium bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300 rounded-full px-2 py-0.5">
                  {{ costTypeLabel(cost.cost_type) }}
                </span>
              </td>
              <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-400">{{ cost.description || '—' }}</td>
              <td class="px-3 py-3 text-right text-sm font-mono font-semibold text-slate-800 dark:text-slate-200">
                ৳{{ Number(cost.amount_bdt).toLocaleString('en-BD', { minimumFractionDigits: 2 }) }}
              </td>
              <td class="px-3 py-3">
                <span v-if="cost.paid_at" class="text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 rounded-full px-2 py-0.5">Paid</span>
                <span v-else class="text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 rounded-full px-2 py-0.5">Unpaid</span>
              </td>
              <td class="px-3 py-3">
                <button @click="removeCost(cost)" class="text-slate-400 hover:text-red-500">
                  <TrashIcon class="w-3.5 h-3.5" />
                </button>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="localCosts.length">
            <tr class="border-t-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50">
              <td colspan="2" class="px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-400 text-right">Grand Total</td>
              <td class="px-3 py-2 text-right text-sm font-mono font-bold text-slate-900 dark:text-slate-100">
                ৳{{ totalCostBdt }}
              </td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- ═══ Documents Tab ══════════════════════════════════════ -->
      <div v-if="activeTab === 'documents'" class="p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Documents</h3>
          <label class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-primary-400 hover:text-indigo-700 dark:hover:text-primary-300 font-medium cursor-pointer">
            <UploadIcon class="w-3 h-3" /> Upload
            <input type="file" class="hidden" @change="uploadDocument" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx" />
          </label>
        </div>

        <div v-if="uploadingDoc" class="mb-3 p-3 bg-indigo-50 rounded-lg border border-indigo-200 flex gap-3 items-center">
          <div class="grid grid-cols-2 gap-2 flex-1">
            <select v-model="newDoc.doc_type"
              class="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-2 py-1.5 bg-white dark:bg-slate-800 dark:text-slate-100">
              <option value="bl">Bill of Lading</option>
              <option value="packing_list">Packing List</option>
              <option value="invoice">Commercial Invoice</option>
              <option value="certificate">Certificate</option>
              <option value="customs_declaration">Customs Declaration</option>
              <option value="other">Other</option>
            </select>
            <input v-model="newDoc.title" type="text" placeholder="Document title"
              class="text-sm border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 rounded-lg px-2 py-1.5" />
          </div>
          <button @click="confirmUpload" :disabled="savingDoc"
            class="text-sm bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 flex items-center gap-1">
            <LoaderIcon v-if="savingDoc" class="w-3 h-3 animate-spin" /> Save
          </button>
          <button @click="cancelUpload" class="text-slate-400 hover:text-red-500">
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <div v-if="localDocs.length === 0 && !uploadingDoc" class="text-center py-8 text-slate-400 text-sm border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
          No documents uploaded
        </div>

        <div v-else class="space-y-2">
          <div v-for="doc in localDocs" :key="doc.id"
            class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-700/50 rounded-lg border border-slate-200 dark:border-slate-700">
            <FileIcon class="w-5 h-5 text-indigo-400 shrink-0" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{{ doc.title }}</p>
              <p class="text-xs text-slate-500 dark:text-slate-400">{{ docTypeLabel(doc.doc_type) }} · {{ doc.file_size_kb }}KB · {{ doc.uploaded_by?.name }}</p>
            </div>
            <button @click="removeDoc(doc)" class="text-slate-400 hover:text-red-500 shrink-0">
              <TrashIcon class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      <!-- ═══ Details Tab ═════════════════════════════════════════ -->
      <div v-if="activeTab === 'details'" class="p-6">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="(val, key) in detailFields" :key="key">
            <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">{{ key }}</dt>
            <dd class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ val || '—' }}</dd>
          </div>
        </dl>
      </div>

      <!-- ═══ Timeline Tab ════════════════════════════════════════ -->
      <div v-if="activeTab === 'timeline'" class="p-6">
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-4">Status Timeline</h3>
        <div class="relative">
          <div class="absolute left-3.5 top-0 bottom-0 w-0.5 bg-slate-200 dark:bg-slate-700"></div>
          <div class="space-y-4">
            <div v-for="(hist, idx) in shipment.status_history" :key="idx" class="flex gap-4">
              <div class="w-7 h-7 rounded-full shrink-0 flex items-center justify-center z-10"
                :class="idx === shipment.status_history.length - 1 ? 'bg-cyan-100 text-cyan-700' : 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300'">
                <CheckIcon class="w-3.5 h-3.5" />
              </div>
              <div class="pb-4">
                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ statusLabel(hist.status) }}</p>
                <p v-if="hist.location" class="text-xs text-slate-500 dark:text-slate-400">📍 {{ hist.location }}</p>
                <p v-if="hist.notes" class="text-xs text-slate-500 dark:text-slate-400 italic mt-0.5">{{ hist.notes }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                  {{ formatDateTime(hist.changed_at) }}
                  <span v-if="hist.changed_by"> · {{ hist.changed_by.name }}</span>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Advance Status Dialog -->
    <div v-if="showAdvanceDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl max-w-md w-full mx-4 p-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-1">Advance Status</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
          Move shipment from
          <span :class="statusBadge(shipment.status)" class="rounded-full px-2 py-0.5 text-xs font-medium">{{ statusLabel(shipment.status) }}</span>
          to
          <span class="bg-cyan-100 text-cyan-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ nextStatusLabel }}</span>
        </p>
        <div class="space-y-3 mb-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Location (optional)</label>
            <input v-model="advanceForm.location" type="text" placeholder="Current location..."
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes (optional)</label>
            <textarea v-model="advanceForm.notes" rows="2" placeholder="Update notes..."
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 resize-none"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <button @click="showAdvanceDialog = false"
            class="px-4 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50">Cancel</button>
          <button @click="advanceStatus" :disabled="advancing"
            class="px-4 py-2 text-sm bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg flex items-center gap-2">
            <LoaderIcon v-if="advancing" class="w-4 h-4 animate-spin" />
            Confirm
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
  ArrowRightIcon, CalculatorIcon, PlusIcon, TrashIcon,
  CheckIcon, LoaderIcon, UploadIcon, FileIcon, XIcon,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackButton from '@/Components/UI/BackButton.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  shipment:          { type: Object, required: true },
  statuses:          { type: Array, default: () => [] },
  costTypes:         { type: Array, default: () => [] },
  allocationMethods: { type: Array, default: () => [] },
})

const { success, error: showError } = useToast()

// ── Local reactive copies ────────────────────────────────────────
const localItems = ref([...(props.shipment.items ?? [])])
const localCosts = ref([...(props.shipment.costs ?? [])])
const localDocs  = ref([...(props.shipment.documents ?? [])])

// ── Tabs ─────────────────────────────────────────────────────────
const activeTab = ref('items')
const tabs = computed(() => [
  { id: 'items',     label: 'Items',     count: localItems.value.length   },
  { id: 'costs',     label: 'Costs',     count: localCosts.value.length   },
  { id: 'documents', label: 'Documents', count: localDocs.value.length    },
  { id: 'details',   label: 'Details'                                      },
  { id: 'timeline',  label: 'Timeline'                                     },
])

// ── Status helpers ────────────────────────────────────────────────
const statusBadges = {
  booked: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300', loaded: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  departed: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300', in_transit: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
  arrived: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300', clearing: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  cleared: 'bg-teal-100 text-teal-700', delivered_to_warehouse: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
}
const sLabels = {
  booked: 'Booked', loaded: 'Loaded', departed: 'Departed', in_transit: 'In Transit',
  arrived: 'Arrived', clearing: 'Clearing', cleared: 'Cleared', delivered_to_warehouse: 'Delivered',
}
const nextStatusMap = {
  booked: 'loaded', loaded: 'departed', departed: 'in_transit', in_transit: 'arrived',
  arrived: 'clearing', clearing: 'cleared', cleared: 'delivered_to_warehouse',
}
const typeLabels = { sea: 'Sea Freight', air: 'Air Freight', rail: 'Rail', courier: 'Courier' }
function statusBadge(s) { return statusBadges[s] || 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }
function statusLabel(s) { return sLabels[s] || s }
function typeLabel(t)   { return typeLabels[t] || t }

const canAdvance      = computed(() => !!nextStatusMap[props.shipment.status])
const nextStatusLabel = computed(() => statusLabel(nextStatusMap[props.shipment.status]))

// ── Totals (costs) ────────────────────────────────────────────────
const totalCostBdt = computed(() =>
  localCosts.value.reduce((s, c) => s + Number(c.amount_bdt), 0)
    .toLocaleString('en-BD', { minimumFractionDigits: 2 })
)

// ── Totals (items) ────────────────────────────────────────────────
const totalQty     = computed(() => localItems.value.reduce((s, i) => s + (i.quantity    || 0), 0))
const totalCartons = computed(() => localItems.value.reduce((s, i) => s + (i.carton_count || 0), 0))
const totalWeight  = computed(() => localItems.value.reduce((s, i) => s + Number(i.weight_kg  || 0), 0).toFixed(3))
const totalVolume  = computed(() => localItems.value.reduce((s, i) => s + Number(i.volume_cm3 || 0), 0).toFixed(3))

// ── Add Item ─────────────────────────────────────────────────────
const showAddItemForm = ref(false)
const addingItem      = ref(false)
const newItem = reactive({ product_id: '', quantity: 1, weight_kg: null, volume_cm3: null })

async function addItem() {
  addingItem.value = true
  try {
    const res = await window.axios.post(`/api/v1/shipments/${props.shipment.id}/items`, newItem)
    localItems.value.push(res.data)
    Object.assign(newItem, { product_id: '', quantity: 1, weight_kg: null, volume_cm3: null })
    showAddItemForm.value = false
    success('Item added.')
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to add item.')
  } finally {
    addingItem.value = false
  }
}

async function removeItem(item) {
  if (!confirm('Remove this item?')) return
  try {
    await window.axios.delete(`/api/v1/shipments/${props.shipment.id}/items/${item.id}`)
    localItems.value = localItems.value.filter(i => i.id !== item.id)
    success('Item removed.')
  } catch { showError('Failed to remove item.') }
}

// ── Add Cost ─────────────────────────────────────────────────────
const showAddCostForm = ref(false)
const addingCost      = ref(false)
const newCost = reactive({ cost_type: 'freight', description: '', amount_bdt: '', voucher_no: '', paid_at: '' })

function costTypeLabel(t) {
  const found = props.costTypes.find(c => c.value === t)
  return found ? found.label : t
}

async function addCost() {
  addingCost.value = true
  try {
    const res = await window.axios.post(`/api/v1/shipments/${props.shipment.id}/costs`, newCost)
    localCosts.value.push(res.data)
    Object.assign(newCost, { cost_type: 'freight', description: '', amount_bdt: '', voucher_no: '', paid_at: '' })
    showAddCostForm.value = false
    success('Cost entry added.')
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to add cost.')
  } finally {
    addingCost.value = false
  }
}

async function removeCost(cost) {
  if (!confirm('Remove this cost entry?')) return
  try {
    await window.axios.delete(`/api/v1/shipments/${props.shipment.id}/costs/${cost.id}`)
    localCosts.value = localCosts.value.filter(c => c.id !== cost.id)
    success('Cost removed.')
  } catch { showError('Failed to remove cost.') }
}

// ── Documents ─────────────────────────────────────────────────────
const uploadingDoc = ref(false)
const savingDoc    = ref(false)
const newDoc       = reactive({ doc_type: 'bl', title: '' })
let   pendingFile  = null

function uploadDocument(event) {
  pendingFile = event.target.files[0]
  if (!pendingFile) return
  newDoc.title = pendingFile.name.replace(/\.[^/.]+$/, '')
  uploadingDoc.value = true
}

function cancelUpload() {
  uploadingDoc.value = false
  pendingFile = null
}

async function confirmUpload() {
  if (!pendingFile) return
  savingDoc.value = true
  const formData = new FormData()
  formData.append('file', pendingFile)
  formData.append('doc_type', newDoc.doc_type)
  formData.append('title', newDoc.title)
  try {
    const res = await window.axios.post(`/api/v1/shipments/${props.shipment.id}/documents`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    localDocs.value.push(res.data)
    uploadingDoc.value = false
    pendingFile = null
    success('Document uploaded.')
  } catch { showError('Failed to upload document.') }
  finally  { savingDoc.value = false }
}

async function removeDoc(doc) {
  if (!confirm('Delete this document?')) return
  try {
    await window.axios.delete(`/api/v1/shipments/${props.shipment.id}/documents/${doc.id}`)
    localDocs.value = localDocs.value.filter(d => d.id !== doc.id)
    success('Document deleted.')
  } catch { showError('Failed to delete document.') }
}

function docTypeLabel(t) {
  const map = { bl: 'B/L', packing_list: 'Packing List', invoice: 'Invoice', certificate: 'Certificate', customs_declaration: 'Customs Decl.', other: 'Other' }
  return map[t] || t
}

// ── Advance Status ─────────────────────────────────────────────────
const showAdvanceDialog = ref(false)
const advancing         = ref(false)
const advanceForm       = reactive({ notes: '', location: '' })

async function advanceStatus() {
  advancing.value = true
  try {
    await window.axios.post(`/api/v1/shipments/${props.shipment.id}/advance-status`, advanceForm)
    success('Status updated!')
    showAdvanceDialog.value = false
    router.reload()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to advance status.')
  } finally {
    advancing.value = false
  }
}

// ── Details ─────────────────────────────────────────────────────
const detailFields = computed(() => ({
  'Carrier':               props.shipment.carrier,
  'Container No.':         props.shipment.container_no,
  'Container Type':        props.shipment.container_type,
  'Bill of Lading':        props.shipment.bl_number,
  'Port of Loading':       props.shipment.port_loading,
  'Port of Discharge':     props.shipment.port_discharge,
  'ETD':                   formatDate(props.shipment.etd),
  'ETA':                   formatDate(props.shipment.eta),
  'ATD (Actual)':          formatDate(props.shipment.atd),
  'ATA (Actual)':          formatDate(props.shipment.ata),
  'Customs Agent':         props.shipment.customs_agent,
  'Customs Declaration':   props.shipment.customs_declaration_no,
  'Allocation Method':     props.allocationMethods.find(m => m.value === props.shipment.cost_allocation_method)?.label,
  'Created By':            props.shipment.created_by?.name,
  'Notes':                 props.shipment.notes,
}))

// ── Helpers ──────────────────────────────────────────────────────
function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
