import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Table, Tag, Tabs } from 'antd';

interface EcomShop {
  id: number;
  business_id: string;
  shop_id: string;
  name: string;
  channel: string;
  status: number;
  created_at: number;
  updated_at: number;
  payload: string;
}

interface EcomReturn {
  id: number;
  business_id: string;
  external_id: string;
  order_id: number;
  status: number;
  customer_name: string;
  customer_mobile: string;
  total_amount: number;
  note: string;
  created_at: number;
  updated_at: number;
  payload: string;
}

const EcomScreen: React.FC = () => {
  const { data: shops = [], isLoading: loadingShops } = useQuery({
    queryKey: ['ecom_shops'],
    queryFn: async () => {
      const res = await fetch('/api/admin/ecom/shops');
      if (!res.ok) throw new Error('Failed to fetch ecom shops');
      return res.json() as Promise<EcomShop[]>;
    },
  });

  const { data: returns = [], isLoading: loadingReturns } = useQuery({
    queryKey: ['ecom_returns'],
    queryFn: async () => {
      const res = await fetch('/api/admin/ecom/returns');
      if (!res.ok) throw new Error('Failed to fetch ecom returns');
      return res.json() as Promise<EcomReturn[]>;
    },
  });

  const shopColumns = [
    { title: 'Shop ID', dataIndex: 'shop_id', key: 'shop_id' },
    { title: 'Tên gian hàng', dataIndex: 'name', key: 'name' },
    { title: 'Sàn', dataIndex: 'channel', key: 'channel' },
    { title: 'Trạng thái', dataIndex: 'status', key: 'status', render: (v: number) => (
      <Tag color={v === 1 ? 'green' : 'red'}> {v === 1 ? 'Hoạt động' : 'Khóa'} </Tag>
    )},
    { title: 'Ngày kết nối', dataIndex: 'created_at', key: 'created_at', render: (v: number) => new Date(v * 1000).toLocaleString('vi-VN') },
  ];

  const returnColumns = [
    { title: 'Mã đơn', dataIndex: 'external_id', key: 'external_id' },
    { title: 'Đơn hàng ID', dataIndex: 'order_id', key: 'order_id' },
    { title: 'Khách hàng', dataIndex: 'customer_name', key: 'customer_name' },
    { title: 'Điện thoại', dataIndex: 'customer_mobile', key: 'customer_mobile' },
    { title: 'Tổng tiền', dataIndex: 'total_amount', key: 'total_amount', render: (v: number) => v.toLocaleString('vi-VN') + ' ₫' },
    { title: 'Trạng thái', dataIndex: 'status', key: 'status', render: (v: number) => (
      <Tag color={v === 1 ? 'green' : v === 2 ? 'blue' : 'red'}> {v === 1 ? 'Mới' : v === 2 ? 'Đang xử lý' : 'Hoàn tất'} </Tag>
    )},
    { title: 'Ghi chú', dataIndex: 'note', key: 'note' },
    { title: 'Ngày tạo', dataIndex: 'created_at', key: 'created_at', render: (v: number) => new Date(v * 1000).toLocaleString('vi-VN') },
  ];

  return (
    <div style={{ padding: 24 }}>
      <h2>TMĐT / Gian hàng / Đơn trả</h2>
      <Tabs defaultActiveKey="shops">
        <Tabs.TabPane tab="Gian hàng TMĐT" key="shops">
          <Table
            dataSource={shops}
            columns={shopColumns}
            loading={loadingShops}
            rowKey="id"
            pagination={{ pageSize: 20 }}
          />
        </Tabs.TabPane>
        <Tabs.TabPane tab="Đơn trả hàng" key="returns">
          <Table
            dataSource={returns}
            columns={returnColumns}
            loading={loadingReturns}
            rowKey="id"
            pagination={{ pageSize: 20 }}
          />
        </Tabs.TabPane>
      </Tabs>
    </div>
  );
};

export default EcomScreen;