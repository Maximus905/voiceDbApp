<?php

return [
  'dataUrl' => '/report/PhoneStatsByClustersReportHandler.json',
  'connection' => '',
  'className' => 'App\\ViewModels\\DevPhoneInfoGeoMat',
  'columns' =>
  [
    'textField' =>
    [
      'id' => 'txt_field',
      'name' => 'ИТОГО:',
      'width' => 35,
      'sortable' => false,
      'filterable' => false,
      'visible' => true,
      'classes' =>
      [
      ],
    ],
    'appType' =>
    [
      'id' => 'app_type',
      'name' => 'appType',
      'width' => 10,
      'sortable' => false,
      'filterable' => false,
      'visible' => false,
      'classes' =>
      [
      ],
    ],
    'employees' =>
    [
      'id' => 'people-v',
      'name' => 'Сотрудников',
      'width' => '60px',
      'sortable' => false,
      'filterable' => false,
      'visible' => true,
      'classes' =>
      [
      ],
    ],
    'phoneAmount' =>
    [
      'id' => 'phone-count',
      'name' => 'кол-во тел.',
      'width' => '60px',
      'sortable' => false,
      'filterable' => false,
      'visible' => true,
      'classes' =>
      [
      ],
    ],
    'HWActive' =>
    [
      'id' => 'hw-active-v',
      'name' => 'HW Phones<br>(актив.)',
      'width' => '60px',
      'sortable' => false,
      'filterable' => false,
      'visible' => true,
      'classes' =>
      [
        0 => 'class_1',
        1 => 'class_2',
      ],
    ],
    'notHWActive' =>
    [
      'id' => 'not-hw-active-v',
      'name' => 'virtual & analog<br>Phones(актив.)',
      'width' => '60px',
      'sortable' => false,
      'filterable' => false,
      'visible' => true,
      'classes' =>
      [
        0 => 'class_1',
        1 => 'class_2',
      ],
    ],
    'byPublishIp' =>
    [
      'id' => 'pub',
      'name' => 'Оборудование',
      'width' => 65,
      'sortable' => false,
      'filterable' => false,
      'visible' => true,
      'classes' =>
      [
      ],
    ],
    'byPublishIpActive' =>
    [
      'id' => 'pub',
      'name' => 'Оборудование',
      'width' => 0,
      'sortable' => false,
      'filterable' => false,
      'visible' => false,
      'classes' =>
      [
      ],
    ],
    'byPublishIpActiveHW' =>
    [
      'id' => 'pub-active-hw',
      'name' => 'Оборудование',
      'width' => 0,
      'sortable' => false,
      'filterable' => false,
      'visible' => false,
      'classes' =>
      [
      ],
    ],
  ],
  'calculated' =>
  [
    'phoneAmount' =>
    [
      'column' => 'appliance_id',
      'method' => 'count',
      'preFilter' =>
      [
      ],
      'selectBy' =>
      [
      ],
    ],
    'HWActive' =>
    [
      'column' => 'appType',
      'method' => 'count',
      'preFilter' =>
      [
        'appType' =>
        [
          'eq' =>
          [
            0 => 'phone',
          ],
        ],
        'isHW' =>
        [
          'eq' =>
          [
            0 => 'true',
          ],
        ],
        'appAge' =>
        [
          'lt' =>
          [
            0 => 73,
          ],
        ],
        'publisherIp' =>
        [
          'eq' =>
          [
            0 => '10.30.30.70',
            1 => '10.30.30.21',
            2 => '10.101.19.100',
            3 => '10.101.15.10',
            4 => '10.30.48.10',
          ],
        ],
      ],
      'selectBy' =>
      [
      ],
    ],
    'notHWActive' =>
    [
      'column' => 'appType',
      'method' => 'count',
      'preFilter' =>
      [
        'appType' =>
        [
          'eq' =>
          [
            0 => 'phone',
          ],
        ],
        'isHW' =>
        [
          'eq' =>
          [
            0 => 'false',
          ],
        ],
        'appAge' =>
        [
          'lt' =>
          [
            0 => 73,
          ],
        ],
        'publisherIp' =>
        [
          'eq' =>
          [
            0 => '10.30.30.70',
            1 => '10.30.30.21',
            2 => '10.101.19.100',
            3 => '10.101.15.10',
            4 => '10.30.48.10',
          ],
        ],
      ],
      'selectBy' =>
      [
      ],
    ],
  ],
  'aliases' =>
  [
  ],
  'extraColumns' =>
  [
    0 => 'textField',
    1 => 'employees',
  ],
  'bodyFooterTable' => '',
  'sortOrderSets' =>
  [
    'default' =>
    [
    ],
  ],
  'sortBy' =>
  [
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
    'publisherIp' =>
    [
      'eq' =>
      [
        0 => '10.30.30.70',
        1 => '10.30.30.21',
        2 => '10.101.19.100',
        3 => '10.101.15.10',
        4 => '10.30.48.10',
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
      ],
    ],
    'body' =>
    [
      'table' =>
      [
        0 => 'table',
        1 => 'bg-success',
        2 => 'table-bordered',
        3 => 'body-footer',
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
      'preFilter' =>
      [
        'appType' =>
        [
          'eq' =>
          [
            0 => 'phone',
          ],
        ],
        'publisherIp' =>
        [
          'eq' =>
          [
            0 => '10.30.30.70',
            1 => '10.30.30.21',
            2 => '10.101.19.100',
            3 => '10.101.15.10',
            4 => '10.30.48.10',
          ],
        ],
      ],
      'selectBy' =>
      [
        0 => 'appType',
      ],
      'sortBy' =>
      [
        'publisherIp' => 'desc',
      ],
      'itemWidth' => '67px',
    ],
    'byPublishIpActive' =>
    [
      'column' => 'publisherIp',
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
        'publisherIp' =>
        [
          'eq' =>
          [
            0 => '10.30.30.70',
            1 => '10.30.30.21',
            2 => '10.101.19.100',
            3 => '10.101.15.10',
            4 => '10.30.48.10',
          ],
        ],
      ],
      'selectBy' =>
      [
        0 => 'appType',
      ],
      'sortBy' =>
      [
      ],
      'itemWidth' => 0,
    ],
    'byPublishIpActiveHW' =>
    [
      'column' => 'publisherIp',
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
        'isHW' =>
        [
          'eq' =>
          [
            0 => 'true',
          ],
        ],
        'publisherIp' =>
        [
          'eq' =>
          [
            0 => '10.30.30.70',
            1 => '10.30.30.21',
            2 => '10.101.19.100',
            3 => '10.101.15.10',
            4 => '10.30.48.10',
          ],
        ],
      ],
      'selectBy' =>
      [
        0 => 'appType',
      ],
      'sortBy' =>
      [
      ],
      'itemWidth' => 0,
    ],
  ],
];