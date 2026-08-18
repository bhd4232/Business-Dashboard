/* @ds-bundle: {"format":4,"namespace":"ZamZamERPDesignSystem_a134f1","components":[{"name":"AdminBadge","sourcePath":"components/admin/AdminBadge.jsx"},{"name":"AdminButton","sourcePath":"components/admin/AdminButton.jsx"},{"name":"Card","sourcePath":"components/admin/Card.jsx"},{"name":"Input","sourcePath":"components/admin/Input.jsx"},{"name":"SidebarNavItem","sourcePath":"components/admin/SidebarNavItem.jsx"},{"name":"PriceTag","sourcePath":"components/storefront/PriceTag.jsx"},{"name":"ProductCard","sourcePath":"components/storefront/ProductCard.jsx"},{"name":"SearchBar","sourcePath":"components/storefront/SearchBar.jsx"},{"name":"StorefrontBadge","sourcePath":"components/storefront/StorefrontBadge.jsx"},{"name":"StorefrontButton","sourcePath":"components/storefront/StorefrontButton.jsx"}],"sourceHashes":{"components/admin/AdminBadge.jsx":"ccba9cb95c1a","components/admin/AdminButton.jsx":"44f6465135d8","components/admin/Card.jsx":"73bc34e46319","components/admin/Input.jsx":"4da034df8c85","components/admin/SidebarNavItem.jsx":"63c697701f43","components/storefront/PriceTag.jsx":"ad6673309727","components/storefront/ProductCard.jsx":"151455c9e833","components/storefront/SearchBar.jsx":"b2bb91ec0403","components/storefront/StorefrontBadge.jsx":"4f2bab48ed7e","components/storefront/StorefrontButton.jsx":"93ef20401c5e","ui_kits/admin-dashboard/AdminShell.jsx":"acb7cd3ba85b","ui_kits/admin-dashboard/DashboardScreen.jsx":"092606a2b2f7","ui_kits/admin-dashboard/ProductsScreen.jsx":"8c3d29808e8a","ui_kits/admin-dashboard/ThemeGalleryScreen.jsx":"55e5e3b44a6b","ui_kits/storefront-marketplace-pro/CartScreen.jsx":"c3754368b83c","ui_kits/storefront-marketplace-pro/HomeScreen.jsx":"4dac570150ce","ui_kits/storefront-marketplace-pro/ListingScreen.jsx":"7946bb368d27","ui_kits/storefront-marketplace-pro/ProductScreen.jsx":"03214903b3e7","ui_kits/storefront-marketplace-pro/StorefrontShell.jsx":"02fb676fd63d"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.ZamZamERPDesignSystem_a134f1 = window.ZamZamERPDesignSystem_a134f1 || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/admin/AdminBadge.jsx
try { (() => {
const colors = {
  success: {
    bg: 'var(--admin-success-bg)',
    fg: 'var(--admin-success)'
  },
  danger: {
    bg: 'var(--admin-danger-bg)',
    fg: 'var(--admin-danger)'
  },
  warning: {
    bg: 'var(--admin-warning-bg)',
    fg: 'var(--admin-warning)'
  },
  info: {
    bg: 'var(--admin-info-bg)',
    fg: 'var(--admin-info)'
  },
  gray: {
    bg: 'var(--admin-gray-bg)',
    fg: 'var(--admin-gray)'
  }
};
function AdminBadge({
  children,
  color = 'gray'
}) {
  const c = colors[color] || colors.gray;
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      background: c.bg,
      color: c.fg,
      fontFamily: 'var(--font-admin)',
      fontWeight: 600,
      fontSize: '12px',
      padding: '3px 9px',
      borderRadius: 'var(--radius-pill)',
      lineHeight: 1.4
    }
  }, children);
}
Object.assign(__ds_scope, { AdminBadge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/admin/AdminBadge.jsx", error: String((e && e.message) || e) }); }

// components/admin/AdminButton.jsx
try { (() => {
const sizes = {
  sm: {
    padding: '6px 12px',
    fontSize: 'var(--text-sm)'
  },
  md: {
    padding: '8px 16px',
    fontSize: 'var(--text-base)'
  },
  lg: {
    padding: '10px 22px',
    fontSize: 'var(--text-md)'
  }
};
const variants = {
  primary: {
    background: 'var(--admin-primary-500)',
    color: '#fff',
    border: '1px solid transparent'
  },
  secondary: {
    background: 'var(--admin-surface)',
    color: 'var(--admin-text-secondary)',
    border: '1px solid var(--admin-border-strong)'
  },
  danger: {
    background: 'var(--admin-danger)',
    color: '#fff',
    border: '1px solid transparent'
  },
  ghost: {
    background: 'transparent',
    color: 'var(--admin-text-secondary)',
    border: '1px solid transparent'
  }
};
function AdminButton({
  children,
  variant = 'primary',
  size = 'md',
  disabled = false,
  onClick
}) {
  const v = variants[variant] || variants.primary;
  const s = sizes[size] || sizes.md;
  return /*#__PURE__*/React.createElement("button", {
    onClick: onClick,
    disabled: disabled,
    style: {
      ...v,
      ...s,
      fontFamily: 'var(--font-admin)',
      fontWeight: 600,
      borderRadius: 'var(--radius-admin-md)',
      cursor: disabled ? 'not-allowed' : 'pointer',
      opacity: disabled ? 0.5 : 1,
      display: 'inline-flex',
      alignItems: 'center',
      gap: 6,
      transition: 'var(--transition-base)'
    }
  }, children);
}
Object.assign(__ds_scope, { AdminButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/admin/AdminButton.jsx", error: String((e && e.message) || e) }); }

// components/admin/Card.jsx
try { (() => {
function Card({
  label,
  value,
  delta,
  deltaDirection = 'up',
  icon
}) {
  const deltaColor = deltaDirection === 'up' ? 'var(--admin-success)' : 'var(--admin-danger)';
  return /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--admin-surface)',
      border: '1px solid var(--admin-border)',
      borderRadius: 'var(--radius-admin-lg)',
      padding: '18px 20px',
      fontFamily: 'var(--font-admin)',
      display: 'flex',
      flexDirection: 'column',
      gap: 8,
      boxShadow: 'var(--shadow-admin-card)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--admin-text-muted)',
      fontWeight: 500
    }
  }, label), icon ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 18
    }
  }, icon) : null), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-2xl)',
      fontWeight: 700,
      color: 'var(--admin-text)'
    }
  }, value), delta ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-xs)',
      fontWeight: 600,
      color: deltaColor
    }
  }, deltaDirection === 'up' ? '↑' : '↓', " ", delta) : null);
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/admin/Card.jsx", error: String((e && e.message) || e) }); }

// components/admin/Input.jsx
try { (() => {
const base = {
  fontFamily: 'var(--font-admin)',
  fontSize: 'var(--text-base)',
  color: 'var(--admin-text)',
  border: '1px solid var(--admin-border-strong)',
  borderRadius: 'var(--radius-admin-md)',
  padding: '8px 12px',
  background: 'var(--admin-surface)',
  outline: 'none',
  width: '100%',
  boxSizing: 'border-box'
};
function Input({
  label,
  placeholder,
  type = 'text',
  options
}) {
  return /*#__PURE__*/React.createElement("label", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 6,
      fontFamily: 'var(--font-admin)'
    }
  }, label ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-sm)',
      fontWeight: 600,
      color: 'var(--admin-text-secondary)'
    }
  }, label) : null, type === 'select' ? /*#__PURE__*/React.createElement("select", {
    style: base
  }, (options || []).map(o => /*#__PURE__*/React.createElement("option", {
    key: o
  }, o))) : /*#__PURE__*/React.createElement("input", {
    style: base,
    type: type,
    placeholder: placeholder
  }));
}
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/admin/Input.jsx", error: String((e && e.message) || e) }); }

// components/admin/SidebarNavItem.jsx
try { (() => {
function SidebarNavItem({
  label,
  icon,
  active = false
}) {
  return /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 10,
      padding: '9px 10px',
      borderRadius: 7,
      textDecoration: 'none',
      fontFamily: 'var(--font-admin)',
      fontSize: '13.5px',
      fontWeight: active ? 700 : 500,
      background: active ? 'var(--admin-primary-100)' : 'transparent',
      color: active ? 'var(--admin-primary-800)' : 'var(--admin-text-secondary)'
    }
  }, icon ? /*#__PURE__*/React.createElement("span", {
    style: {
      width: 18,
      textAlign: 'center'
    }
  }, icon) : null, label);
}
Object.assign(__ds_scope, { SidebarNavItem });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/admin/SidebarNavItem.jsx", error: String((e && e.message) || e) }); }

// components/storefront/PriceTag.jsx
try { (() => {
function PriceTag({
  price,
  oldPrice,
  size = 'md'
}) {
  const fontSize = size === 'lg' ? '30px' : size === 'sm' ? '16px' : '17px';
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'baseline',
      gap: 8,
      fontFamily: 'var(--font-storefront-display)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 800,
      fontSize,
      color: oldPrice ? 'var(--sf-deal)' : 'var(--sf-text)'
    }
  }, "\u09F3", price), oldPrice ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-storefront-body)',
      fontSize: '12.5px',
      color: 'var(--sf-text-faint)',
      textDecoration: 'line-through'
    }
  }, "\u09F3", oldPrice) : null);
}
Object.assign(__ds_scope, { PriceTag });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/storefront/PriceTag.jsx", error: String((e && e.message) || e) }); }

// components/storefront/ProductCard.jsx
try { (() => {
const Star = () => /*#__PURE__*/React.createElement("svg", {
  width: "13",
  height: "13",
  viewBox: "0 0 24 24",
  fill: "var(--sf-star)"
}, /*#__PURE__*/React.createElement("path", {
  d: "M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"
}));
function ProductCard({
  name,
  price,
  oldPrice,
  discount,
  reviews,
  inStock = true,
  ctaLabel = 'Add to cart'
}) {
  return /*#__PURE__*/React.createElement("div", {
    className: "sf-card-hover",
    style: {
      background: 'var(--sf-surface)',
      borderRadius: 'var(--radius-sf-lg)',
      padding: 14,
      position: 'relative',
      fontFamily: 'var(--font-storefront-body)',
      display: 'flex',
      flexDirection: 'column'
    }
  }, discount ? /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      top: 14,
      left: 14,
      background: 'var(--sf-deal)',
      color: '#fff',
      fontSize: '11.5px',
      fontWeight: 800,
      padding: '4px 8px',
      borderRadius: 5,
      zIndex: 1
    }
  }, "-", discount, "%") : null, /*#__PURE__*/React.createElement("div", {
    style: {
      width: '100%',
      aspectRatio: '1',
      borderRadius: 8,
      background: 'var(--sf-surface-muted)'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--sf-text-sm)',
      fontWeight: 600,
      marginTop: 10,
      lineHeight: 1.4,
      minHeight: 38
    }
  }, name), reviews != null ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 3,
      marginTop: 6
    }
  }, [1, 2, 3, 4, 5].map(i => /*#__PURE__*/React.createElement(Star, {
    key: i
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '11.5px',
      color: 'var(--sf-text-muted)',
      marginLeft: 4
    }
  }, "(", reviews, ")")) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 8,
      display: 'flex',
      alignItems: 'baseline',
      gap: 8,
      fontFamily: 'var(--font-storefront-display)',
      fontWeight: 800,
      fontSize: '16px',
      color: discount ? 'var(--sf-deal)' : 'var(--sf-text)'
    }
  }, "\u09F3", price, oldPrice ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-storefront-body)',
      fontWeight: 400,
      fontSize: '12.5px',
      color: 'var(--sf-text-faint)',
      textDecoration: 'line-through'
    }
  }, "\u09F3", oldPrice) : null), inStock ? /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 2,
      fontSize: '11.5px',
      color: 'var(--sf-success)',
      fontWeight: 700
    }
  }, "In stock \xB7 Ships in 2 days") : null, /*#__PURE__*/React.createElement("button", {
    className: "sf-btn-motion",
    style: {
      marginTop: 10,
      background: 'var(--sf-navy)',
      color: '#fff',
      border: 'none',
      padding: 9,
      borderRadius: 'var(--radius-sf-sm)',
      fontWeight: 700,
      fontSize: '12.5px',
      cursor: 'pointer'
    }
  }, ctaLabel));
}
Object.assign(__ds_scope, { ProductCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/storefront/ProductCard.jsx", error: String((e && e.message) || e) }); }

// components/storefront/SearchBar.jsx
try { (() => {
function SearchBar({
  placeholder = 'Search spare parts, machines...'
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      minWidth: 0,
      maxWidth: 480,
      fontFamily: 'var(--font-storefront-body)',
      background: '#fff',
      borderRadius: 'var(--radius-pill)',
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement("input", {
    type: "text",
    placeholder: placeholder,
    style: {
      flex: 1,
      minWidth: 0,
      border: 'none',
      padding: '0 18px',
      fontSize: 'var(--sf-text-base)',
      outline: 'none',
      background: 'transparent'
    }
  }), /*#__PURE__*/React.createElement("button", {
    className: "sf-btn-motion",
    style: {
      flexShrink: 0,
      background: 'var(--sf-accent)',
      border: 'none',
      padding: '0 20px',
      cursor: 'pointer',
      display: 'flex',
      alignItems: 'center',
      color: '#fff',
      fontWeight: 600,
      fontSize: 13
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2.4",
    style: {
      marginRight: 6
    }
  }, /*#__PURE__*/React.createElement("circle", {
    cx: "11",
    cy: "11",
    r: "7"
  }), /*#__PURE__*/React.createElement("path", {
    d: "m21 21-4.3-4.3",
    strokeLinecap: "round"
  })), "Search"));
}
Object.assign(__ds_scope, { SearchBar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/storefront/SearchBar.jsx", error: String((e && e.message) || e) }); }

// components/storefront/StorefrontBadge.jsx
try { (() => {
function StorefrontBadge({
  children,
  kind = 'inStock'
}) {
  const styles = {
    discount: {
      background: 'var(--sf-deal)',
      color: '#fff',
      fontWeight: 800,
      fontSize: '11.5px',
      padding: '4px 8px',
      borderRadius: 5
    },
    save: {
      background: 'var(--sf-deal-bg)',
      color: 'var(--sf-deal)',
      fontWeight: 700,
      fontSize: '12.5px',
      padding: '3px 8px',
      borderRadius: 5
    },
    inStock: {
      background: 'transparent',
      color: 'var(--sf-success)',
      fontWeight: 700,
      fontSize: '13px',
      padding: 0
    },
    tracking: {
      background: 'var(--sf-orange-tint)',
      color: '#B85200',
      fontWeight: 700,
      fontSize: '12px',
      padding: '5px 12px',
      borderRadius: 999
    }
  };
  const s = styles[kind] || styles.inStock;
  return /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-storefront-body)',
      display: 'inline-block',
      ...s
    }
  }, children);
}
Object.assign(__ds_scope, { StorefrontBadge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/storefront/StorefrontBadge.jsx", error: String((e && e.message) || e) }); }

// components/storefront/StorefrontButton.jsx
try { (() => {
const variants = {
  primary: {
    background: 'var(--sf-orange)',
    color: '#fff',
    border: 'none'
  },
  secondary: {
    background: 'var(--sf-navy)',
    color: '#fff',
    border: 'none'
  },
  outline: {
    background: 'none',
    color: 'var(--sf-navy)',
    border: '1.5px solid var(--sf-navy)'
  }
};
function StorefrontButton({
  children,
  variant = 'primary',
  fullWidth = false,
  onClick
}) {
  const v = variants[variant] || variants.primary;
  return /*#__PURE__*/React.createElement("button", {
    onClick: onClick,
    className: "sf-btn-motion",
    style: {
      ...v,
      width: fullWidth ? '100%' : 'auto',
      fontFamily: 'var(--font-storefront-body)',
      fontWeight: 700,
      fontSize: 'var(--sf-text-base)',
      padding: '13px 26px',
      borderRadius: 'var(--radius-sf-md)',
      cursor: 'pointer'
    }
  }, children);
}
Object.assign(__ds_scope, { StorefrontButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/storefront/StorefrontButton.jsx", error: String((e && e.message) || e) }); }

// ui_kits/admin-dashboard/AdminShell.jsx
try { (() => {
function AdminShell({
  page,
  onNavigate,
  children
}) {
  const {
    SidebarNavItem
  } = window.ZamZamERPDesignSystem_a134f1;
  const business = [{
    icon: 'P',
    label: 'Products',
    key: 'products'
  }, {
    icon: 'O',
    label: 'Orders',
    key: 'orders'
  }, {
    icon: 'C',
    label: 'Customers',
    key: 'customers'
  }, {
    icon: 'S',
    label: 'Suppliers & Purchases',
    key: 'suppliers'
  }, {
    icon: 'A',
    label: 'Accounts',
    key: 'accounts'
  }];
  const storefront = [{
    icon: '⚙️',
    label: 'Storefront Settings',
    key: 'settings'
  }, {
    icon: '🎨',
    label: 'Theme Gallery',
    key: 'themes'
  }, {
    icon: '📄',
    label: 'Storefront Pages',
    key: 'pages'
  }, {
    icon: '🎠',
    label: 'Product Carousels',
    key: 'carousels'
  }];
  const go = key => e => {
    e.preventDefault();
    onNavigate && onNavigate(key);
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-admin)',
      background: 'var(--admin-bg)',
      minHeight: '100vh',
      display: 'flex',
      color: 'var(--admin-text)'
    }
  }, /*#__PURE__*/React.createElement("aside", {
    style: {
      width: 260,
      flexShrink: 0,
      background: '#fff',
      borderRight: '1px solid var(--admin-border)',
      padding: '20px 14px',
      display: 'flex',
      flexDirection: 'column',
      gap: 2
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 10,
      padding: '6px 10px 22px'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 32,
      height: 32,
      borderRadius: 8,
      background: 'var(--admin-primary-500)',
      display: 'grid',
      placeItems: 'center',
      fontWeight: 800,
      color: '#fff',
      fontSize: 14
    }
  }, "Z"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      fontSize: 14.5
    }
  }, "ZamZam ERP")), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11,
      fontWeight: 700,
      color: 'var(--admin-text-faint)',
      textTransform: 'uppercase',
      letterSpacing: '0.05em',
      padding: '10px 10px 6px'
    }
  }, "Business"), business.map(b => /*#__PURE__*/React.createElement("a", {
    key: b.key,
    href: "#",
    onClick: go(b.key),
    style: navStyle(page === b.key)
  }, /*#__PURE__*/React.createElement("span", {
    style: iconStyle
  }, b.icon), /*#__PURE__*/React.createElement("span", null, b.label))), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 11,
      fontWeight: 700,
      color: 'var(--admin-text-faint)',
      textTransform: 'uppercase',
      letterSpacing: '0.05em',
      padding: '16px 10px 6px'
    }
  }, "Storefront"), storefront.map(b => /*#__PURE__*/React.createElement("a", {
    key: b.key,
    href: "#",
    onClick: go(b.key),
    style: navStyle(page === b.key)
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 18,
      textAlign: 'center'
    }
  }, b.icon), " ", b.label))), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, children));
}
function navStyle(active) {
  return {
    display: 'flex',
    alignItems: 'center',
    gap: 10,
    padding: '9px 10px',
    borderRadius: 7,
    textDecoration: 'none',
    fontSize: 13.5,
    fontWeight: active ? 700 : 500,
    background: active ? 'var(--admin-primary-100)' : 'transparent',
    color: active ? 'var(--admin-primary-800)' : 'var(--admin-text-secondary)'
  };
}
const iconStyle = {
  width: 20,
  height: 20,
  flexShrink: 0,
  borderRadius: 5,
  background: 'var(--admin-gray-bg)',
  color: 'var(--admin-text-muted)',
  fontSize: 10.5,
  fontWeight: 800,
  display: 'grid',
  placeItems: 'center'
};
function AdminTopbar({
  title,
  right
}) {
  return /*#__PURE__*/React.createElement("header", {
    style: {
      height: 64,
      background: '#fff',
      borderBottom: '1px solid var(--admin-border)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      padding: '0 28px'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      fontSize: 15
    }
  }, title), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 14
    }
  }, right, /*#__PURE__*/React.createElement("select", {
    style: {
      border: '1px solid var(--admin-border-strong)',
      borderRadius: 7,
      padding: '7px 12px',
      fontSize: 13,
      fontWeight: 600,
      color: 'var(--admin-text-secondary)'
    }
  }, /*#__PURE__*/React.createElement("option", null, "Garments Machinery Company"), /*#__PURE__*/React.createElement("option", null, "Tasneem Knitting Industry"), /*#__PURE__*/React.createElement("option", null, "Solar Items Company"), /*#__PURE__*/React.createElement("option", null, "Gadget Items Company")), /*#__PURE__*/React.createElement("div", {
    style: {
      width: 34,
      height: 34,
      borderRadius: '50%',
      background: 'var(--admin-primary-500)',
      display: 'grid',
      placeItems: 'center',
      color: '#fff',
      fontWeight: 700,
      fontSize: 13
    }
  }, "SA")));
}
window.AdminShell = AdminShell;
window.AdminTopbar = AdminTopbar;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/admin-dashboard/AdminShell.jsx", error: String((e && e.message) || e) }); }

// ui_kits/admin-dashboard/DashboardScreen.jsx
try { (() => {
function DashboardScreen({
  onNavigate
}) {
  const {
    Card,
    AdminBadge,
    AdminButton
  } = window.ZamZamERPDesignSystem_a134f1;
  const stats = [{
    label: 'Revenue this month',
    value: '৳24,58,000',
    delta: '12% vs last month',
    dir: 'up',
    icon: '📈'
  }, {
    label: 'Orders this month',
    value: '186',
    delta: '5% vs last month',
    dir: 'up',
    icon: '🧾'
  }, {
    label: 'Customer dues',
    value: '৳3,12,400',
    delta: '4% vs last month',
    dir: 'down',
    icon: '⏳'
  }, {
    label: 'Low stock items',
    value: '7',
    delta: '3 need reorder',
    dir: 'down',
    icon: '📦'
  }];
  const performers = [{
    name: 'Single Jersey Circular Machine 30"',
    sold: 12,
    revenue: '22,80,000'
  }, {
    name: 'Feeder Motor 3HP',
    sold: 41,
    revenue: '3,36,200'
  }, {
    name: 'Cylinder Needle Set (100pc)',
    sold: 96,
    revenue: '2,30,400'
  }];
  const lowStock = [{
    name: 'Sinker Ring - Standard',
    qty: 4,
    status: 'Low',
    color: 'danger'
  }, {
    name: 'Yarn Tension Unit',
    qty: 9,
    status: 'Low',
    color: 'warning'
  }, {
    name: 'Digital Speed Controller',
    qty: 22,
    status: 'OK',
    color: 'success'
  }];
  return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(AdminTopbar, {
    title: "Dashboard"
  }), /*#__PURE__*/React.createElement("main", {
    style: {
      padding: '28px 32px',
      maxWidth: 1180
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: 24
    }
  }, /*#__PURE__*/React.createElement("h1", {
    style: {
      fontSize: 20,
      fontWeight: 800,
      margin: '0 0 6px'
    }
  }, "Welcome back, Super Admin"), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 13.5,
      color: 'var(--admin-text-muted)',
      margin: 0
    }
  }, "Here's how Garments Machinery Company is doing today.")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(4,1fr)',
      gap: 16,
      marginBottom: 24
    }
  }, stats.map(s => /*#__PURE__*/React.createElement(Card, {
    key: s.label,
    label: s.label,
    value: s.value,
    delta: s.delta,
    deltaDirection: s.dir,
    icon: s.icon
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1.3fr 1fr',
      gap: 16
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: '#fff',
      border: '1px solid var(--admin-border)',
      borderRadius: 12,
      padding: 20
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      fontSize: 14,
      marginBottom: 14
    }
  }, "Sales & purchase trend"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'flex-end',
      gap: 8,
      height: 140
    }
  }, [40, 65, 50, 80, 60, 90, 70].map((h, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    style: {
      flex: 1,
      height: `${h}%`,
      background: 'var(--admin-primary-200)',
      borderRadius: 4
    }
  })))), /*#__PURE__*/React.createElement("div", {
    style: {
      background: '#fff',
      border: '1px solid var(--admin-border)',
      borderRadius: 12,
      padding: 20
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      fontSize: 14,
      marginBottom: 14
    }
  }, "Top performers"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 10
    }
  }, performers.map(p => /*#__PURE__*/React.createElement("div", {
    key: p.name,
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      fontSize: 13
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--admin-text-secondary)'
    }
  }, p.name), /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 700
    }
  }, "\u09F3", p.revenue)))))), /*#__PURE__*/React.createElement("div", {
    style: {
      background: '#fff',
      border: '1px solid var(--admin-border)',
      borderRadius: 12,
      padding: 20,
      marginTop: 16
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      marginBottom: 14
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      fontSize: 14
    }
  }, "Low stock products"), /*#__PURE__*/React.createElement(AdminButton, {
    variant: "secondary",
    size: "sm",
    onClick: () => onNavigate('products')
  }, "View all products")), /*#__PURE__*/React.createElement("table", {
    style: {
      width: '100%',
      borderCollapse: 'collapse',
      fontSize: 13
    }
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", {
    style: {
      textAlign: 'left',
      color: 'var(--admin-text-muted)'
    }
  }, /*#__PURE__*/React.createElement("th", {
    style: {
      padding: '6px 0',
      fontWeight: 600
    }
  }, "Product"), /*#__PURE__*/React.createElement("th", {
    style: {
      fontWeight: 600
    }
  }, "Qty on hand"), /*#__PURE__*/React.createElement("th", {
    style: {
      fontWeight: 600
    }
  }, "Status"))), /*#__PURE__*/React.createElement("tbody", null, lowStock.map(p => /*#__PURE__*/React.createElement("tr", {
    key: p.name,
    style: {
      borderTop: '1px solid var(--admin-border)'
    }
  }, /*#__PURE__*/React.createElement("td", {
    style: {
      padding: '10px 0'
    }
  }, p.name), /*#__PURE__*/React.createElement("td", null, p.qty), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(AdminBadge, {
    color: p.color
  }, p.status)))))))));
}
window.DashboardScreen = DashboardScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/admin-dashboard/DashboardScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/admin-dashboard/ProductsScreen.jsx
try { (() => {
function ProductsScreen() {
  const {
    AdminButton,
    AdminBadge,
    Input
  } = window.ZamZamERPDesignSystem_a134f1;
  const products = [{
    name: 'Single Jersey Circular Machine 30", 96F',
    sku: 'CKM-30-96F',
    stock: 3,
    price: '1,92,000',
    status: 'Active',
    color: 'success'
  }, {
    name: 'Feeder Motor 3HP',
    sku: 'MTR-3HP',
    stock: 22,
    price: '8,200',
    status: 'Active',
    color: 'success'
  }, {
    name: 'Cylinder Needle Set (100pc)',
    sku: 'NDL-100',
    stock: 4,
    price: '2,400',
    status: 'Low stock',
    color: 'danger'
  }, {
    name: 'Digital Speed Controller',
    sku: 'DSC-01',
    stock: 0,
    price: '15,600',
    status: 'Out of stock',
    color: 'gray'
  }, {
    name: 'Yarn Tension Unit',
    sku: 'YTU-02',
    stock: 9,
    price: '4,750',
    status: 'Active',
    color: 'success'
  }];
  return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(AdminTopbar, {
    title: "Products",
    right: /*#__PURE__*/React.createElement(AdminButton, {
      size: "sm"
    }, "+ Add product")
  }), /*#__PURE__*/React.createElement("main", {
    style: {
      padding: '28px 32px',
      maxWidth: 1180
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 12,
      marginBottom: 18,
      maxWidth: 420
    }
  }, /*#__PURE__*/React.createElement(Input, {
    placeholder: "Search products..."
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      background: '#fff',
      border: '1px solid var(--admin-border)',
      borderRadius: 12,
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement("table", {
    style: {
      width: '100%',
      borderCollapse: 'collapse',
      fontSize: 13.5
    }
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", {
    style: {
      textAlign: 'left',
      background: 'var(--admin-bg)',
      color: 'var(--admin-text-muted)'
    }
  }, /*#__PURE__*/React.createElement("th", {
    style: {
      padding: '12px 20px',
      fontWeight: 600
    }
  }, "Product"), /*#__PURE__*/React.createElement("th", {
    style: {
      fontWeight: 600
    }
  }, "SKU"), /*#__PURE__*/React.createElement("th", {
    style: {
      fontWeight: 600
    }
  }, "Stock"), /*#__PURE__*/React.createElement("th", {
    style: {
      fontWeight: 600
    }
  }, "Price"), /*#__PURE__*/React.createElement("th", {
    style: {
      fontWeight: 600
    }
  }, "Status"), /*#__PURE__*/React.createElement("th", {
    style: {
      fontWeight: 600
    }
  }))), /*#__PURE__*/React.createElement("tbody", null, products.map(p => /*#__PURE__*/React.createElement("tr", {
    key: p.sku,
    style: {
      borderTop: '1px solid var(--admin-border)'
    }
  }, /*#__PURE__*/React.createElement("td", {
    style: {
      padding: '14px 20px',
      fontWeight: 600
    }
  }, p.name), /*#__PURE__*/React.createElement("td", {
    style: {
      color: 'var(--admin-text-muted)'
    }
  }, p.sku), /*#__PURE__*/React.createElement("td", null, p.stock), /*#__PURE__*/React.createElement("td", null, "\u09F3", p.price), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(AdminBadge, {
    color: p.color
  }, p.status)), /*#__PURE__*/React.createElement("td", {
    style: {
      paddingRight: 20
    }
  }, /*#__PURE__*/React.createElement(AdminButton, {
    variant: "ghost",
    size: "sm"
  }, "Edit")))))))));
}
window.ProductsScreen = ProductsScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/admin-dashboard/ProductsScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/admin-dashboard/ThemeGalleryScreen.jsx
try { (() => {
function ThemeGalleryScreen() {
  const themes = [{
    name: 'Marketplace Pro',
    tagline: 'Dense, deal-driven retail',
    desc: 'Amazon/Walmart-style layout with flash deals, ratings, and a dense product grid. Best for high-SKU consumer catalogs.',
    previewBg: '#0F2A43',
    accent: '#FF6A00',
    surface: 'rgba(255,255,255,0.08)',
    active: true
  }, {
    name: 'Industrial B2B',
    tagline: 'Corporate, spec-sheet led',
    desc: 'Steel-navy palette with certifications, RFQ flow, and spec-driven product cards. Built for machinery and wholesale exporters.',
    previewBg: '#0B2036',
    accent: '#F2A93B',
    surface: 'rgba(255,255,255,0.08)',
    active: false
  }, {
    name: 'Editorial Premium',
    tagline: 'Minimal DTC storytelling',
    desc: 'Generous whitespace, large type, and full-bleed imagery for brand-led direct-to-consumer catalogs.',
    previewBg: '#FAFAF8',
    accent: '#111111',
    surface: '#EDEBE4',
    active: false
  }, {
    name: 'Fresh Value',
    tagline: 'Friendly, value-focused',
    desc: 'Bright, approachable palette with everyday-low-price messaging for family and value retail.',
    previewBg: '#0071CE',
    accent: '#FFC220',
    surface: 'rgba(255,255,255,0.15)',
    active: false
  }, {
    name: 'Bold Studio',
    tagline: 'High-contrast, statement type',
    desc: 'Black-and-lime editorial layout with oversized type and asymmetric grids. Built for tech and lifestyle gadget brands.',
    previewBg: '#0A0A0A',
    accent: '#C6FF3D',
    surface: '#1E1E1E',
    active: false
  }, {
    name: 'Corporate Classic',
    tagline: 'Institutional, trust-first',
    desc: 'Serif headlines, formal grid, and a numbered-services layout for holding companies and traditional trading houses.',
    previewBg: '#FBFAF7',
    accent: '#7A1F2B',
    surface: '#EDEAE0',
    active: false
  }];
  return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(AdminTopbar, {
    title: "Theme Gallery"
  }), /*#__PURE__*/React.createElement("main", {
    style: {
      padding: '28px 32px',
      maxWidth: 1180
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: 26
    }
  }, /*#__PURE__*/React.createElement("h1", {
    style: {
      fontSize: 20,
      fontWeight: 800,
      margin: '0 0 6px'
    }
  }, "Choose a storefront theme"), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 13.5,
      color: 'var(--admin-text-muted)',
      margin: 0,
      maxWidth: 640,
      lineHeight: 1.6
    }
  }, "Pick a visual theme per company. Each theme controls layout, typography, and color \u2014 the underlying products, pages, and settings stay the same.")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(3,1fr)',
      gap: 20
    }
  }, themes.map(t => /*#__PURE__*/React.createElement("div", {
    key: t.name,
    style: {
      background: '#fff',
      border: '1px solid var(--admin-border)',
      borderRadius: 12,
      overflow: 'hidden',
      display: 'flex',
      flexDirection: 'column'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      height: 170,
      position: 'relative',
      background: t.previewBg,
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      inset: 0,
      padding: 16,
      display: 'flex',
      flexDirection: 'column',
      gap: 8
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      height: 10,
      width: '38%',
      borderRadius: 3,
      background: t.accent
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      height: 26,
      width: '100%',
      borderRadius: 5,
      background: t.surface
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8,
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      borderRadius: 5,
      background: t.surface
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      borderRadius: 5,
      background: t.surface
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      borderRadius: 5,
      background: t.surface
    }
  }))), t.active && /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      top: 12,
      right: 12,
      background: '#16A34A',
      color: '#fff',
      fontSize: 11,
      fontWeight: 700,
      padding: '4px 10px',
      borderRadius: 999
    }
  }, "Active")), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '18px 20px',
      display: 'flex',
      flexDirection: 'column',
      gap: 10,
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      fontSize: 15
    }
  }, t.name), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12.5,
      color: 'var(--admin-text-muted)',
      marginTop: 2
    }
  }, t.tagline)), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 12.5,
      color: 'var(--admin-text-muted)',
      lineHeight: 1.6,
      margin: 0,
      flex: 1
    }
  }, t.desc), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8,
      marginTop: 6
    }
  }, /*#__PURE__*/React.createElement("button", {
    style: {
      flex: 1,
      background: 'var(--admin-bg)',
      border: '1px solid var(--admin-border-strong)',
      color: 'var(--admin-text-secondary)',
      padding: 9,
      borderRadius: 7,
      fontWeight: 600,
      fontSize: 12.5,
      cursor: 'pointer'
    }
  }, "Preview"), /*#__PURE__*/React.createElement("button", {
    style: {
      flex: 1,
      background: t.active ? 'var(--admin-border)' : '#18181B',
      color: t.active ? 'var(--admin-text-muted)' : '#fff',
      border: 'none',
      padding: 9,
      borderRadius: 7,
      fontWeight: 700,
      fontSize: 12.5,
      cursor: 'pointer'
    }
  }, t.active ? 'Active' : 'Apply theme'))))))));
}
window.ThemeGalleryScreen = ThemeGalleryScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/admin-dashboard/ThemeGalleryScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/storefront-marketplace-pro/CartScreen.jsx
try { (() => {
function CartScreen({
  onNavigate,
  cartCount
}) {
  const raw = [{
    name: 'Single Jersey Circular Machine 30", 96F',
    qty: 1,
    unit: 192000
  }, {
    name: 'Feeder Motor 3HP',
    qty: 2,
    unit: 8200
  }, {
    name: 'Cylinder Needle Set (100pc)',
    qty: 5,
    unit: 2400
  }];
  const items = raw.map(i => ({
    ...i,
    total: (i.qty * i.unit).toLocaleString()
  }));
  return /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-storefront-body)',
      background: 'var(--sf-bg)',
      color: 'var(--sf-text)',
      minHeight: '100vh'
    }
  }, /*#__PURE__*/React.createElement(StorefrontHeader, {
    variant: "cart",
    cartCount: cartCount,
    onNavigate: onNavigate
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 1100,
      margin: '36px auto 0',
      padding: '0 var(--sf-container-padding) 60px',
      display: 'grid',
      gridTemplateColumns: '1fr 340px',
      gap: 26
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "sf-reveal sf-reveal-1",
    style: {
      background: '#fff',
      borderRadius: 'var(--radius-sf-lg)',
      padding: 22
    }
  }, /*#__PURE__*/React.createElement("h1", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontSize: 20,
      fontWeight: 800,
      margin: '0 0 18px'
    }
  }, "Your cart (", items.length, " items)"), items.map(item => /*#__PURE__*/React.createElement("div", {
    key: item.name,
    style: {
      display: 'grid',
      gridTemplateColumns: '80px 1fr auto auto',
      gap: 16,
      alignItems: 'center',
      padding: '18px 0',
      borderTop: '1px solid var(--sf-border-soft)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 80,
      height: 80,
      borderRadius: 8,
      background: 'var(--sf-surface-muted)'
    }
  }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 14,
      fontWeight: 600,
      lineHeight: 1.4
    }
  }, item.name), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--sf-text-xs)',
      color: 'var(--sf-success)',
      fontWeight: 700,
      marginTop: 4
    }
  }, "In stock"), /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      fontSize: 'var(--sf-text-xs)',
      color: 'var(--sf-deal)',
      textDecoration: 'none',
      marginTop: 6,
      display: 'inline-block'
    }
  }, "Remove")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      border: '1px solid var(--sf-border)',
      borderRadius: 8
    }
  }, /*#__PURE__*/React.createElement("button", {
    className: "sf-btn-motion",
    style: {
      border: 'none',
      background: 'none',
      width: 30,
      height: 30,
      cursor: 'pointer'
    }
  }, "\u2212"), /*#__PURE__*/React.createElement("span", {
    style: {
      width: 30,
      textAlign: 'center',
      fontSize: 'var(--sf-text-sm)'
    }
  }, item.qty), /*#__PURE__*/React.createElement("button", {
    className: "sf-btn-motion",
    style: {
      border: 'none',
      background: 'none',
      width: 30,
      height: 30,
      cursor: 'pointer'
    }
  }, "+")), /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontWeight: 800,
      fontSize: 16,
      textAlign: 'right',
      minWidth: 90
    }
  }, "\u09F3", item.total)))), /*#__PURE__*/React.createElement("div", {
    className: "sf-reveal sf-reveal-2",
    style: {
      background: '#fff',
      borderRadius: 'var(--radius-sf-lg)',
      padding: 22
    }
  }, /*#__PURE__*/React.createElement("h2", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontSize: 16,
      fontWeight: 800,
      margin: '0 0 16px'
    }
  }, "Order summary"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      fontSize: 'var(--sf-text-sm)',
      color: 'var(--sf-text-secondary)',
      marginBottom: 10
    }
  }, /*#__PURE__*/React.createElement("span", null, "Subtotal"), /*#__PURE__*/React.createElement("span", null, "\u09F3256,400")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      fontSize: 'var(--sf-text-sm)',
      color: 'var(--sf-text-secondary)',
      marginBottom: 10
    }
  }, /*#__PURE__*/React.createElement("span", null, "VAT (15%)"), /*#__PURE__*/React.createElement("span", null, "\u09F338,460")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      fontSize: 'var(--sf-text-sm)',
      color: 'var(--sf-success)',
      fontWeight: 700,
      marginBottom: 16
    }
  }, /*#__PURE__*/React.createElement("span", null, "Delivery"), /*#__PURE__*/React.createElement("span", null, "Free")), /*#__PURE__*/React.createElement("div", {
    style: {
      borderTop: '1px solid var(--sf-border-soft)',
      paddingTop: 16,
      display: 'flex',
      justifyContent: 'space-between',
      fontFamily: 'var(--font-storefront-display)',
      fontWeight: 800,
      fontSize: 18,
      marginBottom: 20
    }
  }, /*#__PURE__*/React.createElement("span", null, "Total"), /*#__PURE__*/React.createElement("span", null, "\u09F3294,860")), /*#__PURE__*/React.createElement("button", {
    className: "sf-btn-motion",
    style: {
      display: 'block',
      width: '100%',
      textAlign: 'center',
      background: 'var(--sf-orange)',
      color: '#fff',
      padding: 13,
      borderRadius: 8,
      fontWeight: 700,
      fontSize: 'var(--sf-text-base)',
      border: 'none',
      cursor: 'pointer'
    }
  }, "Proceed to checkout"))), /*#__PURE__*/React.createElement(StorefrontFooter, null));
}
window.CartScreen = CartScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/storefront-marketplace-pro/CartScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/storefront-marketplace-pro/HomeScreen.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function HomeScreen({
  onNavigate,
  cartCount
}) {
  const {
    ProductCard
  } = window.ZamZamERPDesignSystem_a134f1;
  const trustItems = [{
    icon: '🚚',
    title: 'Nationwide delivery',
    sub: 'Dispatch in 24-48h'
  }, {
    icon: '🔒',
    title: 'Secure payment',
    sub: 'bKash, cards, COD'
  }, {
    icon: '🛠️',
    title: 'Installation support',
    sub: 'Certified technicians'
  }, {
    icon: '↩️',
    title: '7-day returns',
    sub: 'On eligible items'
  }];
  const categories = ['Circular Knitting', 'Flat Knitting', 'Needles & Sinkers', 'Motors', 'Electronics', 'Accessories'];
  const dealNames = ['Cylinder Needle Set (100pc)', 'Sinker Ring - Standard', 'Feeder Motor 3HP', 'Yarn Tension Unit', 'Digital Speed Controller'];
  const dealProducts = dealNames.map((name, i) => ({
    name,
    discount: [18, 12, 25, 15, 20][i],
    reviews: 120 + i * 37,
    price: (2400 + i * 850).toLocaleString(),
    oldPrice: (3200 + i * 1100).toLocaleString()
  }));
  const featNames = ['Single Jersey Circular Machine 30", 96F', 'Rib Knitting Machine 26"', 'Auto Stripper Circular Machine', 'Interlock Knitting Machine', 'Fleece Knitting Machine 34"'];
  const featuredProducts = featNames.map((name, i) => ({
    name,
    reviews: 40 + i * 11,
    price: (185000 + i * 23000).toLocaleString()
  }));
  return /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-storefront-body)',
      background: 'var(--sf-bg)',
      color: 'var(--sf-text)',
      minHeight: '100vh'
    }
  }, /*#__PURE__*/React.createElement(StorefrontHeader, {
    variant: "full",
    cartCount: cartCount,
    onNavigate: onNavigate
  }), /*#__PURE__*/React.createElement("section", {
    className: "sf-reveal sf-reveal-1",
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '24px auto 0',
      padding: '0 var(--sf-container-padding)',
      display: 'grid',
      gridTemplateColumns: '2fr 1fr',
      gap: 20
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      borderRadius: 'var(--radius-sf-2xl)',
      overflow: 'hidden',
      background: 'var(--sf-navy)',
      minHeight: 340
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      inset: 0,
      background: 'linear-gradient(135deg, var(--sf-navy-gradient-start), var(--sf-navy-gradient-end))'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      inset: 0,
      background: 'linear-gradient(90deg, rgba(7,20,33,0.88) 0%, rgba(7,20,33,0.45) 55%, rgba(7,20,33,0.05) 100%)'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      padding: 48,
      maxWidth: 480
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'inline-block',
      background: 'var(--sf-orange)',
      color: '#fff',
      fontSize: 12,
      fontWeight: 800,
      padding: '5px 12px',
      borderRadius: 999,
      letterSpacing: '0.03em',
      whiteSpace: 'nowrap'
    }
  }, "SEASON CLEARANCE"), /*#__PURE__*/React.createElement("h1", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontSize: 'var(--sf-text-3xl)',
      fontWeight: 800,
      color: '#fff',
      margin: '18px 0 12px',
      lineHeight: 1.15
    }
  }, "Up to 22% off circular knitting machines"), /*#__PURE__*/React.createElement("p", {
    style: {
      color: '#C7D3DE',
      fontSize: 15,
      lineHeight: 1.6,
      margin: '0 0 24px'
    }
  }, "Genuine parts, factory-direct pricing, and nationwide installation support."), /*#__PURE__*/React.createElement("button", {
    style: {
      background: 'var(--sf-orange)',
      color: '#fff',
      border: 'none',
      padding: '13px 26px',
      borderRadius: 'var(--radius-sf-md)',
      fontWeight: 700,
      fontSize: 'var(--sf-text-base)',
      cursor: 'pointer'
    }
  }, "Shop the sale"))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 20
    }
  }, ['Spare parts, in stock', 'Book installation & service'].map(t => /*#__PURE__*/React.createElement("div", {
    key: t,
    className: "sf-card-hover",
    style: {
      background: '#fff',
      borderRadius: 'var(--radius-sf-xl)',
      padding: 20,
      flex: 1,
      display: 'flex',
      flexDirection: 'column'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontWeight: 700,
      fontSize: 15,
      marginBottom: 12
    }
  }, t), /*#__PURE__*/React.createElement("div", {
    style: {
      width: '100%',
      flex: 1,
      borderRadius: 10,
      minHeight: 80,
      background: 'var(--sf-surface-muted)'
    }
  }), /*#__PURE__*/React.createElement("button", {
    style: {
      marginTop: 12,
      background: 'none',
      border: '1.5px solid var(--sf-navy)',
      color: 'var(--sf-navy)',
      padding: 9,
      borderRadius: 'var(--radius-sf-md)',
      fontWeight: 700,
      fontSize: 13,
      cursor: 'pointer'
    }
  }, t.startsWith('Spare') ? 'Browse parts' : 'Request a visit'))))), /*#__PURE__*/React.createElement("section", {
    className: "sf-reveal sf-reveal-2",
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '28px auto 0',
      padding: '0 var(--sf-container-padding)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: '#fff',
      borderRadius: 'var(--radius-sf-xl)',
      padding: '22px 30px',
      display: 'grid',
      gridTemplateColumns: 'repeat(4,1fr)',
      gap: 20
    }
  }, trustItems.map(it => /*#__PURE__*/React.createElement("div", {
    key: it.title,
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 12
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 42,
      height: 42,
      borderRadius: 'var(--radius-sf-md)',
      background: 'var(--sf-accent-tint)',
      display: 'grid',
      placeItems: 'center',
      flexShrink: 0
    }
  }, it.icon), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      fontSize: 14
    }
  }, it.title), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--sf-text-xs)',
      color: 'var(--sf-text-muted)'
    }
  }, it.sub)))))), /*#__PURE__*/React.createElement("section", {
    className: "sf-reveal sf-reveal-3",
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '36px auto 0',
      padding: '0 var(--sf-container-padding)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      marginBottom: 16
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 12
    }
  }, /*#__PURE__*/React.createElement("h2", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontSize: 'var(--sf-text-xl)',
      fontWeight: 800,
      margin: 0
    }
  }, "Today's deals"), /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--sf-navy)',
      color: '#fff',
      fontSize: 12,
      fontWeight: 700,
      padding: '5px 10px',
      borderRadius: 6
    }
  }, "Ends in 06:42:18")), /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      onNavigate('listing');
    },
    style: {
      color: 'var(--sf-link)',
      fontWeight: 700,
      fontSize: 'var(--sf-text-sm)',
      textDecoration: 'none'
    }
  }, "See all deals \u2192")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(5,1fr)',
      gap: 16
    }
  }, dealProducts.map(p => /*#__PURE__*/React.createElement("a", {
    key: p.name,
    href: "#",
    onClick: e => {
      e.preventDefault();
      onNavigate('product');
    },
    style: {
      textDecoration: 'none',
      color: 'inherit'
    }
  }, /*#__PURE__*/React.createElement(ProductCard, p))))), /*#__PURE__*/React.createElement("section", {
    className: "sf-reveal sf-reveal-4",
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '44px auto 0',
      padding: '0 var(--sf-container-padding)'
    }
  }, /*#__PURE__*/React.createElement("h2", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontSize: 'var(--sf-text-xl)',
      fontWeight: 800,
      margin: '0 0 16px'
    }
  }, "Shop by category"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(6,1fr)',
      gap: 16
    }
  }, categories.map(c => /*#__PURE__*/React.createElement("a", {
    key: c,
    href: "#",
    onClick: e => {
      e.preventDefault();
      onNavigate('listing');
    },
    className: "sf-card-hover",
    style: {
      textDecoration: 'none',
      color: 'var(--sf-text)',
      background: '#fff',
      borderRadius: 'var(--radius-sf-lg)',
      padding: 18,
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      gap: 10,
      textAlign: 'center'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 64,
      height: 64,
      borderRadius: '50%',
      background: 'var(--sf-surface-muted)'
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 13,
      fontWeight: 700
    }
  }, c))))), /*#__PURE__*/React.createElement("section", {
    className: "sf-reveal sf-reveal-5",
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '44px auto 0',
      padding: '0 var(--sf-container-padding)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      marginBottom: 16
    }
  }, /*#__PURE__*/React.createElement("h2", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontSize: 'var(--sf-text-xl)',
      fontWeight: 800,
      margin: 0
    }
  }, "Recommended for your business"), /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      onNavigate('listing');
    },
    style: {
      color: 'var(--sf-link)',
      fontWeight: 700,
      fontSize: 'var(--sf-text-sm)',
      textDecoration: 'none'
    }
  }, "View all products \u2192")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(5,1fr)',
      gap: 16
    }
  }, featuredProducts.map(p => /*#__PURE__*/React.createElement("a", {
    key: p.name,
    href: "#",
    onClick: e => {
      e.preventDefault();
      onNavigate('product');
    },
    style: {
      textDecoration: 'none',
      color: 'inherit'
    }
  }, /*#__PURE__*/React.createElement(ProductCard, _extends({}, p, {
    ctaLabel: "Add to cart"
  })))))), /*#__PURE__*/React.createElement("section", {
    className: "sf-reveal sf-reveal-6",
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '48px auto 0',
      padding: '0 var(--sf-container-padding)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--sf-navy)',
      borderRadius: 'var(--radius-sf-2xl)',
      padding: '28px 34px',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      flexWrap: 'wrap',
      gap: 20
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      color: '#fff'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontWeight: 800,
      fontSize: 18
    }
  }, "Trusted by 1,200+ garment factories nationwide"), /*#__PURE__*/React.createElement("div", {
    style: {
      color: '#93A7B8',
      fontSize: 'var(--sf-text-sm)',
      marginTop: 4
    }
  }, "Genuine parts \xB7 Certified technicians \xB7 48-hour dispatch")), /*#__PURE__*/React.createElement("button", {
    style: {
      background: 'var(--sf-orange)',
      color: '#fff',
      border: 'none',
      padding: '12px 24px',
      borderRadius: 'var(--radius-sf-md)',
      fontWeight: 700,
      fontSize: 14,
      cursor: 'pointer',
      whiteSpace: 'nowrap'
    }
  }, "Open a business account"))), /*#__PURE__*/React.createElement(StorefrontFooter, null));
}
window.HomeScreen = HomeScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/storefront-marketplace-pro/HomeScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/storefront-marketplace-pro/ListingScreen.jsx
try { (() => {
function ListingScreen({
  onNavigate,
  cartCount
}) {
  const {
    ProductCard
  } = window.ZamZamERPDesignSystem_a134f1;
  const names = ['Single Jersey Circular Machine 30", 96F', 'Rib Knitting Machine 26"', 'Auto Stripper Circular Machine', 'Interlock Knitting Machine', 'Fleece Knitting Machine 34"', 'Terry Knitting Machine', 'High Pile Fleece Machine', 'Computerized Jacquard Machine', 'Mini Circular Sample Machine'];
  const products = names.map((name, i) => ({
    name,
    reviews: 40 + i * 11,
    price: (185000 + i * 23000).toLocaleString()
  }));
  const filterGroups = [{
    title: 'Category',
    items: ['Circular Knitting (24)', 'Flat Knitting (11)', 'Spare Parts (58)', 'Motors (9)']
  }, {
    title: 'Price range',
    items: ['Under ৳10,000', '৳10,000 – ৳100,000', 'Over ৳100,000']
  }];
  return /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-storefront-body)',
      background: 'var(--sf-bg)',
      color: 'var(--sf-text)',
      minHeight: '100vh'
    }
  }, /*#__PURE__*/React.createElement(StorefrontHeader, {
    variant: "full",
    cartCount: cartCount,
    onNavigate: onNavigate
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '0 auto',
      padding: '16px var(--sf-container-padding) 0',
      fontSize: 13,
      color: 'var(--sf-text-muted)'
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      onNavigate('home');
    },
    style: {
      color: 'var(--sf-text-muted)',
      textDecoration: 'none'
    }
  }, "Home"), " / ", /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--sf-text)',
      fontWeight: 600
    }
  }, "Circular Knitting Machines")), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '20px auto 60px',
      padding: '0 var(--sf-container-padding)',
      display: 'grid',
      gridTemplateColumns: '260px 1fr',
      gap: 24
    }
  }, /*#__PURE__*/React.createElement("aside", {
    className: "sf-reveal sf-reveal-1",
    style: {
      background: '#fff',
      borderRadius: 'var(--radius-sf-lg)',
      padding: 20,
      alignSelf: 'start',
      display: 'flex',
      flexDirection: 'column',
      gap: 20
    }
  }, filterGroups.map(g => /*#__PURE__*/React.createElement("div", {
    key: g.title
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      fontSize: 14,
      marginBottom: 10
    }
  }, g.title), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 8,
      fontSize: 13,
      color: 'var(--sf-text-secondary)'
    }
  }, g.items.map(it => /*#__PURE__*/React.createElement("label", {
    key: it,
    style: {
      display: 'flex',
      gap: 8,
      alignItems: 'center'
    }
  }, /*#__PURE__*/React.createElement("input", {
    type: "checkbox"
  }), " ", it)))))), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      marginBottom: 16,
      fontSize: 13.5,
      color: 'var(--sf-text-muted)'
    },
    className: "sf-reveal sf-reveal-2"
  }, /*#__PURE__*/React.createElement("span", null, "Showing 1-9 of 48 results"), /*#__PURE__*/React.createElement("select", {
    style: {
      border: '1px solid var(--sf-border)',
      borderRadius: 7,
      padding: '7px 10px',
      fontSize: 13
    }
  }, /*#__PURE__*/React.createElement("option", null, "Sort: Featured"), /*#__PURE__*/React.createElement("option", null, "Price: low to high"), /*#__PURE__*/React.createElement("option", null, "Price: high to low"))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(3,1fr)',
      gap: 16
    }
  }, products.map(p => /*#__PURE__*/React.createElement("a", {
    key: p.name,
    href: "#",
    onClick: e => {
      e.preventDefault();
      onNavigate('product');
    },
    style: {
      textDecoration: 'none',
      color: 'inherit'
    }
  }, /*#__PURE__*/React.createElement(ProductCard, p)))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'center',
      gap: 8,
      marginTop: 32
    }
  }, [1, 2, 3, 4].map(n => /*#__PURE__*/React.createElement("div", {
    key: n,
    className: "sf-btn-motion",
    style: {
      width: 36,
      height: 36,
      borderRadius: 'var(--radius-sf-md)',
      display: 'grid',
      placeItems: 'center',
      fontSize: 13,
      fontWeight: 700,
      cursor: 'pointer',
      background: n === 1 ? 'var(--sf-navy)' : '#fff',
      color: n === 1 ? '#fff' : 'var(--sf-text)'
    }
  }, n))))), /*#__PURE__*/React.createElement(StorefrontFooter, null));
}
window.ListingScreen = ListingScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/storefront-marketplace-pro/ListingScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/storefront-marketplace-pro/ProductScreen.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function ProductScreen({
  onNavigate,
  cartCount,
  onAddToCart
}) {
  const {
    ProductCard
  } = window.ZamZamERPDesignSystem_a134f1;
  const [justAdded, setJustAdded] = React.useState(false);
  const handleAdd = () => {
    setJustAdded(true);
    onAddToCart && onAddToCart();
    setTimeout(() => onNavigate('cart'), 320);
  };
  const specs = [{
    k: 'Cylinder diameter',
    v: '30 inch'
  }, {
    k: 'Feeders',
    v: '96F'
  }, {
    k: 'Max speed',
    v: '32 RPM'
  }, {
    k: 'Power',
    v: '7.5 HP'
  }, {
    k: 'Gauge',
    v: '24GG'
  }, {
    k: 'Weight',
    v: '1,650 kg'
  }];
  const perks = [{
    icon: '🚚',
    text: 'Free delivery within Dhaka; nationwide freight quoted at checkout'
  }, {
    icon: '🛠️',
    text: 'Installation and operator training available on request'
  }, {
    icon: '↩️',
    text: '7-day inspection period, 12-month parts warranty'
  }];
  const relatedNames = ['Rib Knitting Machine 26"', 'Auto Stripper Circular Machine', 'Feeder Motor 3HP', 'Digital Speed Controller'];
  const related = relatedNames.map((name, i) => ({
    name,
    price: (24000 + i * 15000).toLocaleString()
  }));
  return /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-storefront-body)',
      background: 'var(--sf-bg)',
      color: 'var(--sf-text)',
      minHeight: '100vh'
    }
  }, /*#__PURE__*/React.createElement(StorefrontHeader, {
    variant: "product",
    cartCount: cartCount,
    onNavigate: onNavigate
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '0 auto',
      padding: '16px var(--sf-container-padding) 0',
      fontSize: 13,
      color: 'var(--sf-text-muted)'
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      onNavigate('home');
    },
    style: {
      color: 'var(--sf-text-muted)',
      textDecoration: 'none'
    }
  }, "Home"), " / ", /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      onNavigate('listing');
    },
    style: {
      color: 'var(--sf-text-muted)',
      textDecoration: 'none'
    }
  }, "Circular Knitting Machines"), " / ", /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--sf-text)',
      fontWeight: 600
    }
  }, "Single Jersey Circular Machine 30\", 96F")), /*#__PURE__*/React.createElement("section", {
    className: "sf-reveal sf-reveal-1",
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '20px auto 0',
      padding: '0 var(--sf-container-padding)',
      display: 'grid',
      gridTemplateColumns: '0.9fr 1fr 0.7fr',
      gap: 28
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 12
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 10
    }
  }, [0, 1, 2, 3].map(i => /*#__PURE__*/React.createElement("div", {
    key: i,
    className: "sf-card-hover",
    style: {
      width: 64,
      height: 64,
      borderRadius: 8,
      background: 'var(--sf-surface-muted)',
      border: `2px solid ${i === 0 ? 'var(--sf-orange)' : 'transparent'}`
    }
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      background: '#fff',
      borderRadius: 'var(--radius-sf-lg)',
      aspectRatio: '1'
    }
  })), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--sf-text-xs)',
      color: 'var(--sf-link)',
      fontWeight: 700,
      marginBottom: 8
    }
  }, "Circular Knitting Machines"), /*#__PURE__*/React.createElement("h1", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontSize: 26,
      fontWeight: 800,
      margin: '0 0 10px',
      lineHeight: 1.3
    }
  }, "Single Jersey Circular Machine 30\", 96 Feeders"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8,
      marginBottom: 16
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 2
    }
  }, [1, 2, 3, 4, 5].map(i => /*#__PURE__*/React.createElement("svg", {
    key: i,
    width: "15",
    height: "15",
    viewBox: "0 0 24 24",
    fill: "var(--sf-star)"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"
  })))), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 13,
      color: 'var(--sf-link)',
      fontWeight: 600
    }
  }, "128 ratings"), /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--sf-border)'
    }
  }, "|"), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 13,
      color: 'var(--sf-success)',
      fontWeight: 700
    }
  }, "In stock")), /*#__PURE__*/React.createElement("div", {
    style: {
      borderTop: '1px solid var(--sf-border-soft)',
      borderBottom: '1px solid var(--sf-border-soft)',
      padding: '18px 0',
      marginBottom: 18
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'baseline',
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontWeight: 800,
      fontSize: 30,
      color: 'var(--sf-deal)'
    }
  }, "\u09F3192,000"), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 15,
      color: 'var(--sf-text-faint)',
      textDecoration: 'line-through'
    }
  }, "\u09F3225,000"), /*#__PURE__*/React.createElement("span", {
    style: {
      background: 'var(--sf-deal-bg)',
      color: 'var(--sf-deal)',
      fontWeight: 700,
      fontSize: 'var(--sf-text-xs)',
      padding: '3px 8px',
      borderRadius: 5
    }
  }, "Save 15%")), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--sf-text-xs)',
      color: 'var(--sf-text-secondary)',
      marginTop: 6
    }
  }, "Price includes VAT. Bulk pricing available for 3+ units.")), /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: 8,
      fontWeight: 700,
      fontSize: 14
    }
  }, "Key specifications"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1fr 1fr',
      gap: 10,
      fontSize: 'var(--sf-text-sm)',
      color: 'var(--sf-text-secondary)'
    }
  }, specs.map(s => /*#__PURE__*/React.createElement("div", {
    key: s.k
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--sf-text-faint)'
    }
  }, s.k, ":"), " ", s.v))), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 22
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      fontSize: 14,
      marginBottom: 8
    }
  }, "About this machine"), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 'var(--sf-text-sm)',
      lineHeight: 1.8,
      color: 'var(--sf-text-secondary)',
      margin: 0
    }
  }, "Precision-engineered circular knitting machine built for continuous production of lightweight to mid-weight single jersey fabrics. Includes variable-speed drive, digital tension control, and a 12-month parts warranty."))), /*#__PURE__*/React.createElement("div", {
    style: {
      alignSelf: 'start'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: '#fff',
      borderRadius: 'var(--radius-sf-lg)',
      padding: 22,
      position: 'sticky',
      top: 20
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontWeight: 800,
      fontSize: 24,
      marginBottom: 4
    }
  }, "\u09F3192,000"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13,
      color: 'var(--sf-success)',
      fontWeight: 700,
      marginBottom: 18
    }
  }, "In stock \xB7 Ships in 2-3 business days"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 10,
      marginBottom: 16
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      border: '1px solid var(--sf-border)',
      borderRadius: 8
    }
  }, /*#__PURE__*/React.createElement("button", {
    className: "sf-btn-motion",
    style: {
      border: 'none',
      background: 'none',
      width: 34,
      height: 34,
      fontSize: 16,
      cursor: 'pointer'
    }
  }, "\u2212"), /*#__PURE__*/React.createElement("span", {
    style: {
      width: 36,
      textAlign: 'center',
      fontSize: 14
    }
  }, "1"), /*#__PURE__*/React.createElement("button", {
    className: "sf-btn-motion",
    style: {
      border: 'none',
      background: 'none',
      width: 34,
      height: 34,
      fontSize: 16,
      cursor: 'pointer'
    }
  }, "+")), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--sf-text-xs)',
      color: 'var(--sf-text-faint)'
    }
  }, "3 units left")), /*#__PURE__*/React.createElement("button", {
    onClick: handleAdd,
    className: `sf-btn-motion${justAdded ? ' sf-pulse' : ''}`,
    style: {
      width: '100%',
      background: 'var(--sf-orange)',
      color: '#fff',
      border: 'none',
      padding: 13,
      borderRadius: 8,
      fontWeight: 700,
      fontSize: 'var(--sf-text-base)',
      cursor: 'pointer',
      marginBottom: 10
    }
  }, justAdded ? 'Added ✓' : 'Add to cart'), /*#__PURE__*/React.createElement("button", {
    onClick: handleAdd,
    className: "sf-btn-motion",
    style: {
      width: '100%',
      background: 'var(--sf-navy)',
      color: '#fff',
      border: 'none',
      padding: 13,
      borderRadius: 8,
      fontWeight: 700,
      fontSize: 'var(--sf-text-base)',
      cursor: 'pointer'
    }
  }, "Buy now"), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 20,
      display: 'flex',
      flexDirection: 'column',
      gap: 12,
      fontSize: 'var(--sf-text-xs)',
      color: 'var(--sf-text-secondary)'
    }
  }, perks.map(p => /*#__PURE__*/React.createElement("div", {
    key: p.text,
    style: {
      display: 'flex',
      gap: 8,
      alignItems: 'flex-start'
    }
  }, /*#__PURE__*/React.createElement("span", null, p.icon), " ", p.text)))))), /*#__PURE__*/React.createElement("section", {
    className: "sf-reveal sf-reveal-2",
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '52px auto 0',
      padding: '0 var(--sf-container-padding) 60px'
    }
  }, /*#__PURE__*/React.createElement("h2", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontSize: 20,
      fontWeight: 800,
      margin: '0 0 16px'
    }
  }, "You may also like"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(4,1fr)',
      gap: 16
    }
  }, related.map(p => /*#__PURE__*/React.createElement(ProductCard, _extends({
    key: p.name
  }, p, {
    reviews: null
  }))))), /*#__PURE__*/React.createElement(StorefrontFooter, null));
}
window.ProductScreen = ProductScreen;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/storefront-marketplace-pro/ProductScreen.jsx", error: String((e && e.message) || e) }); }

// ui_kits/storefront-marketplace-pro/StorefrontShell.jsx
try { (() => {
function StorefrontHeader({
  variant = 'full',
  cartCount = 0,
  onNavigate
}) {
  const {
    SearchBar
  } = window.ZamZamERPDesignSystem_a134f1;
  const [scrolled, setScrolled] = React.useState(false);
  React.useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 24);
    window.addEventListener('scroll', onScroll);
    return () => window.removeEventListener('scroll', onScroll);
  }, []);
  const nav = page => e => {
    e.preventDefault();
    onNavigate && onNavigate(page);
  };
  return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--sf-navy-dark)',
      color: '#C7D3DE',
      fontSize: 'var(--sf-text-xs)',
      padding: '7px 0'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '0 auto',
      padding: '0 var(--sf-container-padding)',
      display: 'flex',
      justifyContent: 'space-between'
    }
  }, /*#__PURE__*/React.createElement("span", null, "Free delivery on orders over \u09F32,500"), variant === 'full' ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 22
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: nav('track'),
    style: {
      color: '#C7D3DE',
      textDecoration: 'none'
    }
  }, "Track your order"), /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      color: '#C7D3DE',
      textDecoration: 'none'
    }
  }, "Help center")) : /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: nav('home'),
    style: {
      color: '#C7D3DE',
      textDecoration: 'none'
    }
  }, "\u2190 ", variant === 'cart' ? 'Continue shopping' : 'Back to home'))), /*#__PURE__*/React.createElement("header", {
    style: {
      background: 'var(--sf-navy)',
      position: 'sticky',
      top: 0,
      zIndex: 20,
      padding: scrolled ? '10px 0' : 'var(--sf-header-padding-y) 0',
      boxShadow: scrolled ? '0 8px 20px rgba(7,20,33,.25)' : 'none',
      transition: 'padding var(--sf-duration-base) var(--sf-ease), box-shadow var(--sf-duration-base) var(--sf-ease)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '0 auto',
      padding: '0 var(--sf-container-padding)',
      display: 'flex',
      alignItems: 'center',
      gap: 18
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: nav('home'),
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 10,
      flexShrink: 0,
      textDecoration: 'none'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 38,
      height: 38,
      borderRadius: 10,
      background: 'var(--sf-orange)',
      display: 'grid',
      placeItems: 'center',
      fontFamily: 'var(--font-storefront-display)',
      fontWeight: 800,
      color: 'var(--sf-navy)',
      fontSize: 18
    }
  }, "GM"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-storefront-display)',
      fontWeight: 800,
      fontSize: 20,
      color: '#fff',
      letterSpacing: '-0.02em',
      whiteSpace: 'nowrap'
    }
  }, "Garments Machinery Co.")), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0,
      display: 'flex',
      justifyContent: 'center'
    }
  }, /*#__PURE__*/React.createElement(SearchBar, null)), variant === 'full' && /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 16,
      flexShrink: 0,
      color: '#fff'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--sf-text-xs)',
      lineHeight: 1.3,
      whiteSpace: 'nowrap'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      color: '#93A7B8'
    }
  }, "Hello, sign in"), /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700
    }
  }, "Account & Lists")), /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: nav('cart'),
    className: "sf-btn-motion",
    style: {
      position: 'relative',
      display: 'flex',
      alignItems: 'center',
      gap: 6,
      color: '#fff',
      textDecoration: 'none',
      whiteSpace: 'nowrap'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "26",
    height: "26",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "#fff",
    strokeWidth: "1.8"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M2.25 3h1.386c.51 0 .955.343 1.087.836l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.874-4.708 2.25-7.183a1.125 1.125 0 0 0-1.11-1.317H5.25M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'absolute',
      top: -8,
      right: -10,
      background: 'var(--sf-accent)',
      color: '#fff',
      fontSize: '10.5px',
      fontWeight: 800,
      borderRadius: '50%',
      width: 18,
      height: 18,
      display: 'grid',
      placeItems: 'center'
    }
  }, cartCount), /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 700,
      fontSize: 14
    }
  }, "Cart"))), variant !== 'full' && /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: nav('cart'),
    className: "sf-btn-motion",
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 6,
      color: '#fff',
      textDecoration: 'none'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "24",
    height: "24",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "#fff",
    strokeWidth: "1.8"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M2.25 3h1.386c.51 0 .955.343 1.087.836l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.874-4.708 2.25-7.183a1.125 1.125 0 0 0-1.11-1.317H5.25M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 700,
      fontSize: 14
    }
  }, "Cart"))), variant === 'full' && !scrolled && /*#__PURE__*/React.createElement("div", {
    className: "sf-reveal",
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '14px auto 0',
      padding: '10px var(--sf-container-padding) 0',
      borderTop: '1px solid rgba(255,255,255,0.1)',
      display: 'flex',
      gap: 26,
      fontSize: 'var(--sf-text-sm)',
      fontWeight: 600,
      color: '#D6E0E9'
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: nav('listing'),
    style: {
      color: '#fff',
      textDecoration: 'none',
      display: 'flex',
      gap: 6,
      alignItems: 'center'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "#fff",
    strokeWidth: "2"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"
  })), "All categories"), /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: nav('listing'),
    style: {
      color: '#D6E0E9',
      textDecoration: 'none'
    }
  }, "Circular Knitting Machines"), /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      color: '#D6E0E9',
      textDecoration: 'none'
    }
  }, "Flat Knitting Machines"), /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      color: '#D6E0E9',
      textDecoration: 'none'
    }
  }, "Spare Parts"), /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      color: '#D6E0E9',
      textDecoration: 'none'
    }
  }, "Needles & Sinkers"), /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      color: 'var(--sf-orange)',
      textDecoration: 'none'
    }
  }, "Today's deals"))));
}
function StorefrontFooter() {
  const cols = [{
    title: 'Get to know us',
    links: ['About the company', 'Careers', 'Press releases']
  }, {
    title: 'Let us help you',
    links: ['Track your order', 'Returns & replacements', 'Help center']
  }, {
    title: 'Business accounts',
    links: ['Wholesale pricing', 'Become a reseller', 'Corporate procurement']
  }];
  return /*#__PURE__*/React.createElement("footer", {
    style: {
      marginTop: 56,
      background: 'var(--sf-navy-dark)',
      color: '#C7D3DE'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--sf-container-max)',
      margin: '0 auto',
      padding: '44px var(--sf-container-padding)',
      display: 'grid',
      gridTemplateColumns: 'repeat(4,1fr)',
      gap: 32
    }
  }, cols.map(c => /*#__PURE__*/React.createElement("div", {
    key: c.title
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      color: '#fff',
      fontWeight: 700,
      fontSize: 'var(--sf-text-sm)',
      marginBottom: 14
    }
  }, c.title), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 9,
      fontSize: 13
    }
  }, c.links.map(l => /*#__PURE__*/React.createElement("a", {
    key: l,
    href: "#",
    style: {
      color: '#C7D3DE',
      textDecoration: 'none'
    }
  }, l))))), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      color: '#fff',
      fontWeight: 700,
      fontSize: 'var(--sf-text-sm)',
      marginBottom: 14
    }
  }, "Contact"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 9,
      fontSize: 13
    }
  }, /*#__PURE__*/React.createElement("span", null, "+880 1XXX-XXXXXX"), /*#__PURE__*/React.createElement("span", null, "support@garmentsmachinery.com")))), /*#__PURE__*/React.createElement("div", {
    style: {
      borderTop: '1px solid rgba(255,255,255,0.08)',
      padding: '18px var(--sf-container-padding)',
      textAlign: 'center',
      fontSize: 12,
      color: '#5A6E80'
    }
  }, "\xA9 2026 Garments Machinery Company. All rights reserved."));
}
window.StorefrontHeader = StorefrontHeader;
window.StorefrontFooter = StorefrontFooter;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/storefront-marketplace-pro/StorefrontShell.jsx", error: String((e && e.message) || e) }); }

__ds_ns.AdminBadge = __ds_scope.AdminBadge;

__ds_ns.AdminButton = __ds_scope.AdminButton;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.Input = __ds_scope.Input;

__ds_ns.SidebarNavItem = __ds_scope.SidebarNavItem;

__ds_ns.PriceTag = __ds_scope.PriceTag;

__ds_ns.ProductCard = __ds_scope.ProductCard;

__ds_ns.SearchBar = __ds_scope.SearchBar;

__ds_ns.StorefrontBadge = __ds_scope.StorefrontBadge;

__ds_ns.StorefrontButton = __ds_scope.StorefrontButton;

})();
