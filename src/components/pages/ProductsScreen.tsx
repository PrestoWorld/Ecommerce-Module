import React, { useCallback, useEffect, useMemo, useState } from 'react';

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

/* ── Styles (WooCommerce / wp-admin look) ──────────────────────── */

const WOO_CSS = `
.woo-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; color: #2c3338; font-size: 13px; }
.woo-wrap * { box-sizing: border-box; }
.woo-h1 { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 8px 0 16px; }
.woo-h1 h1 { font-size: 23px; font-weight: 400; margin: 0; line-height: 1.3; }
.woo-views { margin: 0 0 8px; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 2px 4px; font-size: 13px; }
.woo-views li a { display: inline-block; padding: 2px 6px; text-decoration: none; color: #2271b1; border-radius: 3px; }
.woo-views li a:hover { color: #0a4b78; background: #f0f6fc; }
.woo-views li a.current { font-weight: 600; color: #1d2327; background: transparent; cursor: default; }
.woo-views .count { opacity: 0.7; margin-left: 2px; }
.woo-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
.woo-table th { text-align: left; font-size: 14px; font-weight: 600; color: #2c3338; padding: 8px 10px; border-bottom: 1px solid #c3c4c7; white-space: nowrap; }
.woo-table td { padding: 10px; border-bottom: 1px solid #f0f0f1; vertical-align: middle; font-size: 13px; }
.woo-table tbody tr:hover { background: #f6f7f7; }
.woo-table .check-column { width: 34px; padding: 8px 0 8px 10px; }
.woo-thumb { width: 40px; height: 40px; border-radius: 3px; background: #f0f0f1; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
.woo-thumb img { width: 100%; height: 100%; object-fit: cover; }
.woo-thumb .dashicons { width: 20px; height: 20px; fill: #8c8f94; }
.woo-title-cell { display: flex; gap: 10px; align-items: flex-start; min-width: 260px; }
.woo-row-title { font-size: 14px; font-weight: 600; color: #2271b1; text-decoration: none; cursor: pointer; line-height: 1.3; display: inline-block; }
.woo-row-title:hover { color: #0a4b78; }
.woo-row-id { color: #8c8f94; font-size: 12px; margin-top: 2px; }
.woo-row-actions { font-size: 12px; color: #8c8f94; margin-top: 4px; visibility: hidden; }
.woo-table tbody tr:hover .woo-row-actions { visibility: visible; }
.woo-row-actions button { background: none; border: 0; padding: 0; color: #2271b1; font-size: 12px; cursor: pointer; font-family: inherit; }
.woo-row-actions button:hover { color: #0a4b78; }
.woo-row-actions .sep { margin: 0 4px; color: #c3c4c7; }
.woo-row-actions button.danger { color: #b32d2e; }
.woo-badge { display: inline-block; padding: 1px 8px 2px; border-radius: 3px; font-size: 12px; font-weight: 500; line-height: 1.6; white-space: nowrap; }
.woo-badge.published, .woo-badge.instock { background: #edfaef; color: #008a20; border: 1px solid #008a2033; }
.woo-badge.draft { background: #fcf9e8; color: #996800; border: 1px solid #99680033; }
.woo-badge.trash { background: #f0f0f1; color: #50575e; border: 1px solid #50575e33; }
.woo-badge.outofstock { background: #fcf0f1; color: #b32d2e; border: 1px solid #b32d2e33; }
.woo-badge.lowstock { background: #fcf9e8; color: #996800; border: 1px solid #99680033; }
.woo-price .sale { color: #b32d2e; font-weight: 600; }
.woo-price .regular { color: #8c8f94; text-decoration: line-through; margin-left: 4px; font-size: 12px; }
.woo-cat-list { max-width: 200px; }
.woo-cat-list button { background: none; border: 0; padding: 0; color: #2271b1; font-size: 13px; cursor: pointer; font-family: inherit; }
.woo-cat-list button:hover { color: #0a4b78; }
.woo-tags { color: #8c8f94; font-size: 12px; max-width: 160px; }
.woo-tablenav { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin: 16px 0 8px; }
.woo-tablenav .bulkactions, .woo-tablenav .actions, .woo-tablenav .search-box { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.woo-tablenav label { font-size: 13px; }
.woo-empty { text-align: center; padding: 48px 16px; color: #8c8f94; }
.woo-empty h2 { font-size: 23px; font-weight: 400; color: #1d2327; margin: 0 0 8px; }
.woo-pagination { display: flex; justify-content: space-between; align-items: center; margin: 16px 0 32px; color: #646970; font-size: 13px; }
.woo-pagination .pages { display: flex; gap: 4px; }
.woo-notice { padding: 10px 14px; border-radius: 4px; margin: 0 0 16px; font-size: 13px; border: 1px solid; }
.woo-notice.success { background: #edfaef; border-color: #008a2033; color: #006d21; }
.woo-notice.error { background: #fcf0f1; border-color: #b32d2e33; color: #8a1f1f; }
.woo-notice.info { background: #f0f6fc; border-color: #2271b133; color: #0a4b78; }

/* Forms */
.woo-wrap input[type=text], .woo-wrap input[type=number], .woo-wrap input[type=url], .woo-wrap input[type=search],
.woo-wrap textarea, .woo-wrap select {
  border: 1px solid #8c8f94; border-radius: 4px; padding: 4px 8px; font-size: 13px; min-height: 30px;
  background: #fff; color: #2c3338; font-family: inherit; width: 100%;
}
.woo-wrap textarea { min-height: 80px; line-height: 1.5; }
.woo-wrap input:focus, .woo-wrap textarea:focus, .woo-wrap select:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none; }
.woo-wrap .button {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  background: #f6f7f7; border: 1px solid #2271b1; border-radius: 3px; color: #2271b1;
  font-size: 13px; font-weight: 500; padding: 0 12px; min-height: 30px; cursor: pointer;
  font-family: inherit; white-space: nowrap; text-decoration: none;
}
.woo-wrap .button:hover { background: #f0f6fc; border-color: #0a4b78; color: #0a4b78; }
.woo-wrap .button-primary { background: #2271b1; border-color: #2271b1; color: #fff; }
.woo-wrap .button-primary:hover { background: #135e96; border-color: #135e96; color: #fff; }
.woo-wrap .button-link-delete { background: none; border: 0; color: #b32d2e; cursor: pointer; font-size: 13px; font-family: inherit; padding: 0; text-decoration: underline; }
.woo-wrap .button-link-delete:hover { color: #8a1f1f; }
.woo-wrap .description { color: #646970; font-size: 12px; margin: 4px 0 0; }
.woo-form-row { margin-bottom: 14px; }
.woo-form-row label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px; }
.woo-form-inline { display: flex; gap: 8px; align-items: center; }
.woo-form-inline > * { width: auto; }
.woo-required { color: #b32d2e; }

/* Editor layout */
.woo-editor { display: flex; gap: 16px; align-items: flex-start; max-width: 1400px; padding-bottom: 40px; flex-wrap: wrap; }
.woo-editor-left { flex: 1 1 640px; min-width: 0; }
.woo-editor-right { flex: 0 0 300px; display: flex; flex-direction: column; gap: 16px; }
.woo-metabox { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px; }
.woo-metabox > h2, .woo-metabox > h3 { font-size: 14px; font-weight: 600; margin: 0; padding: 10px 14px; border-bottom: 1px solid #c3c4c7; color: #1d2327; display: flex; justify-content: space-between; align-items: center; }
.woo-metabox-body { padding: 14px; }
.woo-title-input { font-size: 23px !important; font-weight: 400; padding: 8px 12px !important; min-height: 44px !important; }
.woo-slug-line { font-size: 13px; color: #646970; margin: 8px 2px 16px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.woo-slug-line .slug { color: #2271b1; background: #f0f6fc; padding: 2px 6px; border-radius: 3px; }
.woo-main-editor textarea { min-height: 320px; padding: 12px; font-size: 14px; border-radius: 4px 4px 0 0; }
.woo-short-desc textarea { min-height: 110px; }
.woo-publishbox .woo-form-row:last-child { margin-bottom: 0; }
.woo-publish-actions { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
.woo-publish-actions .button-primary { width: 100%; min-height: 34px; font-size: 14px; }
.woo-cat-checklist { list-style: none; margin: 0; padding: 0; max-height: 180px; overflow-y: auto; border: 1px solid #c3c4c7; border-radius: 4px; }
.woo-cat-checklist li { border-bottom: 1px solid #f0f0f1; }
.woo-cat-checklist li:last-child { border-bottom: 0; }
.woo-cat-checklist label { display: flex; gap: 8px; align-items: center; padding: 6px 10px; cursor: pointer; font-weight: 400 !important; margin: 0 !important; }
.woo-cat-checklist label:hover { background: #f6f7f7; }
.woo-tag-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.woo-tag-chip { display: inline-flex; align-items: center; gap: 4px; background: #f0f6fc; border: 1px solid #2271b133; color: #2271b1; border-radius: 3px; padding: 2px 6px; font-size: 12px; }
.woo-tag-chip button { background: none; border: 0; color: #2271b1; cursor: pointer; font-size: 14px; line-height: 1; padding: 0; }
.woo-img-preview { margin: 8px 0; display: flex; align-items: center; gap: 10px; }
.woo-img-preview img { width: 80px; height: 80px; object-fit: cover; border: 1px solid #c3c4c7; border-radius: 3px; background: #f0f0f1; }

/* Product data metabox */
.woo-product-data { display: flex; min-height: 340px; }
.woo-pd-tabs { flex: 0 0 160px; background: #f6f7f7; border-right: 1px solid #c3c4c7; border-radius: 4px 0 0 4px; padding: 8px 0; }
.woo-pd-tab { display: block; width: 100%; text-align: left; background: none; border: 0; border-left: 3px solid transparent; padding: 8px 14px; font-size: 13px; color: #2271b1; cursor: pointer; font-family: inherit; }
.woo-pd-tab:hover { color: #0a4b78; background: #f0f6fc; }
.woo-pd-tab.active { background: #fff; border-left-color: #2271b1; color: #1d2327; font-weight: 600; }
.woo-pd-panel { flex: 1; padding: 16px; min-width: 0; }
.woo-pd-type { font-weight: 600; margin: 0 0 12px; font-size: 13px; }
.woo-price-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 640px) {
  .woo-editor-right { flex: 1 1 100%; }
  .woo-price-grid { grid-template-columns: 1fr; }
  .woo-product-data { flex-direction: column; }
  .woo-pd-tabs { flex: none; display: flex; overflow-x: auto; border-right: 0; border-bottom: 1px solid #c3c4c7; border-radius: 4px 4px 0 0; padding: 0; }
  .woo-pd-tab { width: auto; white-space: nowrap; border-left: 0; border-bottom: 3px solid transparent; }
  .woo-pd-tab.active { border-bottom-color: #2271b1; }
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
  const [pdTab, setPdTab] = useState('general');
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
      setMode('edit');
      return;
    }
    try {
      const detail = await api<ProductDetail>(`${API}/${id}`);
      setForm(detail);
      setEditingId(id);
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

  const saveProduct = async (opts: { trash?: boolean; draft?: boolean; publish?: boolean } = {}) => {
    if (!form) return;
    if (!form.title.trim()) {
      setNotice({ kind: 'error', text: 'Vui lòng nhập tiêu đề sản phẩm' });
      return;
    }
    setSaving(true);
    try {
      const v = form.variants[0];
      const payload = {
        title: form.title.trim(),
        slug: form.slug || undefined,
        status: opts.trash ? 'archived' : opts.draft ? 'draft' : opts.publish ? 'active' : form.status,
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

      if (editingId) {
        await api(`${API}/${editingId}`, { method: 'PUT', body: JSON.stringify(payload) });
      } else {
        await api(`${API}`, { method: 'POST', body: JSON.stringify(payload) });
      }

      await loadTaxonomies();
      setMode('list');
      setForm(null);
      setEditingId(null);
      setNotice({
        kind: 'success',
        text: opts.trash
          ? 'Đã chuyển sản phẩm vào thùng rác'
          : opts.draft
            ? 'Đã lưu bản nháp'
            : opts.publish && !editingId
              ? 'Đã xuất bản sản phẩm'
              : 'Đã cập nhật sản phẩm',
      });
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
            Thêm sản phẩm mới
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

        <div className="woo-tablenav">
          <div className="bulkactions">
            <label>
              Hành động mass{' '}
              <select value={bulkAction} onChange={(e) => setBulkAction(e.target.value)} style={{ width: 'auto' }}>
                <option value="">Hành động mass</option>
                <option value="trash">Chuyển vào thùng rác</option>
                <option value="restore">Khôi phục từ thùng rác</option>
                <option value="publish">Đăng</option>
                <option value="draft">Chuyển về bản nháp</option>
                <option value="delete">Xóa vĩnh viễn</option>
              </select>
            </label>
            <button type="button" className="button" onClick={() => void applyBulk()}>Áp dụng</button>
          </div>

          <div className="actions">
            <select value={categoryFilter} onChange={(e) => setCategoryFilter(e.target.value)} style={{ width: 'auto' }}>
              <option value="">Tất cả danh mục</option>
              {taxonomies.categories.map((c) => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>
            <select value={orderby} onChange={(e) => setOrderby(e.target.value)} style={{ width: 'auto' }}>
              <option value="date">Mới nhất</option>
              <option value="title">Tên (A → Z)</option>
              <option value="sku">SKU</option>
            </select>
            <button type="button" className="button" onClick={() => void loadList()}>Lọc</button>
          </div>

          <div className="search-box">
            <form
              onSubmit={(e) => { e.preventDefault(); setList((s) => ({ ...s, page: 1 })); void loadList(); }}
              style={{ display: 'flex', gap: 6, alignItems: 'center' }}
            >
              <input
                type="search"
                value={search}
                placeholder="Tìm sản phẩm"
                onChange={(e) => setSearch(e.target.value)}
                style={{ width: 200 }}
              />
              <button type="submit" className="button">Tìm sản phẩm</button>
            </form>
          </div>
        </div>

        {list.items.length === 0 && !loading ? (
          <div className="woo-metabox">
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
                Thêm sản phẩm mới
              </button>
            </div>
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
                <th>SKU</th>
                <th>Tồn kho</th>
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
                      <div style={{ marginLeft: 'auto' }}>
                        <span className={`woo-badge ${item.status === 'active' ? 'published' : item.status}`}>
                          {STATUS_LABELS[item.status] ?? item.status}
                        </span>
                      </div>
                    </div>
                  </td>
                  <td>{item.sku || '—'}</td>
                  <td>
                    <span className={`woo-badge ${item.stock.status}`}>
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
                  <td>
                    {formatDate(view === 'published' ? item.publishedAt : item.createdAt)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        <div className="woo-pagination">
          <span>{loading ? 'Đang tải…' : `${list.total} sản phẩm`}</span>
          {list.total > list.perPage && (
            <div className="pages">
              <button
                type="button" className="button" disabled={list.page <= 1}
                onClick={() => changePage(list.page - 1)}
              >
                « Trước
              </button>
              <span className="button" style={{ cursor: 'default' }}>
                {list.page} / {Math.max(1, Math.ceil(list.total / list.perPage))}
              </span>
              <button
                type="button" className="button" disabled={list.page >= Math.ceil(list.total / list.perPage)}
                onClick={() => changePage(list.page + 1)}
              >
                Sau »
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

  const pdTabs: { id: string; label: string }[] = [
    { id: 'general', label: 'Chung' },
    { id: 'inventory', label: 'Kho' },
    { id: 'shipping', label: 'Vận chuyển' },
    { id: 'advanced', label: 'Nâng cao' },
  ];

  return (
    <div className="woo-wrap">
      <div className="woo-h1">
        <h1>{editingId ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm mới'}</h1>
        <button type="button" className="button" onClick={() => { setMode('list'); setForm(null); }}>
          « Quay lại danh sách
        </button>
      </div>

      {notice && <div className={`woo-notice ${notice.kind}`}>{notice.text}</div>}

      <div className="woo-editor">
        <div className="woo-editor-left">
          <div className="woo-metabox" style={{ marginBottom: 16 }}>
            <div className="woo-metabox-body">
              <input
                type="text"
                className="woo-title-input"
                placeholder="Nhập tiêu đề sản phẩm ở đây"
                value={form.title}
                onChange={(e) => patchForm({ title: e.target.value })}
              />
              <div className="woo-slug-line">
                Đường dẫn tĩnh:
                {slugEditing ? (
                  <>
                    <input
                      type="text"
                      value={form.slug}
                      onChange={(e) => patchForm({ slug: e.target.value })}
                      style={{ width: 260 }}
                      onBlur={() => setSlugEditing(false)}
                      autoFocus
                    />
                  </>
                ) : (
                  <>
                    <span className="slug">/{form.slug || 'san-pham'}</span>
                    <button type="button" className="button" style={{ minHeight: 24, fontSize: 12 }} onClick={() => setSlugEditing(true)}>
                      Sửa
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>

          <div className="woo-metabox woo-main-editor" style={{ marginBottom: 16 }}>
            <div className="woo-metabox-body" style={{ padding: 0 }}>
              <textarea
                value={form.description || ''}
                placeholder="Mô tả chi tiết sản phẩm…"
                onChange={(e) => patchForm({ description: e.target.value })}
                style={{ border: 0, borderRadius: '4px 4px 0 0', minHeight: 320 }}
              />
            </div>
          </div>

          <div className="woo-metabox woo-short-desc" style={{ marginBottom: 16 }}>
            <h2>Mô tả ngắn</h2>
            <div className="woo-metabox-body">
              <textarea
                value={form.shortDescription || ''}
                placeholder="Mô tả ngắn gọn hiển thị gần tên sản phẩm…"
                onChange={(e) => patchForm({ shortDescription: e.target.value })}
              />
            </div>
          </div>

          {/* Product data */}
          <div className="woo-metabox" style={{ marginBottom: 16 }}>
            <h2>Dữ liệu sản phẩm</h2>
            <div className="woo-product-data">
              <div className="woo-pd-tabs">
                {pdTabs.map((t) => (
                  <button
                    key={t.id}
                    type="button"
                    className={`woo-pd-tab ${pdTab === t.id ? 'active' : ''}`}
                    onClick={() => setPdTab(t.id)}
                  >
                    {t.label}
                  </button>
                ))}
              </div>
              <div className="woo-pd-panel">
                <p className="woo-pd-type">Loại sản phẩm: <strong>Đơn giản</strong></p>

                {pdTab === 'general' && (
                  <div className="woo-price-grid">
                    <div className="woo-form-row">
                      <label>Giá thường (₫) <span className="woo-required">*</span></label>
                      <input
                        type="number" min={0} step="1000"
                        value={v?.regularPrice ?? ''}
                        onChange={(e) => patchVariant({ regularPrice: e.target.value === '' ? null : Number(e.target.value) })}
                      />
                    </div>
                    <div className="woo-form-row">
                      <label>Giá khuyến mãi (₫)</label>
                      <input
                        type="number" min={0} step="1000"
                        value={v?.salePrice ?? ''}
                        onChange={(e) => patchVariant({ salePrice: e.target.value === '' ? null : Number(e.target.value) })}
                      />
                      <p className="description">Để trống nếu không giảm giá.</p>
                    </div>
                  </div>
                )}

                {pdTab === 'inventory' && (
                  <>
                    <div className="woo-price-grid">
                      <div className="woo-form-row">
                        <label>SKU (Mã sản phẩm)</label>
                        <input type="text" value={v?.sku ?? ''} onChange={(e) => patchVariant({ sku: e.target.value })} />
                      </div>
                      <div className="woo-form-row">
                        <label>Mã vạch (Barcode)</label>
                        <input type="text" value={v?.barcode ?? ''} onChange={(e) => patchVariant({ barcode: e.target.value || null })} />
                      </div>
                      <div className="woo-form-row">
                        <label>Số lượng tồn</label>
                        <input
                          type="number" min={0}
                          value={v?.stockOnHand ?? 0}
                          onChange={(e) => patchVariant({ stockOnHand: Math.max(0, Number(e.target.value) || 0) })}
                        />
                      </div>
                      <div className="woo-form-row">
                        <label>Ngưỡng cảnh báo tồn kho</label>
                        <input
                          type="number" min={0}
                          value={v?.reorderPoint ?? 0}
                          onChange={(e) => patchVariant({ reorderPoint: Math.max(0, Number(e.target.value) || 0) })}
                        />
                        <p className="description">Tồn kho ≤ ngưỡng sẽ hiển thị “Sắp hết hàng”.</p>
                      </div>
                      <div className="woo-form-row">
                        <label>Giá vốn (₫)</label>
                        <input
                          type="number" min={0} step="1000"
                          value={v?.costPrice ?? 0}
                          onChange={(e) => patchVariant({ costPrice: Math.max(0, Number(e.target.value) || 0) })}
                        />
                      </div>
                      <div className="woo-form-row">
                        <label>Phiên bản / ấn bản</label>
                        <input
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
                  </>
                )}

                {pdTab === 'shipping' && (
                  <div className="woo-price-grid">
                    <div className="woo-form-row">
                      <label>Cân nặng (gram)</label>
                      <input
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
                      <label>Kích thước</label>
                      <input
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
                )}

                {pdTab === 'advanced' && (
                  <>
                    <div className="woo-form-row">
                      <label>Ghi chú mua hàng</label>
                      <textarea
                        value={form.purchaseNote || ''}
                        placeholder="Ghi chú hiển thị sau khi khách hàng mua thành công…"
                        onChange={(e) => patchForm({ purchaseNote: e.target.value || null })}
                      />
                    </div>
                    <div className="woo-form-row" style={{ maxWidth: 160 }}>
                      <label>Thứ tự menu</label>
                      <input
                        type="number" min={0}
                        value={form.menuOrder}
                        onChange={(e) => patchForm({ menuOrder: Math.max(0, Number(e.target.value) || 0) })}
                      />
                    </div>
                  </>
                )}
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
            <h2>{form.status === 'archived' ? 'Thùng rác' : form.status === 'active' ? 'Cập nhật' : 'Đăng'}</h2>
            <div className="woo-metabox-body">
              <div className="woo-form-row">
                <label>Trạng thái</label>
                <span className="description">
                  {form.status === 'active' ? 'Đã xuất bản' : form.status === 'archived' ? 'Thùng rác' : 'Bản nháp'}
                </span>
              </div>
              <div className="woo-form-row">
                <label>Hiển thị</label>
                <select
                  value={form.catalogVisibility}
                  onChange={(e) => patchForm({ catalogVisibility: e.target.value })}
                >
                  <option value="visible">Hiển thị trong cửa hàng và kết quả tìm kiếm</option>
                  <option value="catalog">Chỉ hiển thị trong cửa hàng</option>
                  <option value="search">Chỉ hiển thị trong kết quả tìm kiếm</option>
                  <option value="hidden">Ẩn</option>
                </select>
              </div>
              {form.publishedAt ? (
                <p className="description">Đã đăng lúc: {formatDate(form.publishedAt)}</p>
              ) : (
                <p className="description">Chưa đăng.</p>
              )}
              <div className="woo-publish-actions">
                <button
                  type="button"
                  className="button button-primary"
                  disabled={saving || form.status === 'archived'}
                  onClick={() => void saveProduct(form.status === 'active' ? {} : { publish: true })}
                >
                  {saving ? 'Đang lưu…' : form.status === 'active' ? 'Cập nhật' : 'Đăng'}
                </button>
                {form.status === 'draft' && (
                  <button
                    type="button"
                    className="button-link-delete"
                    disabled={saving}
                    onClick={() => void saveProduct({ draft: true })}
                  >
                    Lưu bản nháp
                  </button>
                )}
                {form.status === 'active' && (
                  <button
                    type="button"
                    className="button-link-delete"
                    disabled={saving}
                    onClick={() => void saveProduct({ draft: true })}
                  >
                    Chuyển về nháp
                  </button>
                )}
                {form.status !== 'archived' && (
                  <button
                    type="button"
                    className="button-link-delete"
                    disabled={saving}
                    onClick={() => void saveProduct({ trash: true })}
                  >
                    Chuyển vào thùng rác
                  </button>
                )}
                {form.status === 'archived' && (
                  <button
                    type="button"
                    className="button"
                    disabled={saving}
                    onClick={() => void saveProduct({ draft: true })}
                  >
                    Khôi phục (bản nháp)
                  </button>
                )}
              </div>
            </div>
          </div>

          <div className="woo-metabox">
            <h2>Danh mục sản phẩm</h2>
            <div className="woo-metabox-body">
              <ul className="woo-cat-checklist">
                {taxonomies.categories.length === 0 && (
                  <li style={{ padding: '8px 10px', color: '#8c8f94' }}>Chưa có danh mục nào.</li>
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
                  value={newCategory}
                  onChange={(e) => setNewCategory(e.target.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addCategory(); } }}
                />
                <button type="button" className="button" onClick={addCategory}>Thêm</button>
              </div>
            </div>
          </div>

          <div className="woo-metabox">
            <h2>Thẻ sản phẩm</h2>
            <div className="woo-metabox-body">
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
                <label>URL ảnh</label>
                <input
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
