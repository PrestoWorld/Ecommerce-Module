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
export default {
  products: ProductsScreen,
  customers: CustomersScreen,
  orders: OrdersScreen,
  bills: BillsScreen,
  ecom: EcomScreen,
  invoices: InvoicesScreen,
} satisfies Record<string, React.ComponentType<Record<string, unknown>>>;
