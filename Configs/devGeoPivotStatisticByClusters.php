<?php

return [
  'dataUrl' => '/report/PhoneStatsByClustersReportHandler.json',
  'connection' => '',
  'className' => 'App\\ViewModels\\DevPhoneInfoGeo',
  'columns' =>
  [
    'region' =>
    [
      'id' => 'region',
      'name' => 'Регион',
      'width' => 10,
      'sortable' => true,
      'filterable' => true,
    ],
    'city' =>
    [
      'id' => 'city',
      'name' => 'Город',
      'width' => 10,
      'sortable' => true,
      'filterable' => true,
    ],
    'office' =>
    [
      'id' => 'office',
      'name' => 'Офис',
      'width' => 15,
      'sortable' => true,
      'filterable' => true,
    ],
    'people' =>
    [
      'id' => 'people',
      'name' => 'Сотр.',
      'width' => '60px',
      'sortable' => false,
      'filterable' => false,
    ],
    'phoneAmount' =>
    [
      'id' => 'phone-count',
      'name' => 'кол-во тел.',
      'width' => '60px',
      'sortable' => false,
      'filterable' => false,
    ],
    'byPublishIp' =>
    [
      'id' => 'pub',
      'name' => 'Оборудование',
      'width' => 65,
      'sortable' => false,
      'filterable' => false,
    ],
    'byPublishIpActive' =>
    [
      'id' => '',
      'name' => '',
      'width' => 0,
      'sortable' => false,
      'filterable' => false,
    ],
    'lotusId' =>
    [
      'id' => 'lot_id',
      'name' => 'ID',
      'width' => '50px',
      'sortable' => false,
      'filterable' => false,
    ],
  ],
  'calculated' =>
  [
    'phoneAmount' =>
    [
      'column' => 'appliance_id',
      'method' => 'count',
    ],
  ],
  'aliases' =>
  [
  ],
  'extraColumns' =>
  [
    0 => 'people',
  ],
  'sortOrderSets' =>
  [
    'region' =>
    [
      'region' => '',
      'city' => '',
      'office' => '',
    ],
    'city' =>
    [
      'city' => '',
      'office' => '',
    ],
  ],
  'sortBy' =>
  [
    'region' => '',
    'city' => '',
    'office' => '',
  ],
  'preFilter' =>
  [
    'appType' =>
    [
      'eq' =>
      [
        0 => 'phone',
      ],
    ],
  ],
  'pagination' =>
  [
    'rowsOnPageList' =>
    [
      0 => 10,
      1 => 50,
      2 => 100,
      3 => 200,
      4 => 'все',
    ],
  ],
  'cssStyles' =>
  [
    'header' =>
    [
      'table' =>
      [
        0 => 'bg-primary',
        1 => 'table-bordered',
        2 => 'table-header-rotated',
      ],
    ],
    'body' =>
    [
      'table' =>
      [
        0 => 'table',
        1 => 'cell-bordered',
        2 => 'cust-table-striped',
      ],
    ],
    'footer' =>
    [
      'table' =>
      [
      ],
    ],
  ],
  'sizes' =>
  [
    'width' => 100,
    'height' => '',
  ],
  'pivot' =>
  [
    'byPublishIp' =>
    [
      'column' => 'publisherIp',
      'display' => true,
      'preFilter' =>
      [
        'appType' =>
        [
          'eq' =>
          [
            0 => 'phone',
          ],
        ],
      ],
      'selectPivotItemsBy' =>
      [
        0 => 'lotusId',
      ],
      'sortBy' =>
      [
        'publisherIp' => 'desc',
      ],
      'itemWidth' => '65px',
    ],
    'byPublishIpActive' =>
    [
      'column' => 'publisherIp',
      'display' => false,
      'preFilter' =>
      [
        'appType' =>
        [
          'eq' =>
          [
            0 => 'phone',
          ],
        ],
        'appAge' =>
        [
          'lt' =>
          [
            0 => 73,
          ],
        ],
      ],
      'selectPivotItemsBy' =>
      [
        0 => 'lotusId',
      ],
      'sortBy' =>
      [
      ],
      'itemWidth' => 0,
    ],
  ],
];