<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_plans', function (Blueprint $table) {
            // Inputs — what the forecast was computed from.
            $table->unsignedSmallInteger('analysis_days')->default(90)->after('item_id');
            $table->unsignedSmallInteger('forecast_days')->default(30)->after('analysis_days');
            $table->unsignedSmallInteger('lead_time_days')->default(7)->after('forecast_days');

            // Workings — kept so the arithmetic can be re-checked on screen.
            $table->decimal('average_daily_usage', 10, 3)->default(0)->after('historical_usage');
            $table->unsignedInteger('safety_stock')->default(0)->after('reorder_point');
            $table->unsignedInteger('suggested_order_quantity')->default(0)->after('safety_stock');

            // Null means "no measurable usage", which is different from zero
            // days of cover — an item nobody has touched is not about to run out.
            $table->unsignedSmallInteger('days_of_cover')->nullable()->after('suggested_order_quantity');
            $table->string('trend')->default('insufficient_data')->after('days_of_cover');

            // Provenance.
            $table->foreignId('generated_by')->nullable()->after('trigger_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable()->after('generated_by');
            $table->text('notes')->nullable()->after('generated_at');

            $table->index(['item_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('demand_plans', function (Blueprint $table) {
            $table->dropIndex(['item_id', 'generated_at']);
            $table->dropForeign(['generated_by']);
            $table->dropColumn([
                'analysis_days',
                'forecast_days',
                'lead_time_days',
                'average_daily_usage',
                'safety_stock',
                'suggested_order_quantity',
                'days_of_cover',
                'trend',
                'generated_by',
                'generated_at',
                'notes',
            ]);
        });
    }
};
