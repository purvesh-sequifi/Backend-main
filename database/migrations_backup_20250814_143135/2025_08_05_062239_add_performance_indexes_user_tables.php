<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Users table indexes
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'social_security_no')) {
                    $table->index(['social_security_no', 'action_item_status'], 'idx_social_action');
                }
                // Skip manager_id - already exists as users_manager_id_foreign
                if (Schema::hasColumn('users', 'office_id')) {
                    $table->index(['office_id'], 'idx_office_id');
                }
            });
        }

        // Onboarding Employees table indexes
        if (Schema::hasTable('onboarding_employees')) {
            Schema::table('onboarding_employees', function (Blueprint $table) {
                $table->index(['hired_by_uid', 'status_id', 'action_item_status'], 'idx_hired_status_action');
            });
        }

        // History tables indexes
        if (Schema::hasTable('user_commission_history')) {
            Schema::table('user_commission_history', function (Blueprint $table) {
                $table->index(['user_id', 'action_item_status'], 'idx_user_action');
            });
        }

        if (Schema::hasTable('user_upfront_history')) {
            Schema::table('user_upfront_history', function (Blueprint $table) {
                $table->index(['user_id', 'action_item_status'], 'idx_user_action');
            });
        }

        if (Schema::hasTable('user_withheld_history')) {
            Schema::table('user_withheld_history', function (Blueprint $table) {
                $table->index(['user_id', 'action_item_status'], 'idx_user_action');
            });
        }

        if (Schema::hasTable('user_organization_history')) {
            Schema::table('user_organization_history', function (Blueprint $table) {
                $table->index(['user_id', 'action_item_status'], 'idx_user_action');
            });
        }

        if (Schema::hasTable('user_redlines')) {
            Schema::table('user_redlines', function (Blueprint $table) {
                $table->index(['user_id', 'action_item_status'], 'idx_user_action');
            });
        }
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_social_action');
            // Skip manager_id - foreign key index
            $table->dropIndex('idx_office_id');
        });

        Schema::table('onboarding_employees', function (Blueprint $table) {
            $table->dropIndex('idx_hired_status_action');
        });

        Schema::table('user_commission_history', function (Blueprint $table) {
            $table->dropIndex('idx_user_action');
        });

        Schema::table('user_upfront_history', function (Blueprint $table) {
            $table->dropIndex('idx_user_action');
        });

        Schema::table('user_withheld_history', function (Blueprint $table) {
            $table->dropIndex('idx_user_action');
        });

        Schema::table('user_organization_history', function (Blueprint $table) {
            $table->dropIndex('idx_user_action');
        });

        Schema::table('user_redlines', function (Blueprint $table) {
            $table->dropIndex('idx_user_action');
        });
    }
};
