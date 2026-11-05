<?php

// Features that are built but not open to the public yet; while off, their pages show a "coming soon" screen.
return [
    'marketplace' => (bool) env('FEATURE_MARKETPLACE', false),
];
