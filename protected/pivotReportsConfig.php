<?php

return [
  'test' =>
  [
    'className' => 'App\\ViewModels\\GeoDev_View',
    'pivotColumn' =>
    [
      'name' =>
      [
        0 => 'platformTitle',
      ],
      'sortOrder' =>
      [
        'platformTitle' => '',
      ],
      'filter' =>
      [
        'allowNull' => false,
        'conditions' =>
        [
          'appType' =>
          [
            0 => 'phone',
          ],
        ],
      ],
    ],
    'columns' =>
    [
      'name' =>
      [
        0 => 'region',
        1 => 'office',
      ],
      'sortOrder' =>
      [
        'region' => '',
        'office' => '',
      ],
      'filter' =>
      [
        'allowNull' => false,
      ],
    ],
  ],
];