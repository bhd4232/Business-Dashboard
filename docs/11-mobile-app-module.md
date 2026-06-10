# Module 11: Mobile App (React Native)

## Overview

React Native mobile application for field operations - barcode scanning, stock checking, sales order creation, and payment collection on the go.

## Architecture

```
┌─────────────────────────────────────┐
│         React Native App             │
│  ┌─────────┐  ┌─────────┐          │
│  │ Screens  │  │Navigation│          │
│  └────┬─────┘  └─────────┘          │
│       │                             │
│  ┌────┴──────────────────────────┐  │
│  │         API Layer              │  │
│  │  (Axios + Auth + Interceptors)│  │
│  └────┬──────────────────────────┘  │
│       │                             │
│  ┌────┴──────┐  ┌──────────────┐   │
│  │ Camera    │  │ Local Storage│   │
│  │ (Scanner) │  │ (AsyncStore) │   │
│  └───────────┘  └──────────────┘   │
└─────────────┬───────────────────────┘
              │ HTTPS
              │ Same Laravel API
              ▼
      ┌───────────────┐
      │  ZamZam ERP   │
      │  API Server    │
      └───────────────┘
```

## App Screens

### 1. Authentication
| Screen | Description |
|--------|-------------|
| Login | Email/phone + password login |
| OTP Verify | Phone OTP verification |
| Pin Setup | Set 4-digit quick access PIN |

### 2. Dashboard (Role-based)
| Screen | Description |
|--------|-------------|
| Salesman Dashboard | Today's sales, pending collections, my customers |
| Storekeeper Dashboard | Today's receives, stock alerts, pending transfers |
| Reseller Dashboard | Credit balance, recent orders, notifications |

### 3. Product & Stock
| Screen | Description |
|--------|-------------|
| Product List | Search/browse products |
| Product Detail | Price, stock, barcode |
| Barcode Scanner | Scan barcode → product lookup |
| Stock Check | Check stock at warehouse |

### 4. Sales Order (Salesman)
| Screen | Description |
|--------|-------------|
| Customer Selection | Select or search customer |
| Create Order | Add products, qty, see pricing |
| Order Review | Review total, discount, payment |
| Order Confirmation | Submit order (cash/credit) |
| Order History | List past orders |
| Order Detail | View order status + items |

### 5. Payment Collection (Salesman)
| Screen | Description |
|--------|-------------|
| Customer Balance | Show outstanding balance |
| Record Payment | Amount, method, reference |
| Payment Confirmation | Photo of cash receipt (optional) |
| Payment History | Today's/all collections |

### 6. Inventory (Storekeeper)
| Screen | Description |
|--------|-------------|
| Goods Receive | Scan barcode → enter received qty |
| Stock Transfer | Select source/dest warehouse, add items |
| Stock Adjustment | Physical count entry |
| Stock Search | Search by name/barcode |

### 7. Reseller Panel
| Screen | Description |
|--------|-------------|
| My Balance | Credit limit, outstanding, available |
| My Orders | Order history with status |
| My Invoices | Invoice list + PDF view |
| My Payments | Payment history |
| Price List | Products with tier pricing |
| Notifications | Push notification list |

## API Endpoints Used by Mobile

Mobile uses the **same API** as the web frontend with `auth:sanctum` token authentication.

### Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/auth/login | Login (returns token) |
| POST | /api/auth/login/phone | Phone + OTP login |
| POST | /api/auth/logout | Logout |
| GET | /api/auth/me | Current user + role |

### Product & Stock
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/products | Product list (search, filter) |
| GET | /api/products/{id} | Product detail |
| GET | /api/stock | Stock overview |
| GET | /api/stock/low-stock | Low stock alerts |
| POST | /api/barcodes/scan | Scan barcode lookup |

### Sales (Salesman)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/customers | Customer list |
| GET | /api/customers/{id} | Customer detail + balance |
| POST | /api/wholesale/orders | Create order |
| GET | /api/wholesale/orders | Order list |
| GET | /api/wholesale/orders/{id} | Order detail |

### Payment (Salesman)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/customers/{id}/ledger | Customer credit info |
| POST | /api/payments | Record payment |
| GET | /api/payments | Payment list |

### Inventory (Storekeeper)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/stock-transfers | Create transfer |
| POST | /api/stock-adjustments | Create adjustment |
| GET | /api/stock-transfers | Transfer list |
| GET | /api/warehouses | Warehouse list |

### Reseller
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/reseller/dashboard | Dashboard data |
| GET | /api/reseller/credit-summary | Credit info |
| GET | /api/reseller/orders | Order history |
| GET | /api/reseller/products | Price list |

## Technical Specifications

### Project Structure
```
mobile/
├── App.js                    (Entry point)
├── app.json                  (App config)
├── package.json
├── src/
│   ├── api/
│   │   ├── client.js         (Axios instance + interceptors)
│   │   ├── auth.js           (Auth API calls)
│   │   ├── products.js       (Product API calls)
│   │   ├── orders.js         (Order API calls)
│   │   ├── payments.js       (Payment API calls)
│   │   ├── inventory.js      (Inventory API calls)
│   │   └── reseller.js       (Reseller API calls)
│   ├── components/
│   │   ├── common/
│   │   │   ├── Button.js
│   │   │   ├── Card.js
│   │   │   ├── Input.js
│   │   │   ├── SearchBar.js
│   │   │   └── Badge.js
│   │   ├── product/
│   │   │   ├── ProductCard.js
│   │   │   └── StockBadge.js
│   │   ├── order/
│   │   │   ├── OrderCard.js
│   │   │   ├── OrderItemRow.js
│   │   │   └── CartSummary.js
│   │   └── payment/
│   │       ├── PaymentMethodPicker.js
│   │       └── ReceiptCamera.js
│   ├── navigation/
│   │   ├── AppNavigator.js   (Root navigator)
│   │   ├── AuthNavigator.js  (Login flow)
│   │   ├── SalesmanNavigator.js
│   │   ├── StorekeeperNavigator.js
│   │   └── ResellerNavigator.js
│   ├── screens/
│   │   ├── auth/
│   │   ├── dashboard/
│   │   ├── products/
│   │   ├── orders/
│   │   ├── payments/
│   │   ├── inventory/
│   │   └── reseller/
│   ├── store/
│   │   ├── authStore.js      (Zustand auth state)
│   │   ├── cartStore.js      (Zustand cart state)
│   │   └── syncStore.js     (Offline queue state)
│   ├── utils/
│   │   ├── constants.js
│   │   ├── formatters.js     (BDT format, date format)
│   │   ├── validators.js
│   │   └── permissions.js    (Role-based feature flags)
│   └── hooks/
│       ├── useAuth.js
│       ├── useBarcodeScanner.js
│       └── useOfflineSync.js
└── android/
└── ios/
```

### Key Packages
| Package | Purpose |
|---------|---------|
| react-navigation | Navigation |
| zustand | State management |
| axios | HTTP client |
| react-native-vision-camera | Barcode scanning |
| @react-native-async-storage | Local storage |
| react-native-paper | UI components (Material Design) |
| react-native-signature-canvas | Signature capture (delivery proof) |
| react-native-svg | SVG support |
| react-native-toast-message | Toast notifications |
| @tanstack/react-query | Server state management |
| react-native-blob-util | PDF download & view |
| notifee | Push notifications |

### Offline Support

```
Online-first with offline queue:

1. App checks network before every API call
2. If offline:
   a. Queue the action in local storage (AsyncStore)
   b. Show "Pending sync" badge on the action
   c. Allow user to continue working
3. When back online:
   a. Process queued actions in order
   b. Mark as synced or show error
   c. Update local data from server

Supported offline actions:
- Record payment (queued)
- Create sales order (queued)
- Stock adjustment (queued)

NOT supported offline:
- Product search (needs server)
- Stock check (needs server)
```

### Barcode Scanner Implementation

```javascript
import { useCameraDevices } from 'react-native-vision-camera';
import { useScanBarcodes } from 'vision-camera-code-scanner';

function BarcodeScannerScreen({ onScan }) {
  const device = useCameraDevices().back;
  const [frameProcessor, barcodes] = useScanBarcodes([
    BarcodeFormat.ALL_FORMATS,
  ]);

  useEffect(() => {
    if (barcodes.length > 0) {
      const code = barcodes[0].displayValue;
      onScan(code);
    }
  }, [barcodes]);

  return (
    <Camera
      device={device}
      isActive={true}
      frameProcessor={frameProcessor}
      style={StyleSheet.absoluteFill}
    />
  );
}
```

### BDT Currency Formatting

```javascript
export const formatBDT = (amount) => {
  return '৳' + Number(amount).toLocaleString('en-BD', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
};
```

## Push Notification System

### Notification Types
| Type | Target | Description |
|------|--------|-------------|
| order_status | Salesman, Reseller | Order confirmed/shipped/delivered |
| payment_received | Reseller | Payment received confirmation |
| credit_limit | Reseller | Credit limit changed |
| low_stock | Storekeeper | Product below minimum |
| goods_received | Storekeeper | New goods to receive |
| overdue | Salesman | Customer payment overdue |
| new_order | Admin, Manager | New WooCommerce order |

### Implementation
```
1. Laravel generates notification event
2. Firebase Cloud Messaging (FCM) sends push
3. App receives via notifee (foreground) / FCM (background)
4. Navigate to relevant screen on tap
5. Mark notification as read in API
```

## Developer Notes

1. Use same Laravel API as web - no separate mobile API needed
2. Token-based auth (Sanctum) - store token in AsyncStorage
3. Role-based navigation - different tab stacks per role
4. Barcode scanner needs camera permission - handle gracefully
5. Payment receipt photo stored locally until sync (compressed JPEG)
6. All API calls should have timeout (10 seconds) and retry logic
7. Use React Query for caching + background refetching
8. App should work on Android 8+ and iOS 14+
9. Consider React Native Expo for faster development (managed workflow)
10. Test barcode scanning in various lighting conditions
11. BDT formatting must use Bangla locale conventions
12. Keep APK size under 30MB (use code splitting)
