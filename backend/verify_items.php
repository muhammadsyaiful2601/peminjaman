<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$items = \DB::table('items')->get();
echo 'Total items: ' . $items->count() . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;
foreach ($items as $i) {
    echo str_pad($i->item_code, 12) . ' | ' . str_pad($i->name, 30) . ' | ' . str_pad($i->category, 25) . ' | stock=' . $i->stock . PHP_EOL;
}
