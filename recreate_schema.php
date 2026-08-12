<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// Drop add-on tables first (reverse order)
$drops = [
    'machine_part_requests', 'defective_parts', 'activity_logs', 'cost_history',
    'maintenance_contracts', 'defective_items', 'machine_requests', 'machine_parts',
    'part_requests', 'receiving', 'customer_item_parts', 'machines',
    'item_parts', 'categories', 'locations', 'customers', 'suppliers', 'users',
];
foreach ($drops as $t) {
    Schema::dropIfExists($t);
    echo "Dropped (if existed): $t\n";
}

// 1. users
Schema::create('users', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->string('username')->unique();
    $t->string('password');
    $t->string('name');
    $t->string('email')->nullable();
    $t->string('role');
    $t->boolean('is_active')->default(1);
    $t->timestamps();
    $t->softDeletes();
});
echo "Created: users\n";

// 2. locations
Schema::create('locations', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->string('code');
    $t->string('description');
    $t->boolean('is_active')->default(1);
    $t->foreignId('created_by')->constrained('users');
    $t->timestamps();
});
echo "Created: locations\n";

// 3. suppliers
Schema::create('suppliers', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->string('name');
    $t->string('address')->nullable();
    $t->string('contact_no')->nullable();
    $t->boolean('is_active')->default(1);
    $t->timestamps();
});
echo "Created: suppliers\n";

// 4. customers
Schema::create('customers', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->string('name');
    $t->boolean('is_active')->default(1);
    $t->timestamps();
});
echo "Created: customers\n";

// 5. categories
Schema::create('categories', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->string('code');
    $t->string('description');
    $t->boolean('is_active')->default(1);
    $t->timestamps();
});
echo "Created: categories\n";

// 6. item_parts
Schema::create('item_parts', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->string('part_no');
    $t->string('manufacturer')->nullable();
    $t->string('model')->nullable();
    $t->string('description');
    $t->decimal('unit_cost', 12, 2)->default(0);
    $t->integer('min_stock')->default(0);
    $t->boolean('is_serialized')->default(0);
    $t->string('type');
    $t->string('remarks')->nullable();
    $t->timestamps();
    $t->softDeletes();
});
echo "Created: item_parts\n";

// 7. customer_item_parts
Schema::create('customer_item_parts', function (Blueprint $t) {
    $t->foreignId('customer_id')->constrained('customers');
    $t->foreignId('item_part_id')->constrained('item_parts');
    $t->primary(['customer_id', 'item_part_id']);
});
echo "Created: customer_item_parts\n";

// 8. machines
Schema::create('machines', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->string('serial_no')->unique();
    $t->string('description');
    $t->string('model')->nullable();
    $t->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
    $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
    $t->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
    $t->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
    $t->string('ati_po')->nullable();
    $t->date('date_received');
    $t->decimal('unit_cost', 12, 2)->default(0);
    $t->string('status');
    $t->dateTime('out_date')->nullable();
    $t->dateTime('return_date')->nullable();
    $t->string('return_remarks')->nullable();
    $t->string('returned_by')->nullable();
    $t->foreignId('created_by')->constrained('users');
    $t->timestamps();
});
echo "Created: machines\n";

// 9. machine_parts
Schema::create('machine_parts', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
    $t->foreignId('item_part_id')->constrained('item_parts');
    $t->integer('quantity');
    $t->string('serial_no')->nullable();
    $t->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
    $t->string('purpose')->nullable();
    $t->string('status')->default('available');
    $t->string('remarks')->nullable();
    $t->timestamps();
});
echo "Created: machine_parts\n";

// 10. receiving
Schema::create('receiving', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->date('date_received');
    $t->string('ati_po')->nullable();
    $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
    $t->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
    $t->foreignId('item_part_id')->constrained('item_parts');
    $t->foreignId('location_id')->constrained('locations');
    $t->integer('quantity');
    $t->string('serial_no')->nullable();
    $t->string('status');
    $t->string('return_remarks')->nullable();
    $t->dateTime('return_date')->nullable();
    $t->string('returned_by')->nullable();
    $t->dateTime('location_updated_at')->nullable();
    $t->string('location_updated_by')->nullable();
    $t->foreignId('created_by')->constrained('users');
    $t->timestamps();
});
echo "Created: receiving\n";

// 11. part_requests
Schema::create('part_requests', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->date('pick_date');
    $t->foreignId('customer_id')->constrained('customers');
    $t->foreignId('item_part_id')->constrained('item_parts');
    $t->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
    $t->integer('quantity');
    $t->string('serial_no')->nullable();
    $t->string('status');
    $t->string('remarks')->nullable();
    $t->string('mris_no')->nullable();
    $t->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
    $t->string('approved_by')->nullable();
    $t->dateTime('out_date')->nullable();
    $t->foreignId('requested_by')->constrained('users');
    $t->timestamps();
});
echo "Created: part_requests\n";

// 12. machine_requests
Schema::create('machine_requests', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->date('pick_date');
    $t->foreignId('customer_id')->constrained('customers');
    $t->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
    $t->string('description')->nullable();
    $t->string('remarks')->nullable();
    $t->string('status');
    $t->string('purpose')->nullable();
    $t->string('mris_no')->nullable();
    $t->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
    $t->string('approved_by')->nullable();
    $t->dateTime('out_date')->nullable();
    $t->foreignId('requested_by')->constrained('users');
    $t->timestamps();
});
echo "Created: machine_requests\n";

// 13. defective_items
Schema::create('defective_items', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->date('date_received');
    $t->foreignId('customer_id')->constrained('customers');
    $t->foreignId('engineer_id')->constrained('users');
    $t->foreignId('item_part_id')->constrained('item_parts');
    $t->foreignId('location_id')->constrained('locations');
    $t->integer('quantity');
    $t->string('serial_no')->nullable();
    $t->string('return_remarks')->nullable();
    $t->dateTime('return_date')->nullable();
    $t->string('returned_by')->nullable();
    $t->timestamps();
});
echo "Created: defective_items\n";

// 14. maintenance_contracts
Schema::create('maintenance_contracts', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->string('account_manager');
    $t->string('equipment_type');
    $t->foreignId('customer_id')->constrained('customers');
    $t->string('po_no');
    $t->string('sites');
    $t->string('brand');
    $t->string('description');
    $t->string('location');
    $t->string('company');
    $t->string('branch');
    $t->string('serial_no');
    $t->integer('coverage_months');
    $t->date('start_date');
    $t->date('end_date');
    $t->string('remarks')->nullable();
    $t->boolean('is_active')->default(1);
    $t->timestamps();
});
echo "Created: maintenance_contracts\n";

// 15. cost_history
Schema::create('cost_history', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->foreignId('item_part_id')->constrained('item_parts');
    $t->decimal('previous_cost', 12, 2);
    $t->decimal('new_cost', 12, 2);
    $t->foreignId('updated_by')->constrained('users');
    $t->timestamp('created_at')->useCurrent();
});
echo "Created: cost_history\n";

// 16. activity_logs
Schema::create('activity_logs', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $t->string('user_role');
    $t->string('action');
    $t->string('remarks')->nullable();
    $t->timestamp('created_at')->useCurrent();
});
echo "Created: activity_logs\n";

// 17. defective_parts (Laravel migration)
Schema::create('defective_parts', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->foreignId('item_part_id')->constrained('item_parts');
    $t->integer('quantity');
    $t->string('serial_no')->nullable();
    $t->string('remarks')->nullable();
    $t->timestamps();
});
echo "Created: defective_parts\n";

// Mark add-on migrations as run
DB::table('migrations')->whereIn('migration', [
    '2026_06_20_120759_create_defective_parts_table',
    '2026_06_20_161358_add_machine_id_to_part_requests_table',
    '2026_06_20_171058_add_status_to_machine_parts_table',
    '2026_06_20_171059_add_machine_part_id_to_part_requests_table',
    '2026_06_21_224952_create_machine_part_requests_table',
])->delete();

DB::table('migrations')->insert([
    ['migration' => '2026_06_20_120759_create_defective_parts_table', 'batch' => 1],
    ['migration' => '2026_06_20_161358_add_machine_id_to_part_requests_table', 'batch' => 1],
    ['migration' => '2026_06_20_171058_add_status_to_machine_parts_table', 'batch' => 1],
    ['migration' => '2026_06_20_171059_add_machine_part_id_to_part_requests_table', 'batch' => 1],
    ['migration' => '2026_06_21_224952_create_machine_part_requests_table', 'batch' => 1],
]);

echo "\nAll tables recreated successfully.\n";
