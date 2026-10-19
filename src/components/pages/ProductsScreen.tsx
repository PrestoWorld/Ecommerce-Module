import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Table, Tag, Button, Modal, Form, Input, Select, InputNumber, message } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';

interface Product {
  id: number;
  business_id: string;
  external_id: string;
  sku: string;
  name: string;
  price: number;
  stock: number;
  payload: string;
  created_at: number;
  updated_at: number;
}

const ProductsScreen: React.FC = () => {
  const { data: products = [], isLoading } = useQuery({
    queryKey: ['products'],
    queryFn: async () => {
      const res = await fetch('/api/admin/products');
      if (!res.ok) throw new Error('Failed to fetch products');
      return res.json() as Promise<Product[]>;
    },
  });

  const columns = [
    { title: 'Mã', dataIndex: 'sku', key: 'sku' },
    { title: 'Tên sản phẩm', dataIndex: 'name', key: 'name' },
    { title: 'Giá', dataIndex: 'price', key: 'price', render: (v: number) => v.toLocaleString('vi-VN') + ' ₫' },
    { title: 'Tồn kho', dataIndex: 'stock', key: 'stock' },
    { title: 'External ID', dataIndex: 'external_id', key: 'external_id' },
  ];

  return (
    <div style={{ padding: 24 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 16 }}>
        <h2>Sản phẩm</h2>
        <Button type="primary" icon={<PlusOutlined />}>Thêm sản phẩm</Button>
      </div>
      <Table
        dataSource={products}
        columns={columns}
        loading={isLoading}
        rowKey="id"
        pagination={{ pageSize: 20 }}
      />
    </div>
  );
};

export default ProductsScreen;