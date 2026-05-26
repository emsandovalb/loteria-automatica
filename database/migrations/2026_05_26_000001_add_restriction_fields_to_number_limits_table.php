<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('number_limits', function (Blueprint $table): void {
            $table->boolean('is_restricted')->default(false)->after('max_amount');
            $table->string('restriction_type')->nullable()->after('is_restricted');
            $table->text('restriction_reason')->nullable()->after('restriction_type');
            $table->boolean('requires_manual_review')->default(false)->after('restriction_reason');
            $table->boolean('is_blocked')->default(false)->after('requires_manual_review');
        });
    }

    public function down(): void
    {
        Schema::table('number_limits', function (Blueprint $table): void {
            $table->dropColumn([
                'is_restricted',
                'restriction_type',
                'restriction_reason',
                'requires_manual_review',
                'is_blocked',
            ]);
        });
    }
};
