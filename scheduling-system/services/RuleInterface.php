<?php

namespace Services;

/**
 * Interface for pluggable scheduling rules.
 *
 * Each rule inspects the current schedule state and can:
 * - Mark certain assignments as invalid
 * - Propose adjustments
 * - Report violations
 *
 * This allows future rules to be added without modifying the
 * SchedulingEngine core logic.
 */
interface RuleInterface
{
    /**
     * Apply this rule to the schedule data.
     *
     * @param array &$schedule   Reference to the schedule grid [employee_id][day] = shift_code
     * @param array  $employees  All active employees
     * @param array  $shifts     All shift definitions
     * @param array  $context    Additional context (forecast, leaves, tlSchedules, etc.)
     *
     * @return array List of violations or messages
     */
    public function apply(array &$schedule, array $employees, array $shifts, array $context): array;

    /**
     * Get the rule name (for display in validation summary).
     */
    public function getName(): string;

    /**
     * Get the rule priority (lower = applied first).
     */
    public function getPriority(): int;
}
