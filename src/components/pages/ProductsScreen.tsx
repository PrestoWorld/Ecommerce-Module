import React, { useCallback, useEffect, useRef, useState } from 'react';

/* ── Types ─────────────────────────────────────────────────────── */

interface ProductPrice {
  regular: number;
  sale: number | null;
  currency: string;
}

interface ProductStock {
  onHand: number;
  reserved: number;
  available: number;
  status: 'instock' | 'lowstock' | 'outofstock';
}

interface ProductListItem {
  id: string;
  title: string;
  slug: string;
  status: 'draft' | 'active' | 'archived';
  sku: string;
  price: ProductPrice;
  stock: ProductStock;
  coverImage: string | null;
  categories: string[];
  tags: string[];
  publishedAt: number | null;
  createdAt: number;
  updatedAt: number;
}

interface ProductVariant {
  id?: string;
  sku: string;
  barcode: string | null;
  edition: number;
  editionName: string | null;
  coverPrice: number;
  wholesalePrice: number;
  costPrice: number;
  regularPrice: number | null;
  salePrice: number | null;
  currency: string;
  stockOnHand: number;
  stockReserved: number;
  reorderPoint: number;
  weight: number | null;
  dimensions: string | null;
  status: string;
  isDefault: boolean;
}

interface ProductDetail {
  id: string;
  title: string;
  slug: string;
  status: 'draft' | 'active' | 'archived';
  subtitle: string | null;
  description: string | null;
  shortDescription: string | null;
  coverImage: string | null;
  categories: string[];
  tags: string[];
  authors: string[];
  translators: string[];
  publisher: string | null;
  originalPublisher: string | null;
  publicationYear: number | null;
  isbn13: string | null;
  isbn10: string | null;
  issn: string | null;
  language: string;
  pageCount: number | null;
  bindingType: string | null;
  dimensions: string | null;
  weight: number | null;
  tableOfContents: string | null;
  metaTitle: string | null;
  metaDescription: string | null;
  metaKeywords: string | null;
  catalogVisibility: string;
  menuOrder: number;
  purchaseNote: string | null;
  variants: ProductVariant[];
  publishedAt: number | null;
  createdAt: number;
  updatedAt: number;
}

interface Taxonomies {
  categories: string[];
  tags: string[];
}

interface ListState {
  items: ProductListItem[];
  total: number;
  page: number;
  perPage: number;
  counts: { all: number; published: number; draft: number; trash: number };
}

/* ── API helpers ───────────────────────────────────────────────── */

const API = '/api/admin/products';

async function api<T = unknown>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(path, {
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    ...init,
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok || body?.success === false) {
    throw new Error(body?.error || `HTTP ${res.status}`);
  }
  return body.data as T;
}

function formatPrice(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(value)) return '—';
  return `${Math.trunc(value).toLocaleString('vi-VN')} ₫`;
}

function formatDate(ts: number | null | undefined): string {
  if (!ts) return '—';
  const d = new Date(ts * 1000);
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
}

const STOCK_LABELS: Record<string, string> = {
  instock: 'Còn hàng',
  lowstock: 'Sắp hết hàng',
  outofstock: 'Hết hàng',
};

const STATUS_LABELS: Record<string, string> = {
  active: 'Đã xuất bản',
  draft: 'Nháp',
  archived: 'Thùng rác',
};

/* ── Styles (Shopify admin / Polaris-inspired) ─────────────────── */

const WOO_CSS = `
.woo-wrap { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif; color: #303030; font-size: 14px; line-height: 1.5; }
.woo-wrap * { box-sizing: border-box; }

/* Page header */
.woo-h1 { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 4px 0 16px; }
.woo-h1 h1 { font-size: 20px; font-weight: 650; margin: 0; line-height: 1.4; color: #1a1a1a; letter-spacing: -0.01em; }

/* View tabs (Polaris Tabs) */
.woo-views { margin: 0 0 12px; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 4px; font-size: 14px; }
.woo-views li a { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; text-decoration: none; color: #4a4a4a; border-radius: 8px; font-weight: 550; }
.woo-views li a:hover { background: rgba(0,0,0,0.05); color: #1a1a1a; }
.woo-views li a.current { background: rgba(0,0,0,0.08); color: #303030; font-weight: 650; cursor: default; }
.woo-views li a.current:hover { background: rgba(0,0,0,0.08); }
.woo-views .count { opacity: 0.65; font-weight: 450; }

/* Cards (Polaris LegacyCard) */
.woo-metabox { background: #fff; border: 1px solid #e3e3e3; box-shadow: 0 1px 0 rgba(26,26,26,0.07); border-radius: 12px; }
.woo-metabox > h2, .woo-metabox > h3 { font-size: 14px; font-weight: 650; margin: 0; padding: 12px 16px; border-bottom: 1px solid #ebebeb; color: #303030; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.woo-metabox-body { padding: 16px; }

/* Toolbar + table card */
.woo-tablecard { background: #fff; border: 1px solid #e3e3e3; box-shadow: 0 1px 0 rgba(26,26,26,0.07); border-radius: 12px; overflow: hidden; }
.woo-toolbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 12px 16px; border-bottom: 1px solid #ebebeb; }
.woo-search { position: relative; flex: 1 1 240px; max-width: 380px; min-width: 180px; }
.woo-search svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #616161; pointer-events: none; }
.woo-search input { padding-left: 32px !important; }
.woo-toolbar-right { display: flex; align-items: center; gap: 8px; margin-left: auto; flex-wrap: wrap; }
.woo-wrap .woo-toolbar-right select { width: auto; min-width: 150px; flex: 0 0 auto; }
.woo-bulkbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; width: 100%; padding-top: 4px; font-size: 13px; color: #616161; }
.woo-bulkbar .sel-count { font-weight: 650; color: #303030; }
.woo-wrap .woo-bulkbar select { width: auto; min-width: 180px; }

/* Table (Polaris IndexTable) */
.woo-table { width: 100%; border-collapse: separate; border-spacing: 0; background: #fff; }
.woo-table th { text-align: left; font-size: 13px; font-weight: 550; color: #616161; padding: 10px 16px; border-bottom: 1px solid #e3e3e3; white-space: nowrap; background: #fff; }
.woo-table td { padding: 12px 16px; border-bottom: 1px solid #ebebeb; vertical-align: middle; font-size: 14px; color: #303030; }
.woo-table tbody tr:last-child td { border-bottom: 0; }
.woo-table tbody tr:hover td { background: #f7f7f7; }
.woo-table .check-column { width: 44px; padding: 12px 0 12px 16px; }
.woo-wrap input[type=checkbox] { accent-color: #1a1a1a; width: 16px; height: 16px; margin: 0; cursor: pointer; }

.woo-thumb { width: 40px; height: 40px; border-radius: 8px; background: #f1f1f1; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
.woo-thumb img { width: 100%; height: 100%; object-fit: cover; }
.woo-thumb .dashicons { width: 20px; height: 20px; fill: #8a8a8a; }
.woo-title-cell { display: flex; gap: 12px; align-items: flex-start; min-width: 260px; }
.woo-row-title { font-size: 14px; font-weight: 550; color: #303030; text-decoration: none; cursor: pointer; line-height: 1.35; display: inline-block; }
.woo-row-title:hover { color: #005bd3; text-decoration: underline; }
.woo-row-id { color: #616161; font-size: 12px; margin-top: 2px; }
.woo-row-actions { font-size: 13px; color: #616161; margin-top: 4px; visibility: hidden; }
.woo-table tbody tr:hover .woo-row-actions, .woo-table tbody tr:focus-within .woo-row-actions { visibility: visible; }
.woo-row-actions button { background: none; border: 0; padding: 0; color: #005bd3; font-size: 13px; cursor: pointer; font-family: inherit; }
.woo-row-actions button:hover { color: #004299; text-decoration: underline; }
.woo-row-actions .sep { margin: 0 6px; color: #d4d4d4; }
.woo-row-actions button.danger { color: #c70a24; }

/* Badges (Polaris Badge) */
.woo-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 8px; font-size: 12px; font-weight: 550; line-height: 1.6; white-space: nowrap; }
.woo-badge.published, .woo-badge.instock { background: #b1fec0; color: #014b40; }
.woo-badge.draft { background: rgba(0,0,0,0.06); color: #616161; }
.woo-badge.trash { background: #ffeb78; color: #4f4700; }
.woo-badge.outofstock { background: #fed1d7; color: #8e0b21; }
.woo-badge.lowstock { background: #ffd6a4; color: #5e4200; }

/* Stock text */
.woo-stock { font-size: 13px; font-weight: 550; white-space: nowrap; }
.woo-stock.instock { color: #047b5d; }
.woo-stock.lowstock { color: #5e4200; }
.woo-stock.outofstock { color: #c70a24; }

.woo-price .sale { color: #303030; font-weight: 550; }
.woo-price .regular { color: #616161; text-decoration: line-through; margin-left: 6px; font-size: 13px; font-weight: 450; }
.woo-cat-list { max-width: 200px; }
.woo-cat-list button { background: none; border: 0; padding: 0; color: #005bd3; font-size: 14px; cursor: pointer; font-family: inherit; text-align: left; }
.woo-cat-list button:hover { color: #004299; text-decoration: underline; }
.woo-tags { color: #616161; font-size: 13px; max-width: 160px; }
.woo-date { color: #616161; font-size: 13px; white-space: nowrap; }

.woo-empty { text-align: center; padding: 56px 24px; color: #616161; }
.woo-empty h2 { font-size: 16px; font-weight: 650; color: #303030; margin: 0 0 6px; }
.woo-empty p { font-size: 14px; margin: 0 0 16px; }

.woo-pagination { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin: 12px 0 40px; color: #616161; font-size: 13px; }
.woo-pagination .pages { display: flex; gap: 6px; align-items: center; }
.woo-pagination .button { min-width: 32px; padding: 0 10px; font-size: 16px; line-height: 1; }

/* Notices (Polaris Banner) */
.woo-notice { padding: 12px 16px; border-radius: 12px; margin: 0 0 16px; font-size: 14px; line-height: 1.5; }
.woo-notice.success { background: #cdfed4; color: #014b40; }
.woo-notice.error { background: #fee8eb; color: #8e0b21; }
.woo-notice.info { background: #eaf4ff; color: #003a5a; }

/* Forms (Polaris TextField / Select) */
.woo-wrap input[type=text], .woo-wrap input[type=number], .woo-wrap input[type=url], .woo-wrap input[type=search],
.woo-wrap textarea, .woo-wrap select {
  border: 1px solid #8a8a8a; border-top-color: #898f94; border-radius: 8px; padding: 6px 12px; font-size: 14px; min-height: 32px;
  background: #fdfdfd; color: #303030; font-family: inherit; width: 100%; transition: box-shadow 120ms ease, border-color 120ms ease;
}
.woo-wrap textarea { min-height: 84px; line-height: 1.55; resize: vertical; }
.woo-wrap select { cursor: pointer; }
.woo-wrap input:focus, .woo-wrap textarea:focus, .woo-wrap select:focus { border-color: #1a1a1a; box-shadow: 0 0 0 1px #005bd3; outline: none; }
.woo-wrap input::placeholder, .woo-wrap textarea::placeholder { color: #8a8a8a; }
.woo-wrap input:disabled, .woo-wrap select:disabled, .woo-wrap textarea:disabled { background: #f1f1f1; color: #b5b5b5; border-color: #ebebeb; }

.woo-wrap .button { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 32px; padding: 0 12px;
  background: #fff; border: none; border-radius: 8px; color: #303030; font-size: 14px; font-weight: 550; cursor: pointer;
  font-family: inherit; white-space: nowrap; text-decoration: none;
  box-shadow: 0 -1px 0 #b5b5b5 inset, 0 0 0 1px rgba(0,0,0,0.1), 0 1px 0 #fff inset; }
.woo-wrap .button:hover { background: #fafafa; box-shadow: 0 -1px 0 #ccc inset, 0 0 0 1px rgba(0,0,0,0.15), 0 1px 0 #fff inset; }
.woo-wrap .button:active { background: #f1f1f1; }
.woo-wrap .button:disabled { background: #f1f1f1; color: #b5b5b5; box-shadow: 0 0 0 1px #ebebeb inset; cursor: not-allowed; }
.woo-wrap .button-primary { background: #1a1a1a; color: #fff; box-shadow: 0 1px 0 rgba(0,0,0,0.2); }
.woo-wrap .button-primary:hover { background: #303030; color: #fff; box-shadow: 0 1px 0 rgba(0,0,0,0.25); }
.woo-wrap .button-primary:active { background: #4a4a4a; }
.woo-wrap .button-primary:disabled { background: rgba(0,0,0,0.17); color: #fff; box-shadow: none; }
.woo-wrap .button-link-delete { background: none; border: 0; color: #c70a24; cursor: pointer; font-size: 14px; font-family: inherit; padding: 0; text-decoration: none; font-weight: 550; }
.woo-wrap .button-link-delete:hover { color: #8e0b21; text-decoration: underline; }
.woo-wrap .button-link-delete:disabled { color: #b5b5b5; cursor: not-allowed; text-decoration: none; }
.woo-wrap .description { color: #616161; font-size: 13px; margin: 4px 0 0; line-height: 1.5; }
.woo-form-row { margin-bottom: 16px; }
.woo-form-row:last-child { margin-bottom: 0; }
.woo-form-row label { display: block; font-weight: 550; margin-bottom: 6px; font-size: 13px; color: #303030; }
.woo-form-inline { display: flex; gap: 8px; align-items: center; }
.woo-form-inline > * { width: auto; }
.woo-form-inline > input { flex: 1 1 auto; width: auto; min-width: 0; }
.woo-required { color: #c70a24; }

/* Editor layout */
.woo-editor { display: flex; gap: 16px; align-items: flex-start; max-width: 1400px; padding-bottom: 48px; flex-wrap: wrap; }
.woo-editor-left { flex: 1 1 640px; min-width: 0; }
.woo-editor-left > .woo-metabox { margin-bottom: 16px; }
.woo-editor-right { flex: 0 0 320px; display: flex; flex-direction: column; gap: 16px; }
.woo-title-area { padding: 4px 0 0; margin-bottom: 20px; }
.woo-title-input { font-size: 22px !important; font-weight: 650; letter-spacing: -0.01em; color: #1a1a1a !important;
  background: transparent !important; border: 1px solid transparent !important; border-radius: 8px !important;
  padding: 6px 8px !important; min-height: 44px !important; }
.woo-title-input::placeholder { color: #8a8a8a; font-weight: 450; }
.woo-title-input:hover { background: #f7f7f7 !important; }
.woo-title-input:focus { background: #fff !important; border-color: #8a8a8a !important; box-shadow: 0 0 0 1px #005bd3 !important; }
.woo-slug-line { font-size: 13px; color: #616161; margin: 6px 8px 0; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.woo-slug-line .slug { color: #005bd3; background: rgba(0,91,211,0.08); padding: 2px 8px; border-radius: 6px; }
.woo-short-desc textarea { min-height: 96px; }

/* Sticky action bar (Shopify editor top bar) */
.woo-topbar { position: sticky; top: 0; z-index: 30; display: flex; align-items: center; justify-content: space-between; gap: 12px;
  flex-wrap: wrap; padding: 8px 0 12px; margin-bottom: 4px; background: rgba(248,250,252,0.92); backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px); border-bottom: 1px solid #e3e3e3; }
.woo-topbar-left { display: flex; align-items: center; gap: 8px; min-width: 0; }
.woo-back { display: inline-flex; align-items: center; gap: 6px; background: none; border: 0; padding: 4px 8px 4px 4px;
  color: #005bd3; font-size: 14px; font-weight: 550; font-family: inherit; cursor: pointer; border-radius: 8px; }
.woo-back:hover { background: rgba(0,0,0,0.05); color: #004299; }
.woo-back svg { width: 16px; height: 16px; }
.woo-topbar-sep { color: #d4d4d4; }
.woo-topbar-title { font-size: 14px; font-weight: 650; color: #1a1a1a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.woo-topbar-right { display: flex; align-items: center; gap: 10px; margin-left: auto; }
.woo-topbar-right .button { min-width: 76px; }

/* Status card */
.woo-publish-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 14px; align-items: flex-start; }
.woo-publish-actions .button { width: 100%; }

/* Organization */
.woo-cat-checklist { list-style: none; margin: 0; padding: 0; max-height: 200px; overflow-y: auto; border: 1px solid #e3e3e3; border-radius: 8px; }
.woo-cat-checklist li { border-bottom: 1px solid #ebebeb; }
.woo-cat-checklist li:last-child { border-bottom: 0; }
.woo-cat-checklist label { display: flex; gap: 8px; align-items: center; padding: 7px 10px; cursor: pointer; font-weight: 450 !important; margin: 0 !important; font-size: 13px !important; }
.woo-cat-checklist label:hover { background: #f7f7f7; }
.woo-tag-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.woo-tag-chip { display: inline-flex; align-items: center; gap: 6px; background: #f1f1f1; border: 1px solid #e3e3e3; color: #303030; border-radius: 8px; padding: 3px 8px; font-size: 13px; font-weight: 550; }
.woo-tag-chip button { background: none; border: 0; color: #616161; cursor: pointer; font-size: 15px; line-height: 1; padding: 0; }
.woo-tag-chip button:hover { color: #c70a24; }
.woo-img-preview { margin: 4px 0 12px; display: flex; align-items: center; gap: 12px; }
.woo-img-preview img { width: 72px; height: 72px; object-fit: cover; border: 1px solid #e3e3e3; border-radius: 8px; background: #f1f1f1; }
.woo-divider { border: 0; border-top: 1px solid #ebebeb; margin: 16px 0; }
.woo-field-label { display: block; font-weight: 550; margin-bottom: 6px; font-size: 13px; color: #303030; }

/* Field grids */
.woo-price-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.woo-profit { font-size: 13px; color: #047b5d; font-weight: 550; margin: 14px 0 0; }

@media (max-width: 900px) {
  .woo-editor-right { flex: 1 1 100%; }
  .woo-price-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .woo-h1 { flex-direction: column; align-items: flex-start; }
  .woo-search { flex: 1 1 100%; max-width: none; }
  .woo-toolbar-right { margin-left: 0; width: 100%; }
  .woo-wrap .woo-toolbar-right select { flex: 1 1 0; width: auto; min-width: 0; }
}
`;

let styleInjected = false;
function ensureStyles(): void {
  if (styleInjected || typeof document === 'undefined') return;
  styleInjected = true;
  const el = document.createElement('style');
  el.setAttribute('data-woo-products', '1');
  el.textContent = WOO_CSS;
  document.head.appendChild(el);
}

/* ── Screen ────────────────────────────────────────────────────── */

type View = 'all' | 'published' | 'draft' | 'trash';

const ProductsScreen: React.FC<Record<string, unknown>> = () => {
  ensureStyles();

  const [mode, setMode] = useState<'list' | 'edit'>('list');
  const [editingId, setEditingId] = useState<string | null>(null);

  // List state
  const [list, setList] = useState<ListState>({
    items: [], total: 0, page: 1, perPage: 20,
    counts: { all: 0, published: 0, draft: 0, trash: 0 },
  });
  const [view, setView] = useState<View>('all');
  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [orderby, setOrderby] = useState('date');
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [bulkAction, setBulkAction] = useState('');
  const [loading, setLoading] = useState(false);
  const [notice, setNotice] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);
  const [taxonomies, setTaxonomies] = useState<Taxonomies>({ categories: [], tags: [] });

  // Editor state
  const [form, setForm] = useState<ProductDetail | null>(null);
  const [saving, setSaving] = useState(false);
  const statusAtLoad = useRef<string | null>(null);
  const [newCategory, setNewCategory] = useState('');
  const [tagInput, setTagInput] = useState('');
  const [slugEditing, setSlugEditing] = useState(false);

  const loadTaxonomies = useCallback(async () => {
    try {
      setTaxonomies(await api<Taxonomies>(`${API}/taxonomies`));
    } catch {
      /* non-critical */
    }
  }, []);

  const loadList = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        view, page: String(list.page), per_page: String(list.perPage),
        orderby, order: orderby === 'title' ? 'asc' : 'desc',
      });
      if (search.trim()) params.set('q', search.trim());
      if (categoryFilter) params.set('category', categoryFilter);
      setList(await api<ListState>(`${API}?${params.toString()}`));
      setSelected(new Set());
    } catch (err) {
      setNotice({ kind: 'error', text: err instanceof Error ? err.message : 'Không tải được danh sách sản phẩm' });
    } finally {
      setLoading(false);
    }
  }, [view, search, categoryFilter, orderby, list.page, list.perPage]);

  useEffect(() => { void loadTaxonomies(); }, [loadTaxonomies]);
  useEffect(() => { void loadList(); }, [loadList]); // eslint-disable-line react-hooks/exhaustive-deps

  const changeView = (v: View) => {
    setView(v);
    setList((s) => ({ ...s, page: 1 }));
  };

  const changePage = (page: number) => {
    setList((s) => ({ ...s, page }));
  };

  const toggleSelect = (id: string) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };

  const toggleSelectAll = () => {
    setSelected((prev) => (prev.size === list.items.length ? new Set() : new Set(list.items.map((i) => i.id))));
  };

  const applyBulk = async () => {
    if (!bulkAction || selected.size === 0) {
      setNotice({ kind: 'error', text: 'Hãy chọn hành động và ít nhất một sản phẩm' });
      return;
    }
    try {
      const result = await api<{ updated: number; deleted: number }>(`${API}/bulk`, {
        method: 'POST',
        body: JSON.stringify({ action: bulkAction, ids: Array.from(selected) }),
      });
      const labels: Record<string, string> = {
        trash: 'đã chuyển vào thùng rác',
        restore: 'đã khôi phục',
        publish: 'đã xuất bản',
        draft: 'đã chuyển về nháp',
        delete: 'đã xóa vĩnh viễn',
      };
      const total = result.updated + result.deleted;
      setNotice({ kind: 'success', text: `${total} sản phẩm ${labels[bulkAction] ?? bulkAction}` });
      setBulkAction('');
      await loadList();
      await loadTaxonomies();
    } catch (err) {
      setNotice({ kind: 'error', text: err instanceof Error ? err.message : 'Thao tác thất bại' });
    }
  };

  const trashOne = async (id: string) => {
    try {
      await api(`${API}/bulk`, { method: 'POST', body: JSON.stringify({ action: 'trash', ids: [id] }) });
      setNotice({ kind: 'success', text: 'Đã chuyển vào thùng rác' });
      await loadList();
    } catch (err) {
      setNotice({ kind: 'error', text: err instanceof Error ? err.message : 'Thao tác thất bại' });
    }
  };

  const restoreOne = async (id: string) => {
    try {
      await api(`${API}/bulk`, { method: 'POST', body: JSON.stringify({ action: 'restore', ids: [id] }) });
      setNotice({ kind: 'success', text: 'Đã khôi phục sản phẩm' });
      await loadList();
    } catch (err) {
      setNotice({ kind: 'error', text: err instanceof Error ? err.message : 'Thao tác thất bại' });
    }
  };

  const deleteForever = async (id: string) => {
    if (!window.confirm('Xóa vĩnh viễn sản phẩm này? Hành động này không thể hoàn tác.')) return;
    try {
      await api(`${API}/${id}`, { method: 'DELETE' });
      setNotice({ kind: 'success', text: 'Đã xóa sản phẩm' });
      await loadList();
    } catch (err) {
      setNotice({ kind: 'error', text: err instanceof Error ? err.message : 'Thao tác thất bại' });
    }
  };

  const deleteFromEditor = async () => {
    if (!editingId) return;
    if (!window.confirm('Xóa vĩnh viễn sản phẩm này? Hành động này không thể hoàn tác.')) return;
    setSaving(true);
    try {
      await api(`${API}/${editingId}`, { method: 'DELETE' });
      setNotice({ kind: 'success', text: 'Đã xóa sản phẩm' });
      setMode('list');
      setForm(null);
      setEditingId(null);
      statusAtLoad.current = null;
      await loadList();
    } catch (err) {
      setNotice({ kind: 'error', text: err instanceof Error ? err.message : 'Thao tác thất bại' });
    } finally {
      setSaving(false);
    }
  };

  const openEditor = async (id: string | null) => {
    setNotice(null);
    if (id === null) {
      setForm({
        id: '', title: '', slug: '', status: 'draft', subtitle: null,
        description: '', shortDescription: '', coverImage: null,
        categories: [], tags: [], authors: [], translators: [],
        publisher: null, originalPublisher: null, publicationYear: null,
        isbn13: null, isbn10: null, issn: null, language: 'vi',
        pageCount: null, bindingType: null, dimensions: null, weight: null,
        tableOfContents: null, metaTitle: null, metaDescription: null, metaKeywords: null,
        catalogVisibility: 'visible', menuOrder: 0, purchaseNote: null,
        variants: [{
          sku: '', barcode: null, edition: 1, editionName: null,
          coverPrice: 0, wholesalePrice: 0, costPrice: 0,
          regularPrice: null, salePrice: null, currency: 'VND',
          stockOnHand: 0, stockReserved: 0, reorderPoint: 0,
          weight: null, dimensions: null, status: 'active', isDefault: true,
        }],
        publishedAt: null, createdAt: 0, updatedAt: 0,
      });
      setEditingId(null);
      statusAtLoad.current = 'draft';
      setMode('edit');
      return;
    }
    try {
      const detail = await api<ProductDetail>(`${API}/${id}`);
      setForm(detail);
      setEditingId(id);
      statusAtLoad.current = detail.status;
      setMode('edit');
    } catch (err) {
      setNotice({ kind: 'error', text: err instanceof Error ? err.message : 'Không mở được sản phẩm' });
    }
  };

  const patchForm = (patch: Partial<ProductDetail>) => {
    setForm((prev) => (prev ? { ...prev, ...patch } : prev));
  };

  const patchVariant = (patch: Partial<ProductVariant>) => {
    setForm((prev) => {
      if (!prev) return prev;
      const variants = prev.variants.length > 0 ? [...prev.variants] : [{
        sku: '', barcode: null, edition: 1, editionName: null,
        coverPrice: 0, wholesalePrice: 0, costPrice: 0,
        regularPrice: null, salePrice: null, currency: 'VND',
        stockOnHand: 0, stockReserved: 0, reorderPoint: 0,
        weight: null, dimensions: null, status: 'active', isDefault: true,
      } satisfies ProductVariant];
      variants[0] = { ...variants[0], ...patch };
      return { ...prev, variants };
    });
  };

  const saveProduct = async (opts: { trash?: boolean; draft?: boolean } = {}) => {
    if (!form) return;
    if (!form.title.trim()) {
      setNotice({ kind: 'error', text: 'Vui lòng nhập tiêu đề sản phẩm' });
      return;
    }
    setSaving(true);
    try {
      const v = form.variants[0];
      const wasArchived = form.status === 'archived';
      const prevStatus = statusAtLoad.current;
      const payload = {
        title: form.title.trim(),
        slug: form.slug || undefined,
        status: opts.trash ? 'archived' : opts.draft ? 'draft' : form.status,
        description: form.description || null,
        shortDescription: form.shortDescription || null,
        coverImage: form.coverImage || null,
        categories: form.categories,
        tags: form.tags,
        authors: form.authors,
        translators: form.translators,
        publisher: form.publisher || null,
        originalPublisher: form.originalPublisher || null,
        publicationYear: form.publicationYear,
        isbn13: form.isbn13 || null,
        isbn10: form.isbn10 || null,
        issn: form.issn || null,
        language: form.language || 'vi',
        pageCount: form.pageCount,
        bindingType: form.bindingType || null,
        dimensions: form.dimensions || null,
        weight: form.weight,
        tableOfContents: form.tableOfContents || null,
        metaTitle: form.metaTitle || null,
        metaDescription: form.metaDescription || null,
        metaKeywords: form.metaKeywords || null,
        catalogVisibility: form.catalogVisibility,
        menuOrder: form.menuOrder,
        purchaseNote: form.purchaseNote || null,
        variants: [{
          external_id: v?.id || undefined,
          sku: v?.sku || '',
          barcode: v?.barcode || null,
          edition: v?.edition ?? 1,
          coverPrice: v?.coverPrice ?? 0,
          wholesalePrice: v?.wholesalePrice ?? 0,
          costPrice: v?.costPrice ?? 0,
          regularPrice: v?.regularPrice ?? 0,
          salePrice: v?.salePrice ?? null,
          currency: v?.currency ?? 'VND',
          stockOnHand: v?.stockOnHand ?? 0,
          reorderPoint: v?.reorderPoint ?? 0,
          weight: v?.weight ?? null,
          dimensions: v?.dimensions || null,
          status: 'active',
          isDefault: true,
        }],
      };

      const saved = editingId
        ? await api<ProductDetail>(`${API}/${editingId}`, { method: 'PUT', body: JSON.stringify(payload) })
        : await api<ProductDetail>(`${API}`, { method: 'POST', body: JSON.stringify(payload) });

      await loadTaxonomies();

      if (opts.trash) {
        setNotice({ kind: 'success', text: 'Đã chuyển sản phẩm vào thùng rác' });
        setMode('list');
        setForm(null);
        setEditingId(null);
        statusAtLoad.current = null;
        await loadList();
        return;
      }

      // Shopify-style: stay in the editor after saving.
      setForm(saved);
      setEditingId(saved.id);
      statusAtLoad.current = saved.status;
      const text = opts.draft && wasArchived
        ? 'Đã khôi phục sản phẩm'
        : saved.status === 'active' && prevStatus !== 'active'
          ? 'Đã xuất bản sản phẩm'
          : saved.status === 'draft' && prevStatus === 'active'
            ? 'Đã chuyển sản phẩm về nháp'
            : !editingId && saved.status === 'draft'
              ? 'Đã lưu bản nháp'
              : 'Đã lưu sản phẩm';
      setNotice({ kind: 'success', text });
      await loadList();
    } catch (err) {
      setNotice({ kind: 'error', text: err instanceof Error ? err.message : 'Lưu thất bại' });
    } finally {
      setSaving(false);
    }
  };

  const addCategory = () => {
    const name = newCategory.trim();
    if (!name || !form) return;
    if (!form.categories.includes(name)) {
      patchForm({ categories: [...form.categories, name] });
    }
    if (!taxonomies.categories.includes(name)) {
      setTaxonomies((t) => ({ ...t, categories: [...t.categories, name].sort((a, b) => a.localeCompare(b, 'vi')) }));
    }
    setNewCategory('');
  };

  const addTag = () => {
    const name = tagInput.trim();
    if (!name || !form) return;
    if (!form.tags.includes(name)) {
      patchForm({ tags: [...form.tags, name] });
    }
    setTagInput('');
  };

  /* ── List view ───────────────────────────────────────────────── */

  if (mode === 'list') {
    const views: { id: View; label: string; count: number }[] = [
      { id: 'all', label: 'Tất cả', count: list.counts.all },
      { id: 'published', label: 'Đã xuất bản', count: list.counts.published },
      { id: 'draft', label: 'Nháp', count: list.counts.draft },
      { id: 'trash', label: 'Thùng rác', count: list.counts.trash },
    ];

    return (
      <div className="woo-wrap">
        <div className="woo-h1">
          <h1>Sản phẩm</h1>
          <button type="button" className="button button-primary" onClick={() => void openEditor(null)}>
            Thêm sản phẩm
          </button>
        </div>

        {notice && (
          <div className={`woo-notice ${notice.kind}`}>{notice.text}</div>
        )}

        <ul className="woo-views">
          {views.map((v) => (
            <li key={v.id}>
              <a
                className={view === v.id ? 'current' : undefined}
                onClick={(e) => { e.preventDefault(); changeView(v.id); }}
                href="#"
              >
                {v.label} <span className="count">({v.count})</span>
              </a>
            </li>
          ))}
        </ul>

        <div className="woo-tablecard">
          <div className="woo-toolbar">
            <div className="woo-search">
              <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <circle cx="9" cy="9" r="5.5" stroke="currentColor" strokeWidth="1.6" />
                <path d="M13.2 13.2 17 17" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
              </svg>
              <form onSubmit={(e) => { e.preventDefault(); setList((s) => ({ ...s, page: 1 })); void loadList(); }}>
                <input
                  type="search"
                  value={search}
                  placeholder="Tìm sản phẩm…"
                  aria-label="Tìm sản phẩm"
                  onChange={(e) => setSearch(e.target.value)}
                />
              </form>
            </div>

            <div className="woo-toolbar-right">
              <select
                value={categoryFilter}
                aria-label="Lọc theo danh mục"
                onChange={(e) => { setCategoryFilter(e.target.value); setList((s) => ({ ...s, page: 1 })); }}
              >
                <option value="">Tất cả danh mục</option>
                {taxonomies.categories.map((c) => (
                  <option key={c} value={c}>{c}</option>
                ))}
              </select>
              <select value={orderby} aria-label="Sắp xếp" onChange={(e) => setOrderby(e.target.value)}>
                <option value="date">Mới nhất</option>
                <option value="title">Tên (A → Z)</option>
                <option value="sku">SKU</option>
              </select>
            </div>

            {selected.size > 0 && (
              <div className="woo-bulkbar">
                <span className="sel-count">{selected.size} sản phẩm đã chọn</span>
                <select
                  value={bulkAction}
                  aria-label="Hành động hàng loạt"
                  onChange={(e) => setBulkAction(e.target.value)}
                >
                  <option value="">Chọn hành động…</option>
                  <option value="trash">Chuyển vào thùng rác</option>
                  <option value="restore">Khôi phục từ thùng rác</option>
                  <option value="publish">Xuất bản</option>
                  <option value="draft">Chuyển về bản nháp</option>
                  <option value="delete">Xóa vĩnh viễn</option>
                </select>
                <button type="button" className="button" onClick={() => void applyBulk()}>Áp dụng</button>
                <button type="button" className="button" onClick={() => { setSelected(new Set()); setBulkAction(''); }}>
                  Bỏ chọn
                </button>
              </div>
            )}
          </div>

          {list.items.length === 0 && !loading ? (
            <div className="woo-empty">
              <h2>Không tìm thấy sản phẩm</h2>
              <p>
                {view === 'trash'
                  ? 'Thùng rác đang trống.'
                  : search || categoryFilter
                    ? 'Thử thay đổi từ khóa hoặc bộ lọc.'
                    : 'Bắt đầu bằng cách thêm sản phẩm đầu tiên.'}
              </p>
              <button type="button" className="button button-primary" onClick={() => void openEditor(null)}>
                Thêm sản phẩm
              </button>
            </div>
          ) : (
          <table className="woo-table">
            <thead>
              <tr>
                <th className="check-column">
                  <input
                    type="checkbox"
                    checked={list.items.length > 0 && selected.size === list.items.length}
                    onChange={toggleSelectAll}
                  />
                </th>
                <th colSpan={2}>Tên</th>
                <th>Trạng thái</th>
                <th>SKU</th>
                <th>Kho</th>
                <th>Giá</th>
                <th>Danh mục</th>
                <th>Thẻ</th>
                <th>Ngày</th>
              </tr>
            </thead>
            <tbody>
              {list.items.map((item) => (
                <tr key={item.id}>
                  <td className="check-column">
                    <input type="checkbox" checked={selected.has(item.id)} onChange={() => toggleSelect(item.id)} />
                  </td>
                  <td>
                    <div className="woo-thumb">
                      {item.coverImage
                        ? <img src={item.coverImage} alt="" />
                        : (
                          <svg viewBox="0 0 24 24" className="dashicons" aria-hidden>
                            <path d="M18 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 14H6l4-5 2 2.5L14.5 11l3.5 4.5V18z" />
                          </svg>
                        )}
                    </div>
                  </td>
                  <td>
                    <div className="woo-title-cell">
                      <div>
                        <a className="woo-row-title" onClick={(e) => { e.preventDefault(); void openEditor(item.id); }} href="#">
                          {item.title || '(Không có tiêu đề)'}
                        </a>
                        <div className="woo-row-actions">
                          <button type="button" onClick={() => void openEditor(item.id)}>Sửa</button>
                          {view === 'trash' ? (
                            <>
                              <span className="sep">|</span>
                              <button type="button" onClick={() => void restoreOne(item.id)}>Khôi phục</button>
                              <span className="sep">|</span>
                              <button type="button" className="danger" onClick={() => void deleteForever(item.id)}>Xóa vĩnh viễn</button>
                            </>
                          ) : (
                            <>
                              <span className="sep">|</span>
                              <button type="button" onClick={() => void trashOne(item.id)}>Thùng rác</button>
                            </>
                          )}
                        </div>
                        <div className="woo-row-id">ID: {item.id}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span className={`woo-badge ${item.status === 'active' ? 'published' : item.status}`}>
                      {STATUS_LABELS[item.status] ?? item.status}
                    </span>
                  </td>
                  <td>{item.sku || '—'}</td>
                  <td>
                    <span className={`woo-stock ${item.stock.status}`}>
                      {STOCK_LABELS[item.stock.status] ?? item.stock.status}
                      {item.stock.status !== 'outofstock' && ` (${item.stock.available})`}
                    </span>
                  </td>
                  <td className="woo-price">
                    {item.price.sale !== null && item.price.sale > 0 && item.price.sale < item.price.regular ? (
                      <>
                        <span className="sale">{formatPrice(item.price.sale)}</span>
                        <span className="regular">{formatPrice(item.price.regular)}</span>
                      </>
                    ) : (
                      formatPrice(item.price.regular)
                    )}
                  </td>
                  <td className="woo-cat-list">
                    {item.categories.length === 0
                      ? '—'
                      : item.categories.map((c, i) => (
                          <React.Fragment key={c}>
                            {i > 0 && ', '}
                            <button type="button" onClick={() => { setCategoryFilter(c); setList((s) => ({ ...s, page: 1 })); }}>
                              {c}
                            </button>
                          </React.Fragment>
                        ))}
                  </td>
                  <td className="woo-tags">
                    {item.tags.length === 0 ? '—' : item.tags.join(', ')}
                  </td>
                  <td className="woo-date">
                    {formatDate(view === 'published' ? item.publishedAt : item.createdAt)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          )}
        </div>

        <div className="woo-pagination">
          <span>{loading ? 'Đang tải…' : `${list.total} sản phẩm`}</span>
          {list.total > list.perPage && (
            <div className="pages">
              <button
                type="button"
                className="button"
                aria-label="Trang trước"
                disabled={list.page <= 1}
                onClick={() => changePage(list.page - 1)}
              >
                ‹
              </button>
              <span>
                {list.page} / {Math.max(1, Math.ceil(list.total / list.perPage))}
              </span>
              <button
                type="button"
                className="button"
                aria-label="Trang sau"
                disabled={list.page >= Math.ceil(list.total / list.perPage)}
                onClick={() => changePage(list.page + 1)}
              >
                ›
              </button>
            </div>
          )}
        </div>
      </div>
    );
  }

  /* ── Editor view ─────────────────────────────────────────────── */

  if (!form) return null;
  const v = form.variants[0];

  const profit = v && v.regularPrice && v.regularPrice > 0 && v.costPrice > 0
    ? v.regularPrice - v.costPrice
    : null;

  return (
    <div className="woo-wrap">
      <div className="woo-topbar">
        <div className="woo-topbar-left">
          <button type="button" className="woo-back" onClick={() => { setMode('list'); setForm(null); }}>
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
              <path d="M12.5 4.5 7 10l5.5 5.5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
            Sản phẩm
          </button>
          <span className="woo-topbar-sep">/</span>
          <span className="woo-topbar-title">
            {editingId ? form.title || 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm mới'}
          </span>
        </div>
        <div className="woo-topbar-right">
          <span className={`woo-badge ${form.status === 'active' ? 'published' : form.status === 'archived' ? 'trash' : 'draft'}`}>
            {STATUS_LABELS[form.status] ?? form.status}
          </span>
          <button
            type="button"
            className="button button-primary"
            disabled={saving || form.status === 'archived'}
            onClick={() => void saveProduct()}
          >
            {saving ? 'Đang lưu…' : 'Lưu'}
          </button>
        </div>
      </div>

      {notice && <div className={`woo-notice ${notice.kind}`}>{notice.text}</div>}

      <div className="woo-editor">
        <div className="woo-editor-left">
          <div className="woo-title-area">
            <input
              type="text"
              className="woo-title-input"
              placeholder="Nhập tiêu đề sản phẩm ở đây"
              aria-label="Tiêu đề sản phẩm"
              value={form.title}
              onChange={(e) => patchForm({ title: e.target.value })}
            />
            <div className="woo-slug-line">
              Đường dẫn tĩnh:
              {slugEditing ? (
                <input
                  type="text"
                  aria-label="Đường dẫn tĩnh"
                  value={form.slug}
                  onChange={(e) => patchForm({ slug: e.target.value })}
                  style={{ width: 260 }}
                  onBlur={() => setSlugEditing(false)}
                  autoFocus
                />
              ) : (
                <>
                  <span className="slug">/{form.slug || 'san-pham'}</span>
                  <button
                    type="button"
                    className="button"
                    style={{ height: 24, minHeight: 24, padding: '0 8px', fontSize: 12 }}
                    onClick={() => setSlugEditing(true)}
                  >
                    Sửa
                  </button>
                </>
              )}
            </div>
          </div>

          <div className="woo-metabox woo-main-editor">
            <h2>Mô tả</h2>
            <div className="woo-metabox-body">
              <textarea
                value={form.description || ''}
                placeholder="Mô tả chi tiết sản phẩm…"
                onChange={(e) => patchForm({ description: e.target.value })}
                style={{ minHeight: 260 }}
              />
            </div>
          </div>

          <div className="woo-metabox woo-short-desc">
            <h2>Mô tả ngắn</h2>
            <div className="woo-metabox-body">
              <textarea
                value={form.shortDescription || ''}
                placeholder="Mô tả ngắn gọn hiển thị gần tên sản phẩm…"
                onChange={(e) => patchForm({ shortDescription: e.target.value })}
              />
            </div>
          </div>

          {/* Pricing */}
          <div className="woo-metabox">
            <h2>Giá</h2>
            <div className="woo-metabox-body">
              <div className="woo-price-grid">
                <div className="woo-form-row">
                  <label htmlFor="woo-price">Giá bán (₫) <span className="woo-required">*</span></label>
                  <input
                    id="woo-price"
                    type="number" min={0} step="1000"
                    value={v?.regularPrice ?? ''}
                    onChange={(e) => patchVariant({ regularPrice: e.target.value === '' ? null : Number(e.target.value) })}
                  />
                </div>
                <div className="woo-form-row">
                  <label htmlFor="woo-sale">Giá khuyến mãi (₫)</label>
                  <input
                    id="woo-sale"
                    type="number" min={0} step="1000"
                    value={v?.salePrice ?? ''}
                    onChange={(e) => patchVariant({ salePrice: e.target.value === '' ? null : Number(e.target.value) })}
                  />
                  <p className="description">Để trống nếu không giảm giá.</p>
                </div>
                <div className="woo-form-row">
                  <label htmlFor="woo-cost">Giá vốn (₫)</label>
                  <input
                    id="woo-cost"
                    type="number" min={0} step="1000"
                    value={v?.costPrice ?? 0}
                    onChange={(e) => patchVariant({ costPrice: Math.max(0, Number(e.target.value) || 0) })}
                  />
                </div>
              </div>
              {profit !== null && profit >= 0 && v?.regularPrice ? (
                <p className="woo-profit">
                  Lợi nhuận ước tính: {formatPrice(profit)} ({Math.round((profit / v.regularPrice) * 100)}%)
                </p>
              ) : null}
            </div>
          </div>

          {/* Inventory */}
          <div className="woo-metabox">
            <h2>Kho hàng</h2>
            <div className="woo-metabox-body">
              <div className="woo-price-grid">
                <div className="woo-form-row">
                  <label htmlFor="woo-sku">SKU (Mã sản phẩm)</label>
                  <input id="woo-sku" type="text" value={v?.sku ?? ''} onChange={(e) => patchVariant({ sku: e.target.value })} />
                </div>
                <div className="woo-form-row">
                  <label htmlFor="woo-barcode">Mã vạch (Barcode)</label>
                  <input id="woo-barcode" type="text" value={v?.barcode ?? ''} onChange={(e) => patchVariant({ barcode: e.target.value || null })} />
                </div>
                <div className="woo-form-row">
                  <label htmlFor="woo-qty">Số lượng tồn</label>
                  <input
                    id="woo-qty"
                    type="number" min={0}
                    value={v?.stockOnHand ?? 0}
                    onChange={(e) => patchVariant({ stockOnHand: Math.max(0, Number(e.target.value) || 0) })}
                  />
                </div>
                <div className="woo-form-row">
                  <label htmlFor="woo-reorder">Ngưỡng cảnh báo tồn kho</label>
                  <input
                    id="woo-reorder"
                    type="number" min={0}
                    value={v?.reorderPoint ?? 0}
                    onChange={(e) => patchVariant({ reorderPoint: Math.max(0, Number(e.target.value) || 0) })}
                  />
                  <p className="description">Tồn kho ≤ ngưỡng sẽ hiển thị “Sắp hết hàng”.</p>
                </div>
                <div className="woo-form-row">
                  <label htmlFor="woo-edition">Phiên bản / ấn bản</label>
                  <input
                    id="woo-edition"
                    type="text"
                    placeholder="VD: Bìa mềm, Tái bản lần 3"
                    value={v?.editionName ?? ''}
                    onChange={(e) => patchVariant({ editionName: e.target.value || null })}
                  />
                </div>
              </div>
              <p className="description">
                Tình trạng tồn kho:{' '}
                <span className={`woo-badge ${v && v.stockOnHand - 0 <= 0 ? 'outofstock' : (v?.reorderPoint ?? 0) > 0 && v!.stockOnHand <= v!.reorderPoint ? 'lowstock' : 'instock'}`}>
                  {(() => {
                    if (!v) return 'Hết hàng';
                    if (v.stockOnHand <= 0) return 'Hết hàng';
                    if (v.reorderPoint > 0 && v.stockOnHand <= v.reorderPoint) return 'Sắp hết hàng';
                    return `Còn hàng (${v.stockOnHand})`;
                  })()}
                </span>
              </p>
            </div>
          </div>

          {/* Shipping */}
          <div className="woo-metabox">
            <h2>Vận chuyển</h2>
            <div className="woo-metabox-body">
              <div className="woo-price-grid">
                <div className="woo-form-row">
                  <label htmlFor="woo-weight">Cân nặng (gram)</label>
                  <input
                    id="woo-weight"
                    type="number" min={0}
                    value={v?.weight ?? form.weight ?? ''}
                    onChange={(e) => {
                      const val = e.target.value === '' ? null : Number(e.target.value);
                      patchVariant({ weight: val });
                      patchForm({ weight: val });
                    }}
                  />
                </div>
                <div className="woo-form-row">
                  <label htmlFor="woo-dimensions">Kích thước</label>
                  <input
                    id="woo-dimensions"
                    type="text"
                    placeholder="VD: 14 x 20.5 x 2.8 cm"
                    value={v?.dimensions ?? form.dimensions ?? ''}
                    onChange={(e) => {
                      const val = e.target.value || null;
                      patchVariant({ dimensions: val });
                      patchForm({ dimensions: val });
                    }}
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Advanced */}
          <div className="woo-metabox">
            <h2>Nâng cao</h2>
            <div className="woo-metabox-body">
              <div className="woo-form-row">
                <label htmlFor="woo-purchase-note">Ghi chú mua hàng</label>
                <textarea
                  id="woo-purchase-note"
                  value={form.purchaseNote || ''}
                  placeholder="Ghi chú hiển thị sau khi khách hàng mua thành công…"
                  onChange={(e) => patchForm({ purchaseNote: e.target.value || null })}
                />
              </div>
              <div className="woo-form-row" style={{ maxWidth: 160 }}>
                <label htmlFor="woo-menu-order">Thứ tự menu</label>
                <input
                  id="woo-menu-order"
                  type="number" min={0}
                  value={form.menuOrder}
                  onChange={(e) => patchForm({ menuOrder: Math.max(0, Number(e.target.value) || 0) })}
                />
              </div>
            </div>
          </div>

          {/* Book info */}
          <div className="woo-metabox" style={{ marginBottom: 16 }}>
            <h2>Thông tin sách</h2>
            <div className="woo-metabox-body">
              <div className="woo-price-grid">
                <div className="woo-form-row">
                  <label>Tác giả</label>
                  <input
                    type="text"
                    placeholder="Nguyễn Nhật Ánh, Dale Carnegie…"
                    value={form.authors.join(', ')}
                    onChange={(e) => patchForm({
                      authors: e.target.value.split(',').map((s) => s.trim()).filter(Boolean),
                    })}
                  />
                  <p className="description">Ngăn cách bằng dấu phẩy.</p>
                </div>
                <div className="woo-form-row">
                  <label>Dịch giả</label>
                  <input
                    type="text"
                    value={form.translators.join(', ')}
                    onChange={(e) => patchForm({
                      translators: e.target.value.split(',').map((s) => s.trim()).filter(Boolean),
                    })}
                  />
                </div>
                <div className="woo-form-row">
                  <label>Nhà xuất bản</label>
                  <input
                    type="text"
                    value={form.publisher ?? ''}
                    onChange={(e) => patchForm({ publisher: e.target.value || null })}
                  />
                </div>
                <div className="woo-form-row">
                  <label>Năm xuất bản</label>
                  <input
                    type="number" min={1000} max={2100}
                    value={form.publicationYear ?? ''}
                    onChange={(e) => patchForm({ publicationYear: e.target.value === '' ? null : Number(e.target.value) })}
                  />
                </div>
                <div className="woo-form-row">
                  <label>ISBN-13</label>
                  <input
                    type="text"
                    value={form.isbn13 ?? ''}
                    onChange={(e) => patchForm({ isbn13: e.target.value || null })}
                  />
                </div>
                <div className="woo-form-row">
                  <label>ISBN-10</label>
                  <input
                    type="text"
                    value={form.isbn10 ?? ''}
                    onChange={(e) => patchForm({ isbn10: e.target.value || null })}
                  />
                </div>
                <div className="woo-form-row">
                  <label>Số trang</label>
                  <input
                    type="number" min={0}
                    value={form.pageCount ?? ''}
                    onChange={(e) => patchForm({ pageCount: e.target.value === '' ? null : Number(e.target.value) })}
                  />
                </div>
                <div className="woo-form-row">
                  <label>Loại bìa</label>
                  <select
                    value={form.bindingType ?? ''}
                    onChange={(e) => patchForm({ bindingType: e.target.value || null })}
                  >
                    <option value="">— Chọn —</option>
                    <option value="softcover">Bìa mềm</option>
                    <option value="hardcover">Bìa cứng</option>
                    <option value="paperback">Bìa gập</option>
                    <option value="boardbook">Sổ bìa carton</option>
                  </select>
                </div>
                <div className="woo-form-row">
                  <label>Ngôn ngữ</label>
                  <select value={form.language} onChange={(e) => patchForm({ language: e.target.value })}>
                    <option value="vi">Tiếng Việt</option>
                    <option value="en">Tiếng Anh</option>
                    <option value="other">Khác</option>
                  </select>
                </div>
              </div>
              <div className="woo-form-row">
                <label>Mục lục</label>
                <textarea
                  value={form.tableOfContents || ''}
                  placeholder="Liệt kê các chương / mục lục…"
                  onChange={(e) => patchForm({ tableOfContents: e.target.value || null })}
                />
              </div>
            </div>
          </div>

          {/* SEO */}
          <div className="woo-metabox">
            <h2>SEO</h2>
            <div className="woo-metabox-body">
              <div className="woo-form-row">
                <label>Meta title</label>
                <input
                  type="text"
                  value={form.metaTitle ?? ''}
                  onChange={(e) => patchForm({ metaTitle: e.target.value || null })}
                />
              </div>
              <div className="woo-form-row">
                <label>Meta description</label>
                <textarea
                  value={form.metaDescription ?? ''}
                  onChange={(e) => patchForm({ metaDescription: e.target.value || null })}
                />
              </div>
              <div className="woo-form-row">
                <label>Meta keywords</label>
                <input
                  type="text"
                  value={form.metaKeywords ?? ''}
                  onChange={(e) => patchForm({ metaKeywords: e.target.value || null })}
                />
              </div>
            </div>
          </div>
        </div>

        {/* Right column */}
        <div className="woo-editor-right">
          <div className="woo-metabox woo-publishbox">
            <h2>Trạng thái</h2>
            <div className="woo-metabox-body">
              <div className="woo-form-row">
                <label htmlFor="woo-status">Trạng thái</label>
                <select
                  id="woo-status"
                  value={form.status}
                  disabled={form.status === 'archived' || saving}
                  onChange={(e) => patchForm({ status: e.target.value as ProductDetail['status'] })}
                >
                  <option value="draft">Nháp</option>
                  <option value="active">Đã xuất bản</option>
                  {form.status === 'archived' && <option value="archived">Thùng rác</option>}
                </select>
              </div>
              <p className="description">
                {form.publishedAt ? `Đã đăng lúc: ${formatDate(form.publishedAt)}` : 'Chưa đăng.'}
              </p>
              <div className="woo-publish-actions">
                {form.status === 'archived' ? (
                  <>
                    <button
                      type="button"
                      className="button button-primary"
                      disabled={saving}
                      onClick={() => void saveProduct({ draft: true })}
                    >
                      Khôi phục sản phẩm
                    </button>
                    <button
                      type="button"
                      className="button-link-delete"
                      disabled={saving}
                      onClick={() => void deleteFromEditor()}
                    >
                      Xóa vĩnh viễn
                    </button>
                  </>
                ) : (
                  <button
                    type="button"
                    className="button-link-delete"
                    disabled={saving}
                    onClick={() => void saveProduct({ trash: true })}
                  >
                    Chuyển vào thùng rác
                  </button>
                )}
              </div>
            </div>
          </div>

          <div className="woo-metabox">
            <h2>Cửa hàng trực tuyến</h2>
            <div className="woo-metabox-body">
              <div className="woo-form-row">
                <label htmlFor="woo-visibility">Hiển thị</label>
                <select
                  id="woo-visibility"
                  value={form.catalogVisibility}
                  onChange={(e) => patchForm({ catalogVisibility: e.target.value })}
                >
                  <option value="visible">Hiển thị trong cửa hàng và kết quả tìm kiếm</option>
                  <option value="catalog">Chỉ hiển thị trong cửa hàng</option>
                  <option value="search">Chỉ hiển thị trong kết quả tìm kiếm</option>
                  <option value="hidden">Ẩn</option>
                </select>
              </div>
            </div>
          </div>

          <div className="woo-metabox">
            <h2>Tổ chức sản phẩm</h2>
            <div className="woo-metabox-body">
              <p className="woo-field-label">Danh mục</p>
              <ul className="woo-cat-checklist">
                {taxonomies.categories.length === 0 && (
                  <li style={{ padding: '8px 10px', color: '#616161' }}>Chưa có danh mục nào.</li>
                )}
                {taxonomies.categories.map((c) => (
                  <li key={c}>
                    <label>
                      <input
                        type="checkbox"
                        checked={form.categories.includes(c)}
                        onChange={() => patchForm({
                          categories: form.categories.includes(c)
                            ? form.categories.filter((x) => x !== c)
                            : [...form.categories, c],
                        })}
                      />
                      {c}
                    </label>
                  </li>
                ))}
              </ul>
              <div className="woo-form-inline" style={{ marginTop: 10 }}>
                <input
                  type="text"
                  placeholder="Thêm danh mục mới"
                  aria-label="Thêm danh mục mới"
                  value={newCategory}
                  onChange={(e) => setNewCategory(e.target.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addCategory(); } }}
                />
                <button type="button" className="button" onClick={addCategory}>Thêm</button>
              </div>

              <hr className="woo-divider" />

              <p className="woo-field-label">Thẻ</p>
              {form.tags.length > 0 && (
                <div className="woo-tag-chips">
                  {form.tags.map((t) => (
                    <span key={t} className="woo-tag-chip">
                      {t}
                      <button
                        type="button" aria-label={`Xóa thẻ ${t}`}
                        onClick={() => patchForm({ tags: form.tags.filter((x) => x !== t) })}
                      >
                        ×
                      </button>
                    </span>
                  ))}
                </div>
              )}
              <div className="woo-form-inline">
                <input
                  type="text"
                  placeholder="Thêm thẻ, ngăn cách bằng phẩy"
                  aria-label="Thêm thẻ"
                  list="woo-product-tag-suggestions"
                  value={tagInput}
                  onChange={(e) => setTagInput(e.target.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addTag(); } }}
                />
                <button type="button" className="button" onClick={addTag}>Thêm</button>
              </div>
              <datalist id="woo-product-tag-suggestions">
                {taxonomies.tags.map((t) => <option key={t} value={t} />)}
              </datalist>
            </div>
          </div>

          <div className="woo-metabox">
            <h2>Ảnh sản phẩm</h2>
            <div className="woo-metabox-body">
              {form.coverImage ? (
                <div className="woo-img-preview">
                  <img src={form.coverImage} alt={form.title} />
                  <button type="button" className="button-link-delete" onClick={() => patchForm({ coverImage: null })}>
                    Xóa ảnh
                  </button>
                </div>
              ) : (
                <p className="description" style={{ margin: '0 0 8px' }}>Chưa đặt ảnh sản phẩm.</p>
              )}
              <div className="woo-form-row">
                <label htmlFor="woo-cover">URL ảnh</label>
                <input
                  id="woo-cover"
                  type="url"
                  placeholder="https://…"
                  value={form.coverImage ?? ''}
                  onChange={(e) => patchForm({ coverImage: e.target.value || null })}
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ProductsScreen;
