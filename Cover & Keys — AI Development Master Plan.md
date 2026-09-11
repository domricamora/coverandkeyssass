# COVER & KEYS
## AI-DRIVEN DEVELOPMENT MASTER PLAN

Version: 1.0  
Project: COVER & KEYS SaaS  
Target Platforms: Hotels, Resorts, B&Bs, Rooms, Vacation Rentals, Restaurants  
Primary Stack: PHP + Laravel + MySQL  
Hosting: Z.com VPS + cPanel  
Payment Gateway: PayMongo  
AI Development Agents: GLM, DeepSeek, Qwen, Claude Code or equivalent

---

# 1. PURPOSE

This document is the master implementation instruction for AI coding agents building COVER & KEYS.

COVER & KEYS is a multi-tenant SaaS platform combining:

1. Hospitality marketplace
2. Hotel/property management system
3. Online booking platform
4. Restaurant management
5. Restaurant ordering
6. Restaurant reservations
7. Food delivery
8. Payment processing
9. Customer CRM
10. Host management
11. SaaS subscription billing
12. Modular feature billing
13. Accounting
14. Inventory
15. Staff management
16. Housekeeping
17. POS
18. Marketing automation
19. Loyalty
20. Reviews
21. Messaging
22. Analytics
23. Super Admin management
24. API integrations
25. Future AI functionality

The system should combine the marketplace concepts of Airbnb/Booking.com with a complete hospitality SaaS operating system.

---

# 2. MOST IMPORTANT AI DEVELOPMENT RULE

DO NOT attempt to build the entire application in one generation.

The AI agent must implement the system incrementally.

Every phase must:

1. Inspect the existing project.
2. Understand existing architecture.
3. Create a written implementation plan.
4. Implement only the current phase.
5. Run tests.
6. Fix errors.
7. Run database migrations.
8. Verify functionality.
9. Update documentation.
10. Update AI_PROGRESS.md.
11. Commit changes if Git is available.
12. Only then proceed to the next phase.

NEVER destroy working functionality to implement a new feature.

NEVER replace the entire project unless explicitly instructed.

NEVER create duplicate systems when an existing implementation can be extended.

---

# 3. AI AGENT ROLE

You are the lead software architect, senior Laravel developer, database architect, QA engineer, DevOps engineer and security engineer for this project.

You must think like an enterprise SaaS engineering team.

Prioritize:

- Security
- Maintainability
- Scalability
- Modularity
- Testability
- Performance
- Data integrity
- Multi-tenancy
- API compatibility
- Mobile compatibility
- Future extensibility

Do not optimize for the shortest code.

Optimize for a production-ready system.

---

# 4. AI MODEL USAGE STRATEGY

The project may be developed using free or low-cost AI models including:

- GLM
- DeepSeek
- Qwen
- Other compatible coding agents

Different models may be used for different tasks.

Recommended division:

## GLM

Use primarily for:

- Project planning
- Architecture analysis
- UI generation
- CRUD development
- Laravel implementation
- Documentation
- Refactoring

## DeepSeek

Use primarily for:

- PHP development
- Laravel backend
- SQL
- Debugging
- Algorithm development
- API implementation
- Code review
- Test generation

## Qwen

Use primarily for:

- Frontend
- UI components
- JavaScript
- API integration
- Database work
- Refactoring
- Code analysis

## Stronger reasoning model

When available, use a stronger model for:

- Architecture decisions
- Security review
- Payment architecture
- Booking engine
- Multi-tenancy
- Database migrations
- Production deployment
- Final QA

---

# 5. AI AGENT HANDOFF SYSTEM

Different AI agents may work on the same project.

Therefore every agent must read these files before doing work:

```text
AI.md
AI_PROGRESS.md
ARCHITECTURE.md
DATABASE.md
API.md
SECURITY.md
DEPLOYMENT.md
CHANGELOG.md
```

If any file does not exist:

CREATE IT.

The agent must not assume another agent's work.

---

# 6. PROJECT DOCUMENTATION

The project must maintain:

```text
/docs/

AI.md
AI_PROGRESS.md
ARCHITECTURE.md
DATABASE.md
API.md
SECURITY.md
DEPLOYMENT.md
MODULES.md
TESTING.md
CHANGELOG.md
```

Additional module documentation:

```text
/docs/modules/

marketplace.md
property-management.md
booking.md
restaurant.md
ordering.md
delivery.md
payments.md
crm.md
accounting.md
inventory.md
staff.md
housekeeping.md
pos.md
marketing.md
loyalty.md
reviews.md
messaging.md
analytics.md
subscriptions.md
```

---

# 7. AI_PROGRESS.md

Every AI agent must update:

```text
AI_PROGRESS.md
```

after completing a task.

Format:

```text
# AI DEVELOPMENT PROGRESS

## Current Phase

Phase 01 - Foundation

## Completed

- Laravel installed
- Database configured
- Authentication implemented
- RBAC implemented

## In Progress

- Multi-tenancy

## Pending

- Marketplace
- Booking
- Restaurant
- Payments

## Known Issues

- None

## Last Agent

GLM

## Last Updated

YYYY-MM-DD

## Last Successful Test

php artisan test

Result: PASS
```

Never claim a feature is completed unless it has been tested.

---

# 8. TECHNOLOGY STACK

Use:

```text
PHP 8.3+
Laravel
MySQL 8+
Redis
Blade
Livewire
Alpine.js
Tailwind CSS
Laravel Queue
Laravel Scheduler
REST API
Composer
Git
```

Use Laravel's native features whenever practical.

Avoid unnecessary dependencies.

---

# 9. HOSTING ENVIRONMENT

Target hosting:

```text
Z.com VPS
cPanel
Apache
PHP-FPM
MySQL
Redis
SSL
Cron
```

The application must be deployable through cPanel.

Do not introduce infrastructure that requires Docker/Kubernetes unless it is optional.

The application must work on a conventional cPanel VPS.

---

# 10. ENVIRONMENT CONFIGURATION

Use `.env`.

Never hard-code:

- Database passwords
- API keys
- PayMongo credentials
- SMTP credentials
- Encryption keys
- Admin credentials
- Secret tokens

Example:

```text
APP_NAME="COVER & KEYS"
APP_ENV=production
APP_DEBUG=false
APP_URL=

DB_CONNECTION=mysql
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

REDIS_HOST=
REDIS_PASSWORD=
REDIS_PORT=

PAYMONGO_PUBLIC_KEY=
PAYMONGO_SECRET_KEY=
PAYMONGO_WEBHOOK_SECRET=
```

---

# 11. ARCHITECTURE

Use modular architecture.

Recommended structure:

```text
app/

Modules/

    Marketplace/

    PropertyManagement/

    Booking/

    Restaurant/

    Ordering/

    Delivery/

    Payments/

    CRM/

    Accounting/

    Inventory/

    Staff/

    Housekeeping/

    POS/

    Marketing/

    Loyalty/

    Reviews/

    Messaging/

    Analytics/

    Subscriptions/

    Admin/
```

Each module should contain its own:

```text
Models
Controllers
Services
Repositories
Requests
Policies
Events
Listeners
Jobs
Routes
Views
Tests
Migrations
```

Do not create a giant controller.

---

# 12. MULTI-TENANCY

COVER & KEYS is a multi-tenant SaaS.

Every tenant represents a business.

Example:

```text
Hotel A
Hotel B
Restaurant A
Restaurant B
Resort A
```

Use tenant-aware database architecture.

Core tables must contain:

```text
tenant_id
```

where applicable.

Never allow Tenant A to access Tenant B data.

Every query involving tenant-owned resources must enforce tenant isolation.

Implement:

```text
TenantContext
TenantMiddleware
TenantScope
TenantPolicy
```

---

# 13. USER ARCHITECTURE

Users:

```text
Customer
Host
Host Staff
Admin
Super Admin
```

Host roles:

```text
Owner
General Manager
Property Manager
Front Desk
Reservation Agent
Housekeeping
Maintenance
Restaurant Manager
Restaurant Staff
Kitchen Staff
Delivery Staff
Accountant
Marketing Manager
```

Use RBAC.

Permissions should be granular.

Examples:

```text
properties.view
properties.create
properties.update
properties.delete

bookings.view
bookings.create
bookings.cancel

payments.view
payments.refund

orders.view
orders.create
orders.update

staff.view
staff.create
staff.update
```

---

# 14. DEVELOPMENT PHASES

Development must follow these phases.

---

# PHASE 01 — PROJECT FOUNDATION

Build:

```text
Laravel
Environment configuration
Database connection
Authentication
User system
RBAC
Super Admin
Tenant system
Basic dashboard
Logging
Audit logs
Error handling
Base UI
```

Create:

```text
users
roles
permissions
role_permissions
user_roles
tenants
tenant_users
audit_logs
```

Acceptance criteria:

- Application loads
- User registration works
- Login works
- Logout works
- Super Admin works
- Tenant creation works
- Tenant isolation works
- Roles work
- Permissions work
- Tests pass

DO NOT continue until this phase passes.

---

# PHASE 02 — MODULE ENGINE

Build the modular SaaS engine.

Tables:

```text
modules
module_features
tenant_modules
module_plans
```

Features:

```text
Enable module
Disable module
Module pricing
Module description
Module limits
Trial period
Module permissions
Module dependencies
```

Example:

```text
Booking
requires:
Property Management
```

The system must automatically detect dependencies.

Super Admin can:

```text
Create module
Edit module
Activate module
Deactivate module
Set price
Set limits
Set dependencies
```

Acceptance:

A Super Admin can activate/deactivate modules for any tenant.

---

# PHASE 03 — MARKETPLACE

Build public marketplace.

Features:

```text
Homepage
Search
Locations
Property listings
Restaurant listings
Filters
Sorting
Map
Property detail
Restaurant detail
Favorites
Reviews
SEO URLs
```

Property categories:

```text
Hotel
Resort
B&B
Guesthouse
Apartment
Condo
Villa
Hostel
Private Room
Entire Property
```

Marketplace URLs:

```text
/hotels
/hotels/{location}
/property/{slug}
/restaurant/{slug}
/room/{slug}
```

Acceptance:

Customer can search, filter and view properties.

---

# PHASE 04 — PROPERTY MANAGEMENT

Build host property management.

Features:

```text
Property profile
Photos
Videos
Amenities
Policies
Location
Rooms
Room types
Room inventory
Rates
Availability
Property staff
```

Room structure:

```text
Property
    Room Type
        Room
```

Example:

```text
Hotel ABC
    Deluxe Room
        101
        102
        103
```

---

# PHASE 05 — BOOKING ENGINE

Build booking engine.

Features:

```text
Availability
Calendar
Reservations
Room inventory
Pricing
Discounts
Promotions
Cancellation
Guest information
Check-in
Check-out
No-show
Walk-in
Manual reservation
Multi-room booking
Group booking
```

Booking states:

```text
pending
held
confirmed
checked_in
checked_out
cancelled
no_show
refunded
completed
```

Critical requirement:

PREVENT DOUBLE BOOKING.

Use database transactions and inventory locking.

---

# PHASE 06 — CUSTOMER SYSTEM

Build customer portal.

Features:

```text
Profile
Bookings
Orders
Restaurant reservations
Favorites
Reviews
Messages
Invoices
Payments
Wallet
Loyalty
Coupons
Notifications
```

Customer dashboard:

```text
Upcoming bookings
Past bookings
Orders
Reservations
Rewards
```

---

# PHASE 07 — PAYMONGO

Implement PayMongo.

Features:

```text
Checkout
Payment Intent
Payment record
Webhook
Payment verification
Refund
Failed payment
Payment status
Transaction history
```

Payment lifecycle:

```text
Booking
↓
Payment initialization
↓
PayMongo
↓
Customer payment
↓
Webhook
↓
Verify payment
↓
Confirm booking
```

NEVER confirm a booking solely from a browser redirect.

Always verify payment server-side.

Implement idempotency.

A duplicate webhook must not:

- Duplicate payment
- Duplicate booking
- Duplicate order

---

# PHASE 08 — HOST WALLET AND COMMISSIONS

Build:

```text
wallets
wallet_transactions
commissions
payouts
```

Example:

```text
Booking = ₱5,000

Commission = 10%

Platform = ₱500
Host = ₱4,500
```

Super Admin controls commission rates.

Support:

```text
Global rate
Property rate
Restaurant rate
Promotional rate
```

---

# PHASE 09 — RESTAURANT MANAGEMENT

Build restaurant module.

Features:

```text
Restaurant profile
Opening hours
Menu
Categories
Menu items
Photos
Pricing
Modifiers
Add-ons
Availability
Tables
Dining areas
```

Menu example:

```text
Burger
₱250

Add:
Cheese +₱30
Bacon +₱50
Egg +₱25
```

---

# PHASE 10 — RESTAURANT RESERVATIONS

Build:

```text
Tables
Table capacity
Reservation calendar
Time slots
Guest count
Special requests
Reservation confirmation
Cancellation
No-show
```

Prevent table overbooking.

---

# PHASE 11 — ONLINE FOOD ORDERING

Build:

```text
Cart
Checkout
Menu
Order
Order items
Modifiers
Discounts
Taxes
Payment
Order status
```

Order states:

```text
pending
accepted
preparing
ready
out_for_delivery
delivered
completed
cancelled
refunded
```

---

# PHASE 12 — DELIVERY

Build:

```text
Delivery zones
Delivery radius
Delivery fees
Minimum order
Free delivery
Driver
Delivery assignment
Delivery status
Estimated time
```

Support:

```text
Restaurant delivery
Hotel room service
Pickup
Scheduled delivery
```

---

# PHASE 13 — HOTEL ROOM SERVICE

Allow hotel guests to order restaurant food directly to their room.

Flow:

```text
Guest
↓
Restaurant
↓
Menu
↓
Order
↓
Room Service
↓
Room
↓
Charge to Folio OR Pay
```

---

# PHASE 14 — GUEST FOLIO

Build:

```text
Room charges
Food
Room service
Laundry
Minibar
Activities
Transport
Other charges
Payments
Refunds
```

Example:

```text
Room       ₱3,500
Food         ₱850
Transport    ₱800
------------------
Total      ₱5,150
```

---

# PHASE 15 — HOUSEKEEPING

Build:

```text
Room status
Cleaning tasks
Assignments
Inspection
Maintenance requests
Staff
Notifications
```

Statuses:

```text
dirty
cleaning
clean
inspected
maintenance
out_of_order
```

---

# PHASE 16 — MAINTENANCE

Build:

```text
Maintenance tickets
Priority
Category
Assigned staff
Status
Cost
Attachments
Notes
```

---

# PHASE 17 — STAFF MANAGEMENT

Build:

```text
Employees
Departments
Positions
Schedules
Shifts
Attendance
Leave
Tasks
Permissions
```

---

# PHASE 18 — INVENTORY

Build inventory for both hotels and restaurants.

Features:

```text
Items
Categories
Units
Suppliers
Stock
Stock movement
Purchase orders
Transfers
Waste
Low-stock alerts
```

Restaurant inventory should optionally connect menu ingredients to inventory.

Example:

```text
Burger
requires:
1 bun
150g beef
1 cheese
```

Selling a burger decreases inventory.

---

# PHASE 19 — POS

Build optional POS module.

Features:

```text
Tables
Orders
Kitchen
Cashier
Discounts
Taxes
Payments
Receipts
Refunds
Shift management
Daily closing
```

---

# PHASE 20 — ACCOUNTING

Build:

```text
Revenue
Expenses
Invoices
Receivables
Payables
Taxes
Commissions
Payouts
Financial reports
```

Accounting should be modular.

---

# PHASE 21 — CRM

Build:

```text
Contacts
Customer profiles
Tags
Segments
Notes
Booking history
Order history
Spending
VIP status
Communication history
```

Segments:

```text
VIP
Frequent Guest
Inactive
High Spender
New Customer
Restaurant Customer
Hotel Customer
```

---

# PHASE 22 — MARKETING

Build:

```text
Email campaigns
SMS campaigns
Promotions
Coupons
Discount codes
Abandoned booking
Abandoned cart
Review requests
Post-stay campaigns
Customer reactivation
```

---

# PHASE 23 — LOYALTY

Build:

```text
Points
Rewards
Membership tiers
Referral rewards
Gift cards
Credits
```

Example:

```text
₱100 spending = 1 point
```

---

# PHASE 24 — REVIEWS

Support reviews for:

```text
Property
Room
Restaurant
Food
Host
Booking
```

Ratings:

```text
Overall
Cleanliness
Location
Service
Value
Food
Amenities
```

Hosts can reply.

Admin can moderate.

---

# PHASE 25 — MESSAGING

Build:

```text
Guest ↔ Host
Guest ↔ Restaurant
Guest ↔ Support
Staff ↔ Management
```

Features:

```text
Threads
Messages
Attachments
Read status
Notifications
```

---

# PHASE 26 — NOTIFICATIONS

Implement:

```text
Email
SMS
In-app
Push-ready architecture
```

Events:

```text
Booking confirmed
Payment received
Booking cancelled
Order received
Order ready
Delivery update
Review request
Subscription expiring
Payment failed
```

---

# PHASE 27 — SaaS BILLING

Build:

```text
Plans
Subscriptions
Subscription items
Invoices
Invoice items
Module pricing
Usage
Limits
Coupons
Trials
```

A tenant may purchase individual modules.

Example:

```text
Hotel ABC

PMS             ₱999
Booking         ₱999
Restaurant      ₱999
CRM             ₱499
Analytics       ₱499
---------------------
Total          ₱3,995
```

---

# PHASE 28 — SUPER ADMIN

Super Admin must control EVERYTHING.

Dashboard:

```text
Revenue
Bookings
Orders
Hosts
Customers
Properties
Restaurants
Subscriptions
Commissions
Payouts
```

Controls:

```text
Users
Hosts
Customers
Properties
Restaurants
Bookings
Orders
Reviews
Payments
Refunds
Payouts
Modules
Pricing
Subscriptions
CMS
Settings
Reports
Logs
```

---

# PHASE 29 — MARKETPLACE ADMINISTRATION

Super Admin controls:

```text
Featured properties
Featured restaurants
Sponsored listings
Search ranking
Categories
Locations
Reviews
Reported content
Host verification
Property verification
Restaurant verification
```

---

# PHASE 30 — SEO

Implement:

```text
SEO titles
Meta descriptions
Canonical URLs
Open Graph
Twitter cards
Schema.org
Breadcrumbs
Sitemap
Robots.txt
Structured data
```

Schemas:

```text
Hotel
Restaurant
LocalBusiness
Product
Offer
Review
AggregateRating
BreadcrumbList
FAQ
```

---

# PHASE 31 — API

Create:

```text
/api/v1/
```

Endpoints:

```text
/auth
/users
/properties
/rooms
/bookings
/restaurants
/menu
/orders
/delivery
/payments
/customers
/reviews
/messages
/notifications
/subscriptions
```

API authentication must be secure.

---

# PHASE 32 — SECURITY AUDIT

Perform a complete security review.

Check:

```text
Authentication
Authorization
RBAC
Tenant isolation
SQL injection
XSS
CSRF
File uploads
Rate limiting
API security
Session security
Password security
Webhook validation
Payment security
Audit logs
Secrets
Error handling
```

Run:

```text
composer audit
```

where supported.

---

# PHASE 33 — PERFORMANCE

Optimize:

```text
Database indexes
Eager loading
Caching
Redis
Queues
Images
CSS
JavaScript
API responses
Search
Pagination
```

Never load thousands of records into one page.

Use pagination.

---

# PHASE 34 — TESTING

Every module must have:

```text
Unit tests
Feature tests
Integration tests
Authorization tests
Tenant isolation tests
Payment tests
Booking tests
```

Critical tests:

```text
Cannot double-book room
Cannot access another tenant
Cannot bypass permission
Duplicate webhook is safe
Failed payment does not confirm booking
Refund updates booking correctly
Cancelled booking releases inventory
Restaurant order correctly updates status
Delivery updates order
```

---

# PHASE 35 — PRODUCTION DEPLOYMENT

Prepare cPanel deployment.

Requirements:

```text
PHP
MySQL
Composer
SSL
Redis
Cron
Queue worker
Storage permissions
Environment variables
```

Laravel scheduler:

```text
php artisan schedule:run
```

Queue processing:

```text
php artisan queue:work
```

Configure these through the appropriate cPanel/VPS process management.

Never expose:

```text
.env
storage
vendor
.git
```

publicly.

Public document root should point to:

```text
/public
```

---

# PHASE 36 — BACKUPS

Implement:

```text
Database backup
File backup
Configuration backup
Backup rotation
Restore procedure
```

Document restoration.

A backup is not considered valid until restoration has been tested.

---

# PHASE 37 — MONITORING

Implement logging for:

```text
Errors
Payments
Bookings
Orders
Webhooks
Login attempts
Admin actions
Subscription changes
Refunds
Payouts
```

---

# PHASE 38 — FINAL SYSTEM AUDIT

Before launch, AI must verify:

```text
Authentication
Marketplace
Properties
Rooms
Availability
Booking
Payments
Restaurants
Menu
Orders
Delivery
Reservations
CRM
Staff
Inventory
Accounting
POS
Marketing
Reviews
Messaging
Subscriptions
Modules
Super Admin
SEO
Security
Performance
Backups
Deployment
```

Anything not working must remain marked:

```text
INCOMPLETE
```

Never mark unfinished functionality as completed.

---

# 15. DATABASE DESIGN RULES

Use:

```text
BIGINT UNSIGNED
UUID where appropriate
foreign keys
indexes
timestamps
soft deletes where appropriate
```

Every important table should have:

```text
id
created_at
updated_at
```

Tenant-owned tables should normally have:

```text
tenant_id
```

Use proper indexes.

Examples:

```text
INDEX tenant_id
INDEX status
INDEX created_at
INDEX booking dates
INDEX slug
INDEX email
```

Do not use unindexed large-table searches.

---

# 16. BOOKING DATABASE RULES

Availability must never be calculated only from frontend JavaScript.

The backend is authoritative.

When creating a reservation:

```text
BEGIN TRANSACTION

LOCK inventory

CHECK availability

CREATE reservation

CREATE reservation items

CREATE payment intent

COMMIT
```

If anything fails:

```text
ROLLBACK
```

---

# 17. PAYMENT RULES

Never store raw card information.

PayMongo handles payment credentials.

Store only appropriate gateway references.

Always verify:

```text
Amount
Currency
Payment status
Reference
Booking/order association
Webhook authenticity
```

---

# 18. UI REQUIREMENTS

The interface should be modern SaaS.

Use:

```text
Responsive design
Mobile-first
Accessible components
Consistent spacing
Reusable components
Dark/light compatible architecture
```

Marketplace:

```text
Clean
Visual
Image-heavy
Trust-oriented
Conversion focused
```

Host dashboard:

```text
Dense
Efficient
Data-oriented
Professional
```

Super Admin:

```text
Enterprise dashboard
```

---

# 19. RESPONSIVE REQUIREMENTS

Support:

```text
Mobile
Tablet
Laptop
Desktop
Large monitor
```

Do not create separate mobile and desktop codebases.

Use responsive components.

---

# 20. AI CODING PROCEDURE

Every task must follow this sequence.

## STEP 1

Read:

```text
AI.md
AI_PROGRESS.md
ARCHITECTURE.md
DATABASE.md
```

## STEP 2

Inspect project.

Do not assume files exist.

## STEP 3

Identify:

```text
Existing functionality
Dependencies
Database state
Routes
Models
Controllers
Views
Tests
```

## STEP 4

Create a task plan.

Example:

```text
TASK:
Implement property management.

Plan:
1. Create migrations
2. Create models
3. Add relationships
4. Create policies
5. Create services
6. Create controllers
7. Create routes
8. Create views
9. Create tests
10. Run migrations
11. Run tests
12. Update documentation
```

## STEP 5

Implement.

## STEP 6

Run tests.

## STEP 7

Fix errors.

## STEP 8

Check security.

## STEP 9

Check database integrity.

## STEP 10

Update:

```text
AI_PROGRESS.md
CHANGELOG.md
```

## STEP 11

Commit.

## STEP 12

Move to next task.

---

# 21. NEVER DO THESE THINGS

AI agents must NEVER:

- Delete working code without reason.
- Rewrite the entire project unnecessarily.
- Disable authentication to make something work.
- Disable CSRF.
- Disable authorization.
- Hard-code passwords.
- Hard-code API keys.
- Store card information.
- Trust payment redirects.
- Ignore database errors.
- Ignore failed tests.
- Mark untested functionality as complete.
- Create duplicate tables.
- Create duplicate controllers.
- Create duplicate routes.
- Modify production configuration without confirmation.
- Delete production data.
- Run destructive migrations without warning.
- Drop tables casually.
- Disable tenant isolation.
- Bypass RBAC.
- Commit `.env`.
- Expose secrets.

---

# 22. FREE AI AGENT WORKFLOW

A practical development workflow:

```text
              AI.md
                │
                ▼
        Architecture / Plan
                │
       ┌────────┴────────┐
       │                 │
      GLM            DeepSeek
       │                 │
   UI/CRUD          Backend/API
       │                 │
       └────────┬────────┘
                │
               Qwen
                │
        Frontend / Review
                │
                ▼
              Tests
                │
                ▼
             Security
                │
                ▼
             Commit
                │
                ▼
        AI_PROGRESS.md
```

Never allow two agents to modify the same critical files simultaneously.

---

# 23. MODEL HANDOFF PROMPT

When handing work from one AI agent to another, provide:

```text
You are continuing development of COVER & KEYS.

Read:

AI.md
AI_PROGRESS.md
ARCHITECTURE.md
DATABASE.md
API.md
SECURITY.md
CHANGELOG.md

Do not start coding immediately.

First inspect the existing implementation.

Determine:

1. What has already been completed?
2. What is currently being developed?
3. What files were modified?
4. What database migrations exist?
5. What tests exist?
6. What tests currently fail?
7. What is the next approved task?

Do not rewrite existing functionality.

Continue only from the current project state.

Before finishing:
- Run tests
- Fix errors
- Check security
- Update documentation
- Update AI_PROGRESS.md
- Update CHANGELOG.md
```

---

# 24. TASK PROMPT

For each individual task use:

```text
TASK:

Implement:
[FEATURE]

OBJECTIVE:

[WHAT THE FEATURE MUST DO]

REQUIREMENTS:

[List requirements]

CONSTRAINTS:

- Laravel
- PHP 8.3+
- MySQL 8+
- Multi-tenant
- RBAC
- Secure
- Modular
- cPanel compatible

PROCESS:

1. Inspect existing code.
2. Inspect database.
3. Inspect related modules.
4. Create implementation plan.
5. Implement database changes.
6. Implement backend.
7. Implement frontend.
8. Implement permissions.
9. Implement tests.
10. Run migrations.
11. Run tests.
12. Fix all errors.
13. Perform security check.
14. Update documentation.
15. Update AI_PROGRESS.md.

Do not implement unrelated features.
Do not remove working functionality.
Do not mark the task complete until tests pass.
```

---

# 25. DEFINITION OF DONE

A feature is NOT complete until:

```text
[ ] Database implemented
[ ] Backend implemented
[ ] Frontend implemented
[ ] Permissions implemented
[ ] Validation implemented
[ ] Error handling implemented
[ ] Tenant isolation verified
[ ] Security checked
[ ] Unit tests created
[ ] Feature tests created
[ ] Tests pass
[ ] Documentation updated
[ ] AI_PROGRESS.md updated
[ ] CHANGELOG.md updated
```

---

# 26. MVP RELEASE

The first public MVP should include:

```text
Authentication
Multi-tenancy
RBAC

Marketplace
Properties
Rooms
Search
Listings

Availability
Booking
Customer accounts

PayMongo
Payments
Refunds

Restaurants
Menus
Restaurant reservations
Online ordering

Host dashboard
Super Admin

SaaS modules
Module billing

Reviews
Messaging
Notifications
```

Do not delay MVP waiting for advanced:

```text
AI
POS
Accounting
Channel Manager
Advanced marketing automation
```

Those can be enabled as later modules.

---

# 27. VERSION 2

After MVP:

```text
POS
Inventory
Accounting
Staff
Housekeeping
Delivery
Loyalty
Marketing
Advanced CRM
Analytics
```

---

# 28. VERSION 3

Advanced platform:

```text
Channel Manager
Airbnb integration
Booking.com integration
Expedia integration
Mobile apps
AI concierge
AI customer support
AI pricing
AI marketing
Revenue management
Advanced reporting
```

---

# 29. FINAL PRODUCT ARCHITECTURE

The final system should operate as:

```text
                         COVER & KEYS
                              │
             ┌────────────────┼────────────────┐
             │                │                │
        MARKETPLACE          HOST             ADMIN
             │                │                │
          Guests          Operations        Platform
             │                │                │
       ┌─────┴─────┐    ┌─────┴─────┐    ┌────┴─────┐
       │           │    │           │    │          │
     Hotels   Restaurants PMS       POS  Modules   Billing
       │           │    │           │    │          │
     Rooms       Menu Booking    Inventory Pricing Commissions
       │           │    │           │    │          │
       └───────────┴────┴───────────┴────┴──────────┘
                              │
                           PayMongo
                              │
                           Payments
```

---

# 30. FINAL AI INSTRUCTION

You are not building a prototype.

You are building a production-oriented, modular, multi-tenant hospitality SaaS platform.

Always prioritize:

1. Data integrity
2. Security
3. Tenant isolation
4. Payment correctness
5. Booking correctness
6. Modularity
7. Maintainability
8. Testing
9. Performance
10. Scalability

Build one phase at a time.

Never skip testing.

Never assume functionality works.

Verify it.

Document it.

Then continue.

END OF AI.md