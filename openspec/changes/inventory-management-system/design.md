## Context

The organization needs a centralized, Docker-based inventory management system to replace manual stock tracking. Currently, inventory data is fragmented and lacks audit trails. The system must integrate with GLPI API for location/asset data and support multi-user operations with role-based access control. Key stakeholders include warehouse staff (for daily operations), managers (for reporting), and procurement (for purchasing recommendations).

## Goals / Non-Goals

**Goals:**
- Provide centralized, real-time inventory tracking with complete audit trails
- Enable automated email notifications when stock reaches minimum levels
- Generate consumption reports and purchasing recommendations
- Forecast stock exhaustion based on consumption patterns
- Integrate with GLPI for location and asset data
- Support Docker containerization for deployment
- Implement security best practices including authentication, authorization, and signature requirements

**Non-Goals:**
- POS/sales integration (not required for this phase)
- Multi-warehouse/multi-location support beyond GLPI location assignment
- Barcode/RFID scanning (can be added later)
- Mobile app (web interface primary)
- Integration with other ERP systems beyond GLPI

## Decisions

### Decision 1: Technology Stack
**Choice**: Python backend (FastAPI), PostgreSQL database, React frontend, Docker containerization

**Rationale**: 
- FastAPI provides excellent async support for background tasks (notifications, forecasting)
- PostgreSQL offers strong ACID compliance for critical inventory transactions
- React allows responsive UI for warehouse staff
- Docker enables consistent deployment across environments

**Alternatives Considered**:
- Node.js/Express: Good choice but Python has better ML libraries for forecasting
- MySQL: Adequate but PostgreSQL's JSON support valuable for audit trails
- Vue.js: Equally valid, chose React for larger community and ecosystem

### Decision 2: Audit Trail Architecture
**Choice**: Immutable audit log table with JSON payload storage, separate from entity tables

**Rationale**:
- Provides complete history without modifying historical records
- JSON payloads allow storing full context of each movement
- Supports compliance and forensics requirements
- Separates data modification concerns from audit concerns

**Alternatives Considered**:
- Event sourcing: Powerful but adds complexity not needed for this phase
- Trigger-based audits: Brittle and harder to maintain

### Decision 3: Notification System
**Choice**: Async task queue (Celery) with scheduled checks, email backend

**Rationale**:
- Decouples notifications from request-response cycle
- Handles batching and rate limiting naturally
- Easy to add SMS/Slack notifications later
- Celery well-proven for background tasks in Python

**Alternatives Considered**:
- Polling in background thread: Less reliable and harder to scale
- WebSockets for real-time: Overkill for notification requirements

### Decision 4: Forecasting Approach
**Choice**: Time-series analysis using exponential smoothing with seasonal adjustment

**Rationale**:
- Simpler than full ML models but handles seasonal patterns
- Sufficient for inventory forecasting use case
- Can be computed incrementally without ML infrastructure
- Easy to tune and explain to stakeholders

**Alternatives Considered**:
- ARIMA models: Overkill, harder to implement and explain
- Simple linear regression: Ignores seasonal patterns

### Decision 5: GLPI Integration
**Choice**: Scheduled sync (24-hour interval) with local cache and conflict detection

**Rationale**:
- Reduces API load on GLPI
- Inventory system remains functional if GLPI is unavailable
- Conflicts detected automatically and escalated to admin
- User doesn't need real-time GLPI connection

**Alternatives Considered**:
- Real-time API calls: Performance impact and dependency risk
- Manual mapping: Burden on users

### Decision 6: Data Validation and Signature
**Choice**: Role-based signature requirement (password/PIN for exits) with configurable strict mode

**Rationale**:
- Simple implementation for security requirement
- Can be extended to biometric/hardware tokens later
- Audit trail captures who approved each movement
- Role-based allows different strictness per user level

**Alternatives Considered**:
- Always require signature: Too strict for routine operations
- No signature requirement: Inadequate for compliance

## Risks / Trade-offs

**Risk 1: GLPI API Availability** → Mitigation: Cache locations locally, continue operation with cached data, alert if sync fails

**Risk 2: Forecast Accuracy with Sporadic Consumption** → Mitigation: System flags low-confidence forecasts, recommends manual review for items with volatile consumption

**Risk 3: Audit Trail Storage Growth** → Mitigation: Implement archival strategy (move old records to archive table after 2 years), partition by date

**Risk 4: Email Deliverability** → Mitigation: Track delivery status, retry failed sends, provide admin dashboard for notification status

**Risk 5: Race Conditions in Stock Updates** → Mitigation: Use database transactions and row-level locking for all inventory updates

**Risk 6: User Training for New System** → Mitigation: Comprehensive documentation, in-app guidance, phased rollout with pilot group

## Migration Plan

**Phase 1 - Setup (Week 1)**:
- Deploy Docker containers for backend, database, and frontend
- Configure GLPI API credentials and initial sync
- Set up email service
- Configure admin user and roles

**Phase 2 - Data Migration (Week 2)**:
- Perform physical inventory count
- Import current inventory data into system
- Reconcile any discrepancies
- Export baseline data for audit

**Phase 3 - Pilot (Weeks 3-4)**:
- Train pilot group (5-10 warehouse staff)
- Run parallel with existing system
- Collect feedback and resolve issues

**Phase 4 - Rollout (Week 5+)**:
- Full staff training
- Transition to new system as primary inventory tracker
- Sunset legacy tracking method
- Monitor for issues and provide support

**Rollback Strategy**: Keep historical data exported and accessible; can revert to manual tracking if critical issues arise (system designed for this possibility)

## Open Questions

1. **Forecasting Tuning**: Should forecast weights be per-item or global? Recommendation: Start global, allow per-item customization based on pilot feedback
2. **Email Recipients**: Should recipients be per-item or global? Recommendation: Both - global defaults + per-item overrides
3. **Signature Method**: PIN, password, or both? Recommendation: Password initially, PIN optional in future versions
4. **Loan Return Handling**: Should partial returns be supported? Recommendation: Yes - update loan quantity on partial return
5. **Integration Timeline**: Can GLPI sync wait for Phase 2, or needed in Phase 1? Recommendation: Implement in Phase 1 but can initially auto-create locations if GLPI unavailable
