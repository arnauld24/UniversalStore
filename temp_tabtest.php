<?php
$current_tab = 'profil';
$val = htmlspecialchars($current_tab === 'orders') ? 'A' : 'B';
echo "profil -> $val\n";
$current_tab = 'orders';
$val = htmlspecialchars($current_tab === 'orders') ? 'A' : 'B';
echo "orders -> $val\n";
