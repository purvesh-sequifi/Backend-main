<?php

namespace App\Imports;

use App\Models\Lead;
use App\Models\leadComment;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LeadsImport implements ToModel, WithHeadingRow
{
    // public function model(array $row)
    // {
    //     // Validate required fields
    //     $requiredFields = ['first_name', 'last_name', 'phone_number', 'email'];

    //     foreach ($requiredFields as $field) {
    //         if (!array_key_exists($field, $row) || empty($row[$field])) {
    //             throw new \Exception("The field '{$field}' is required.");
    //         }
    //     }

    //     // Find state by name
    //     $stateName = $row['home_location_of_the_candidate'];
    //     $state = State::where('name', $stateName)->first();

    //     if (!$state) {
    //         throw new \Exception("State '{$stateName}' not found.");
    //     }

    //     // Find user by reporting manager's full name
    //     $reportingManagerFullName = $row['reporting_manager'];
    //     list($managerFirstName, $managerLastName) = $this->splitFullName($reportingManagerFullName);
    //     $reportingManager = User::where('first_name', $managerFirstName)
    //                             ->where('last_name', $managerLastName)
    //                             ->first();

    //     if (!$reportingManager) {
    //         throw new \Exception("Reporting manager '{$reportingManagerFullName}' not found.");
    //     }

    //     // Create and save the Lead
    //     $lead = new Lead([
    //         'first_name' => $row['first_name'],
    //         'last_name' => $row['last_name'],
    //         'source' => $row['source'] ?: 'Excel Import',
    //         'mobile_no' => $row['phone_number'],
    //         'email' => $row['email'],
    //         'state_id' => $state->id,
    //         'reporting_manager_id' => $reportingManager->id,
    //         'status' => 'Follow Up',
    //         'type' => 'Lead'
    //     ]);

    //     // Save the Lead
    //     $lead->save();

    //     // Save comment in lead_comments table if provided
    //     if (!empty($row['comments'])) {
    //         leadComment::create([
    //             'lead_id' => $lead->id,
    //             'user_id' => auth()->id(), // Assuming you are using Laravel's Auth system
    //             'comments' => $row['comments'],
    //             'status' => 1, // Default status
    //         ]);
    //     }

    //     // Return the newly created Lead
    //     return $lead;
    // }
    // public function model(array $row)
    // {
    //     // Log the entire row before processing to see its content
    //     \Log::info('Processing row:', ['row' => $row]);

    //     // Trim all values in the row to remove leading and trailing whitespace
    //     $row = array_map('trim', $row);

    //     // Check if the row is entirely empty (all fields are empty)
    //     if ($this->isRowEmpty($row)) {
    //         \Log::info('Skipping empty row:', ['row' => $row]);
    //         return null; // Skip processing this row
    //     }

    //     // Validate required fields
    //     $requiredFields = ['first_name', 'last_name', 'phone_number', 'email'];
    //     foreach ($requiredFields as $field) {
    //         if (!array_key_exists($field, $row) || empty($row[$field])) {
    //             // Log the missing or empty field issue
    //             \Log::error("Missing or empty field detected", [
    //                 'field' => $field,
    //                 'row' => $row
    //             ]);
    //             throw new \Exception("The field '{$field}' is required.");
    //         }
    //     }

    //     // Check for duplicate email or phone number
    //     $duplicateField = $this->checkForDuplicateLead($row['email'], $row['phone_number']);
    //     if ($duplicateField) {
    //         $message = "Duplicate entry detected for " . $duplicateField . ": " . $row[$duplicateField];
    //         \Log::info($message, ['row' => $row]);
    //         throw new \Exception($message);
    //     }

    //     // Find state by name
    //     $stateName = $row['home_location_of_the_candidate'];
    //     $state = State::where('name', $stateName)->first();

    //     if (!$state) {
    //         throw new \Exception("State '{$stateName}' not found.");
    //     }

    //     // Find user by reporting manager's full name
    //     $reportingManagerFullName = $row['reporting_manager'];
    //     list($managerFirstName, $managerLastName) = $this->splitFullName($reportingManagerFullName);
    //     $reportingManager = User::where('first_name', $managerFirstName)
    //                             ->where('last_name', $managerLastName)
    //                             ->first();

    //     if (!$reportingManager) {
    //         throw new \Exception("Reporting manager '{$reportingManagerFullName}' not found.");
    //     }

    //     // Create and save the Lead
    //     $lead = new Lead([
    //         'first_name' => $row['first_name'],
    //         'last_name' => $row['last_name'],
    //         'source' => $row['source'] ?: 'Excel Import',
    //         'mobile_no' => $row['phone_number'],
    //         'email' => $row['email'],
    //         'state_id' => $state->id,
    //         'reporting_manager_id' => $reportingManager->id,
    //         'status' => 'Follow Up',
    //         'type' => 'Lead'
    //     ]);

    //     // Save the Lead
    //     $lead->save();

    //     // Save comment in lead_comments table if provided
    //     if (!empty($row['comments'])) {
    //         LeadComment::create([
    //             'lead_id' => $lead->id,
    //             'user_id' => auth()->id(), // Assuming you are using Laravel's Auth system
    //             'comments' => $row['comments'],
    //             'status' => 1, // Default status
    //         ]);
    //     }

    //     // Return the newly created Lead
    //     return $lead;
    // }
    // public function model(array $row)
    // {
    //     // Log the entire row before processing to see its content
    //     \Log::info('Processing row:', ['row' => $row]);

    //     // Trim all values in the row to remove leading and trailing whitespace
    //     $row = array_map('trim', $row);

    //     // Check if the row is entirely empty (all fields are empty)
    //     if ($this->isRowEmpty($row)) {
    //         \Log::info('Skipping empty row:', ['row' => $row]);
    //         return null; // Skip processing this row
    //     }

    //     // Validate required fields
    //     $requiredFields = ['first_name', 'last_name', 'phone_number', 'email'];
    //     foreach ($requiredFields as $field) {
    //         if (!array_key_exists($field, $row) || empty($row[$field])) {
    //             // Log the missing or empty field issue
    //             \Log::error("Missing or empty field detected", [
    //                 'field' => $field,
    //                 'row' => $row
    //             ]);
    //             throw new \Exception("The field '{$field}' is required.");
    //         }
    //     }

    //     // Check for duplicate email or phone number
    //     $duplicateField = $this->checkForDuplicateLead($row['email'], $row['phone_number']);
    //     if ($duplicateField) {
    //         $message = "Duplicate entry detected for " . $duplicateField . ": " . $row[$duplicateField];
    //         \Log::info($message, ['row' => $row]);
    //         throw new \Exception($message);
    //     }

    //     // Find state by name
    //     $stateName = $row['home_location_of_the_candidate'];
    //     $state = State::where('name', $stateName)->first();

    //     if (!$state) {
    //         throw new \Exception("State '{$stateName}' not found.");
    //     }

    //     // Find user by reporting manager's full name
    //     $reportingManagerFullName = $row['reporting_manager'];
    //     list($managerFirstName, $managerLastName) = $this->splitFullName($reportingManagerFullName);
    //     $reportingManager = User::where('first_name', $managerFirstName)
    //                             ->where('last_name', $managerLastName)
    //                             ->first();

    //     if (!$reportingManager) {
    //         throw new \Exception("Reporting manager '{$reportingManagerFullName}' not found.");
    //     }

    //     // Create and save the Lead
    //     $lead = new Lead([
    //         'first_name' => $row['first_name'],
    //         'last_name' => $row['last_name'],
    //         'source' => $row['source'] ?: 'Excel Import',
    //         'mobile_no' => $row['phone_number'],
    //         'email' => $row['email'],
    //         'state_id' => $state->id,
    //         'reporting_manager_id' => $reportingManager->id,
    //         'status' => 'Follow Up',
    //         'type' => 'Lead'
    //     ]);

    //     // Save the Lead
    //     $lead->save();

    //     // Save comment in lead_comments table if provided
    //     if (!empty($row['comments'])) {
    //         LeadComment::create([
    //             'lead_id' => $lead->id,
    //             'user_id' => auth()->id(), // Assuming you are using Laravel's Auth system
    //             'comments' => $row['comments'],
    //             'status' => 1, // Default status
    //         ]);
    //     }

    //     // Return the newly created Lead
    //     return $lead;
    // }
    //     public function model(array $row)
    // {
    //     // Log the entire row before processing to see its content
    //     \Log::info('Processing row:', ['row' => $row]);

    //     // Trim all values in the row to remove leading and trailing whitespace
    //     $row = array_map('trim', $row);

    //     // Check if the row is entirely empty (all fields are empty)
    //     if ($this->isRowEmpty($row)) {
    //         \Log::info('Skipping empty row:', ['row' => $row]);
    //         return null; // Skip processing this row
    //     }

    //     // Validate required fields
    //     $requiredFields = ['first_name', 'last_name', 'phone_number', 'email'];
    //     foreach ($requiredFields as $field) {
    //         if (!array_key_exists($field, $row) || empty($row[$field])) {
    //             // Log the missing or empty field issue
    //             \Log::error("Missing or empty field detected", [
    //                 'field' => $field,
    //                 'row' => $row
    //             ]);
    //             throw new \Exception("The field '{$field}' is required in row: " . json_encode($row));
    //         }
    //     }

    //     // Check for duplicate email or phone number
    //     $duplicateField = $this->checkForDuplicateLead($row['email'], $row['phone_number']);
    //     if ($duplicateField) {
    //         $message = "Duplicate entry detected for " . $duplicateField . ": " . $row[$duplicateField] . " in row: " . json_encode($row);
    //         \Log::info($message, ['row' => $row]);
    //         throw new \Exception($message);
    //     }

    //     // Find state by name
    //     $stateName = $row['home_location_of_the_candidate'];
    //     $state = State::where('name', $stateName)->first();

    //     if (!$state) {
    //         throw new \Exception("State '{$stateName}' not found in row: " . json_encode($row));
    //     }

    //     // Find user by reporting manager's full name
    //     $reportingManagerFullName = $row['reporting_manager'];
    //     list($managerFirstName, $managerLastName) = $this->splitFullName($reportingManagerFullName);
    //     $reportingManager = User::where('first_name', $managerFirstName)
    //                             ->where('last_name', $managerLastName)
    //                             ->first();

    //     if (!$reportingManager) {
    //         throw new \Exception("Reporting manager '{$reportingManagerFullName}' not found in row: " . json_encode($row));
    //     }

    //     // Create and save the Lead
    //     $lead = new Lead([
    //         'first_name' => $row['first_name'],
    //         'last_name' => $row['last_name'],
    //         'source' => $row['source'] ?: 'Excel Import',
    //         'mobile_no' => $row['phone_number'],
    //         'email' => $row['email'],
    //         'state_id' => $state->id,
    //         'reporting_manager_id' => $reportingManager->id,
    //         'status' => 'Follow Up',
    //         'type' => 'Lead'
    //     ]);

    //     // Save the Lead
    //     $lead->save();

    //     // Save comment in lead_comments table if provided
    //     if (!empty($row['comments'])) {
    //         LeadComment::create([
    //             'lead_id' => $lead->id,
    //             'user_id' => auth()->id(), // Assuming you are using Laravel's Auth system
    //             'comments' => $row['comments'],
    //             'status' => 1, // Default status
    //         ]);
    //     }

    //     // Return the newly created Lead
    //     return $lead;
    // }
    public function model(array $row): ?Model
    {
        // Log the entire row before processing to see its content
        \Log::info('Processing row:', ['row' => $row]);

        // Trim all values in the row to remove leading and trailing whitespace
        $row = array_map('trim', $row);

        // Check if the row is entirely empty (all fields are empty)
        if ($this->isRowEmpty($row)) {
            \Log::info('Skipping empty row:', ['row' => $row]);

            return null; // Skip processing this row
        }

        // Validate required fields
        $requiredFields = ['first_name', 'last_name', 'phone_number', 'email'];
        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $row) || empty($row[$field])) {
                // Log the missing or empty field issue
                \Log::error('Missing or empty field detected', [
                    'field' => $field,
                    'row' => $row,
                ]);
                throw new \Exception("The field '{$field}' is required.");
            }
        }

        // Check for duplicate email or phone number
        $duplicateField = $this->checkForDuplicateLead($row['email'], $row['phone_number']);
        if ($duplicateField) {
            // Simplify the message to just mention the field with the duplicate entry
            $message = 'Duplicate entry detected for '.$duplicateField;
            \Log::info($message, ['row' => $row]);
            throw new \Exception($message);
        }

        // Find state by name
        $stateName = $row['home_location_of_the_candidate'];
        $state = State::where('name', $stateName)->first();

        if (! $state) {
            throw new \Exception("State '{$stateName}' not found.");
        }

        // Find user by reporting manager's full name
        $reportingManagerFullName = $row['reporting_manager'];
        [$managerFirstName, $managerLastName] = $this->splitFullName($reportingManagerFullName);
        $reportingManager = User::where('first_name', $managerFirstName)
            ->where('last_name', $managerLastName)
            ->first();

        if (! $reportingManager) {
            throw new \Exception("Reporting manager '{$reportingManagerFullName}' not found.");
        }

        // Find recruiter by source full name
        $recruiterInfo = null;

        if (! empty($row['source'])) {
            $sourceFullName = trim($row['source']);

            // Search in the User model by matching the full name
            $recruiterInfo = User::whereRaw("CONCAT(first_name, ' ', last_name) = ?", [$sourceFullName])->first();
        }

        // Throw exception if no recruiter is found
        if (! $recruiterInfo) {
            throw new \Exception("Source '".($sourceFullName ?? 'Unknown')."' not found.");
        }

        // Create and save the Lead
        $lead = new Lead([
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'source' => $row['source'] ?: 'Excel Import',
            'mobile_no' => $row['phone_number'],
            'email' => $row['email'],
            'state_id' => $state->id,
            'reporting_manager_id' => $reportingManager->id,
            'status' => 'Follow Up',
            'type' => 'Lead',
            'office_id' => (! empty($recruiterInfo->office_id) ? $recruiterInfo->office_id : ''),
            'pipeline_status_date' => date('Y-m-d'),
            'recruiter_id' => $recruiterInfo->id,
            'comments' => (! empty($row['comments']) ? $row['comments'] : ''),
        ]);

        // Save the Lead
        $lead->save();

        // Save comment in lead_comments table if provided
        if (! empty($row['comments'])) {
            LeadComment::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(), // Assuming you are using Laravel's Auth system
                'comments' => $row['comments'],
                'status' => 1, // Default status
            ]);
        }

        // Return the newly created Lead
        return $lead;
    }

    /**
     * Check if a lead with the given email or phone number already exists.
     */
    protected function checkForDuplicateLead(string $email, string $phoneNumber): ?string
    {
        // Validate the email and phone number format
        $emailValidator = Validator::make(
            ['email' => $email],
            ['email' => 'email|unique:users|unique:onboarding_employees']
        );

        if ($emailValidator->fails()) {
            return 'email';
        }

        $phoneValidator = Validator::make(
            ['mobile_no' => $phoneNumber],
            ['mobile_no' => 'unique:onboarding_employees,mobile_no|unique:users,mobile_no']
        );

        if ($phoneValidator->fails()) {
            return 'phone_number';
        }
        // Check for duplicate email
        if (Lead::where('email', $email)->exists()) {
            return 'email';
        }

        // Check for duplicate phone number
        if (Lead::where('mobile_no', $phoneNumber)->exists()) {
            return 'phone_number';
        }

        // No duplicates found
        return null;
    }

    /**
     * Helper function to check if the row is empty.
     */
    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $key => $value) {
            if (! empty($value)) {
                return false; // If any field is not empty, the row is not empty
            }
        }

        return true; // All fields are empty
    }

    /**
     * Helper function to split full name into first name and last name.
     */
    private function splitFullName(string $fullName): array
    {
        $parts = explode(' ', $fullName, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
