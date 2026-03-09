<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates triggers that automatically set onboardProcess = 1 when all mandatory fields are filled.
     * Triggers fire BEFORE INSERT and BEFORE UPDATE on the users table.
     * 
     * Important: Only sets onboardProcess = 1 when it's currently 0. Never changes it back to 0.
     * 
     * Note: Using BEFORE triggers because MySQL doesn't allow updating the same table in AFTER triggers.
     * BEFORE triggers allow us to modify NEW values directly without recursion issues.
     * 
     * Mandatory fields checked (simplified - only checks if not null/empty, no format validation):
     * - Core: entity_type, name_of_bank, routing_no, account_no, type_of_account
     * - Additional: first_name, last_name, mobile_no, email, employee_id, home_address_line_1,
     *   home_address_city, home_address_state, home_address_zip, dob, account_name
     * - Entity-specific: social_sequrity_no (for individual) OR business_ein, business_name, business_type (for business)
     * - W2 workers: everee_embed_onboard_profile = 1
     */
    public function up(): void
    {
        $validationLogic = $this->getValidationLogic();
        
        // Drop existing triggers if they exist (user will remove manually, but this ensures clean state)
        DB::unprepared('DROP TRIGGER IF EXISTS auto_update_onboard_process_on_users_update');
        DB::unprepared('DROP TRIGGER IF EXISTS auto_update_onboard_process_on_users_insert');
        
        // Create UPDATE trigger
        DB::unprepared("
            CREATE TRIGGER auto_update_onboard_process_on_users_update
            BEFORE UPDATE ON users
            FOR EACH ROW
            BEGIN
                DECLARE all_fields_filled BOOLEAN DEFAULT TRUE;
                
                -- Only process if onboardProcess is currently 0 (not 1)
                -- Skip if onboardProcess is being changed in this update (to prevent recursion)
                IF NEW.onboardProcess = 0 
                   AND (OLD.onboardProcess = NEW.onboardProcess OR OLD.onboardProcess IS NULL) THEN
                    {$validationLogic}
                    
                    -- If all fields are filled, set onboardProcess to 1 directly
                    -- Using BEFORE UPDATE allows us to modify NEW values without recursion
                    IF all_fields_filled = TRUE THEN
                        SET NEW.onboardProcess = 1;
                    END IF;
                END IF;
            END
        ");
        
        // Create INSERT trigger
        DB::unprepared("
            CREATE TRIGGER auto_update_onboard_process_on_users_insert
            BEFORE INSERT ON users
            FOR EACH ROW
            BEGIN
                DECLARE all_fields_filled BOOLEAN DEFAULT TRUE;
                
                -- Only process if onboardProcess is NULL or 0 (not 1)
                IF NEW.onboardProcess IS NULL OR NEW.onboardProcess = 0 THEN
                    {$validationLogic}
                    
                    -- If all fields are filled, set onboardProcess = 1 directly
                    IF all_fields_filled = TRUE THEN
                        SET NEW.onboardProcess = 1;
                    END IF;
                END IF;
            END
        ");
    }
    
    /**
     * Get the validation logic as SQL code (reusable for both INSERT and UPDATE triggers)
     * Simplified validation: only checks if fields are not null/empty, no format validation
     */
    private function getValidationLogic(): string
    {
        return '
                        -- Check core mandatory fields (simplified - only check if not null/empty)
                        IF (NEW.entity_type IS NULL OR NEW.entity_type = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.name_of_bank IS NULL OR NEW.name_of_bank = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.routing_no IS NULL OR NEW.routing_no = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.account_no IS NULL OR NEW.account_no = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.type_of_account IS NULL OR NEW.type_of_account = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        -- Check additional mandatory fields
                        IF (NEW.first_name IS NULL OR NEW.first_name = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.last_name IS NULL OR NEW.last_name = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.mobile_no IS NULL OR NEW.mobile_no = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.email IS NULL OR NEW.email = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.employee_id IS NULL OR NEW.employee_id = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.home_address_line_1 IS NULL OR NEW.home_address_line_1 = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.home_address_city IS NULL OR NEW.home_address_city = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.home_address_state IS NULL OR NEW.home_address_state = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.home_address_zip IS NULL OR NEW.home_address_zip = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.dob IS NULL) THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        IF (NEW.account_name IS NULL OR NEW.account_name = \'\') THEN
                            SET all_fields_filled = FALSE;
                        END IF;
                        
                        -- Check entity type-specific fields
                        IF (LOWER(COALESCE(NEW.entity_type, \'\')) = \'individual\') THEN
                            IF (NEW.social_sequrity_no IS NULL OR NEW.social_sequrity_no = \'\') THEN
                                SET all_fields_filled = FALSE;
                            END IF;
                        ELSEIF (LOWER(COALESCE(NEW.entity_type, \'\')) = \'business\') THEN
                            IF (NEW.business_ein IS NULL OR NEW.business_ein = \'\') THEN
                                SET all_fields_filled = FALSE;
                            END IF;
                            IF (NEW.business_name IS NULL OR NEW.business_name = \'\') THEN
                                SET all_fields_filled = FALSE;
                            END IF;
                            IF (NEW.business_type IS NULL OR NEW.business_type = \'\') THEN
                                SET all_fields_filled = FALSE;
                            END IF;
                        ELSE
                            -- Default: check if at least one tax identifier exists
                            IF ((NEW.social_sequrity_no IS NULL OR NEW.social_sequrity_no = \'\') 
                                AND (NEW.business_ein IS NULL OR NEW.business_ein = \'\')) THEN
                                SET all_fields_filled = FALSE;
                            END IF;
                        END IF;
                        
                        -- Check W2 worker requirement
                        IF (LOWER(COALESCE(NEW.worker_type, \'\')) = \'w2\') THEN
                            IF (NEW.everee_embed_onboard_profile IS NULL OR NEW.everee_embed_onboard_profile = \'\' OR NEW.everee_embed_onboard_profile != 1) THEN
                                SET all_fields_filled = FALSE;
                            END IF;
                        END IF;
        ';
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS auto_update_onboard_process_on_users_update');
        DB::unprepared('DROP TRIGGER IF EXISTS auto_update_onboard_process_on_users_insert');
    }
};
