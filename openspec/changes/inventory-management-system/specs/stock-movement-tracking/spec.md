## ADDED Requirements

### Requirement: Record item entry (received stock)
The system SHALL record all incoming inventory items with complete audit trail.

#### Scenario: Record item entry
- **WHEN** user logs an incoming item with quantity, receiving employee, timestamp, and notes
- **THEN** system adds quantity to item stock and creates audit entry with all details

### Requirement: Record item exit (distribution)
The system SHALL record all outgoing inventory items with purpose classification and audit trail.

#### Scenario: Record consumption exit
- **WHEN** user logs item exit for consumption with quantity, withdrawing employee, timestamp, and notes
- **THEN** system decreases item quantity by specified amount and logs exit with purpose marked as "consumption"

#### Scenario: Record loan exit
- **WHEN** user logs item exit for loan with quantity, borrowing employee, department, timestamp, and notes
- **THEN** system decreases item quantity and logs exit with purpose marked as "loan" and borrower information

### Requirement: Audit trail for stock movements
The system SHALL maintain immutable audit trails for all stock movements.

#### Scenario: View movement history
- **WHEN** user views an item's history
- **THEN** system displays complete list of all entries and exits with user, timestamp, quantity, purpose, and notes in chronological order

#### Scenario: Filter movements by date range
- **WHEN** user applies date filter to item history
- **THEN** system displays only movements within specified date range

### Requirement: Signature requirement for exits
The system SHALL require electronic signature or verification for all item exits.

#### Scenario: Sign item exit
- **WHEN** user logs item exit and submits the form
- **THEN** system requires user to confirm with password, PIN, or biometric (based on configuration) before recording exit

### Requirement: Inventory reconciliation
The system SHALL support physical inventory counting and reconciliation.

#### Scenario: Record inventory count
- **WHEN** user performs physical count and enters counted quantity for each item
- **THEN** system compares counted quantity to system quantity and shows variance
