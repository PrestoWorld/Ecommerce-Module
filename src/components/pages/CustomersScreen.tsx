import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Table, Tag } from 'antd';

interface Customer {
  id: number;
  business_id: string;
  external_id: string;
  code: string;
  name: string;
  mobile: string;
  email: string;
  payload: string;
  created_at: number;
  updated_at: number;
}

const CustomersScreen: React.FC = () => {
  const { data: customers = [], isLoading } = useQuery({
    queryKey: ['customers'],
    queryFn: async () => {
      const res = await fetch('/api/admin/customers');
      if (!res.ok) throw new Error('Failed to fetch customers');
      return res.json() as Promise<Customer[]>;
    },
  });

  const columns = [
    { title: 'Mã', dataIndex: 'code', key: 'code' },
    { title: 'Tên khách hàng', dataIndex: 'name', key: 'name' },
    { title: 'Điện thoại', dataIndex: 'mobile', key: 'mobile' },
    { title: 'Email', dataIndex: 'email', key: 'email' },
    { title: 'External ID', dataIndex: 'external_id', key: 'external_id' },
    { title: 'Ngày tạo', dataIndex: 'created_at', key: 'created_at', render: (v: number) => new Date(v * 1000).toLocaleString('vi-VN') },
  ];

  return (
    <div style={{ padding: 24 }}>
      <h2>Khách hàng</h2>
      <Table
        dataSource={customers}
        columns={columns}
        loading={isLoading}
        rowKey="id"
        pagination={{ pageSize: 20 }}
      />
    </div>
  );
};

export default CustomersScreen;