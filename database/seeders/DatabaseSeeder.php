<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Prevent seeding in production environment (case-insensitive, catches prod/production variations)
        $env = strtolower(app()->environment());
        if (in_array($env, ['production', 'prod']) || str_contains($env, 'prod')) {
            $this->command->error('🛑 SEEDING BLOCKED: Running in PRODUCTION environment (' . app()->environment() . ')');
            $this->command->warn('⚠️  Seeding is disabled in PRODUCTION for safety.');
            $this->command->warn('If you need to seed production, use specific seeders with --class option.');
            return;
        }

        // Ordered by parent-child dependencies
        $this->call([
            // ========================================
            // LEVEL 1: System Tables (No Dependencies)
            // ========================================
            //CompanyProfileSeeder::class,  // Depends on CompanyType, State, Cities
            PlansSeeder::class,
            CompanyTypeSeeder::class,
            CRMSSeeder::class,
            StateSeeder::class,
            StateMVRCostSeeder::class,
            CitiesSeeder::class,
            FrequencyTypeSeeder::class,
            PayrollProcessSeeder::class,
            Accounting_SoftwaresSeeder::class,
            DepartmentSeeder::class,
            AlertSeeder::class,
            CommissionsSeeder::class,
            Sub_CommissionsSeeder::class,
            TimezoneSeeder::class,
            CreateStatusSeeder::class,
            BackendSettingSeeder::class,
            SchedulingApprovalSettingSeeder::class,
            SettingsSeeder::class,  // System settings (S3 URLs, etc.)
            OverridesSeeder::class,
            OverridetypeSeeder::class,
            OverrideSettingSeeder::class,
            OverridesTypeSeeder::class,
            PayrollStatusSeeder::class,
            PipelineLeadStatusSeeder::class,
            SClearanceStatusSeeder::class,
            SClearanceTurnStatusSeeder::class,
            SClearancePlanSeeder::class,
            SClearanceTurnPackageConfigurationsSeeder::class,
            MarketingdealsSeeder::class,
            MarketingReconciliationSeeder::class,
            MarginsettingSeeder::class,
            MarginDifferenceSeeder::class,
            TierSettingsSeeder::class,
            TierSystemSeeder::class,
            TierDurationSeeder::class,
            ReconciliationsSeeder::class,
            AdjustmentTypeSeeder::class,
            MarkAccountSeeder::class,
            ApprovalsAndRequeststatusSeeder::class,
            DocumentTypeSeeder::class,
            AddonPlansSeeder::class,
            BillingTypeSeeder::class,
            BillingFrequencyTableSeeder::class,
            EmployeeIdSettingSeeder::class,
            PayFrequencySettingSeeder::class,
            AdwancePaymentSettingsSeeder::class,
            ScheduleTimeMasterSeeder::class,

            // ========================================
            // LEVEL 2: Depends on Level 1
            // ========================================
            // NOTE: The following 7 seeders depend on CompanyProfile and are now run via API
            // after company profile creation using: php artisan company:seed-dependent-data
            // - TierMetricsSeeder::class
            // - SchemaTriggerDateSeeder::class
            // - MilestoneSchemaSeeder::class
            // - MilestoneSeeder::class
            // - ProductSeeder::class
            // - ImportCategorySeeder::class
            // - SalesImportTemplatesSeeder::class

            LocationSeeder::class,  // Depends on State, Cities - creates default locations
            SClearanceStatusAddManuallSeeder::class,  // Adds manual verification status (depends on SClearanceStatusSeeder)
            CompanyPayrollSeeder::class,
            CompanySettingSeeder::class,
            PositionsSeeder::class,  // Depends on Department
            CostCenterSeeder::class,
            TierLevelNameSeeder::class,
            TierLevelSettingSeeder::class,
            ConfigureTierSeeder::class,
            GroupMasterSeeder::class,
            GroupPoliciesSeeder::class,
            PoliciesTabsSeeder::class,
            Employee_PositionsSeeder::class,
            BusinessAddressSeeder::class,
            DomainSettingsSeeder::class,
            SubscriptionsSeeder::class,

            // ========================================
            // LEVEL 3: Depends on Level 2
            // ========================================
            UserStatusSeeder::class,
            RolesSeeder::class,  // Must run before UsersSeeder for role assignments
            UsersSeeder::class,  // Depends on Positions, State, Department, GroupMaster, Roles
            PositionCommissionSeeder::class,  // Depends on Positions
            PositionCommissionUpfrontsSeeder::class,  // Depends on Positions
            PositionCommissionDeductionSettingSeeder::class,  // Depends on Positions
            PositionCommissionDeductionSeeder::class,  // Depends on Positions
            PositionOverrideSattlementSeeder::class,  // Depends on Positions
            PositionOverrideSeeder::class,  // Depends on Positions
            PositionTierOverrideSeeder::class,  // Depends on Positions
            PositionCommissionDeductionlimitSeeder::class,  // Depends on Positions
            MarketingDealAlertSeeder::class,
            IncompleteAccountAlertSeeder::class,
            CreateTemplateSeeder::class,
            TemplateMetaSeeder::class,
            TemplateCategoriesSeeder::class,
            CRMSettingSeeder::class,
            HiringStatusCompleteSeeder::class,  // Complete hiring status data (24 statuses with full configuration)
            //SequiDocsEmailSettingSeeder::class, // Depends on Company Name
            SequiDocsTemplatePermissionSeeder::class,
            SequiDocsDefaultTemplatesSeeder::class,  // Create default W9, W-4, I-9 templates
            TicketModulesDataSeeder::class,
            TicketFaqDataSeeder::class,
            EmailNotificationSettingsSeeder::class,
            SequiAiSettingSeeder::class,
            AddPlanForSequiAiSeeder::class,
            AddSequiAiSubscriptionSeeder::class,
            SequiaiPlanSeeder::class,
            UserScheduleTimeSeeder::class,  // Depends on Users, ScheduleTimeMaster

            // ========================================
            // LEVEL 4: Depends on Level 3 (Permission System)
            // ========================================
            PermissionsSeeder::class,  // Must seed permissions BEFORE GroupPermissionTblSeeder
            GroupPermissionTblSeeder::class,  // Depends on GroupMaster, Policies, Users, Permissions
            PermissionsFinalizeAndExecuteSeeder::class,
            ProductionPermissionsImporter::class,  // Import production permission data (roles, group_policies, policies_tabs, permissions, group_permissions, profile_access_permissions)
            AdditionalGroupPermissionsSeeder::class,  // Add Admin, Standard, Manager groups with permissions (MUST run AFTER ProductionPermissionsImporter)

            // ========================================
            // LEVEL 5: Data Migrations (Run Last)
            // ========================================
            UserEvereeMigrationSeeder::class,  // NEW: Update user everee data
            PayrollV2DataMigrationSeeder::class,  // NEW: Payroll V2 data migration
            OnboardingEmployeeStatusMigrationSeeder::class,  // NEW: Update onboarding statuses
            UpdatePipelineStatusIdAsPerStatusSeeder::class,  // Update pipeline status IDs
            // HiringStatusSetDisplayOrderAndColorCodeSeeder::class,  // REPLACED by HiringStatusCompleteSeeder
        ]);
    }
}
