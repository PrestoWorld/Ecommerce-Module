import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Table, Tag } from 'antd';

interface Order {
  id: number;
  business_id: string;
  external_id: string;
  code: string;
  status: string;
  source: string;
  customer_name: string;
  customer_mobile: string;
  total_amount: number;
  payload: string;
  created_at: number;
  updated_at: number;
}

const OrdersScreen: React.FC = () => {
  const { data: orders = [], isLoading } = useQuery({
    queryKey: ['orders'],
    queryFn: async () => {
      const res = await fetch('/api/admin/orders');
      if (!res.ok) throw new Error('Failed to fetch orders');
      return res.json() as Promise<Order[]>;
    },
  });

  const statusColors: Record<string, string> = {
    pending: 'orange',
    confirmed: 'blue',
    shipping: 'purple',
    delivered: 'green',
    cancelled: 'red',
    returned: 'red',
  };

  const columns = [
    { title: 'Mã đơn', dataIndex: 'code', key: 'code' },
    { title: 'Khách hàng', dataIndex: 'customer_name', key: 'customer_name' },
    { title: 'Điện thoại', dataIndex: 'customer_mobile', key: 'customer_mobile' },
    { title: 'Nguồn', dataIndex: 'source', key: 'source' },
    { title: 'Tổng tiền', dataIndex: 'total_amount', key: 'total_amount', render: (v: number) => v.toLocaleString('vi-VN') + ' ₫' },
    { title: 'Trạng thái', dataIndex: 'status', key: 'status', render: (v: string) => (
      <Tag color={statusColors[v] || 'default'}> {v} </Tag>
    )},
    { title: 'Ngày tạo', dataIndex: 'created_at', key: 'created_at', render: (v: number) => new Date(v * 1000).toLocaleString('vi-VN') },
  ];

  return (
    <div style={{ padding: 24 }}>
      <h2>Đơn hàng</h2>
      <Table
        dataSource={orders}
        columns={columns}
        loading={isLoading}
        rowKey="id"
        pagination={{ pageSize: 20 }}
      />
    </div>
  );
};

export default OrdersScreen;