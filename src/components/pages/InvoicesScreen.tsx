import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Table, Tag } from 'antd';

interface Invoice {
  id: number;
  business_id: string;
  external_id: string;
  depot_id: number;
  reference_item_id: string;
  tax_type: number;
  tax_invoice_number: string;
  tax_status: number;
  tax_status_name: string;
  error_code: string;
  error_message: string;
  buyer_name: string;
  buyer_mobile: string;
  buyer_address: string;
  buyer_tax_code: string;
  buyer_email: string;
  buyer_company: string;
  total_amount: number;
  invoice_number: string;
  invoice_date: string;
  created_at: number;
  payload: string;
}

const InvoicesScreen: React.FC = () => {
  const { data: invoices = [], isLoading } = useQuery({
    queryKey: ['invoices'],
    queryFn: async () => {
      const res = await fetch('/api/admin/invoices');
      if (!res.ok) throw new Error('Failed to fetch invoices');
      return res.json() as Promise<Invoice[]>;
    },
  });

  const typeLabels: Record<number, string> = { 1: 'Nháp', 2: 'Đã phát hành', 3: 'Điều chỉnh', 4: 'Thay thế' };
  const statusColors: Record<number, string> = { 0: 'red', 1: 'orange', 2: 'green', 3: 'blue', 4: 'purple' };

  const columns = [
    { title: 'Số hóa đơn', dataIndex: 'invoice_number', key: 'invoice_number' },
    { title: 'Ngày phát hành', dataIndex: 'invoice_date', key: 'invoice_date' },
    { title: 'Loại', dataIndex: 'tax_type', key: 'tax_type', render: (v: number) => typeLabels[v] || v },
    { title: 'Trạng thái', dataIndex: 'tax_status_name', key: 'tax_status_name', render: (v: string) => (
      <Tag color={statusColors[0] || 'default'}> {v} </Tag>
    )},
    { title: 'Người mua', dataIndex: 'buyer_name', key: 'buyer_name' },
    { title: 'Điện thoại', dataIndex: 'buyer_mobile', key: 'buyer_mobile' },
    { title: 'Mã số thuế', dataIndex: 'buyer_tax_code', key: 'buyer_tax_code' },
    { title: 'Email', dataIndex: 'buyer_email', key: 'buyer_email' },
    { title: 'Công ty', dataIndex: 'buyer_company', key: 'buyer_company' },
    { title: 'Tổng tiền', dataIndex: 'total_amount', key: 'total_amount', render: (v: number) => v.toLocaleString('vi-VN') + ' ₫' },
    { title: 'Số HĐ cơ quan', dataIndex: 'tax_invoice_number', key: 'tax_invoice_number' },
    { title: 'Lỗi', dataIndex: 'error_message', key: 'error_message' },
    { title: 'Ngày tạo', dataIndex: 'created_at', key: 'created_at', render: (v: number) => new Date(v * 1000).toLocaleString('vi-VN') },
  ];

  return (
    <div style={{ padding: 24 }}>
      <h2>Hóa đơn điện tử</h2>
      <Table
        dataSource={invoices}
        columns={columns}
        loading={isLoading}
        rowKey="id"
        pagination={{ pageSize: 20 }}
      />
    </div>
  );
};

export default InvoicesScreen;