## ADDED Requirements

### Requirement: Create item loans
The system SHALL allow creation of loan records linking items to employees and departments.

#### Scenario: Create new loan
- **WHEN** user creates loan with borrowing employee, department, item, quantity, and expected return date
- **THEN** system creates loan record with status "active" and logs as item exit with purpose "loan"

### Requirement: Track loan status
The system SHALL track loan lifecycle from creation through return.

#### Scenario: View active loans
- **WHEN** user filters loans by status "active"
- **THEN** system displays all active loans with borrower, department, item, quantity, and dates

#### Scenario: Close returned loan
- **WHEN** user marks loan as returned with return date and notes
- **THEN** system closes loan record, increases item quantity back to inventory, and logs return movement

### Requirement: Overdue loan tracking
The system SHALL identify and track overdue loans.

#### Scenario: Identify overdue loan
- **WHEN** user views loans and filters by overdue status
- **THEN** system displays loans past their expected return date with days overdue

### Requirement: Loan history and analytics
The system SHALL provide loan history and borrower analytics.

#### Scenario: View employee loan history
- **WHEN** user selects an employee
- **THEN** system displays all past and current loans for that employee including items, quantities, and dates

#### Scenario: View department loan summary
- **WHEN** user selects a department
- **THEN** system displays total items currently loaned to department and list of all active loans
