<?php

return [
  'dataUrl' => '/test/devicesPivotTable.json',
  'connection' => '',
  'className' => 'App\\ViewModels\\DevGeoPeople_1',
  'columns' =>
  [
    'lotusId' =>
    [
      'id' => 'lot_id',
      'name' => 'Lotus ID',
      'width' => 12,
      'sortable' => true,
      'filterable' => true,
    ],
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
    'phoneAmount' =>
    [
      'id' => 'phone-count',
      'name' => 'кол-во тел.',
      'width' => '60px',
      'sortable' => false,
      'filterable' => false,
    ],
    'plTitle' =>
    [
      'id' => 'pl',
      'name' => 'Оборудование',
      'width' => 65,
      'sortable' => false,
      'filterable' => false,
    ],
    'plTitleActive' =>
    [
      'id' => '',
      'name' => '',
      'width' => 0,
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
  ],
  'sortOrderSets' =>
  [
    'lotusId' =>
    [
      'lotusId' => '',
      'region' => '',
      'city' => '',
      'office' => '',
    ],
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
    'lotusId' => '',
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
    'plTitle' =>
    [
      'column' => 'platformTitle',
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
        'platformTitle' => 'desc',
      ],
      'itemWidth' => '65px',
    ],
    'plTitleActive' =>
    [
      'column' => 'platformTitle',
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
            0 => 300,
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