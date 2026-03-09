<?php

namespace Tests\Unit;

use App\Http\Controllers\API\V2\Sales\SalesExcelProcessController;
use App\Models\CompanyProfile;
use ReflectionClass;
use Tests\TestCase;

class SalesExcelProcessControllerTest extends TestCase
{
    private SalesExcelProcessController $controller;
    private $company;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->controller = new SalesExcelProcessController();
        
        // Create mock company object
        $this->company = new \stdClass();
        $this->company->company_type = CompanyProfile::PEST_COMPANY_TYPE;
        $this->company->id = 1;
    }

    /**
     * Helper method to call private buildCreateSaleMasterData method
     */
    private function callBuildCreateSaleMasterData($checked, $templateMappedFields = [])
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildCreateSaleMasterData');
        $method->setAccessible(true);

        return $method->invoke(
            $this->controller,
            $checked,
            [], // allProducts
            null, // defaultProduct
            collect([]), // saleProductRecords
            $templateMappedFields,
            [], // templateCustomFields
            [], // templateMappedCustomFields
            $this->company
        );
    }

    /** @test */
    public function it_does_not_include_closer_ids_when_null()
    {
        // Simulate raw data with NULL closer IDs
        $checked = new \stdClass();
        $checked->pid = 'TEST-001';
        $checked->customer_name = 'John Doe';
        $checked->customer_address = '123 Main St';
        $checked->customer_email = 'john@test.com';
        $checked->gross_account_value = 1000;
        $checked->closer1_id = null; // NULL - should NOT be included
        $checked->closer2_id = null; // NULL - should NOT be included
        $checked->setter1_id = null;
        $checked->setter2_id = null;
        $checked->customer_signoff = now();
        $checked->trigger_date = null;
        $checked->mapped_fields = [
            'pid',
            'customer_name',
            'customer_address',
            'customer_email',
            'gross_account_value',
            'customer_signoff',
            // closer1_id NOT in mapped_fields (was blank in Excel)
        ];

        $templateMappedFields = [
            'pid',
            'customer_name',
            'customer_address',
            'customer_email',
            'gross_account_value',
            'customer_signoff',
            'closer1_id',
            'closer2_id',
            'setter1_id',
            'setter2_id',
        ];

        $result = $this->callBuildCreateSaleMasterData($checked, $templateMappedFields);

        // Assertions
        $this->assertArrayNotHasKey('closer1_id', $result, 'closer1_id should NOT be in result when NULL');
        $this->assertArrayNotHasKey('closer2_id', $result, 'closer2_id should NOT be in result when NULL');
        $this->assertArrayNotHasKey('setter1_id', $result, 'setter1_id should NOT be in result when NULL');
        $this->assertArrayNotHasKey('setter2_id', $result, 'setter2_id should NOT be in result when NULL');
        
        // Other fields should be included
        $this->assertArrayHasKey('customer_name', $result);
        $this->assertEquals('John Doe', $result['customer_name']);
    }

    /** @test */
    public function it_includes_closer_ids_when_provided()
    {
        // Simulate raw data with closer IDs
        $checked = new \stdClass();
        $checked->pid = 'TEST-002';
        $checked->customer_name = 'Jane Doe';
        $checked->customer_address = '456 Oak Ave';
        $checked->customer_email = 'jane@test.com';
        $checked->gross_account_value = 2000;
        $checked->closer1_id = 2; // Has value - SHOULD be included
        $checked->closer2_id = 3; // Has value - SHOULD be included
        $checked->setter1_id = 4;
        $checked->setter2_id = 5;
        $checked->customer_signoff = now();
        $checked->trigger_date = null;
        $checked->mapped_fields = [
            'pid',
            'customer_name',
            'customer_address',
            'customer_email',
            'gross_account_value',
            'customer_signoff',
            'closer1_id',
            'closer2_id',
        ];

        $templateMappedFields = [
            'pid',
            'customer_name',
            'customer_address',
            'customer_email',
            'gross_account_value',
            'customer_signoff',
            'closer1_id',
            'closer2_id',
            'setter1_id',
            'setter2_id',
        ];

        $result = $this->callBuildCreateSaleMasterData($checked, $templateMappedFields);

        // Assertions
        $this->assertArrayHasKey('closer1_id', $result, 'closer1_id SHOULD be in result when provided');
        $this->assertEquals(2, $result['closer1_id']);
        
        $this->assertArrayHasKey('closer2_id', $result, 'closer2_id SHOULD be in result when provided');
        $this->assertEquals(3, $result['closer2_id']);
        
        $this->assertArrayHasKey('setter1_id', $result);
        $this->assertEquals(4, $result['setter1_id']);
        
        $this->assertArrayHasKey('setter2_id', $result);
        $this->assertEquals(5, $result['setter2_id']);
    }

    /** @test */
    public function it_does_not_include_regular_fields_when_blank_and_not_in_mapped_fields()
    {
        // Simulate raw data with blank customer_address
        $checked = new \stdClass();
        $checked->pid = 'TEST-003';
        $checked->customer_name = 'Test User';
        $checked->customer_address = ''; // Empty - should NOT be included
        $checked->customer_email = 'test@test.com';
        $checked->gross_account_value = 1500;
        $checked->customer_phone = null; // NULL - should NOT be included
        $checked->customer_signoff = now();
        $checked->trigger_date = null;
        $checked->mapped_fields = [
            'pid',
            'customer_name',
            'customer_email',
            'gross_account_value',
            'customer_signoff',
            // customer_address NOT in mapped_fields (was blank in Excel)
            // customer_phone NOT in mapped_fields
        ];

        $templateMappedFields = [
            'pid',
            'customer_name',
            'customer_address',
            'customer_email',
            'customer_phone',
            'gross_account_value',
            'customer_signoff',
        ];

        $result = $this->callBuildCreateSaleMasterData($checked, $templateMappedFields);

        // Assertions
        $this->assertArrayNotHasKey('customer_address', $result, 'customer_address should NOT be in result when empty');
        $this->assertArrayNotHasKey('customer_phone', $result, 'customer_phone should NOT be in result when NULL');
        
        // Fields with values should be included
        $this->assertArrayHasKey('customer_name', $result);
        $this->assertEquals('Test User', $result['customer_name']);
        
        $this->assertArrayHasKey('customer_email', $result);
        $this->assertEquals('test@test.com', $result['customer_email']);
    }

    /** @test */
    public function it_includes_date_fields_when_mapped_even_if_null()
    {
        // Simulate raw data with NULL date_cancelled (to clear it)
        $checked = new \stdClass();
        $checked->pid = 'TEST-004';
        $checked->customer_name = 'Test User';
        $checked->customer_address = '789 Pine St';
        $checked->customer_email = 'test@test.com';
        $checked->gross_account_value = 1200;
        $checked->customer_signoff = now();
        $checked->date_cancelled = null; // NULL but should be included (to clear)
        $checked->m1_date = null; // NULL but should be included (to clear)
        $checked->scheduled_install = '2025-12-01'; // Has value
        $checked->trigger_date = null;
        $checked->mapped_fields = [
            'pid',
            'customer_name',
            'customer_address',
            'customer_email',
            'gross_account_value',
            'customer_signoff',
            'date_cancelled', // In mapped_fields (was in Excel, but blank)
            'm1_date', // In mapped_fields (was in Excel, but blank)
            'scheduled_install',
        ];

        $templateMappedFields = [
            'pid',
            'customer_name',
            'customer_address',
            'customer_email',
            'gross_account_value',
            'customer_signoff',
            'date_cancelled',
            'm1_date',
            'scheduled_install',
        ];

        $result = $this->callBuildCreateSaleMasterData($checked, $templateMappedFields);

        // Assertions - date fields should be included even when NULL
        $this->assertArrayHasKey('date_cancelled', $result, 'date_cancelled SHOULD be in result (to allow clearing)');
        $this->assertNull($result['date_cancelled'], 'date_cancelled should be NULL');
        
        $this->assertArrayHasKey('m1_date', $result, 'm1_date SHOULD be in result (to allow clearing)');
        $this->assertNull($result['m1_date'], 'm1_date should be NULL');
        
        $this->assertArrayHasKey('scheduled_install', $result);
        $this->assertEquals('2025-12-01', $result['scheduled_install']);
    }

    /** @test */
    public function it_includes_fields_with_values_even_if_not_in_mapped_fields()
    {
        // Edge case: field has value but not in mapped_fields
        // This can happen if template changed after import
        $checked = new \stdClass();
        $checked->pid = 'TEST-005';
        $checked->customer_name = 'Edge Case User';
        $checked->customer_address = '321 Elm St';
        $checked->customer_email = 'edge@test.com';
        $checked->gross_account_value = 3000;
        $checked->customer_phone = '555-1234'; // Has value but NOT in mapped_fields
        $checked->customer_signoff = now();
        $checked->trigger_date = null;
        $checked->mapped_fields = [
            'pid',
            'customer_name',
            'customer_address',
            'customer_email',
            'gross_account_value',
            'customer_signoff',
            // customer_phone NOT in mapped_fields
        ];

        $templateMappedFields = [
            'pid',
            'customer_name',
            'customer_address',
            'customer_email',
            'customer_phone',
            'gross_account_value',
            'customer_signoff',
        ];

        $result = $this->callBuildCreateSaleMasterData($checked, $templateMappedFields);

        // Assertion - field with value should be included even if not in mapped_fields
        $this->assertArrayHasKey('customer_phone', $result, 'customer_phone SHOULD be included (has value)');
        $this->assertEquals('555-1234', $result['customer_phone']);
    }

    /** @test */
    public function it_handles_mixed_scenario_correctly()
    {
        // Complex scenario: some fields blank, some with values, some dates
        $checked = new \stdClass();
        $checked->pid = 'TEST-006';
        $checked->customer_name = 'Complex User';
        $checked->customer_address = ''; // Blank - should NOT be included
        $checked->customer_email = 'complex@test.com'; // Has value
        $checked->customer_phone = null; // NULL - should NOT be included
        $checked->gross_account_value = 5000; // Has value
        $checked->closer1_id = 2; // Has value
        $checked->closer2_id = null; // NULL - should NOT be included
        $checked->date_cancelled = null; // Date field, in mapped - SHOULD be included
        $checked->scheduled_install = '2025-12-15'; // Date field with value
        $checked->customer_signoff = now();
        $checked->trigger_date = null;
        $checked->mapped_fields = [
            'pid',
            'customer_name',
            'customer_email',
            'gross_account_value',
            'closer1_id',
            'date_cancelled', // In mapped_fields (was in Excel, but blank)
            'scheduled_install',
            'customer_signoff',
            // customer_address, customer_phone, closer2_id NOT in mapped_fields
        ];

        $templateMappedFields = [
            'pid',
            'customer_name',
            'customer_address',
            'customer_email',
            'customer_phone',
            'gross_account_value',
            'closer1_id',
            'closer2_id',
            'date_cancelled',
            'scheduled_install',
            'customer_signoff',
        ];

        $result = $this->callBuildCreateSaleMasterData($checked, $templateMappedFields);

        // Assertions
        // Fields with values - should be included
        $this->assertArrayHasKey('customer_name', $result);
        $this->assertArrayHasKey('customer_email', $result);
        $this->assertArrayHasKey('gross_account_value', $result);
        $this->assertArrayHasKey('closer1_id', $result);
        $this->assertEquals(2, $result['closer1_id']);
        
        // Blank/NULL non-date fields - should NOT be included
        $this->assertArrayNotHasKey('customer_address', $result, 'Empty customer_address should NOT be included');
        $this->assertArrayNotHasKey('customer_phone', $result, 'NULL customer_phone should NOT be included');
        $this->assertArrayNotHasKey('closer2_id', $result, 'NULL closer2_id should NOT be included');
        
        // Date fields - should be included even if NULL (when in mapped_fields)
        $this->assertArrayHasKey('date_cancelled', $result, 'NULL date_cancelled SHOULD be included');
        $this->assertNull($result['date_cancelled']);
        
        $this->assertArrayHasKey('scheduled_install', $result);
        $this->assertEquals('2025-12-15', $result['scheduled_install']);
    }
}

