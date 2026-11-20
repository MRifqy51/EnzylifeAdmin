<?php

return [
    'ph_baseline_start_id' => (int) env(
        'ZSCORE_PH_BASELINE_START_ID',
        0
    ),

    'temp_baseline_start_id' => (int) env(
        'ZSCORE_TEMP_BASELINE_START_ID',
        0
    ),
];