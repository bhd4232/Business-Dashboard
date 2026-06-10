<template>
  <div
    :class="[
      'relative inline-flex items-center justify-center shrink-0',
      sizeClass,
      animate ? 'transition-transform duration-300 hover:scale-110' : '',
    ]"
  >
    <img
      :src="iconUrl"
      :alt="name + ' icon'"
      class="w-full h-full object-contain"
      style="filter: drop-shadow(0 4px 8px rgba(0,0,0,0.15));"
      loading="lazy"
      draggable="false"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'

// ── Icon Registry (Icons8 Fluency 96px) ────────────────────────────────────
const ICON_MAP = {
  // Navigation & Modules
  dashboard:       'dashboard-layout.png',
  suppliers:       'factory.png',
  products:        'open-box.png',
  purchase_orders: 'purchase-order.png',   // PO list / statcard
  procurement:     'shopping-cart.png',     // Procurement sidebar GROUP header
  new_po:          'add-shopping-cart.png', // "New Purchase Order" quick action
  categories:      'category.png',
  inventory:       'warehouse.png',
  warehouses:      'commercial-development-management.png',
  stock:           'box.png',
  transfers:       'transfer-between-accounts.png',
  adjustments:     'scales.png',
  barcodes:        'barcode.png',
  shipping:        'shipped.png',
  shipment:        'cargo-ship.png',
  domestic:        'delivery.png',
  parcels:         'parcel.png',
  finance:         'money-bag.png',
  payments:        'banknote.png',
  accounts:        'accounting.png',
  chat:            'chat.png',
  workflows:       'workflow.png',
  conversations:   'communication.png',
  reports:         'bar-chart.png',
  settings:        'gear.png',
  customers:       'conference-call.png',
  orders:          'purchase-order.png',
  invoices:        'invoice.png',
  sales:           'sales-performance.png',

  // Dashboard Stats
  trending_up:     'bullish.png',
  low_stock:       'low-battery.png',
  alert:           'box-important.png',
  check:           'checked.png',
  wallet:          'wallet.png',
  target:          'target.png',
  star:            'star.png',
  fire:            'fire-element.png',
  award:           'medal.png',
  pie_chart:       'pie-chart.png',
  activity:        'performance-macbook.png',
  cloud:           'cloud.png',
  security:        'police-badge.png',
  key:             'key.png',
  layers:          'stack.png',
  location:        'map-pin.png',
  logout:          'exit.png',
  user:            'user-male-circle.png',
  search:          'search.png',
  download:        'download.png',
  upload:          'upload.png',
  image:           'image-file.png',
  video:           'video-file.png',
  home:            'home.png',
  lock:            'lock.png',
  zap:             'flash-on.png',
  trending_down:   'stocks.png',
  china:           'china.png',
  bangladesh:      'bangladesh.png',

  // Actions (used with ThreeDIcon at sm/md size)
  edit:            'edit.png',
  delete:          'delete.png',
  add:             'add.png',
  save:            'save.png',
  close:           'cancel.png',
  filter:          'filter.png',
  sort:            'sort.png',
  refresh:         'synchronize.png',
  view:            'visible.png',
  print:           'print.png',
  export:          'export.png',
  import:          'import.png',
  calendar:        'calendar.png',
  clock:           'clock.png',
  money:           'money.png',
  check_circle:    'ok.png',
  error:           'error.png',
  info:            'info.png',
  warning:         'warning.png',
}

const ICON_BASE = '/icons/fluency'

const props = defineProps({
  name:    { type: String,  required: true },
  size:    { type: String,  default: 'md', validator: v => ['xs','sm','md','lg','xl','2xl'].includes(v) },
  animate: { type: Boolean, default: true },
})

const iconUrl = computed(() => {
  const file = ICON_MAP[props.name] ?? ICON_MAP.stock
  return `${ICON_BASE}/${file}`
})

const sizeClass = computed(() => ({
  xs:  'w-5 h-5',
  sm:  'w-6 h-6',
  md:  'w-8 h-8',
  lg:  'w-10 h-10',
  xl:  'w-12 h-12',
  '2xl': 'w-16 h-16',
}[props.size]))
</script>
