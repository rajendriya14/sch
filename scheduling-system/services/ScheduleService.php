<?php

namespace Services;

use Models\ScheduleHeader;
use Models\ScheduleDetail;
use Models\Employee;

class ScheduleService
{
    private SchedulingEngine $engine;
    private ScheduleValidator $validator;

    public function __construct()
    {
        $this->engine = new SchedulingEngine();
        $this->validator = new ScheduleValidator();
    }

    /**
     * Generate and save a new schedule for the given week
     */
    public function generate(string $weekStartDate): array
    {
        // Run the engine
        $result = $this->engine->generate($weekStartDate);

        if (empty($result['schedule'])) {
            return ['success' => false, 'error' => 'Schedule generation produced no results.'];
        }

        // Save to database
        $headerId = ScheduleHeader::create([
            'week_start_date' => $weekStartDate,
            'status'          => 'Draft',
        ]);

        $details = [];
        foreach ($result['schedule'] as $empId => $days) {
            $employee = Employee::find($empId);
            if (!$employee) {
                continue;
            }

            foreach ($days as $d => $shiftCode) {
                $shiftCode = $shiftCode ?: 'OFF';
                $details[] = [
                    'schedule_header_id' => $headerId,
                    'employee_id'        => $empId,
                    'day_of_week'        => $d,
                    'shift_code'         => $shiftCode,
                    'work_date'          => $result['weekDates'][$d],
                ];
            }
        }

        ScheduleDetail::bulkCreate($details);

        // Run validation
        $validation = $this->validator->validate($result['schedule']);

        return [
            'success'    => true,
            'header_id'  => $headerId,
            'schedule'   => $result['schedule'],
            'validation' => $validation,
            'weekDates'  => $result['weekDates'],
        ];
    }

    /**
     * Get the latest saved schedule
     */
    public function getLatest(): ?array
    {
        $header = ScheduleHeader::getLatest();
        if (!$header) {
            return null;
        }

        $details = ScheduleDetail::getGroupedByEmployee($header['id']);

        // Build schedule grid format
        $schedule = [];
        foreach ($details as $empDbId => $data) {
            $schedule[$empDbId] = $data['shifts'];
        }

        $validation = $this->validator->validate($schedule);

        $weekDates = [];
        $ts = strtotime($header['week_start_date']);
        for ($i = 0; $i < 7; $i++) {
            $weekDates[$i] = date('Y-m-d', strtotime("+{$i} days", $ts));
        }

        return [
            'header'     => $header,
            'details'    => $details,
            'validation' => $validation,
            'weekDates'  => $weekDates,
        ];
    }

    /**
     * Get schedule for a specific week
     */
    public function getForWeek(string $weekStartDate): ?array
    {
        $header = ScheduleHeader::getLatestForWeek($weekStartDate);
        if (!$header) {
            return null;
        }

        $details = ScheduleDetail::getGroupedByEmployee($header['id']);

        $schedule = [];
        foreach ($details as $empDbId => $data) {
            $schedule[$empDbId] = $data['shifts'];
        }

        $validation = $this->validator->validate($schedule);

        $weekDates = [];
        $ts = strtotime($header['week_start_date']);
        for ($i = 0; $i < 7; $i++) {
            $weekDates[$i] = date('Y-m-d', strtotime("+{$i} days", $ts));
        }

        return [
            'header'     => $header,
            'details'    => $details,
            'validation' => $validation,
            'weekDates'  => $weekDates,
        ];
    }

    /**
     * Get the validator instance (for re-use)
     */
    public function getValidator(): ScheduleValidator
    {
        return $this->validator;
    }
}
