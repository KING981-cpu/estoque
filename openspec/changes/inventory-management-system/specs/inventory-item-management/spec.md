## ADDED Requirements

### Requirement: Create and manage inventory items
The system SHALL allow users to create and manage inventory items with unique identifiers, names, descriptions, quantities, minimum thresholds, and desired quantities.

#### Scenario: Create new inventory item
- **WHEN** user submits a new item with name, description, minimum quantity, and desired quantity
- **THEN** system creates the item with status "active" and initial quantity of 0

#### Scenario: Update item thresholds
- **WHEN** user updates minimum or desired quantity for an existing item
- **THEN** system saves the new thresholds and triggers notification if current quantity falls below new minimum

### Requirement: Track current inventory quantity
The system SHALL maintain accurate current quantity for each item, updated when items are received or distributed.

#### Scenario: View current quantity
- **WHEN** user views an inventory item
- **THEN** system displays current quantity, minimum quantity, desired quantity, and status (low stock/normal/overstocked)

### Requirement: Item status management
The system SHALL support item activation and deactivation with proper disposition tracking.

#### Scenario: Deactivate active item with zero quantity
- **WHEN** user deactivates an item that has no remaining stock
- **THEN** system changes item status to "inactive" without requiring disposition notes

#### Scenario: Deactivate active item with remaining quantity
- **WHEN** user attempts to deactivate an item with remaining stock
- **THEN** system requires user to specify disposition reason (consumed/donated/destroyed/transferred) and target location/department

#### Scenario: Reactivate deactivated item
- **WHEN** user attempts to reactivate a deactivated item
- **THEN** system restores the item to active status if no conflicts exist

### Requirement: Search and filter items
The system SHALL provide search and filtering capabilities for inventory items.

#### Scenario: Search items by name
- **WHEN** user enters search text in item search field
- **THEN** system returns all items matching the search term in name or description

#### Scenario: Filter by status
- **WHEN** user applies status filter (active/inactive)
- **THEN** system displays only items matching the selected status
