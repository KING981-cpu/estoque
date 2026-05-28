## ADDED Requirements

### Requirement: Generate monthly consumption reports
The system SHALL generate reports showing item consumption patterns by month.

#### Scenario: Generate monthly consumption report
- **WHEN** user selects a month and year
- **THEN** system displays report showing total consumed quantity for each item during that month

#### Scenario: Export consumption report
- **WHEN** user clicks export on consumption report
- **THEN** system exports report as PDF or CSV with item names, quantities consumed, and trend information

### Requirement: Analyze consumption by department
The system SHALL break down consumption by department or cost center.

#### Scenario: View consumption by department
- **WHEN** user selects a month and department
- **THEN** system displays consumption report filtered by selected department

#### Scenario: Compare department consumption
- **WHEN** user views consumption dashboard
- **THEN** system displays consumption comparison across departments for selected period

### Requirement: Track consumption trends
The system SHALL identify consumption trends and patterns.

#### Scenario: View consumption trend chart
- **WHEN** user views item details
- **THEN** system displays consumption chart showing monthly usage for past 12 months

#### Scenario: Identify high consumption items
- **WHEN** user generates consumption report
- **THEN** system highlights items with highest consumption quantities and trends

### Requirement: Movement history reports
The system SHALL provide detailed movement history with analytics.

#### Scenario: Export movement history
- **WHEN** user selects date range and clicks "Export History"
- **THEN** system exports all movements (entries and exits) for selected period with details on user, item, quantity, and purpose
