import type React from 'react';
import { createElement } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import ProductsScreenBase from './ProductsScreen';
import CustomersScreenBase from './CustomersScreen';
import OrdersScreenBase from './OrdersScreen';
import BillsScreenBase from './BillsScreen';
import EcomScreenBase from './EcomScreen';
import InvoicesScreenBase from './InvoicesScreen';

// The module bundle ships its own copy of @tanstack/react-query (only the
// react family is external), so a provider in the host app never reaches
// these screens — wrap every screen with a provider from this bundle.
const queryClient = new QueryClient({
  defaultOptions: {
    queries: { refetchOnWindowFocus: false, retry: 1 },
  },
});

function withQueryClient<P extends Record<string, unknown>>(
  Screen: React.ComponentType<P>,
): React.ComponentType<P> {
  const Wrapped: React.FC<P> = (props) => createElement(
    QueryClientProvider,
    { client: queryClient },
    createElement(Screen, props),
  );
  Wrapped.displayName = `withQueryClient(${Screen.displayName ?? Screen.name ?? 'Screen'})`;
  return Wrapped;
}

export const ProductsScreen = withQueryClient(ProductsScreenBase);
export const CustomersScreen = withQueryClient(CustomersScreenBase);
export const OrdersScreen = withQueryClient(OrdersScreenBase);
export const BillsScreen = withQueryClient(BillsScreenBase);
export const EcomScreen = withQueryClient(EcomScreenBase);
export const InvoicesScreen = withQueryClient(InvoicesScreenBase);

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
