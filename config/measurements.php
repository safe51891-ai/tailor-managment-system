<?php

return [
    'sections' => [
        'Kameez' => [
            'kameez_length' => 'Length', 'shoulder' => 'Shoulder width',
            'chest' => 'Chest', 'waist' => 'Waist', 'hip' => 'Hip',
            'sleeve' => 'Sleeve length', 'armhole' => 'Armhole',
            'neck' => 'Neck', 'cuff' => 'Cuff', 'daman' => 'Daman width',
        ],
        'Shalwar' => [
            'shalwar_length' => 'Length', 'shalwar_waist' => 'Waist',
            'shalwar_hip' => 'Hip', 'thigh' => 'Thigh', 'knee' => 'Knee',
            'rise' => 'Rise', 'poncha' => 'Bottom / poncha',
        ],
        'Shirt' => [
            'shirt_length' => 'Shirt length', 'shirt_back_length' => 'Back length',
            'shirt_shoulder' => 'Shoulder width', 'shirt_chest' => 'Chest',
            'shirt_waist' => 'Waist', 'shirt_hip' => 'Hip / hem girth',
            'shirt_neck' => 'Collar / neck', 'shirt_sleeve' => 'Sleeve length',
            'shirt_biceps' => 'Biceps', 'shirt_armhole' => 'Armhole', 'shirt_cuff' => 'Cuff',
        ],
        'Pant / Trouser' => [
            'pant_length' => 'Outside leg length', 'pant_inseam' => 'Inside leg / inseam',
            'pant_waist' => 'Waist', 'pant_hip' => 'Seat / hip', 'pant_thigh' => 'Thigh',
            'pant_knee' => 'Knee', 'pant_front_rise' => 'Front rise',
            'pant_back_rise' => 'Back rise', 'pant_bottom' => 'Bottom opening width',
        ],
        'Waistcoat — Sleeveless' => [
            'waistcoat_length' => 'Front length', 'waistcoat_back_length' => 'Back length',
            'waistcoat_shoulder' => 'Shoulder width', 'waistcoat_chest' => 'Chest',
            'waistcoat_waist' => 'Waist', 'waistcoat_hip' => 'Hip / hem girth',
            'waistcoat_armhole' => 'Armhole', 'waistcoat_neck_depth' => 'Front neck depth',
        ],
        'Waistcoat — With Sleeves' => [
            'sleeved_waistcoat_length' => 'Front length', 'sleeved_waistcoat_back_length' => 'Back length',
            'sleeved_waistcoat_shoulder' => 'Shoulder width', 'sleeved_waistcoat_chest' => 'Chest',
            'sleeved_waistcoat_waist' => 'Waist', 'sleeved_waistcoat_hip' => 'Hip / hem girth',
            'sleeved_waistcoat_armhole' => 'Armhole', 'sleeved_waistcoat_neck_depth' => 'Front neck depth',
            'sleeved_waistcoat_sleeve' => 'Sleeve length', 'sleeved_waistcoat_biceps' => 'Biceps',
            'sleeved_waistcoat_cuff' => 'Cuff',
        ],
    ],
    'garments' => [
        'set' => 'Suit (Shalwar Kameez)', 'kameez' => 'Kameez', 'shalwar' => 'Shalwar',
        'shirt' => 'Shirt', 'pant' => 'Pant / Trouser',
        'waistcoat' => 'Waistcoat — Sleeveless',
        'sleeved_waistcoat' => 'Waistcoat — With Sleeves',
    ],
    'garment_symbols' => ['waistcoat' => '🦺', 'sleeved_waistcoat' => '👕'],
    'order_sections' => [
        'set' => ['Kameez', 'Shalwar'], 'kameez' => ['Kameez'], 'shalwar' => ['Shalwar'],
        'shirt' => ['Shirt'], 'pant' => ['Pant / Trouser'],
        'waistcoat' => ['Waistcoat — Sleeveless'],
        'sleeved_waistcoat' => ['Waistcoat — With Sleeves'],
    ],
    'required_for_order' => [
        'set' => ['kameez_length', 'shalwar_length'], 'kameez' => ['kameez_length'],
        'shalwar' => ['shalwar_length'], 'shirt' => ['shirt_length', 'shirt_sleeve'],
        'pant' => ['pant_length'], 'waistcoat' => ['waistcoat_length'],
        'sleeved_waistcoat' => ['sleeved_waistcoat_length', 'sleeved_waistcoat_sleeve'],
    ],
    'length_fields' => ['kameez_length', 'shalwar_length', 'shirt_length', 'pant_length', 'waistcoat_length', 'sleeved_waistcoat_length'],
    'statuses' => ['received' => 'Received', 'cutting' => 'Cutting', 'stitching' => 'Stitching', 'ready' => 'Ready', 'delivered' => 'Delivered'],
];
