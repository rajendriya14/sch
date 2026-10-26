<?php

namespace Services;

use Models\LeaveRequest;
use Models\Employee;

class LeaveService
{
    /**
     * Get all leave requests with employee info
     */
    public function getAll(): array
    {
        return LeaveRequest::all();
    }

    /**
     * Get approved leaves for a specific week
     */
    public function getApprovedForWeek(string $weekStart, string $weekEnd): array
    {
        return LeaveRequest::getApprovedForWeek($weekStart, $weekEnd);
    }

    /**
     * Get leave by ID
     */
    public function getById(int $id): ?array
    {
        return LeaveRequest::find($id);
    }

    /**
     * Create a new leave request
     */
    public function create(array $data): array
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Lookup employee by email
        $employee = Employee::findByEmail($data['email']);
        if (!$employee) {
            return ['success' => false, 'errors' => ['Employee with this email not found.']];
        }

        $leaveData = [
            'employee_id'  => $employee['id'],
            'request_date' => $data['request_date'],
            'reason'       => $data['reason'] ?? '',
            'status'       => 'Approved', // Auto-approved for MVP
        ];

        LeaveRequest::create($leaveData);
        return ['success' => true];
    }

    /**
     * Approve a leave request
     */
    public function approve(int $id): bool
    {
        return LeaveRequest::updateStatus($id, 'Approved');
    }

    /**
     * Reject a leave request
     */
    public function reject(int $id): bool
    {
        return LeaveRequest::updateStatus($id, 'Rejected');
    }

    /**
     * Delete a leave request
     */
    public function delete(int $id): bool
    {
        return LeaveRequest::delete($id);
    }

    /**
     * Validate leave request data
     */
    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['email'])) {
            $errors[] = 'Employee email is required.';
        }
        if (empty($data['request_date'])) {
            $errors[] = 'Request date is required.';
        }
        return $errors;
    }
}
