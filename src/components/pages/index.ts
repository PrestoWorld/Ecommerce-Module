import type React from 'react';
import ProductsScreen from './ProductsScreen';
import CustomersScreen from './CustomersScreen';
import OrdersScreen from './OrdersScreen';
import BillsScreen from './BillsScreen';
import EcomScreen from './EcomScreen';
import InvoicesScreen from './InvoicesScreen';

export { ProductsScreen, CustomersScreen, OrdersScreen, BillsScreen, EcomScreen, InvoicesScreen };

// Default export: screen-id → component map consumed by the Dashboard
// module screen loader (SellerCenterPage componentUrl fallback).
const screenMap = {
  products: ProductsScreen,
  customers: CustomersScreen,
  orders: OrdersScreen,
  bills: BillsScreen,
  ecom: EcomScreen,
  invoices: InvoicesScreen,
} satisfies Record<string, React.ComponentType<Record<string, unknown>>>;

declare global {
  interface Window {
    // Merged screen-id maps from all loaded module bundles. IIFE bundles
    // loaded via import() keep their `var` exports module-scoped, so the
    // map must be published on window explicitly.
    __moduleScreens?: Record<string, React.ComponentType<Record<string, unknown>>>;
  }
}

if (typeof window !== 'undefined') {
  window.__moduleScreens = { ...(window.__moduleScreens ?? {}), ...screenMap };
}

export default screenMap;
