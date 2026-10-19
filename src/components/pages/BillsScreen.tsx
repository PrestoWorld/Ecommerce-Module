import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Table, Tag } from 'antd';

interface Bill {
  id: number;
  business_id: string;
  external_id: string;
  code: string;
  type: number;
  status: number;
  customer_id: number;
  customer_name: string;
  customer_mobile: string;
  total_amount: number;
  note: string;
  created_at: number;
  updated_at: number;
  payload: string;
}

const BillsScreen: React.FC = () => {
  const { data: bills = [], isLoading } = useQuery({
    queryKey: ['bills'],
    queryFn: async () => {
      const res = await fetch('/api/admin/bills');
      if (!res.ok) throw new Error('Failed to fetch bills');
      return res.json() as Promise<Bill[]>;
    },
  });

  const typeLabels: Record<number, string> = { 1: 'Nhập', 2: 'Xuất', 3: 'Chuyển', 4: 'Khác' };
  const statusLabels: Record<number, string> = { 0: 'Đã xóa', 1: 'Mới', 2: 'Đã duyệt', 3: 'Hoàn tất' };

  const columns = [
    { title: 'Mã', dataIndex: 'code', key: 'code' },
    { title: 'Loại', dataIndex: 'type', key: 'type', render: (v: number) => typeLabels[v] || v },
    { title: 'Khách hàng', dataIndex: 'customer_name', key: 'customer_name' },
    { title: 'Điện thoại', dataIndex: 'customer_mobile', key: 'customer_mobile' },
    { title: 'Tổng tiền', dataIndex: 'total_amount', key: 'total_amount', render: (v: number) => v.toLocaleString('vi-VN') + ' ₫' },
    { title: 'Trạng thái', dataIndex: 'status', key: 'status', render: (v: number) => (
      <Tag color={v === 3 ? 'green' : v === 2 ? 'blue' : v === 1 ? 'orange' : 'red'}> {statusLabels[v] || v} </Tag>
    )},
    { title: 'Ghi chú', dataIndex: 'note', key: 'note' },
    { title: 'Ngày tạo', dataIndex: 'created_at', key: 'created_at', render: (v: number) => new Date(v * 1000).toLocaleString('vi-VN') },
  ];

  return (
    <div style={{ padding: 24 }}>
      <h2>Hóa đơn / Phiếu XNK</h2>
      <Table
        dataSource={bills}
        columns={columns}
        loading={isLoading}
        rowKey="id"
        pagination={{ pageSize: 20 }}
      />
    </div>
  );
};

export default BillsScreen;